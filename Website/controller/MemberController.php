<?php

require_once __DIR__ . '/../model/member.php';

class MemberController {

    private $memberModel;

    public function __construct() {
        $this->memberModel = new Member();
    }

    public function getTripMembers($trip_id) {
        return $this->memberModel->getTripMembers($trip_id);
    }

    public function getPendingInvites($trip_id) {
        return $this->memberModel->getPendingInvites($trip_id);
    }

    public function isOrganizer($user_id, $trip_id) {
        return $this->memberModel->isOrganizer($user_id, $trip_id);
    }

    /**
     * Promote a member to leader — only organizer can do this
     */
    public function promote($actor_id, $target_user_id, $trip_id) {
        if (!$this->memberModel->isOrganizer($actor_id, $trip_id)) {
            return ['success' => false, 'message' => 'Only organizers can promote members.'];
        }
        $result = $this->memberModel->promoteToLeader($target_user_id, $trip_id);
        return $result
            ? ['success' => true,  'message' => 'Member promoted to organizer.']
            : ['success' => false, 'message' => 'Promotion failed.'];
    }

    /**
     * Demote a leader to member — only organizer can do this; cannot self-demote
     */
    public function demote($actor_id, $target_user_id, $trip_id) {
        if (!$this->memberModel->isOrganizer($actor_id, $trip_id)) {
            return ['success' => false, 'message' => 'Only organizers can demote members.'];
        }
        if ((int)$actor_id === (int)$target_user_id) {
            return ['success' => false, 'message' => 'You cannot demote yourself.'];
        }
        $result = $this->memberModel->demoteToMember($target_user_id, $trip_id);
        return $result
            ? ['success' => true,  'message' => 'Member demoted.']
            : ['success' => false, 'message' => 'Demotion failed.'];
    }

    /**
     * Remove a member — only organizer can do this
     */
    public function remove($actor_id, $target_user_id, $trip_id) {
        if (!$this->memberModel->isOrganizer($actor_id, $trip_id)) {
            return ['success' => false, 'message' => 'Only organizers can remove members.'];
        }
        if ((int)$actor_id === (int)$target_user_id) {
            return ['success' => false, 'message' => 'You cannot remove yourself.'];
        }
        $result = $this->memberModel->removeMember($target_user_id, $trip_id);
        return $result
            ? ['success' => true,  'message' => 'Member removed.']
            : ['success' => false, 'message' => 'Removal failed.'];
    }

    /**
     * Invite by email — only organizer can invite
     * If email exists in users → add directly as member
     * If email does not exist → create pending invite
     */
    public function invite($actor_id, $email, $trip_id) {
        if (!$this->memberModel->isOrganizer($actor_id, $trip_id)) {
            return ['success' => false, 'message' => 'Only organizers can invite members.'];
        }

        if (empty(trim($email)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        $existingUser = $this->memberModel->getUserByEmail($email);

        if ($existingUser) {
            // User exists — add directly
            if ((int)$existingUser['user_id'] === (int)$actor_id) {
                return ['success' => false, 'message' => 'You are already in this trip.'];
            }
            $result = $this->memberModel->addMemberByUserId($existingUser['user_id'], $trip_id);
            if ($result === 'already_member') {
                return ['success' => false, 'message' => 'This user is already a member of this trip.'];
            }
            return $result
                ? ['success' => true,  'message' => htmlspecialchars($existingUser['name']) . ' has been added to the trip.']
                : ['success' => false, 'message' => 'Failed to add member.'];
        } else {
            // User does not exist — create pending invite
            $result = $this->memberModel->createPendingInvite($email, $trip_id, $actor_id);
            if ($result === 'already_invited') {
                return ['success' => false, 'message' => 'An invite has already been sent to this email.'];
            }
            return $result
                ? ['success' => true,  'message' => 'Invitation sent to ' . htmlspecialchars($email) . '.']
                : ['success' => false, 'message' => 'Failed to send invitation.'];
        }
    }

    /**
     * Cancel a pending invite — only organizer can cancel
     */
    public function cancelInvite($actor_id, $invite_id, $trip_id) {
        if (!$this->memberModel->isOrganizer($actor_id, $trip_id)) {
            return ['success' => false, 'message' => 'Only organizers can cancel invitations.'];
        }
        $result = $this->memberModel->cancelInvite($invite_id, $trip_id);
        return $result
            ? ['success' => true,  'message' => 'Invitation cancelled.']
            : ['success' => false, 'message' => 'Failed to cancel invitation.'];
    }
}
?>