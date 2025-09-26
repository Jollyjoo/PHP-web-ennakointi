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

    // Jos alle5=1, palauta alle 5-vuotiaiden määrä ja muutos
    if (isset($_GET['alle5']) && $_GET['alle5'] == '1') {
        $years = [2024, 2013, 1972];
        $ikas = ['0','1','2','3','4'];
        $values = [];
        foreach ($years as $year) {
            $placeholders = implode(',', array_fill(0, count($ikas), '?'));
            $sql = "SELECT SUM(Maara) as summa FROM Asukasmaara WHERE Kunta_ID = ? AND Sukupuoli_ID = 3 AND ika IN ($placeholders) AND Tilastovuosi = ?";
            $params = array_merge([$region], $ikas, [$year]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $values[$year] = $row && $row['summa'] !== null ? intval($row['summa']) : 0;
        }
        $latestYear = $years[0];
        $value = $values[$latestYear];
        $change2013 = $value - $values[2013];
        $pct2013 = $values[2013] ? ($change2013 / $values[2013] * 100) : 0;
        $change1972 = $value - $values[1972];
        $pct1972 = $values[1972] ? ($change1972 / $values[1972] * 100) : 0;
        echo json_encode([
            "year" => $latestYear,
            "value" => $value,
            "change2013" => $change2013,
            "pct2013" => $pct2013,
            "change1972" => $change1972,
            "pct1972" => $pct1972
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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
