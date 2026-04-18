<?php

namespace Tests\Feature\Governance;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class FilamentArabicLocaleTest extends BasePermissionTestCase
{
    #[Test]
    public function admin_login_uses_rtl_hook_when_panel_locale_is_arabic(): void
    {
        config(['governance.panel_locale' => 'ar']);

        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee("setAttribute('dir', 'rtl')", false);
        $response->assertDontSee('Rakez Governance', false);
    }

    #[Test]
    public function admin_login_can_render_in_english_when_panel_locale_is_english(): void
    {
        config(['governance.panel_locale' => 'en']);

        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Rakez Governance', false);
        $response->assertDontSee("setAttribute('dir', 'rtl')", false);
    }
}
