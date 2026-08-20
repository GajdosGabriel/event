<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Presunie existujúce File záznamy z lokálneho/public disku na S3 disk.
 *
 * Beží priamo tam, kde súbory fyzicky ležia (na produkčnom serveri) — číta
 * a zapisuje cez Storage facade, netreba súbory sťahovať na iný stroj.
 * Idempotentný: raz presunuté záznamy majú disk='s3', takže ich ďalší beh
 * automaticky preskočí.
 */
class MigrateFilesToS3 extends Command
{
    protected $signature = 'files:migrate-to-s3
        {--dry-run : Len vypíše, čo by sa presunulo, bez zápisu}
        {--delete : Po overenom uploade zmaže lokálnu kópiu}
        {--prune-local : Nemigruje; len zmaže lokálne zvyšky po už presunutých súboroch (disk=s3)}
        {--chunk=100 : Počet záznamov spracovaných naraz}';

    protected $description = 'Presunie File záznamy z lokálneho/public disku na S3 a aktualizuje ich disk stĺpec';

    private const SOURCE_DISKS = ['local', 'public'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if ($this->option('prune-local')) {
            return $this->pruneLocal($dryRun, $chunkSize);
        }

        $migrated = 0;
        $failed = 0;
        $bytes = 0;

        File::query()
            ->whereIn('disk', self::SOURCE_DISKS)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($files) use ($dryRun, $delete, &$migrated, &$failed, &$bytes) {
                foreach ($files as $file) {
                    try {
                        $bytes += $this->migrateFile($file, $dryRun, $delete);
                        $migrated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->error("Súbor #{$file->id} ({$file->path}): {$e->getMessage()}");
                    }
                }
            });

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%sPresunuté: %d, chyby: %d, celková veľkosť: %s',
            $prefix,
            $migrated,
            $failed,
            $this->formatBytes($bytes),
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Overí cieľový disk ešte pred prvým zápisom.
     *
     * Samotný --dry-run číta iba lokálny disk, takže by prešiel aj s úplne
     * nenastaveným S3. Bez tejto kontroly sa dá ľahko spustiť migrácia do
     * cudzieho prefixu (napr. prod dáta do dev/) alebo do prázdneho bucketu.
     */
    private function preflight(): bool
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $region = (string) config('filesystems.disks.s3.region');
        $root = (string) config('filesystems.disks.s3.root');

        if ($bucket === '') {
            $this->error('AWS_BUCKET nie je nastavený — S3 disk nie je nakonfigurovaný.');

            return false;
        }

        $this->line(sprintf(
            'Cieľ: bucket=%s region=%s prefix=%s',
            $bucket,
            $region,
            $root !== '' ? $root . '/' : '(koreň bucketu)',
        ));

        if ($root === '') {
            $this->warn('AWS_ROOT je prázdny — súbory pôjdu do koreňa bucketu a môžu sa miešať s iným prostredím.');
        }

        // Skutočný zápis: overí kľúče, región aj práva naraz. Bez neho by sa
        // chyba prejavila až v polovici migrácie. Disk má throw=false, takže
        // samotné put() pri chybe nevyhodí výnimku — kontrolujeme exists().
        $probe = '_preflight-' . uniqid() . '.txt';

        try {
            $disk = Storage::disk('s3');
            $disk->put($probe, 'ok');

            if (! $disk->exists($probe)) {
                $this->error('Zápis na S3 neprešiel (súbor po zápise neexistuje). Skontroluj kľúče a práva.');

                return false;
            }

            $disk->delete($probe);
        } catch (\Throwable $e) {
            $this->error('S3 nedostupné: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Zmaže lokálne zvyšky po súboroch, ktoré už sú na S3 (disk=s3).
     *
     * Migrácia zámerne kopíruje a nemaže, takže po jej dobehnutí ostávajú
     * originály na lokálnom disku ako záloha. Tento režim ich upratá — ale
     * každý súbor zmaže až po overení, že jeho kópia na S3 naozaj existuje.
     */
    private function pruneLocal(bool $dryRun, int $chunkSize): int
    {
        $s3 = Storage::disk('s3');
        $deleted = 0;
        $skipped = 0;
        $bytes = 0;

        // `local` a `public` mieria na ten istý adresár, takže bez deduplikácie
        // podľa reálneho rootu by sme každý súbor započítali dvakrát.
        $localDisks = [];
        foreach (self::SOURCE_DISKS as $diskName) {
            $root = Storage::disk($diskName)->path('');
            $localDisks[$root] = Storage::disk($diskName);
        }

        File::query()
            ->where('disk', 's3')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($files) use ($s3, $localDisks, $dryRun, &$deleted, &$skipped, &$bytes) {
                foreach ($files as $file) {
                    $paths = array_values(array_unique(array_filter([$file->path, $file->thumb, $file->large])));

                    foreach ($paths as $path) {
                        foreach ($localDisks as $local) {
                            if (! $local->exists($path)) {
                                continue;
                            }

                            if (! $s3->exists($path)) {
                                $skipped++;
                                $this->warn("Preskočené (na S3 chýba): {$path}");
                                continue;
                            }

                            $bytes += (int) $local->size($path);

                            if (! $dryRun) {
                                $local->delete($path);
                            }

                            $deleted++;
                        }
                    }
                }
            });

        $this->info(sprintf(
            '%sZmazané lokálne kópie: %d, preskočené: %d, uvoľnené: %s',
            $dryRun ? '[DRY RUN] ' : '',
            $deleted,
            $skipped,
            $this->formatBytes($bytes),
        ));

        return self::SUCCESS;
    }

    private function migrateFile(File $file, bool $dryRun, bool $delete): int
    {
        $source = Storage::disk($file->disk);
        $target = Storage::disk('s3');

        $paths = array_values(array_unique(array_filter([$file->path, $file->thumb, $file->large])));
        $size = 0;

        foreach ($paths as $path) {
            if (!$source->exists($path)) {
                continue;
            }

            $size += (int) $source->size($path);

            if ($dryRun) {
                continue;
            }

            $this->copyToS3($source, $target, $path);
        }

        if ($dryRun) {
            return $size;
        }

        $file->disk = 's3';
        $file->save();

        if ($delete) {
            foreach ($paths as $path) {
                $source->delete($path);
            }
        }

        return $size;
    }

    private function copyToS3($source, $target, string $path): void
    {
        $stream = $source->readStream($path);
        if ($stream === null) {
            throw new \RuntimeException("Nepodarilo sa otvoriť zdrojový stream pre {$path}");
        }

        try {
            // Bez ACL zámerne: bucket má ObjectOwnership=BucketOwnerEnforced,
            // takže ACL sú vypnuté a verejné čítanie rieši bucket policy.
            $target->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (!$target->exists($path)) {
            throw new \RuntimeException("Upload na S3 zlyhal (súbor po zápise neexistuje) pre {$path}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $value, $units[$i]);
    }
}
