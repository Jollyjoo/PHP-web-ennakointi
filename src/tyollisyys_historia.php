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
// Get stat_code and field
$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : 'MK07';
$field = isset($_GET['field']) ? $_GET['field'] : 'tyotosuus';
// Validate field
$allowed_fields = ['tyotosuus', 'uudetavp', 'tyottomatlopussa'];
if (!in_array($field, $allowed_fields)) {
    $field = 'tyotosuus';
}
$sql = "SELECT aika, `$field` FROM Tyonhakijat WHERE stat_code = '" . $conn->real_escape_string($stat_code) . "' ORDER BY aika DESC LIMIT 12";
$res = $conn->query($sql);
$labels = [];
$data = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['aika'];
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
