<?php
$host = 'localhost';
$dbname = 'u545996239_cdsportal';
$db_username = 'u545996239_cdsportal'; // Changed variable name to avoid conflict
$db_password = 'B@nana2025';     //

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>