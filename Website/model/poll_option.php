<?php

require_once __DIR__ . '/../controller/DBController.php';

class PollOption {
    public $option_id;
    public $poll_id;
    public $option_text;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function addOption($poll_id, $option_text) {
        if (!$this->db->openConnection()) return false;

        $poll_id     = (int) $poll_id;
        $option_text = $this->db->connection->real_escape_string(trim($option_text));

        $result = $this->db->insert(
            "INSERT INTO poll_option (poll_id, option_text) VALUES ($poll_id, '$option_text')"
        );
        $this->db->closeConnection();
        return $result;
    }

    public function getOptionsByPoll($poll_id) {
        if (!$this->db->openConnection()) return [];

        $poll_id = (int) $poll_id;
        $result  = $this->db->select(
            "SELECT * FROM poll_option WHERE poll_id = $poll_id ORDER BY option_id ASC"
        );
        $this->db->closeConnection();
        return $result ?: [];
    }
}