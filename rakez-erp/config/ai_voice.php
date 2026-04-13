<?php

return [
    'enabled' => env('AI_VOICE_ENABLED', true),

    'upload' => [
        // Keep uploads short and bounded for push-to-talk MVP.
        'max_kb' => (int) env('AI_VOICE_UPLOAD_MAX_KB', 12288),
        'allowed_mimetypes' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/mp4',
            'audio/x-m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/webm',
            'audio/ogg',
            'video/webm',
        ],
        'allowed_extensions' => ['mp3', 'wav', 'm4a', 'mp4', 'webm', 'ogg'],
    ],

    'transcription' => [
        'model' => env('AI_VOICE_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'language' => env('AI_VOICE_TRANSCRIPTION_LANGUAGE', 'ar'),
        'prompt' => env('AI_VOICE_TRANSCRIPTION_PROMPT', 'Transcribe spoken Arabic and English accurately. Preserve names, numbers, and short commands.'),
        'max_transcript_characters' => (int) env('AI_VOICE_MAX_TRANSCRIPT_CHARS', 2000),
    ],

    'speech' => [
        'default_voice' => env('AI_VOICE_TTS_VOICE', 'alloy'),
        'default_format' => env('AI_VOICE_TTS_FORMAT', 'mp3'),
        'model' => env('AI_VOICE_TTS_MODEL', 'gpt-4o-mini-tts'),
        'max_input_characters' => (int) env('AI_VOICE_TTS_MAX_INPUT_CHARS', 2000),
        'allowed_voices' => ['alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse'],
        'allowed_formats' => ['mp3', 'wav', 'opus'],
    ],
];
