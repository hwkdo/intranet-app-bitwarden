<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\User;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppBitwarden\Data\AppSettings;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollection;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollectionMember;
use Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class SupervisorCustomCollectionService
{
    public function __construct(
        protected SupervisorBitwardenScope $supervisorScope,
        protected BitwardenManagementApiInterface $apiService,
        protected BitwardenVaultApiService $vaultApiService,
    ) {}

    /**
     * Intranet-User, die in Vaultwarden als Org-Mitglied existieren (E-Mail-Match).
     *
     * @return Collection<int, User>
     */
    public function selectableVaultwardenUsers(?User $exclude = null): Collection
    {
        $memberMap = $this->confirmedMemberMapByEmail();

        if ($memberMap === []) {
            return collect();
        }

        $emails = array_keys($memberMap);

        $query = User::query()
            ->where('active', true)
            ->where(function ($emailQuery) use ($emails): void {
                foreach ($emails as $email) {
                    $emailQuery->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->orderBy('nachname')
            ->orderBy('vorname');

        if ($exclude !== null) {
            $query->where('id', '!=', $exclude->id);
        }

        return $query->get();
    }

    public function appSettings(): AppSettings
    {
        $current = IntranetAppBitwardenSettings::current();

        if ($current?->settings instanceof AppSettings) {
            return $current->settings;
        }

        return new AppSettings;
    }

    public function canCreate(User $actor): bool
    {
        if (! $this->appSettings()->onlySupervisorsCanCreateCustomCollections) {
            return true;
        }

        return $this->supervisorScope->isSupervisor($actor);
    }

    /**
     * @param  list<int>  $userIds
     */
    public function create(User $actor, string $name, array $userIds = []): CustomCollection
    {
        $this->assertCanCreate($actor);

        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Name der Sammlung darf nicht leer sein.');
        }

        $memberIds = $this->normalizeMemberUserIds($actor, $userIds);
        $usersById = $this->resolveSelectableUsersById($memberIds);
        $memberMap = $this->confirmedMemberMapByEmail();
        $aclUsers = $this->buildAclUsers($actor, $usersById, $memberMap);

        if ($aclUsers === []) {
            throw new RuntimeException('Kein Vaultwarden-Mitglied für den Ersteller gefunden.');
        }

        $externalId = 'custom-'.Str::uuid()->toString();

        $response = $this->vaultApiService->createCollection([
            'name' => $name,
            'externalId' => $externalId,
            'groups' => [],
            'users' => $aclUsers,
        ]);

        $collectionId = $this->extractCollectionId($response);

        return DB::transaction(function () use ($actor, $name, $externalId, $collectionId, $memberIds): CustomCollection {
            $collection = CustomCollection::query()->create([
                'name' => $name,
                'bitwarden_collection_id' => $collectionId,
                'external_id' => $externalId,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($memberIds as $userId) {
                CustomCollectionMember::query()->create([
                    'custom_collection_id' => $collection->id,
                    'user_id' => $userId,
                ]);
            }

            return $collection->load(['members.user', 'creator']);
        });
    }

    /**
     * @param  list<int>  $userIds
     */
    public function addMembers(User $actor, CustomCollection $collection, array $userIds): CustomCollection
    {
        $this->assertIsCreator($actor, $collection);

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, static fn (int $id): bool => $id > 0));

        if ($userIds === []) {
            return $collection->load(['members.user', 'creator']);
        }

        $existingIds = $collection->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $toAdd = array_values(array_diff($userIds, $existingIds));

        if ($toAdd === []) {
            return $collection->load(['members.user', 'creator']);
        }

        $allMemberIds = array_values(array_unique(array_merge($existingIds, $toAdd)));
        $this->assertAllSelectable($allMemberIds);

        foreach ($toAdd as $userId) {
            CustomCollectionMember::query()->create([
                'custom_collection_id' => $collection->id,
                'user_id' => $userId,
            ]);
        }

        $collection = $collection->fresh(['members.user', 'creator']) ?? $collection;
        $this->syncCollectionAcl($collection);

        return $collection;
    }

    public function removeMember(User $actor, CustomCollection $collection, User $member): CustomCollection
    {
        $this->assertIsCreator($actor, $collection);

        if ((int) $member->id === (int) $collection->created_by_user_id) {
            throw new RuntimeException('Der Ersteller kann nicht aus der Sammlung entfernt werden.');
        }

        CustomCollectionMember::query()
            ->where('custom_collection_id', $collection->id)
            ->where('user_id', $member->id)
            ->delete();

        $collection = $collection->fresh(['members.user', 'creator']) ?? $collection;
        $this->syncCollectionAcl($collection);

        return $collection;
    }

    public function delete(User $actor, CustomCollection $collection): void
    {
        $this->assertIsCreator($actor, $collection);

        try {
            $this->vaultApiService->deleteCollection((string) $collection->bitwarden_collection_id);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Sammlung konnte in Vaultwarden nicht gelöscht werden: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        DB::transaction(function () use ($collection): void {
            CustomCollectionMember::query()
                ->where('custom_collection_id', $collection->id)
                ->delete();

            $collection->delete();
        });
    }

    /**
     * @return Collection<int, CustomCollection>
     */
    public function collectionsVisibleTo(User $user): Collection
    {
        return CustomCollection::query()
            ->where(function ($query) use ($user): void {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('members', function ($members) use ($user): void {
                        $members->where('user_id', $user->id);
                    });
            })
            ->with(['members.user', 'creator'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function membersFor(CustomCollection $collection): Collection
    {
        $collection->loadMissing('members.user');

        return $collection->members
            ->map(static fn (CustomCollectionMember $member): ?User => $member->user)
            ->filter()
            ->sortBy([
                ['nachname', 'asc'],
                ['vorname', 'asc'],
            ])
            ->values();
    }

    protected function syncCollectionAcl(CustomCollection $collection): void
    {
        $collection->loadMissing(['members.user', 'creator']);

        $memberIds = $collection->members->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        if (! in_array((int) $collection->created_by_user_id, $memberIds, true)) {
            $memberIds[] = (int) $collection->created_by_user_id;
        }

        $usersById = User::query()->whereIn('id', $memberIds)->get()->keyBy('id');
        $memberMap = $this->confirmedMemberMapByEmail();
        $creator = $collection->creator ?? User::query()->find($collection->created_by_user_id);

        if ($creator === null) {
            throw new RuntimeException('Ersteller der Sammlung nicht gefunden.');
        }

        $aclUsers = $this->buildAclUsers($creator, $usersById, $memberMap);

        if ($aclUsers === []) {
            throw new RuntimeException('Kein Vaultwarden-Mitglied für die ACL gefunden.');
        }

        $this->vaultApiService->updateCollection((string) $collection->bitwarden_collection_id, [
            'name' => $collection->name,
            'externalId' => $collection->external_id,
            'groups' => [],
            'users' => $aclUsers,
        ]);
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $usersById
     * @param  array<string, string>  $memberMap  email_lower => orgMemberId
     * @return list<array{id: string, readOnly: bool, hidePasswords: bool, manage: bool}>
     */
    protected function buildAclUsers(User $creator, Collection|array $usersById, array $memberMap): array
    {
        $users = $usersById instanceof Collection ? $usersById : collect($usersById);
        $acl = [];
        $seen = [];

        foreach ($users as $user) {
            $email = strtolower(trim((string) ($user->email ?? '')));

            if ($email === '' || ! isset($memberMap[$email]) || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $acl[] = [
                'id' => $memberMap[$email],
                'readOnly' => false,
                'hidePasswords' => false,
                'manage' => (int) $user->id === (int) $creator->id,
            ];
        }

        $creatorEmail = strtolower(trim((string) ($creator->email ?? '')));

        if ($creatorEmail !== '' && isset($memberMap[$creatorEmail]) && ! isset($seen[$creatorEmail])) {
            array_unshift($acl, [
                'id' => $memberMap[$creatorEmail],
                'readOnly' => false,
                'hidePasswords' => false,
                'manage' => true,
            ]);
        }

        return $acl;
    }

    /**
     * @return array<string, string> email_lower => orgMemberId
     */
    protected function confirmedMemberMapByEmail(): array
    {
        $map = [];

        foreach (OrganizationMemberStatus::unwrapMembers($this->apiService->getMembers()) as $member) {
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
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    protected function normalizeMemberUserIds(User $actor, array $userIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if (! in_array((int) $actor->id, $ids, true)) {
            $ids[] = (int) $actor->id;
        }

        $this->assertAllSelectable($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, User>
     */
    protected function resolveSelectableUsersById(array $userIds): Collection
    {
        $selectable = $this->selectableVaultwardenUsers()->keyBy('id');
        $missing = [];

        foreach ($userIds as $userId) {
            if (! $selectable->has($userId)) {
                $missing[] = $userId;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Einige Benutzer sind nicht in Vaultwarden verfügbar: '.implode(', ', $missing),
            );
        }

        return $selectable->only($userIds);
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function assertAllSelectable(array $userIds): void
    {
        $this->resolveSelectableUsersById($userIds);
    }

    protected function assertCanCreate(User $actor): void
    {
        if (! $this->canCreate($actor)) {
            throw new AccessDeniedHttpException('Nur Vorgesetzte können Sammlungen anlegen.');
        }
    }

    protected function assertIsCreator(User $actor, CustomCollection $collection): void
    {
        if (! $collection->isCreatedBy($actor)) {
            throw new AccessDeniedHttpException('Nur der Ersteller darf diese Sammlung verwalten.');
        }
    }

    /**
     * @param  array<string, mixed>  $collectionResponse
     */
    protected function extractCollectionId(array $collectionResponse): string
    {
        $collectionId = $collectionResponse['id'] ?? null;

        if (is_string($collectionId) && $collectionId !== '') {
            return $collectionId;
        }

        if (isset($collectionResponse['data']['id']) && is_string($collectionResponse['data']['id'])) {
            return $collectionResponse['data']['id'];
        }

        throw new RuntimeException('Collection wurde erstellt, aber keine ID erhalten');
    }
}
