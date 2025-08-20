<?php
// --- Hämeen osaamistarpeiden ennakointialustan työllisyystilastojen backend ---
// Palauttaa JSON-muodossa työttömien osuudet, uudet avoimet työpaikat ja työttömät työnhakijat maakunnittain

$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

// Kirjaa debug-viestit tiedostoon
function log_debug($msg) {
    file_put_contents(__DIR__ . '/tyollisyys_log.txt', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Yhdistetään tietokantaan
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    log_debug('Tietokantayhteys epäonnistui: ' . $conn->connect_error);
    die(json_encode(["error" => "Tietokantayhteys epäonnistui"]));
}
$conn->set_charset("utf8");

// Haetaan viimeisin kuukausi, jolta molemmille maakunnille löytyy dataa (MK05=Kanta-Häme, MK07=Päijät-Häme)
$sql = "SELECT aika FROM Tyonhakijat WHERE stat_code IN ('MK05','MK07') ORDER BY aika DESC LIMIT 1";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $aika = $row['aika'];
    log_debug('Haettu aika: ' . $aika);
} else {
    // Jos tilastokuukautta ei löydy, palautetaan virhe
    log_debug('Ei tilastotietoja saatavilla (aika-kysely epäonnistui)');
    echo json_encode(["error" => "Ei tilastotietoja saatavilla"]);
    $conn->close();
    exit;
}

// Haetaan tilastot kyseiseltä kuukaudelta molemmille maakunnille
$sql = "SELECT stat_code, stat_label, tyotosuus, uudetavp, tyottomatlopussa FROM Tyonhakijat WHERE aika = ? AND stat_code IN ('MK05','MK07')";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_debug('Prepare epäonnistui: ' . $conn->error);
    echo json_encode(["error" => "Tietokantavirhe"]);
    $conn->close();
    exit;
}
$stmt->bind_param("s", $aika);
$stmt->execute();
$result = $stmt->get_result();

// Haetaan viimeisin päivitysaika (stat_update_date)
$paivitys = null;
$sql2 = "SELECT MAX(stat_update_date) as paivitys FROM Tyonhakijat WHERE stat_code IN ('MK05','MK07')";
$res2 = $conn->query($sql2);
if ($res2 && $row2 = $res2->fetch_assoc()) {
    $paivitys = $row2['paivitys'];
}

// Muodostetaan palautettava data-taulukko
$data = [
    "aika" => $aika, // tilastokuukausi
    "paivitys" => $paivitys, // viimeisin päivitysaika
    "maakunnat" => [] // tilastot maakunnittain
];
$count = 0;
while ($row = $result->fetch_assoc()) {
    $data["maakunnat"][$row["stat_code"]] = [
        "nimi" => $row["stat_label"], // alueen nimi
        "tyottomien_osuus" => $row["tyotosuus"], // työttömien osuus (%)
        "uudet_avopaikat" => $row["uudetavp"], // uudet avoimet työpaikat
        "tyottomat_yht" => $row["tyottomatlopussa"] // työttömät työnhakijat yhteensä
    ];
    $count++;
}
log_debug('Rivejä haettu: ' . $count);
$stmt->close();
$conn->close();

// Palautetaan data JSON-muodossa frontendille
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);
// EOF
?>