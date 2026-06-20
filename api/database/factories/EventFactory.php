<?php

namespace Database\Factories;

use App\Models\Event; // Pridaný import
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\{Canal, Municipality, User, Venue};
use App\Enums\ModelStatus;

class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Event::class; // Presunuté sem

    public function configure(): static
    {
        return $this->afterMaking(function (Event $event) {
            $this->syncLocationFields($event);
        });
    }


 /**
     * Vygeneruje dátum s minútami 00 alebo 30.
     */
    private function halfHourDate($from = '-1 year', $to = '+1 year'): \DateTime
    {
        $dt = $this->faker->dateTimeBetween($from, $to);
        $minute = $this->faker->randomElement([0, 30]);
        return $dt->setTime((int)$dt->format('H'), $minute);
    }

    private function registrationDeadlineFor(\DateTimeInterface $startAt): \DateTime
    {
        $start = Carbon::instance($startAt instanceof \DateTime ? clone $startAt : new \DateTime($startAt->format('c')));
        $deadlineFrom = $start->copy()->subDay();
        $deadlineTo = $start->copy()->subHours(5);

        return $this->faker->dateTimeBetween($deadlineFrom, $deadlineTo);
    }

    private function syncLocationFields(Event $event): void
    {
        if ($event->venue_id) {
            $venue = Venue::query()->find($event->venue_id);

            if ($venue) {
                $event->canal_id = (int) $venue->canal_id;

                return;
            }
        }

        $canalId = (int) ($event->canal_id ?: (Canal::query()->inRandomOrder()->value('id') ?? Canal::factory()->create()->id));

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canalId))
            ->inRandomOrder()
            ->first();

        if (! $venue) {
            $venue = Venue::factory()->create([
                'canal_id' => $canalId,
                'village_id' => (int) (Municipality::query()->inRandomOrder()->value('id') ?? 4209),
            ]);
        }

        $event->canal_id = $canalId;
        $event->venue_id = (int) $venue->id;
    }


    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Tímový building',
            'Firemný workshop',
            'Stratégia rozvoja',
            'Tréning komunikácie',
            'Manažérsky seminár',
            'Motivačný program',
            'Kreatívne myslenie',
            'Leaderšip tréning'
        ]);

        $published = $this->faker->dateTimeBetween('-1 year', '+1 year');
        $startAt = $this->halfHourDate('-1 year', '+1 year');
        $canal = Canal::query()->inRandomOrder()->first() ?? Canal::factory()->create();

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
            ->inRandomOrder()
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $canal->id,
                'village_id' => (int) (Municipality::query()->inRandomOrder()->value('id') ?? 4209),
            ]);

        return [
            'name' => $name, // Zmenené z $this->faker->sentence(3) na $name
            'slug' => Str::slug($name),
            'body' => $this->faker->paragraph(5),
            'published_at' => $published,
            'start_at' => $startAt,
            'end_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween(
                    $attributes['start_at'],
                    '+1 year'
                );
            },
            'registration_deadline_at' => $this->registrationDeadlineFor($startAt),
            'status' => $this->faker->randomElement(ModelStatus::cases())->value,
            'website' => $this->faker->url,
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'venue_id' => (int) $venue->id,
            'canal_id' => (int) $canal->id,
            'user_id' => User::inRandomOrder()->value('id'),
        ];
    }

    public function past(): self
    {
        return $this->state(function (array $attributes) {
            $startAt = $this->halfHourDate('-1 year', '+1 year');

            return [
                'start_at' => $startAt,
                'end_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
                'published_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
                'registration_deadline_at' => $this->registrationDeadlineFor($startAt),
            ];
        });
    }

    public function future(): self
    {
        return $this->state(function (array $attributes) {
            $startAt = $this->halfHourDate('+1 day', '+1 year');
            $start = Carbon::instance(clone $startAt);

            return [
                'status' => $this->faker->randomElement(ModelStatus::cases())->value,
                'start_at' => $startAt,
                'end_at' => $this->faker->dateTimeBetween($start->copy()->addHour(), $start->copy()->addYear()),
                'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
                'registration_deadline_at' => $this->registrationDeadlineFor($startAt),
            ];
        });
    }

    public function active(): self
    {
        return $this->state(function (array $attributes) {
            $startAt = now()->subWeek();

            return [
                'status' => $this->faker->randomElement(ModelStatus::cases())->value,
                'published_at' => now()->subMonth(),
                'start_at' => $startAt,
                'end_at' => now()->addWeek(),
                'registration_deadline_at' => $this->registrationDeadlineFor($startAt),
            ];
        });
    }
}
