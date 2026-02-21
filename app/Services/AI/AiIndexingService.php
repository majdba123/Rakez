<?php

namespace App\Services\AI;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Services\AI\VectorStore\VectorStoreInterface;
use Illuminate\Support\Str;

class AiIndexingService
{
    public function __construct(
        private readonly OpenAIEmbeddingClient $embeddingClient,
        private readonly VectorStoreInterface $vectorStore
    ) {}

    /**
     * Approximate token count (4 chars ~ 1 token).
     */
    public function approximateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Chunk text with overlap. Returns array of { content, tokens, content_hash }.
     *
     * @return array<int, array{content: string, tokens: int, content_hash: string}>
     */
    public function chunkText(string $text): array
    {
        $minTokens = config('ai_assistant.v2.chunking.chunk_tokens_min', 400);
        $maxTokens = config('ai_assistant.v2.chunking.chunk_tokens_max', 800);
        $overlapTokens = config('ai_assistant.v2.chunking.chunk_overlap_tokens', 80);

        $chunks = [];
        $start = 0;
        $len = mb_strlen($text);
        $approxCharsPerToken = 4;
        $minChars = $minTokens * $approxCharsPerToken;
        $maxChars = $maxTokens * $approxCharsPerToken;
        $overlapChars = $overlapTokens * $approxCharsPerToken;

        while ($start < $len) {
            $slice = mb_substr($text, $start, $maxChars);
            $sliceLen = mb_strlen($slice);
            $tokens = $this->approximateTokens($slice);
            $contentHash = hash('sha256', $slice);
            $chunks[] = [
                'content' => $slice,
                'tokens' => $tokens,
                'content_hash' => $contentHash,
            ];
            $advance = $sliceLen - $overlapChars;
            if ($advance <= 0) {
                break;
            }
            $start += $advance;
        }

        return $chunks;
    }

    /**
     * Redact secrets from text before indexing or sending to LLM.
     */
    public function redactSecrets(string $text): string
    {
        $patterns = [
            '/\b(?:sk-[a-zA-Z0-9]{20,})\b/',
            '/\b(?:password|passwd|secret|api_key|apikey)\s*[:=]\s*[\'"][^\'"]+[\'"]/i',
        ];
        foreach ($patterns as $p) {
            $text = preg_replace($p, '[REDACTED]', $text);
        }

        return $text;
    }

    /**
     * Index a record summary (from observers/jobs).
     */
    public function indexRecordSummary(string $module, string $recordId, string $title, string $content, array $meta): void
    {
        $content = $this->redactSecrets($content);
        $contentHash = hash('sha256', $content);
        $sourceUri = "{$module}/{$recordId}";

        $doc = AiDocument::query()->updateOrCreate(
            [
                'type' => 'record_summary',
                'source_uri' => $sourceUri,
            ],
            [
                'title' => $title,
                'meta_json' => array_merge($meta, ['module' => $module, 'record_id' => $recordId]),
                'content_hash' => $contentHash,
            ]
        );

        $chunks = $this->chunkText($content);
        $existingChunks = $doc->chunks()->orderBy('chunk_index')->get();
        $toEmbed = [];

        foreach ($chunks as $i => $chunkData) {
            $existing = $existingChunks->get($i);
            $chunkHash = $chunkData['content_hash'];
            if ($existing && ($existing->content_hash === $chunkHash)) {
                continue;
            }
            $chunk = AiChunk::updateOrCreate(
                [
                    'document_id' => $doc->id,
                    'chunk_index' => $i,
                ],
                [
                    'content_text' => $chunkData['content'],
                    'tokens' => $chunkData['tokens'],
                    'content_hash' => $chunkHash,
                    'meta_json' => array_merge($meta, [
                        'module' => $module,
                        'record_id' => $recordId,
                        'title' => $title,
                        'source_uri' => $sourceUri,
                    ]),
                ]
            );
            $toEmbed[] = [
                'chunk_id' => $chunk->id,
                'document_id' => $doc->id,
                'content' => $chunkData['content'],
                'meta' => $chunk->meta_json ?? [],
                'embedding' => [],
            ];
        }

        if (empty($toEmbed)) {
            return;
        }

        $texts = array_column($toEmbed, 'content');
        $embeddings = $this->embeddingClient->embedMany($texts);
        foreach ($toEmbed as $idx => $item) {
            $item['embedding'] = $embeddings[$idx] ?? [];
            $toEmbed[$idx] = $item;
        }
        $this->vectorStore->upsertChunks($toEmbed);
    }

    /**
     * Index a document (file ingestion).
     */
    public function indexDocument(int $documentId, string $content, array $meta): void
    {
        $content = $this->redactSecrets($content);
        $doc = AiDocument::find($documentId);
        if (! $doc) {
            return;
        }
        $doc->update(['content_hash' => hash('sha256', $content), 'meta_json' => array_merge($doc->meta_json ?? [], $meta)]);

        $chunks = $this->chunkText($content);
        $toEmbed = [];
        foreach ($chunks as $i => $chunkData) {
            $chunk = AiChunk::updateOrCreate(
                [
                    'document_id' => $documentId,
                    'chunk_index' => $i,
                ],
                [
                    'content_text' => $chunkData['content'],
                    'tokens' => $chunkData['tokens'],
                    'content_hash' => $chunkData['content_hash'],
                    'meta_json' => array_merge($meta, ['title' => $doc->title ?? 'Document']),
                ]
            );
            $toEmbed[] = [
                'chunk_id' => $chunk->id,
                'document_id' => $documentId,
                'content' => $chunkData['content'],
                'meta' => $chunk->meta_json ?? [],
                'embedding' => [],
            ];
        }
        $texts = array_column($toEmbed, 'content');
        $embeddings = $this->embeddingClient->embedMany($texts);
        foreach ($toEmbed as $idx => $item) {
            $item['embedding'] = $embeddings[$idx] ?? [];
            $toEmbed[$idx] = $item;
        }
        $this->vectorStore->upsertChunks($toEmbed);
    }

    /**
     * Mark a record summary as deleted (soft). Keeps document/chunks but sets is_deleted so RAG filters them out.
     */
    public function markRecordDeleted(string $module, string $recordId): void
    {
        $sourceUri = "{$module}/{$recordId}";
        $doc = AiDocument::query()
            ->where('type', 'record_summary')
            ->where('source_uri', $sourceUri)
            ->first();
        if (! $doc) {
            return;
        }
        $docMeta = $doc->meta_json ?? [];
        $docMeta['is_deleted'] = true;
        $doc->update(['meta_json' => $docMeta]);

        foreach ($doc->chunks as $chunk) {
            $meta = $chunk->meta_json ?? [];
            $meta['is_deleted'] = true;
            $chunk->update(['meta_json' => $meta]);
        }
    }
}
