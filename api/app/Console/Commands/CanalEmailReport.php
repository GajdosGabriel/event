<?php

namespace App\Console\Commands;

use App\Models\CanalEmail;
use App\Services\Canals\CanalEmails;
use Illuminate\Console\Command;

/**
 * Ručný zápis toho, čo prišlo do schránky portálu: e-mail sa vrátil, alebo
 * sa adresa ozvala. Kým schránku nečíta automat, je to jediný vstup —
 * automat potom volá tie isté metódy CanalEmails.
 *
 *   php artisan app:canal-email info@divadlo.sk bounced --reason="550 user unknown"
 *   php artisan app:canal-email info@divadlo.sk full
 *   php artisan app:canal-email info@divadlo.sk replied
 */
class CanalEmailReport extends Command
{
    protected $signature = 'app:canal-email
        {email : Adresa kanála}
        {state : bounced (adresa neexistuje) | full (plná schránka, dočasné) | replied (ozvala sa)}
        {--reason= : Text z vráteného e-mailu}';

    protected $description = 'Zapíše vrátený e-mail alebo odpoveď z adresy kanála a dorovná primárnu adresu.';

    public function handle(CanalEmails $emails): int
    {
        $email = (string) $this->argument('email');
        $reason = $this->option('reason');

        $canals = match ($this->argument('state')) {
            'bounced' => $emails->recordBounce($email, CanalEmail::BOUNCE_HARD, $reason),
            'full' => $emails->recordBounce($email, CanalEmail::BOUNCE_SOFT, $reason ?? 'mailbox full'),
            'replied' => $emails->recordReply($email),
            default => null,
        };

        if ($canals === null) {
            $this->error('Stav musí byť bounced, full alebo replied.');

            return self::INVALID;
        }

        $this->info(sprintf('%s: zapísané pri %d kanáloch.', $email, $canals));

        foreach (CanalEmail::query()->with('canal')->where('email', CanalEmails::normalize($email))->get() as $row) {
            $this->line(sprintf('  #%d %s → primárna: %s', $row->canal_id, $row->canal?->name, $row->canal?->email ?? '—'));
        }

        return self::SUCCESS;
    }
}
