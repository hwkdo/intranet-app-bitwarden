<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Jobs;

use Hwkdo\IntranetAppBitwarden\Services\BitwardenFullResetService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunBitwardenFullResetJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public string $keepEmail = BitwardenFullResetService::DEFAULT_KEEP_EMAIL,
        public bool $deleteAccounts = true,
        public bool $dryRun = false,
        public int $delayMs = 400,
        public ?int $initiatedByUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return 'bitwarden-full-reset';
    }

    public function handle(BitwardenFullResetService $service): void
    {
        Log::info('Bitwarden full reset job started', [
            'keep_email' => $this->keepEmail,
            'delete_accounts' => $this->deleteAccounts,
            'dry_run' => $this->dryRun,
            'initiated_by' => $this->initiatedByUserId,
        ]);

        $service->reset(
            keepEmail: $this->keepEmail,
            deleteAccounts: $this->deleteAccounts,
            dryRun: $this->dryRun,
            delayMs: $this->delayMs,
            onProgress: static function (string $message): void {
                Log::info('Bitwarden full reset: '.$message);
            },
        );
    }
}
