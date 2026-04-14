<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get conversations where user is either user_one or user_two
            $conversations = Conversation::where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->with(['userOne', 'userTwo'])
                ->orderBy('last_message_at', 'desc')
                ->get();

            return ApiResponse::success(
                ConversationResource::collection($conversations),
                'تم جلب المحادثات بنجاح'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء جلب المحادثات: ' . $e->getMessage());
        }
    }

    /**
     * Get or create a conversation with another user.
     */
    public function getOrCreateConversation(Request $request, int $userId): JsonResponse
    {
        try {
            $currentUser = $request->user();
            if (!$currentUser) {
                return ApiResponse::unauthorized('غير مصرح - يجب تسجيل الدخول للوصول إلى المحادثات');
            }

            // Validate that user is not trying to chat with themselves
            if ($currentUser->id === $userId) {
                return ApiResponse::error('لا يمكنك بدء محادثة مع نفسك', 400);
            }

            // Check if the other user exists
            $otherUser = User::find($userId);
            if (!$otherUser) {
                return ApiResponse::notFound('المستخدم غير موجود');
            }

            // Find or create conversation
            $conversation = Conversation::findOrCreateBetween($currentUser->id, $userId);
            $conversation->load('userOne', 'userTwo');

            return ApiResponse::success(
                new ConversationResource($conversation),
                'تم جلب المحادثة بنجاح'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء جلب المحادثة: ' . $e->getMessage());
        }
    }

    /**
     * Get messages for a specific conversation.
     */
    public function getMessages(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            // Find conversation and verify user has access
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ApiResponse::notFound('المحادثة غير موجودة');
            }

            if (!$conversation->hasUser($user->id)) {
                return ApiResponse::forbidden('ليس لديك صلاحية للوصول إلى هذه المحادثة');
            }

            // Get messages with pagination
            $perPage = ApiResponse::getPerPage($request, 50, 100);
            $messages = $conversation->messages()
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Mark messages as read
            $conversation->markAsRead($user->id);

            return ApiResponse::paginated($messages, 'تم جلب الرسائل بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء جلب الرسائل: ' . $e->getMessage());
        }
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            $hasVoice = $request->hasFile('voice');
            $hasAttachment = $request->hasFile('attachment');

            if ($hasVoice && $hasAttachment) {
                throw ValidationException::withMessages([
                    'voice' => ['لا يمكن إرسال ملف صوتي ومرفق (صورة/فيديو/ملف) في نفس الطلب.'],
                ]);
            }

            $rules = [
                'voice_duration_seconds' => 'nullable|integer|min:1|max:600',
            ];

            if ($hasVoice) {
                $rules['voice'] = [
                    'required',
                    'file',
                    'max:10240',
                    'mimetypes:audio/webm,audio/wav,audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/x-ms-wma,video/webm,application/octet-stream',
                ];
                $rules['message'] = 'nullable|string|max:5000';
            } elseif ($hasAttachment) {
                $rules['attachment'] = [
                    'required',
                    'file',
                    'max:51200',
                    'mimes:jpeg,jpg,png,gif,webp,bmp,mp4,webm,mov,avi,m4v,pdf,doc,docx,xls,xlsx,txt,csv,zip',
                ];
                $rules['message'] = 'nullable|string|max:5000';
            } else {
                $rules['message'] = 'required|string|max:5000';
            }

            $validated = $request->validate($rules);

            // Find conversation and verify user has access
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ApiResponse::notFound('المحادثة غير موجودة');
            }

            if (!$conversation->hasUser($user->id)) {
                return ApiResponse::forbidden('ليس لديك صلاحية لإرسال رسالة في هذه المحادثة');
            }

            $payload = [
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
            ];

            if ($hasVoice) {
                $payload['type'] = 'voice';
                $payload['message'] = $validated['message'] ?? 'رسالة صوتية';
                $payload['voice_path'] = $request->file('voice')->store(
                    'chat/voice/'.$conversation->id,
                    'public'
                );
                $payload['voice_duration_seconds'] = $validated['voice_duration_seconds'] ?? null;
            } elseif ($hasAttachment) {
                /** @var UploadedFile $file */
                $file = $request->file('attachment');
                $attachmentType = $this->inferAttachmentType($file);
                $payload['type'] = $attachmentType;
                $payload['message'] = $validated['message'] ?? match ($attachmentType) {
                    'image' => 'صورة',
                    'video' => 'فيديو',
                    default => 'ملف',
                };
                // Same public-disk layout as voice: storage/app/public/chat/voice/{conversationId}/
                $payload['attachment_path'] = $file->store(
                    'chat/voice/'.$conversation->id,
                    'public'
                );
                $payload['attachment_original_name'] = $file->getClientOriginalName();
            } else {
                $payload['type'] = 'text';
                $payload['message'] = $validated['message'];
            }

            // Create message
            $message = Message::create($payload);

            // Update conversation's last_message_at
            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Load sender relationship
            $message->load('sender');

            // Broadcast message via WebSocket
            event(new MessageSent($message));

            return ApiResponse::created(
                new MessageResource($message),
                'تم إرسال الرسالة بنجاح'
            );
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء إرسال الرسالة: ' . $e->getMessage());
        }
    }

    /**
     * Mark messages as read in a conversation.
     */
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            // Find conversation and verify user has access
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ApiResponse::notFound('المحادثة غير موجودة');
            }

            if (!$conversation->hasUser($user->id)) {
                return ApiResponse::forbidden('ليس لديك صلاحية للوصول إلى هذه المحادثة');
            }

            // Mark messages as read
            $conversation->markAsRead($user->id);

            return ApiResponse::success(null, 'تم تحديد الرسائل كمقروءة');
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء تحديث حالة الرسائل: ' . $e->getMessage());
        }
    }

    /**
     * Get unread messages count for all conversations.
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get all conversations for the user
            $conversations = Conversation::where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->get();

            $totalUnread = 0;
            foreach ($conversations as $conversation) {
                /** @var \App\Models\Conversation $conversation */
                $totalUnread += $conversation->getUnreadCount($user->id);
            }

            return ApiResponse::success([
                'unread_count' => $totalUnread,
            ], 'تم جلب عدد الرسائل غير المقروءة بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء جلب عدد الرسائل غير المقروءة: ' . $e->getMessage());
        }
    }

    /**
     * Delete a message (soft delete or hard delete based on requirements).
     */
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = $request->user();

            // Find message
            $message = Message::find($messageId);
            if (!$message) {
                return ApiResponse::notFound('الرسالة غير موجودة');
            }

            // Verify user is the sender
            if ($message->sender_id !== $user->id) {
                return ApiResponse::forbidden('يمكنك حذف رسائلك فقط');
            }

            // Verify user has access to the conversation
            $conversation = $message->conversation;
            if (!$conversation->hasUser($user->id)) {
                return ApiResponse::forbidden('ليس لديك صلاحية للوصول إلى هذه المحادثة');
            }

            if ($message->voice_path) {
                Storage::disk('public')->delete($message->voice_path);
            }
            if ($message->attachment_path) {
                Storage::disk('public')->delete($message->attachment_path);
            }

            // Delete message
            $message->delete();

            return ApiResponse::success(null, 'تم حذف الرسالة بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::serverError('حدث خطأ أثناء حذف الرسالة: ' . $e->getMessage());
        }
    }

    /**
     * @return 'image'|'video'|'file'
     */
    private function inferAttachmentType(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }
}

