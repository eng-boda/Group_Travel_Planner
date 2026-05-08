<?php

require_once __DIR__ . '/../controller/DBController.php';

/**
 * Settlement model — Function 20: Settlement Approval Workflow
 *
 * Logic gate: ALL members must "Sign-Off" on the final balance
 * before the trip is marked as "Settled."
 *
 * Tables:
 *   settlement  (settlementId, tripId, status enum pending/completed/rejected)
 *   approval    (approvalId, settlementId, user_id, status varchar pending/approved/rejected)
 */
class Settlement {
    public $settlementId;
    public $tripId;
    public $status;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /**
     * Get the current settlement for a trip, or null if none exists.
     */
    public function getByTrip(int $trip_id): ?array {
        if (!$this->db->openConnection()) return null;
        $trip_id = (int) $trip_id;
        $result  = $this->db->select(
            "SELECT * FROM settlement WHERE tripId = $trip_id ORDER BY settlementId DESC LIMIT 1"
        );
        $this->db->closeConnection();
        return $result ? $result[0] : null;
    }

    /**
     * Initiate a new settlement for a trip (leader action).
     * Creates a settlement row + one approval row per member (all start as 'pending').
     * Returns the new settlementId, or false on failure.
     */
    public function initiate(int $trip_id, array $memberIds) {
        if (!$this->db->openConnection()) return false;
        $trip_id = (int) $trip_id;

        // Only one active settlement at a time
        $existing = $this->db->select(
            "SELECT settlementId FROM settlement WHERE tripId = $trip_id AND status = 'pending' LIMIT 1"
        );
        if ($existing) {
            $this->db->closeConnection();
            return 'already_exists';
        }

        // Create settlement record
        $this->db->connection->query(
            "INSERT INTO settlement (tripId, status) VALUES ($trip_id, 'pending')"
        );
        $settlementId = $this->db->connection->insert_id;
        if (!$settlementId) {
            $this->db->closeConnection();
            return false;
        }

        // Create one approval row per member
        $stmt = $this->db->connection->prepare(
            "INSERT INTO approval (settlementId, user_id, status) VALUES (?, ?, 'pending')"
        );
        foreach ($memberIds as $uid) {
            $uid = (int) $uid;
            $stmt->bind_param("ii", $settlementId, $uid);
            $stmt->execute();
        }
        $stmt->close();
        $this->db->closeConnection();
        return $settlementId;
    }

    /**
     * A member signs off (approves) their approval row.
     * After every member approves, the settlement auto-completes.
     *
     * @return string  'approved' | 'completed' | 'not_found' | 'already_done' | 'error'
     */
    public function approve(int $settlementId, int $userId): string {
        if (!$this->db->openConnection()) return 'error';

        $settlementId = (int) $settlementId;
        $userId       = (int) $userId;

        // Check approval row exists and is still pending
        $row = $this->db->select(
            "SELECT * FROM approval WHERE settlementId = $settlementId AND user_id = $userId LIMIT 1"
        );
        if (!$row) {
            $this->db->closeConnection();
            return 'not_found';
        }
        if ($row[0]['status'] !== 'pending') {
            $this->db->closeConnection();
            return 'already_done';
        }

        // Mark as approved
        $this->db->connection->query(
            "UPDATE approval SET status = 'approved'
             WHERE settlementId = $settlementId AND user_id = $userId"
        );

        // Check if ALL members have now approved
        $remaining = $this->db->select(
            "SELECT COUNT(*) AS cnt FROM approval
             WHERE settlementId = $settlementId AND status = 'pending'"
        );
        $allDone = ($remaining && (int)$remaining[0]['cnt'] === 0);

        if ($allDone) {
            $this->db->connection->query(
                "UPDATE settlement SET status = 'completed' WHERE settlementId = $settlementId"
            );
            $this->db->closeConnection();
            return 'completed';
        }

        $this->db->closeConnection();
        return 'approved';
    }

    /**
     * A member rejects the settlement.
     * This marks the settlement as 'rejected' immediately — leader must restart.
     */
    public function reject(int $settlementId, int $userId): bool {
        if (!$this->db->openConnection()) return false;
        $settlementId = (int) $settlementId;
        $userId       = (int) $userId;

        $this->db->connection->query(
            "UPDATE approval SET status = 'rejected'
             WHERE settlementId = $settlementId AND user_id = $userId"
        );
        $this->db->connection->query(
            "UPDATE settlement SET status = 'rejected' WHERE settlementId = $settlementId"
        );
        $this->db->closeConnection();
        return true;
    }

    /**
     * Get all approval rows for a settlement with user names.
     */
    public function getApprovals(int $settlementId): array {
        if (!$this->db->openConnection()) return [];
        $settlementId = (int) $settlementId;
        $result = $this->db->select("
            SELECT a.approvalId, a.user_id, a.status, u.name AS userName, u.email
            FROM approval a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.settlementId = $settlementId
            ORDER BY a.status ASC, u.name ASC
        ");
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Cancel / reset a pending settlement (leader only).
     */
    public function cancel(int $settlementId): bool {
        if (!$this->db->openConnection()) return false;
        $settlementId = (int) $settlementId;
        $this->db->connection->query(
            "DELETE FROM approval WHERE settlementId = $settlementId"
        );
        $this->db->connection->query(
            "DELETE FROM settlement WHERE settlementId = $settlementId"
        );
        $this->db->closeConnection();
        return true;
    }
}
?>
