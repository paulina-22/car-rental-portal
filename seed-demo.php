<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($pdo)) { require __DIR__ . '/config.php'; }
// Deliberately refuse to merge demonstration records into an existing dataset.
foreach (['users','vehicle_groups','vehicles','vehicle_images','reservations','reviews'] as $table) {
    if ($pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() > 0) {
        throw new RuntimeException('Demo seed requires empty tables. No records were inserted.');
    }
}
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO users (name,email,password,role,birth_date) VALUES (?,?,?,?,?)');
    $hash = password_hash('Demo2026!', PASSWORD_DEFAULT);
    foreach ([
        ['Demo klientas','customer@example.test','vartotojas','1998-05-12'],
        ['Demo nuomotojas','owner@example.test','nuomotojas',null],
        ['Demo kontrolierius','admin@example.test','admin',null]
    ] as $user) { $insert->execute([$user[0],$user[1],$hash,$user[2],$user[3]]); }
    $pdo->exec("INSERT INTO vehicle_groups (group_name) VALUES ('benzinas'),('dyzelis'),('elektra')");
    $insert = $pdo->prepare('INSERT INTO vehicles (owner_id,name,description,price_per_day,mileage_km,grupe,group_id,image_url) VALUES (2,?,?,?,?,?,?,?)');
    foreach ([
        ['Toyota Corolla','Demonstracinis miesto automobilis.',35,54000,'benzinas',1],
        ['Volkswagen Golf','Demonstracinis automobilis ilgesnei kelionei.',40,82000,'dyzelis',2],
        ['Nissan Leaf','Demonstracinis elektrinis automobilis.',45,36000,'elektra',3]
    ] as $car) { $insert->execute(array_merge($car,['demo-car.svg'])); }
    $pdo->exec("INSERT INTO vehicle_images (vehicle_id,image_url) VALUES (1,'demo-car.svg'),(2,'demo-car.svg'),(3,'demo-car.svg')");
    $pdo->exec("INSERT INTO reservations (vehicle_id,user_id,username,start_date,end_date,status,total_price) VALUES
        (1,1,'Demo klientas',DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_SUB(CURDATE(),INTERVAL 8 DAY),'approved',105.00),
        (2,1,'Demo klientas',DATE_ADD(CURDATE(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 9 DAY),'pending',120.00)");
    $pdo->exec("INSERT INTO reviews (user_id,owner_id,vehicle_id,review_text,rating) VALUES (1,2,1,'Demonstracinis atsiliepimas: patogus automobilis ir sklandi rezervacija.',5)");
    $pdo->commit();
    echo "Fictional demo data inserted. Demo password: Demo2026!\n";
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
