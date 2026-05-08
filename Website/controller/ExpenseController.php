<?php

require_once __DIR__ . '/../model/expense.php';
require_once __DIR__ . '/../controller/DBController.php';

class ExpenseController
{
    public function addExpense($data)
    {
        // Validate required fields
        $required_fields = ['trip_id', 'description', 'original_amount', 'original_currency', 'category_id'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                error_log("Missing required field in addExpense: " . $field);
                return false;
            }
        }

        // Validate data types and values
        $trip_id = (int)$data['trip_id'];
        $category_id = (int)$data['category_id'];
        $original_amount = (float)$data['original_amount'];
        $converted_amount = !empty($data['converted_amount']) ? (float)$data['converted_amount'] : $original_amount;

        // Validate positive amounts
        if ($original_amount <= 0 || $converted_amount <= 0) {
            error_log("Invalid amounts in addExpense: original=" . $original_amount . ", converted=" . $converted_amount);
            return false;
        }

        // Validate IDs are positive
        if ($trip_id <= 0 || $category_id <= 0) {
            error_log("Invalid IDs in addExpense: trip=" . $trip_id . ", category=" . $category_id . ", user=" . $user_id);
            return false;
        }

        try {
            $expense = new expense();

            // Fill expense object with validated data
            $expense->trip_id = $trip_id;
            $expense->category_id = $category_id;
            $expense->description = trim($data['description']);
            $expense->original_amount = $original_amount;
            $expense->original_currency = trim($data['original_currency']);
            $expense->converted_amount = $converted_amount;
            $expense->uploaded_by = $user_id;

            // Save expense
            $expenseId = $expense->createExpense();

            if (!$expenseId) {
                error_log("createExpense returned false or invalid ID");
                return false;
            }

            // Optional: save splits if provided
            if (isset($data['split']) && is_array($data['split']) && count($data['split']) > 0) {
                $this->saveSplits($expenseId, $data['split']);
            }

            return true;

        } catch (Exception $e) {
            error_log("Exception in addExpense: " . $e->getMessage());
            return false;
        }
    }

    private function saveSplits($expenseId, $splits)
    {
        $db = new DBController();
        if (!$db->openConnection()) {
            error_log("Failed to open DB connection in saveSplits");
            return false;
        }

        try {
            // Prepare statement once for efficiency
            $query = "INSERT INTO split (expense_id, userId, shareAmount, percentage) VALUES (?, ?, ?, ?)";
            $stmt = $db->connection->prepare($query);

            if (!$stmt) {
                error_log("Statement preparation failed in saveSplits: " . $db->connection->error);
                $db->closeConnection();
                return false;
            }

            foreach ($splits as $item) {
                $uId = (int)$item['userId'];
                $amt = (float)$item['shareAmount'];
                $pct = (float)($item['percentage'] ?? 0);

                if (!$stmt->bind_param("iddd", $expenseId, $uId, $amt, $pct)) {
                    error_log("Bind param failed in saveSplits: " . $stmt->error);
                    continue;
                }

                if (!$stmt->execute()) {
                    error_log("Execute failed for split: " . $stmt->error);
                }
            }

            $stmt->close();
            $db->closeConnection();
            return true;

        } catch (Exception $e) {
            error_log("Exception in saveSplits: " . $e->getMessage());
            $db->closeConnection();
            return false;
        }
    }

    
    public function getExpenses($trip_id)
    {
        try {
            $expense = new expense();

            $result = $expense->getExpensesByTrip($trip_id);

            return is_array($result) ? $result : [];

        } catch (Exception $e) {
            error_log("Exception in getExpenses: " . $e->getMessage());
            return [];
        }
    }
    
    /* Inside ExpenseController.php */

public function addNonCashContribution($data, $user_id) {
    $trip_id = (int)$data['trip_id'];
    $value = (float)$data['estimated_value_base'];
    $desc = trim((string)$data['description']);
    $file = isset($data['proof_file']) ? $data['proof_file'] : null;

    if ($trip_id <= 0 || $value <= 0 || empty($desc)) {
        return false;
    }

    require_once __DIR__ . '/../model/NonCash.php';
    $nc = new NonCash();
    $nc->trip_id = $trip_id;
    $nc->contributor_id = (int)$user_id; // The person logged in
    $nc->estimatedValue = $value;
    $nc->description = $desc;
    $nc->proof_file = $file;

    return $nc->create();
}
}

?>
