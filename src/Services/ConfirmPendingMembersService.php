<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppBitwarden\Data\AppSettings;
use Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ConfirmPendingMembersService
{
    private const PENDING_COUNT_CACHE_KEY = 'intranet-app-bitwarden.pending_members_count';

    private const PENDING_COUNT_CACHE_SECONDS = 60;

    public function __construct(
        private readonly BitwardenManagementApiInterface $api,
        private readonly BitwardenVaultApiService $vault,
    ) {}

    public function appSettings(): AppSettings
    {
        $current = IntranetAppBitwardenSettings::current();

        if ($current?->settings instanceof AppSettings) {
            return $current->settings;
        }

        return new AppSettings;
    }

    public function autoConfirmEnabled(): bool
    {
        return $this->appSettings()->autoConfirmEnabled;
    }

    public function staleDays(): int
    {
        return max(1, $this->appSettings()->pendingConfirmStaleDays);
    }

    /**
     * Alle Org-Mitglieder, die noch nicht confirmed sind (Invited/Accepted).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingMembers(): array
    {
        return OrganizationMemberStatus::pendingMembers($this->api->getMembers());
    }

    /**
     * Anzahl unbestätigter Mitglieder (kurz gecacht für Nav-Badge).
     */
    public function pendingCount(bool $fresh = false): int
    {
        if ($fresh) {
            Cache::forget(self::PENDING_COUNT_CACHE_KEY);
        }

        return (int) Cache::remember(
            self::PENDING_COUNT_CACHE_KEY,
            now()->addSeconds(self::PENDING_COUNT_CACHE_SECONDS),
            function (): int {
                try {
                    return count($this->listPendingMembers());
                } catch (Throwable $exception) {
                    Log::warning('ConfirmPendingMembersService: Pending-Count fehlgeschlagen', [
                        'message' => $exception->getMessage(),
                    ]);

                    return 0;
                }
            },
        );
    }

    public function forgetPendingCountCache(): void
    {
        Cache::forget(self::PENDING_COUNT_CACHE_KEY);
    }

    /**
     * Mitglieder, die jetzt bestätigt werden können.
     *
     * @param  list<string>|null  $emailFilterLower
     * @return list<array<string, mixed>>
     */
    public function listConfirmableMembers(?array $emailFilterLower = null): array
    {
        return OrganizationMemberStatus::membersNeedingConfirm(
            $this->api->getMembers(),
            $emailFilterLower,
        );
    }

    /**
     * Bestätigt alle confirmbaren Org-Mitglieder (für Scheduler).
     *
     * @return array{attempted: int, confirmed: int, skipped: bool, errors: list<string>}
     */
    public function confirmAllPending(bool $respectAutoConfirmSetting = true): array
    {
        if ($respectAutoConfirmSetting && ! $this->autoConfirmEnabled()) {
            return [
                'attempted' => 0,
                'confirmed' => 0,
                'skipped' => true,
                'errors' => [],
            ];
        }

        return $this->confirmMembers(
            OrganizationMemberStatus::membersNeedingConfirm($this->api->getMembers()),
        );
    }

    /**
     * Bestätigt confirmbare Mitglieder aus einer bereits geladenen Member-Liste
     * (optional gefiltert auf E-Mails) – z. B. nach GVP-Sync.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $apiResponse
     * @param  list<string>|null  $emailFilterLower
     * @return array{attempted: int, confirmed: int, skipped: bool, errors: list<string>}
     */
    public function confirmFromMembersList(array $apiResponse, ?array $emailFilterLower = null): array
    {
        return $this->confirmMembers(
            OrganizationMemberStatus::membersNeedingConfirm($apiResponse, $emailFilterLower),
        );
    }

    /**
     * @return array{attempted: int, confirmed: int, skipped: bool, errors: list<string>}
     */
    public function confirmMemberById(string $memberId): array
    {
        $memberId = trim($memberId);
        if ($memberId === '') {
            return [
                'attempted' => 0,
                'confirmed' => 0,
                'skipped' => false,
                'errors' => ['Mitglied-ID fehlt'],
            ];
        }

        return $this->confirmMembers([['id' => $memberId, 'status' => OrganizationMemberStatus::ACCEPTED]]);
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array{attempted: int, confirmed: int, skipped: bool, errors: list<string>}
     */
    private function confirmMembers(array $members): array
    {
        if ($members === []) {
            return [
                'attempted' => 0,
                'confirmed' => 0,
                'skipped' => false,
                'errors' => [],
            ];
        }

        try {
            $this->vault->ensureUnlocked();
        } catch (\Throwable $exception) {
            Log::error('ConfirmPendingMembersService: Vault konnte nicht entsperrt werden', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'attempted' => count($members),
                'confirmed' => 0,
                'skipped' => false,
                'errors' => ['Vault unlock fehlgeschlagen: '.$exception->getMessage()],
            ];
        }

        $confirmed = 0;
        $errors = [];

        foreach ($members as $member) {
            $memberId = OrganizationMemberStatus::id($member);
            if ($memberId === '') {
                continue;
            }

            try {
                $this->vault->confirmMember($memberId);
                $confirmed++;
            } catch (\Throwable $exception) {
                $errors[] = $memberId.': '.$exception->getMessage();
                Log::error('ConfirmPendingMembersService: Confirm fehlgeschlagen', [
                    'member_id' => $memberId,
                    'email' => OrganizationMemberStatus::email($member),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($confirmed > 0) {
            $this->forgetPendingCountCache();
        }

        return [
            'attempted' => count($members),
            'confirmed' => $confirmed,
            'skipped' => false,
            'errors' => $errors,
        ];
    }
}
