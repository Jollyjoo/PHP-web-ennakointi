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
        $ikas = ['0','1','2','3','4'];
        $sql_latest = "SELECT MAX(Tilastovuosi) as maxyear FROM Asukasmaara WHERE Kunta_ID = ? AND Sukupuoli_ID = 3 AND ika IN (?,?,?,?,?)";
        $stmt_latest = $pdo->prepare($sql_latest);
        $stmt_latest->execute(array_merge([$region], $ikas));
        $row_latest = $stmt_latest->fetch(PDO::FETCH_ASSOC);
        $latestYear = $row_latest && $row_latest['maxyear'] ? intval($row_latest['maxyear']) : 2024;

        // Get values for latestYear, latestYear-10, latestYear-20
        $years = [$latestYear, $latestYear-10, $latestYear-20];
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
        $value = $values[$latestYear];
        $value10 = $values[$latestYear-10];
        $value20 = $values[$latestYear-20];
        $change10 = $value - $value10;
        $pct10 = $value10 ? ($change10 / $value10 * 100) : 0;
        $change20 = $value - $value20;
        $pct20 = $value20 ? ($change20 / $value20 * 100) : 0;
        echo json_encode([
            "year" => $latestYear,
            "value" => $value,
            "change10" => $change10,
            "pct10" => $pct10,
            "change20" => $change20,
            "pct20" => $pct20
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Jos alle5history=1, palauta alle 5-vuotiaiden määrä vuosittain (sparkline)
    if (isset($_GET['alle5history']) && $_GET['alle5history'] == '1') {
        $ikas = ['0','1','2','3','4'];
        $sql_years = "SELECT DISTINCT Tilastovuosi FROM Asukasmaara WHERE Kunta_ID = ? AND Sukupuoli_ID = 3 AND ika IN (?,?,?,?,?) ORDER BY Tilastovuosi ASC";
        $stmt_years = $pdo->prepare($sql_years);
        $stmt_years->execute(array_merge([$region], $ikas));
        $years = [];
        while ($row = $stmt_years->fetch(PDO::FETCH_ASSOC)) {
            $years[] = intval($row['Tilastovuosi']);
        }
        $labels = [];
        $data = [];
        foreach ($years as $year) {
            $placeholders = implode(',', array_fill(0, count($ikas), '?'));
            $sql = "SELECT SUM(Maara) as summa FROM Asukasmaara WHERE Kunta_ID = ? AND Sukupuoli_ID = 3 AND ika IN ($placeholders) AND Tilastovuosi = ?";
            $params = array_merge([$region], $ikas, [$year]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $labels[] = $year;
            $data[] = $row && $row['summa'] !== null ? intval($row['summa']) : 0;
        }
        echo json_encode([
            "labels" => $labels,
            "data" => $data
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
