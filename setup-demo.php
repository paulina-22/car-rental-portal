<?php
// CLI only: this installer must never be accessible through a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME') ?: 'nuomosportalas_demo';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    fwrite(STDERR, "Invalid database name.\n"); exit(1);
}
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4",
        getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');
    $check->execute([$database]);
    if ($check->fetchColumn() > 0) {
        throw new RuntimeException('Database already contains tables. Nothing was changed. Choose an empty DB_NAME.');
    }
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$database`");
    $pdo->exec(file_get_contents(__DIR__ . '/database.sql'));
    require __DIR__ . '/seed-demo.php';
    echo "Database ready: $database. Open login.php in your local web server.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
