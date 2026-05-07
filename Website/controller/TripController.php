<?php

require_once __DIR__ . '/../model/trip.php';

class TripController {

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
}
?>