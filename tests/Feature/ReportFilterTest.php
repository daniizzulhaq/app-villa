<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        $this->actingAs($this->makeUser(UserRole::Master));
    }

    public function test_monthly_filters_match_the_report_period_and_villa_from_the_url(): void
    {
        $villa = $this->makeVilla();
        $response = $this->get(route('reports.monthly', ['year' => '2026', 'month' => '11', 'villa' => (string) $villa->id]));

        $response->assertOk()->assertSee('November 2026');
        $this->assertSelected($response, 'year', '2026');
        $this->assertSelected($response, 'month', '11');
        $this->assertSelected($response, 'villa', (string) $villa->id);
        $response->assertSee(route('reports.monthly', ['villa' => $villa->id, 'year' => 2026, 'month' => 12]));
    }

    public function test_annual_filter_matches_the_year_from_the_url(): void
    {
        $this->makeVilla();
        $response = $this->get(route('reports.annual', ['year' => '2026']));

        $response->assertOk()->assertSee('Tahun 2026');
        $this->assertSelected($response, 'year', '2026');
    }

    public function test_navigation_outside_the_recent_years_keeps_the_actual_period_selected(): void
    {
        $this->makeVilla();
        foreach ([2020, 2028] as $year) {
            $response = $this->get(route('reports.monthly', ['year' => (string) $year, 'month' => '12']));

            $response->assertOk()->assertSee('Desember '.$year);
            $this->assertSelected($response, 'year', (string) $year);
            $this->assertSelected($response, 'month', '12');
            $response->assertSee(route('reports.monthly', ['year' => $year + 1, 'month' => 1]));
        }
    }

    private function assertSelected(TestResponse $response, string $name, string $value): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $options = (new \DOMXPath($dom))->query('//select[@name="'.$name.'"]/option[@selected]');

        $this->assertCount(1, $options, 'The '.$name.' filter must select the report value.');
        $this->assertSame($value, $options->item(0)->getAttribute('value'));
    }
}
