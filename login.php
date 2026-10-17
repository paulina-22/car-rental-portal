<?php
session_start();
require_once "config.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Naudojame password_verify() vietoje paprasto palyginimo
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        // Prisiminti el. paštą
        if(isset($_POST['remember'])){
            setcookie('remember_email', $email, time() + (30*24*60*60), "/"); // galioja 30 dienų
        } else {
            setcookie('remember_email', '', time() - 3600, "/"); // ištrinti cookie, jei nepažymėta
        }

        // Nukreipimas pagal rolę
        if ($user['role'] === 'nuomotojas') {
            header("Location: nuomotojas_dashboard.php");
        } elseif ($user['role'] === 'admin') {
            header("Location: kontrolierius_dashboard.php");
        } elseif ($user['role'] === 'vartotojas') {
            header("Location: user_dashboard.php");
        } else {
            $message = "Nepažįstama vartotojo rolė.";
            exit;
        }
        exit;
    } else {
        $message = "❌ Neteisingas el. paštas arba slaptažodis.";
    }
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Trumpalaikės nuomos skelbimų portalas</title>
<style>
    body { 
        font-family: 'Segoe UI', sans-serif; 
        background: #eef2f5; 
        color: #333; 
        text-align: center; 
        margin-top: 60px; 
    }
    h1 {
        font-size: 28px;
        margin-bottom: 5px;
        color: #2c3e50;
    }
    h2 {
        font-size: 18px;
        color: #555;
        margin-bottom: 40px;
    }
    form { 
        display: inline-block; 
        background: #fff; 
        padding: 30px; 
        border-radius: 10px; 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
    }
    input { 
        width: 250px; 
        padding: 10px; 
        margin: 8px 0; 
        border: 1px solid #ccc; 
        border-radius: 5px; 
    }
    button { 
        background: #3498db; 
        color: #fff; 
        border: none; 
        padding: 10px 20px; 
        border-radius: 5px; 
        cursor: pointer; 
    }
    button:hover { 
        background: #1abc9c; 
    }
    p { 
        color: #777; 
    }
    label { 
        font-size: 14px; 
    }
</style>
</head>
<body>

<h1>Trumpalaikės nuomos skelbimų portalas</h1>
<h2>Paulina Velickaitė</h2>

<h3>Prisijungimas</h3>
<?php if($message) echo "<p style='color:red;'>$message</p>"; ?>

<form method="post">
    <input type="email" name="email" placeholder="El. paštas" required 
           value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>"><br>
    <input type="password" name="password" placeholder="Slaptažodis" required><br>
    <label>
        <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>
        Prisiminti mano el. paštą
    </label><br>
    <button type="submit">Prisijungti</button>
</form>

<p><a href="register.php">Neturite paskyros? Registruokitės</a></p>

</body>
</html>
