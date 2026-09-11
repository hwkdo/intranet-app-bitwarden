<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Commands;

use Hwkdo\IntranetAppBitwarden\Jobs\RunBitwardenFullResetJob;
use Hwkdo\IntranetAppBitwarden\Services\BitwardenFullResetService;
use Illuminate\Console\Command;

class FullResetBitwardenCommand extends Command
{
    protected $signature = 'intranet-app-bitwarden:full-reset
                            {--keep=do.it@hwk-do.de : E-Mail des Kontos, das erhalten bleibt}
                            {--delete-accounts : Vaultwarden-Konten (nicht nur Org-Mitgliedschaften) löschen}
                            {--keep-accounts : Nur aus Org entfernen, Konten behalten}
                            {--dry-run : Nur zählen/loggen, nichts löschen}
                            {--delay=400 : Pause zwischen API-Calls in Millisekunden}
                            {--queue : Als Queue-Job ausführen}
                            {--force : Bestätigung überspringen}';

    protected $description = 'Full Reset: Collections, Gruppen und Mitglieder in Bitwarden löschen; GVP-IDs leeren';

    public function handle(BitwardenFullResetService $service): int
    {
        $keepEmail = (string) $this->option('keep');
        $dryRun = (bool) $this->option('dry-run');
        $delayMs = max(0, (int) $this->option('delay'));
        $deleteAccounts = ! (bool) $this->option('keep-accounts');

        if ($this->option('delete-accounts')) {
            $deleteAccounts = true;
        }

        $this->warn('Bitwarden Full Reset');
        $this->line("Keep-E-Mail: {$keepEmail}");
        $this->line('Konten löschen: '.($deleteAccounts ? 'ja' : 'nein'));
        $this->line('Dry-Run: '.($dryRun ? 'ja' : 'nein'));
        $this->line("Delay: {$delayMs} ms");

        if (! $dryRun && ! $this->option('force') && ! $this->option('queue')) {
            if (! $this->confirm('Wirklich ALLES außer dem Keep-Konto zurücksetzen?', false)) {
                $this->info('Abgebrochen.');

                return self::SUCCESS;
            }

            $phrase = $this->ask('Bitte Bestätigungsphrase eingeben ('.BitwardenFullResetService::CONFIRM_PHRASE.')');

            if (trim((string) $phrase) !== BitwardenFullResetService::CONFIRM_PHRASE) {
                $this->error('Falsche Bestätigungsphrase — abgebrochen.');

                return self::FAILURE;
            }
        }

        if ($this->option('queue')) {
            RunBitwardenFullResetJob::dispatch(
                keepEmail: $keepEmail,
                deleteAccounts: $deleteAccounts,
                dryRun: $dryRun,
                delayMs: $delayMs,
            );
            $this->info('Full Reset als Queue-Job gestartet.');

            return self::SUCCESS;
        }

        $result = $service->reset(
            keepEmail: $keepEmail,
            deleteAccounts: $deleteAccounts,
            dryRun: $dryRun,
            delayMs: $delayMs,
            onProgress: fn (string $message) => $this->line($message),
        );

        $this->info($result->summary());

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
