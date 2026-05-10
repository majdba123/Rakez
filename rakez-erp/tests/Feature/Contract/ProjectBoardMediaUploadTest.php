<?php

namespace Tests\Feature\Contract;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectBoardMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $pmStaff;
    protected User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $this->pmStaff = User::factory()->create(['type' => 'project_management']);
        $this->pmStaff->syncRolesFromType();

        $this->salesUser = User::factory()->create(['type' => 'sales']);
        $this->salesUser->syncRolesFromType();
    }

    public function test_valid_upload_creates_project_media_rows(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->pmStaff, 'sanctum')
            ->post("/api/contracts/{$contract->id}/board-images", [
                'kind' => 'signboard_image',
                'note' => 'North entrance boards.',
                'images' => [
                    UploadedFile::fake()->image('board-1.jpg'),
                    UploadedFile::fake()->image('board-2.png'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.department', 'boards')
            ->assertJsonPath('data.0.type', 'image')
            ->assertJsonPath('data.0.kind', 'signboard_image');

        $this->assertDatabaseCount('project_media', 2);
        $this->assertDatabaseHas('project_media', [
            'contract_id' => $contract->id,
            'department' => 'boards',
            'type' => 'image',
            'kind' => 'signboard_image',
            'note' => 'North entrance boards.',
        ]);
    }

    public function test_invalid_file_fails_validation(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->pmStaff, 'sanctum')
            ->post("/api/contracts/{$contract->id}/board-images", [
                'images' => [
                    UploadedFile::fake()->create('not-image.pdf', 100, 'application/pdf'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    public function test_uploaded_image_response_contains_usable_url_and_metadata(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->pmStaff, 'sanctum')
            ->post("/api/contracts/{$contract->id}/board-images", [
                'kind' => 'board_image',
                'images' => [
                    UploadedFile::fake()->image('board.jpg'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.0.department', 'boards')
            ->assertJsonPath('data.0.kind', 'board_image');

        $url = $response->json('data.0.url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/storage/projects/' . $contract->id . '/boards/', $url);
    }

    public function test_unauthorized_user_cannot_upload_board_images(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->post("/api/contracts/{$contract->id}/board-images", [
                'images' => [
                    UploadedFile::fake()->image('board.jpg'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertForbidden();
    }
}
