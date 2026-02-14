# Chat System Documentation

## Overview

This chat system enables real-time messaging between two users using WebSockets (Laravel Reverb). The system includes:

- **Conversations**: Private conversations between two users
- **Messages**: Real-time messages with read status
- **WebSocket Broadcasting**: Instant message delivery via Laravel Reverb

---

## Business Logic / المنطق التجاري (بالعربية)

### نظرة عامة على النظام / System Overview

نظام الدردشة هذا يسمح للمستخدمين بالتواصل الفوري مع بعضهم البعض من خلال رسائل نصية في الوقت الفعلي. النظام مصمم ليكون بسيطاً وآمناً وسهل الاستخدام.

### المكونات الأساسية / Core Components

#### 1. المحادثات (Conversations)
**المنطق التجاري:**
- كل محادثة تربط بين مستخدمين فقط (محادثة خاصة بين شخصين)
- لا يمكن إنشاء محادثة مع نفسك
- النظام يمنع إنشاء محادثات مكررة بين نفس المستخدمين
- عند طلب محادثة مع مستخدم آخر، النظام يبحث أولاً عن محادثة موجودة، وإذا لم يجدها يقوم بإنشائها تلقائياً
- ترتيب المحادثات يعتمد على آخر رسالة (الأحدث أولاً)

**قواعد العمل:**
- المستخدم الأول (user_one_id) دائماً يكون صاحب الرقم الأصغر
- المستخدم الثاني (user_two_id) دائماً يكون صاحب الرقم الأكبر
- هذا يضمن عدم وجود محادثات مكررة بين نفس المستخدمين

#### 2. الرسائل (Messages)
**المنطق التجاري:**
- كل رسالة تنتمي لمحادثة واحدة فقط
- كل رسالة لها مرسل واحد (sender_id)
- الرسائل تُحفظ في قاعدة البيانات بشكل دائم
- الرسائل مرتبة من الأحدث للأقدم عند العرض

**حالة القراءة (Read Status):**
- عند إرسال رسالة، تكون غير مقروءة (`is_read = false`)
- عند جلب الرسائل من محادثة، يتم تحديدها تلقائياً كمقروءة
- يمكن تحديد الرسائل كمقروءة يدوياً عبر API
- يتم حفظ وقت القراءة (`read_at`) عند تحديد الرسالة كمقروءة

**قواعد العمل:**
- المستخدم لا يمكنه تحديد رسائله الخاصة كمقروءة (فقط رسائل الآخرين)
- عند جلب الرسائل، يتم تحديد جميع الرسائل غير المقروءة من المستخدم الآخر تلقائياً
- الحد الأقصى لطول الرسالة: 5000 حرف

#### 3. الإشعارات الفورية (Real-time Notifications)
**المنطق التجاري:**
- عند إرسال رسالة، يتم بثها فوراً عبر WebSocket
- المستخدم الآخر يستقبل الرسالة في الوقت الفعلي دون الحاجة لتحديث الصفحة
- القناة المستخدمة: `conversation.{conversationId}` (قناة خاصة)
- اسم الحدث: `message.sent`

**الأمان:**
- فقط المستخدمين المشاركين في المحادثة يمكنهم الاستماع للقناة
- النظام يتحقق من صلاحيات المستخدم قبل السماح بالاشتراك

### سير العمل / Workflow

#### سيناريو 1: بدء محادثة جديدة / Starting a New Conversation

1. **المستخدم (أ) يريد التحدث مع المستخدم (ب)**
   - يرسل طلب: `GET /api/chat/conversations/{userId}`
   - النظام يتحقق: هل توجد محادثة موجودة؟
   - إذا كانت موجودة: يعيد المحادثة الموجودة
   - إذا لم تكن موجودة: ينشئ محادثة جديدة تلقائياً

2. **إنشاء المحادثة:**
   - النظام يحدد المستخدم الأول (الرقم الأصغر) والمستخدم الثاني (الرقم الأكبر)
   - ينشئ سجل في جدول `conversations`
   - يعيد معلومات المحادثة مع معلومات المستخدم الآخر

