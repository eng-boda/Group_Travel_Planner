<?php

require_once __DIR__ . '/../model/trip.php';

class TripController {

    private $db;

    public function addTrip($data, $user_id) {

        $trip = new Trip();

        $trip->trip_name = $data['trip_name'];
        $trip->description = $data['description'];
        $trip->start_date = $data['start_date'];
        $trip->end_date = $data['end_date'];
        $trip->budget = $data['budget'];
        $trip->base_currency = $data['base_currency'];
        $trip->created_by = $user_id;

        return $trip->createTrip();
    }

    public function delete($trip_id) {
        $trip = new Trip();
        return $trip->deleteTrip($trip_id);
    }

    public function getTripById($id) {
        $trip = new Trip();
        return $trip->getTripById($id);
    }

    public function update($data, $trip_id) {
        $trip = new Trip();
        $trip->trip_name = $data['trip_name'];
        $trip->description = $data['description'];
        $trip->start_date = $data['start_date'];
        $trip->end_date = $data['end_date'];
        $trip->budget = $data['budget'];
        $trip->base_currency = $data['base_currency'];
        
        return $trip->updateTrip($trip_id);
    }

    public function getAllTrips($user_id) {
        $this->db = new DBController();

        if(!$this->db->openConnection()) {
            return false;
        }

        $query = "SELECT * FROM trip WHERE created_by = $user_id ORDER BY start_date DESC";
        
        $result = $this->db->select($query);
        $this->db->closeConnection();
        
        return $result;
    }
}
?>