<?php
// db.php - Database connection
//$host = 'http://localhost:8080/';
//$db   = 'smart_agri'; // Change to your DB name
//$user = 'root';         // Change to your DB user
//$pass = 'rootpassword';             // Change to your DB password

$servername = "mysql"; // This is the service name of your MySQL container
$username = "root";    // As defined in MYSQL_USER
$password = "rootpassword"; // As defined in MYSQL_PASSWORD
$dbname = "smart_agri";    // As defined in MYSQL_DATABASE
$port = "3306";        // The default MySQL port inside the container

// Create connection using MySQLi
$conn = new mysqli($servername, $username, $password, $dbname, $port);

//$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

/**
 * Helper function to get the final SQL statement with bound parameters
 * Useful for debugging SQL statements
 * 
 * @param string $sql The SQL statement with placeholders
 * @param string $types The types string (e.g., 'siiiiidddd')
 * @param array $params The array of parameters to bind
 * @return string The final SQL statement with actual values
 */
function getFinalSQL($sql, $types, $params) {
    $finalSQL = $sql;
    
    // Replace each ? with the actual value
    foreach ($params as $param) {
        $pos = strpos($finalSQL, '?');
        if ($pos !== false) {
            // Escape the value properly for display
            if (is_string($param)) {
                $param = "'" . addslashes($param) . "'";
            }
            $finalSQL = substr_replace($finalSQL, $param, $pos, 1);
        }
    }
    
    return $finalSQL;
}