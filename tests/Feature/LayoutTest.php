<?php

namespace Tests\Feature;

use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_theme_defaults_to_device_preference(): void
    {
        $user = $this->admin();
        $this->assertNull($user->theme);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('data-user-theme="system"', false)
            ->assertSee('name="crm-theme-url" content="'.route('preferences.theme').'"', false);
    }

    public function test_theme_choice_is_saved_to_account_and_rendered_server_side(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->postJson(route('preferences.theme'), ['theme' => 'dark'])
            ->assertOk()->assertJson(['theme' => 'dark']);
        $this->assertSame('dark', $user->fresh()->theme);

        // Phiên mới (thiết bị khác) vẫn mở giao diện tối ngay từ HTML
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()
            ->assertSee('data-bs-theme="dark" data-user-theme="dark"', false);
    }

    public function test_system_choice_clears_saved_theme(): void
    {
        $user = $this->admin();
        $user->forceFill(['theme' => 'dark'])->save();

        $this->actingAs($user)->postJson(route('preferences.theme'), ['theme' => 'system'])
            ->assertOk()->assertJson(['theme' => 'system']);
        $this->assertNull($user->fresh()->theme);
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->postJson(route('preferences.theme'), ['theme' => 'neon'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('preferences.theme'), ['theme' => '<script>'])->assertStatus(422);
        $this->assertNull($user->fresh()->theme);
    }

    public function test_guests_cannot_save_theme(): void
    {
        $this->admin();
        $this->postJson(route('preferences.theme'), ['theme' => 'dark'])->assertUnauthorized();
    }

    public function test_theme_change_is_not_written_to_activity_log(): void
    {
        $user = $this->admin();
        $before = \App\Models\ActivityLog::withoutGlobalScopes()->count();

        $this->actingAs($user)->postJson(route('preferences.theme'), ['theme' => 'light'])->assertOk();
        $this->assertSame($before, \App\Models\ActivityLog::withoutGlobalScopes()->count());
    }

    public function test_sidebar_has_menu_search(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('data-nav-search', false)
            ->assertSee('Tìm chức năng...')
            ->assertSee('Không có chức năng phù hợp');

        $user->forceFill(['locale' => 'en'])->save();
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()
            ->assertSee('Find a feature...')
            ->assertSee('No matching features');
    }
}
