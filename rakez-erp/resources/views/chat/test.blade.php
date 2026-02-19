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
            <input type="number" id="conversationId" value="" placeholder="سيتم إنشاؤها تلقائياً">
            <label style="margin-top: 10px;">معرف المستخدم للدردشة (Target User ID):</label>
            <input type="number" id="targetUserId" value="1" placeholder="معرف المستخدم">
            <button onclick="loadConversation()">تحميل/إنشاء المحادثة</button>
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
                <button onclick="sendMessage()" id="sendButton">إرسال</button>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const API_BASE_URL = '{{ url("/api") }}';
        const CURRENT_USER_ID = {{ auth()->id() ?? 1 }};
        const TARGET_USER_ID = 1; // User ID to chat with
        let conversationId = null;
        let echo = null;
        let channel = null;
        let authToken = null;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Try to get token from localStorage first
            authToken = localStorage.getItem('auth_token');

            // If no token, try to create one via API (for authenticated users)
            if (!authToken && {{ auth()->check() ? 'true' : 'false' }}) {
                createAuthToken();
            } else {
                initializeEcho();
                loadConversation();
            }
        });

        // Create auth token for current user
        async function createAuthToken() {
            try {
                // Use session-based auth for web routes
                // For API calls, we'll use CSRF token
                initializeEcho();
                loadConversation();
            } catch (error) {
                console.error('Error creating auth token:', error);
                alert('خطأ في المصادقة. يرجى تسجيل الدخول أولاً.');
            }
        }

        // Initialize Laravel Echo
        function initializeEcho() {
            if (typeof Echo === 'undefined') {
                console.error('Echo is not defined. Make sure Laravel Echo is loaded.');
                updateConnectionStatus(false);
                return;
            }

            // For session-based auth, Echo will use cookies automatically
            // But we can also set auth headers if token is available
            if (authToken && Echo.connector && Echo.connector.options) {
                if (!Echo.connector.options.auth) {
                    Echo.connector.options.auth = {};
                }
                if (!Echo.connector.options.auth.headers) {
                    Echo.connector.options.auth.headers = {};
                }
                Echo.connector.options.auth.headers['Authorization'] = `Bearer ${authToken}`;
            }

            updateConnectionStatus(true);
            console.log('Echo initialized');
        }

        // Load or create conversation
        async function loadConversation() {
            const inputConversationId = document.getElementById('conversationId').value;
            const targetUserId = parseInt(document.getElementById('targetUserId').value) || TARGET_USER_ID;

            try {
                // If conversation ID is provided, use it; otherwise create/get conversation
                if (inputConversationId) {
                    conversationId = parseInt(inputConversationId);
                    await loadMessages(conversationId);
                    listenToConversation(conversationId);
                } else {
                    // Get or create conversation with target user
                    const response = await fetch(`${API_BASE_URL}/chat/conversations/${targetUserId}`, {
                        headers: getAuthHeaders(),
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error('Failed to get conversation');
                    }

                    const data = await response.json();
                    conversationId = data.data.id;
                    document.getElementById('conversationId').value = conversationId;

                    await loadMessages(conversationId);
                    listenToConversation(conversationId);
                }
            } catch (error) {
                console.error('Error loading conversation:', error);
                alert('خطأ في تحميل المحادثة: ' + error.message);
            }
        }

        // Load messages
        async function loadMessages(convId) {
            try {
                const response = await fetch(`${API_BASE_URL}/chat/conversations/${convId}/messages?per_page=50`, {
                    headers: getAuthHeaders(),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to load messages');
                }

                const data = await response.json();
                const messagesContainer = document.getElementById('messagesContainer');
                messagesContainer.innerHTML = '';

                if (data.data && data.data.length > 0) {
                    data.data.forEach(message => {
                        addMessageToUI(message, false);
                    });
                } else {
                    messagesContainer.innerHTML = '<div class="empty-state"><p>لا توجد رسائل بعد. ابدأ المحادثة!</p></div>';
                }

                scrollToBottom();
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        // Listen to conversation channel
        function listenToConversation(convId) {
            if (!Echo) {
                console.error('Echo is not available');
                return;
            }

            // Leave previous channel if exists
            if (channel) {
                Echo.leave(`conversation.${channel}`);
            }

            // Listen to new conversation channel
            channel = convId;
            const conversationChannel = Echo.private(`conversation.${convId}`);

            conversationChannel
                .listen('.message.sent', (e) => {
                    console.log('Message received via WebSocket:', e);
                    addMessageToUI(e, true);
                })
                .error((error) => {
                    console.error('Channel error:', error);
                    updateConnectionStatus(false);
                });

            updateConnectionStatus(true);
            console.log(`Listening to conversation.${convId}`);
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

        // Add message to UI
        function addMessageToUI(message, isReceived) {
            const messagesContainer = document.getElementById('messagesContainer');

            // Remove empty state if exists
            const emptyState = messagesContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.sender_id === CURRENT_USER_ID ? 'sent' : 'received'}`;

            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';
            messageContent.textContent = message.message;

            const messageInfo = document.createElement('div');
            messageInfo.className = 'message-info';
            const date = new Date(message.created_at);
            messageInfo.textContent = `${message.sender?.name || 'مستخدم'} - ${date.toLocaleTimeString('ar-SA')}`;

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

        // Update connection status
        function updateConnectionStatus(connected) {
            const statusElement = document.getElementById('connectionStatus');
            if (connected) {
                statusElement.innerHTML = '<span class="status-connected">● متصل</span>';
            } else {
                statusElement.innerHTML = '<span class="status-disconnected">● غير متصل</span>';
            }
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

