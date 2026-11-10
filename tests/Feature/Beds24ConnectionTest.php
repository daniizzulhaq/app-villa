<?php

namespace Tests\Feature;

use App\Services\Beds24Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class Beds24ConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['propertyme.channel_manager_provider' => 'beds24']);
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.beds24.invite_code' => null, 'services.beds24.token' => null, 'services.beds24.refresh_token' => null]);
    }

    public function test_invite_code_is_exchanged_once_and_credentials_are_encrypted(): void
    {
        Http::fake([
            'api.beds24.com/v2/authentication/setup' => Http::response([
                'token' => 'test-access-secret', 'refreshToken' => 'test-refresh-secret', 'expiresIn' => 3600,
            ]),
            'api.beds24.com/v2/properties*' => Http::response(['data' => [
                ['id' => 101, 'name' => 'Demo property', 'roomTypes' => [['id' => 201, 'name' => 'Demo villa']]],
            ]]),
        ]);

        $this->artisan('propertyme:beds24-connect')
            ->expectsQuestion('Paste the NEW Beds24 invite code (input hidden)', 'test-invite-code')
            ->expectsOutputToContain('Connection verified: 1 properties accessible.')
            ->doesntExpectOutputToContain('test-access-secret')
            ->doesntExpectOutputToContain('test-refresh-secret')
            ->doesntExpectOutputToContain('test-invite-code')
            ->assertSuccessful();

        $stored = Storage::disk('local')->get('integrations/beds24-credentials.enc');
        $this->assertStringNotContainsString('test-access-secret', $stored);
        $credentials = json_decode(Crypt::decryptString($stored), true);
        $this->assertSame('test-refresh-secret', $credentials['refreshToken']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.beds24.com/v2/authentication/setup'
            && $request->method() === 'GET' && $request->hasHeader('code', 'test-invite-code'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.beds24.com/v2/properties?')
            && $request['includeAllRooms'] === 'true'
            && $request->hasHeader('token', 'test-access-secret'));

        app(Beds24Client::class)->properties();
        Http::assertSentCount(3); // one authorization, two reads, no repeat code exchange
    }

    public function test_expired_access_token_is_refreshed_without_losing_refresh_token(): void
    {
        Storage::disk('local')->put('integrations/beds24-credentials.enc', Crypt::encryptString(json_encode([
            'token' => 'expired', 'refreshToken' => 'test-refresh', 'expires_at' => now()->subMinute()->timestamp,
        ])));
        Http::fake([
            'api.beds24.com/v2/authentication/token' => Http::response(['token' => 'new-access', 'expiresIn' => 3600]),
            'api.beds24.com/v2/properties*' => Http::response(['data' => []]),
        ]);

        $this->assertSame([], app(Beds24Client::class)->properties());
        $credentials = json_decode(Crypt::decryptString(Storage::disk('local')->get('integrations/beds24-credentials.enc')), true);
        $this->assertSame('test-refresh', $credentials['refreshToken']);
        $this->assertSame('new-access', $credentials['token']);
        Http::assertSent(fn ($request) => $request->hasHeader('refreshToken', 'test-refresh'));
    }

    public function test_env_invite_connects_without_a_prompt_and_is_not_redeemed_twice(): void
    {
        config(['services.beds24.invite_code' => 'test-env-invite']);
        Http::fake([
            'api.beds24.com/v2/authentication/setup' => Http::response([
                'token' => 'test-env-access', 'refreshToken' => 'test-env-refresh', 'expiresIn' => 3600,
            ]),
            'api.beds24.com/v2/properties*' => Http::response(['data' => []]),
        ]);

        for ($i = 0; $i < 2; $i++) {
            $this->artisan('propertyme:beds24-connect')->doesntExpectOutputToContain('test-env-invite')->assertSuccessful();
        }

        Http::assertSentCount(3);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->url() === 'https://api.beds24.com/v2/authentication/setup'));
    }

    public function test_long_life_token_reads_all_pages_without_authentication_exchange(): void
    {
        config(['services.beds24.token' => 'test-long-life']);
        Http::fakeSequence()
            ->push(['data' => [['id' => 101, 'name' => 'First']], 'pages' => [
                'nextPageExists' => true, 'nextPageLink' => 'https://api.beds24.com/v2/properties?page=2',
            ]])
            ->push(['data' => [['id' => 102, 'name' => 'Second']], 'pages' => ['nextPageExists' => false]]);

        $this->assertSame([101, 102], array_column(app(Beds24Client::class)->properties(), 'id'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.beds24.com/v2/properties?page=2'
            && $request->hasHeader('token', 'test-long-life'));
        Http::assertSentCount(2);
    }

    public function test_pagination_cannot_send_credentials_to_another_host(): void
    {
        config(['services.beds24.token' => 'test-long-life']);
        Http::fake(['api.beds24.com/v2/properties' => Http::response([
            'data' => [], 'pages' => ['nextPageLink' => 'https://untrusted.example/properties?page=2'],
        ])]);
        try {
            app(Beds24Client::class)->properties();
            $this->fail('Untrusted pagination was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Beds24 returned an unexpected pagination URL.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_room_details_and_booking_filters_are_preserved_when_paginating(): void
    {
        config(['services.beds24.token' => 'test-long-life']);
        Http::fakeSequence()
            ->push(['data' => [['id' => 101]], 'pages' => ['nextPageLink' => '/v2/properties?page=2']])
            ->push(['data' => [['id' => 102]], 'pages' => ['nextPageExists' => false]])
            ->push(['data' => [['id' => 301]], 'pages' => ['nextPageLink' => '/v2/bookings?page=2&propertyId=999&roomId=999']])
            ->push(['data' => [['id' => 302]], 'pages' => ['nextPageExists' => false]]);

        $this->assertCount(2, app(Beds24Client::class)->properties(true));
        $this->assertSame([301, 302], array_column(app(Beds24Client::class)->bookings(101, 201), 'id'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/properties?') && ($r['page'] ?? null) == 2 && $r['includeAllRooms'] === 'true');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/bookings?') && ($r['page'] ?? null) == 2
            && $r['propertyId'] == 101 && $r['roomId'] == 201 && in_array('cancelled', $r['status'], true)
            && $r['arrivalFrom'] === '2000-01-01');
        Http::assertSentCount(4);
    }

    public function test_rejected_invite_does_not_echo_remote_data_or_save_credentials(): void
    {
        Http::fake(['api.beds24.com/v2/authentication/setup' => Http::response(['error' => 'test-sensitive-body'], 401)]);

        $this->artisan('propertyme:beds24-connect')
            ->expectsQuestion('Paste the NEW Beds24 invite code (input hidden)', 'test-used-code')
            ->expectsOutputToContain('Beds24 rejected the credential.')
            ->doesntExpectOutputToContain('test-sensitive-body')
            ->doesntExpectOutputToContain('test-used-code')
            ->assertFailed();
        Storage::disk('local')->assertMissing('integrations/beds24-credentials.enc');
        Http::assertSentCount(1);
    }

    public function test_check_without_credentials_explains_setup_without_network_calls(): void
    {
        $this->artisan('propertyme:beds24-connect --check')
            ->expectsOutputToContain('Beds24 is not connected.')
            ->assertFailed();
        Http::assertNothingSent();
    }

    public function test_non_successful_json_response_is_not_reported_as_a_connection(): void
    {
        config(['services.beds24.token' => 'test-long-life']);
        Http::fake(['api.beds24.com/v2/properties*' => Http::response(['success' => false, 'error' => 'hidden'])]);

        $this->artisan('propertyme:beds24-connect --check')
            ->expectsOutputToContain('Beds24 returned an unsuccessful or invalid API response.')
            ->doesntExpectOutputToContain('hidden')
            ->assertFailed();
    }

    public function test_unexpected_errors_do_not_print_raw_exception_messages(): void
    {
        $this->mock(Beds24Client::class, fn ($mock) => $mock->shouldReceive('properties')->once()
            ->andThrow(new RuntimeException('private-error-detail')));

        $this->artisan('propertyme:beds24-connect --check')
            ->expectsOutputToContain('Beds24 connection check failed.')
            ->doesntExpectOutputToContain('private-error-detail')
            ->assertFailed();
    }
}
