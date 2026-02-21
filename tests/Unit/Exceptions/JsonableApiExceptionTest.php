<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\CommissionException;
use Tests\TestCase;

class JsonableApiExceptionTest extends TestCase
{
    public function test_concrete_exception_has_message_and_error_code_and_status(): void
    {
        $e = CommissionException::notFound();

        $this->assertSame('العمولة غير موجودة', $e->getMessage());
        $this->assertSame('COMM_009', $e->getErrorCode());
        $this->assertSame(404, $e->getCode());
    }

    public function test_render_returns_json_with_expected_shape(): void
    {
        $e = CommissionException::notFound();

        $response = $e->render();

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('العمولة غير موجودة', $data['message']);
        $this->assertSame('COMM_009', $data['error_code']);
    }
}
