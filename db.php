<?php
$currentHost = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($currentHost, 'onrender.com') !== false) {
    // RENDER KU - SQLite use pannrom (InfinityFree block panniduchu)
    $dbFile = __DIR__ . '/tourtravels.db';
    $pdo = new PDO("sqlite:$dbFile");
} elseif (strpos($currentHost, 'infinityfree')!== false || strpos($currentHost, 'great-site.net')!== false || strpos($currentHost, 'free.nf')!== false) {
    $host = "sql103.infinityfree.com";
    $user = "if0_42864780";
    $pass = "sriramar03";
    $dbname = "if0_42864780_tour";
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
} else {
    $tmp = new PDO("mysql:host=localhost", "root", "");
    $tmp->exec("CREATE DATABASE IF NOT EXISTS tour_db");
    $tmp->exec("CREATE DATABASE IF NOT EXISTS if0_42864780_tour");
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "if0_42864780_tour";
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===== BOOKINGS TABLE =====
$pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 car_number VARCHAR(50), car_name VARCHAR(50),
 driver_name VARCHAR(100), customer_name VARCHAR(100),
 customer_phone VARCHAR(20),
 pickup VARCHAR(100), destination VARCHAR(100),
 tour_date DATE, tour_time TIME,
 start_km INT DEFAULT 0, close_km INT DEFAULT 0, total_km INT DEFAULT 0,
 car_type VARCHAR(20), trip_type VARCHAR(20),
 fare INT, advance INT DEFAULT 0, balance INT DEFAULT 0,
 diesel INT DEFAULT 0, toll INT DEFAULT 0, driver_bata INT DEFAULT 0,
 payment VARCHAR(20),
 user_id INT DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ===== USERS TABLE =====
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 username VARCHAR(100) UNIQUE,
 password VARCHAR(255),
 role VARCHAR(20) DEFAULT 'customer',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("INSERT OR IGNORE INTO users (id, username,password,role) VALUES (1, 'admin','".password_hash('admin123',PASSWORD_DEFAULT)."','admin')");
?>
