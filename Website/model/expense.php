<?php

require_once __DIR__ . '/../controller/DBController.php';

class expense
{
    public $expense_id;
    public $trip_id;
    public $category_id;
    public $original_currency;
    public $description;
    public $original_amount;
    public $converted_amount;
    public $uploaded_by;

    private $db;

    public function __construct()
    {
        $this->db = new DBController();
    }

    public function createExpense()
    {
        if (!$this->db->openConnection()) {
            error_log("Database connection failed in createExpense");
            return false;
        }
        $query = "
    INSERT INTO expense
    (
        trip_id,
        category_id,
        original_currency,
        description, 
        original_amount, 
        converted_amount, 
        uploaded_by
    )   
    VALUES
    (
        '$this->trip_id',
        '$this->category_id',
        '$this->original_currency',
        '$this->description',
        '$this->original_amount',
        '$this->converted_amount',
        '$this->uploaded_by'
    )
    ";
    $result = $this->db->insert($query);

    $this->db->closeConnection();

    return $result;
}

    public function getExpensesByTrip($trip_id) {

    if(!$this->db->openConnection()) {
        return [];
    }

    $query = "SELECT * FROM expense WHERE trip_id = $trip_id ORDER BY created_at DESC";

    $result = $this->db->select($query);

    $this->db->closeConnection();

    return $result;
}

public function getExpense($id) {
    if(!$this->db->openConnection()) return false;
    $query = "SELECT * FROM expense WHERE expense_id = $id";
    $result = $this->db->select($query);
    $this->db->closeConnection();
    return $result ? $result[0] : null;
}







    public function addExpense($expenseData, $splits)
    {
        try {
            // Set properties from $expenseData
            $this->trip_id = (int)$expenseData['trip_id'];
            $this->category_id = (int)$expenseData['category_id'];
            $this->original_currency = (string)$expenseData['original_currency'];
            $this->description = (string)$expenseData['description'];
            $this->original_amount = (float)$expenseData['original_amount'];
            $this->converted_amount = (float)$expenseData['converted_amount'];
            $this->uploaded_by = (int)$expenseData['uploaded_by'];

            // Create the expense
            $expenseId = $this->createExpense();

            if (!$expenseId) {
                error_log("Failed to create expense in addExpense");
                return false;
            }

            // Save splits
            if (!$this->saveSplits($expenseId, $splits)) {
                error_log("Failed to save splits for expense ID: " . $expenseId);
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("Error in addExpense: " . $e->getMessage());
            return false;
        }
    }
}