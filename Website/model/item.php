<?php 

require_once __DIR__ . '/../controller/DBController.php';

class item {

    public $item_id;
    public $trip_id;
    public $user_id; 
    public $itemName;
    public $status = "Pending";
    public $completed_by;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function addItem() {
        if(!$this->db->openConnection()) {
            return false;
        }

        $query = "INSERT INTO item
                  (trip_id , user_id , itemName , status)
                  VALUES
                  ('$this->trip_id' ,
                   '$this->user_id' ,
                   '$this->itemName' ,
                   '$this->status')";

        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result;
    }

    public function getItems($trip_id) {
        if(!$this->db->openConnection()) {
            return false;
        }

    
        $query = "SELECT item.*, 
                  u1.name as creator_name, 
                  u2.name as completer_name 
                  FROM item 
                  JOIN users u1 ON item.user_id = u1.user_id 
                  LEFT JOIN users u2 ON item.completed_by = u2.user_id 
                  WHERE item.trip_id = '$trip_id' 
                  ORDER BY item.item_id DESC";

        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result;
    }

    
    public function toggleStatus($item_id, $current_status, $user_id) {
    if(!$this->db->openConnection()) {
        return false;
    }

   
    $status = trim($current_status);

   
    if (strcasecmp($status, 'Done') == 0) {
    
        $query = "UPDATE item 
                  SET status = 'Pending', completed_by = NULL 
                  WHERE item_id = '$item_id'";
    } else {
        
        $query = "UPDATE item 
                  SET status = 'Done', completed_by = '$user_id' 
                  WHERE item_id = '$item_id'";
    }

    $result = $this->db->connection->query($query);
    $this->db->closeConnection();
    return $result;
}
public function deleteItem($item_id) {
    if(!$this->db->openConnection()) {
        return false;
    }

    $query = "DELETE FROM item WHERE item_id = '$item_id'";

    $result = $this->db->connection->query($query);
    $this->db->closeConnection();
    return $result;
}
}
?>