#### سيناريو 2: إرسال رسالة / Sending a Message

1. **المستخدم يكتب رسالة ويرسلها:**
   - يرسل طلب: `POST /api/chat/conversations/{conversationId}/messages`
   - النظام يتحقق: هل المستخدم جزء من هذه المحادثة؟
   - إذا كان جزءاً: ينشئ الرسالة في قاعدة البيانات
   - يحدث `last_message_at` في المحادثة

2. **البث الفوري:**
   - يتم إطلاق حدث `MessageSent`
   - يتم بث الرسالة عبر WebSocket على القناة الخاصة
   - المستخدم الآخر يستقبل الرسالة فوراً

3. **حالة الرسالة:**
   - الرسالة تُحفظ كـ `is_read = false`
   - عند فتح المحادثة من قبل المستخدم الآخر، تصبح مقروءة تلقائياً

#### سيناريو 3: قراءة الرسائل / Reading Messages

1. **المستخدم يفتح محادثة:**
   - يرسل طلب: `GET /api/chat/conversations/{conversationId}/messages`
   - النظام يجلب الرسائل مع معلومات المرسل
   - **تلقائياً**: يتم تحديد جميع الرسائل غير المقروءة من المستخدم الآخر كمقروءة
   - يتم حفظ `read_at` لكل رسالة

2. **عرض الرسائل:**
   - الرسائل مرتبة من الأحدث للأقدم
   - كل رسالة تحتوي على معلومات المرسل والوقت

#### سيناريو 4: تتبع الرسائل غير المقروءة / Unread Messages Tracking

1. **عرض عدد الرسائل غير المقروءة:**
   - يرسل طلب: `GET /api/chat/unread-count`
   - النظام يجمع عدد الرسائل غير المقروءة من جميع المحادثات
   - يعيد العدد الإجمالي

2. **استخدامات:**
   - عرض شارة (badge) بعدد الرسائل غير المقروءة
   - إشعارات للمستخدم
   - ترتيب المحادثات حسب الرسائل غير المقروءة

### القواعد التجارية المهمة / Important Business Rules

#### 1. الأمان والصلاحيات / Security & Permissions

- **المصادقة (Authentication):** جميع الطلبات تتطلب توكن مصادقة (Sanctum)
- **الصلاحيات (Authorization):**
  - المستخدم يمكنه فقط الوصول للمحادثات التي هو جزء منها
  - المستخدم يمكنه فقط حذف رسائله الخاصة
  - لا يمكن للمستخدم الوصول لمحادثات الآخرين

#### 2. منع التكرار / Preventing Duplicates

- النظام يضمن عدم وجود محادثات مكررة بين نفس المستخدمين
- عند إنشاء محادثة، يتم ترتيب معرفات المستخدمين (الأصغر أولاً)
- يتم البحث عن محادثة موجودة قبل الإنشاء

#### 3. إدارة الحالة / State Management

- **حالة الرسالة:** غير مقروءة → مقروءة
- **تحديث المحادثة:** يتم تحديث `last_message_at` عند كل رسالة جديدة
- **الترتيب:** المحادثات مرتبة حسب آخر رسالة (الأحدث أولاً)

#### 4. الأداء / Performance

- **التصفح (Pagination):** الرسائل تُجلب بشكل صفحات (50 رسالة لكل صفحة)
- **الفهرسة (Indexing):** قاعدة البيانات مفهرسة للبحث السريع
- **الاستعلامات المحسنة:** استخدام علاقات Eloquent لتحسين الأداء

### حالات الاستخدام الشائعة / Common Use Cases

#### 1. دردشة بين موظفين / Employee Chat
- موظف المبيعات يتواصل مع مدير المبيعات
- مناقشة الصفقات والعقود
- تنسيق العمل اليومي

#### 2. التواصل بين الأقسام / Inter-Department Communication
- قسم المبيعات يتواصل مع قسم المحاسبة
- قسم المشاريع يتواصل مع قسم التسويق
- تنسيق العمل بين الأقسام المختلفة

