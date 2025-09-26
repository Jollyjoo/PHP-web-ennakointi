<?php
// --- Väestön historian backend ---
// Palauttaa JSON-muodossa väestön historian Kanta-Hämeelle ja Päijät-Hämeelle

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

// Haetaan maakunta parametri (1 = Päijät-Häme, 2 = Kanta-Häme, default = Kanta-Häme)
$region = isset($_GET['region']) ? intval($_GET['region']) : 2;
$region_map = [1 => 'Päijät-Häme', 2 => 'Kanta-Häme'];
$region_name = isset($region_map[$region]) ? $region_map[$region] : 'Kanta-Häme';

// Oletetaan taulu Asukasmaara, kentät: vuosi, Kunta_ID, vaki_luku
$sql = "SELECT Tilastovuosi, Maara FROM Asukasmaara WHERE Kunta_ID = $region and Sukupuoli_ID = 3 and ika = 'yhteensä' ORDER BY Tilastovuosi ASC";
$res = $conn->query($sql);
$labels = [];
$data = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['Tilastovuosi'];
        $data[] = intval($row['Maara']);
    }
}
$conn->close();
// Palautetaan tiedot Chart.js:lle
echo json_encode([
    "labels" => $labels,
    "data" => $data,
    "region" => $region_name
], JSON_UNESCAPED_UNICODE);
?>
