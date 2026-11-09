<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\User;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppBitwarden\Support\UserSecretKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class UserApiKeyVaultService
{
    private const CACHE_KEY = 'intranet-app-bitwarden.confirmed-member-ids-by-email';

    public function __construct(
        protected BitwardenManagementApiInterface $membersApi,
        protected BitwardenVaultApiService $vaultApi,
        protected BitwardenConfigService $config,
    ) {}

    /**
     * eligible, ineligible oder unavailable.
     */
    public function status(User $user): string
    {
        $email = strtolower(trim((string) $user->email));

        if ($email === '') {
            return 'ineligible';
        }

        try {
            $memberIds = $this->confirmedMemberIdsByEmail();
        } catch (Throwable $exception) {
            Log::warning('Bitwarden-Mitgliedschaft für Schlüssel-Speichern nicht prüfbar', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return 'unavailable';
        }

        return isset($memberIds[$email]) ? 'eligible' : 'ineligible';
    }

    public function store(User $user, string $kind, string $secret): void
    {
        $secret = trim($secret);

        if ($secret === '') {
            throw new RuntimeException('Es ist kein Schlüssel zum Speichern vorhanden.');
        }

        if (! UserSecretKind::isValid($kind)) {
            throw new RuntimeException('Unbekannte Schlüssel-Art.');
        }

        if ($this->status($user) !== 'eligible') {
            throw new RuntimeException('Dein Bitwarden-Konto ist noch nicht bestätigt.');
        }

        $organizationId = trim($this->config->getOrganizationId());

        if ($organizationId === '') {
            throw new RuntimeException('Die Bitwarden-Organisation ist nicht konfiguriert.');
        }

        try {
            $collectionId = $this->ensureCollection($user, $organizationId);
            $this->upsertLogin($user, $organizationId, $collectionId, $kind, $secret);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Bitwarden-Schlüssel konnte nicht gespeichert werden', [
                'user_id' => $user->id,
                'kind' => $kind,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('Der Schlüssel konnte nicht in Bitwarden gespeichert werden.');
        }
    }

    /**
     * @return array<string, string> email_lower => organization user id
     */
    protected function confirmedMemberIdsByEmail(): array
    {
        /** @var array<string, string> $memberIds */
        $memberIds = Cache::remember(self::CACHE_KEY, now()->addMinutes(2), function (): array {
            $map = [];

            foreach (OrganizationMemberStatus::unwrapMembers($this->membersApi->getMembers()) as $member) {
                if (! OrganizationMemberStatus::isConfirmed($member)) {
                    continue;
                }

                $email = OrganizationMemberStatus::email($member);
                $id = OrganizationMemberStatus::id($member);

                if ($email === '' || $id === '') {
                    continue;
                }

                $map[$email] = $id;
            }

            return $map;
        });

        return $memberIds;
    }

    protected function ensureCollection(User $user, string $organizationId): string
    {
        $this->vaultApi->ensureUnlocked();

        $externalId = UserSecretKind::collectionExternalId($user->id);
        $email = trim((string) $user->email);
        $name = UserSecretKind::collectionName($email);
        $orgUserId = $this->confirmedMemberIdsByEmail()[strtolower($email)] ?? '';

        if ($orgUserId === '') {
            throw new RuntimeException('Dein Bitwarden-Konto ist noch nicht bestätigt.');
        }

        $payload = [
            'name' => $name,
            'externalId' => $externalId,
            'groups' => [],
            'users' => [[
                'id' => $orgUserId,
                'readOnly' => false,
                'hidePasswords' => false,
                'manage' => false,
            ]],
        ];

        $existingId = $this->findCollectionId($externalId);

        if ($existingId === null) {
            return $this->extractCollectionId($this->vaultApi->createCollection($payload, $organizationId));
        }

        $this->vaultApi->updateCollection($existingId, $payload, $organizationId);

        return $existingId;
    }

    protected function findCollectionId(string $externalId): ?string
    {
        foreach ($this->unwrapCollections($this->vaultApi->listOrgCollections()) as $collection) {
            if ((string) ($collection['externalId'] ?? '') === $externalId) {
                $id = trim((string) ($collection['id'] ?? ''));

                return $id !== '' ? $id : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function unwrapCollections(array $response): array
    {
        return $this->unwrapList($response);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    protected function unwrapList(array $response): array
    {
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            $response = $response['data']['data'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $response = $response['data'];
        }

        $items = [];

        foreach ($response as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractCollectionId(array $response): string
    {
        $id = $response['id'] ?? null;

        if (is_string($id) && $id !== '') {
            return $id;
        }

        if (isset($response['data']['id']) && is_string($response['data']['id']) && $response['data']['id'] !== '') {
            return $response['data']['id'];
        }

        throw new RuntimeException('Die Bitwarden-Sammlung wurde angelegt, aber ohne ID zurückgegeben.');
    }

    protected function upsertLogin(User $user, string $organizationId, string $collectionId, string $kind, string $secret): void
    {
        $this->vaultApi->sync();

        $items = $this->unwrapList($this->vaultApi->listItems($collectionId));
        $existing = $this->findItem($items, $kind);
        $cipher = $this->cipherPayload($user, $organizationId, $collectionId, $kind, $secret);

        if ($existing === null) {
            $this->vaultApi->createItem($cipher);

            return;
        }

        $cipher['id'] = $existing['id'];
        $this->vaultApi->updateItem((string) $existing['id'], $cipher);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    protected function findItem(array $items, string $kind): ?array
    {
        $name = UserSecretKind::itemName($kind);

        foreach ($items as $item) {
            if (trim((string) ($item['id'] ?? '')) === '') {
                continue;
            }

            foreach ($item['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['name'] ?? '') === UserSecretKind::FIELD_NAME && ($field['value'] ?? '') === $kind && $this->itemBelongsToThisInstance($item)) {
                    return $item;
                }
            }
        }

        foreach ($items as $item) {
            if (($item['name'] ?? '') === $name && trim((string) ($item['id'] ?? '')) !== '') {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function itemBelongsToThisInstance(array $item): bool
    {
        $instanceUrl = UserSecretKind::instanceUrl();

        foreach ($item['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['name'] ?? '') === UserSecretKind::INSTANCE_FIELD) {
                return rtrim(trim((string) ($field['value'] ?? '')), '/') === $instanceUrl;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function cipherPayload(User $user, string $organizationId, string $collectionId, string $kind, string $secret): array
    {
        $uri = UserSecretKind::uri($kind);
        $uris = $uri === '' ? [] : [[
            'match' => null,
            'uri' => $uri,
        ]];

        return [
            'organizationId' => $organizationId,
            'collectionIds' => [$collectionId],
            'type' => 1,
            'name' => UserSecretKind::itemName($kind),
            'notes' => null,
            'favorite' => false,
            'reprompt' => 0,
            'fields' => [
                [
                    'name' => UserSecretKind::FIELD_NAME,
                    'value' => $kind,
                    'type' => 0,
                ],
                [
                    'name' => UserSecretKind::INSTANCE_FIELD,
                    'value' => UserSecretKind::instanceUrl(),
                    'type' => 0,
                ],
            ],
            'login' => [
                'username' => trim((string) $user->email),
                'password' => $secret,
                'uris' => $uris,
                'totp' => null,
            ],
        ];
    }
}
