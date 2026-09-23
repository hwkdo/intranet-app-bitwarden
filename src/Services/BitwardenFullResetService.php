<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Services\VaultwardenAdminApiService;
use Hwkdo\BitwardenLaravel\Support\ApiResponseNormalizer;
use Hwkdo\IntranetAppBitwarden\Data\BitwardenFullResetResult;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollection;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollectionMember;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenSyncGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BitwardenFullResetService
{
    public const DEFAULT_KEEP_EMAIL = 'do.it@hwk-do.de';

    public const CONFIRM_PHRASE = 'FULL RESET';

    public const LAST_RESULT_CACHE_KEY = 'intranet-app-bitwarden.full-reset.last-result';

    public function __construct(
        protected BitwardenManagementApiInterface $managementApi,
        protected BitwardenVaultApiService $vaultApi,
        protected VaultwardenAdminApiService $adminApi,
    ) {}

    /**
     * @param  callable(string): void|null  $onProgress
     */
    public function reset(
        string $keepEmail = self::DEFAULT_KEEP_EMAIL,
        bool $deleteAccounts = true,
        bool $dryRun = false,
        int $delayMs = 400,
        ?callable $onProgress = null,
    ): BitwardenFullResetResult {
        $keepEmail = strtolower(trim($keepEmail));

        if ($keepEmail === '' || ! filter_var($keepEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('keepEmail muss eine gültige E-Mail-Adresse sein.');
        }

        $delayMs = max(0, $delayMs);
        $result = new BitwardenFullResetResult(dryRun: $dryRun, keepEmail: $keepEmail);

        $lock = Cache::lock('intranet-app-bitwarden.full-reset', 3600);

        if (! $lock->get()) {
            throw new \RuntimeException('Ein Full Reset läuft bereits.');
        }

        try {
            @set_time_limit(0);
            BitwardenSyncGuard::pause(3600);
            $this->progress($onProgress, 'Sync pausiert.');

            $this->clearGvpIds($result, $dryRun, $onProgress);
            $this->deleteAllCollections($result, $dryRun, $delayMs, $onProgress);
            $this->deleteAllGroups($result, $dryRun, $delayMs, $onProgress);
            $this->deleteAllMembers($result, $keepEmail, $deleteAccounts, $dryRun, $delayMs, $onProgress);

            $this->progress($onProgress, $result->summary());
            Cache::put(self::LAST_RESULT_CACHE_KEY, $result->toArray(), now()->addDay());

            Log::info('Bitwarden full reset finished', $result->toArray());
        } finally {
            BitwardenSyncGuard::resume();
            $lock->release();
            $this->progress($onProgress, 'Sync wieder freigegeben.');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastResult(): ?array
    {
        $result = Cache::get(self::LAST_RESULT_CACHE_KEY);

        return is_array($result) ? $result : null;
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function clearGvpIds(
        BitwardenFullResetResult $result,
        bool $dryRun,
        ?callable $onProgress,
    ): void {
        if (! class_exists(Gvp::class)) {
            $this->progress($onProgress, 'Gvp-Modell nicht verfügbar — DB-Clear übersprungen.');

            return;
        }

        $query = Gvp::query()->where(function ($builder): void {
            $builder->whereNotNull('bitwarden_group_id')
                ->orWhereNotNull('bitwarden_collection_id')
                ->orWhereNotNull('bitwarden_gesamt_collection_id');
        });

        $count = (clone $query)->count();
        $this->progress($onProgress, "GVP-IDs leeren ({$count})…");

        if (! $dryRun && $count > 0) {
            $query->update([
                'bitwarden_group_id' => null,
                'bitwarden_collection_id' => null,
                'bitwarden_gesamt_collection_id' => null,
            ]);
        }

        $result->clearedGvps = $count;

        $this->clearCustomCollections($dryRun, $onProgress);
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function clearCustomCollections(bool $dryRun, ?callable $onProgress): void
    {
        $memberCount = CustomCollectionMember::query()->count();
        $collectionCount = CustomCollection::query()->count();

        $this->progress($onProgress, "Custom-Sammlungen leeren ({$collectionCount} / {$memberCount} Mitglieder)…");

        if ($dryRun || ($collectionCount === 0 && $memberCount === 0)) {
            return;
        }

        CustomCollectionMember::query()->delete();
        CustomCollection::query()->delete();
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function deleteAllCollections(
        BitwardenFullResetResult $result,
        bool $dryRun,
        int $delayMs,
        ?callable $onProgress,
    ): void {
        $collections = $this->withRetry(
            fn (): array => $this->unwrapCollections($this->vaultApi->listOrgCollections()),
            $delayMs,
        );

        $this->progress($onProgress, 'Collections löschen ('.count($collections).')…');

        foreach ($collections as $collection) {
            $id = (string) ($collection['id'] ?? '');
            $name = (string) ($collection['name'] ?? $id);

            if ($id === '') {
                continue;
            }

            try {
                if (! $dryRun) {
                    $this->withRetry(
                        function () use ($id): void {
                            $this->vaultApi->deleteCollection($id);
                        },
                        $delayMs,
                    );
                } else {
                    $this->throttle($delayMs);
                }

                $result->deletedCollections++;
                $this->progress($onProgress, "Collection gelöscht: {$name}");
            } catch (Throwable $e) {
                $result->failed++;
                $message = "Collection {$name}: {$e->getMessage()}";
                $result->errors[] = $message;
                Log::warning('Bitwarden full reset collection failed', ['id' => $id, 'error' => $e->getMessage()]);
                $this->throttle($delayMs);
            }
        }
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function deleteAllGroups(
        BitwardenFullResetResult $result,
        bool $dryRun,
        int $delayMs,
        ?callable $onProgress,
    ): void {
        $groups = $this->withRetry(
            fn (): array => ApiResponseNormalizer::unwrapList($this->managementApi->getGroups()),
            $delayMs,
        );

        $this->progress($onProgress, 'Gruppen löschen ('.count($groups).')…');

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $id = (string) ($group['id'] ?? '');
            $name = (string) ($group['name'] ?? $id);

            if ($id === '') {
                continue;
            }

            try {
                if (! $dryRun) {
                    $this->withRetry(
                        function () use ($id): void {
                            $this->managementApi->deleteGroup($id);
                        },
                        $delayMs,
                    );
                } else {
                    $this->throttle($delayMs);
                }

                $result->deletedGroups++;
                $this->progress($onProgress, "Gruppe gelöscht: {$name}");
            } catch (Throwable $e) {
                $result->failed++;
                $message = "Gruppe {$name}: {$e->getMessage()}";
                $result->errors[] = $message;
                Log::warning('Bitwarden full reset group failed', ['id' => $id, 'error' => $e->getMessage()]);
                $this->throttle($delayMs);
            }
        }
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function deleteAllMembers(
        BitwardenFullResetResult $result,
        string $keepEmail,
        bool $deleteAccounts,
        bool $dryRun,
        int $delayMs,
        ?callable $onProgress,
    ): void {
        $members = $this->withRetry(
            fn (): array => ApiResponseNormalizer::unwrapList($this->managementApi->getMembers()),
            $delayMs,
        );

        $this->progress($onProgress, 'Mitglieder entfernen ('.count($members).'), Keep: '.$keepEmail.'…');

        /** @var list<array{email: string, userId: string}> $accountsToDelete */
        $accountsToDelete = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $email = strtolower(trim((string) ($member['email'] ?? '')));
            $orgUserId = (string) ($member['id'] ?? '');
            $userId = trim((string) ($member['userId'] ?? ''));

            if ($email === $keepEmail) {
                $result->skipped[] = "Keep: {$email}";
                $this->progress($onProgress, "Übersprungen (Keep): {$email}");

                continue;
            }

            if ($orgUserId === '') {
                continue;
            }

            try {
                if (! $dryRun) {
                    $this->withRetry(
                        function () use ($orgUserId): void {
                            $this->managementApi->deleteMember($orgUserId);
                        },
                        $delayMs,
                    );
                } else {
                    $this->throttle($delayMs);
                }

                $result->removedOrgMembers++;
                $this->progress($onProgress, "Org-Mitglied entfernt: {$email}");
            } catch (Throwable $e) {
                $result->failed++;
                $message = "Org-Mitglied {$email}: {$e->getMessage()}";
                $result->errors[] = $message;
                Log::warning('Bitwarden full reset member failed', [
                    'email' => $email,
                    'id' => $orgUserId,
                    'error' => $e->getMessage(),
                ]);
                $this->throttle($delayMs);
            }

            if ($deleteAccounts && $userId !== '') {
                $accountsToDelete[] = ['email' => $email !== '' ? $email : $userId, 'userId' => $userId];
            }
        }

        if (! $deleteAccounts) {
            return;
        }

        $this->progress($onProgress, 'Vaultwarden-Konten löschen ('.count($accountsToDelete).')…');

        foreach ($accountsToDelete as $account) {
            try {
                if (! $dryRun) {
                    $this->withRetry(
                        function () use ($account): void {
                            $this->adminApi->deleteUserAccount($account['userId']);
                        },
                        $delayMs,
                    );
                } else {
                    $this->throttle($delayMs);
                }

                $result->deletedUserAccounts++;
                $this->progress($onProgress, "Konto gelöscht: {$account['email']}");
            } catch (Throwable $e) {
                $result->failed++;
                $message = "Konto {$account['email']}: {$e->getMessage()}";
                $result->errors[] = $message;
                Log::warning('Bitwarden full reset account failed', [
                    'email' => $account['email'],
                    'userId' => $account['userId'],
                    'error' => $e->getMessage(),
                ]);
                $this->throttle($delayMs);
            }
        }
    }

    /**
     * @param  array<mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function unwrapCollections(array $response): array
    {
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            return array_values(array_filter(
                $response['data']['data'],
                static fn ($item): bool => is_array($item),
            ));
        }

        $list = ApiResponseNormalizer::unwrapList($response);

        return array_values(array_filter(
            $list,
            static fn ($item): bool => is_array($item),
        ));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withRetry(callable $callback, int $delayMs, int $maxAttempts = 6): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $value = $callback();
                $this->throttle($delayMs);

                return $value;
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts || ! $this->isRetryable($e)) {
                    throw $e;
                }

                $backoffMs = min(30_000, (int) ($delayMs * (2 ** ($attempt - 1))));
                Log::warning('Bitwarden full reset retry', [
                    'attempt' => $attempt,
                    'backoff_ms' => $backoffMs,
                    'error' => $e->getMessage(),
                ]);
                usleep($backoffMs * 1000);
            }
        }
    }

    protected function isRetryable(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, '429')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
            || str_contains($message, '503')
            || str_contains($message, '502')
            || str_contains($message, '504')
            || str_contains($message, 'timeout')
            || str_contains($message, 'timed out');
    }

    protected function throttle(int $delayMs): void
    {
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * @param  callable(string): void|null  $onProgress
     */
    protected function progress(?callable $onProgress, string $message): void
    {
        if ($onProgress !== null) {
            $onProgress($message);
        }
    }
}
