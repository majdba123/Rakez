<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\UploadDocumentRequest;
use App\Models\AiDocument;
use App\Models\User;
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
            $user = $request->user();
            $meta = array_merge($request->input('meta', []), [
                'uploaded_by_user_id' => $user->id,
                'correlation_id' => $request->header('X-Request-Id')
                    ?? $request->header('X-Request-ID')
                    ?? $request->header('X-Correlation-Id')
                    ?? $request->header('X-Correlation-ID'),
            ]);

            $document = $this->ingestionService->ingest(
                file: $request->file('file'),
                title: $request->input('title'),
                meta: $meta,
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
        $user = $request->user();
        $perPage = $request->integer('per_page', 15);

        $query = AiDocument::withCount('chunks')->orderByDesc('created_at');

        if (! $this->isRagAdmin($user)) {
            $query->where('uploaded_by_user_id', $user->id);
        }

        $documents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Show a document with chunk details.
     * GET /api/ai/documents/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $document = AiDocument::with(['chunks' => fn ($q) => $q->select(
            'id', 'document_id', 'chunk_index', 'tokens', 'content_hash', 'content_text', 'created_at'
        )->orderBy('chunk_index')])->find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        if (! $this->canAccessDocument($user, $document)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بعرض هذا المستند.',
            ], 403);
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
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $document = AiDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        if (! $this->canAccessDocument($user, $document)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بحذف هذا المستند.',
            ], 403);
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
    public function reindex(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $document = AiDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'المستند غير موجود.',
            ], 404);
        }

        if (! $this->canAccessDocument($user, $document)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بإعادة فهرسة هذا المستند.',
            ], 403);
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
     * Semantic search across documents (snippet-only payload).
     * POST /api/ai/documents/search
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'query' => 'required|string|max:1000',
            'limit' => 'nullable|integer|min:1|max:20',
            'document_id' => 'nullable|integer|exists:ai_documents,id',
        ]);

        $query = $request->input('query');
        $limit = $request->integer('limit', 5);
        $documentId = $request->input('document_id');

        if ($documentId !== null) {
            $doc = AiDocument::find($documentId);
            if (! $doc || ! $this->canAccessDocument($user, $doc)) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستند غير موجود أو غير مصرح بالوصول.',
                ], 403);
            }
        }

        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $minSimilarity = (float) config('ai_assistant.rag.min_similarity', 0.7);

            $allowAll = $this->isRagAdmin($user);
            $results = $this->vectorSearchService->search(
                queryEmbedding: $queryEmbedding,
                limit: $limit,
                minSimilarity: $minSimilarity,
                documentId: $documentId,
                ownerUserId: $allowAll ? null : $user->id,
                allowAllDocuments: $allowAll,
            );

            $ids = $results->pluck('document_id')->unique()->filter()->all();
            $titles = $ids === []
                ? collect()
                : AiDocument::query()->whereIn('id', $ids)->pluck('title', 'id');

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => $results->map(fn ($chunk) => [
                        'document_id' => $chunk->document_id,
                        'title' => $titles[$chunk->document_id] ?? 'Document #' . $chunk->document_id,
                        'snippet' => $this->makeSnippet((string) $chunk->content_text),
                        'score' => $chunk->similarity,
                        'chunk_index' => $chunk->chunk_index,
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

    private function isRagAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function canAccessDocument(User $user, AiDocument $document): bool
    {
        if ($this->isRagAdmin($user)) {
            return true;
        }

        return (int) $document->uploaded_by_user_id === (int) $user->id;
    }

    private function makeSnippet(string $text, int $max = 280): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max) . '…';
    }
}
