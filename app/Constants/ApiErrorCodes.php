<?php

namespace App\Constants;

/**
 * Centralized API error codes for consistent client handling and monitoring.
 * Use these constants when returning error responses via ApiResponse::error().
 *
 * @see \App\Http\Responses\ApiResponse
 * @see docs/ar/ERROR_CODES_REFERENCE.md for full reference
 */
final class ApiErrorCodes
{
    /** Validation failed (422). */
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    /** Not authenticated (401). */
    public const UNAUTHORIZED = 'UNAUTHORIZED';

    /** Authenticated but not allowed (403). */
    public const FORBIDDEN = 'FORBIDDEN';

    /** Resource not found (404). */
    public const NOT_FOUND = 'NOT_FOUND';

    /** Conflict / duplicate (409). */
    public const CONFLICT = 'CONFLICT';

    /** Server error (500). */
    public const SERVER_ERROR = 'SERVER_ERROR';

    /** User account suspended / inactive (401). */
    public const ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';
}
