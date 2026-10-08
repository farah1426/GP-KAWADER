<?php
$host = "127.0.0.1";
$port = 3306;
$dbname = "kawader_db";
$username = "root";
$password = "Kawader2026@";

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    echo "Database connected successfully!";

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Database connection failed!";
}
?>