<?php

require_once __DIR__ . '/../controller/DBController.php';

class Expense {
    public $expense_id;
    public $trip_id;
    public $category_id;
    public $original_currency;
    public $description;
    public $original_amount;
    public $converted_amount;
    public $uploaded_by;

    private $db;

    public function __construct() {
        $this->db = new DBController();
    }

    // Add a new expense
    public function createExpense() {
        if (!$this->db->openConnection()) return false;

        $query = "INSERT INTO expense 
                  (trip_id, category_id, original_currency, description, original_amount, converted_amount, uploaded_by)
                  VALUES 
                  ('$this->trip_id', '$this->category_id', '$this->original_currency', 
                   '$this->description', '$this->original_amount', '$this->converted_amount', '$this->uploaded_by')";

        $result = $this->db->insert($query);
        $this->db->closeConnection();
        return $result;
    }

    // Get all expenses for a trip
    public function getExpensesByTrip($trip_id) {
        if (!$this->db->openConnection()) return [];
        $query = "SELECT e.*, c.name as category_name 
                  FROM expense e 
                  LEFT JOIN category c ON e.category_id = c.category_id
                  WHERE e.trip_id = $trip_id 
                  ORDER BY e.expense_id DESC";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result : [];
    }

    // Get total spent for a trip (sum of converted_amount)
    public function getTotalSpent($trip_id) {
        if (!$this->db->openConnection()) return 0;
        $query = "SELECT SUM(converted_amount) as total FROM expense WHERE trip_id = $trip_id";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? (float)($result[0]['total'] ?? 0) : 0;
    }

    // Get total spent grouped by category for a trip
    public function getSpentByCategory($trip_id) {
        if (!$this->db->openConnection()) return [];
        $query = "SELECT c.name as category_name, SUM(e.converted_amount) as total
                  FROM expense e
                  LEFT JOIN category c ON e.category_id = c.category_id
                  WHERE e.trip_id = $trip_id
                  GROUP BY e.category_id";
        $result = $this->db->select($query);
        $this->db->closeConnection();
        return $result ? $result : [];
    }
}
?>
