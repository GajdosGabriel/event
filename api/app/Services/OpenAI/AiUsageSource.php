<?php

namespace App\Services\OpenAI;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Úloha, z ktorej práve ide volanie OpenAI — zdroj v prehľade spotreby.
 *
 * Plánovač púšťa príkazy cez Artisan::call() v jednom procese (viď
 * routes/console.php), takže $_SERVER['argv'] je pri všetkých rovnaké
 * „schedule:run" a prehľad nevedel povedať, či volanie patrí importu,
 * detektoru alebo štítkom. Preto sa príkazy a joby evidujú ako zásobník:
 * platí ten najvnútornejší (schedule:run → queue:work → job → príkaz).
 *
 * Singleton, nie scoped — queue worker scoped inštancie pred každým jobom
 * zahodí, a s nimi by zmizol aj príkaz, v ktorom worker beží.
 */
class AiUsageSource
{
    /** @var list<array{key: string, label: string}> */
    private array $stack = [];

    public function current(): ?string
    {
        $top = end($this->stack);

        return $top === false ? null : $top['label'];
    }

    public function commandStarting(CommandStarting $event): void
    {
        if ($event->command === null || $event->command === '') {
            return;
        }

        $this->push('command:'.$event->command, $this->commandLabel($event->command, $event->input));
    }

    public function commandFinished(CommandFinished $event): void
    {
        if ($event->command !== null && $event->command !== '') {
            $this->finish('command:'.$event->command);
        }
    }

    public function jobProcessing(JobProcessing $event): void
    {
        $this->push('job:'.$event->job->getJobId(), 'job:'.class_basename($event->job->resolveName()));
    }

    public function jobFinished(JobProcessed|JobFailed|JobExceptionOccurred $event): void
    {
        $this->finish('job:'.$event->job->getJobId());
    }

    private function push(string $key, string $label): void
    {
        $this->stack[] = ['key' => $key, 'label' => mb_substr($label, 0, 120)];
    }

    /**
     * Zruší posledný záznam s kľúčom aj všetko nad ním — vnútorný príkaz,
     * ktorý skončil výnimkou, svoje CommandFinished nevyšle. Neznámy kľúč
     * (job zlyhal a vyslal dve udalosti) nerobí nič.
     */
    private function finish(string $key): void
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if ($this->stack[$i]['key'] === $key) {
                array_splice($this->stack, $i);

                return;
            }
        }
    }

    /**
     * Import má jeden príkaz na všetky zdroje — bez hostiteľa by sa
     * vyveska.sk a tkkbs.sk v prehľade zliali do jedného riadku.
     */
    private function commandLabel(string $command, InputInterface $input): string
    {
        $url = $input->getParameterOption('--url', null, true);
        if (is_array($url)) {
            $url = reset($url);
        }

        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (! is_string($host) || $host === '') {
            return $command;
        }

        return $command.' '.preg_replace('/^www\./i', '', $host);
    }
}
