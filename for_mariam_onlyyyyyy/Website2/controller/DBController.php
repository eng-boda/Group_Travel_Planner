<?php

    class DBController {
        public $dbHost = "localhost";
        public $dbUser = "root";
        public $dbPassword = "";
        public $dbName = "travel_db";
        public $connection;

        public function openConnection() {
            $this->connection = new mysqli($this->dbHost , $this->dbUser , $this->dbPassword , $this->dbName);
            if($this->connection->connect_error) {
                echo "Error in Connection : " . $this->connection->connect_error;
                return false;
            }
            return true;
        }

        public function closeConnection() {
            if($this->connection) {
                $this->connection->close();
            } else {
                echo "Connection is not opened";
            }
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
