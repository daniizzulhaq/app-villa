<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_login_and_lands_on_home(): void
    {
        $user = $this->makeUser(UserRole::Master, ['password' => 'rahasia123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser(UserRole::Staff, ['password' => 'rahasia123', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = $this->makeUser(UserRole::Staff);
        $this->actingAs($user)->get(route('staff.calendar'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('staff.calendar'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_each_role_dashboard_renders(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master))->get('/dashboard')->assertOk()->assertSee('Total net profit');
        $this->actingAs($this->makeUser(UserRole::Owner))->get('/dashboard')->assertOk();
        // Staff langsung ke Kalender
        $this->actingAs($this->makeUser(UserRole::Staff))->get('/dashboard')->assertRedirect(route('staff.calendar'));
    }

    public function test_user_can_login_with_username(): void
    {
        $user = $this->makeUser(UserRole::Owner, ['password' => 'password123', 'username' => 'owner']);

        $this->post('/login', ['email' => 'Owner', 'password' => 'password123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_language_can_be_switched_from_login_page_and_is_saved_per_user(): void
    {
        // Tamu: pilih bahasa di halaman login
        $this->get('/login')->assertSee('Masuk ke akun Anda');
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->get('/login')->assertSee('Sign in to your account')->assertSee('lang="en"', false);
        $this->post(route('locale.update'), ['locale' => 'ja'])->assertRedirect();
        $this->get('/login')->assertSee('アカウントにログイン');

        // Bahasa tidak dikenal ditolak
        $this->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');

        // Pengguna: pilihan disimpan di akun, ikut terbawa ke sesi berikutnya
        $user = $this->makeUser(UserRole::Master);
        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'de']);
        $this->assertSame('de', $user->fresh()->locale);
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()->assertSee('Gesamter Nettogewinn');

        // Pesan validasi ikut bahasa pengguna
        $this->actingAs($user->fresh())->post(route('villas.store'), ['name' => ''])->assertSessionHasErrors('name');
    }
}
