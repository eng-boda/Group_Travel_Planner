<?php

require_once __DIR__ . '/../controller/DBController.php';

class RSVP {

    public $activity_id;
    public $user_id;
    public $response;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function saveResponse() {

        if(!$this->db->openConnection()){
            return false;
        }

        $conn = $this->db->connection;

        // check if already exists
        $check = $conn->prepare("
            SELECT rsvp_id 
            FROM rsvp
            WHERE rsvp.activity_id = ? AND user_id = ?
        ");

        $check->bind_param("ii", $this->activity_id, $this->user_id);
        $check->execute();

        $result = $check->get_result();

        // update
        if($result->num_rows > 0){

            $update = $conn->prepare("
                UPDATE rsvp
                SET response = ?
                WHERE rsvp.activity_id = ? AND user_id = ?
            ");

            $update->bind_param(
                "sii",
                $this->response,
                $this->activity_id,
                $this->user_id
            );

            return $update->execute();
        }

        // insert
        $insert = $conn->prepare("
            INSERT INTO rsvp(activity_id, user_id, response)
            VALUES(?,?,?)
        ");

        $insert->bind_param(
            "iis",
            $this->activity_id,
            $this->user_id,
            $this->response
        );

        return $insert->execute();
    }

    public function getActivityResponses($activity_id){

        if(!$this->db->openConnection()){
            return false;
        }

        $conn = $this->db->connection;

        $sql = "
            SELECT 
                users.name,
                rsvp.response
            FROM rsvp
            JOIN users
            ON users.user_id = rsvp.user_id
            WHERE rsvp.activity_id = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $activity_id);

        $stmt->execute();

        return $stmt->get_result();
    }

    public function getCounts($activity_id){

        if(!$this->db->openConnection()){
            return false;
        }

        $conn = $this->db->connection;

        $sql = "
            SELECT 
                response,
                COUNT(*) as total
            FROM rsvp
            WHERE rsvp.activity_id = ?
            GROUP BY response
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $activity_id);

        $stmt->execute();

        return $stmt->get_result();
    }
}