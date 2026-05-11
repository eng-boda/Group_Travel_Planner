<?php

require_once __DIR__ . '/../controller/DBController.php';

class Vote {
    public $vote_id;
    public $poll_id;
    public $option_id;
    public $user_id;
    public $weight;
    public $anonymous_token;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    /** Insert new vote. Returns true, false, or 'already_voted'. */
    public function castVote($poll_id, $option_id, $user_id, $weight = 1) {
        if (!$this->db->openConnection()) return false;

        $poll_id   = (int) $poll_id;
        $option_id = (int) $option_id;
        $user_id   = (int) $user_id;
        $weight    = (int) $weight;

        $existing = $this->db->select(
            "SELECT vote_id FROM vote WHERE poll_id = $poll_id AND user_id = $user_id LIMIT 1"
        );
        if ($existing) {
            $this->db->closeConnection();
            return 'already_voted';
        }

        $result = $this->db->insert(
            "INSERT INTO vote (poll_id, option_id, user_id, weight)
             VALUES ($poll_id, $option_id, $user_id, $weight)"
        );
        $this->db->closeConnection();
        return $result ? true : false;
    }

    /** Update an existing vote to a new option (weight stays unchanged). */
    public function changeVote($poll_id, $new_option_id, $user_id) {
        if (!$this->db->openConnection()) return false;

        $poll_id       = (int) $poll_id;
        $new_option_id = (int) $new_option_id;
        $user_id       = (int) $user_id;

        $this->db->connection->query(
            "UPDATE vote SET option_id = $new_option_id
             WHERE poll_id = $poll_id AND user_id = $user_id LIMIT 1"
        );
        $ok = ($this->db->connection->affected_rows >= 0);
        $this->db->closeConnection();
        return $ok;
    }

    /** Returns the option_id the user voted for in this poll, or null. */
    public function getUserVote($poll_id, $user_id) {
        if (!$this->db->openConnection()) return null;

        $poll_id = (int) $poll_id;
        $user_id = (int) $user_id;
        $result  = $this->db->select(
            "SELECT option_id FROM vote WHERE poll_id = $poll_id AND user_id = $user_id LIMIT 1"
        );
        $this->db->closeConnection();
        return $result ? (int) $result[0]['option_id'] : null;
    }

    /**
     * Returns per-option stats for a poll:
     *   vote_count      — number of people who voted for this option
     *   total_weight    — weighted sum (organizer = 2, member = 1); drives % bar
     *   organizer_count — how many of those voters are organizers
     *
     * Sorted by total_weight DESC so the winning option comes first.
     */
    public function getResults($poll_id) {
        if (!$this->db->openConnection()) return [];

        $poll_id = (int) $poll_id;
        $result  = $this->db->select("
            SELECT
                po.option_id,
                po.option_text,
                COUNT(v.vote_id)                                AS vote_count,
                COALESCE(SUM(v.weight), 0)                      AS total_weight,
                SUM(CASE WHEN v.weight >= 2 THEN 1 ELSE 0 END) AS organizer_count
            FROM poll_option po
            LEFT JOIN vote v ON v.option_id = po.option_id AND v.poll_id = $poll_id
            WHERE po.poll_id = $poll_id
            GROUP BY po.option_id, po.option_text
            ORDER BY total_weight DESC
        ");
        $this->db->closeConnection();
        return $result ?: [];
    }

    /**
     * Returns organizer names who voted for a specific option (weight >= 2).
     */
    public function getOrganizerVoters($poll_id, $option_id) {
        if (!$this->db->openConnection()) return [];

        $poll_id   = (int) $poll_id;
        $option_id = (int) $option_id;
        $result    = $this->db->select("
            SELECT u.name
            FROM vote v
            JOIN users u ON v.user_id = u.user_id
            WHERE v.poll_id   = $poll_id
              AND v.option_id = $option_id
              AND v.weight    >= 2
            ORDER BY u.name ASC
        ");
        $this->db->closeConnection();
        return $result ? array_column($result, 'name') : [];
    }
}