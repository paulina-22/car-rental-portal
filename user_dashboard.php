<?php
session_start();
require 'config.php'; // PDO $pdo

// Tik vartotojai
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'vartotojas'){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


// --- Lietuviški statusų pavadinimai ---
$status_translate = [
    'pending'   => 'Laukia patvirtinimo',
    'approved'  => 'Patvirtinta',
    'rejected'  => 'Atmesta',
    'canceled'  => 'Atšaukta',
    'completed' => 'Įvykdyta'
];

$user_name = $_SESSION['user_name'] ?? 'Vartotojas';
$stmt = $pdo->prepare("SELECT birth_date FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

$user_birth_date = null;
if (!empty($u['birth_date'])) {
   $user_birth_date = !empty($u['birth_date']) ? $u['birth_date'] : null;

}




// --- Nauja rezervacija ---
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'], $_POST['start_date'], $_POST['end_date'])){
    $vehicle_id = intval($_POST['vehicle_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if(empty($start_date) || empty($end_date) || $end_date < $start_date){
        $_SESSION['error'] = "Neteisingai pasirinktos datos!";
        header("Location: user_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE vehicle_id = ?
         AND status IN ('pending','approved')
         AND NOT (end_date < ? OR start_date > ?)");
    $stmt->execute([$vehicle_id, $start_date, $end_date]);
    $taken = $stmt->fetchColumn();

    if($taken > 0){
        $_SESSION['error'] = "Pasirinktu laikotarpiu automobilis jau rezervuotas!";
        header("Location: user_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT price_per_day FROM vehicles WHERE id=?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$vehicle){
        $_SESSION['error'] = "Automobilis nerastas!";
        header("Location: user_dashboard.php");
        exit;
    }

    $days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
    $days = max(1, intval($days));
    $total_price = $days * $vehicle['price_per_day'];

    $stmt = $pdo->prepare(
        "INSERT INTO reservations (vehicle_id, user_id, username, start_date, end_date, status, reserved_at, total_price)
         VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?)");
    $stmt->execute([$vehicle_id, $user_id, $user_name, $start_date, $end_date, $total_price]);

    $_SESSION['message'] = "Rezervacija sukurta! Laukia nuomotojo patvirtinimo.";
    header("Location: user_dashboard.php");
    exit;
}

// --- Aktyvios rezervacijos (vartotojo) ---
$stmt = $pdo->prepare(
    "SELECT r.*, v.name AS vehicle_name
     FROM reservations r
     LEFT JOIN vehicles v ON r.vehicle_id=v.id
     WHERE r.user_id=? AND r.status IN ('pending','approved')
     ORDER BY r.reserved_at DESC"
);
$stmt->execute([$user_id]);
$active_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Grupės filtravimas ir paieška ---
$filter_group = $_GET['group_filter'] ?? '';
$filter_owner = $_GET['owner_filter'] ?? ''; // PRIDĖTA
$search = $_GET['search'] ?? '';

$groups = $pdo->query("SELECT DISTINCT grupe FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);
$owners = $pdo->query("SELECT id, name FROM users WHERE role='nuomotojas'")->fetchAll(PDO::FETCH_ASSOC);


// --- Transporto priemonės su nuomotojo vardu ---
// Bazinė užklausa, be filtravimo
$sql = "SELECT v.*, u.name AS owner_name
        FROM vehicles v
        LEFT JOIN users u ON v.owner_id = u.id
        WHERE 1";
$params = [];

// Filtravimo sąlygos
if($filter_group !== ''){
    $sql .= " AND v.grupe=?";
    $params[] = $filter_group;
}

// Jei nori kol kas išjungti filtravimą pagal nuomotoją, palik šitą sąlygą neaktyvią:
// if($filter_owner !== ''){
//     $sql .= " AND v.owner_id=?";
//     $params[] = $filter_owner;
// }

if($search !== ''){
    $sql .= " AND v.name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY v.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);







// --- Nuotraukos, atsiliepimai ir aktyvios rezervacijos prie transporto priemonių ---
foreach($vehicles as &$v){
    $stmt = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY id ASC");
    $stmt->execute([$v['id']]);
    $v['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT r.review_text, r.rating, u.name AS user_name 
         FROM reviews r 
         LEFT JOIN users u ON r.user_id=u.id 
         WHERE r.vehicle_id=? 
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$v['id']]);
    $v['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE vehicle_id=? AND status IN ('pending','approved') ORDER BY start_date ASC");
    $stmt->execute([$v['id']]);
    $v['reservations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=? AND vehicle_id=? AND status='approved'");
    $stmt->execute([$user_id, $v['id']]);
    $v['can_review'] = $stmt->fetchColumn() > 0;
}
unset($v);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Vartotojo skydelis</title>
<style>
/* Pagrindinė stilizacija */
:root{--bg:#f0f4f8;--card:#fff;--muted:#666;--accent:#007bff;--success:#28a745;--danger:#dc3545;--shadow:0 6px 18px rgba(17,24,39,0.08);--radius:12px;}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);margin:0;padding:28px;color:#222;}
.header{text-align:center;max-width:1100px;margin:0 auto 20px;}
.header h1{margin:0;font-size:24px;color:#111;}
.header p{margin:8px 0 0;font-weight:600;color:#333;}
.filter-form{max-width:1100px;margin:18px auto;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
.filter-form .field{flex:1;min-width:200px;}
.filter-form label{display:block;margin-bottom:6px;font-weight:600;color:#333;}
.filter-form select,.filter-form input[type="text"]{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #d6dde6;background:#fff;font-size:14px;}
.active-reservations{max-width:1100px;margin:10px auto 20px;background:#e9f7ef;border-left:6px solid var(--success);padding:14px 18px;border-radius:10px;color:#155724;box-shadow:var(--shadow);} 
.vehicles{max-width:1100px;margin:14px auto 60px;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
.vehicle-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;transition:transform .15s ease,box-shadow .15s ease;display:flex;flex-direction:column;}
.vehicle-card:hover{transform:translateY(-6px);}
.vehicle-card .img-wrap{width:100%;height:220px;background:#f6f8fa;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.vehicle-card img.main-img{width:100%;height:100%;object-fit:cover;}
.vehicle-content{padding:14px 16px 18px;flex:1;display:flex;flex-direction:column;}
.vehicle-content h3{margin:0 0 8px;font-size:18px;color:#111;}
.vehicle-content p{margin:6px 0;color:var(--muted);font-size:14px;}
.vehicle-content .meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;color:#555;font-size:13px;}
.button-row{display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap;}
button.primary{background:var(--accent);color:#fff;border:none;padding:10px 12px;border-radius:8px;cursor:pointer;box-shadow:0 6px 14px rgba(0,123,255,0.12);} 
button.disabled{background:#9fb1d6;color:#fff;border:none;padding:10px 12px;border-radius:8px;cursor:not-allowed;}
.badge{display:inline-block;padding:6px 8px;border-radius:8px;background:#f1f5f9;color:#344;font-weight:600;font-size:13px;}
.thumbnails{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;}
.thumbnails img.thumb{width:66px;height:46px;object-fit:cover;border-radius:8px;border:2px solid #edf2f7;cursor:pointer;transition:all .12s ease;}
.thumbnails img.thumb:hover{transform:translateY(-3px);border-color:var(--accent);}
.reviews{margin-top:12px;padding-top:8px;border-top:1px solid #f0f2f5;}
.reviews h4{margin:8px 0;color:#222;}
.reviews p{margin:6px 0;color:#444;font-size:14px;}
#rentForm{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10000;background:var(--card);padding:20px;border-radius:12px;box-shadow:0 18px 50px rgba(2,6,23,0.2);display:none;width:420px;}
#rentForm h3{margin:0 0 10px;}
#rentForm input[type=date]{width:48%;padding:10px;border-radius:8px;border:1px solid #d6dde6;margin-bottom:10px;}
#rentForm .date-row{display:flex;justify-content:space-between;gap:6px;align-items:center;}
#rentForm .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px;}
#rentForm .actions button{padding:8px 12px;border-radius:8px;border:none;cursor:pointer;}
#rentCalendar{margin-top:8px;border-radius:8px;padding:8px;background:#f8fafc;border:1px solid #e6edf3;}
.cal-row{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
.cal-cell{padding:8px;text-align:center;border-radius:6px;cursor:pointer;background:#fff;border:1px solid #eef2f6;font-size:13px;}
.cal-cell.disabled{background:#f3f4f6;color:#9aa4b2;cursor:not-allowed;border-style:dashed;}
.cal-cell.taken{background:#fde2e2;color:#8b1a1a;border:1px solid #f5c2c2;}
.cal-cell.selected{background:#dbeafe;border:1px solid #93c5fd;}
.top-owners{max-width:1100px;margin:30px auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
.owner-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;transition:transform .15s;}
.owner-card:hover{transform:translateY(-6px);}
.owner-card h4{margin:0 0 8px;font-size:16px;color:#111;}
.owner-card p{margin:4px 0;color:#555;font-size:14px;}
.message{max-width:1100px;margin:10px auto 20px;padding:14px 18px;border-radius:10px;box-shadow:var(--shadow);} 
</style>
</head>
<body>

<div class="header">
    <h1>
    Sveiki atvykę, 
    <?= htmlspecialchars($user_name) ?>
    <?php if ($user_birth_date): ?>
        (<?= $user_birth_date ?> m.)
    <?php endif; ?>
    !
</h1>

    <p>Raskite ir rezervuokite transporto priemonę — patogu ir greita.</p>
	
	 <!-- Atsijungimo mygtukas viršuje dešinėje -->
    <a href="logout.php" style="position:absolute; top:10px; right:10px; background:#dc3545; color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:600; box-shadow:0 4px 12px rgba(220,53,69,0.2);">
        Atsijungti
    </a>
</div>


<div>
<?php /* 
	<form method="post">
	
	<label>Gimimo Metai:</label>
<input type="date" name="birth_date" required>
<button type="submit" name="save_birth">Pridėti</button>
</form>

<?php if(isset($_SESSION['message'])): ?>
<div class="message" style="border-left:6px solid var(--success); background:#e9f7ef; color:#155724;">
    <?= htmlspecialchars($_SESSION['message']) ?>
</div>







<?php unset($_SESSION['message']); endif; ?>

*/ ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="message" style="border-left:6px solid var(--danger); background:#f8d7da; color:#842029;">
    <?= htmlspecialchars($_SESSION['error']) ?>
</div>
<?php unset($_SESSION['error']); endif; ?>

<?php if(!empty($active_reservations)): ?>
<div class="active-reservations">
    <h2>Jūsų aktyvios rezervacijos</h2>
    <?php foreach($active_reservations as $r):
        $days = (strtotime($r['end_date']) - strtotime($r['start_date'])) / (60*60*24) + 1;
    ?>
        <p>
            <strong><?= htmlspecialchars($r['vehicle_name']) ?></strong>
            &nbsp;|&nbsp; <?= intval($days) ?> dienų
            &nbsp;|&nbsp; <?= htmlspecialchars($r['total_price']) ?> €
            &nbsp;|&nbsp; <b>Statusas:</b> <?= htmlspecialchars($status_translate[$r['status']] ?? $r['status']) ?>

        </p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" class="filter-form" aria-label="Filtras ir paieška">
    <div class="field">
        <label>Filtruoti pagal grupę:</label>
        <select name="group_filter" onchange="this.form.submit()">
            <option value="">Visos grupės</option>
            <?php foreach($groups as $g): ?>
                <option value="<?= htmlspecialchars($g) ?>" <?= ($filter_group==$g)?'selected':'' ?>><?= htmlspecialchars(ucfirst($g)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
	
	
	
	
	
	
	
	
<?php /*
<div class="field">
    <label>Filtruoti pagal nuomotoją:</label>
    <select name="owner_filter" onchange="this.form.submit()">
        <option value="">Visi nuomotojai</option>
        <?php foreach($owners as $o): ?>
            <option value="<?= $o['id'] ?>" <?= ($filter_owner==$o['id'])?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
*/ ?>





    <div class="field">
        <label>Paieška pagal pavadinimą:</label>
        <input type="text" name="search" placeholder="Įveskite pavadinimą..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div style="min-width:120px;">
        <button type="submit" class="primary" style="width:100%;">Ieškoti</button>
    </div>
</form>

<h2 style="text-align:center;margin-top:6px;color:#333;">Pasirinkite automobilį nuomai</h2>
<div class="vehicles">
<?php foreach($vehicles as $v):
    $first_img = $v['images'][0]['image_url'] ?? 'https://via.placeholder.com/400x250?text=No+Image';
    $res_json = json_encode(array_map(function($r){
        return ['start'=>$r['start_date'],'end'=>$r['end_date']];
    }, $v['reservations']));
?>
<div class="vehicle-card" id="vehicle-<?= $v['id'] ?>">
    <div class="img-wrap"><img class="main-img" src="<?= htmlspecialchars($first_img) ?>" alt="<?= htmlspecialchars($v['name']) ?>"></div>
    <div class="vehicle-content">
        <h3><?= htmlspecialchars($v['name']) ?></h3>
        <p><?= htmlspecialchars($v['description']) ?></p>
        <div class="meta">
            <div><span class="badge">Kaina: <?= htmlspecialchars($v['price_per_day']) ?> € / dienai</span></div>
            <div>Rida: <?= htmlspecialchars($v['mileage_km']) ?> km</div>
            <div>Grupė: <strong><?= htmlspecialchars(ucfirst($v['grupe'])) ?></strong></div>
            <?php if(!empty($v['owner_name'])): ?>
            <div>Nuomotojas: <strong><?= htmlspecialchars($v['owner_name']) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="button-row">
            <button class="primary" 
                onclick='openRentForm(<?= $v['id'] ?>, <?= json_encode($v['name']) ?>, <?= $v['price_per_day'] ?>, <?= $res_json ?>)'>
                Nuomotis
            </button>
        </div>
        <?php if(!empty($v['images'])): ?>
        <div class="thumbnails">
            <?php foreach($v['images'] as $img): ?>
                <img class="thumb" src="<?= htmlspecialchars($img['image_url']) ?>" alt="" onclick="changeMainImage(this)">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="reviews">
            <h4>Atsiliepimai</h4>
            <?php if(!empty($v['reviews'])): ?>
                <?php foreach($v['reviews'] as $rev): ?>
                    <p><strong><?= htmlspecialchars($rev['user_name']) ?>:</strong> <?= htmlspecialchars($rev['review_text']) ?><?php if(isset($rev['rating'])): ?> — <?= intval($rev['rating']) ?> ⭐<?php endif; ?></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p><em>Dar nėra atsiliepimų.</em></p>
            <?php endif; ?>
            <?php if($v['can_review']): ?>
            <form method="post" action="submit_review.php" style="margin-top:8px;">
                <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                <textarea name="review_text" rows="2" required placeholder="Palikite atsiliepimą šiai mašinai" style="width:100%;padding:8px;border-radius:8px;border:1px solid #e6edf3;"></textarea>
                <label style="display:block;margin-top:6px;">Vertinimas:</label>
                <select name="rating" required style="padding:8px;border-radius:8px;border:1px solid #e6edf3;width:100%;margin-top:6px;">
                    <option value="">Pasirinkite</option>
                    <?php for($i=1;$i<=5;$i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                    <?php endfor; ?>
                </select>
                <button type="submit" style="margin-top:8px;background:var(--accent);color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;">Siųsti atsiliepimą</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div id="rentForm" role="dialog" aria-modal="true" aria-hidden="true">
    <h3>Rezervacija: <span id="carName"></span></h3>
    <form method="post">
        <input type="hidden" name="vehicle_id" id="vehicle_id">
        <div class="date-row">
            <input type="date" name="start_date" id="start_date" required>
            <input type="date" name="end_date" id="end_date" required>
        </div>
        <div id="rentCalendar" aria-hidden="true">
            <div id="calMonthYear" style="text-align:center;font-weight:700;margin-bottom:8px;"></div>
            <div class="cal-row" id="calDaysHeader"></div>
            <div id="calGrid"></div>
            <p style="font-size:12px;color:#666;margin-top:8px;">Spustelkite datą kalendoriuje, kad nustatytumėte <em>Nuo</em> ir <em>Iki</em>. Užimtos datos pažymėtos raudonai.</p>
        </div>
        <p style="margin-top:8px;">Bendra kaina: <span id="totalPrice">0</span> €</p>
        <div class="actions">
            <button type="button" class="cancel" onclick="closeForm()">Atšaukti</button>
            <button type="submit" class="confirm">Patvirtinti rezervaciją</button>
        </div>
    </form>
</div>

<script>
function fmtDate(d){ const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; }

let currentReservations=[], selectedPrice=0, lastClicked='start';

function openRentForm(id, name, price, reservations){
    currentReservations = reservations || [];
    selectedPrice = parseFloat(price)||0;

    document.getElementById('rentForm').style.display='block';
    document.getElementById('rentForm').setAttribute('aria-hidden','false');
    document.getElementById('carName').innerText=name;
    document.getElementById('vehicle_id').value=id;

    const today = new Date(), isoToday=fmtDate(today);
    document.getElementById('start_date').value = isoToday;
    document.getElementById('start_date').min = isoToday;
    document.getElementById('end_date').value = isoToday;
    document.getElementById('end_date').min = isoToday;

    buildCalendar(today.getFullYear(), today.getMonth());
    updatePrice();
}

function closeForm(){
    document.getElementById('rentForm').style.display='none';
    document.getElementById('rentForm').setAttribute('aria-hidden','true');
    lastClicked='start';
}

function updatePrice(){
    const s=document.getElementById('start_date').value, e=document.getElementById('end_date').value;
    if(!s||!e){ document.getElementById('totalPrice').innerText='0'; return; }
    const start=new Date(s), end=new Date(e);
    if(end<start){ document.getElementById('totalPrice').innerText='0'; return; }
    const days=Math.floor((end-start)/(1000*60*60*24))+1;
    document.getElementById('totalPrice').innerText=(days*selectedPrice).toFixed(2);
}

function buildCalendar(year, month){
    const monthNames=['Sausis','Vasaris','Kovas','Balandis','Gegužė','Birželis','Liepa','Rugpjūtis','Rugsėjis','Spalis','Lapkritis','Gruodis'];
    document.getElementById('calMonthYear').innerText=monthNames[month]+' '+year;

    const daysHeader=['Pr','An','Tr','Kt','Pn','Št','Sk'];
    const headerEl=document.getElementById('calDaysHeader');
    headerEl.innerHTML='';
    daysHeader.forEach(dh=>{ const div=document.createElement('div'); div.className='cal-cell'; div.style.fontWeight='700'; div.style.background='transparent'; div.style.border='none'; div.innerText=dh; headerEl.appendChild(div); });

    const firstDay=new Date(year, month, 1), startWeekDay=(firstDay.getDay()+6)%7, daysInMonth=new Date(year, month+1, 0).getDate();
    const grid=document.getElementById('calGrid'); grid.innerHTML=''; grid.className='cal-row';

    for(let i=0;i<startWeekDay;i++){ const cell=document.createElement('div'); cell.className='cal-cell'; cell.style.visibility='hidden'; grid.appendChild(cell); }

    for(let d=1;d<=daysInMonth;d++){
        const cell=document.createElement('div'); cell.className='cal-cell';
        const thisDate=new Date(year, month, d), thisISO=fmtDate(thisDate);
        cell.innerText=d;

        let taken=false;
        for(const r of currentReservations){
            if(r.start&&r.end){ if(thisISO>=r.start&&thisISO<=r.end){ taken=true; break; } }
        }
        if(taken){ cell.classList.add('taken'); cell.classList.add('disabled'); }

        const todayISO=fmtDate(new Date());
        if(thisISO<todayISO&&!cell.classList.contains('taken')){ cell.classList.add('disabled'); }

        cell.addEventListener('click', function(){
            if(cell.classList.contains('disabled')||cell.classList.contains('taken')) return;
            if(lastClicked==='start'){ document.getElementById('start_date').value=thisISO; lastClicked='end'; }
            else{ document.getElementById('end_date').value=thisISO; lastClicked='start'; }
            highlightSelected(year, month); updatePrice();
        });

        grid.appendChild(cell);
    }
    highlightSelected(year, month);
}

function highlightSelected(year, month){
    const s=document.getElementById('start_date').value, e=document.getElementById('end_date').value;
    const cells=document.querySelectorAll('#calGrid .cal-cell'); cells.forEach(c=>c.classList.remove('selected'));
    if(!s||!e) return;
    const start=new Date(s), end=new Date(e);
    if(end<start) return;
    for(const c of cells){
        if(c.innerText==='') continue;
        const d=parseInt(c.innerText,10), curDate=new Date(year, month, d), iso=fmtDate(curDate);
        if(iso>=s&&iso<=e&&!c.classList.contains('taken')) c.classList.add('selected');
    }
}

document.getElementById('start_date').addEventListener('change', function(){
    const dt=new Date(this.value); buildCalendar(dt.getFullYear(), dt.getMonth()); updatePrice();
});
document.getElementById('end_date').addEventListener('change', function(){
    const dt=new Date(this.value); buildCalendar(dt.getFullYear(), dt.getMonth()); updatePrice();
});

function changeMainImage(img){ const card=img.closest('.vehicle-card'), mainImg=card.querySelector('.main-img'); mainImg.src=img.src; }
</script>
</body>
</html>
