<?php

require_once __DIR__ . '/../controller/DBController.php';

class Poll {
    public $poll_id;
    public $trip_id;
    public $question;
    public $deadline;
    public $create_at;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function createPoll() {
        if (!$this->db->openConnection()) return false;

        $trip_id  = (int) $this->trip_id;
        $question = $this->db->connection->real_escape_string($this->question);
        $deadline = $this->db->connection->real_escape_string($this->deadline);

        $query = "INSERT INTO poll (trip_id, question, deadline, create_at)
                  VALUES ($trip_id, '$question', '$deadline', CURDATE())";

        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result; // returns insert_id
    }

    public function getPollsByTrip($trip_id) {
        if (!$this->db->openConnection()) return [];

        $trip_id = (int) $trip_id;
        $result  = $this->db->select(
            "SELECT * FROM poll WHERE trip_id = $trip_id ORDER BY create_at DESC"
        );
        $this->db->closeConnection();
        return $result ?: [];
    }

    public function deletePoll($poll_id) {
        if (!$this->db->openConnection()) return false;

        $poll_id = (int) $poll_id;
        // Votes & options cascade if FK set; otherwise delete manually via controller
        $this->db->connection->query("DELETE FROM vote        WHERE poll_id = $poll_id");
        $this->db->connection->query("DELETE FROM poll_option WHERE poll_id = $poll_id");
        $this->db->connection->query("DELETE FROM poll        WHERE poll_id = $poll_id");
        $this->db->closeConnection();
        return true;
    }
}