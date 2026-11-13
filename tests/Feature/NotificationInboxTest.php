<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\BookingSyncedNotification;
use App\Notifications\ExpenseReturnedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use App\Notifications\ReportFinalizedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    public function test_receipt_and_general_menus_include_only_their_own_notifications_and_unread_counts(): void
    {
        $user = $this->makeUser(UserRole::Master);
        $this->notification($user, ExpenseSubmittedNotification::class, 'Staff receipt uploaded');
        $this->notification($user, ExpenseReturnedNotification::class, 'Receipt returned');
        $this->notification($user, ExpenseSubmittedNotification::class, 'Older receipt already read', true);
        $this->notification($user, BookingSyncedNotification::class, 'Booking imported');
        $this->notification($user, ReportFinalizedNotification::class, 'Report finalized', true);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $receipt = $xpath->query('//*[@id="pm-notifications-receipt"]')->item(0)->textContent;
        $general = $xpath->query('//*[@id="pm-notifications-general"]')->item(0)->textContent;

        $this->assertStringContainsString('Older receipt already read', $receipt);
        $this->assertStringContainsString('Staff receipt uploaded', $receipt);
        $this->assertStringContainsString('Receipt returned', $receipt);
        $this->assertStringNotContainsString('Booking imported', $receipt);
        $this->assertStringContainsString('Booking imported', $general);
        $this->assertStringContainsString('Report finalized', $general);
        $this->assertStringNotContainsString('Staff receipt uploaded', $general);
        $this->assertStringNotContainsString('Receipt returned', $general);
        foreach (['receipt' => '2', 'general' => '1'] as $category => $count) {
            $badge = $xpath->query('//*[@data-notification-category="'.$category.'"]/button/span')->item(0);
            $this->assertSame($count, $badge->textContent);
        }
    }

    public function test_receipt_list_and_pagination_keep_the_category(): void
    {
        $user = $this->makeUser(UserRole::Master);
        for ($i = 0; $i < 21; $i++) {
            $this->notification($user, ExpenseSubmittedNotification::class, 'Receipt '.$i);
        }
        $this->notification($user, BookingSyncedNotification::class, 'General booking');

        $response = $this->actingAs($user)->get(route('notifications.index', ['category' => 'receipt']))
            ->assertOk()->assertViewHas('category', NotificationCategory::Receipt);
        $notifications = $response->viewData('notifications');
        $this->assertSame(21, $notifications->total());
        $this->assertCount(20, $notifications->items());
        $this->assertStringContainsString('category=receipt', $notifications->nextPageUrl());
        $this->assertSame(['general' => 1, 'receipt' => 21], $response->viewData('unreadCounts'));
        $this->actingAs($user)->get($notifications->nextPageUrl())->assertOk()
            ->assertViewHas('notifications', fn ($list) => $list->count() === 1 && $list->first()->type === ExpenseSubmittedNotification::class);
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertViewHas('notifications', fn ($list) => $list->total() === 1 && $list->first()->type === BookingSyncedNotification::class);
    }

    public function test_marking_receipts_read_keeps_general_and_other_users_notifications_unread(): void
    {
        $user = $this->makeUser(UserRole::Master);
        $other = $this->makeUser(UserRole::Manager);
        $receipt = $this->notification($user, ExpenseSubmittedNotification::class, 'Receipt');
        $returned = $this->notification($user, ExpenseReturnedNotification::class, 'Returned');
        $general = $this->notification($user, BookingSyncedNotification::class, 'Booking');
        $private = $this->notification($other, ExpenseSubmittedNotification::class, 'Private receipt');
        $url = route('notifications.index', ['category' => 'receipt']);

        $this->actingAs($user)->from($url)->post(route('notifications.read-all'), ['category' => 'receipt'])->assertRedirect($url);

        $this->assertNotNull($receipt->fresh()->read_at);
        $this->assertNotNull($returned->fresh()->read_at);
        $this->assertNull($general->fresh()->read_at);
        $this->assertNull($private->fresh()->read_at);
    }

    public function test_default_read_all_marks_only_general_notifications(): void
    {
        $user = $this->makeUser(UserRole::Master);
        $receipt = $this->notification($user, ExpenseSubmittedNotification::class, 'Receipt');
        $general = $this->notification($user, BookingSyncedNotification::class, 'Booking');

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertNull($receipt->fresh()->read_at);
        $this->assertNotNull($general->fresh()->read_at);
    }

    public function test_staff_receipt_inbox_is_private_and_opening_a_receipt_marks_only_that_item(): void
    {
        $staff = $this->makeUser(UserRole::Staff);
        $other = $this->makeUser(UserRole::Staff);
        $own = $this->notification($staff, ExpenseReturnedNotification::class, 'Fix your receipt');
        $general = $this->notification($staff, 'UnknownGeneralNotification', 'Other information');
        $private = $this->notification($other, ExpenseReturnedNotification::class, 'Other staff receipt');

        $this->actingAs($staff)->get(route('notifications.index', ['category' => 'receipt']))->assertOk()
            ->assertViewHas('notifications', fn ($list) => $list->total() === 1 && $list->first()->is($own));
        $this->get(route('notifications.open', $private->id))->assertNotFound();
        $this->get(route('notifications.open', $own->id))->assertRedirect(route('notifications.index', ['category' => 'receipt']));
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($general->fresh()->read_at);
        $this->assertNull($private->fresh()->read_at);
    }

    public function test_invalid_category_does_not_mark_any_notification_read(): void
    {
        $user = $this->makeUser(UserRole::Master);
        $receipt = $this->notification($user, ExpenseSubmittedNotification::class, 'Receipt');
        $general = $this->notification($user, BookingSyncedNotification::class, 'Booking');

        $this->actingAs($user)->postJson(route('notifications.read-all'), ['category' => 'all'])->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->assertNull($receipt->fresh()->read_at);
        $this->assertNull($general->fresh()->read_at);
    }

    private function notification(User $user, string $type, string $title, bool $read = false): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => $type,
            'data' => ['title' => $title, 'body' => $title.' details'],
            'read_at' => $read ? now()->subDay() : null,
        ]);
    }
}
