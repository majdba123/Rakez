# API Examples: AI Assistant

## Ask (Stateless)
`POST /api/ai/ask`

**Request Body:**
```json
{
    "question": "How many units are available in Riyadh?",
    "section": "units"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "There are currently 15 available units in Riyadh across 3 projects.",
        "session_id": "uuid-123",
        "suggestions": [
            "Show me units in Jeddah",
            "What is the average price?"
        ]
    }
}
```

## Chat (Session-based)
`POST /api/ai/chat`

**Request Body:**
```json
{
    "message": "What is the floor area of the first one?",
    "session_id": "uuid-123",
    "section": "units"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "The first unit (Unit #101) has a floor area of 120 sqm.",
        "session_id": "uuid-123"
    }
}
```

## Conversations
`GET /api/ai/conversations`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "session_id": "uuid-123",
            "last_message": "The first unit...",
            "created_at": "2026-01-26T10:00:00Z"
        }
    ]
}
```
