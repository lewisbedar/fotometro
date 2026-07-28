<?php

namespace App\Console\Commands;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Console\Command;

class ProcessPhotosCommand extends Command
{
    protected $signature = 'fotometro:process-photos
        {--limit=10 : Number of photos to process}
        {--photo= : Process one photo id}
        {--retry-failed : Include failed photos}
        {--force : Reprocess ready photos too}';

    protected $description = 'Process pending fotometro photos without requiring a permanent worker.';

    public function handle(PhotoProcessor $processor): int
    {
        $query = Photo::query()->with('station')->orderBy('created_at');

        if ($this->option('photo')) {
            $query->whereKey($this->option('photo'));
        } elseif ($this->option('force')) {
            $query->limit((int) $this->option('limit'));
        } else {
            $statuses = [PhotoProcessingStatus::Pending];

            if ($this->option('retry-failed')) {
                $statuses[] = PhotoProcessingStatus::Failed;
            }

            $query->whereIn('processing_status', $statuses)->limit((int) $this->option('limit'));
        }

        $processed = 0;
        $ready = 0;
        $failed = 0;

        foreach ($query->get() as $photo) {
            $photo->forceFill(['processing_status' => PhotoProcessingStatus::Processing])->save();
            $result = $processor->process($photo, (bool) $this->option('force'));
            $processed++;
            $result->processing_status === PhotoProcessingStatus::Ready ? $ready++ : $failed++;
        }

        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Ready', $ready],
            ['Failed', $failed],
        ]);

        return self::SUCCESS;
    }
}
