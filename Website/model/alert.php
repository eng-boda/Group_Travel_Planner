<?php

require_once __DIR__ . '/../controller/DBController.php';

/**
 * Alert model — Budget Threshold Alerts
 * Notifies the group when total spend exceeds a pre-set budget %.
 */
class Alert {
    public $alertId;
    public $message;
    public $threshold;
    public $trip_id;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /**
     * Ensure the last_email_sent column exists (auto-migration for XAMPP).
     */
    private function ensureColumn() {
        $check = $this->db->select(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'alert'
               AND COLUMN_NAME  = 'last_email_sent'"
        );
        if (!$check) {
            $this->db->connection->query(
                "ALTER TABLE `alert` ADD COLUMN `last_email_sent` datetime DEFAULT NULL"
            );
        }
    }

    /**
     * Save/update alert threshold for a trip (one per trip).
     */
    public function createAlert($trip_id, $threshold, $message) {
        if (!$this->db->openConnection()) return false;

        $this->ensureColumn();

        $trip_id   = (int) $trip_id;
        $threshold = (float) $threshold;
        $message   = $this->db->connection->real_escape_string($message);

        $this->db->connection->query("DELETE FROM alert WHERE tripId = $trip_id");
        $query = "INSERT INTO alert (message, threshold, tripId, last_email_sent)
                  VALUES ('$message', $threshold, $trip_id, NULL)";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result;
    }

    /**
     * Get the alert record for a trip.
     */
    public function getAlertByTrip($trip_id) {
        if (!$this->db->openConnection()) return null;
        $this->ensureColumn();
        $trip_id = (int) $trip_id;
        $result  = $this->db->select("SELECT * FROM alert WHERE tripId = $trip_id LIMIT 1");
        $this->db->closeConnection();
        return $result ? $result[0] : null;
    }

    /**
     * Mark that we just sent the budget alert email.
     */
    public function markEmailSent($trip_id) {
        if (!$this->db->openConnection()) return false;
        $this->ensureColumn();
        $trip_id = (int) $trip_id;
        $this->db->connection->query(
            "UPDATE alert SET last_email_sent = NOW() WHERE tripId = $trip_id"
        );
        $this->db->closeConnection();
    }

    /**
     * Returns true if we should send the alert email now (6-hr cooldown).
     */
    public function shouldSendEmail(?string $lastSent, int $cooldownHours = 6): bool {
        if (!$lastSent) return true;
        return (time() - strtotime($lastSent)) > ($cooldownHours * 3600);
    }
}
?>
