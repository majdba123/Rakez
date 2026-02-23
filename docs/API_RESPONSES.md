# API Response Standards

All JSON API endpoints should use `App\Http\Responses\ApiResponse` for consistent response shape and status codes.

## Response shape

- **Success**: `{ "success": true, "message": "...", "data": ... }` (optional `meta` for pagination)
- **Error**: `{ "success": false, "message": "...", "error_code": "..." }` (optional `errors` for validation)

## Pagination

List endpoints return pagination under `meta.pagination` with this canonical shape:

| Key | Type | Description |
|-----|------|--------------|
| `total` | int | Total number of items |
| `count` | int | Number of items in current page |
| `per_page` | int | Items per page |
| `current_page` | int | Current page number |
| `total_pages` | int | Last page number (same as `last_page`) |
| `has_more_pages` | bool | Whether more pages exist |

Use `ApiResponse::getPerPage($request, $default, $max)` for `per_page` validation (default 15, max 100). Use `ApiResponse::paginationMeta($paginator)` to build the meta array, or `ApiResponse::paginated($paginator, $message)` for a full paginated response.
