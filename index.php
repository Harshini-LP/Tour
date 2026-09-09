<?php
session_start();
include 'db.php';

// --- SAFE PATCHES ---
try { $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE, password VARCHAR(255), role VARCHAR(20) DEFAULT 'customer')"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'customer'"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE bookings ADD COLUMN user_id INT DEFAULT 1"); } catch(Exception $e){}

// Fix old 'member' role to 'customer'
$pdo->exec("UPDATE users SET role='customer' WHERE role='member'");

$pdo->exec("INSERT OR IGNORE INTO users (id, username,password,role) VALUES (1, 'admin','".password_hash('admin123',PASSWORD_DEFAULT)."','admin')");

// --- REGISTER ---
if (isset($_POST['register'])) {
    $u = trim($_POST['user']);
    $p = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?,?,'customer')")->execute([$u, $p]);
        $msg = "Account created da! Login pannu";
        $page = 'login';
    } catch (Exception $e) {
        $err = "Username already irukku da";
        $page = 'register';
    }
}

// --- ADD BOOKING - FIXED BUTTON NAME ---
if (isset($_POST['add']) || isset($_POST['add_booking'])) {
    $start_km = (int)($_POST['start_km']??0);
    $close_km = (int)($_POST['close_km']??0);
    $total_km = max(0, $close_km - $start_km);
    $fare = (int)($_POST['fare']??0);
    $advance = (int)($_POST['advance']??0);
    $balance = $fare - $advance;

    $pickup = ($_POST['pickup_district']?? $_POST['pickup']?? ''). " - ". ($_POST['pickup_address']?? '');
    $dest = ($_POST['dest_district']?? $_POST['destination']?? ''). " - ". ($_POST['dest_address']?? '');
    if(trim($pickup," -")==='') $pickup = $_POST['pickup']?? 'Madurai';
    if(trim($dest," -")==='') $dest = $_POST['destination']?? 'Chennai';

    $current_user_id = $_SESSION['user_id']?? 1;

    $stmt = $pdo->prepare("INSERT INTO bookings (car_number, car_name, driver_name, customer_name, customer_phone, pickup, destination, tour_date, tour_time, car_type, trip_type, start_km, close_km, total_km, fare, advance, balance, diesel, toll, driver_bata, payment, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['car_number'], $_POST['car_name'], $_POST['driver']??$_POST['driver_name']??'Driver', $_POST['customer'], $_POST['cphone'],
        $pickup, $dest, $_POST['tour_date']??date('Y-m-d'), $_POST['tour_time']??'08:00', $_POST['car_type']??'Sedan', $_POST['trip_type']??'One Way',
        $start_km, $close_km, $total_km, $fare, $advance, $balance,
        (int)($_POST['diesel']??0), (int)($_POST['toll']??0), (int)($_POST['bata']??$_POST['driver_bata']??0), $_POST['payment']??'Pending', $current_user_id
    ]);
    header("Location: index.php?page=bookings");
    exit;
}

// --- UPDATE ---
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $start_km = (int)$_POST['start_km']; $close_km = (int)$_POST['close_km']; $total_km = max(0, $close_km - $start_km);
    $fare = (int)$_POST['fare']; $advance = (int)$_POST['advance']; $balance = $fare - $advance;
    $pickup = $_POST['pickup_district']. " - ". $_POST['pickup_address'];
    $dest = $_POST['dest_district']. " - ". $_POST['dest_address'];

    if ($_SESSION['role'] == 'admin') {
        $pdo->prepare("UPDATE bookings SET car_number=?, car_name=?, driver_name=?, customer_name=?, customer_phone=?, pickup=?, destination=?, tour_date=?, tour_time=?, car_type=?, trip_type=?, start_km=?, close_km=?, total_km=?, fare=?, advance=?, balance=?, diesel=?, toll=?, driver_bata=?, payment=? WHERE id=?")
             ->execute([$_POST['car_number'], $_POST['car_name'], $_POST['driver'], $_POST['customer'], $_POST['cphone'], $pickup, $dest, $_POST['tour_date'], $_POST['tour_time'], $_POST['car_type'], $_POST['trip_type'], $start_km, $close_km, $total_km, $fare, $advance, $balance, (int)$_POST['diesel'], (int)$_POST['toll'], (int)$_POST['bata'], $_POST['payment'], $id]);
    } else {
        $pdo->prepare("UPDATE bookings SET car_number=?, car_name=?, driver_name=?, customer_name=?, customer_phone=?, pickup=?, destination=?, tour_date=?, tour_time=?, car_type=?, trip_type=?, start_km=?, close_km=?, total_km=?, fare=?, advance=?, balance=?, diesel=?, toll=?, driver_bata=?, payment=? WHERE id=? AND user_id=?")
             ->execute([$_POST['car_number'], $_POST['car_name'], $_POST['driver'], $_POST['customer'], $_POST['cphone'], $pickup, $dest, $_POST['tour_date'], $_POST['tour_time'], $_POST['car_type'], $_POST['trip_type'], $start_km, $close_km, $total_km, $fare, $advance, $balance, (int)$_POST['diesel'], (int)$_POST['toll'], (int)$_POST['bata'], $_POST['payment'], $id, $_SESSION['user_id']]);
    }
    header("Location: index.php?page=bookings"); exit;
}

