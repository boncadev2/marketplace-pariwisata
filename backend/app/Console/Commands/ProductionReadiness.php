<?php

namespace App\Console\Commands;

use App\Services\ProductionReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:production-readiness {--strict : Return a failure exit code if any prerequisite is missing}')]
#[Description('Check launch prerequisites without exposing credentials or enabling production')]
class ProductionReadiness extends Command
{
    public function handle(ProductionReadinessService $service): int
    {
        $report = $service->report();
        $this->table(['Pemeriksaan', 'Status', 'Tindak lanjut'], collect($report['checks'])->map(fn ($check) => [$check['label'], $check['ready'] ? 'Siap' : 'Belum siap', $check['ready'] ? '—' : $check['instruction']])->all());
        $this->info($report['note']);

        return $this->option('strict') && ! $report['ready'] ? self::FAILURE : self::SUCCESS;
    }
}
