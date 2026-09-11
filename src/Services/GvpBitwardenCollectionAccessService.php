<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

class GvpBitwardenCollectionAccessService
{
    public function __construct(
        protected BitwardenManagementApiInterface $apiService,
        protected BitwardenVaultApiService $vaultApiService,
    ) {}

    public function syncDirectCollectionAccess(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenCollection()) {
            return;
        }

        try {
            $payload = $this->buildDirectCollectionPayload($gvp);
            $this->vaultApiService->updateCollection(
                (string) $gvp->bitwarden_collection_id,
                $payload,
            );
        } catch (Throwable $exception) {
            Log::error('GvpBitwardenCollectionAccessService: Direct-Collection-ACL Sync fehlgeschlagen', [
                'gvp_id' => $gvp->id,
                'collection_id' => $gvp->bitwarden_collection_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function syncGesamtCollectionAccess(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenGesamtCollection()) {
            return;
        }

        try {
            $payload = $this->buildGesamtCollectionPayload($gvp);
            $this->vaultApiService->updateCollection(
                (string) $gvp->bitwarden_gesamt_collection_id,
                $payload,
            );
        } catch (Throwable $exception) {
            Log::error('GvpBitwardenCollectionAccessService: Gesamt-Collection-ACL Sync fehlgeschlagen', [
                'gvp_id' => $gvp->id,
                'collection_id' => $gvp->bitwarden_gesamt_collection_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{name: string, externalId: string, groups: list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>, users: list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>}
     */
    public function buildDirectCollectionPayload(Gvp $gvp, ?array $membersByEmail = null): array
    {
        $membersByEmail ??= $this->memberMapByEmail();

        return [
            'name' => $gvp->bezeichnung,
            'externalId' => 'gvp-'.$gvp->id,
            'groups' => $this->buildDirectCollectionGroups($gvp),
            'users' => $this->buildManageUsers($gvp, $membersByEmail),
        ];
    }

    /**
     * @return array{name: string, externalId: string, groups: list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>, users: list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>}
     */
    public function buildGesamtCollectionPayload(Gvp $gvp, ?array $membersByEmail = null): array
    {
        $membersByEmail ??= $this->memberMapByEmail();

        return [
            'name' => $gvp->bezeichnung.' (Gesamt)',
            'externalId' => 'gvp-'.$gvp->id.'-gesamt',
            'groups' => $this->buildGesamtCollectionGroups($gvp),
            'users' => $this->buildManageUsers($gvp, $membersByEmail),
        ];
    }

    /**
     * @return list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>
     */
    public function buildDirectCollectionGroups(Gvp $gvp): array
    {
        if (! $gvp->hasBitwardenGroup()) {
            return [];
        }

        return [$this->groupAccessEntry((string) $gvp->bitwarden_group_id)];
    }

    /**
     * @return list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>
     */
    public function buildGesamtCollectionGroups(Gvp $gvp): array
    {
        $groupIds = [];

        if ($gvp->hasBitwardenGroup()) {
            $groupIds[(string) $gvp->bitwarden_group_id] = true;
        }

        $children = $gvp->relationLoaded('childGvps')
            ? $gvp->childGvps
            : $gvp->childGvps()->get();

        foreach ($children as $child) {
            if ($child->hasBitwardenGroup()) {
                $groupIds[(string) $child->bitwarden_group_id] = true;
            }
        }

        $groups = [];

        foreach (array_keys($groupIds) as $groupId) {
            $groups[] = $this->groupAccessEntry($groupId);
        }

        return $groups;
    }

    /**
     * Vorgesetzte und Stellvertreter mit manage=true.
     *
     * @param  array<string, string>  $membersByEmail  email_lower => memberId
     * @return list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>
     */
    public function buildManageUsers(Gvp $gvp, array $membersByEmail): array
    {
        $emails = [];

        $gvp->loadMissing(['vorgesetzter', 'stellvertreter']);

        foreach ([$gvp->vorgesetzter, $gvp->stellvertreter] as $user) {
            if ($user === null) {
                continue;
            }

            $email = trim((string) ($user->email ?? ''));

            if ($email === '') {
                continue;
            }

            $emails[strtolower($email)] = $email;
        }

        $users = [];

        foreach ($emails as $lowerEmail => $originalEmail) {
            if (! isset($membersByEmail[$lowerEmail])) {
                continue;
            }

            $users[] = [
                'id' => $membersByEmail[$lowerEmail],
                'readOnly' => false,
                'hidePasswords' => false,
                'manage' => true,
            ];
        }

        return $users;
    }

    /**
     * @return array<string, string> email_lower => memberId
     */
    public function memberMapByEmail(): array
    {
        $map = [];

        foreach (OrganizationMemberStatus::unwrapMembers($this->apiService->getMembers()) as $member) {
            $email = trim((string) ($member['email'] ?? ''));
            $id = OrganizationMemberStatus::id($member);

            if ($email === '' || $id === '') {
                continue;
            }

            $map[strtolower($email)] = $id;
        }

        return $map;
    }

    /**
     * @return array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}
     */
    protected function groupAccessEntry(string $groupId): array
    {
        return [
            'id' => $groupId,
            'readOnly' => false,
            'hidePasswords' => false,
            'manage' => false,
        ];
    }
}
