<?php

require_once __DIR__ . '/../controller/DBController.php';

class Activity {

    public $activity_id;
    public $trip_id;

    public $title;
    public $activity_location;
    public $notes;

    public $type;
    public $activity_state;

    public $activity_date;
    public $activity_time;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function createActivity() {

    if(!$this->db->openConnection()) {
        return false;
    }

    $query = "
    INSERT INTO activity
    (
        trip_id,
        title,
        activity_location,
        type,
        activity_state,
        activity_date
    )
    VALUES
    (
        '$this->trip_id',
        '$this->title',
        '$this->activity_location',
        '$this->type',
        '$this->activity_state',
        '$this->activity_date'
    )
    ";

    $result = $this->db->insert($query);

    $this->db->closeConnection();

    return $result;
}
}
?>