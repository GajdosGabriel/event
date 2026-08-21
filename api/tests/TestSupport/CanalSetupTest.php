<?php

namespace Tests\TestSupport;


use App\Enums\ModelStatus;
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

        // CanalFactory losuje status naprieč všetkými prípadmi ModelStatus,
        // vrátane tých, ktoré formulár ponúknuť nesmie (pending_review,
        // rejected, blocked). Odkedy CanalStoreRequest status validuje, taký
        // payload spadne na 422 — fixture ho preto určuje natvrdo.
        $this->formCanal = Canal::factory()->make([
            'status' => ModelStatus::Draft->value,
        ])->toArray();
    }
}
