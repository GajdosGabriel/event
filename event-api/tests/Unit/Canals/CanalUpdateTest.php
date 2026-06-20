<?php

namespace Tests\Unit\Canals;

use App\Models\Canal;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

class CanalUpdateTest extends CanalSetupTest
{
    #[Test]
    public function repository_can_update_soft_deleted_canal(): void
    {
        $canal = $this->canalPrimary;
        $canal->delete();

        $payload = [
            'name' => 'Updated Canal ' . Str::random(5),
            'body' => 'Updated canal body ' . Str::random(20),
            'municipality_id' => $canal->municipality_id,
        ];

        /** @var Canal $updatedCanal */
        $updatedCanal = $this->canalRepository->update($canal->id, $payload);

        $this->assertSame($canal->id, $updatedCanal->id);
        $this->assertSame($payload['name'], $updatedCanal->name);
        $this->assertSame($payload['body'], $updatedCanal->body);
        $this->assertSoftDeleted('canals', [
            'id' => $canal->id,
        ]);
    }
}
