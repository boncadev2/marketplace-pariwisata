<?php

namespace App\Jobs;

use App\Services\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyReconciliationReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public ?string $date = null) {}

    /**
     * Execute the job.
     */
    public function handle(ReconciliationService $reconciliationService): void
    {
        $date = $this->date === null
            ? CarbonImmutable::now('Asia/Jakarta')->subDay()->toDateString()
            : CarbonImmutable::parse($this->date, 'Asia/Jakarta')->toDateString();

        $reconciliationService->generateDailyReport($date);
    }
}
