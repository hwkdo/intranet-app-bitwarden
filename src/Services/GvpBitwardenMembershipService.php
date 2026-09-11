<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenMemberEligibility;
use Illuminate\Support\Facades\Log;

class GvpBitwardenMembershipService
{
    public function __construct(
        protected BitwardenManagementApiInterface $apiService,
        protected ConfirmPendingMembersService $confirmPendingMembers,
    ) {}

    public function syncGroupMembers(Gvp $gvp): void
    {
        if (! $gvp->hasBitwardenGroup()) {
            return;
        }

        $groupId = (string) $gvp->bitwarden_group_id;

        try {
            $members = BitwardenMemberEligibility::filterUsersForGvp(
                $gvp->getAllMembersForBitwarden(),
                $gvp,
            );

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

            $this->confirmPendingMembers->confirmFromMembersList(
                $updatedMembers,
                array_keys($emails),
            );

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
     * @return array<string, string> email_lower => memberId
     */
    protected function extractMemberMap(array $apiResponse): array
    {
        $map = [];

        foreach (OrganizationMemberStatus::unwrapMembers($apiResponse) as $member) {
            $email = trim((string) ($member['email'] ?? ''));
            $id = OrganizationMemberStatus::id($member);

            if ($email === '' || $id === '') {
                continue;
            }

            $map[strtolower($email)] = $id;
        }

        return $map;
    }
}
