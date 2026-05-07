<?php
require_once __DIR__ . '/DBController.php';
class RoleController{
    private $db;
    public function isLeader($user_id, $trip_id) {

    $this->db = new DBController();

    if(!$this->db->openConnection()) {
        return false;
    }

    $query = "
    SELECT * FROM roles
    WHERE user_id = '$user_id'
    AND trip_id = '$trip_id'
    AND role = 'leader'
    ";

    $result = $this->db->select($query);

    $this->db->closeConnection();

    return !empty($result);
}

}
?>