<?php
$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

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

// Haetaan viimeisin vuosi, jolta löytyy kaikki kolme stat_codea
$sql = "SELECT Vuosi FROM Opiskelijat WHERE stat_code IN ('MK05','MK07','SSS') GROUP BY Vuosi HAVING COUNT(DISTINCT stat_code) = 3 ORDER BY Vuosi DESC LIMIT 1";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $aika = $row['Vuosi'];
    log_debug('Haettu aika: ' . $aika);
} else {
    log_debug('Ei koulutustilastoja saatavilla (aika-kysely epäonnistui)');
    echo json_encode(["error" => "Ei koulutustilastoja saatavilla"]);
    $conn->close();
    exit;
    
}

// Haetaan tilastot molemmille maakunnille ja koko maalle
$sql = "SELECT stat_code, stat_label, toisenjalk, korkeajalk FROM Opiskelijat WHERE Vuosi = ? AND stat_code IN ('MK05','MK07','SSS')";
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

// Haetaan viimeisin päivitysaika
$paivitys = null;
$sql2 = "SELECT MAX(stat_update_date) as paivitys FROM Opiskelijat WHERE stat_code IN ('MK05','MK07')";
$res2 = $conn->query($sql2);
if ($res2 && $row2 = $res2->fetch_assoc()) {
    $paivitys = $row2['paivitys'];
}

$data = [
    "aika" => $aika,
    "paivitys" => $paivitys,
    "maakunnat" => []
];
$count = 0;
while ($row = $result->fetch_assoc()) {
    $data["maakunnat"][$row["stat_code"]] = [
        "nimi" => $row["stat_label"],
        "toisen_aste" => $row["toisenjalk"],
        "korkea_aste" => $row["korkeajalk"]
    ];
    $count++;
}
log_debug('Koulutusrivejä haettu: ' . $count);
$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);
// EOF
?>