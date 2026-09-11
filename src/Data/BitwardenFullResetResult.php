<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Data;

class BitwardenFullResetResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $skipped
     */
    public function __construct(
        public int $clearedGvps = 0,
        public int $deletedCollections = 0,
        public int $deletedGroups = 0,
        public int $removedOrgMembers = 0,
        public int $deletedUserAccounts = 0,
        public int $failed = 0,
        public array $errors = [],
        public array $skipped = [],
        public bool $dryRun = false,
        public string $keepEmail = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cleared_gvps' => $this->clearedGvps,
            'deleted_collections' => $this->deletedCollections,
            'deleted_groups' => $this->deletedGroups,
            'removed_org_members' => $this->removedOrgMembers,
            'deleted_user_accounts' => $this->deletedUserAccounts,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'dry_run' => $this->dryRun,
            'keep_email' => $this->keepEmail,
            'finished_at' => now()->toIso8601String(),
        ];
    }

    public function summary(): string
    {
        $prefix = $this->dryRun ? 'Dry-Run: ' : '';

        return $prefix.sprintf(
            'GVPs geleert: %d, Collections: %d, Gruppen: %d, Org-Mitglieder: %d, Konten: %d, Fehler: %d',
            $this->clearedGvps,
            $this->deletedCollections,
            $this->deletedGroups,
            $this->removedOrgMembers,
            $this->deletedUserAccounts,
            $this->failed,
        );
    }
}
