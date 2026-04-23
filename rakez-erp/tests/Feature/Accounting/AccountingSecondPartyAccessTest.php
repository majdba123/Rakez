<?php

namespace Tests\Feature\Accounting;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AccountingSecondPartyAccessTest extends BasePermissionTestCase
{
    #[Test]
    public function second_parties_accessible_by_accounting_staff(): void
    {
        $user = $this->createUserWithType('accounting');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/second-party-data/second-parties');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function contracts_by_email_accessible_by_accounting_staff(): void
    {
        $user = $this->createUserWithType('accounting');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/second-party-data/contracts-by-email?email=test@example.com');

        $this->assertNotEquals(403, $response->status());
    }

    #[Test]
    public function second_parties_accessible_by_accountant_staff(): void
    {
        $user = $this->createUserWithType('accountant');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/second-party-data/second-parties');

        $this->assertNotEquals(403, $response->status());
    }
}
