<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenMemberEligibility;
use RuntimeException;

class GvpBitwardenProvisioningService
{
    public function __construct(
        protected BitwardenManagementApiInterface $apiService,
        protected BitwardenVaultApiService $vaultApiService,
        protected GvpBitwardenMembershipService $membershipService,
        protected GvpBitwardenCollectionAccessService $collectionAccessService,
    ) {}

    public function createGroup(Gvp $gvp): string
    {
        if ($gvp->hasBitwardenGroup()) {
            throw new RuntimeException('Diese GVP hat bereits eine Bitwarden-Gruppe');
        }

        $members = BitwardenMemberEligibility::filterUsersForGvp(
            $gvp->getAllMembersForBitwarden(),
            $gvp,
        );

        if ($members === []) {
            throw new RuntimeException('Keine Mitglieder für diese GVP gefunden');
        }

        $groupResponse = $this->apiService->createGroup([
            'name' => $gvp->bezeichnung,
            'accessAll' => false,
            'collections' => [],
            'users' => [],
        ]);

        $groupId = $groupResponse['id'] ?? null;

        if (! is_string($groupId) || $groupId === '') {
            throw new RuntimeException('Gruppe wurde erstellt, aber keine ID erhalten');
        }

        $gvp->update(['bitwarden_group_id' => $groupId]);

        $fresh = $gvp->fresh();
        $this->membershipService->syncGroupMembers($fresh);

        $parent = $fresh?->parent;

        if ($parent !== null && $parent->hasBitwardenGesamtCollection()) {
            $this->collectionAccessService->syncGesamtCollectionAccess($parent);
        }

        return $groupId;
    }

    public function createDirectCollection(Gvp $gvp): string
    {
        if ($gvp->hasBitwardenCollection()) {
            throw new RuntimeException('Diese GVP hat bereits eine Bitwarden-Collection');
        }

        if (! $gvp->hasBitwardenGroup()) {
            throw new RuntimeException('Für diese GVP muss zuerst eine Gruppe erstellt werden');
        }

        $payload = $this->collectionAccessService->buildDirectCollectionPayload($gvp);
        $collectionResponse = $this->vaultApiService->createCollection($payload);
        $collectionId = $this->extractCollectionId($collectionResponse);

        $gvp->update(['bitwarden_collection_id' => $collectionId]);

        return $collectionId;
    }

    public function createGesamtCollection(Gvp $gvp): string
    {
        if (! $gvp->needsBitwardenGesamt()) {
            throw new RuntimeException('Gesamt-Collection ist nur für Abteilungen (Kürzel A) mit Untergruppen vorgesehen');
        }

        if ($gvp->hasBitwardenGesamtCollection()) {
            throw new RuntimeException('Diese GVP hat bereits eine Gesamt-Collection');
        }

        $payload = $this->collectionAccessService->buildGesamtCollectionPayload($gvp);
        $collectionResponse = $this->vaultApiService->createCollection($payload);
        $collectionId = $this->extractCollectionId($collectionResponse);

        $gvp->update(['bitwarden_gesamt_collection_id' => $collectionId]);

        return $collectionId;
    }

    /**
     * Richtet eine Abteilung inkl. untergeordneter Gruppen/Fachbereiche vollständig ein:
     * Gruppen, Direct-Collections und Gesamt-Collection (soweit möglich/nötig).
     *
     * @return array{
     *     created_groups: list<string>,
     *     created_collections: list<string>,
     *     created_gesamt: bool,
     *     synced_gesamt: bool,
     *     skipped: list<string>
     * }
     */
    public function setupAbteilung(Gvp $abteilung): array
    {
        if ($abteilung->kuerzel !== 'A') {
            throw new RuntimeException('Setup ist nur für Abteilungen (Kürzel A) verfügbar');
        }

        $abteilung->loadMissing('childGvps');

        $createdGroups = [];
        $createdCollections = [];
        $skipped = [];
        $createdGesamt = false;
        $syncedGesamt = false;

        $units = collect([$abteilung])->concat($abteilung->childGvps);

        foreach ($units as $unit) {
            $unit = $unit->fresh() ?? $unit;

            if (! $unit->hasBitwardenGroup()) {
                $eligibleMembers = BitwardenMemberEligibility::filterUsersForGvp(
                    $unit->getAllMembersForBitwarden(),
                    $unit,
                );

                if ($eligibleMembers === []) {
                    $skipped[] = $unit->bezeichnung.': keine Mitglieder – Gruppe übersprungen';

                    continue;
                }

                $this->createGroup($unit);
                $createdGroups[] = $unit->bezeichnung;
                $unit = $unit->fresh() ?? $unit;
            }

            if ($unit->hasBitwardenGroup() && ! $unit->hasBitwardenCollection()) {
                $this->createDirectCollection($unit);
                $createdCollections[] = $unit->bezeichnung;
            }
        }

        $abteilung = $abteilung->fresh(['childGvps', 'vorgesetzter', 'stellvertreter']) ?? $abteilung;

        if ($abteilung->needsBitwardenGesamt() && ! $abteilung->hasBitwardenGesamtCollection()) {
            $this->createGesamtCollection($abteilung);
            $createdGesamt = true;
        } elseif ($abteilung->hasBitwardenGesamtCollection()) {
            $this->collectionAccessService->syncGesamtCollectionAccess($abteilung);
            $syncedGesamt = true;
        }

        return [
            'created_groups' => $createdGroups,
            'created_collections' => $createdCollections,
            'created_gesamt' => $createdGesamt,
            'synced_gesamt' => $syncedGesamt,
            'skipped' => $skipped,
        ];
    }

    public function deleteDirectCollection(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenCollection()) {
            throw new RuntimeException('Diese GVP hat keine Bitwarden-Collection');
        }

        $this->vaultApiService->deleteCollection((string) $gvp->bitwarden_collection_id);
        $gvp->update(['bitwarden_collection_id' => null]);
    }

    public function deleteGesamtCollection(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenGesamtCollection()) {
            throw new RuntimeException('Diese GVP hat keine Gesamt-Collection');
        }

        $this->vaultApiService->deleteCollection((string) $gvp->bitwarden_gesamt_collection_id);
        $gvp->update(['bitwarden_gesamt_collection_id' => null]);
    }

    public function deleteGroup(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenGroup()) {
            throw new RuntimeException('Diese GVP hat keine Bitwarden-Gruppe');
        }

        if ($gvp->hasBitwardenCollection()) {
            throw new RuntimeException('Bitte zuerst die Collection löschen, bevor die Gruppe gelöscht werden kann');
        }

        $this->apiService->deleteGroup((string) $gvp->bitwarden_group_id);
        $gvp->update(['bitwarden_group_id' => null]);

        if ($gvp->hasBitwardenGesamtCollection()) {
            $this->collectionAccessService->syncGesamtCollectionAccess($gvp->fresh());
        }

        $parent = $gvp->parent;

        if ($parent !== null && $parent->hasBitwardenGesamtCollection()) {
            $this->collectionAccessService->syncGesamtCollectionAccess($parent);
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
