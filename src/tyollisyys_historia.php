<?php
// --- Työttömien osuus historian backend ---
// Palauttaa JSON-muodossa työttömien osuuden historian MK07 (Päijät-Häme)

$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Tietokantayhteys epäonnistui"]);
    exit;
}
$conn->set_charset("utf8");

// Haetaan viimeiset 12 kuukautta MK07 (Päijät-Häme) työttömien osuus
$sql = "SELECT aika, tyotosuus FROM Tyonhakijat WHERE stat_code = 'MK07' ORDER BY aika DESC LIMIT 12";
$res = $conn->query($sql);
$labels = [];
$data = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['aika'];
        $data[] = floatval($row['tyotosuus']);
    }
}
$conn->close();
// Palautetaan käännetyssä järjestyksessä (vanhin ensin)
echo json_encode([
    "labels" => array_reverse($labels),
    "data" => array_reverse($data)
], JSON_UNESCAPED_UNICODE);
?>
