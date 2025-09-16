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

// Haetaan viimeiset 10 vuotta
$sql = "SELECT vuosi, perusjalk, korkeajalk, toisenjalk FROM Opiskelu ORDER BY vuosi DESC LIMIT 10";
$res = $conn->query($sql);
$labels = [];
$data = [
    "perusjalk" => [],
    "korkeajalk" => [],
    "toisenjalk" => []
];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['vuosi'];
        $data["perusjalk"][] = floatval($row["perusjalk"]);
        $data["korkeajalk"][] = floatval($row["korkeajalk"]);
        $data["toisenjalk"][] = floatval($row["toisenjalk"]);
    }
}
$conn->close();
// Palautetaan käännetyssä järjestyksessä (vanhin ensin)
echo json_encode([
    "labels" => array_reverse($labels),
    "data" => [
        "perusjalk" => array_reverse($data["perusjalk"]),
        "korkeajalk" => array_reverse($data["korkeajalk"]),
        "toisenjalk" => array_reverse($data["toisenjalk"])
    ]
], JSON_UNESCAPED_UNICODE);
?>