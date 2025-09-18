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
$conn->set_charset("latin1");

// Parametrit: stat_code, limit, toimiala
$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 24;
$toimiala = isset($_GET['toimiala']) ? $_GET['toimiala'] : null;

// Rakennetaan SQL-kysely
// Step 1: Get last N unique months for the selected stat_code
$months_sql = "SELECT DISTINCT Tilastokuukausi FROM Avoimet_tyopaikat WHERE stat_code = ? ORDER BY Tilastokuukausi DESC LIMIT ?";
$months_stmt = $conn->prepare($months_sql);
if (!$months_stmt) {
    log_debug('Prepare epäonnistui (months): ' . $conn->error);
    echo json_encode(["error" => "Tietokantavirhe"]);
    $conn->close();
    exit;
}
$months_stmt->bind_param('si', $stat_code, $limit);
$months_stmt->execute();
$months_result = $months_stmt->get_result();
$months = [];
while ($row = $months_result->fetch_assoc()) {
    $months[] = $row['Tilastokuukausi'];
}
$months_stmt->close();

if (count($months) === 0) {
    log_debug('Ei kuukausia löytynyt');
    $conn->close();
    echo json_encode([]);
    exit;
}

// Step 2: Fetch all rows for those months
$in_clause = implode(',', array_fill(0, count($months), '?'));
$sql = "SELECT Tilastokuukausi, stat_code, Toimiala, Maara FROM Avoimet_tyopaikat WHERE stat_code = ? AND Tilastokuukausi IN ($in_clause)";
if ($toimiala) {
    $sql .= " AND Toimiala = ?";
}
$sql .= " ORDER BY Tilastokuukausi DESC, Toimiala ASC";

$params = [$stat_code];
$types = 's';
foreach ($months as $m) {
    $params[] = $m;
    $types .= 's';
}
if ($toimiala) {
    $params[] = $toimiala;
    $types .= 's';
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_debug('Prepare epäonnistui: ' . $conn->error);
    echo json_encode(["error" => "Tietokantavirhe"]);
    $conn->close();
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    if (isset($row['Toimiala'])) {
        $row['Toimiala'] = utf8_encode($row['Toimiala']);
    }
    $data[] = $row;
}
log_debug('Rivejä haettu: ' . count($data));
$stmt->close();
$conn->close();

echo json_encode($data, JSON_UNESCAPED_UNICODE);
// EOF
?>
