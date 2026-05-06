<?php 
require_once '../../model/user.php';
require_once '../../controller/DBController.php';

class AuthController
{
    protected $db;

    public function login(User $user)
    {
        $this->db = new DBController;
        if ($this->db->openConnection()) {
            // Use prepared statement to prevent SQL injection
            $stmt = $this->db->connection->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $user->email);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();

            if ($userData && $userData['password'] === $user->password) { // plain text compare
                session_start();
                $_SESSION['user'] = [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => ($userData['roleId'] == 1) ? 'Admin' : 'Client'
                ];
                // Also keep old keys for compatibility if needed
                $_SESSION["userId"] = $userData["id"];
                $_SESSION["userName"] = $userData["name"];
                $_SESSION["userRole"] = ($userData["roleId"] == 1) ? "Admin" : "Client";
                
                $this->db->closeConnection();
                return true;
            } else {
                session_start();
                $_SESSION["errMsg"] = "You have entered wrong email or password";
                $this->db->closeConnection();
                return false;
            }
        } else {
            echo "Error in Database Connection";
            return false;
        }
    }

    public function register(User $user)
    {
        $this->db = new DBController;
        if ($this->db->openConnection()) {
            // Check if email exists
            $checkStmt = $this->db->connection->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $user->email);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $_SESSION["errMsg"] = "Email already registered";
                $this->db->closeConnection();
                return false;
            }
            $checkStmt->close();

            // Insert new user (plain text password – recommend hashing later)
            $roleId = 2; // Client
            $stmt = $this->db->connection->prepare("INSERT INTO users (name, email, password, roleId) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $user->name, $user->email, $user->password, $roleId);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                session_start();
                $_SESSION['user'] = [
                    'id' => $newId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'Client'
                ];
                $_SESSION["userId"] = $newId;
                $_SESSION["userName"] = $user->name;
                $_SESSION["userRole"] = "Client";
                $this->db->closeConnection();
                return true;
            } else {
                session_start();
                $_SESSION["errMsg"] = "Something went wrong... try again later";
                $this->db->closeConnection();
                return false;
            }
        } else {
            echo "Error in Database Connection";
            return false;
        }
    }

    // Add this method – used by index.php to check login status
    public function isLoggedIn()
    {
        session_start();
        return isset($_SESSION['user']) && !empty($_SESSION['user']);
    }

    // Add this method to get current user
    public function getCurrentUser()
    {
        session_start();
        return $_SESSION['user'] ?? null;
    }

    // Add logout method
    public function logout()
    {
        session_start();
        $_SESSION = [];
        session_destroy();
    }
    public function select($query) {
        $result = $this->connection->query($query);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return false;
    }

    public function insert($query) {
        if ($this->connection->query($query)) {
            return $this->connection->insert_id;
        }
        return false;
    }
}
?>
