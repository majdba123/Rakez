<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_require_authentication(): void
    {
        $response = $this->getJson('/api/tasks/sections');
        $response->assertStatus(401);
    }

    public function test_sections_returns_value_and_label_for_authenticated_user(): void
    {
        $user = User::factory()->create(['type' => 'marketing', 'is_active' => true]);
        User::factory()->create(['type' => 'sales', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks/sections');

        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $values = array_column($data, 'value');
        $this->assertContains('marketing', $values);
        $this->assertContains('sales', $values);
        $byValue = array_column($data, 'label', 'value');
        $this->assertSame('قسم التسويق', $byValue['marketing'] ?? null);
        $this->assertSame('قسم المبيعات', $byValue['sales'] ?? null);
    }

    public function test_users_by_section_require_authentication(): void
    {
        $response = $this->getJson('/api/tasks/sections/marketing/users');
        $response->assertStatus(401);
    }

    public function test_users_by_section_returns_only_users_in_that_section(): void
    {
        $marketing1 = User::factory()->create([
            'name' => 'Marketing User 1',
            'type' => 'marketing',
            'is_active' => true,
        ]);
        $marketing2 = User::factory()->create([
            'name' => 'Marketing User 2',
            'type' => 'marketing',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Sales User',
            'type' => 'sales',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketing1, 'sanctum')
            ->getJson('/api/tasks/sections/marketing/users');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $users = $response->json('data');
        $this->assertCount(2, $users);
        $ids = array_column($users, 'id');
        $this->assertContains($marketing1->id, $ids);
        $this->assertContains($marketing2->id, $ids);
        foreach ($users as $u) {
            $this->assertArrayHasKey('id', $u);
            $this->assertArrayHasKey('name', $u);
            $this->assertArrayHasKey('email', $u);
        }
    }

    public function test_users_by_section_excludes_inactive_users(): void
    {
        $active = User::factory()->create([
            'type' => 'marketing',
            'is_active' => true,
        ]);
        User::factory()->create([
            'type' => 'marketing',
            'is_active' => false,
        ]);

        $response = $this->actingAs($active, 'sanctum')
            ->getJson('/api/tasks/sections/marketing/users');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_users_by_section_returns_empty_for_section_with_no_users(): void
    {
        $user = User::factory()->create(['type' => 'marketing', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks/sections/nonexistent_section/users');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);
    }
}
