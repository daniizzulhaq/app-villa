<?php

namespace Tests\Feature;

use App\Exceptions\HostexException;
use App\Services\Beds24Client;
use App\Services\HostexClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HostexConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.access_token' => 'test-hostex-secret']);
    }

    private function success(array $data): array
    {
        return ['request_id' => 'test-request', 'error_code' => 200, 'error_msg' => 'Done.', 'data' => $data];
    }

    public function test_read_only_connection_lists_properties_with_the_documented_envelope_and_header(): void
    {
        $listingId = '1273618741658905815';
        Http::fake(['api.hostex.io/v3/properties*' => Http::response($this->success([
            'properties' => [['id' => 501, 'title' => 'Avora', 'channels' => [['channel_type' => 'airbnb', 'listing_id' => $listingId]]]],
            'total' => 1,
        ]))]);
        $this->artisan('propertyme:hostex-check')
            ->expectsOutputToContain('Connection verified: 1 properties accessible.')
            ->expectsOutputToContain($listingId)
            ->doesntExpectOutputToContain('test-hostex-secret')->assertSuccessful();
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('Hostex-Access-Token', 'test-hostex-secret')
            && ! str_contains($r->url(), 'test-hostex-secret') && $r['offset'] == 0 && $r['limit'] == 100);
        Http::assertSentCount(1);
    }

    public function test_missing_token_and_inactive_provider_fail_without_network_calls(): void
    {
        config(['services.hostex.access_token' => '']);
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('Set HOSTEX_ACCESS_TOKEN in .env')->assertFailed();
        config(['propertyme.channel_manager_provider' => 'beds24']);
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('Hostex is not the configured channel manager.')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_hostex_switch_prevents_legacy_beds24_calls_even_with_valid_cached_credentials(): void
    {
        config(['services.beds24.token' => 'test-old-beds24-secret']);
        try {
            app(Beds24Client::class)->properties();
            $this->fail('Legacy Beds24 should be disabled.');
        } catch (RuntimeException $e) {
            $this->assertSame('Beds24 is disabled. The active channel manager is Hostex.', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_property_pagination_reads_past_full_pages_without_following_remote_urls(): void
    {
        $first = array_map(fn ($id) => ['id' => $id, 'title' => 'Villa '.$id, 'channels' => []], range(1, 100));
        Http::fakeSequence()->push($this->success(['properties' => $first, 'total' => 101]))
            ->push($this->success(['properties' => [['id' => 101, 'title' => 'Last', 'channels' => []]], 'total' => 101]));
        $this->assertCount(101, app(HostexClient::class)->properties());
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.hostex.io/v3/properties?') && $r['offset'] == 100);
        Http::assertSentCount(2);
    }

    public function test_reservation_read_counts_stays_not_orders_and_keeps_explicit_property_and_date_filters(): void
    {
        $first = array_map(fn ($id) => ['stay_code' => 'stay-'.$id, 'reservation_code' => 'same-order', 'property_id' => 501], range(1, 100));
        Http::fakeSequence()->push($this->success(['properties' => [['id' => 501, 'title' => 'Avora', 'channels' => []]], 'total' => 1]))
            ->push($this->success(['reservations' => $first]))
            ->push($this->success(['reservations' => [['stay_code' => 'stay-101', 'reservation_code' => 'same-order', 'property_id' => 501]]]));
        $this->artisan('propertyme:hostex-check --property=501 --from=2026-01-01 --to=2026-02-01')
            ->expectsOutputToContain('Reservation read verified: 101 stays with check-in 2026-01-01 to 2026-02-01.')
            ->doesntExpectOutputToContain('same-order')->assertSuccessful();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/reservations?') && $r['offset'] == 100 && $r['property_id'] == 501
            && $r['start_check_in_date'] === '2026-01-01' && $r['end_check_in_date'] === '2026-02-01');
        Http::assertSentCount(3);
    }

    public function test_long_date_ranges_use_complete_non_overlapping_windows_of_at_most_180_days(): void
    {
        $windows = [];
        Http::fake(function ($r) use (&$windows) {
            $start = new \DateTimeImmutable($r['start_check_in_date']);
            $end = new \DateTimeImmutable($r['end_check_in_date']);
            $this->assertLessThanOrEqual(179, $start->diff($end)->days);
            $this->assertArrayNotHasKey('start_check_out_date', $r->data());
            $windows[] = [$start->format('Y-m-d'), $end->format('Y-m-d')];

            return Http::response($this->success(['reservations' => [['stay_code' => 'stay-'.count($windows), 'property_id' => 501]]]));
        });
        $this->assertCount(3, app(HostexClient::class)->reservations(501, '2026-01-01', '2026-12-31'));
        $this->assertSame([['2026-01-01', '2026-06-29'], ['2026-06-30', '2026-12-26'], ['2026-12-27', '2026-12-31']], $windows);
    }

    public function test_http_200_auth_and_subscription_errors_are_not_reported_as_success_or_echoed(): void
    {
        Http::fakeSequence()
            ->push(['error_code' => 401, 'error_msg' => 'test-secret-remote'])
            ->push(['error_code' => 420, 'error_msg' => 'private-subscription-details'])
            ->push(['error_code' => 429, 'error_msg' => 'private-rate-limit-detail']);
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('Hostex rejected the Access Token.')
            ->doesntExpectOutputToContain('test-secret-remote')->assertFailed();
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('Check Pro access and subscription status.')
            ->doesntExpectOutputToContain('private-subscription-details')->assertFailed();
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('rate limit reached')
            ->doesntExpectOutputToContain('private-rate-limit-detail')->assertFailed();
        Http::assertSentCount(3);
    }

    public function test_wrong_property_response_does_not_pass_a_reservation_check(): void
    {
        Http::fakeSequence()->push($this->success(['properties' => [['id' => 501, 'title' => 'Avora', 'channels' => []]], 'total' => 1]))
            ->push($this->success(['reservations' => [['stay_code' => 'stay-1', 'property_id' => 999]]]));
        $this->artisan('propertyme:hostex-check --property=501')->expectsOutputToContain('reservation for a different property')->assertFailed();
    }

    public function test_repeated_pagination_cannot_be_reported_as_a_complete_list(): void
    {
        $rows = array_map(fn ($id) => ['id' => $id, 'title' => 'Villa '.$id, 'channels' => []], range(1, 100));
        Http::fakeSequence()->push($this->success(['properties' => $rows, 'total' => 200]))
            ->push($this->success(['properties' => $rows, 'total' => 200]));
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('missing or repeated record ID')->assertFailed();
        Http::assertSentCount(2);
    }

    public function test_malformed_or_partial_response_is_not_a_success(): void
    {
        Http::fakeSequence()->push(['data' => ['properties' => []]])
            ->push($this->success(['properties' => null]))
            ->push($this->success(['properties' => [], 'total' => 10]));
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('invalid API response')->assertFailed();
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('invalid properties list')->assertFailed();
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('incomplete property list')->assertFailed();
    }

    public function test_invalid_dates_are_rejected_before_requesting_reservations(): void
    {
        $this->expectException(HostexException::class);
        try {
            app(HostexClient::class)->reservations(501, '2026-02-31', '2026-12-01');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_unexpected_exceptions_do_not_echo_private_details(): void
    {
        $this->mock(HostexClient::class, fn ($mock) => $mock->shouldReceive('properties')->andThrow(new RuntimeException('test-private-detail')));
        $this->artisan('propertyme:hostex-check')->expectsOutputToContain('Hostex check failed.')
            ->doesntExpectOutputToContain('test-private-detail')->assertFailed();
    }
}
