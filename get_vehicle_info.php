<?php
require 'config.php';
header('Content-Type: application/json');

$id = intval($_GET['id']);

// Gauti transporto priemonės info
$stmt = $pdo->prepare("SELECT v.*, COALESCE(g.group_name,'Nepasirinkta') AS group_name 
                       FROM vehicles v 
                       LEFT JOIN vehicle_groups g ON v.group_id=g.id 
                       WHERE v.id=?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$vehicle) exit(json_encode(['error'=>'Neegzistuoja automobilis']));

// Gauti nuotraukas
$stmt = $pdo->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC");
$stmt->execute([$id]);
$vehicle['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gauti rezervacijas
$stmt = $pdo->prepare("SELECT r.*, u.name AS user_name 
                       FROM reservations r 
                       LEFT JOIN users u ON r.user_id=u.id 
                       WHERE r.vehicle_id=? 
                       ORDER BY r.reserved_at DESC");
$stmt->execute([$id]);
$vehicle['reservations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gražinti JSON
echo json_encode($vehicle);

