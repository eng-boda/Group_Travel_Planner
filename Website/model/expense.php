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

        $query = "INSERT INTO expense 
        (trip_id, category_id, original_currency, description, original_amount, converted_amount, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->connection->prepare($query);

        if (!$stmt) {
            error_log("Statement preparation failed: " . $this->db->connection->error);
            $this->db->closeConnection();
            return false;
        }

        // Variables in SAME ORDER as the query
        $trip_id = (int)$this->trip_id;
        $category_id = (int)$this->category_id;
        $currency = (string)$this->original_currency;
        $description = (string)$this->description;
        $original_amount = (float)$this->original_amount;
        $converted_amount = (float)$this->converted_amount;
        $uploaded_by = (int)$this->uploaded_by;

        // bind_param types MUST match the ORDER in the INSERT query
        // Query order: trip_id(i), category_id(i), original_currency(s), description(s), original_amount(d), converted_amount(d), uploaded_by(i)
        if (!$stmt->bind_param("iissddi", $trip_id, $category_id, $currency, $description, $original_amount, $converted_amount, $uploaded_by)) {
            error_log("Bind param failed: " . $stmt->error);
            $stmt->close();
            $this->db->closeConnection();
            return false;
        }

        // Execute and check for errors
        if ($stmt->execute()) {
            $newId = $this->db->connection->insert_id;
            $stmt->close();
            $this->db->closeConnection();
            return $newId;
        }

        error_log("Statement execution failed: " . $stmt->error);
        $stmt->close();
        $this->db->closeConnection();
        return false;
    }

    public function getExpensesByTrip($trip_id)
{
    $this->db->openConnection();

    $query = "SELECT * FROM expense WHERE trip_id = ?";

    $stmt = $this->db->connection->prepare($query);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $trip_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $expenses = [];

    if ($result) {
        $expenses = $result->fetch_all(MYSQLI_ASSOC);
    }

    $stmt->close();
    $this->db->closeConnection();

    return $expenses;
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