// --- DELETE ADMIN ONLY ---
if (isset($_GET['delete'])) {
    if (($_SESSION['role']??'') == 'admin') {
        $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([(int)$_GET['delete']]);
    }
    header("Location: index.php?page=bookings"); exit;
}

// --- LOGIN ---
if (isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([trim($_POST['user'])]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['pass'], $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']?? 'customer';
        if($_SESSION['role'] == 'admin') $_SESSION['admin'] = 1;
        header("Location: index.php?page=". ($_SESSION['role']=='admin'? 'dashboard' : 'mybookings')); exit();
    } else $err = "Wrong Username / Password da!";
}

// --- LOGOUT ---
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

// --- ROUTER SAFE ---
if (!isset($_SESSION['user_id'])) {
    $page = $_GET['page']?? 'login';
    if (!in_array($page, ['login', 'register'])) $page = 'login';
} else {
    $role = $_SESSION['role']?? 'admin';
    $page = $_GET['page']?? ($role=='admin'?'dashboard':'mybookings');
    if($role == 'admin'){
        $admin_pages = ['dashboard','bookings','add','edit','customers','invoice','mybookings','myprofile','booknow'];
        if (!in_array($page, $admin_pages)) $page = 'dashboard';
    } else {
        $customer_pages = ['mybookings','myprofile','booknow','invoice','add'];
        if (!in_array($page, $customer_pages)) $page = 'mybookings';
    }
}
$dists = ["Ariyalur","Chengalpattu","Chennai","Coimbatore","Cuddalore","Dharmapuri","Dindigul","Erode","Kallakurichi","Kanchipuram","Kanyakumari","Karur","Krishnagiri","Madurai","Mayiladuthurai","Nagapattinam","Namakkal","Nilgiris","Perambalur","Pudukkottai","Ramanathapuram","Ranipet","Salem","Sivaganga","Tenkasi","Thanjavur","Theni","Thoothukudi","Tiruchirappalli","Tirunelveli","Tirupathur","Tiruppur","Tiruvallur","Tiruvannamalai","Tiruvarur","Vellore","Viluppuram","Virudhunagar"];
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sri Ramar Travels</title><meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script><style>@media print { nav, #sidebar, #sidebarOverlay, header,.no-print { display:none!important; } } 
   </style></head><body class="bg-gray-100">

<?php if (isset($_SESSION['user_id'])):?>
<header class="bg-gray-900 text-white p-3 flex justify-between items-center sticky top-0 z-50">
  <div class="flex items-center gap-3 cursor-pointer" onclick="toggleMenu()"><div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-200 to-orange-400 p-0.5"><img src="logo.jpg" class="w-full h-full rounded-full bg-white"></div><span class="font-black text-yellow-400">SRI RAMAR</span><span class="text-xs">☰</span></div>
  <div class="flex items-center gap-2"><span class="text-xs bg-gray-800 px-2 py-1 rounded uppercase">👤 <?= htmlspecialchars($_SESSION['username'])?> (<?=$_SESSION['role']?>)</span><a href="?logout=1" class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">Logout</a></div>
</header>
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40" onclick="toggleMenu()"></div>
<div id="sidebar" class="fixed top-0 left-0 w-64 h-full bg-gray-900 text-white transform -translate-x-full transition-transform duration-300 z-50">
    <div class="p-4 border-b border-gray-800 flex justify-between"><span class="font-black text-yellow-400">NAVIGATION</span><button onclick="toggleMenu()" class="text-2xl">&times;</button></div>
    <nav class="p-4 flex flex-col gap-2">
        <?php if($_SESSION['role']=='admin'):?>
        <a href="?page=dashboard" class="p-3 rounded hover:bg-gray-800 <?= $page=='dashboard'?'bg-gray-800 border-l-4 border-yellow-400':''?>">📊 Dashboard</a>
        <a href="?page=bookings" class="p-3 rounded hover:bg-gray-800 <?= $page=='bookings'?'bg-gray-800 border-l-4 border-yellow-400':''?>">📋 All Bookings</a>
        <a href="?page=add" class="p-3 rounded bg-yellow-500 text-black font-bold">🚗 Add Journey</a>
        <a href="?page=customers" class="p-3 rounded hover:bg-gray-800">👥 Customers</a>
        <?php else:?>
        <a href="?page=mybookings" class="p-3 rounded hover:bg-gray-800 <?= $page=='mybookings'?'bg-gray-800':''?>">🚗 My Trips</a>
        <a href="?page=myprofile" class="p-3 rounded hover:bg-gray-800">👤 My Profile</a>
        <a href="?page=add" class="p-3 rounded bg-yellow-500 text-black font-bold">🚗 Add My Journey</a>
        <a href="?page=booknow" class="p-3 rounded bg-blue-500 text-white font-bold">📞 Book Now</a>

        <?php endif;?>
    </nav>
</div>
<script>function toggleMenu(){let sb=document.getElementById('sidebar'),ov=document.getElementById('sidebarOverlay');if(sb.classList.contains('-translate-x-full')){sb.classList.remove('-translate-x-full');ov.classList.remove('hidden');}else{sb.classList.add('-translate-x-full');ov.classList.add('hidden');}}</script>
<?php endif;?>

<div class="p-4 max-w-6xl mx-auto">
<?php if($page=='login'):?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-yellow-50 to-orange-100 p-4"><div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-sm text-center"><h1 class="font-black text-2xl">SRI RAMAR TRAVELS</h1><p class="text-gray-500 text-sm mb-4">Tour & Travels</p><img src="logo.jpg" class="w-32 h-32 mx-auto mb-4 rounded-full"><?php if(isset($err)) echo "<p class='text-red-600 bg-red-50 p-2 rounded mb-2'>$err</p>"; if(isset($msg)) echo "<p class='text-green-600 bg-green-50 p-2 rounded mb-2'>$msg</p>";?><form method="post"><input name="user" placeholder="Username" class="border-2 p-3 w-full mb-2 rounded-lg" required><input name="pass" type="password" placeholder="Password" class="border-2 p-3 w-full mb-2 rounded-lg" required><button name="login" class="bg-black text-white w-full p-3 rounded-lg font-bold">Login</button><p class="mt-3 text-sm">No account? <a href="?page=register" class="text-blue-600 font-bold">Create</a></p></form></div></div>
<?php elseif($page=='register'):?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-yellow-50 to-orange-100 p-4"><div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-sm text-center"><h1 class="font-black text-xl mb-3">Create Account</h1><img src="logo.jpg" class="w-20 h-20 mx-auto mb-3 rounded-full"><?php if(isset($err)) echo "<p class='text-red-600 bg-red-50 p-2 rounded mb-2'>$err</p>";?><form method="post"><input name="user" placeholder="New Username" class="border p-3 w-full mb-2 rounded" required><input name="pass" type="password" placeholder="Password" class="border p-3 w-full mb-2 rounded" required><button name="register" class="bg-yellow-500 text-black w-full p-3 rounded font-bold">Create</button><p class="mt-3 text-sm"><a href="?page=login" class="text-blue-600">Login</a></p></form></div></div>
<?php elseif($page=='dashboard'): $total=$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(); $fare=$pdo->query("SELECT SUM(fare) FROM bookings")->fetchColumn(); $bal=$pdo->query("SELECT SUM(balance) FROM bookings")->fetchColumn(); $profit=$pdo->query("SELECT SUM(fare-diesel-toll-driver_bata) FROM bookings")->fetchColumn();?>
<div class="bg-black text-yellow-400 p-2 rounded mb-4 text-sm font-bold"><marquee>📌 SRI RAMAR TRAVELS Rate Board 2026 - Sedan ₹15/km | SUV ₹20/km | Crysta ₹25/km | Tempo ₹28/km | Toll & Parking Extra |</marquee></div>
<div class="grid grid-cols-1 gap-3"><div class="bg-blue-600 text-white p-4 rounded-xl">Trips<br><b class="text-2xl"><?=$total?></b></div><div class="bg-green-600 text-white p-4 rounded-xl">Income<br><b class="text-2xl">Rs.<?=$fare?:0?></b></div><div class="bg-red-600 text-white p-4 rounded-xl">Balance<br><b class="text-2xl">Rs.<?=$bal?:0?></b></div><div class="bg-yellow-500 text-black p-4 rounded-xl">Profit<br><b class="text-2xl">Rs.<?=$profit?:0?></b></div></div>
<?php elseif($page=='mybookings'):?>
<div class="bg-white p-3 rounded"><h2 class="font-bold mb-2">🚗 My Trips - <?=$_SESSION['username']?></h2><table class="w-full text-sm"><tr class="bg-blue-600 text-white"><th class="p-2">Car</th><th class="p-2">Route</th><th class="p-2">Fare</th><th>Bill</th></tr>
<?php $stmt=$pdo->prepare("SELECT * FROM bookings WHERE user_id=? ORDER BY id DESC"); $stmt->execute([$_SESSION['user_id']]); foreach($stmt->fetchAll() as $r):?><tr class="border-b"><td class="p-2"><b><?=$r['car_number']?></b><br><?=$r['car_name']?></td><td class="p-2"><?=$r['pickup']?>→<?=$r['destination']?><br><?=$r['tour_date']?></td><td class="p-2">Rs.<?=$r['fare']?> / Bal: <b class="text-red-600"><?=$r['balance']?></b></td><td class="p-2"><a href="?page=invoice&id=<?=$r['id']?>" class="bg-black text-white px-2 py-1 rounded text-xs">Bill</a></td></tr><?php endforeach;?></table></div>
<?php elseif($page=='myprofile'):?><div class="bg-white p-6 rounded max-w-sm mx-auto text-center"><h2 class="font-bold text-xl mb-3">👤 My Profile</h2><p class="font-bold"><?=$_SESSION['username']?></p><p class="text-sm text-gray-500">ID: <?=$_SESSION['user_id']?> | Role: <?=$_SESSION['role']?></p><a href="?page=mybookings" class="bg-blue-600 text-white px-4 py-2 rounded mt-4 inline-block">My Trips</a></div>
<?php elseif($page=='booknow'):?><div class="bg-yellow-50 p-6 rounded border-2 border-dashed text-center"><h2 class="font-bold text-lg">📞 Call to Book!</h2><p class="text-2xl font-black mt-2">SRI RAMAR TRAVELS 24x7</p><a href="?page=mybookings" class="bg-black text-white px-4 py-2 rounded mt-4 inline-block">Back</a></div>

<?php elseif($page=='edit'): $id=(int)($_GET['id']??0); $r=$pdo->prepare("SELECT * FROM bookings WHERE id=?"); $r->execute([$id]); $r=$r->fetch(); if(!$r) echo "Not found"; else { $pp=explode(" - ",$r['pickup'],2); $pd=$pp[0]??''; $pa=$pp[1]??$r['pickup']; $dp=explode(" - ",$r['destination'],2); $dd=$dp[0]??''; $da=$dp[1]??$r['destination'];?>
<form method="POST" class="bg-white p-4 rounded grid md:grid-cols-2 gap-3"><h2 class="col-span-2 font-bold">Edit #<?=$r['id']?></h2><input type="hidden" name="id" value="<?=$r['id']?>"><input name="car_number" value="<?=$r['car_number']?>" class="border p-2 rounded" required><input name="car_name" value="<?=$r['car_name']?>" class="border p-2 rounded" required><input name="customer" value="<?=$r['customer_name']?>" class="border p-2 rounded" required><input name="cphone" value="<?=$r['customer_phone']?>" class="border p-2 rounded" required><input name="tour_date" type="date" value="<?=$r['tour_date']?>" class="border p-2 rounded" required><input name="fare" type="number" value="<?=$r['fare']?>" class="border p-2 rounded"><input name="advance" type="number" value="<?=$r['advance']?>" class="border p-2 rounded"><input name="driver" value="<?=$r['driver_name']?>" class="border p-2 rounded"><select name="car_type" class="border p-2 rounded"><option <?=$r['car_type']=='Sedan'?'selected':''?>>Sedan</option><option <?=$r['car_type']=='SUV'?'selected':''?>>SUV</option></select><button name="update" class="col-span-2 bg-blue-600 text-white p-2 rounded">Update</button></form><?php }?>

<?php elseif($page=='add'): $cars_list = [["no"=>"TN 45 CH 1234","name"=>"Innova Crysta"],["no"=>"TN 45 AB 5678","name"=>"Innova"],["no"=>"TN 63 XY 9012","name"=>"Etios"],["no"=>"TN 59 CD 3456","name"=>"Swift Dzire"],["no"=>"TN 30 GH 7890","name"=>"Tempo 12 Seater"],["no"=>"TN 45 EF 2024","name"=>"Ertiga"]];?>
<form method="POST" class="bg-white p-4 rounded grid md:grid-cols-2 gap-3"><h2 class="col-span-2 font-bold">New Booking</h2>
<div class="col-span-2 bg-yellow-50 p-3 rounded border-l-4 border-yellow-500 grid grid-cols-2 gap-2"><select id="carNameSelect" class="border p-2 rounded col-span-2" onchange="let v=this.value.split('|');document.getElementById('car_number').value=v[0];document.getElementById('car_name').value='SRI RAMAR - '+v[1];"><option value="">-- Car Select --</option><?php foreach($cars_list as $c) echo "<option value='{$c['no']}|{$c['name']}'>{$c['name']} - {$c['no']}</option>";?></select><input name="car_number" id="car_number" placeholder="Car Number" class="border p-2 rounded bg-gray-100" required><input name="car_name" id="car_name" placeholder="Car Name" class="border p-2 rounded bg-gray-100" required></div>
<input name="customer" placeholder="Customer Name" class="border p-2 rounded" required><input name="cphone" placeholder="Phone" class="border p-2 rounded" required><input name="driver" placeholder="Driver Name" class="border p-2 rounded"><input name="tour_date" type="date" value="<?=date('Y-m-d')?>" class="border p-2 rounded" required><input name="pickup_district" list="dists" placeholder="Pickup District" class="border p-2 rounded" ><input name="pickup_address" placeholder="Pickup Address" class="border p-2 rounded"><input name="dest_district" list="dists" placeholder="Dest District" class="border p-2 rounded"><input name="dest_address" placeholder="Dest Address" class="border p-2 rounded"><datalist id="dists"><?php foreach($dists as $d) echo "<option value='$d'>";?></datalist><input name="fare" type="number" placeholder="Fare" class="border p-2 rounded" required><input name="advance" type="number" placeholder="Advance" class="border p-2 rounded" ><input name="start_km" type="number" placeholder="Start KM" class="border p-2 rounded"><input name="close_km" type="number" placeholder="Close KM" class="border p-2 rounded"><select name="car_type" class="border p-2 rounded"><option>Sedan</option><option>SUV</option><option>Tempo Traveller</option></select><select name="trip_type" class="border p-2 rounded"><option>One Way</option><option>Round Trip</option></select><select name="payment" class="border p-2 rounded"><option>Pending</option><option>Paid</option></select><input name="tour_time" type="time" class="border p-2 rounded" value="08:00"><button name="add" class="col-span-2 bg-green-600 text-white p-2 rounded font-bold">Save Booking</button></form>

<?php elseif($page=='bookings'):?>
<div class="bg-white p-3 rounded shadow mb-3"><input id="s" placeholder="🔎 Search" class="border p-2 w-full mb-3 rounded" onkeyup="let v=this.value.toLowerCase();document.querySelectorAll('#t tr:not(:first-child)').forEach(r=>r.style.display=r.innerText.toLowerCase().includes(v)?'':'none')"><table class="w-full text-xs" id="t"><tr class="bg-gray-800 text-white"><th class="p-2">Car & Customer</th><th class="p-2">Route</th><th class="p-2">Fare</th><th class="p-2">Action</th></tr>
<?php
if($_SESSION['role']=='admin') $all=$pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();
else { $st=$pdo->prepare("SELECT * FROM bookings WHERE user_id=? ORDER BY id DESC"); $st->execute([$_SESSION['user_id']]); $all=$st->fetchAll(); }
foreach($all as $r): $profitRow=$r['fare']-$r['diesel']-$r['toll']-$r['driver_bata'];?>
<tr class="border-b"><td class="p-2"><b><?=$r['car_number']?></b><br><?=$r['customer_name']?>-<?=$r['customer_phone']?></td><td class="p-2"><?=$r['pickup']?> to <?=$r['destination']?><br><?=$r['tour_date']?></td><td class="p-2">Fare:<?=$r['fare']?><br>Bal:<b class="text-red-600"><?=$r['balance']?></b><br>Profit:<span class="text-green-600"><?=$profitRow?></span></td><td class="p-2"><a href="?page=edit&id=<?=$r['id']?>" class="bg-blue-600 text-white px-2 py-1 rounded block text-center mb-1">Edit</a><a href="?page=invoice&id=<?=$r['id']?>" class="bg-black text-white px-2 py-1 rounded block text-center mb-1">Bill</a><?php if($_SESSION['role']=='admin'):?><a href="?delete=<?=$r['id']?>" onclick="return confirm('Delete?')" class="bg-red-500 text-white px-2 py-1 rounded block text-center">Del</a><?php endif;?></td></tr><?php endforeach;?></table></div>

<?php elseif($page=='invoice'): $id=(int)$_GET['id']; $stmt=$pdo->prepare("SELECT * FROM bookings WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r) die("Not found"); if($_SESSION['role']!='admin' && $r['user_id']!=$_SESSION['user_id']) die("Not allowed!");?>
<div class="bg-white p-8 rounded max-w-2xl mx-auto"><h1 class="font-black text-center text-xl">SRI RAMAR TRAVELS - INVOICE #00<?=$r['id']?></h1><hr class="my-3"><p>Car: <?=$r['car_number']?> (<?=$r['car_name']?>)</p><p>Customer: <?=$r['customer_name']?> - <?=$r['customer_phone']?></p><p>Route: <?=$r['pickup']?> → <?=$r['destination']?></p><p>Date: <?=$r['tour_date']?> KM: <?=$r['total_km']?></p><hr class="my-3"><p>Fare: Rs.<?=$r['fare']?> Advance: <?=$r['advance']?> Balance: <b>Rs.<?=$r['balance']?></b></p><button onclick="window.print()" class="bg-black text-white px-8 py-2 rounded mt-4 w-full no-print">Print PDF</button></div>

<?php elseif($page=='customers'):?>
<div class="bg-white p-4 rounded"><h2 class="font-bold mb-3">👥 Customer Phonebook</h2><table class="w-full text-sm"><tr class="bg-black text-white"><th class="p-2">Name</th><th>Phone</th><th>Trips</th><th>WA</th></tr><?php foreach($pdo->query("SELECT customer_name, customer_phone, COUNT(*) trips FROM bookings WHERE customer_phone!='' GROUP BY customer_phone ORDER BY trips DESC")->fetchAll() as $cu):?><tr class="border-b"><td class="p-2"><?=$cu['customer_name']?></td><td><?=$cu['customer_phone']?></td><td><?=$cu['trips']?></td><td><a href="https://wa.me/91<?=$cu['customer_phone']?>" target="_blank" class="bg-green-500 text-white px-2 py-1 rounded text-xs">WhatsApp</a></td></tr><?php endforeach;?></table></div>
<?php endif;?>
</div></body></html>