#### 3. الإشعارات الفورية / Instant Notifications
- إشعار فوري عند استلام رسالة جديدة
- عرض عدد الرسائل غير المقروءة
- تحديث واجهة المستخدم تلقائياً

### معالجة الأخطاء / Error Handling

#### الأخطاء الشائعة ومعالجتها:

1. **المستخدم غير موجود:**
   - الخطأ: `404 - المستخدم غير موجود`
   - الحل: التحقق من معرف المستخدم قبل إنشاء المحادثة

2. **المحادثة غير موجودة:**
   - الخطأ: `404 - المحادثة غير موجودة`
   - الحل: التحقق من معرف المحادثة أو إنشاء محادثة جديدة

3. **عدم وجود صلاحية:**
   - الخطأ: `403 - ليس لديك صلاحية`
   - الحل: التحقق من أن المستخدم جزء من المحادثة

4. **رسالة فارغة أو طويلة جداً:**
   - الخطأ: `422 - خطأ في التحقق من البيانات`
   - الحل: التحقق من طول الرسالة (1-5000 حرف)

### أفضل الممارسات / Best Practices

1. **عند إرسال رسالة:**
   - تحقق من وجود المحادثة أولاً
   - استخدم WebSocket للبث الفوري
   - احفظ الرسالة في قاعدة البيانات

2. **عند عرض الرسائل:**
   - استخدم التصفح (pagination) للرسائل الكثيرة
   - حدد الرسائل كمقروءة تلقائياً عند الجلب
   - اعرض معلومات المرسل مع كل رسالة

3. **عند إدارة المحادثات:**
   - اعرض المحادثات مرتبة حسب آخر رسالة
   - اعرض عدد الرسائل غير المقروءة لكل محادثة
   - حدّث القائمة تلقائياً عند استلام رسالة جديدة

---

## Database Structure

### Tables

1. **conversations**
   - `id`: Primary key
   - `user_one_id`: First user in conversation
   - `user_two_id`: Second user in conversation
   - `last_message_at`: Timestamp of last message
   - `created_at`, `updated_at`: Timestamps

2. **messages**
   - `id`: Primary key
   - `conversation_id`: Foreign key to conversations
   - `sender_id`: Foreign key to users (who sent the message)
   - `message`: Message content (text)
   - `is_read`: Boolean flag for read status
   - `read_at`: Timestamp when message was read
   - `created_at`, `updated_at`: Timestamps

## API Endpoints

All endpoints require authentication via Sanctum (`auth:sanctum` middleware).

### Base URL: `/api/chat`

#### 1. Get All Conversations / جلب جميع المحادثات
```
GET /api/chat/conversations
```

**المنطق التجاري / Business Logic:**
- يجلب جميع المحادثات التي المستخدم الحالي جزء منها
- يعرض معلومات المستخدم الآخر في كل محادثة
- يعرض عدد الرسائل غير المقروءة لكل محادثة
- مرتبة حسب آخر رسالة (الأحدث أولاً)
- لا يعرض محادثات المستخدمين الآخرين

**Response:**
```json
{
  "success": true,
  "message": "تم جلب المحادثات بنجاح",
  "data": [
    {
      "id": 1,
      "other_user": {
        "id": 2,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "1234567890",
        "type": "sales"
      },
      "last_message_at": "2026-02-13T10:30:00.000000Z",
      "unread_count": 3,
      "created_at": "2026-02-13T09:00:00.000000Z",
      "updated_at": "2026-02-13T10:30:00.000000Z"
    }
  ]
}
```

#### 2. Get or Create Conversation with User / جلب أو إنشاء محادثة مع مستخدم
```
GET /api/chat/conversations/{userId}
```

**المنطق التجاري / Business Logic:**
- يبحث عن محادثة موجودة بين المستخدم الحالي والمستخدم المحدد
- إذا كانت موجودة: يعيد المحادثة الموجودة
- إذا لم تكن موجودة: ينشئ محادثة جديدة تلقائياً
- يمنع إنشاء محادثة مع نفسك (خطأ 400)
- يتحقق من وجود المستخدم الآخر قبل الإنشاء

