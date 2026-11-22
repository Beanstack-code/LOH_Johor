<?php

namespace App\Console\Commands;

use App\Services\FraudDetectionService;
use Illuminate\Console\Command;

class DetectFraud extends Command
{
    protected $signature = 'pkpms:detect-fraud';

    protected $description = 'Evaluate enabled fraud rules and persist alerts.';

    public function handle(FraudDetectionService $service): int
    {
        $summary = $service->detect();

        $this->info(sprintf(
            'Fraud detection complete | rules_evaluated=%d alerts_created_or_updated=%d',
            (int) $summary['rules_evaluated'],
            (int) $summary['alerts_created_or_updated'],
        ));

        return self::SUCCESS;
    }
}
