<?php

require_once __DIR__ . '/../controller/DBController.php';

/**
 * Split model — Function 13: Uneven Split Logic
 * Manages per-user share of an expense (not necessarily equal).
 * Each row: which user owes how much (amount + percentage) for a given expense.
 */
class Split {
    public $splitId;
    public $expenseId;
    public $userId;
    public $shareAmount;
    public $percentage;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /**
     * Save splits for an expense.
     * $splits = [ ['userId'=>int, 'shareAmount'=>float, 'percentage'=>float], ... ]
     * Deletes existing splits first so this is idempotent (safe to call on edit too).
     */
    public function saveSplits(int $expenseId, array $splits): bool {
        if (!$this->db->openConnection()) return false;

        $expenseId = (int) $expenseId;

        // Remove old splits for this expense
        $this->db->connection->query("DELETE FROM split WHERE expenseId = $expenseId");

        $stmt = $this->db->connection->prepare(
            "INSERT INTO split (expenseId, userId, shareAmount, percentage) VALUES (?, ?, ?, ?)"
        );
        if (!$stmt) {
            $this->db->closeConnection();
            return false;
        }

        $ok = true;
        foreach ($splits as $row) {
            $uid = (int) $row['userId'];
            $amt = (float) $row['shareAmount'];
            $pct = (float) ($row['percentage'] ?? 0);
            $stmt->bind_param("iidd", $expenseId, $uid, $amt, $pct);
            if (!$stmt->execute()) $ok = false;
        }

        $stmt->close();
        $this->db->closeConnection();
        return $ok;
    }

    /**
     * Get all splits for a single expense.
     * Returns rows joined with user name for display.
     */
    public function getSplitsByExpense(int $expenseId): array {
        if (!$this->db->openConnection()) return [];
        $expenseId = (int) $expenseId;
        $query = "
            SELECT s.splitId, s.expenseId, s.userId, s.shareAmount, s.percentage,
                   u.name AS userName
            FROM split s
            LEFT JOIN users u ON s.userId = u.user_id
            WHERE s.expenseId = $expenseId
            ORDER BY s.shareAmount DESC
        ";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Get all splits for a trip — used by settlement/debt calculation.
     * Returns each member's total owed across all expenses.
     */
    public function getMemberBalances(int $trip_id): array {
        if (!$this->db->openConnection()) return [];
        $trip_id = (int) $trip_id;
        $query = "
            SELECT s.userId, u.name AS userName, u.email,
                   SUM(s.shareAmount) AS totalOwed
            FROM split s
            JOIN expense e  ON s.expenseId = e.expense_id
            JOIN users u    ON s.userId    = u.user_id
            WHERE e.trip_id = $trip_id
            GROUP BY s.userId
            ORDER BY totalOwed DESC
        ";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Get total splits per user per trip alongside what they paid (uploaded_by).
     * Used to compute net balance: paid - owed.
     */
    public function getNetBalances(int $trip_id): array {
        if (!$this->db->openConnection()) return [];
        $trip_id = (int) $trip_id;

        // What each member paid
        $paid = $this->db->select("
            SELECT uploaded_by AS userId, SUM(converted_amount) AS totalPaid
            FROM expense
            WHERE trip_id = $trip_id
            GROUP BY uploaded_by
        ") ?: [];

        // What each member owes
        $owed = $this->db->select("
            SELECT s.userId, SUM(s.shareAmount) AS totalOwed
            FROM split s
            JOIN expense e ON s.expenseId = e.expense_id
            WHERE e.trip_id = $trip_id
            GROUP BY s.userId
        ") ?: [];

        $this->db->closeConnection();

        // Merge into a single map
        $map = [];
        foreach ($paid as $row) {
            $uid = $row['userId'];
            $map[$uid]['userId']    = $uid;
            $map[$uid]['totalPaid'] = (float) $row['totalPaid'];
            $map[$uid]['totalOwed'] = $map[$uid]['totalOwed'] ?? 0;
        }
        foreach ($owed as $row) {
            $uid = $row['userId'];
            $map[$uid]['userId']    = $uid;
            $map[$uid]['totalOwed'] = (float) $row['totalOwed'];
            $map[$uid]['totalPaid'] = $map[$uid]['totalPaid'] ?? 0;
        }
        foreach ($map as &$row) {
            $row['netBalance'] = $row['totalPaid'] - $row['totalOwed']; // positive = owed money back
        }

        return array_values($map);
    }
}
?>
