<?php

namespace App\Jobs;

use App\Models\PosterDraft;
use App\Services\Posters\PosterEmailIntake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * E-mail s pozvánkou z webhooku Accountu (PosterEmailIntake). Rozbor AI trvá
 * desiatky sekúnd — webhook preto len odovzdá správu do fronty a hneď odpovie.
 */
class ProcessPosterEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** AI volanie sa neopakuje naslepo — zlyhanie zostane v denníku. */
    public int $tries = 1;

    public int $timeout = 180;

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $conversation,
        public readonly array $message,
        public readonly ?string $draftId = null,
    ) {}

    public function handle(PosterEmailIntake $intake): void
    {
        if ($this->kind === 'unassigned') {
            $intake->unassigned($this->conversation, $this->message);

            return;
        }

        $draft = $this->draftId !== null ? PosterDraft::query()->find($this->draftId) : null;

        if ($draft instanceof PosterDraft) {
            $intake->received($draft, $this->message);
        }
    }
}
