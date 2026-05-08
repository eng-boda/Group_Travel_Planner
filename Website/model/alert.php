<?php

require_once __DIR__ . '/../controller/DBController.php';

class Alert {
    public $alertId;
    public $message;
    public $threshold;
    public $trip_id;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    // Save a new alert threshold for a trip
    public function createAlert($trip_id, $threshold, $message) {
        if (!$this->db->openConnection()) return false;

        // Only one alert per trip — delete old one first
        $this->db->connection->query("DELETE FROM alert WHERE tripId = $trip_id");

        $query = "INSERT INTO alert (message, threshold, tripId) 
                  VALUES ('$message', '$threshold', '$trip_id')";
        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result;
    }

    // Get alert threshold for a trip
    public function getAlertByTrip($trip_id) {
        if (!$this->db->openConnection()) return null;
        $query = "SELECT * FROM alert WHERE tripId = $trip_id LIMIT 1";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result[0] : null;
    }
}
?>