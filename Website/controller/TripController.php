<?php

require_once __DIR__ . '/../model/trip.php';
require_once __DIR__ . '/DBController.php';
require_once __DIR__ . '/RoleController.php';

class TripController {

    private $db;

    // إضافة رحلة جديدة
    public function addTrip($data, $user_id) {
        $trip = new Trip();
        $trip->trip_name = $data['trip_name'];
        $trip->description = $data['description'];
        $trip->start_date = $data['start_date'];
        $trip->end_date = $data['end_date'];
        $trip->budget = $data['budget'];
        $trip->base_currency = $data['base_currency'];
        $trip->created_by = $user_id;

        $trip_id = $trip->createTrip(); 

        if ($trip_id && is_numeric($trip_id)) {
            $this->db = new DBController();
            if($this->db->openConnection()) {
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

    // الفنكشن اللي كانت ناقصة ومسببة الـ Error
    public function getTripById($id) {
        $trip = new Trip();
        return $trip->getTripById($id);
    }

    // تحديث الرحلة
    public function update($data, $trip_id, $user_id) {
        $roleController = new RoleController();

        // حماية: التأكد إن اللي بيعدل هو القائد
        if(!$roleController->isLeader($user_id, $trip_id)) {
            return false;
        }

        $trip = new Trip();
        $trip->trip_name = $data['trip_name'];
        $trip->description = $data['description'];
        $trip->start_date = $data['start_date'];
        $trip->end_date = $data['end_date'];
        $trip->budget = $data['budget'];
        $trip->base_currency = $data['base_currency'];
        
        return $trip->updateTrip($trip_id);
    }

    // حذف الرحلة
    public function delete($trip_id, $user_id) {
        $roleController = new RoleController();

        if(!$roleController->isLeader($user_id, $trip_id)) {
            return false;
        }

        $trip = new Trip();
        return $trip->deleteTrip($trip_id);
    }

    // جلب كل الرحلات للمستخدم
    public function getAllTrips($user_id) {
        $this->db = new DBController();
        if(!$this->db->openConnection()) return false;

        $query = "SELECT * FROM trip WHERE created_by = $user_id ORDER BY start_date DESC";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        
        return $result;
    }
}