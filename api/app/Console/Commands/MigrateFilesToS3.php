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
        {--chunk=100 : Počet záznamov spracovaných naraz}';

    protected $description = 'Presunie File záznamy z lokálneho/public disku na S3 a aktualizuje ich disk stĺpec';

    private const SOURCE_DISKS = ['local', 'public'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');
        $chunkSize = max(1, (int) $this->option('chunk'));

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
