<?php

namespace Tests\TestSupport;


use App\Models\Canal;
use App\Repositories\Contracts\CanalRepository;
use Tests\TestSupport\UserSetupTest;
use Illuminate\Foundation\Testing\RefreshDatabase;



abstract class CanalSetupTest extends UserSetupTest
{
    use RefreshDatabase;

    protected Canal $canalPrimary;
    protected $formCanal;
    protected CanalRepository $canalRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canalRepository = app(CanalRepository::class);
        $this->canalPrimary = $this->user->canals->first();

        $this->formCanal = Canal::factory()->make()->toArray(); // Vytvorte aj kanál
    }
}
