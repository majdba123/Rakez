<?php

namespace Tests\Feature\Marketing;

use App\Events\Marketing\ImageUploadedEvent;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MarketingImageUploadNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_media_and_sends_notifications_when_image_is_uploaded(): void
    {
        $contract = Contract::factory()->create();

        $marketingA = User::factory()->create(['type' => 'marketing']);
        $marketingB = User::factory()->create(['type' => 'marketing']);

        event(new ImageUploadedEvent($contract->id, 'https://cdn.example.com/asset.jpg', 'montage'));

        $this->assertDatabaseHas('project_media', [
            'contract_id' => $contract->id,
            'url' => 'https://cdn.example.com/asset.jpg',
            'department' => 'montage',
            'type' => 'image',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $marketingA->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $marketingB->id,
            'status' => 'pending',
        ]);
    }
}
