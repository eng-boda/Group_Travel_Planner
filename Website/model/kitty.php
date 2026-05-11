<?php

require_once __DIR__ . '/../controller/DBController.php';

/**
 * Kitty model — Function 16: Shared "Kitty" or "Common Pool" Manager
 * Tracks a group fund where members contribute upfront and the trip leader spends from it.
 *
 * Tables: kitty (kittyId, totalBalance, tripId)
 *         contribution (contributionId, amount, kittyId, userId)
 */
class Kitty {
    public $kittyId;
    public $totalBalance;
    public $trip_id;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /**
     * Get the kitty for a trip. Creates one if it doesn't exist yet.
     */
    public function getOrCreateKitty(int $trip_id): ?array {
        if (!$this->db->openConnection()) return null;
        $trip_id = (int) $trip_id;

        $result = $this->db->select("SELECT * FROM kitty WHERE tripId = $trip_id LIMIT 1");
        if ($result) {
            $this->db->closeConnection();
            return $result[0];
        }

        // Create a new kitty with zero balance
        $this->db->connection->query(
            "INSERT INTO kitty (totalBalance, tripId) VALUES (0.00, $trip_id)"
        );
        $kittyId = $this->db->connection->insert_id;
        $this->db->closeConnection();

        return ['kittyId' => $kittyId, 'totalBalance' => 0.00, 'tripId' => $trip_id];
    }

    /**
     * Add a contribution from a user to the trip kitty.
     * Updates kitty.totalBalance and inserts a contribution row.
     *
     * @return bool|string  true on success, error string on failure
     */
    public function addContribution(int $trip_id, int $userId, float $amount) {
        if ($amount <= 0) return 'Amount must be greater than zero.';
        if (!$this->db->openConnection()) return 'Database connection failed.';

        $trip_id = (int) $trip_id;
        $userId  = (int) $userId;
        $amount  = round($amount, 2);

        // Ensure kitty exists
        $kitty = $this->db->select("SELECT kittyId FROM kitty WHERE tripId = $trip_id LIMIT 1");
        if (!$kitty) {
            $this->db->connection->query(
                "INSERT INTO kitty (totalBalance, tripId) VALUES (0.00, $trip_id)"
            );
            $kittyId = $this->db->connection->insert_id;
        } else {
            $kittyId = (int) $kitty[0]['kittyId'];
        }

        // Insert contribution
        $ins = $this->db->connection->query(
            "INSERT INTO contribution (amount, kittyId, userId) VALUES ($amount, $kittyId, $userId)"
        );
        if (!$ins) {
            $this->db->closeConnection();
            return 'Failed to record contribution.';
        }

        // Update total balance
        $this->db->connection->query(
            "UPDATE kitty SET totalBalance = totalBalance + $amount WHERE kittyId = $kittyId"
        );

        $this->db->closeConnection();
        return true;
    }

    /**
     * Deduct an amount from the kitty (leader spending from pool).
     *
     * @return bool|string
     */
    public function deductFromKitty(int $trip_id, float $amount) {
        if ($amount <= 0) return 'Amount must be greater than zero.';
        if (!$this->db->openConnection()) return 'Database connection failed.';

        $trip_id = (int) $trip_id;
        $amount  = round($amount, 2);

        $kitty = $this->db->select("SELECT kittyId, totalBalance FROM kitty WHERE tripId = $trip_id LIMIT 1");
        if (!$kitty) {
            $this->db->closeConnection();
            return 'No kitty found for this trip.';
        }

        $balance = (float) $kitty[0]['totalBalance'];
        if ($amount > $balance) {
            $this->db->closeConnection();
            return 'Insufficient kitty balance.';
        }

        $kittyId = (int) $kitty[0]['kittyId'];
        $this->db->connection->query(
            "UPDATE kitty SET totalBalance = totalBalance - $amount WHERE kittyId = $kittyId"
        );
        $this->db->closeConnection();
        return true;
    }

    /**
     * Get all contributions for a trip with contributor name.
     */
    public function getContributions(int $trip_id): array {
        if (!$this->db->openConnection()) return [];
        $trip_id = (int) $trip_id;
        $result = $this->db->select("
            SELECT c.contributionId, c.amount, c.userId, u.name AS userName
            FROM contribution c
            JOIN kitty k ON c.kittyId = k.kittyId
            JOIN users u ON c.userId  = u.user_id
            WHERE k.tripId = $trip_id
            ORDER BY c.contributionId DESC
        ");
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Get current kitty balance for a trip.
     */
    public function getBalance(int $trip_id): float {
        if (!$this->db->openConnection()) return 0.0;
        $trip_id = (int) $trip_id;
        $result  = $this->db->select("SELECT totalBalance FROM kitty WHERE tripId = $trip_id LIMIT 1");
        $this->db->closeConnection();
        return $result ? (float) $result[0]['totalBalance'] : 0.0;
    }
}
?>
