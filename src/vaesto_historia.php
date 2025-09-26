<?php
// --- Väestön historian backend ---
// Palauttaa JSON-muodossa väestön historian Kanta-Hämeelle ja Päijät-Hämeelle
header('Content-Type: application/json; charset=utf-8');
require_once('db.php'); // Use shared DB connection settings

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Haetaan maakunta parametri (1 = Päijät-Häme, 2 = Kanta-Häme, default = Kanta-Häme)
    $region = isset($_GET['region']) ? intval($_GET['region']) : 2;
    $region_map = [1 => 'Päijät-Häme', 2 => 'Kanta-Häme'];
    $region_name = isset($region_map[$region]) ? $region_map[$region] : 'Kanta-Häme';

    // taulu Asukasmaara, kentät: Tilastovuosi, Kunta_ID, Maara
    $sql = "SELECT Tilastovuosi, Maara FROM Asukasmaara WHERE Kunta_ID = :region and Sukupuoli_ID = 3 and ika = 'yhteensä' ORDER BY Tilastovuosi ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['region' => $region]);
    $labels = [];
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[] = $row['Tilastovuosi'];
        $data[] = intval($row['Maara']);
    }
    // Palautetaan tiedot Chart.js:lle
    echo json_encode([
        "labels" => $labels,
        "data" => $data,
        "region" => $region_name
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
