<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\UploadDocumentRequest;
use App\Models\AiDocument;
use App\Services\AI\Rag\DocumentIngestionService;
use App\Services\AI\Rag\EmbeddingService;
use App\Services\AI\Rag\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentIngestionService $ingestionService,
        private readonly EmbeddingService $embeddingService,
        private readonly VectorSearchService $vectorSearchService,
    ) {}

    /**
     * Upload and ingest a document.
     * POST /api/ai/documents
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        try {
            $document = $this->ingestionService->ingest(
                file: $request->file('file'),
                title: $request->input('title'),
                meta: $request->input('meta', []),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم رفع وفهرسة المستند بنجاح.',
                'data' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'source' => $document->source,
                    'mime_type' => $document->mime_type,
                    'chunks_count' => $document->chunkCount(),
                    'total_tokens' => $document->totalTokens(),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع المستند: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all documents (paginated).
     * GET /api/ai/documents
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $documents = AiDocument::withCount('chunks')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Show a document with chunk details.
     * GET /api/ai/documents/{id}
     */
    public function show(int $id): JsonResponse
    {
        $document = AiDocument::with(['chunks' => fn ($q) => $q->select(
            'id', 'document_id', 'chunk_index', 'tokens', 'content_hash', 'created_at'
        )->orderBy('chunk_index')])->find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
                'source' => $document->source,
                'mime_type' => $document->mime_type,
                'meta' => $document->meta_json,
                'chunks_count' => $document->chunks->count(),
                'total_tokens' => $document->chunks->sum('tokens'),
                'chunks' => $document->chunks,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ],
        ]);
    }

    /**
     * Delete a document and all its chunks.
     * DELETE /api/ai/documents/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $document = AiDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        $this->ingestionService->delete($document);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستند بنجاح.',
        ]);
    }

    /**
     * Re-index (re-chunk and re-embed) a document.
     * POST /api/ai/documents/{id}/reindex
     */
    public function reindex(int $id): JsonResponse
    {
        $document = AiDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        try {
            $this->ingestionService->reindex($document);

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة فهرسة المستند بنجاح.',
                'data' => [
                    'id' => $document->id,
                    'chunks_count' => $document->chunkCount(),
                    'total_tokens' => $document->totalTokens(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت إعادة الفهرسة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Semantic search across all documents.
     * POST /api/ai/documents/search
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:1000',
            'limit' => 'nullable|integer|min:1|max:20',
            'document_id' => 'nullable|integer|exists:ai_documents,id',
        ]);

        $query = $request->input('query');
        $limit = $request->integer('limit', 5);
        $documentId = $request->input('document_id');

        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $minSimilarity = (float) config('ai_assistant.rag.min_similarity', 0.7);

            $results = $this->vectorSearchService->search(
                queryEmbedding: $queryEmbedding,
                limit: $limit,
                minSimilarity: $minSimilarity,
                documentId: $documentId,
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => $results->map(fn ($chunk) => [
                        'content' => $chunk->content_text,
                        'similarity' => $chunk->similarity,
                        'document_id' => $chunk->document_id,
                        'document_title' => $chunk->document?->title,
                        'chunk_index' => $chunk->chunk_index,
                        'tokens' => $chunk->tokens,
                    ]),
                    'total' => $results->count(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل البحث الدلالي: ' . $e->getMessage(),
            ], 500);
        }
    }
}
