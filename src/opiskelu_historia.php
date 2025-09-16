<?php
// --- Opiskelu historian backend ---
// Palauttaa JSON-muodossa opiskelun historian vuosittain

$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Tietokantayhteys epäonnistui"]);
    exit;
}
$conn->set_charset("utf8");

// Haetaan pyydetty kenttä
$allowed_fields = ["perusjalk", "korkeajalk", "toisenjalk"];
$field = isset($_GET['field']) && in_array($_GET['field'], $allowed_fields) ? $_GET['field'] : "toisenjalk";

$sql = "SELECT vuosi, perusjalk, korkeajalk, toisenjalk FROM Opiskelu ORDER BY vuosi DESC LIMIT 10";
$res = $conn->query($sql);
$labels = [];
$data = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['vuosi'];
        $data[] = floatval($row[$field]);
    }
}
$conn->close();
// Palautetaan käännetyssä järjestyksessä (vanhin ensin)
echo json_encode([
    "labels" => array_reverse($labels),
    "data" => array_reverse($data)
], JSON_UNESCAPED_UNICODE);
?>