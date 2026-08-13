<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Illuminate\Support\Facades\Log;

class GvpBitwardenMembershipService
{
    /**
     * Bitwarden OrganizationUserStatusType.
     */
    private const MEMBER_STATUS_INVITED = 0;

    private const MEMBER_STATUS_ACCEPTED = 1;

    public function __construct(
        protected BitwardenManagementApiInterface $apiService,
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

            $this->confirmPendingMembers($updatedMembers, $emails, $gvp->id, $groupId);

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
     * Bestätigt GVP-Mitglieder über die Vault API, die Confirm brauchen.
     *
     * @param  array<string, string>  $emails  email_lower => original email
     */
    protected function confirmPendingMembers(array $apiResponse, array $emails, int $gvpId, string $groupId): void
    {
        $memberIdsToConfirm = $this->extractMemberIdsNeedingConfirm($apiResponse, $emails);

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
     * Confirm nötig bei:
     * - Status Accepted (1), oder
     * - Status Invited (0) mit gesetzter userId und hasMasterPassword (Vaultwarden-Quirk nach Registrierung).
     *
     * @param  array<string, string>  $emails  email_lower => original email
     * @return list<string>
     */
    protected function extractMemberIdsNeedingConfirm(array $apiResponse, array $emails): array
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

            if (! $this->memberNeedsConfirm($member)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    protected function memberNeedsConfirm(array $member): bool
    {
        $status = (int) ($member['status'] ?? -1);

        if ($status === self::MEMBER_STATUS_ACCEPTED) {
            return true;
        }

        if ($status !== self::MEMBER_STATUS_INVITED) {
            return false;
        }

        $userId = trim((string) ($member['userId'] ?? ''));
        $hasMasterPassword = filter_var($member['hasMasterPassword'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $userId !== '' && $hasMasterPassword;
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