**Response:**
```json
{
  "success": true,
  "message": "تم جلب المحادثة بنجاح",
  "data": {
    "id": 1,
    "other_user": { ... },
    "last_message_at": "...",
    "unread_count": 0,
    ...
  }
}
```

#### 3. Get Messages for Conversation / جلب رسائل المحادثة
```
GET /api/chat/conversations/{conversationId}/messages?page=1&per_page=50
```

**المنطق التجاري / Business Logic:**
- يجلب الرسائل لمحادثة محددة مع التصفح (pagination)
- يتحقق من أن المستخدم جزء من المحادثة
- **تلقائياً**: يحدد جميع الرسائل غير المقروءة من المستخدم الآخر كمقروءة
- يحفظ وقت القراءة (`read_at`) لكل رسالة
- الرسائل مرتبة من الأحدث للأقدم

**Query Parameters:**
- `page`: رقم الصفحة (افتراضي: 1) / Page number (default: 1)
- `per_page`: عدد العناصر لكل صفحة (افتراضي: 50، الحد الأقصى: 100) / Items per page (default: 50, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "تم جلب الرسائل بنجاح",
  "data": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com"
      },
      "sender_id": 1,
      "message": "Hello!",
      "is_read": true,
      "read_at": "2026-02-13T10:31:00.000000Z",
      "created_at": "2026-02-13T10:30:00.000000Z",
      "updated_at": "2026-02-13T10:30:00.000000Z"
    }
  ],
  "meta": {
    "pagination": {
      "total": 50,
      "count": 50,
      "per_page": 50,
      "current_page": 1,
      "total_pages": 1,
      "has_more_pages": false
    }
  }
}
```

#### 4. Send Message / إرسال رسالة
```
POST /api/chat/conversations/{conversationId}/messages
```

**المنطق التجاري / Business Logic:**
- ينشئ رسالة جديدة في المحادثة
- يتحقق من أن المستخدم جزء من المحادثة
- يتحقق من صحة الرسالة (غير فارغة، لا تتجاوز 5000 حرف)
- يحفظ الرسالة في قاعدة البيانات
- يحدث `last_message_at` في المحادثة
- يبث الرسالة فوراً عبر WebSocket للمستخدم الآخر
- الرسالة تُحفظ كـ `is_read = false` حتى يقرأها المستخدم الآخر

**Request Body:**
```json
{
  "message": "Hello, how are you?"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم إرسال الرسالة بنجاح",
  "data": {
    "id": 2,
    "conversation_id": 1,
    "sender": { ... },
    "sender_id": 1,
    "message": "Hello, how are you?",
    "is_read": false,
    "read_at": null,
    "created_at": "2026-02-13T10:35:00.000000Z",
    "updated_at": "2026-02-13T10:35:00.000000Z"
  }
}
```

#### 5. Mark Messages as Read / تحديد الرسائل كمقروءة
```
PATCH /api/chat/conversations/{conversationId}/read
```

**المنطق التجاري / Business Logic:**
- يحدد جميع الرسائل غير المقروءة من المستخدم الآخر كمقروءة
- يتحقق من أن المستخدم جزء من المحادثة
- يحفظ وقت القراءة (`read_at`) لكل رسالة
- **ملاحظة:** الرسائل تُحدد تلقائياً كمقروءة عند جلبها، لكن يمكن تحديدها يدوياً أيضاً

**Response:**
```json
{
  "success": true,
  "message": "تم تحديد الرسائل كمقروءة"
}
```

#### 6. Get Unread Count / جلب عدد الرسائل غير المقروءة
```
GET /api/chat/unread-count
```

**المنطق التجاري / Business Logic:**
- يجمع عدد الرسائل غير المقروءة من جميع المحادثات
- يحسب فقط الرسائل من المستخدمين الآخرين (ليس رسائلك الخاصة)
- يعيد العدد الإجمالي لجميع المحادثات
- **الاستخدام:** عرض شارة (badge) بعدد الرسائل غير المقروءة في الواجهة

**Response:**
```json
{
  "success": true,
  "message": "تم جلب عدد الرسائل غير المقروءة بنجاح",
  "data": {
    "unread_count": 5
  }
}
```

#### 7. Delete Message / حذف رسالة
```
DELETE /api/chat/messages/{messageId}
```

**المنطق التجاري / Business Logic:**
- يحذف رسالة محددة من المحادثة
- يتحقق من أن المستخدم هو مرسل الرسالة (يمكنك حذف رسائلك فقط)
- يتحقق من أن المستخدم جزء من المحادثة
- **ملاحظة:** الحذف نهائي (لا يوجد soft delete حالياً)

**Response:**
```json
{
  "success": true,
  "message": "تم حذف الرسالة بنجاح"
}
```

## WebSocket Integration

### Channel Authorization

The system uses private channels for conversations:
- Channel name: `conversation.{conversationId}`
- Only users who are part of the conversation can subscribe

### Event Broadcasting

When a message is sent, a `MessageSent` event is broadcast on the conversation channel.

**Event Name:** `message.sent`

**Event Data:**
```json
{
  "id": 2,
  "conversation_id": 1,
  "sender_id": 1,
  "sender": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com"
  },
  "message": "Hello, how are you?",
  "is_read": false,
  "created_at": "2026-02-13T10:35:00.000000Z"
}
```

### Frontend Implementation

See `resources/js/chat-example.js` for a complete example of how to:

1. Initialize Laravel Echo with Reverb
2. Listen to conversation channels
3. Handle incoming messages
4. Send messages via API

**Example Usage:**

```javascript
import { chatManager } from './chat-example.js';

