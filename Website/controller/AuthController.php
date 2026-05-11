<?php
require_once __DIR__ . '/../model/User.php';
require_once __DIR__ . '/DBController.php';

class AuthController
{
    protected $db;

    public function login(User $user)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = new DBController();
        if (!$this->db->openConnection()) {
            return false;
        }

        $email = $this->db->connection->real_escape_string($user->email);

        // Fetch by email only — password is verified with password_verify()
        $query  = "SELECT * FROM users WHERE email='$email' LIMIT 1";
        $result = $this->db->select($query);
        $this->db->closeConnection();

        if ($result === false || count($result) === 0) {
            $_SESSION['errMsg'] = 'Invalid email or password';
            return false;
        }

        $row = $result[0];

        // Support both legacy plain-text passwords and new hashed passwords
        $passwordValid = false;
        if (password_get_info($row['password'])['algo'] !== null && password_get_info($row['password'])['algo'] !== 0) {
            // Hashed password — use password_verify
            $passwordValid = password_verify($user->password, $row['password']);
        } else {
            // Legacy plain-text — compare directly, then upgrade the hash
            $passwordValid = ($user->password === $row['password']);
            if ($passwordValid) {
                // Upgrade to hashed on next login
                $this->db = new DBController();
                if ($this->db->openConnection()) {
                    $newHash = password_hash($user->password, PASSWORD_DEFAULT);
                    $newHash = $this->db->connection->real_escape_string($newHash);
                    $uid     = (int)$row['user_id'];
                    $this->db->connection->query("UPDATE users SET password='$newHash' WHERE user_id=$uid");
                    $this->db->closeConnection();
                }
            }
        }

        if (!$passwordValid) {
            $_SESSION['errMsg'] = 'Invalid email or password';
            return false;
        }

        $_SESSION['userId']   = $row['user_id'];
        $_SESSION['userName'] = $row['name'];
        $_SESSION['userRole'] = 'Client';
        return true;
    }

    public function register(User $user)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($user->name) || empty($user->email) || empty($user->password)) {
            return ['success' => false, 'message' => 'All fields are required.'];
        }

        if (strlen($user->password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        $this->db = new DBController();
        if (!$this->db->openConnection()) {
            return ['success' => false, 'message' => 'Database connection error.'];
        }

        $name  = $this->db->connection->real_escape_string(trim($user->name));
        $email = $this->db->connection->real_escape_string(strtolower(trim($user->email)));

        // Hash the password — never store plain text
        $hashedPassword = password_hash($user->password, PASSWORD_DEFAULT);
        $hashedPassword = $this->db->connection->real_escape_string($hashedPassword);

        // Check duplicate email
        $check = $this->db->select("SELECT user_id FROM users WHERE email='$email' LIMIT 1");
        if ($check !== false && count($check) > 0) {
            $this->db->closeConnection();
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $query  = "INSERT INTO users (name, email, password) VALUES ('$name', '$email', '$hashedPassword')";
        $result = $this->db->insert($query);
        $this->db->closeConnection();

        if ($result === false) {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }

        return ['success' => true, 'message' => 'Account created successfully!'];
    }

    public function isLoggedIn()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['userId']);
    }

    public function getCurrentUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['userId'])) {
            return null;
        }
        $user          = new User();
        $user->user_id = $_SESSION['userId'];
        $user->name    = $_SESSION['userName'];
        return $user;
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }
}