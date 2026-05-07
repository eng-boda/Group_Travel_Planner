<?php

require_once __DIR__ . '/../controller/DBController.php';

class Member {

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /**
     * Get all members of a trip with their roles
     */
    public function getTripMembers($trip_id) {
        if (!$this->db->openConnection()) return [];

        $trip_id = (int) $trip_id;
        $query = "
            SELECT u.user_id, u.name, u.email, r.role
            FROM roles r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.trip_id = $trip_id
            ORDER BY FIELD(r.role, 'leader', 'member'), u.name ASC
        ";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Get a single member's role in a trip
     */
    public function getMemberRole($user_id, $trip_id) {
        if (!$this->db->openConnection()) return null;

        $user_id = (int) $user_id;
        $trip_id = (int) $trip_id;
        $query = "SELECT role FROM roles WHERE user_id = $user_id AND trip_id = $trip_id LIMIT 1";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result[0]['role'] : null;
    }

    /**
     * Check if user is leader/organizer of a trip
     */
    public function isOrganizer($user_id, $trip_id) {
        return $this->getMemberRole($user_id, $trip_id) === 'leader';
    }

    /**
     * Promote member to leader
     */
    public function promoteToLeader($target_user_id, $trip_id) {
        if (!$this->db->openConnection()) return false;

        $target_user_id = (int) $target_user_id;
        $trip_id = (int) $trip_id;
        $query = "UPDATE roles SET role = 'leader' WHERE user_id = $target_user_id AND trip_id = $trip_id";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false;
    }

    /**
     * Demote leader to member
     */
    public function demoteToMember($target_user_id, $trip_id) {
        if (!$this->db->openConnection()) return false;

        $target_user_id = (int) $target_user_id;
        $trip_id = (int) $trip_id;
        $query = "UPDATE roles SET role = 'member' WHERE user_id = $target_user_id AND trip_id = $trip_id";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false;
    }

    /**
     * Remove member from trip
     */
    public function removeMember($target_user_id, $trip_id) {
        if (!$this->db->openConnection()) return false;

        $target_user_id = (int) $target_user_id;
        $trip_id = (int) $trip_id;
        $query = "DELETE FROM roles WHERE user_id = $target_user_id AND trip_id = $trip_id";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false;
    }

    /**
     * Add user to trip as member directly (when email exists)
     */
    public function addMemberByUserId($user_id, $trip_id) {
        if (!$this->db->openConnection()) return false;

        $user_id  = (int) $user_id;
        $trip_id  = (int) $trip_id;

        // Check if already a member
        $check = $this->db->select("SELECT role FROM roles WHERE user_id = $user_id AND trip_id = $trip_id LIMIT 1");
        if ($check) {
            $this->db->closeConnection();
            return 'already_member';
        }

        $query = "INSERT INTO roles (user_id, trip_id, role, assigned_at) VALUES ($user_id, $trip_id, 'member', NOW())";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false ? true : false;
    }

    /**
     * Find user by email
     */
    public function getUserByEmail($email) {
        if (!$this->db->openConnection()) return null;

        $email = $this->db->connection->real_escape_string(strtolower(trim($email)));
        $result = $this->db->select("SELECT user_id, name, email FROM users WHERE email = '$email' LIMIT 1");
        $this->db->closeConnection();
        return $result ? $result[0] : null;
    }

    /**
     * Get pending invites for a trip
     */
    public function getPendingInvites($trip_id) {
        if (!$this->db->openConnection()) return [];

        $trip_id = (int) $trip_id;
        $query = "SELECT * FROM invites WHERE trip_id = $trip_id AND status = 'pending' ORDER BY sent_at DESC";

        // Check if invites table exists
        $tableCheck = $this->db->select("SHOW TABLES LIKE 'invites'");
        if (!$tableCheck) {
            $this->db->closeConnection();
            return [];
        }

        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Create a pending invite for non-existing email
     */
    public function createPendingInvite($email, $trip_id, $invited_by) {
        if (!$this->db->openConnection()) return false;

        // Check if invites table exists, create if not
        $tableCheck = $this->db->select("SHOW TABLES LIKE 'invites'");
        if (!$tableCheck) {
            $create = "CREATE TABLE `invites` (
                `invite_id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL,
                `trip_id` int(11) NOT NULL,
                `invited_by` int(11) NOT NULL,
                `status` enum('pending','accepted','cancelled') DEFAULT 'pending',
                `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`invite_id`),
                KEY `fk_invite_trip` (`trip_id`),
                CONSTRAINT `fk_invite_trip` FOREIGN KEY (`trip_id`) REFERENCES `trip` (`trip_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $this->db->connection->query($create);
        }

        $email      = $this->db->connection->real_escape_string(strtolower(trim($email)));
        $trip_id    = (int) $trip_id;
        $invited_by = (int) $invited_by;

        // Check if pending invite already exists
        $existing = $this->db->select("SELECT invite_id FROM invites WHERE email = '$email' AND trip_id = $trip_id AND status = 'pending' LIMIT 1");
        if ($existing) {
            $this->db->closeConnection();
            return 'already_invited';
        }

        $query = "INSERT INTO invites (email, trip_id, invited_by, status, sent_at) VALUES ('$email', $trip_id, $invited_by, 'pending', NOW())";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false ? true : false;
    }

    /**
     * Cancel a pending invite
     */
    public function cancelInvite($invite_id, $trip_id) {
        if (!$this->db->openConnection()) return false;

        $invite_id = (int) $invite_id;
        $trip_id   = (int) $trip_id;
        $query = "UPDATE invites SET status = 'cancelled' WHERE invite_id = $invite_id AND trip_id = $trip_id";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result !== false;
    }
}
?>