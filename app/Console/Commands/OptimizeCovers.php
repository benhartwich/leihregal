<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\CoverImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeCovers extends Command
{
    protected $signature   = 'covers:optimize {--min-bytes=200000 : Only resize files at least this big}';
    protected $description = 'Resize stored cover images to max ' . CoverImageService::MAX_WIDTH . 'x' . CoverImageService::MAX_HEIGHT;

    public function handle(CoverImageService $svc): int
    {
        $minBytes = (int) $this->option('min-bytes');
        $disk     = Storage::disk('public');

        $count   = 0;
        $bytesIn = 0;
        $bytesOut = 0;

        Media::whereNotNull('cover_path')->chunkById(50, function ($media) use ($svc, $disk, $minBytes, &$count, &$bytesIn, &$bytesOut) {
            foreach ($media as $m) {
                if (! $disk->exists($m->cover_path)) continue;
                $size = $disk->size($m->cover_path);
                if ($size < $minBytes) continue;

                $contents = $disk->get($m->cover_path);
                $resized  = $svc->resize($contents);
                if (! $resized) {
                    $this->warn("skip (resize failed): {$m->cover_path}");
                    continue;
                }
                if (strlen($resized) >= $size) {
                    $this->line("skip (no gain): {$m->cover_path}");
                    continue;
                }
                $disk->put($m->cover_path, $resized);
                $bytesIn += $size;
                $bytesOut += strlen($resized);
                $count++;
                $this->line(sprintf('%s: %d → %d KB', $m->cover_path, $size / 1024, strlen($resized) / 1024));
            }
        });

        $this->info(sprintf('%d files resized. %d KB → %d KB', $count, $bytesIn / 1024, $bytesOut / 1024));
        return self::SUCCESS;
    }
}
