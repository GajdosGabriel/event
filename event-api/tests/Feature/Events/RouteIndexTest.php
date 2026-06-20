<?php

namespace Tests\Feature\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Event;
use App\Models\User;
use App\Models\Canal;
use Illuminate\Foundation\Testing\DatabaseTransactions; // Používame DatabaseTransactions pre testy,
use App\Repositories\Contracts\EventRepository;

class RouteIndexTest extends TestCase
{
    use DatabaseTransactions;

    protected EventRepository $eventRepository;
    protected $user;
    protected $canal;

    protected function setUp(): void
    {
        parent::setUp(); // Vždy volajte parent::setUp() najskôr

        $this->eventRepository = app(EventRepository::class); // Injektujte repository

        $this->user = User::factory()->create();
        $this->canal = Canal::factory()->create(); // Vytvorte aj kanál

        Canal::factory()->create(); // Vytvorte aj kanál
    }

    // public function test_can_filter_events_via_query_param()
    // {
    //     $rubyEvent = Event::factory()->create(['name' => 'Ruby Conference']);
    //     $jsEvent = Event::factory()->create(['name' => 'JS Meetup']);

    //     $response = $this->get('/api/events');

    //     $response->assertSeeText('Ruby Conference');
    //     $response->assertDontSeeText('JS Meetup');
    // }

    // public function test_returns_all_events_when_no_query()
    // {
    //     Event::factory()->count(3)->create();

    //     $response = $this->get('/api/events');

    //     $response->assertOk();
    //     $this->assertCount(3, $response->viewData('events'));
    // }
}
