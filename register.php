<?php
session_start();
require_once "config.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // gauta reikšmė iš pasirinkimo laukelio (vartotojas arba nuomotojas)

    // Saugos patikrinimas – tik leidžiamos rolės
    if (!in_array($role, ['vartotojas', 'nuomotojas'])) {
        $message = "Neteisinga rolė.";
    } else {
        // Užšifruojame slaptažodį
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Patikriname ar el. paštas jau egzistuoja
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $message = "⚠️ Toks el. paštas jau egzistuoja!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $email, $hashedPassword, $role]);
                $message = "✅ Sėkmingai užsiregistravote kaip $role! Galite prisijungti.";
            } catch (PDOException $e) {
                $message = "Klaida: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Registracija</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #eef2f5; color: #333; text-align: center; margin-top: 60px; }
    form { display: inline-block; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    input, select { width: 250px; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 5px; }
    button { background: #3498db; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    button:hover { background: #1abc9c; }
    p { color: #777; }
</style>
</head>
<body>
<h1>Registracija</h1>

<?php if($message) echo "<p style='color:green;'>$message</p>"; ?>

<form method="post">
    <input type="text" name="name" placeholder="Vardas" required><br>
    <input type="email" name="email" placeholder="El. paštas" required><br>
    <input type="password" name="password" placeholder="Slaptažodis" required><br>

    <label for="role">Pasirinkite rolę:</label><br>
    <select name="role" id="role" required>
        <option value="vartotojas">Vartotojas</option>
        <option value="nuomotojas">Nuomotojas</option>
    </select><br>

    <button type="submit">Registruotis</button>
</form>

<p><a href="login.php">Grįžti į prisijungimą</a></p>
</body>
</html>

