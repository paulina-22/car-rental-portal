<?php
session_start();
require 'config.php'; // PDO $pdo

// Klaidų rodymas
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// Tik nuomotojas
if(!isset($_SESSION['role']) || $_SESSION['role']!=='nuomotojas'){
    header("Location: login.php");
    exit;
}

// Gauti prisijungusio nuomotojo duomenis
$stmt = $pdo->prepare("SELECT name FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$nuomotojas = $stmt->fetch(PDO::FETCH_ASSOC);
$nuomotojo_vardas = $nuomotojas['name'] ?? 'Nežinomas';

// ===== UŽDARBIO APSKAIČIAVIMAS =====
// Skaičiuojame tik patvirtintas rezervacijas
$stmt = $pdo->prepare("
    SELECT SUM(
        DATEDIFF(r.end_date, r.start_date) * v.price_per_day
    ) AS total_earnings
    FROM reservations r
    JOIN vehicles v ON v.id = r.vehicle_id
    WHERE v.owner_id = ? AND r.status = 'approved'
");
$stmt->execute([$_SESSION['user_id']]);

$earnings = $stmt->fetchColumn();
if(!$earnings) $earnings = 0;


// ===== KURO TIPAI =====
$fuel_types = ['benzinas','dyzelis','elektra'];

// ===== PRIDĖJIMAS / REDAGAVIMAS =====
if(isset($_POST['submit']) || isset($_POST['update'])){
    $isUpdate = isset($_POST['update']);
    $id = $isUpdate ? intval($_POST['id']) : null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $mileage = intval($_POST['mileage'] ?? 0);
    $grupe = $_POST['grupe'] ?? null;
    if($grupe === '') $grupe = null;

    if($isUpdate){
        $stmt = $pdo->prepare("UPDATE vehicles SET name=?, description=?, price_per_day=?, mileage_km=?, grupe=? WHERE id=?");
        $stmt->execute([$name,$description,$price,$mileage,$grupe,$id]);
        $_SESSION['message'] = "Transporto priemonė sėkmingai atnaujinta!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO vehicles (name, description, price_per_day, mileage_km, grupe, owner_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name,$description,$price,$mileage,$grupe,$_SESSION['user_id']]);
        $id = $pdo->lastInsertId();
        $_SESSION['message'] = "Transporto priemonė sėkmingai sukurta!";
    }

    // ===== NUOTRAUKOS =====
    if(isset($_FILES['images']) && !empty(array_filter($_FILES['images']['name']))){
        $uploadDir = 'uploads/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $firstUploaded = null;
        foreach($_FILES['images']['tmp_name'] as $key => $tmp_name){
            if(isset($_FILES['images']['error'][$key]) && $_FILES['images']['error'][$key] === UPLOAD_ERR_OK){
                $origName = $_FILES['images']['name'][$key] ?? 'image';
                $ext = pathinfo($origName, PATHINFO_EXTENSION) ?: 'jpg';
                $filename = uniqid('img_') . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                if(move_uploaded_file($tmp_name, $targetFile)){
                    $stmtImg = $pdo->prepare("INSERT INTO vehicle_images (vehicle_id, image_url) VALUES (?, ?)");
                    $stmtImg->execute([$id, $targetFile]);
                    if(!$firstUploaded) $firstUploaded = $targetFile;
                }
            }
        }
        if($firstUploaded){
            $pdo->prepare("UPDATE vehicles SET image_url=? WHERE id=?")->execute([$firstUploaded, $id]);
        }
    }

    header("Location: nuomotojas_dashboard.php");
    exit;
}

// ===== REDAGAVIMO DALIS =====
$edit_vehicle = null;
$edit_images = [];
if(isset($_GET['edit_id'])){
    $id = intval($_GET['edit_id']);
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id=?");
    $stmt->execute([$id]);
    $edit_vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC");
    $stmt->execute([$id]);
    $edit_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== TRINTI NUOTRAUKĄ =====
if(isset($_GET['delete_image'])){
    $img_id = intval($_GET['delete_image']);
    $stmt = $pdo->prepare("SELECT image_url, vehicle_id FROM vehicle_images WHERE id=?");
    $stmt->execute([$img_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if($img){
        if(!empty($img['image_url']) && file_exists($img['image_url'])) unlink($img['image_url']);
        $pdo->prepare("DELETE FROM vehicle_images WHERE id=?")->execute([$img_id]);

        $stmt2 = $pdo->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC LIMIT 1");
        $stmt2->execute([$img['vehicle_id']]);
        $first = $stmt2->fetchColumn();
        $pdo->prepare("UPDATE vehicles SET image_url=? WHERE id=?")->execute([$first ?: null, $img['vehicle_id']]);
    }

    $_SESSION['message'] = "Nuotrauka sėkmingai ištrinta!";
    header("Location: ?edit_id=".$img['vehicle_id']);
    exit;
}

// ===== IŠTRYNIMAS SKELBIMO =====
if(isset($_GET['delete_id'])){
    $id = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("SELECT image_url FROM vehicle_images WHERE vehicle_id=?");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($images as $img){
        if(!empty($img['image_url']) && file_exists($img['image_url'])) unlink($img['image_url']);
    }

    $pdo->prepare("DELETE FROM vehicle_images WHERE vehicle_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);

    $_SESSION['message'] = "Transporto priemonės skelbimas sėkmingai ištrintas!";
    header("Location: nuomotojas_dashboard.php");
    exit;
}

// ===== FILTRAVIMAS =====
$filter_group = $_GET['group_filter'] ?? '';
$sql = "SELECT * FROM vehicles WHERE owner_id=?";
$params = [$_SESSION['user_id']];
if($filter_group !== '' && in_array($filter_group, $fuel_types)){
    $sql .= " AND grupe=?";
    $params[] = $filter_group;
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nuotraukos ir atsiliepimai su įvertinimais
foreach($vehicles as &$v){
    $stmtImg = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC");
    $stmtImg->execute([$v['id']]);
    $v['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
    if(empty($v['images']) && !empty($v['image_url'])) $v['images'][]=['image_url'=>$v['image_url']];

    $stmtRev = $pdo->prepare("SELECT r.review_text AS comment, r.rating, u.name AS user_name, r.created_at 
                               FROM reviews r 
                               LEFT JOIN users u ON r.user_id = u.id 
                               WHERE r.vehicle_id=? ORDER BY r.created_at DESC");
    $stmtRev->execute([$v['id']]);
    $v['reviews'] = $stmtRev->fetchAll(PDO::FETCH_ASSOC);
}
unset($v);

// ===== REZERVACIJŲ VALDYMAS =====
// ===== VIENOS REZERVACIJOS VEIKSMAI =====
if(isset($_POST['reservation_action'])){
    $raw = $_POST['reservation_action'];

    if(str_starts_with($raw, 'approve_')){
        $reservation_id = intval(substr($raw, 8));
        $action = 'approve';

    } elseif(str_starts_with($raw, 'cancel_')){
        $reservation_id = intval(substr($raw, 7));
        $action = 'cancel';
    }

    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if($reservation){
        if($action==='approve' && $reservation['status']==='pending'){
            $pdo->prepare("UPDATE reservations SET status='approved' WHERE id=?")->execute([$reservation_id]);
            $pdo->prepare("UPDATE vehicles SET is_rented=1 WHERE id=?")->execute([$reservation['vehicle_id']]);
            $_SESSION['message']="Rezervacija patvirtinta!";
        } elseif($action==='cancel'){
            $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([$reservation_id]);

            if($reservation['status']==='approved'){
                $pdo->prepare("UPDATE vehicles SET is_rented=0 WHERE id=?")->execute([$reservation['vehicle_id']]);
            }
            $_SESSION['message']="Rezervacija atšaukta!";
        }
    }

    header("Location: nuomotojas_dashboard.php");
    exit;
}

// ===== MASINIS VEIKSMAI SU CHECKBOX =====
if(isset($_POST['bulk_action']) && !empty($_POST['selected_reservations'])){
    $action = $_POST['bulk_action'];
    $selected = array_map('intval', $_POST['selected_reservations']);

    foreach($selected as $res_id){
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $stmt->execute([$res_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$reservation) continue;

        if($action === 'approve_selected' && $reservation['status'] === 'pending'){
            $pdo->prepare("UPDATE reservations SET status='approved' WHERE id=?")->execute([$res_id]);
            $pdo->prepare("UPDATE vehicles SET is_rented=1 WHERE id=?")->execute([$reservation['vehicle_id']]);
        }

        if($action === 'cancel_selected'){
            $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([$res_id]);
            if($reservation['status'] === 'approved'){
                $pdo->prepare("UPDATE vehicles SET is_rented=0 WHERE id=?")->execute([$reservation['vehicle_id']]);
            }
        }
    }

    $_SESSION['message'] = "Pažymėtos rezervacijos sėkmingai apdorotos!";
    header("Location: nuomotojas_dashboard.php");
    exit;
}



// ===== IŠVALYTI VISAS REZERVACIJAS =====
if(isset($_POST['clear_reservations']) && !empty($_POST['selected_reservations'])){
    $selected = array_map('intval', $_POST['selected_reservations']);
    foreach($selected as $res_id){
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $stmt->execute([$res_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$reservation) continue;

        $pdo->prepare("DELETE FROM reservations WHERE id=?")->execute([$res_id]);
        $pdo->prepare("UPDATE vehicles SET is_rented=0 WHERE id=?")->execute([$reservation['vehicle_id']]);
    }

    $_SESSION['message'] = "Pažymėtos rezervacijos išvalytos!";
    header("Location: nuomotojas_dashboard.php");
    exit;
}


// ===== GAUJAME REZERVACIJAS KIEKVIENAM AUTOMOBILIUI =====
foreach($vehicles as &$v){
    $stmt = $pdo->prepare("SELECT r.*, u.name AS client_name FROM reservations r LEFT JOIN users u ON r.user_id=u.id WHERE r.vehicle_id=? ORDER BY r.reserved_at DESC");
    $stmt->execute([$v['id']]);
    $v['reservations']=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($v);

// ===== TOP 10 NUOMOTOJŲ PAGAL VIDUTINĮ ĮVERTINIMĄ =====
$stmt = $pdo->prepare("
    SELECT u.id, u.name, ROUND(AVG(r.rating),2) AS avg_rating, COUNT(r.id) AS review_count
    FROM users u
    JOIN vehicles v ON v.owner_id = u.id
    JOIN reviews r ON r.vehicle_id = v.id
    GROUP BY u.id
    HAVING review_count>0
    ORDER BY avg_rating DESC
    LIMIT 10
");
$stmt->execute();
$top_owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Nuomotojo Dashboard</title>
<style>
body{font-family:'Segoe UI',Tahoma,sans-serif;background:#f0f4f8;margin:0;padding:20px;color:#333;}
h1,h2,h3,h4{margin:0 0 10px;}
h1{text-align:center;color:#222;}
form.card{background:#fff;padding:20px;margin:20px auto;border-radius:12px;max-width:700px;box-shadow:0 5px 15px rgba(0,0,0,0.1);}
form label{display:block;margin:10px 0 5px;font-weight:600;}
input[type=text],input[type=number],select,textarea{width:100%;padding:10px;margin-bottom:10px;border-radius:8px;border:1px solid #ccc;box-sizing:border-box;}
button{background:#007bff;color:#fff;padding:10px 14px;border:none;border-radius:8px;cursor:pointer;margin-top:6px;}
button:hover{opacity:0.9;}
.vehicles-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:30px;}
.vehicle-card{background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.08);overflow:hidden;transition:transform 0.15s;}
.vehicle-card:hover{transform:translateY(-6px);}
.vehicle-card img{width:100%;height:220px;object-fit:cover;}
.vehicle-content{padding:14px;}
.controls{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
.edit-btn,.delete-btn{padding:8px 12px;border-radius:8px;text-decoration:none;color:#fff;}
.edit-btn{background:#28a745;}
.delete-btn{background:#dc3545;}
.reservation{background:#f8f9fa;padding:10px;border-radius:8px;margin-top:10px;border-left:4px solid #007bff;}
.small{font-size:13px;color:#666;}
.approve{background:#28a745;color:#fff;padding:6px 10px;border:none;border-radius:6px;cursor:pointer;}
.cancel{background:#dc3545;color:#fff;padding:6px 10px;border:none;border-radius:6px;cursor:pointer;}
.clear{background:#6c757d;color:#fff;padding:6px 10px;border:none;border-radius:6px;cursor:pointer;}
.top-owners{max-width:1100px;margin:30px auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;}
.owner-card{background:#fff;padding:12px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,0.08);}
</style>
</head>
<body>

<?php if(isset($_SESSION['message'])): ?>
<div style="max-width:700px;margin:20px auto;padding:12px 20px;background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;border-radius:10px;text-align:center;">
<?= htmlspecialchars($_SESSION['message']) ?>
</div>
<?php unset($_SESSION['message']); endif; ?>

<h1>Nuomojamų transporto priemonių valdymas</h1>
<p style="text-align:center;font-weight:bold;">Sveiki, <?= htmlspecialchars($nuomotojo_vardas) ?>!</p>
   <!-- Atsijungimo mygtukas viršuje dešinėje -->
    <a href="logout.php" style="position:absolute; top:10px; right:10px; background:#dc3545; color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:600; box-shadow:0 4px 12px rgba(220,53,69,0.2);">
        Atsijungti
    </a>

<!-- Sveikinimas + atsijungimo mygtukas -->
<div style="position:relative; max-width:700px; margin:0 auto 15px; padding:15px; background:#fff3cd; border:1px solid #ffeeba; border-radius:10px; text-align:center;">
    <h3 style="margin:0;">Sveiki, <?= htmlspecialchars($nuomotojo_vardas) ?>!</h3>
    <p style="font-weight:bold;">Jūsų bendras uždarbis: <span style="font-size:22px; font-weight:bold; color:#856404;"><?= number_format($earnings, 2) ?> €</span></p>

 
</div>


<!-- TOP 10 nuomotojų -->
<h2 style="text-align:center;margin-top:40px;color:#333;">TOP 10 nuomotojų pagal įvertinimus</h2>
<div class="top-owners">
<?php foreach($top_owners as $owner): ?>
<div class="owner-card">
<h4><?= htmlspecialchars($owner['name']) ?></h4>
<p>Vidutinis įvertinimas: <strong><?= $owner['avg_rating'] ?> ⭐</strong></p>
<p>Atsiliepimų skaičius: <?= $owner['review_count'] ?></p>
</div>
<?php endforeach; ?>
</div>

<!-- Filtras -->
<form method="get" style="max-width:320px;margin:0 auto 20px;">
<label>Filtruoti pagal kuro tipą:</label>
<select name="group_filter" onchange="this.form.submit()">
<option value="">Visi kuro tipai</option>
<?php foreach($fuel_types as $fuel): ?>
<option value="<?= $fuel ?>" <?= ($filter_group == $fuel) ? 'selected' : '' ?>><?= ucfirst($fuel) ?></option>
<?php endforeach; ?>
</select>
</form>

<!-- Pridėti / redaguoti -->
<?php if($edit_vehicle): ?>
<form method="post" enctype="multipart/form-data" class="card">
<h2>Redaguoti transporto priemonę</h2>
<input type="hidden" name="id" value="<?= intval($edit_vehicle['id']) ?>">
<label>Pavadinimas:</label>
<input type="text" name="name" required value="<?= htmlspecialchars($edit_vehicle['name'] ?? '') ?>">
<label>Aprašymas:</label>
<textarea name="description"><?= htmlspecialchars($edit_vehicle['description'] ?? '') ?></textarea>
<label>Kaina per dieną (€):</label>
<input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($edit_vehicle['price_per_day'] ?? 0) ?>">
<label>Pravažiuota km:</label>
<input type="number" name="mileage" required value="<?= htmlspecialchars($edit_vehicle['mileage_km'] ?? 0) ?>">
<label>Kuro tipas:</label>
<select name="grupe" required>
<option value="">Pasirinkite kuro tipą</option>
<?php foreach($fuel_types as $fuel): ?>
<option value="<?= $fuel ?>" <?= ($edit_vehicle['grupe']==$fuel)?'selected':'' ?>><?= ucfirst($fuel) ?></option>
<?php endforeach; ?>
</select>

<label>Esamos nuotraukos:</label><br>
<?php foreach($edit_images as $img): ?>
<div style="display:inline-block;position:relative;margin:6px;">
<img src="<?= htmlspecialchars($img['image_url']) ?>" width="120" style="border:1px solid #ccc;border-radius:6px;">
<a href="?delete_image=<?= intval($img['id']) ?>&edit_id=<?= intval($edit_vehicle['id']) ?>" onclick="return confirm('Ar tikrai norite ištrinti nuotrauką?')" style="position:absolute;top:0;right:0;background:red;color:#fff;padding:2px 6px;border-radius:50%;text-decoration:none;font-weight:bold;">×</a>
</div>
<?php endforeach; ?>

<label>Pridėti naujas nuotraukas:</label>
<input type="file" name="images[]" multiple accept="image/*">
<button type="submit" name="update">Atnaujinti</button>
</form>

<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card">
<h2>Pridėti naują transporto priemonę</h2>
<label>Pavadinimas:</label>
<input type="text" name="name" required>
<label>Aprašymas:</label>
<textarea name="description"></textarea>
<label>Kaina per dieną (€):</label>
<input type="number" step="0.01" name="price" required>
<label>Pravažiuota km:</label>
<input type="number" name="mileage" required>
<label>Kuro tipas:</label>
<select name="grupe" required>
<option value="">Pasirinkite kuro tipą</option>
<?php foreach($fuel_types as $fuel): ?>
<option value="<?= $fuel ?>"><?= ucfirst($fuel) ?></option>
<?php endforeach; ?>
</select>
<label>Nuotraukos:</label>
<input type="file" name="images[]" multiple accept="image/*">
<button type="submit" name="submit">Pridėti</button>
</form>
<?php endif; ?>

<!-- VISI AUTOMOBILIAI SU REZERVACIJOMIS -->
<h2 style="text-align:center;margin-top:40px;">Jūsų įkelti automobiliai</h2>

<form method="post">
<div class="vehicles-container">
<?php foreach($vehicles as $v): ?>
<div class="vehicle-card">
    <?php if(!empty($v['image_url'])): ?>
        <img src="<?= htmlspecialchars($v['image_url']) ?>" alt="auto">
    <?php else: ?>
        <img src="https://via.placeholder.com/400x250?text=Be+nuotraukos" alt="placeholder">
    <?php endif; ?>
    <div class="vehicle-content">
        <h3><?= htmlspecialchars($v['name']) ?></h3>
        <p><?= htmlspecialchars($v['description']) ?></p>
        <p><strong>Kaina:</strong> <?= htmlspecialchars($v['price_per_day']) ?> €/dienai</p>
        <p><strong>Rida:</strong> <?= htmlspecialchars($v['mileage_km']) ?> km</p>
        <p><strong>Kuro tipas:</strong> <?= htmlspecialchars($v['grupe'] ?? 'Nežinomas') ?></p>
        <p><strong>Būsena:</strong> <?= $v['is_rented'] ? 'Užimta 🚗' : 'Laisva ✅' ?></p>

        <div class="controls">
            <a href="?edit_id=<?= intval($v['id']) ?>" class="edit-btn">Redaguoti</a>
            <a href="?delete_id=<?= intval($v['id']) ?>" class="delete-btn" onclick="return confirm('Ar tikrai norite ištrinti šį automobilį?')">Ištrinti</a>
        </div>

<!-- Rezervacijos -->
<?php if(!empty($v['reservations'])): ?>
    <h4>Rezervacijos:</h4>
    <?php foreach($v['reservations'] as $r): ?>
    <div class="reservation" style="display:flex;align-items:flex-start;gap:10px; background:#f8f9fa; padding:8px; border-radius:6px; border-left:4px solid #007bff;">
        <!-- Checkbox visada aktyvus -->
        <input type="checkbox" name="selected_reservations[]" value="<?= intval($r['id']) ?>" style="width:20px;height:20px;cursor:pointer; margin-top:2px;">
        
        <div style="display:flex;flex-direction:column;">
            <strong>Klientas:</strong> <?= htmlspecialchars($r['client_name']) ?><br>
            <strong>Nuo:</strong> <?= htmlspecialchars($r['start_date']) ?> 
            <strong>Iki:</strong> <?= htmlspecialchars($r['end_date']) ?><br>
            <strong>Būsena:</strong> 
            <?php if($r['status']==='pending'): ?>
                <span style="color:#d97706; font-weight:bold;">Laukiama</span>
            <?php elseif($r['status']==='approved'): ?>
                <span style="color:#16a34a; font-weight:bold;">Patvirtinta</span>
            <?php else: ?>
                <span style="color:#dc2626; font-weight:bold;">Atšaukta</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <p class="small">Rezervacijų nėra.</p>
<?php endif; ?>


        <!-- Atsiliepimai -->
        <?php if(!empty($v['reviews'])): ?>
            <h4>Atsiliepimai:</h4>
            <?php foreach($v['reviews'] as $rev): ?>
            <div class="reservation" style="background:#fff9db;">
                <p><strong><?= htmlspecialchars($rev['user_name'] ?? 'Anonimas') ?>:</strong> 
                   <?= htmlspecialchars($rev['comment'] ?? '') ?></p>
                <p>Įvertinimas: ⭐ <?= htmlspecialchars($rev['rating'] ?? '-') ?></p>
                <p class="small"><?= htmlspecialchars($rev['created_at']) ?></p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="small">Atsiliepimų nėra.</p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Masiniai mygtukai po visomis kortelėmis -->
<div style="text-align:center;margin:20px 0;">
    <button type="submit" name="bulk_action" value="approve_selected" class="approve" style="margin:5px 10px;">Patvirtinti pažymėtas</button>
    <button type="submit" name="bulk_action" value="cancel_selected" class="cancel" style="margin:5px 10px;">Atšaukti pažymėtas</button>
    <button type="submit" name="clear_reservations" class="clear" style="margin:5px 10px;" onclick="return confirm('Ar tikrai norite išvalyti VISAS rezervacijas?');">Išvalyti visas rezervacijas</button>
</div>
</form>


</body>
</html>

