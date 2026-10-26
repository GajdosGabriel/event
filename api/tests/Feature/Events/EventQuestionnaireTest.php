<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Enums\TicketPaymentStatus;
use App\Models\EventQuestion;
use App\Models\Ticket;
use App\Models\TicketType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class EventQuestionnaireTest extends EventSetupTest
{
    private TicketType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $this->type = $this->futureEvent->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);
    }

    private function actingAsOrganizer(): void
    {
        $this->user->givePermissionTo(['ticket.view', 'ticket.update']);
        $this->actingAs($this->user, 'sanctum');
    }

    /** Otázka „Strava" za každého účastníka. */
    private function mealQuestion(array $overrides = []): EventQuestion
    {
        $question = $this->futureEvent->questions()->create($overrides + [
            'scope' => 'attendee',
            'type' => 'single_choice',
            'label' => 'Strava',
            'is_required' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'Normálna', 'sort_order' => 1],
            ['label' => 'Vegetariánska', 'sort_order' => 2],
        ]);

        return $question->load('options');
    }

    /** Otázka „Ubytovanie" s cenou a kapacitou na možnosti „Áno". */
    private function lodgingQuestion(int $capacity = 2, int $price = 3000): EventQuestion
    {
        $question = $this->futureEvent->questions()->create([
            'scope' => 'attendee',
            'type' => 'single_choice',
            'label' => 'Ubytovanie',
        ]);
        $question->options()->create(['label' => 'Áno', 'price_amount' => $price, 'capacity' => $capacity]);

        return $question->load('options');
    }

    private function order(array $attendees, array $orderAnswers = [], string $email = 'janko@example.com')
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Janko',
            'holder_email' => $email,
            'answers' => $orderAnswers,
            'items' => [[
                'ticket_type_id' => $this->type->id,
                'quantity' => count($attendees),
                'attendees' => $attendees,
            ]],
        ]);
    }

    #[Test]
    public function organizer_creates_question_with_options(): void
    {
        $this->actingAsOrganizer();

        $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/questionnaire", [
            'label' => 'Strava',
            'scope' => 'attendee',
            'type' => 'single_choice',
            'is_required' => true,
            'options' => [['label' => 'Normálna'], ['label' => 'Vegetariánska'], ['label' => 'Ubytovanie', 'price_amount' => 3000, 'capacity' => 40]],
        ])->assertStatus(201)
            ->assertJsonPath('label', 'Strava')
            ->assertJsonCount(3, 'options')
            ->assertJsonPath('options.2.price_amount', 3000);
    }

    #[Test]
    public function choice_question_requires_options(): void
    {
        $this->actingAsOrganizer();

        $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/questionnaire", [
            'label' => 'Strava', 'scope' => 'attendee', 'type' => 'single_choice', 'options' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['options']);
    }

    #[Test]
    public function foreign_event_questions_are_forbidden(): void
    {
        $this->actingAsOrganizer();

        $this->postJson("/api/dashboard/events/{$this->cudziEvent->id}/questionnaire", [
            'label' => 'Strava', 'scope' => 'order', 'type' => 'text',
        ])->assertStatus(403);
    }

    #[Test]
    public function public_endpoint_lists_active_questions(): void
    {
        $this->mealQuestion();
        $this->futureEvent->questions()->create(['scope' => 'order', 'type' => 'text', 'label' => 'Skryté', 'is_active' => false]);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/events/{$this->futureEvent->id}/questionnaire")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Strava');
    }

    #[Test]
    public function required_attendee_question_must_be_answered_per_seat(): void
    {
        $meal = $this->mealQuestion();
        $veg = $meal->options[1];

        $this->order([
            ['name' => 'A', 'answers' => [$meal->id => $veg->id]],
            ['name' => 'B'],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.attendees.1.answers.'.$meal->id]);

        $this->assertSame(0, Ticket::query()->count());
    }

    #[Test]
    public function answers_are_stored_per_seat_and_per_order(): void
    {
        $meal = $this->mealQuestion();
        $note = $this->futureEvent->questions()->create(['scope' => 'order', 'type' => 'textarea', 'label' => 'Poznámka']);
        [$normal, $veg] = $meal->options;

        $response = $this->order([
            ['name' => 'A', 'answers' => [$meal->id => $normal->id]],
            ['name' => 'B', 'answers' => [$meal->id => $veg->id]],
        ], [$note->id => 'Prídeme neskôr']);

        $response->assertStatus(201)
            ->assertJsonPath('answers.0.display', 'Prídeme neskôr')
            ->assertJsonPath('admissions.1.answers.0.display', 'Vegetariánska');

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame(3, $ticket->answers()->count());
        $this->assertSame(TicketPaymentStatus::None, $ticket->payment_status);
    }

    #[Test]
    public function priced_option_makes_order_payable(): void
    {
        $lodging = $this->lodgingQuestion(price: 3000);

        $this->order([
            ['name' => 'A', 'answers' => [$lodging->id => $lodging->options[0]->id]],
            ['name' => 'B'],
        ])->assertStatus(201)
            ->assertJsonPath('price_amount', 3000)
            ->assertJsonPath('payment_status', 'pending');
    }

    #[Test]
    public function option_capacity_is_enforced(): void
    {
        $lodging = $this->lodgingQuestion(capacity: 2);
        $yes = $lodging->options[0]->id;

        $this->order([
            ['name' => 'A', 'answers' => [$lodging->id => $yes]],
            ['name' => 'B', 'answers' => [$lodging->id => $yes]],
        ])->assertStatus(201);

        $this->order([['name' => 'C', 'answers' => [$lodging->id => $yes]]], [], 'iny@example.com')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    #[Test]
    public function cancelled_seat_frees_option_capacity(): void
    {
        $lodging = $this->lodgingQuestion(capacity: 1, price: 0);
        $yes = $lodging->options[0]->id;

        $this->order([['name' => 'A', 'answers' => [$lodging->id => $yes]]])->assertStatus(201);
        Ticket::query()->firstOrFail()->admissions()->update(['status' => 'cancelled']);

        $this->order([['name' => 'C', 'answers' => [$lodging->id => $yes]]], [], 'iny@example.com')
            ->assertStatus(201);
    }

    #[Test]
    public function questions_restricted_to_other_type_are_not_asked(): void
    {
        $vip = $this->futureEvent->ticketTypes()->create(['name' => 'VIP', 'price_amount' => 0, 'is_active' => true]);
        $this->mealQuestion(['ticket_type_ids' => [$vip->id]]);

        $this->order([['name' => 'A']])->assertStatus(201);
    }

    #[Test]
    public function attendee_edits_answers_via_ticket_link(): void
    {
        $meal = $this->mealQuestion();
        [$normal, $veg] = $meal->options;

        $uuid = $this->order([['name' => 'A', 'answers' => [$meal->id => $normal->id]]])->json('uuid');
        $ticket = Ticket::query()->where('uuid', $uuid)->firstOrFail();
        $admission = $ticket->admissions()->firstOrFail();

        $this->putJson("/api/tickets/{$uuid}/answers", [
            'admissions' => [$admission->id => [$meal->id => $veg->id]],
        ])->assertOk()->assertJsonPath('admissions.0.answers.0.display', 'Vegetariánska');

        $this->putJson("/api/admissions/{$admission->uuid}/answers", [
            'answers' => [$meal->id => $normal->id],
        ])->assertOk()->assertJsonPath('answers.0.display', 'Normálna');
    }

    #[Test]
    public function attendee_cannot_edit_after_deadline(): void
    {
        $meal = $this->mealQuestion();
        $uuid = $this->order([['name' => 'A', 'answers' => [$meal->id => $meal->options[0]->id]]])->json('uuid');

        $this->futureEvent->update(['registration_deadline_at' => now()->subDay()]);

        $this->putJson("/api/tickets/{$uuid}/answers", ['answers' => []])->assertStatus(422);
    }

    #[Test]
    public function attendee_cannot_drop_paid_option_but_organizer_can(): void
    {
        $lodging = $this->lodgingQuestion(price: 3000);
        $uuid = $this->order([['name' => 'A', 'answers' => [$lodging->id => $lodging->options[0]->id]]])->json('uuid');
        $ticket = Ticket::query()->where('uuid', $uuid)->firstOrFail();
        $ticket->update(['payment_status' => 'paid']);
        $admission = $ticket->admissions()->firstOrFail();

        $this->putJson("/api/tickets/{$uuid}/answers", [
            'admissions' => [$admission->id => [$lodging->id => null]],
        ])->assertStatus(422);

        $this->actingAsOrganizer();
        $this->putJson("/api/dashboard/tickets/{$ticket->id}/answers", [
            'admissions' => [$admission->id => [$lodging->id => null]],
        ])->assertOk();

        $ticket->refresh();
        $this->assertNull($ticket->price_amount);
        $this->assertSame(0, $ticket->answers()->count());
    }

    #[Test]
    public function adding_priced_option_later_raises_price(): void
    {
        $lodging = $this->lodgingQuestion(price: 2500);
        $uuid = $this->order([['name' => 'A']])->json('uuid');
        $ticket = Ticket::query()->where('uuid', $uuid)->firstOrFail();
        $admission = $ticket->admissions()->firstOrFail();

        $this->putJson("/api/tickets/{$uuid}/answers", [
            'admissions' => [$admission->id => [$lodging->id => $lodging->options[0]->id]],
        ])->assertOk()
            ->assertJsonPath('price_amount', 2500)
            ->assertJsonPath('payment_status', 'pending');
    }

    #[Test]
    public function summary_counts_answers_per_option(): void
    {
        $meal = $this->mealQuestion();
        [$normal, $veg] = $meal->options;

        $this->order([
            ['name' => 'A', 'answers' => [$meal->id => $veg->id]],
            ['name' => 'B', 'answers' => [$meal->id => $veg->id]],
            ['name' => 'C', 'answers' => [$meal->id => $normal->id]],
        ])->assertStatus(201);

        $this->actingAsOrganizer();
        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/questionnaire/summary")
            ->assertOk()
            ->assertJsonPath('data.0.options.0.count', 1)
            ->assertJsonPath('data.0.options.1.count', 2);
    }

    #[Test]
    public function csv_export_has_a_column_per_question(): void
    {
        $meal = $this->mealQuestion();
        $note = $this->futureEvent->questions()->create(['scope' => 'order', 'type' => 'text', 'label' => 'Faktúra na']);

        $this->order([
            ['name' => 'A', 'answers' => [$meal->id => $meal->options[1]->id]],
            ['name' => 'B', 'answers' => [$meal->id => $meal->options[0]->id]],
        ], [$note->id => 'Farnosť X'])->assertStatus(201);

        $this->actingAsOrganizer();
        $csv = $this->get("/api/dashboard/events/{$this->futureEvent->id}/attendees/export")->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertStringEndsWith(';Strava;"Faktúra na"', trim($lines[0]));
        $this->assertStringEndsWith(';Vegetariánska;"Farnosť X"', trim($lines[1]));
        $this->assertStringEndsWith(';Normálna;"Farnosť X"', trim($lines[2]));
    }

    #[Test]
    public function ticket_email_lists_answers(): void
    {
        $meal = $this->mealQuestion();
        $uuid = $this->order([['name' => 'Anna', 'answers' => [$meal->id => $meal->options[1]->id]]])->json('uuid');

        $ticket = Ticket::query()->where('uuid', $uuid)->firstOrFail();
        $data = (new \App\Notifications\TicketIssued($ticket))->toMail($ticket)->viewData;

        $this->assertSame('Strava', $data['seats'][0]['answers'][0]['label']);
        $this->assertSame('Vegetariánska', $data['seats'][0]['answers'][0]['display']);
    }

    #[Test]
    public function one_click_signup_needs_form_when_questions_are_required(): void
    {
        $this->mealQuestion();
        $this->futureEvent->forceFill(['status' => ModelStatus::Published->value])->save();

        $this->postJson("/api/events/{$this->futureEvent->id}/signup")
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_form');
    }
}
