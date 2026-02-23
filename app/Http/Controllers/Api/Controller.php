<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Responses\ApiResponse;

/**
 * Base controller for API endpoints in this folder.
 * All controllers under App\Http\Controllers\Api must extend this class (not the root Controller)
 * so the Api folder is self-contained. Use ApiResponse for all JSON responses.
 */
abstract class Controller extends BaseController
{
    // Use ApiResponse::success(), ApiResponse::error(), etc. in actions.
    // Optional: add protected helpers that delegate to ApiResponse if needed.
}
