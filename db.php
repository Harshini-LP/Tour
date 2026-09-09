<?php
// Auto detect da - Hosting na InfinityFree, Laptop na localhost
$currentHost = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($currentHost, 'infinityfree')!== false || strpos($currentHost, 'great-site.net')!== false || strpos($currentHost, 'free.nf')!== false || strpos($currentHost, 'onrender.com')!== false) {
  
    $host = "sql103.infinityfree.com";
    $user = "if0_42864780";
    $pass = "sriramar03";
    $dbname = "if0_42864780_tour";
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
} else {
    // Laptop / ngrok ku - old file maathiri
    $tmp = new PDO("mysql:host=localhost", "root", "");
    $tmp->exec("CREATE DATABASE IF NOT EXISTS tour_db");
    $tmp->exec("CREATE DATABASE IF NOT EXISTS if0_42864780_tour");
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "if0_42864780_tour"; // ippo itha than use panrom
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===== BOOKINGS TABLE =====
$pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
 id INT AUTO_INCREMENT PRIMARY KEY,
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
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(100) UNIQUE,
 password VARCHAR(255),
 role VARCHAR(20) DEFAULT 'customer',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("INSERT IGNORE INTO users (username,password,role) VALUES ('admin','".password_hash('admin123',PASSWORD_DEFAULT)."','admin')");

// Old columns safe-a add pannum code apdiye irukattum
$addCols = ["user_id INT DEFAULT 0","customer_phone VARCHAR(20)","diesel INT DEFAULT 0","toll INT DEFAULT 0","driver_bata INT DEFAULT 0","start_km INT DEFAULT 0","close_km INT DEFAULT 0","total_km INT DEFAULT 0","car_type VARCHAR(20)","trip_type VARCHAR(20)","advance INT DEFAULT 0","balance INT DEFAULT 0"];
foreach($addCols as $col){
 try{
   $colName = explode(" ", trim($col))[0];
   $check = $pdo->query("SHOW COLUMNS FROM bookings LIKE '$colName'")->fetch();
   if(!$check){ $pdo->exec("ALTER TABLE bookings ADD COLUMN $col"); }
 } catch(Exception $e){}
}
?>
