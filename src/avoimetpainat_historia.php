<?php
// --- Hämeen osaamistarpeiden ennakointialustan avoimet työpaikat toimialoittain backend ---
// Palauttaa JSON-muodossa avoimet työpaikat toimialoittain, stat_code, Tilastokuukausi, Maara

$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

header('Content-Type: application/json; charset=utf-8');

// Kirjaa debug-viestit tiedostoon
function log_debug($msg) {
    file_put_contents(__DIR__ . '/avoimetpainat_log.txt', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Yhdistetään tietokantaan
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    log_debug('Tietokantayhteys epäonnistui: ' . $conn->connect_error);
    die(json_encode(["error" => "Tietokantayhteys epäonnistui"]));
}
$conn->set_charset("utf8");

// Parametrit: stat_code, limit, toimiala
$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 24;
$toimiala = isset($_GET['toimiala']) ? $_GET['toimiala'] : null;

// Rakennetaan SQL-kysely
$sql = "SELECT Tilastokuukausi, stat_code, Toimiala, Maara FROM Avoimet_tyopaikat";
$where = [];
$params = [];
$types = '';
if ($stat_code) {
    $where[] = "stat_code = ?";
    $params[] = $stat_code;
    $types .= 's';
}
if ($toimiala) {
    $where[] = "Toimiala = ?";
    $params[] = $toimiala;
    $types .= 's';
}
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY Tilastokuukausi DESC";
if ($limit) {
    $sql .= " LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_debug('Prepare epäonnistui: ' . $conn->error);
    echo json_encode(["error" => "Tietokantavirhe"]);
    $conn->close();
    exit;
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
log_debug('Rivejä haettu: ' . count($data));
$stmt->close();
$conn->close();

echo json_encode($data, JSON_UNESCAPED_UNICODE);
// EOF
?>
