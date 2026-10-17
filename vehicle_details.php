<?php
session_start();
require 'config.php';

// Patikrinimas ar vartotojas prisijungęs
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'vartotojas') {
    header("Location: login.php");
    exit;
}

// Gauname transporto priemonės ID
$vehicle_id = $_GET['id'] ?? null;
if(!$vehicle_id) {
    die("Transporto priemonė nerasta.");
}

// Gauname transporto priemonę iš DB
$stmt = $pdo->prepare("
    SELECT *, 
           CASE WHEN group_id IS NULL THEN 'Nepasirinkta' ELSE group_id END AS group_name
    FROM vehicles
    WHERE id = ?
");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch();

if(!$vehicle) {
    die("Transporto priemonė nerasta.");
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($vehicle['name']) ?> - Detalės</title>
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: #eef2f7;
    margin:0;
    padding:20px;
}

.container {
    max-width: 500px; /* siauresnis konteineris telefono tipo nuotraukai */
    margin: 40px auto;
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

h1 {
    color:#007bff;
    font-size: 1.5em;
    text-align: center;
    margin-bottom: 20px;
}

.vehicle-image {
    width: 100%;
    height: 600px; /* telefono tipo aukštis */
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 20px;
}

p {
    color:#555;
    font-size:16px;
    margin: 8px 0;
}

span {
    font-weight:bold;
}

.back-link {
    display:block;
    text-align:center;
    margin-top:20px;
    padding:10px 15px;
    background:#3498db;
    color:#fff;
    border-radius:5px;
    text-decoration:none;
    transition: 0.3s;
}
.back-link:hover {
    background:#1abc9c;
}
</style>
</head>
<body>

<div class="container">
    <h1><?= htmlspecialchars($vehicle['name']) ?> (<?= htmlspecialchars($vehicle['group_name']) ?>)</h1>

    <?php if($vehicle['image_url']): ?>
        <img class="vehicle-image" src="<?= htmlspecialchars($vehicle['image_url']) ?>" alt="<?= htmlspecialchars($vehicle['name']) ?>">
    <?php else: ?>
        <img class="vehicle-image" src="https://via.placeholder.com/400x600?text=No+Image" alt="No Image">
    <?php endif; ?>

    <p><span>Kaina per dieną:</span> <?= $vehicle['price_per_day'] ?> €</p>
    <p><span>Pravažiuota:</span> <?= $vehicle['mileage_km'] ?> km</p>
    <p><span>Aprašymas:</span> <?= nl2br(htmlspecialchars($vehicle['description'])) ?></p>

    <a href="user_dashboard.php" class="back-link">← Grįžti atgal</a>
</div>

</body>
</html>

