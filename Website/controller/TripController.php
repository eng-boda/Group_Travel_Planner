<?php

require_once __DIR__ . '/../model/trip.php';
require_once __DIR__ . '/DBController.php';
require_once __DIR__ . '/RoleController.php';

class TripController {

    private $db;

    public function addTrip($data, $user_id) {
        $trip = new Trip();
        $trip->trip_name    = $data['trip_name'];
        $trip->description  = $data['description'];
        $trip->start_date   = $data['start_date'];
        $trip->end_date     = $data['end_date'];
        $trip->budget       = $data['budget'];
        $trip->base_currency = $data['base_currency'];
        $trip->created_by   = $user_id;

        $trip_id = $trip->createTrip();

        if ($trip_id && is_numeric($trip_id)) {
            $this->db = new DBController();
            if ($this->db->openConnection()) {
                $query = "INSERT INTO roles (user_id, trip_id, role, assigned_at)
                          VALUES ($user_id, $trip_id, 'leader', NOW())
                          ON DUPLICATE KEY UPDATE role = 'leader'";
                $this->db->insert($query);
                $this->db->closeConnection();
            }
            return $trip_id;
        }
        return false;
    }

    public function getTripById($id) {
        $trip = new Trip();
        return $trip->getTripById($id);
    }

    public function update($data, $trip_id, $user_id) {
        $roleController = new RoleController();
        if (!$roleController->isLeader($user_id, $trip_id)) {
            die("Unauthorized Action: You are not the leader.");
        }

        $trip = new Trip();
        $trip->trip_name    = $data['trip_name'];
        $trip->description  = $data['description'];
        $trip->start_date   = $data['start_date'];
        $trip->end_date     = $data['end_date'];
        $trip->budget       = $data['budget'];
        $trip->base_currency = $data['base_currency'];

        return $trip->updateTrip($trip_id);
    }

    public function delete($trip_id, $user_id) {
        $roleController = new RoleController();
        if (!$roleController->isLeader($user_id, $trip_id)) {
            die("Access Denied: You are not the leader.");
        }

        $trip = new Trip();
        return $trip->deleteTrip($trip_id);
    }

    /**
     * Get all trips the user is associated with:
     * - trips they created (created_by)
     * - trips they were invited to (via roles table)
     */
    public function getAllTrips($user_id) {
        $this->db = new DBController();
        if (!$this->db->openConnection()) return false;

        $user_id = (int) $user_id;

        $query = "
            SELECT DISTINCT t.*
            FROM trip t
            LEFT JOIN roles r ON r.trip_id = t.trip_id AND r.user_id = $user_id
            WHERE t.created_by = $user_id
               OR r.user_id = $user_id
            ORDER BY t.start_date DESC
        ";

        $result = $this->db->select($query);
        $this->db->closeConnection();

        return $result;
    }
}