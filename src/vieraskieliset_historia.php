<?php
// vieraskieliset_historia.php
// Palauttaa JSON: tilastovuosi (vuosi), yhteensa, ulkomaiset, vieraskieliset - embedded line chartia varten
header('Content-Type: application/json; charset=utf-8');
require_once "db.php";


$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : 'SSS'; // default: koko maa/alue

// Jos pyydetään stat_code-listaa (dropdownia varten)
if (isset($_GET['list_stat_codes'])) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
        $stmt = $pdo->query("SELECT DISTINCT stat_code FROM Vieraskieliset WHERE stat_code IS NOT NULL AND stat_code != '' ORDER BY stat_code ASC");
        $codes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codes[] = $row['stat_code'];
        }
        echo json_encode(['stat_codes' => $codes], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Tietokantavirhe', 'details' => $e->getMessage()]);
        exit;
    }
}

try {
    // Luo PDO-yhteys (sama kuin muissa skripteissä)
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    // Haetaan kaikki vuodet, oletuksena stat_code='SSS' (koko alue)
    $stmt = $pdo->prepare("SELECT sektori, YEAR(Tilastovuosi) as vuosi, 
        SUM(Yhteensa) as yhteensa, 
        SUM(Ulkomaiset) as ulkomaiset, 
        SUM(Vieraskieliset) as vieraskieliset
        FROM Vieraskieliset
        WHERE stat_code = :stat_code
        GROUP BY sektori, vuosi
        ORDER BY sektori ASC, vuosi ASC");
    $stmt->execute(['stat_code' => $stat_code]);
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sektori = $row['sektori'] ?: 'Muu';
        if (!isset($data[$sektori])) {
            $data[$sektori] = [
                'years' => [],
                'yhteensa' => [],
                'ulkomaiset' => [],
                'vieraskieliset' => []
            ];
        }
        $data[$sektori]['years'][] = $row['vuosi'];
        $data[$sektori]['yhteensa'][] = (int)$row['yhteensa'];
        $data[$sektori]['ulkomaiset'][] = (int)$row['ulkomaiset'];
        $data[$sektori]['vieraskieliset'][] = (int)$row['vieraskieliset'];
    }
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe', 'details' => $e->getMessage()]);
}