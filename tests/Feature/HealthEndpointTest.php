<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    #[Test]
    public function api_health_returns_200_and_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
    }

    #[Test]
    public function up_route_returns_200(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
