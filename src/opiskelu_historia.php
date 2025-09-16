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
$sql = "SELECT vuosi, toisen_aste, korkea_aste, amk, yo FROM Opiskelu ORDER BY vuosi DESC LIMIT 10";
$res = $conn->query($sql);
$labels = [];
$data = [
    "toisen_aste" => [],
    "korkea_aste" => [],
    "amk" => [],
    "yo" => []
];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['vuosi'];
        $data["toisen_aste"][] = floatval($row["toisen_aste"]);
        $data["korkea_aste"][] = floatval($row["korkea_aste"]);
        $data["amk"][] = floatval($row["amk"]);
        $data["yo"][] = floatval($row["yo"]);
    }
}
$conn->close();
// Palautetaan käännetyssä järjestyksessä (vanhin ensin)
echo json_encode([
    "labels" => array_reverse($labels),
    "data" => [
        "toisen_aste" => array_reverse($data["toisen_aste"]),
        "korkea_aste" => array_reverse($data["korkea_aste"]),
        "amk" => array_reverse($data["amk"]),
        "yo" => array_reverse($data["yo"])
    ]
], JSON_UNESCAPED_UNICODE);
?>