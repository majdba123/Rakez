# AI Assistant API Reference
## Rakez ERP - AI-Powered Assistant Documentation

**Version:** 1.0  
**Last Updated:** January 26, 2026  
**AI Model:** GPT-4 (OpenAI)

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Authentication](#authentication)
- [Endpoints](#endpoints)
  - [Ask Question (Stateless)](#ask-question-stateless)
  - [Chat (Session-based)](#chat-session-based)
  - [List Conversations](#list-conversations)
  - [Delete Conversation](#delete-conversation)
  - [Get Available Sections](#get-available-sections)
- [Sections System](#sections-system)
- [Context System](#context-system)
- [Budget Management](#budget-management)
- [Error Handling](#error-handling)
- [Best Practices](#best-practices)

---

## Overview

The AI Assistant is an intelligent, context-aware assistant powered by OpenAI GPT-4 that helps users interact with the Rakez ERP system through natural language queries.

### Base URL
```
http://localhost/api/ai
```

### Key Capabilities

- ✅ **Natural Language Understanding**: Ask questions in plain language
- ✅ **Context-Aware Responses**: Provides specific answers based on user data
- ✅ **Session Management**: Maintains conversation history
- ✅ **Permission-Based**: Answers filtered by user capabilities
- ✅ **Budget Control**: Daily token limits per user
- ✅ **Multi-Section Support**: Specialized knowledge areas

---

## Features

### 1. Dynamic Context Building

The AI automatically loads relevant data based on:
- User permissions (via Spatie Permissions)
- Requested section (contracts, units, departments, general)
- Provided context parameters (contract_id, unit_id, etc.)

### 2. Permission-Based Filtering

Responses are tailored to what the user can access:
- Only shows contracts the user owns or has permission to view
- Hides operations the user cannot perform
- Provides role-appropriate suggestions

### 3. Session-Based Conversations

- **Ask**: One-time questions without history
- **Chat**: Follow-up questions with conversation context
- Sessions automatically created and managed

### 4. Suggestion System

Each response includes relevant follow-up suggestions based on:
- Current section
- User's last question
- Available features

---

## Authentication

All AI endpoints require authentication via Bearer token:

```http
Authorization: Bearer YOUR_TOKEN
```

**Required Permission:** None (all authenticated users can use AI)

**Rate Limiting:** 30 requests per minute per user

---

## Endpoints

### Ask Question (Stateless)

**Endpoint:** `POST /ai/ask`

**Description:** Ask a one-time question without maintaining conversation history. Creates a new session ID that can be used for follow-up questions.

**Request Body:**
```json
{
    "question": "How do I create a new contract?",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| question | string | Yes | The question to ask (max 1000 characters) |
| section | string | No | Section key (`contracts`, `units`, `departments`, `general`) |
| context | object | No | Additional context parameters |

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "message": "To create a new contract in Rakez ERP:\n\n1. Navigate to the Contracts section\n2. Click on 'Create New Contract'\n3. Fill in required fields:\n   - Project Name\n   - Developer Name and Contact\n   - Location (City and District)\n   - Units details\n4. Submit for approval\n\nThe contract will be in 'pending' status until approved by an admin.",
        "session_id": "550e8400-e29b-41d4-a716-446655440000",
        "conversation_id": 1523,
        "suggestions": [
            "How do I create a new contract?",
            "Why is my contract pending?",
            "What are contract statuses?",
            "How do I approve a contract?"
        ],
        "error_code": null
    }
}
```

**Budget Exceeded Error (429):**
```json
{
    "success": false,
    "error_code": "ai_budget_exceeded",
    "message": "Daily token budget exceeded (12000/12000). Please try again later."
}
```

**Example with Context:**
```bash
curl -X POST http://localhost/api/ai/ask \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "What is the status of this contract?",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
  }'
```

**Context-Aware Response:**
```json
{
    "success": true,
    "data": {
        "message": "Contract #1 (Al Noor Towers):\n\n**Status**: Approved\n**Project Name**: Al Noor Towers\n**Developer**: Rakez Development\n**Location**: Riyadh, Al Malqa\n**Total Units**: 120\n\nThis contract has been approved and is ready for operations. You can now:\n- Add or edit units\n- Upload department data\n- Manage project details\n\nWould you like to know more about any specific aspect?",
        "session_id": "660f9511-f3ac-52e5-b827-557766551111",
        "conversation_id": 1524,
        "suggestions": [
            "How do I add units to this contract?",
            "Show me unit pricing for this project",
            "What department data is missing?"
        ],
        "error_code": null
    }
}
```

---

### Chat (Session-based)

**Endpoint:** `POST /ai/chat`

**Description:** Continue a conversation using a session_id from a previous ask or chat. Maintains conversation history for natural follow-up questions.

**Request Body:**
```json
{
    "message": "What happens after I submit it?",
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "section": "contracts",
    "context": {}
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| message | string | Yes | Follow-up message/question |
| session_id | string | Yes | UUID from previous ask/chat response |
| section | string | No | Section key (should match initial ask) |
| context | object | No | Additional context parameters |

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "message": "After you submit a new contract:\n\n1. The contract status will be set to 'pending'\n2. An admin user will receive a notification\n3. Admin reviews the contract details\n4. Admin can either:\n   - Approve it (status becomes 'approved')\n   - Reject it with comments\n5. Once approved, you can proceed to add:\n   - Second party data\n   - Units information\n   - Department data (boards, photography, montage)\n\nYou'll receive notifications about the approval status.",
        "session_id": "550e8400-e29b-41d4-a716-446655440000",
        "conversation_id": 1523,
        "suggestions": [
            "How do I add units to a contract?",
            "What is second party data?",
            "Can I edit a pending contract?"
        ],
        "error_code": null
    }
}
```

**Conversation Flow Example:**

```bash
# 1. Initial question
POST /ai/ask
{
    "question": "How do I create a contract?"
}
# Returns: session_id = "abc-123"

# 2. Follow-up question
POST /ai/chat
{
    "message": "What happens after I submit it?",
    "session_id": "abc-123"
}

# 3. Another follow-up
POST /ai/chat
{
    "message": "How long does approval take?",
    "session_id": "abc-123"
}
```

---

### List Conversations

**Endpoint:** `GET /ai/conversations`

**Description:** Get a paginated list of all conversation sessions for the authenticated user.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Results per page (default: 20) |
| section | string | No | Filter by section key |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "session_id": "550e8400-e29b-41d4-a716-446655440000",
            "section": "contracts",
            "first_question": "How do I create a new contract?",
            "last_message": "What happens after I submit it?",
            "messages_count": 3,
            "created_at": "2026-01-26T10:00:00Z",
            "updated_at": "2026-01-26T10:05:00Z"
        },
        {
            "session_id": "660f9511-f3ac-52e5-b827-557766551111",
            "section": "units",
            "first_question": "How to upload units via CSV?",
            "last_message": "What CSV format is required?",
            "messages_count": 2,
            "created_at": "2026-01-25T14:30:00Z",
            "updated_at": "2026-01-25T14:32:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 45
    }
}
```

**Filter by Section:**
```bash
GET /ai/conversations?section=contracts&per_page=10
```

---

### Delete Conversation

**Endpoint:** `DELETE /ai/conversations/{sessionId}`

**Description:** Delete a conversation session and all its messages. This action cannot be undone.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| sessionId | string | UUID of the session to delete |

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "deleted": true
    }
}
```

**Not Found Error (404):**
```json
{
    "success": false,
    "error_code": "conversation_not_found",
    "message": "Conversation session not found or you don't have access to it."
}
```

**Example:**
```bash
curl -X DELETE http://localhost/api/ai/conversations/550e8400-e29b-41d4-a716-446655440000 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Get Available Sections

**Endpoint:** `GET /ai/sections`

**Description:** Get list of available AI assistant sections based on user permissions. Only sections the user has access to will be returned.

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "key": "contracts",
            "title": "Contracts",
            "description": "Ask about contract creation, approval, and management",
            "icon": "document-text",
            "suggestions": [
                "How do I create a new contract?",
                "Why is my contract pending?",
                "What are contract statuses?",
                "How do I approve a contract?"
            ]
        },
        {
            "key": "units",
            "title": "Units",
            "description": "Questions about unit management, pricing, and CSV uploads",
            "icon": "home",
            "suggestions": [
                "How do I add units to a contract?",
                "How to upload units via CSV?",
                "How are unit prices calculated?"
            ]
        },
        {
            "key": "departments",
            "title": "Departments",
            "description": "Information about Boards, Photography, and Montage departments",
            "icon": "briefcase",
            "suggestions": [
                "What is the Montage department?",
                "How do I upload photography work?",
                "What are boards department requirements?"
            ]
        },
        {
            "key": "general",
            "title": "General",
            "description": "General system questions and workflows",
            "icon": "information-circle",
            "suggestions": [
                "How do notifications work?",
                "What are user roles?",
                "How do I change my profile?"
            ]
        }
    ]
}
```

**Permission-Based Filtering:**

Different users see different sections based on their permissions:

**Admin User:**
- ✅ Contracts (has `contracts.view_all`)
- ✅ Units (has `units.view`)
- ✅ Departments (has all department permissions)
- ✅ General (always available)

**Sales Employee:**
- ❌ Contracts (lacks `contracts.view`)
- ❌ Units (lacks `units.view`)
- ❌ Departments (lacks department permissions)
- ✅ General (always available)

---

## Sections System

### Available Sections

#### 1. Contracts Section

**Key:** `contracts`  
**Required Permission:** `contracts.view`  
**Context Parameters:** `contract_id`

**Example Questions:**
- "How do I create a new contract?"
- "What are the contract statuses?"
- "Why is my contract pending?"
- "How do I approve a contract?"
- "What is the difference between approved and ready status?"

**Context Example:**
```json
{
    "question": "What is the status of this contract?",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
}
```

#### 2. Units Section

**Key:** `units`  
**Required Permission:** `units.view`  
**Context Parameters:** `contract_id`, `unit_id`

**Example Questions:**
- "How do I add units to a contract?"
- "How to upload units via CSV?"
- "What CSV format is required?"
- "How are unit prices calculated?"
- "Can I edit units after contract approval?"

**Context Example:**
```json
{
    "question": "Show me details of this unit",
    "section": "units",
    "context": {
        "contract_id": 1,
        "unit_id": 101
    }
}
```

#### 3. Departments Section

**Key:** `departments`  
**Required Permissions:** 
- `departments.boards.view` OR
- `departments.photography.view` OR
- `departments.montage.view`

**Example Questions:**
- "What is the Montage department responsible for?"
- "How do I upload photography work?"
- "What are boards department requirements?"
- "What is the workflow for department approvals?"

#### 4. General Section

**Key:** `general`  
**Required Permission:** None (always available)

**Example Questions:**
- "How do notifications work?"
- "What are the different user roles?"
- "How do I reset my password?"
- "What browsers are supported?"

---

## Context System

### How Context Works

The AI Assistant builds context dynamically based on:

1. **User Capabilities**: Loaded from Spatie Permissions
2. **Section**: Determines which knowledge base to use
3. **Context Parameters**: Additional data like contract_id, unit_id
4. **Conversation History**: Previous messages in the session

### Context Building Example

For a user asking about a specific contract:

```json
{
    "question": "What is the status of this project?",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
}
```

**Behind the scenes:**

1. **Permission Check**: Verify user can view contract #1
2. **Data Loading**: Load contract details from database
3. **Context Assembly**:
   ```
   User Context:
   - User ID: 5
   - Name: Mohammed Ali
   - Type: project_management
   - Capabilities: [contracts.view, contracts.create, units.view, units.edit, ...]

   Contract #1 Context:
   - Project Name: Al Noor Towers
   - Developer: Rakez Development
   - Status: approved
   - Location: Riyadh, Al Malqa
   - Total Units: 120
   - User's Contracts: 15
   ```
4. **AI Response**: Generated with full context

### Context Parameters

#### Contracts Section
- `contract_id`: Loads specific contract details

#### Units Section
- `contract_id`: Loads project context
- `unit_id`: Loads specific unit details

#### Departments Section
- `contract_id`: Loads project and department data

#### General Section
- No specific parameters (uses user profile only)

---

## Budget Management

### Daily Token Limits

Each user has a daily token budget to prevent abuse:

- **Default Limit**: 12,000 tokens per day
- **Configurable**: Via `AI_ASSISTANT_DAILY_TOKEN_BUDGET` env variable

### Token Counting

Tokens are consumed from:
- User messages
- AI responses
- System prompts and context

### Budget Exceeded Response

When a user exceeds their daily limit:

```json
{
    "success": false,
    "error_code": "ai_budget_exceeded",
    "message": "Daily token budget exceeded (12000/12000). Please try again later."
}
```

**HTTP Status:** 429 (Too Many Requests)

### Checking Budget Usage

Users can monitor their usage by tracking the `tokens_used` field in responses (if exposed) or by counting requests until they hit the limit.

### Budget Reset

Budgets reset at midnight (server timezone).

---

## Error Handling

### Error Response Format

```json
{
    "success": false,
    "error_code": "error_type",
    "message": "Human-readable error description"
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `ai_disabled` | 503 | AI Assistant is disabled in configuration |
| `ai_budget_exceeded` | 429 | Daily token budget exceeded |
| `conversation_not_found` | 404 | Session ID not found or no access |
| `invalid_section` | 400 | Section key is invalid |
| `context_validation_failed` | 400 | Context parameters are invalid |
| `openai_error` | 500 | Error communicating with OpenAI API |

### Common Errors

#### AI Disabled
```json
{
    "success": false,
    "error_code": "ai_disabled",
    "message": "AI Assistant is currently disabled."
}
```

#### Invalid Session
```json
{
    "success": false,
    "error_code": "conversation_not_found",
    "message": "Conversation session not found or you don't have access to it."
}
```

#### Context Validation Failed
```json
{
    "success": false,
    "error_code": "context_validation_failed",
    "message": "Invalid context parameter: contract_id must be an integer"
}
```

---

## Best Practices

### 1. Choose the Right Endpoint

**Use `ask` when:**
- Starting a new topic
- Asking unrelated questions
- Don't need conversation history

**Use `chat` when:**
- Following up on previous question
- Need AI to remember context
- Building on earlier responses

### 2. Provide Context

Always include relevant context parameters:

```json
// Good
{
    "question": "What is this project's status?",
    "section": "contracts",
    "context": {
        "contract_id": 1
    }
}

// Bad - AI can't load specific data
{
    "question": "What is this project's status?",
    "section": "contracts"
}
```

### 3. Use Appropriate Sections

Match your question to the right section:

```json
// Good - contracts question in contracts section
{
    "question": "How do I create a contract?",
    "section": "contracts"
}

// Bad - contracts question in units section
{
    "question": "How do I create a contract?",
    "section": "units"
}
```

### 4. Monitor Budget Usage

- Track failed requests with `ai_budget_exceeded`
- Implement user-facing budget indicators
- Educate users about efficient question asking

### 5. Handle Errors Gracefully

```javascript
try {
    const response = await fetch('/api/ai/ask', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            question: userQuestion,
            section: currentSection
        })
    });
    
    const data = await response.json();
    
    if (!data.success) {
        // Handle specific error codes
        switch (data.error_code) {
            case 'ai_budget_exceeded':
                showMessage('Daily AI usage limit reached. Try again tomorrow.');
                break;
            case 'ai_disabled':
                showMessage('AI Assistant is temporarily unavailable.');
                break;
            default:
                showMessage(data.message);
        }
        return;
    }
    
    // Display AI response
    displayResponse(data.data.message);
    saveSess ionId(data.data.session_id);
    showSuggestions(data.data.suggestions);
    
} catch (error) {
    console.error('AI request failed:', error);
    showMessage('Failed to get AI response. Please try again.');
}
```

### 6. Implement Suggestions UI

Use the returned suggestions to guide users:

```javascript
function showSuggestions(suggestions) {
    const container = document.getElementById('suggestions');
    container.innerHTML = suggestions.map(suggestion => `
        <button onclick="askQuestion('${suggestion}')">
            ${suggestion}
        </button>
    `).join('');
}
```

---

## Example Integration

### Complete React Component Example

```jsx
import React, { useState } from 'react';
import axios from 'axios';

function AIAssistant() {
    const [question, setQuestion] = useState('');
    const [messages, setMessages] = useState([]);
    const [sessionId, setSessionId] = useState(null);
    const [section, setSection] = useState('general');
    const [loading, setLoading] = useState(false);

    const askQuestion = async () => {
        if (!question.trim()) return;

        setLoading(true);
        const userMessage = { role: 'user', content: question };
        setMessages([...messages, userMessage]);

        try {
            const endpoint = sessionId ? '/api/ai/chat' : '/api/ai/ask';
            const payload = {
                [sessionId ? 'message' : 'question']: question,
                section,
                ...(sessionId && { session_id: sessionId })
            };

            const response = await axios.post(endpoint, payload, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                }
            });

            const { message, session_id, suggestions } = response.data.data;
            
            setMessages([...messages, userMessage, {
                role: 'assistant',
                content: message,
                suggestions
            }]);
            
            if (!sessionId) {
                setSessionId(session_id);
            }
            
            setQuestion('');
        } catch (error) {
            if (error.response?.data?.error_code === 'ai_budget_exceeded') {
                alert('Daily AI usage limit reached. Try again tomorrow.');
            } else {
                console.error('AI error:', error);
                alert('Failed to get response. Please try again.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="ai-assistant">
            <div className="section-selector">
                <select value={section} onChange={(e) => setSection(e.target.value)}>
                    <option value="general">General</option>
                    <option value="contracts">Contracts</option>
                    <option value="units">Units</option>
                    <option value="departments">Departments</option>
                </select>
            </div>

            <div className="messages">
                {messages.map((msg, idx) => (
                    <div key={idx} className={`message ${msg.role}`}>
                        <div className="content">{msg.content}</div>
                        {msg.suggestions && (
                            <div className="suggestions">
                                {msg.suggestions.map((suggestion, i) => (
                                    <button
                                        key={i}
                                        onClick={() => setQuestion(suggestion)}
                                    >
                                        {suggestion}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="input-area">
                <input
                    type="text"
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && askQuestion()}
                    placeholder="Ask a question..."
                    disabled={loading}
                />
                <button onClick={askQuestion} disabled={loading || !question.trim()}>
                    {loading ? 'Thinking...' : 'Ask'}
                </button>
            </div>
        </div>
    );
}

export default AIAssistant;
```

---

## Configuration

### Environment Variables

```env
# Enable/disable AI Assistant
AI_ASSISTANT_ENABLED=true

# OpenAI API Key
OPENAI_API_KEY=sk-...

# Daily token budget per user
AI_ASSISTANT_DAILY_TOKEN_BUDGET=12000

# OpenAI model to use
AI_ASSISTANT_MODEL=gpt-4

# Rate limiting (requests per minute)
AI_ASSISTANT_RATE_LIMIT=30
```

### Config Files

**Location:** `config/ai_assistant.php`

```php
return [
    'enabled' => env('AI_ASSISTANT_ENABLED', true),
    'daily_user_token_budget' => env('AI_ASSISTANT_DAILY_TOKEN_BUDGET', 12000),
    'model' => env('AI_ASSISTANT_MODEL', 'gpt-4'),
    'max_tokens_per_response' => 1000,
    'temperature' => 0.7,
];
```

---

## Testing

### Running AI Tests

```bash
# All AI tests
php artisan test tests/Feature/AI/

# Specific test
php artisan test --filter=AIAssistantTest
```

### Example Test

```php
public function test_ask_returns_response_with_suggestions()
{
    $user = User::factory()->create(['type' => 'admin']);
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/ai/ask', [
            'question' => 'How do I create a contract?',
            'section' => 'contracts',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'message',
                'session_id',
                'conversation_id',
                'suggestions',
                'error_code',
            ],
        ]);
}
```

---

## Postman Collection

Import the complete Postman collection:
- **File**: `POSTMAN_AI_ASSISTANT_COLLECTION.json`
- **Location**: `docs/POSTMAN_AI_ASSISTANT_COLLECTION.json`

The collection includes:
- ✅ All 5 endpoints
- ✅ Example requests for each section
- ✅ Context examples
- ✅ Error scenarios
- ✅ Environment variables setup

---

## Support & Additional Resources

- **Arabic Documentation**: [SALES_AI_REPORT_AR.md](./SALES_AI_REPORT_AR.md)
- **Sales API Reference**: [API_EXAMPLES_SALES.md](./API_EXAMPLES_SALES.md)
- **AI Operations Guide**: [AI_ASSISTANT_OPERATIONS.md](./AI_ASSISTANT_OPERATIONS.md)

---

**Last Updated:** January 26, 2026  
**Version:** 1.0  
**Maintained by:** Rakez ERP Development Team
