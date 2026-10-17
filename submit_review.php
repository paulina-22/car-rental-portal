<?php
session_start();
require 'config.php'; // PDO $pdo

// Tik vartotojai
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'vartotojas'){
    header("Location: login.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'], $_POST['review_text'], $_POST['rating'])){
    $user_id = $_SESSION['user_id'];
    $vehicle_id = intval($_POST['vehicle_id']);
    $review_text = trim($_POST['review_text']);
    $rating = intval($_POST['rating']);

    if(empty($review_text)){
        $_SESSION['error'] = "Atsiliepimo tekstas negali būti tuščias.";
        header("Location: user_dashboard.php");
        exit;
    }

    // Gauname owner_id tiesiai iš vehicles lentelės
    $stmt = $pdo->prepare("SELECT owner_id FROM vehicles WHERE id=?");
    $stmt->execute([$vehicle_id]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$owner){
        $_SESSION['error'] = "Transporto priemonė neegzistuoja.";
        header("Location: user_dashboard.php");
        exit;
    }
    $owner_id = $owner['owner_id'];

    // Patikriname, ar vartotojas turi bent vieną approved rezervaciją šiai mašinai
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE user_id=? AND vehicle_id=? AND status='approved'
    ");
    $stmt->execute([$user_id, $vehicle_id]);
    if($stmt->fetchColumn() == 0){
        $_SESSION['error'] = "Negalite palikti atsiliepimo šiai transporto priemonei, nes neturite patvirtintos rezervacijos.";
        header("Location: user_dashboard.php");
        exit;
    }

    // Įrašome atsiliepimą kartu su įvertinimu
    $stmt = $pdo->prepare("
        INSERT INTO reviews (user_id, owner_id, vehicle_id, review_text, rating, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $owner_id, $vehicle_id, $review_text, $rating]);

    // ✅ Sėkmės pranešimas
    $_SESSION['message'] = "Atsiliepimas sėkmingai paliktas!";

    header("Location: user_dashboard.php");
    exit;
}
?>
