<?php
// vieraskieliset_historia.php
// Palauttaa JSON: tilastovuosi (vuosi), yhteensa, ulkomaiset, vieraskieliset - embedded line chartia varten
header('Content-Type: application/json; charset=utf-8');
require_once "db.php";

$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : 'SSS'; // default: koko maa/alue

try {
    // Luo PDO-yhteys (sama kuin muissa skripteissä)
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    // Haetaan kaikki vuodet, oletuksena stat_code='SSS' (koko alue)
    $stmt = $pdo->prepare("SELECT YEAR(Tilastovuosi) as vuosi, 
        SUM(Yhteensa) as yhteensa, 
        SUM(Ulkomaiset) as ulkomaiset, 
        SUM(Vieraskieliset) as vieraskieliset
        FROM Vieraskieliset
        WHERE stat_code = :stat_code
        GROUP BY vuosi
        ORDER BY vuosi ASC");
    $stmt->execute(['stat_code' => $stat_code]);
    $years = [];
    $yhteensa = [];
    $ulkomaiset = [];
    $vieraskieliset = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $years[] = $row['vuosi'];
        $yhteensa[] = (int)$row['yhteensa'];
        $ulkomaiset[] = (int)$row['ulkomaiset'];
        $vieraskieliset[] = (int)$row['vieraskieliset'];
    }
    echo json_encode([
        'years' => $years,
        'yhteensa' => $yhteensa,
        'ulkomaiset' => $ulkomaiset,
        'vieraskieliset' => $vieraskieliset
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe', 'details' => $e->getMessage()]);
}