<?php

namespace Tests\Feature\Livewire\Calendar;

use App\Actions\Calendar\MoveTrainingSession;
use App\Enums\BookingStatus;
use App\Enums\CalendarEventType;
use App\Enums\TrainingSessionStatus;
use App\Livewire\Calendar\Index;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->admin()->create();
    }

    public function test_page_renders(): void
    {
        $this->actingAs($this->trainer)->get(route('calendar'))->assertOk()->assertSeeLivewire(Index::class);
    }

    public function test_events_returns_own_sessions_and_events_in_local_time(): void
    {
        $service = Service::factory()->create(['name' => 'Personal', 'color' => '#123456']);
        $client = User::factory()->client($this->trainer)->create(['name' => 'Μαρία']);
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->for($service)->at('2026-12-07 18:00')->create();
        Booking::factory()->for($session)->for($client, 'client')->create();
        CalendarEvent::factory()->for($this->trainer, 'trainer')->at('2026-12-08 12:00')->create(['title' => 'Γιατρός']);
        TrainingSession::factory()->at('2026-12-07 10:00')->create();
        AvailabilityException::factory()->for($this->trainer, 'trainer')->create(['starts_on' => '2026-12-09', 'ends_on' => '2026-12-09']);

        $events = Livewire::actingAs($this->trainer)->test(Index::class)->instance()->events('2026-12-07T00:00:00', '2026-12-14T00:00:00');

        $entries = collect($events)->keyBy(fn (array $entry) => $entry['id'] ?? 'background');

        $this->assertCount(3, $events);
        $this->assertSame('Μαρία · Personal', $entries['s'.$session->id]['title']);
        $this->assertSame('2026-12-07T18:00:00', $entries['s'.$session->id]['start']);
        $this->assertSame('#123456', $entries['s'.$session->id]['backgroundColor']);
        $this->assertSame('Γιατρός', $entries->firstWhere('extendedProps.kind', 'event')['title']);
        $this->assertSame('background', $entries['background']['display']);
    }

    public function test_group_session_title_shows_places_taken(): void
    {
        $service = Service::factory()->group(6)->create(['name' => 'HIIT']);
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->for($service)->group(6)->at('2026-12-07 18:00')->create();
        Booking::factory()->count(2)->for($session)->create();

        $events = Livewire::actingAs($this->trainer)->test(Index::class)->instance()->events('2026-12-07T00:00:00', '2026-12-08T00:00:00');

        $this->assertSame('HIIT (2/6)', $events[0]['title']);
    }

    public function test_books_a_personal_session_for_a_client(): void
    {
        $service = Service::factory()->create();
        $client = User::factory()->client($this->trainer)->create();

        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T18:00:00')
            ->assertSet('date', '2026-12-07')
            ->assertSet('time', '18:00')
            ->set('serviceId', $service->id)
            ->set('clientId', $client->id)
            ->call('saveSession')
            ->assertHasNoErrors()
            ->assertSet('showCreate', false)
            ->assertDispatched('calendar-refresh');

        $session = TrainingSession::query()->sole();
        $this->assertSame('2026-12-07 18:00', $session->localStartsAt()->format('Y-m-d H:i'));
        $this->assertTrue($session->bookings()->where('client_id', $client->id)->exists());
    }

    public function test_weekly_series_creates_a_session_per_week(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-08T18:00:00')
            ->set('serviceId', Service::factory()->create()->id)
            ->set('weeks', 8)
            ->call('saveSession')
            ->assertHasNoErrors();

        $this->assertSame(8, TrainingSession::query()->count());
    }

    public function test_conflicting_time_shows_an_error_on_the_time_field(): void
    {
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 18:00')->create();

        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T18:30:00')
            ->set('serviceId', Service::factory()->create()->id)
            ->call('saveSession')
            ->assertHasErrors('time');

        $this->assertSame(1, TrainingSession::query()->count());
    }

    public function test_cannot_book_a_client_of_another_trainer(): void
    {
        $otherClient = User::factory()->client()->create();
        $trainer = User::factory()->trainer()->create();

        Livewire::actingAs($trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T18:00:00')
            ->set('serviceId', Service::factory()->create()->id)
            ->set('clientId', $otherClient->id)
            ->call('saveSession')
            ->assertHasErrors('clientId');
    }

    public function test_offers_free_slots_for_the_chosen_day(): void
    {
        app(Settings::class)->set(['buffer_minutes' => 0, 'slot_interval_minutes' => 60]);
        AvailabilityRule::factory()->for($this->trainer, 'trainer')->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 10:00')->create();
        $service = Service::factory()->create(['duration_min' => 60]);

        $component = Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T09:00:00')
            ->set('serviceId', $service->id);

        $this->assertSame(['09:00', '11:00'], $component->instance()->availableSlots->all());

        $component->call('pickSlot', '11:00')->assertSet('time', '11:00');
    }

    public function test_creates_edits_and_deletes_blocked_time(): void
    {
        $component = Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T13:00:00', '2026-12-07T14:00:00')
            ->set('createMode', 'event')
            ->set('eventTitle', 'Διάλειμμα')
            ->set('eventType', CalendarEventType::Blocked->value)
            ->call('saveEvent')
            ->assertHasNoErrors();

        $event = CalendarEvent::query()->sole();
        $this->assertSame('13:00', $event->localStartsAt()->format('H:i'));
        $this->assertSame('14:00', $event->localEndsAt()->format('H:i'));

        $component->call('openEvent', $event->id)
            ->assertSet('eventTitle', 'Διάλειμμα')
            ->set('eventEnd', '15:00')
            ->call('saveEvent');

        $this->assertSame('15:00', $event->refresh()->localEndsAt()->format('H:i'));

        $component->call('openEvent', $event->id)->call('deleteEvent');

        $this->assertModelMissing($event);
    }

    public function test_event_end_must_be_after_start(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openCreate', '2026-12-07T13:00:00')
            ->set('createMode', 'event')
            ->set('eventTitle', 'Λάθος')
            ->set('eventStart', '14:00')
            ->set('eventEnd', '13:00')
            ->call('saveEvent')
            ->assertHasErrors('eventEnd');
    }

    public function test_drag_and_drop_moves_a_session(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 10:00')->create();

        $moved = Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->instance()
            ->moveEntry('session', $session->id, '2026-12-07T12:00:00', '2026-12-07T13:00:00', app(MoveTrainingSession::class));

        $this->assertTrue($moved);
        $this->assertSame('12:00', $session->refresh()->localStartsAt()->format('H:i'));
    }

    public function test_drag_and_drop_onto_a_conflict_is_reverted(): void
    {
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 12:00')->create();
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 10:00')->create();

        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('moveEntry', 'session', $session->id, '2026-12-07T12:00:00', '2026-12-07T13:00:00')
            ->assertReturned(false)
            ->assertDispatched('notify');

        $this->assertSame('10:00', $session->refresh()->localStartsAt()->format('H:i'));
    }

    public function test_session_details_add_client_change_status_and_cancel(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->group(4)->create();
        $client = User::factory()->client($this->trainer)->create();

        $component = Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openSession', $session->id)
            ->set('addClientId', $client->id)
            ->call('addClientToSession')
            ->assertHasNoErrors();

        $booking = Booking::query()->sole();

        $component->call('setBookingStatus', $booking->id, BookingStatus::Completed->value);
        $this->assertSame(BookingStatus::Completed, $booking->refresh()->status);

        $component->call('cancelSession');
        $this->assertSame(TrainingSessionStatus::Cancelled, $session->refresh()->status);
    }

    public function test_full_session_reports_error_when_adding_client(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->create(['capacity' => 1]);
        Booking::factory()->for($session)->create();

        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openSession', $session->id)
            ->set('addClientId', User::factory()->client($this->trainer)->create()->id)
            ->call('addClientToSession')
            ->assertHasErrors('addClientId');
    }

    public function test_cannot_open_another_trainers_session(): void
    {
        $session = TrainingSession::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Index::class)
            ->call('openSession', $session->id)
            ->assertNotFound();
    }
}
