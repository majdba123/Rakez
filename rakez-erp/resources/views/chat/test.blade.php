<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار نظام الدردشة - Chat System Test</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .chat-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chat-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .chat-header .status {
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.9;
        }

        .status-connected {
            color: #4ade80;
        }

        .status-disconnected {
            color: #f87171;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-content {
            background: #e5e7eb;
            color: #1f2937;
            border-bottom-left-radius: 4px;
        }

        .message-info {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.7;
        }

        .message.sent .message-info {
            text-align: right;
        }

        .message.received .message-info {
            text-align: left;
        }

        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .input-group input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            border-color: #667eea;
        }

        .input-group button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, opacity 0.2s;
        }

        .input-group button:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .input-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .config-panel {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
        }

        .config-panel label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }

        .config-panel input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .config-panel button {
            margin-top: 10px;
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .typing-indicator {
            display: none;
            padding: 10px 20px;
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
        }

        .typing-indicator.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1>🧪 اختبار نظام الدردشة - Chat System Test</h1>
            <div class="status" id="connectionStatus">
                <span class="status-disconnected">● غير متصل</span>
            </div>
        </div>

        <div class="config-panel">
            <label>معرف المحادثة (Conversation ID):</label>
            <input type="number" id="conversationId" value="" placeholder="مثال: 5">
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                <button type="button" onclick="subscribeRealtimeOnly()" style="background:#2563eb;">① اشترك في البث المباشر</button>
                <button type="button" onclick="loadHistory()" style="background:#64748b;">② تحميل الرسائل (اختياري)</button>
            </div>
            <p style="margin-top:10px; font-size:12px; color:#64748b; line-height:1.5;">
                لاختبار Postman: أرسل <span style="font-family:monospace;">POST /api/chat/conversations/{id}/messages</span> مع مستخدم مشارك في نفس المحادثة (Bearer token).
                المستخدم المسجّل هنا في المتصفح يجب أن يكون أحد طرفي المحادثة حتى يُسمح بقناة <span style="font-family:monospace;">private-conversation.*</span>.
            </p>
            <hr style="margin:14px 0; border:none; border-top:1px solid #e5e7eb;">
            <label>إنشاء/فتح محادثة مع مستخدم (اختياري):</label>
            <input type="number" id="targetUserId" value="1" placeholder="معرف المستخدم الآخر">
            <button type="button" onclick="loadOrCreateWithUser()">تحميل أو إنشاء المحادثة</button>
            <div style="margin-top: 10px; font-size: 12px; color: #6b7280;">
                المستخدم الحالي: <strong>{{ auth()->user()?->name ?? 'غير مسجل' }}</strong> (ID: {{ auth()->id() ?? 'N/A' }})
            </div>
        </div>

        <div class="chat-messages" id="messagesContainer">
            <div class="empty-state">
                <p>لا توجد رسائل بعد. ابدأ المحادثة!</p>
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            جاري الكتابة...
        </div>

        <div class="chat-input">
            <div class="input-group">
                <input
                    type="text"
                    id="messageInput"
                    placeholder="اكتب رسالتك هنا..."
                    onkeypress="handleKeyPress(event)"
                >
                <button type="button" onclick="sendMessage()" id="sendButton">إرسال</button>
            </div>
            <div class="voice-row" style="margin-top:12px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <button type="button" id="voiceRecordBtn" onclick="toggleVoiceRecord()" style="background:#0d9488;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">
                    تسجيل صوتي (اضغط للبدء، ثم للإيقاف والإرسال)
                </button>
                <span id="voiceStatus" style="font-size:13px;color:#64748b;"></span>
            </div>
            <p style="margin-top:8px;font-size:12px;color:#94a3b8;line-height:1.5;">
                الرسائل الصوتية: <code style="direction:ltr;">POST multipart/form-data</code> الحقل <code style="direction:ltr;">voice</code> (ملف صوت) واختياري <code style="direction:ltr;">message</code> كتعليق، و<code style="direction:ltr;">voice_duration_seconds</code>.
                يتطلب <code style="direction:ltr;">php artisan storage:link</code> لتشغيل الملفات من <code style="direction:ltr;">/storage</code>.
            </p>
        </div>
    </div>

    <script>
        const API_BASE_URL = '{{ url("/api") }}';
        const CURRENT_USER_ID = {{ (int) (auth()->id() ?? 0) }};
        let conversationId = null;
        let subscribedConversationId = null;
        let authToken = localStorage.getItem('auth_token');
        const seenMessageIds = new Set();

        let mediaRecorder = null;
        let voiceChunks = [];
        let voiceRecording = false;
        let voiceStartedAt = null;
        let voiceTickTimer = null;

        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');
            if (typeof window.Echo === 'undefined') {
                updateConnectionStatus(false, 'Echo غير محمّل — نفّذ npm run build وشغّل Vite');
                return;
            }
            if (authToken && window.Echo?.connector?.options) {
                window.Echo.connector.options.auth = window.Echo.connector.options.auth || {};
                window.Echo.connector.options.auth.headers = Object.assign(
                    {},
                    window.Echo.connector.options.auth.headers,
                    { Authorization: 'Bearer ' + authToken }
                );
            }
            wirePusherConnectionStatus();
        });

        function wirePusherConnectionStatus() {
            try {
                const pusher = window.Echo?.connector?.pusher;
                if (!pusher?.connection) return;
                pusher.connection.bind('connected', () => updateConnectionStatus(true, 'WebSocket متصل (Reverb)'));
                pusher.connection.bind('disconnected', () => updateConnectionStatus(false, 'WebSocket غير متصل'));
                pusher.connection.bind('error', () => updateConnectionStatus(false, 'خطأ WebSocket'));
            } catch (e) {
                console.warn(e);
            }
        }

        /** اشترك فقط بالقناة — مناسب عند إرسال الرسائل من Postman */
        function subscribeRealtimeOnly() {
            const raw = document.getElementById('conversationId').value.trim();
            if (!raw) {
                alert('أدخل Conversation ID أولاً');
                return;
            }
            const id = parseInt(raw, 10);
            if (!id) {
                alert('معرف غير صالح');
                return;
            }
            conversationId = id;
            listenToConversation(id);
        }

        async function loadHistory() {
            const raw = document.getElementById('conversationId').value.trim();
            if (!raw) {
                alert('أدخل Conversation ID');
                return;
            }
            const id = parseInt(raw, 10);
            if (!id) return;
            conversationId = id;
            await loadMessages(id);
            listenToConversation(id);
        }

        async function loadOrCreateWithUser() {
            const targetUserId = parseInt(document.getElementById('targetUserId').value, 10);
            if (!targetUserId) {
                alert('أدخل معرف المستخدم');
                return;
            }
            try {
                const response = await fetch(`${API_BASE_URL}/chat/conversations/${targetUserId}`, {
                    headers: getAuthHeaders(),
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    const t = await response.text();
                    throw new Error(t || 'فشل جلب المحادثة');
                }
                const json = await response.json();
                conversationId = json.data.id;
                document.getElementById('conversationId').value = conversationId;
                await loadMessages(conversationId);
                listenToConversation(conversationId);
            } catch (e) {
                console.error(e);
                alert('خطأ: ' + e.message);
            }
        }

        async function loadMessages(convId) {
            try {
                const response = await fetch(`${API_BASE_URL}/chat/conversations/${convId}/messages?per_page=50`, {
                    headers: getAuthHeaders(),
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const json = await response.json();
                const list = Array.isArray(json.data) ? json.data : [];
                const messagesContainer = document.getElementById('messagesContainer');
                messagesContainer.innerHTML = '';
                seenMessageIds.clear();

                if (list.length > 0) {
                    list.slice().reverse().forEach((message) => addMessageToUI(message, false));
                } else {
                    messagesContainer.innerHTML = '<div class="empty-state"><p>لا توجد رسائل بعد.</p></div>';
                }
                scrollToBottom();
            } catch (error) {
                console.error('loadMessages', error);
                alert('تعذر تحميل الرسائل (تأكد أنك طرف في المحادثة): ' + error.message);
            }
        }

        function listenToConversation(convId) {
            if (typeof window.Echo === 'undefined') {
                alert('Echo غير متوفر');
                return;
            }

            if (subscribedConversationId !== null) {
                try {
                    window.Echo.leave('conversation.' + subscribedConversationId);
                } catch (e) {
                    console.warn('leave channel', e);
                }
            }

            subscribedConversationId = convId;
            const conversationChannel = window.Echo.private('conversation.' + convId);

            conversationChannel
                .subscribed(() => {
                    updateConnectionStatus(true, 'مشترك في المحادثة #' + convId + ' — جاهز للبث');
                })
                .listen('.message.sent', (e) => {
                    console.log('message.sent', e);
                    addMessageToUI(e, true);
                })
                .listen('pusher:subscription_error', (status) => {
                    console.error('pusher:subscription_error', status);
                    updateConnectionStatus(false, 'فشل الاشتراك — تحقق أنك طرف في المحادثة و /api/broadcasting/auth');
                });

            console.log('Listening private conversation.' + convId);
        }

        function getVoiceUploadHeaders() {
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
            if (authToken) {
                headers['Authorization'] = 'Bearer ' + authToken;
            }
            return headers;
        }

        function pickVoiceMime() {
            if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) {
                return '';
            }
            const opts = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
            for (let i = 0; i < opts.length; i++) {
                if (MediaRecorder.isTypeSupported(opts[i])) return opts[i];
            }
            return '';
        }

        async function toggleVoiceRecord() {
            const btn = document.getElementById('voiceRecordBtn');
            const status = document.getElementById('voiceStatus');
            if (!conversationId) {
                alert('حدد محادثة أولاً (معرف المحادثة أو إنشاء مع مستخدم)');
                return;
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                alert('المتصفح لا يدعم تسجيل الصوت من الميكروفون');
                return;
            }
            if (!voiceRecording) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    voiceChunks = [];
                    const mime = pickVoiceMime();
                    mediaRecorder = mime
                        ? new MediaRecorder(stream, { mimeType: mime })
                        : new MediaRecorder(stream);
                    mediaRecorder.ondataavailable = (e) => {
                        if (e.data && e.data.size > 0) voiceChunks.push(e.data);
                    };
                    mediaRecorder.start(200);
                    voiceRecording = true;
                    voiceStartedAt = Date.now();
                    btn.textContent = 'إيقاف وإرسال';
                    btn.style.background = '#dc2626';
                    status.textContent = 'جاري التسجيل…';
                    voiceTickTimer = setInterval(() => {
                        const sec = Math.round((Date.now() - voiceStartedAt) / 1000);
                        status.textContent = 'جاري التسجيل… ' + sec + ' ث';
                    }, 500);
                } catch (e) {
                    console.error(e);
                    alert('تعذر الوصول للميكروفون: ' + e.message);
                }
            } else {
                clearInterval(voiceTickTimer);
                voiceTickTimer = null;
                voiceRecording = false;
                btn.disabled = true;
                btn.textContent = 'جاري الإرسال…';
                const durationSec = voiceStartedAt
                    ? Math.max(1, Math.round((Date.now() - voiceStartedAt) / 1000))
                    : null;
                voiceStartedAt = null;
                status.textContent = 'معالجة الملف…';

                const rec = mediaRecorder;
                const mimeType = rec.mimeType || 'audio/webm';
                rec.onstop = async () => {
                    rec.stream.getTracks().forEach((t) => t.stop());
                    const blob = new Blob(voiceChunks, { type: mimeType });
                    voiceChunks = [];
                    const ext = (blob.type || '').includes('mp4') ? 'm4a' : 'webm';
                    await sendVoiceBlob(blob, durationSec, ext);
                    btn.disabled = false;
                    btn.textContent = 'تسجيل صوتي (اضغط للبدء، ثم للإيقاف والإرسال)';
                    btn.style.background = '#0d9488';
                    status.textContent = '';
                    mediaRecorder = null;
                };
                rec.stop();
            }
        }

        async function sendVoiceBlob(blob, durationSec, ext) {
            if (!conversationId || !blob.size) {
                alert('لا يوجد تسجيل صوتي');
                return;
            }
            const caption = document.getElementById('messageInput').value.trim();
            const formData = new FormData();
            formData.append('voice', blob, 'voice.' + ext);
            if (durationSec) {
                formData.append('voice_duration_seconds', String(durationSec));
            }
            if (caption) {
                formData.append('message', caption);
            }
            try {
                const response = await fetch(
                    API_BASE_URL + '/chat/conversations/' + conversationId + '/messages',
                    {
                        method: 'POST',
                        headers: getVoiceUploadHeaders(),
                        credentials: 'same-origin',
                        body: formData
                    }
                );
                if (!response.ok) {
                    let msg = 'HTTP ' + response.status;
                    try {
                        const err = await response.json();
                        msg = err.message || msg;
                    } catch (_) {}
                    throw new Error(msg);
                }
                const data = await response.json();
                document.getElementById('messageInput').value = '';
                addMessageToUI(data.data, false);
            } catch (e) {
                console.error(e);
                alert('خطأ في إرسال الرسالة الصوتية: ' + e.message);
            }
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const messageText = input.value.trim();
            const sendButton = document.getElementById('sendButton');

            if (!messageText || !conversationId) {
                return;
            }

            // Disable button
            sendButton.disabled = true;
            sendButton.textContent = 'جاري الإرسال...';

            try {
                const response = await fetch(`${API_BASE_URL}/chat/conversations/${conversationId}/messages`, {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        message: messageText
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to send message');
                }

                const data = await response.json();

                // Clear input
                input.value = '';

                // Message will be added via WebSocket, but we can add it immediately for better UX
                addMessageToUI(data.data, false);

            } catch (error) {
                console.error('Error sending message:', error);
                alert('خطأ في إرسال الرسالة: ' + error.message);
            } finally {
                sendButton.disabled = false;
                sendButton.textContent = 'إرسال';
            }
        }

        function addMessageToUI(message, fromRealtime) {
            if (message.id != null && seenMessageIds.has(message.id)) {
                return;
            }
            if (message.id != null) {
                seenMessageIds.add(message.id);
            }

            const messagesContainer = document.getElementById('messagesContainer');
            const emptyState = messagesContainer.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            const senderName = message.sender?.name || message.sender?.data?.name || 'مستخدم';
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (message.sender_id === CURRENT_USER_ID ? 'sent' : 'received');

            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';

            const type = message.type || 'text';
            if (type === 'voice' && message.voice_url) {
                const cap = document.createElement('div');
                cap.style.marginBottom = '8px';
                cap.textContent = message.message || 'رسالة صوتية';
                messageContent.appendChild(cap);
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.preload = 'metadata';
                audio.style.maxWidth = '100%';
                audio.src = message.voice_url;
                messageContent.appendChild(audio);
                if (message.voice_duration_seconds) {
                    const dur = document.createElement('div');
                    dur.style.fontSize = '11px';
                    dur.style.opacity = '0.85';
                    dur.style.marginTop = '4px';
                    dur.textContent = 'المدة: ~' + message.voice_duration_seconds + ' ث';
                    messageContent.appendChild(dur);
                }
            } else {
                messageContent.textContent = message.message || '';
            }

            const messageInfo = document.createElement('div');
            messageInfo.className = 'message-info';
            const date = new Date(message.created_at);
            messageInfo.textContent = senderName + ' — ' + date.toLocaleTimeString('ar-SA') + (fromRealtime ? ' (مباشر)' : '');

            messageContent.appendChild(messageInfo);
            messageDiv.appendChild(messageContent);
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        // Handle Enter key press
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        // Scroll to bottom
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messagesContainer');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function updateConnectionStatus(connected, detail) {
            const statusElement = document.getElementById('connectionStatus');
            const cls = connected ? 'status-connected' : 'status-disconnected';
            const dot = connected ? '● متصل' : '● غير متصل';
            const extra = detail ? ' — ' + detail : '';
            statusElement.innerHTML = '<span class="' + cls + '">' + dot + extra + '</span>';
        }

        // Get auth token
        function getAuthToken() {
            // Return stored token or empty (session auth will be used)
            return authToken || '';
        }

        // Get auth headers for API calls
        function getAuthHeaders() {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };

            if (authToken) {
                headers['Authorization'] = `Bearer ${authToken}`;
            }

            return headers;
        }
    </script>
</body>
</html>

