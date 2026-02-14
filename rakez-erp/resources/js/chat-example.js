/**
 * Chat System Example using Laravel Echo and WebSockets
 *
 * This is an example implementation of how to use the chat system
 * with Laravel Reverb WebSocket server.
 *
 * Prerequisites:
 * 1. Laravel Echo and Pusher JS are already installed
 * 2. Reverb server is running (php artisan reverb:start)
 * 3. User is authenticated with Sanctum token
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo (configure this based on your .env settings)
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`, // Your Sanctum token
        },
    },
});

/**
 * Chat Manager Class
 */
class ChatManager {
    constructor() {
        this.currentConversationId = null;
        this.listeners = {};
    }

    /**
     * Set the authentication token
     */
    setAuthToken(token) {
        // Update Echo auth headers
        window.Echo.connector.options.auth.headers.Authorization = `Bearer ${token}`;
        localStorage.setItem('auth_token', token);
    }

    /**
     * Listen to a conversation channel
     */
    listenToConversation(conversationId, callbacks) {
        // Stop listening to previous conversation if any
        if (this.currentConversationId && this.currentConversationId !== conversationId) {
            this.stopListening(this.currentConversationId);
        }

        this.currentConversationId = conversationId;

        // Listen to private conversation channel
        const channel = window.Echo.private(`conversation.${conversationId}`);

        // Listen for new messages
        if (callbacks.onMessage) {
            channel.listen('.message.sent', (data) => {
                callbacks.onMessage(data);
            });
        }

        // Store channel reference for cleanup
        this.listeners[conversationId] = channel;

        return channel;
    }

    /**
     * Stop listening to a conversation
     */
    stopListening(conversationId) {
        if (this.listeners[conversationId]) {
            window.Echo.leave(`conversation.${conversationId}`);
            delete this.listeners[conversationId];
        }

        if (this.currentConversationId === conversationId) {
            this.currentConversationId = null;
        }
    }

    /**
     * Disconnect from all channels
     */
    disconnect() {
        Object.keys(this.listeners).forEach(conversationId => {
            this.stopListening(conversationId);
        });
        window.Echo.disconnect();
    }
}

/**
 * Example Usage:
 */

// Initialize chat manager
const chatManager = new ChatManager();

// Set auth token (get from your auth system)
// chatManager.setAuthToken('your-sanctum-token-here');

// Example: Listen to conversation with ID 1
// chatManager.listenToConversation(1, {
//     onMessage: (data) => {
//         console.log('New message received:', data);
//         // Update UI with new message
//         // data contains: id, conversation_id, sender_id, sender, message, is_read, created_at
//     }
// });

// Example: Send a message via API
// async function sendMessage(conversationId, message) {
//     const response = await fetch(`/api/chat/conversations/${conversationId}/messages`, {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
//             'Accept': 'application/json',
//         },
//         body: JSON.stringify({ message }),
//     });
//     return await response.json();
// }

// Example: Get conversations list
// async function getConversations() {
//     const response = await fetch('/api/chat/conversations', {
//         headers: {
//             'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
//             'Accept': 'application/json',
//         },
//     });
//     return await response.json();
// }

// Example: Get messages for a conversation
// async function getMessages(conversationId, page = 1) {
//     const response = await fetch(`/api/chat/conversations/${conversationId}/messages?page=${page}`, {
//         headers: {
//             'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
//             'Accept': 'application/json',
//         },
//     });
//     return await response.json();
// }

// Export for use in other files
export default ChatManager;
export { chatManager };

