<?php
require_once __DIR__ . '/../controller/DBController.php';

class rsvp {

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function createRSVP($activity_id, $user_id, $response) {

        if ($this->db->openConnection()) {

            $query = "INSERT INTO rsvp (activity_id, user_id, response) 
                  VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE response = ?";
        
                 $stmt = $this->db->connection->prepare($query);
        
                $stmt->bind_param("iiss", $activity_id, $user_id, $response, $response);

            $result = $stmt->execute();

            $this->db->closeConnection();

            return $result;
        }

        return false;
    }
    
public function getActivityAttendanceDetails($activity_id) {
    $data = [
        "counts" => ["yes" => 0, "maybe" => 0, "no" => 0],
        "names" => ["yes" => [], "maybe" => [], "no" => []]
    ];

    if ($this->db->openConnection()) {
        // Query يجيب العدد والأسماء في نفس الوقت عن طريق عمل JOIN مع جدول الـ users
        $query = "SELECT r.response, u.name 
                  FROM rsvp r 
                  JOIN users u ON r.user_id = u.user_id 
                  WHERE r.activity_id = ?";
        
        $stmt = $this->db->connection->prepare($query);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $resp = $row['response']; // yes, maybe, or no
            if (isset($data['names'][$resp])) {
                $data['names'][$resp][] = $row['name'];
                $data['counts'][$resp]++;
            }
        }
        $this->db->closeConnection();
    }
    return $data;
}
public function getMyResponse($activity_id, $user_id) {
    $response = null;
    if ($this->db->openConnection()) {
        $stmt = $this->db->connection->prepare(
            "SELECT response FROM rsvp
             WHERE activity_id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->bind_param("ii", $activity_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) $response = $row['response'];
        $this->db->closeConnection();
    }
    return $response;
}
}
?>
