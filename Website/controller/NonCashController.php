<?php

require_once __DIR__ . '/../controller/DBController.php';

class NonCash
{
    public $non_cash_id;
    public $trip_id;
    public $contributor_id;
    public $estimatedValue;
    public $description;
    public $proof_file;
    public $leader_comment;
    public $status;

    private $db;

    public function __construct()
    {
        $this->db = new DBController();
    }

    // ✅ Now uses DBController::insert() – same as Alert
    public function createNonCash()
    {
        if (!$this->db->openConnection()) {
            return false;
        }

        // Escape values safely (DBController::insert() should handle it)
        $trip_id = (int)$this->trip_id;
        $contributor_id = (int)$this->contributor_id;
        $estimatedValue = (float)$this->estimatedValue;
        $description = $this->db->connection->real_escape_string($this->description);
        $proof_file = $this->db->connection->real_escape_string($this->proof_file);
        $leader_comment = $this->db->connection->real_escape_string($this->leader_comment);
        $status = $this->db->connection->real_escape_string($this->status);

        $query = "INSERT INTO non_cash_contribution 
                  (trip_id, contributor_id, estimatedValue, description, proof_file, leader_comment, status) 
                  VALUES ($trip_id, $contributor_id, $estimatedValue, '$description', '$proof_file', '$leader_comment', '$status')";

        $result = $this->db->insert($query);  // ← same pattern as Alert
        $this->db->closeConnection();
        return $result;
    }

    // ✅ Fixed ORDER BY column – assuming primary key is non_cash_contribution_id
    public function getNonCashByTrip($trip_id)
    {
        if (!$this->db->openConnection()) {
            return [];
        }

        $tripId = (int)$trip_id;
        $query = "SELECT * FROM non_cash_contribution 
                  WHERE trip_id = $tripId 
                  ORDER BY non_cash_contribution_id DESC";  // changed from non_cash_id

        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result : [];
    }
}