<?php

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_returns_json_with_expected_structure(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Done');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Done', $data['message']);
        $this->assertSame(['id' => 1], $data['data']);
    }

    public function test_error_returns_json_with_expected_structure(): void
    {
        $response = ApiResponse::error('Something failed', 400, 'ERR_CODE');

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Something failed', $data['message']);
        $this->assertSame('ERR_CODE', $data['error_code']);
    }

    public function test_validation_error_returns_422_with_errors(): void
    {
        $response = ApiResponse::validationError(['field' => ['The field is invalid.']]);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error_code']);
        $this->assertSame(['field' => ['The field is invalid.']], $data['errors']);
    }

    public function test_get_per_page_uses_request_input(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 25]);
        $this->assertSame(25, ApiResponse::getPerPage($request));
    }

    public function test_get_per_page_respects_max(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 500]);
        $this->assertSame(100, ApiResponse::getPerPage($request));
    }

    public function test_paginated_returns_consistent_meta_structure(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new LengthAwarePaginator($items, 10, 5, 1);

        $response = ApiResponse::paginated($paginator);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(10, $data['meta']['pagination']['total']);
        $this->assertSame(5, $data['meta']['pagination']['per_page']);
        $this->assertSame(1, $data['meta']['pagination']['current_page']);
        $this->assertSame(2, $data['meta']['pagination']['total_pages']);
        $this->assertTrue($data['meta']['pagination']['has_more_pages']);
    }
}
