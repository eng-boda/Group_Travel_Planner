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

    public function createNonCash()
    {
        if (!$this->db->openConnection()) {
            return false;
        }

        $query = "INSERT INTO non_cash
        (trip_id, contributor_id, estimatedValue, description, proof_file, leader_comment, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->connection->prepare($query);

        if (!$stmt) {
            $this->db->closeConnection();
            return false;
        }

        $trip_id = (int)$this->trip_id;
        $contributor_id = (int)$this->contributor_id;
        $estimatedValue = (float)$this->estimatedValue;
        $description = (string)$this->description;
        $proof_file = (string)$this->proof_file;
        $leader_comment = (string)$this->leader_comment;
        $status = (string)$this->status;

        $stmt->bind_param(
            "iidssss",
            $trip_id,
            $contributor_id,
            $estimatedValue,
            $description,
            $proof_file,
            $leader_comment,
            $status
        );

        if ($stmt->execute()) {
            $id = $this->db->connection->insert_id;

            $stmt->close();
            $this->db->closeConnection();

            return $id;
        }

        $stmt->close();
        $this->db->closeConnection();

        return false;
    }

    public function getNonCashByTrip($trip_id)
    {
        if (!$this->db->openConnection()) {
            return [];
        }

        $query = "SELECT * FROM non_cash
                  WHERE trip_id = ?
                  ORDER BY non_cash_id DESC";

        $stmt = $this->db->connection->prepare($query);

        if (!$stmt) {
            $this->db->closeConnection();
            return [];
        }

        $tripId = (int)$trip_id;

        $stmt->bind_param("i", $tripId);

        $stmt->execute();

        $result = $stmt->get_result();

        $rows = $result->fetch_all(MYSQLI_ASSOC);

        $stmt->close();
        $this->db->closeConnection();

        return $rows;
    }
}