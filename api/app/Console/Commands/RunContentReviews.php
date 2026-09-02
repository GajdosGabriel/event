<?php

namespace App\Console\Commands;

use App\Services\Content\ContentReviewService;
use Illuminate\Console\Command;

/**
 * Posúdi dávku zverejnených popisov, ktorým dobehol odklad (viď
 * ContentReviewService).
 *
 * Dávka je menšia než pri sondách odkazov a beh má časový strop: každý riadok
 * je jedno volanie OpenAI, teda sekundy, a hosting nemá shell — schedule:run
 * volá webcron v HTTP requeste a všetky príkazy v ňom bežia za sebou. Zvyšok
 * dávky sa radšej presunie do ďalšieho behu; kontrola textu nikam nespěchá.
 */
class RunContentReviews extends Command
{
    protected $signature = 'app:content-reviews-run
        {--limit= : Koľko záznamov posúdiť (predvolene z configu)}
        {--time-budget=25 : Strop behu v sekundách (0 = bez stropu)}';

    protected $description = 'Review published descriptions and notify owners about issues';

    public function handle(ContentReviewService $service): int
    {
        if (! config('content_review.enabled', true)) {
            $this->info('ContentReviews: vypnuté konfiguráciou.');

            return self::SUCCESS;
        }

        $start = microtime(true);
        $limit = max(1, (int) ($this->option('limit') ?: config('content_review.batch', 5)));
        $timeBudget = max(0, (int) $this->option('time-budget'));

        $reviewed = 0;
        $flagged = 0;

        foreach ($service->due($limit) as $review) {
            if (! $service->run($review)) {
                continue;
            }

            $reviewed++;

            if ($review->issues !== null && $review->issues !== []) {
                $flagged++;
            }

            if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                break;
            }
        }

        $this->info(sprintf('ContentReviews: posúdených %d, z toho s výhradami %d', $reviewed, $flagged));

        return self::SUCCESS;
    }
}
