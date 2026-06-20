<?php

namespace Database\Factories;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Venue $venue): void {
            $canalIds = $venue->pendingCanalIds();

            if ($canalIds === []) {
                $canalIds = [$this->resolveCanalId()];
            }

            $venue->syncCanalAssignments($canalIds, true);
        });
    }

    protected function withFaker()
    {
        return FakerFactory::create('sk_SK');
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Kulturny dom',
            'Mestsky dom kultury',
            'Sportova hala',
            'Komunitne centrum',
            'Konferencne centrum',
        ]).' '.$this->faker->city();

        $canalId = $this->resolveCanalId();

        $villageId = Municipality::query()->inRandomOrder()->value('id')
            ?? 4209;

        $status = $this->faker->randomElement(ModelStatus::cases())->value;
        $openingHours = [
            'mon' => '08:00-18:00',
            'tue' => '08:00-18:00',
            'wed' => '08:00-18:00',
            'thu' => '08:00-18:00',
            'fri' => '08:00-18:00',
            'sat' => '10:00-16:00',
            'sun' => null,
        ];

        return [
            'canal_id' => $canalId,
            'village_id' => $villageId,
            'name' => $name,
            'street' => $this->faker->randomElement([
                'Hlavna',
                'SNP',
                'Mierova',
                'Sturova',
                'Komenskeho',
                'Ruzova',
            ]).' '.$this->faker->buildingNumber(),
            'postcode' => sprintf('%02d %03d', $this->faker->numberBetween(1, 99), $this->faker->numberBetween(1, 999)),
            'slug' => Str::slug($name),
            'body' => $this->faker->optional()->randomElement([
                'Priestor vhodny na kulturne, spolocenske aj vzdelavacie podujatia.',
                'Objekt s dobrou dostupnostou MHD aj parkovania.',
                'Miesto pravidelne vyuzivane na konferencie a komunitne akcie.',
            ]),
            'website' => $this->faker->optional()->passthrough('https://'.$this->faker->domainWord().'.sk'),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'country' => 'Slovensko',
            'latitude' => $this->faker->optional()->latitude(47.7, 49.7),
            'longitude' => $this->faker->optional()->longitude(16.8, 22.6),
            'capacity' => $this->faker->optional()->numberBetween(20, 5000),
            'opening_hours' => json_encode($openingHours, JSON_THROW_ON_ERROR),
            'category' => $this->faker->optional()->randomElement([
                'konferencia',
                'koncert',
                'sport',
                'komunita',
                'gastronomia',
            ]),
            'status' => $status,
        ];
    }

    public function draft(): self
    {
        return $this->state(fn () => ['status' => ModelStatus::Draft->value]);
    }

    public function forCanal(int $canalId, bool $isOwner = true, ModelStatus|string|bool|null $status = null): self
    {
        return $this
            ->state(fn () => [
                'canal_id' => $canalId,
            ])
            ->afterCreating(function (Venue $venue) use ($canalId, $isOwner, $status): void {
                $venue->assignCanal($canalId, $isOwner, $status);
            });
    }

    public function forCanals(array $canalIds, ?int $ownerCanalId = null): self
    {
        $canalIds = collect($canalIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this
            ->state(fn () => [
                'canal_id' => $canalIds[0] ?? $this->resolveCanalId(),
            ])
            ->afterCreating(function (Venue $venue) use ($canalIds, $ownerCanalId): void {
                $venue->syncCanalAssignments($canalIds, $ownerCanalId === null, $ownerCanalId);
            });
    }

    private function resolveCanalId(): int
    {
        $usedCanalIds = Schema::hasTable('canal_venue')
            ? DB::table('canal_venue')->pluck('canal_id')
            : collect();

        $canalId = Canal::query()
            ->whereNotIn('id', $usedCanalIds)
            ->inRandomOrder()
            ->value('id');

        return (int) ($canalId
            ?? Canal::query()->inRandomOrder()->value('id')
            ?? Canal::factory()->create()->id);
    }
}
