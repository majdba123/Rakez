<?php

namespace App\Services\AI\Rag;

use App\Models\AiChunk;
use App\Models\AiDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentIngestionService
{
    public function __construct(
        private readonly TextChunkerService $chunker,
        private readonly EmbeddingService $embedding,
        private readonly DocumentAnalyzerService $analyzer,
    ) {}

    /**
     * Ingest a file: extract text, chunk, embed, and store.
     */
    public function ingest(UploadedFile $file, ?string $title = null, array $meta = []): AiDocument
    {
        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
        $filePath = $file->getRealPath();
        $title = $title ?: $file->getClientOriginalName();

        $analysis = $this->analyzer->analyze($filePath, $mimeType);

        if ($analysis['text'] === '') {
            Log::warning('DocumentIngestion: no text extracted from file', [
                'title' => $title,
                'mime_type' => $mimeType,
            ]);
        }

        return $this->ingestText(
            text: $analysis['text'],
            title: $title,
            source: $file->getClientOriginalName(),
            meta: array_merge($meta, [
                'mime_type' => $mimeType,
                'page_count' => $analysis['page_count'],
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]),
        );
    }

    /**
     * Ingest raw text directly: chunk, embed, and store.
     */
    public function ingestText(
        string $text,
        string $title,
        string $source = 'manual',
        array $meta = [],
    ): AiDocument {
        return DB::transaction(function () use ($text, $title, $source, $meta) {
            // 1. Create the document record
            $document = AiDocument::create([
                'title' => $title,
                'source' => $source,
                'mime_type' => $meta['mime_type'] ?? 'text/plain',
                'meta_json' => $meta,
            ]);

            // 2. Chunk the text
            $maxTokens = (int) config('ai_assistant.rag.chunk_max_tokens', 500);
            $overlap = (int) config('ai_assistant.rag.chunk_overlap_tokens', 50);
            $chunks = $this->chunker->chunk($text, $maxTokens, $overlap);

            if (empty($chunks)) {
                Log::info('DocumentIngestion: no chunks produced', ['document_id' => $document->id]);

                return $document;
            }

            // 3. Generate embeddings in batch
            $chunkTexts = array_map(fn ($c) => $c['text'], $chunks);
            $embeddings = $this->embedding->embedBatch($chunkTexts);

            // 4. Bulk insert chunks
            $now = now();
            $records = [];
            foreach ($chunks as $i => $chunk) {
                $records[] = [
                    'document_id' => $document->id,
                    'chunk_index' => $chunk['index'],
                    'content_text' => $chunk['text'],
                    'meta_json' => json_encode(['source' => $source, 'title' => $title]),
                    'tokens' => $chunk['tokens'],
                    'content_hash' => hash('sha256', $chunk['text']),
                    'embedding_json' => json_encode($embeddings[$i] ?? []),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert in batches of 100 to avoid query size limits
            foreach (array_chunk($records, 100) as $batch) {
                AiChunk::insert($batch);
            }

            Log::info('DocumentIngestion: completed', [
                'document_id' => $document->id,
                'title' => $title,
                'chunks' => count($chunks),
                'total_tokens' => array_sum(array_column($chunks, 'tokens')),
            ]);

            return $document;
        });
    }

    /**
     * Re-index an existing document: delete old chunks, re-chunk, re-embed.
     */
    public function reindex(AiDocument $document): void
    {
        // Collect all chunk texts
        $fullText = $document->chunks()
            ->orderBy('chunk_index')
            ->pluck('content_text')
            ->implode("\n\n");

        DB::transaction(function () use ($document, $fullText) {
            // Delete old chunks
            $document->chunks()->delete();

            // Re-chunk and re-embed
            $maxTokens = (int) config('ai_assistant.rag.chunk_max_tokens', 500);
            $overlap = (int) config('ai_assistant.rag.chunk_overlap_tokens', 50);
            $chunks = $this->chunker->chunk($fullText, $maxTokens, $overlap);

            if (empty($chunks)) {
                return;
            }

            $chunkTexts = array_map(fn ($c) => $c['text'], $chunks);
            $embeddings = $this->embedding->embedBatch($chunkTexts);

            $now = now();
            $records = [];
            foreach ($chunks as $i => $chunk) {
                $records[] = [
                    'document_id' => $document->id,
                    'chunk_index' => $chunk['index'],
                    'content_text' => $chunk['text'],
                    'meta_json' => json_encode(['source' => $document->source, 'title' => $document->title]),
                    'tokens' => $chunk['tokens'],
                    'content_hash' => hash('sha256', $chunk['text']),
                    'embedding_json' => json_encode($embeddings[$i] ?? []),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($records, 100) as $batch) {
                AiChunk::insert($batch);
            }
        });

        Log::info('DocumentIngestion: re-indexed', ['document_id' => $document->id]);
    }

    /**
     * Delete a document and all its chunks.
     */
    public function delete(AiDocument $document): void
    {
        $document->delete(); // cascade via foreign key
    }
}
