<?php

require_once __DIR__ . '/../controller/DBController.php';

class Trip {

    public $trip_id;
    public $trip_name;
    public $description;
    public $start_date;
    public $end_date;
    public $budget;
    public $base_currency;
    public $created_by;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    public function createTrip() {

        if(!$this->db->openConnection()) {
            return false;
        }

       $query = "
INSERT INTO trip
(trip_name, trip_description, start_date, end_date, budget, base_currency, created_by)
VALUES
(
    '$this->trip_name',
    '$this->description',
    '$this->start_date',
    '$this->end_date',
    '$this->budget',
    '$this->base_currency',
    '$this->created_by'
)
";

        $result = $this->db->insert($query);

        $this->db->closeConnection();

        return $result;
    }
}
?>