// Set authentication token
chatManager.setAuthToken('your-sanctum-token');

// Listen to conversation
chatManager.listenToConversation(1, {
    onMessage: (data) => {
        console.log('New message:', data.message);
        // Update your UI here
    }
});

// Send a message
async function sendMessage(conversationId, messageText) {
    const response = await fetch(`/api/chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ message: messageText }),
    });
    return await response.json();
}
```

## Setup Instructions

### 1. Run Migrations

```bash
php artisan migrate
```

This will create the `conversations` and `messages` tables.

### 2. Configure Reverb

Ensure your `.env` file has the following Reverb configuration:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 3. Start Reverb Server

```bash
php artisan reverb:start
```

### 4. Frontend Configuration

Make sure your frontend has access to Reverb environment variables:

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## Models

### Conversation Model

**Location:** `app/Models/Conversation.php`

**Key Methods:**
- `findOrCreateBetween($userOneId, $userTwoId)`: Find or create conversation between two users
- `getOtherUser($currentUserId)`: Get the other user in the conversation
- `hasUser($userId)`: Check if user is part of conversation
- `getUnreadCount($userId)`: Get unread messages count
- `markAsRead($userId)`: Mark all messages as read

### Message Model

**Location:** `app/Models/Message.php`

**Key Methods:**
- `markAsRead()`: Mark message as read

### User Model

**New Relationships:**
- `conversationsAsUserOne()`: Conversations where user is user_one
- `conversationsAsUserTwo()`: Conversations where user is user_two
- `conversations()`: All conversations for user
- `sentMessages()`: All messages sent by user

## Security

1. **Authentication**: All endpoints require Sanctum authentication
2. **Authorization**: Users can only access conversations they are part of
3. **Channel Authorization**: WebSocket channels verify user membership before allowing subscription
4. **Message Ownership**: Users can only delete their own messages

## Error Handling

All endpoints return standardized error responses:

```json
{
  "success": false,
  "message": "Error message in Arabic",
  "error_code": "ERROR_CODE"
}
```

Common error codes:
- `VALIDATION_ERROR`: Validation failed (422)
- `UNAUTHORIZED`: Not authenticated (401)
- `FORBIDDEN`: Not authorized (403)
- `NOT_FOUND`: Resource not found (404)
- `SERVER_ERROR`: Internal server error (500)

## Testing

Example test scenarios:

1. **Create Conversation**: GET `/api/chat/conversations/{userId}`
2. **Send Message**: POST `/api/chat/conversations/{conversationId}/messages`
3. **Receive Message**: Listen to WebSocket channel `conversation.{conversationId}`
4. **Mark as Read**: PATCH `/api/chat/conversations/{conversationId}/read`
5. **Get Unread Count**: GET `/api/chat/unread-count`

## Postman Collection

A complete Postman collection is available at:
- **Location**: `postman/Chat_API.postman_collection.json`
- **Import**: Import this file into Postman to test all endpoints

**Collection includes:**
- ✅ All 7 chat endpoints
- ✅ Pre-configured authentication (Bearer token)
- ✅ Test scripts for each endpoint
- ✅ Variable chaining (auto-saves conversation/message IDs)
- ✅ Example requests with descriptions
- ✅ Response structure documentation

**Quick Setup:**
1. Import `Chat_API.postman_collection.json` into Postman
2. Set `baseUrl` variable (default: `http://localhost:8000`)
3. Set `token` variable with your Sanctum token
4. Set `userId` variable to test with another user
5. Start testing!

## Notes / ملاحظات

- Messages are ordered by `created_at` descending (newest first)
  - الرسائل مرتبة حسب تاريخ الإنشاء تنازلياً (الأحدث أولاً)
- Conversations are ordered by `last_message_at` descending
  - المحادثات مرتبة حسب آخر رسالة تنازلياً (الأحدث أولاً)
- Maximum message length: 5000 characters
  - الحد الأقصى لطول الرسالة: 5000 حرف
- Read status is automatically updated when fetching messages
  - حالة القراءة تُحدث تلقائياً عند جلب الرسائل
- The system ensures unique conversations between two users (no duplicates)
  - النظام يضمن عدم وجود محادثات مكررة بين نفس المستخدمين

---

## ملخص النظام / System Summary

### الميزات الرئيسية / Key Features

✅ **محادثات خاصة بين شخصين** - كل محادثة تربط مستخدمين فقط  
✅ **رسائل فورية** - إرسال واستقبال الرسائل في الوقت الفعلي عبر WebSocket  
✅ **تتبع حالة القراءة** - معرفة الرسائل المقروءة وغير المقروءة  
✅ **أمان عالي** - مصادقة وصلاحيات كاملة لكل عملية  
✅ **أداء محسّن** - تصفح الرسائل وقواعد بيانات مفهرسة  
✅ **سهولة الاستخدام** - واجهة برمجية بسيطة وواضحة  

### حالات الاستخدام / Use Cases

1. **التواصل بين الموظفين** - دردشة مباشرة بين أعضاء الفريق
2. **التنسيق بين الأقسام** - التواصل بين أقسام مختلفة في الشركة
3. **الإشعارات الفورية** - إشعارات لحظية عند استلام رسائل جديدة
4. **تتبع الرسائل** - معرفة عدد الرسائل غير المقروءة في جميع المحادثات

### الأمان / Security

- ✅ جميع الطلبات تتطلب مصادقة (Sanctum Token)
- ✅ المستخدمون يمكنهم فقط الوصول لمحادثاتهم الخاصة
- ✅ قنوات WebSocket محمية وتتحقق من الصلاحيات
- ✅ المستخدمون يمكنهم فقط حذف رسائله الخاصة

### الأداء / Performance

- ✅ تصفح الرسائل (50 رسالة لكل صفحة)
- ✅ فهرسة قاعدة البيانات للبحث السريع
- ✅ استخدام علاقات Eloquent المحسّنة
- ✅ بث فوري عبر WebSocket بدون تأخير

---

**تم إنشاء النظام بواسطة:** Laravel 12.x + Reverb WebSocket  
**التاريخ:** فبراير 2026  
**الحالة:** ✅ جاهز للإنتاج / Production Ready

