<?php
session_start();
require 'config.php'; // PDO $pdo

// Tik adminai
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['user_name'] ?? 'Admin';

// --- Naikinti atsiliepimą ---
if(isset($_GET['delete_review']) && is_numeric($_GET['delete_review'])){
    $review_id = intval($_GET['delete_review']);
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id=?");
    $stmt->execute([$review_id]);
    header("Location: kontrolierius_dashboard.php?deleted=review");
    exit;
}

// --- Naikinti nuotrauką ---
if(isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])){
    $image_id = intval($_GET['delete_image']);
    $stmt = $pdo->prepare("SELECT image_url FROM vehicle_images WHERE id=?");
    $stmt->execute([$image_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if($img){
        $server_path = __DIR__ . '/' . $img['image_url'];
        if(file_exists($server_path)) unlink($server_path);
    }
    $stmt = $pdo->prepare("DELETE FROM vehicle_images WHERE id=?");
    $stmt->execute([$image_id]);
    header("Location: kontrolierius_dashboard.php?deleted=image");
    exit;
}

// --- Grupės filtravimas ir paieška ---
// Iš vehicles lentelės imame unikalius grupe ENUM reikšmes
$filter_group = $_GET['group_filter'] ?? '';
$search = $_GET['search'] ?? '';
$groups = $pdo->query("SELECT DISTINCT grupe FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);

// --- Transporto priemonės ---
$sql = "SELECT * FROM vehicles WHERE 1";
$params = [];

if($filter_group !== ''){
    $sql .= " AND grupe=?";
    $params[] = $filter_group;
}

if($search !== ''){
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Nuotraukos ir atsiliepimai ---
foreach($vehicles as &$v){
    $stmt = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC");
    $stmt->execute([$v['id']]);
    $v['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT r.*, u.name AS user_name 
                           FROM reviews r 
                           LEFT JOIN users u ON r.user_id=u.id 
                           WHERE r.vehicle_id=? 
                           ORDER BY r.created_at DESC");
    $stmt->execute([$v['id']]);
    $v['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($v);

// ✅ Pranešimo tekstas (jei kažkas buvo ištrinta)
$deleted_msg = '';
if(isset($_GET['deleted'])){
    if($_GET['deleted'] === 'review') $deleted_msg = "Atsiliepimas sėkmingai ištrintas!";
    elseif($_GET['deleted'] === 'image') $deleted_msg = "Nuotrauka sėkmingai ištrinta!";
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Admino skydelis</title>
<style>
body {font-family:'Segoe UI',Tahoma; background:#f4f6f9; margin:0; padding:20px; text-align:center;}
h1,h2 {color:#333; margin-bottom:20px;}
.filter-form {max-width:800px; margin:20px auto; display:flex; gap:10px; flex-wrap:wrap; justify-content:center; align-items:flex-end;}
.filter-form div {flex:1; min-width:200px;}
.filter-form select, .filter-form input {width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;}
.filter-form button {padding:10px 20px; border:none; border-radius:8px; background:#007bff; color:#fff; cursor:pointer; transition:0.3s;}
.filter-form button:hover {background:#0056b3;}
.vehicles {display:flex; flex-wrap:wrap; justify-content:center; gap:20px; margin-top:30px;}
.vehicle-card {background:#fff; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,0.1); overflow:hidden; width:350px; transition:.3s; padding:10px; text-align:left;}
.vehicle-card:hover {transform: scale(1.03); box-shadow:0 10px 20px rgba(0,0,0,0.2);}
.vehicle-card img.main-img {width:100%; height:220px; object-fit:cover; border-radius:8px; margin-bottom:5px;}
.vehicle-content h3 {margin:0 0 10px; color:#222;}
.vehicle-content p {margin:5px 0; color:#555;}
.thumbnails {display:flex; gap:5px; margin-top:5px; flex-wrap:wrap;}
.thumbnails div {position:relative;}
.thumbnails img.thumb {width:60px; height:80px; object-fit:cover; cursor:pointer; border:2px solid #ddd; border-radius:6px; transition:0.2s;}
.thumbnails img.thumb:hover {border-color:#007bff;}
a.button {background:#d9534f; color:#fff; padding:3px 7px; border-radius:5px; text-decoration:none; margin-left:5px; font-size:12px;}
a.button:hover {background:#c9302c;}
.reviews {margin-top:10px;}
.reviews p {margin:3px 0;}
.alert-success {background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:10px; border-radius:8px; max-width:600px; margin:10px auto; font-weight:500; animation: fadeIn 0.3s ease-in-out;}
@keyframes fadeIn {from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);}}
</style>
</head>
<body>

<h1>Sveiki atvykę, <?= htmlspecialchars($admin_name) ?>!</h1>

 <!-- Atsijungimo mygtukas viršuje dešinėje -->
    <a href="logout.php" style="position:absolute; top:10px; right:10px; background:#dc3545; color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:600; box-shadow:0 4px 12px rgba(220,53,69,0.2);">
        Atsijungti
    </a>

<?php if($deleted_msg): ?> 
    <div class="alert-success"><?= htmlspecialchars($deleted_msg) ?></div>
<?php endif; ?>

<!-- Filtras + paieška -->
<form method="get" class="filter-form">
    <div>
        <label>Filtruoti pagal grupę:</label>
        <select name="group_filter" onchange="this.form.submit()">
            <option value="">Visos grupės</option>
            <?php foreach($groups as $g): ?>
                <option value="<?= htmlspecialchars($g) ?>" <?= ($filter_group==$g)?'selected':'' ?>><?= htmlspecialchars(ucfirst($g)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Paieška pagal pavadinimą:</label>
        <input type="text" name="search" placeholder="Įveskite pavadinimą..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div>
        <button type="submit">Ieškoti</button>
    </div>
</form>

<h2>Visos transporto priemonės ir atsiliepimai</h2>
<div class="vehicles">
<?php foreach($vehicles as $v): 
    $first_img = $v['images'][0]['image_url'] ?? 'https://via.placeholder.com/400x250?text=No+Image';
?>
<div class="vehicle-card" id="vehicle-<?= $v['id'] ?>">
    <img class="main-img" src="<?= htmlspecialchars($first_img) ?>" alt="">
    <div class="vehicle-content">
        <h3><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars(ucfirst($v['grupe'])) ?>)</h3>
        <p><?= htmlspecialchars($v['description']) ?></p>
        <p><strong>Kaina:</strong> <?= htmlspecialchars($v['price_per_day']) ?> € / dienai</p>
        <p><strong>Rida:</strong> <?= htmlspecialchars($v['mileage_km']) ?> km</p>

        <?php if(!empty($v['images'])): ?>
            <div class="thumbnails">
                <?php foreach($v['images'] as $img): ?>
                    <div>
                        <img class="thumb" src="<?= htmlspecialchars($img['image_url']) ?>" alt="" onclick="changeMainImage(this)">
                        <a href="?delete_image=<?= $img['id'] ?>" class="button" onclick="return confirm('Ar tikrai norite ištrinti nuotrauką?')">X</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="reviews">
            <h4>Atsiliepimai:</h4>
            <?php if(!empty($v['reviews'])): ?>
                <?php foreach($v['reviews'] as $rev): ?>
                    <p>
                        <strong><?= htmlspecialchars($rev['user_name']) ?>:</strong> 
                        <?= htmlspecialchars($rev['review_text']) ?> 
                        <?php if(isset($rev['rating'])): ?> - <?= intval($rev['rating']) ?> ⭐<?php endif; ?>
                        <a href="?delete_review=<?= $rev['id'] ?>" class="button" onclick="return confirm('Ar tikrai norite ištrinti atsiliepimą?')">X</a>
                    </p>
                <?php endforeach; ?>
            <?php else: ?>
                <p><em>Dar nėra atsiliepimų.</em></p>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php endforeach; ?>
</div>

<script>
function changeMainImage(img){
    const card = img.closest('.vehicle-card');
    const mainImg = card.querySelector('.main-img');
    mainImg.src = img.src;
}
setTimeout(() => {
    const msg = document.querySelector('.alert-success');
    if(msg) msg.style.display = 'none';
}, 3000);
</script>

</body>
</html>
