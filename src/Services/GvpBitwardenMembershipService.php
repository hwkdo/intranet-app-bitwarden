<?php

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Illuminate\Support\Facades\Log;

class GvpBitwardenMembershipService
{
    /**
     * Bitwarden OrganizationUserStatusType: Accepted (Einladung angenommen, noch nicht bestätigt).
     */
    private const MEMBER_STATUS_ACCEPTED = 1;

    public function __construct(
        protected BitwardenPublicApiService $apiService,
        protected BitwardenVaultApiService $vaultApiService,
    ) {}

    public function syncGroupMembers(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenGroup()) {
            return;
        }

        $groupId = (string) $gvp->bitwarden_group_id;

        try {
            $members = $gvp->getAllMembersForBitwarden();

            $emails = [];

            foreach ($members as $member) {
                $email = trim((string) ($member->email ?? ''));

                if ($email === '') {
                    continue;
                }

                $emails[strtolower($email)] = $email;
            }

            if ($emails === []) {
                $this->apiService->updateGroupUsers($groupId, []);

                return;
            }

            $currentMembers = $this->apiService->getMembers();
            $existingMembersByEmail = $this->extractMemberMap($currentMembers);

            $emailsToInvite = [];

            foreach ($emails as $lowerEmail => $originalEmail) {
                if (! isset($existingMembersByEmail[$lowerEmail])) {
                    $emailsToInvite[] = $originalEmail;
                }
            }

            if ($emailsToInvite !== []) {
                try {
                    $this->apiService->inviteMembers([
                        'emails' => array_values($emailsToInvite),
                        'type' => '2',
                        'accessAll' => false,
                        'collections' => [],
                        'groups' => [],
                    ]);
                } catch (\Throwable $exception) {
                    Log::error('GvpBitwardenMembershipService: Fehler beim Einladen von Mitgliedern', [
                        'gvp_id' => $gvp->id,
                        'group_id' => $groupId,
                        'emails' => $emailsToInvite,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $updatedMembers = $this->apiService->getMembers();
            $allMembersByEmail = $this->extractMemberMap($updatedMembers);

            $this->confirmAcceptedMembers($updatedMembers, $emails, $gvp->id, $groupId);

            $userIds = [];

            foreach ($emails as $lowerEmail => $originalEmail) {
                if (isset($allMembersByEmail[$lowerEmail])) {
                    $userIds[] = $allMembersByEmail[$lowerEmail];
                }
            }

            $this->apiService->updateGroupUsers($groupId, $userIds);
        } catch (\Throwable $exception) {
            Log::error('GvpBitwardenMembershipService: Fehler beim Synchronisieren der Gruppe', [
                'gvp_id' => $gvp->id,
                'group_id' => $groupId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Bestätigt alle GVP-Mitglieder mit Status Accepted über die Vault API.
     *
     * @param  array<string, string>  $emails  email_lower => original email
     */
    protected function confirmAcceptedMembers(array $apiResponse, array $emails, int $gvpId, string $groupId): void
    {
        $memberIdsToConfirm = $this->extractAcceptedMemberIds($apiResponse, $emails);

        if ($memberIdsToConfirm === []) {
            return;
        }

        try {
            $this->vaultApiService->ensureUnlocked();
        } catch (\Throwable $exception) {
            Log::error('GvpBitwardenMembershipService: Vault konnte nicht entsperrt werden', [
                'gvp_id' => $gvpId,
                'group_id' => $groupId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($memberIdsToConfirm as $memberId) {
            try {
                $this->vaultApiService->confirmMember($memberId);
            } catch (\Throwable $exception) {
                Log::error('GvpBitwardenMembershipService: Fehler beim Bestätigen eines Mitglieds', [
                    'gvp_id' => $gvpId,
                    'group_id' => $groupId,
                    'member_id' => $memberId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, string>  $emails  email_lower => original email
     * @return list<string>
     */
    protected function extractAcceptedMemberIds(array $apiResponse, array $emails): array
    {
        $members = $apiResponse;

        if (isset($members['data']) && is_array($members['data'])) {
            $members = $members['data'];
        }

        if (! is_array($members)) {
            return [];
        }

        $ids = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            if (! isset($member['email'], $member['id'])) {
                continue;
            }

            $email = strtolower(trim((string) $member['email']));
            $id = (string) $member['id'];

            if ($email === '' || $id === '' || ! isset($emails[$email])) {
                continue;
            }

            if ((int) ($member['status'] ?? -1) !== self::MEMBER_STATUS_ACCEPTED) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @return array<string, string> email_lower => memberId
     */
    protected function extractMemberMap(array $apiResponse): array
    {
        $members = $apiResponse;

        if (isset($members['data']) && is_array($members['data'])) {
            $members = $members['data'];
        }

        if (! is_array($members)) {
            return [];
        }

        $map = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            if (! isset($member['email'], $member['id'])) {
                continue;
            }

            $email = trim((string) $member['email']);
            $id = (string) $member['id'];

            if ($email === '' || $id === '') {
                continue;
            }

            $map[strtolower($email)] = $id;
        }

        return $map;
    }
}
