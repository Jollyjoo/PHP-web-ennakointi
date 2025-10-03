<?php
// vieraskieliset_historia.php
// Palauttaa JSON: tilastovuosi (vuosi), yhteensa, ulkomaiset, vieraskieliset - embedded line chartia varten
header('Content-Type: application/json; charset=utf-8');
require_once "db.php";


$stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : 'SSS'; // default: koko maa/alue

// Jos pyydetään stat_code-listaa (dropdownia varten)
if (isset($_GET['list_stat_codes'])) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
        // Haetaan kaikki stat_codet, kunta-nimet ja maakunta_id:t
        $stmt = $pdo->query("SELECT DISTINCT k.stat_code, k.Kunta, k.Maakunta_ID FROM Kunta k WHERE k.stat_code IS NOT NULL AND k.stat_code != '' ORDER BY k.Maakunta_ID, k.Kunta ASC");
        // Hae maakunta-nimet ja maakunta-tason stat_codet
        $maakuntaStmt = $pdo->query("SELECT Maakunta_ID, Maakunta, stat_code FROM Maakunnat");
        $maakuntaNimet = [];
        $maakuntaStatCodes = [];
        while ($mk = $maakuntaStmt->fetch(PDO::FETCH_ASSOC)) {
            $maakuntaNimet[$mk['Maakunta_ID']] = $mk['Maakunta'];
            if (!empty($mk['stat_code'])) {
                $maakuntaStatCodes[$mk['stat_code']] = [
                    'maakunta_id' => $mk['Maakunta_ID'],
                    'maakunta_nimi' => $mk['Maakunta']
                ];
            }
        }
        $maakunnat = [];
        // Lisää maakunta-tason stat_codet omaksi ryhmäksi
        $maakunnat['maakunta'] = ['maakunta_nimi' => 'Maakuntataso', 'kunnat' => []];
        foreach ($maakuntaStatCodes as $code => $info) {
            $maakunnat['maakunta']['kunnat'][] = [
                'stat_code' => $code,
                'kunta' => $info['maakunta_nimi'] . ' (maakunta)'
            ];
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mkid = $row['Maakunta_ID'] ?: 'Tuntematon';
            $mkname = isset($maakuntaNimet[$mkid]) ? $maakuntaNimet[$mkid] : ('Maakunta ' . $mkid);
            if (!isset($maakunnat[$mkid])) $maakunnat[$mkid] = ['maakunta_nimi' => $mkname, 'kunnat' => []];
            $maakunnat[$mkid]['kunnat'][] = [
                'stat_code' => $row['stat_code'],
                'kunta' => $row['Kunta']
            ];
        }
        echo json_encode(['stat_codes_grouped' => $maakunnat], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Tietokantavirhe', 'details' => $e->getMessage()]);
        exit;
    }
}

try {
    // Luo PDO-yhteys (sama kuin muissa skripteissä)
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    // Haetaan kaikki vuodet, oletuksena stat_code='SSS' (koko alue)
    $stmt = $pdo->prepare("SELECT sektori, YEAR(Tilastovuosi) as vuosi, 
        SUM(Yhteensa) as yhteensa, 
        SUM(Ulkomaiset) as ulkomaiset, 
        SUM(Vieraskieliset) as vieraskieliset
        FROM Vieraskieliset
        WHERE stat_code = :stat_code
        GROUP BY sektori, vuosi
        ORDER BY sektori ASC, vuosi ASC");
    $stmt->execute(['stat_code' => $stat_code]);
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sektori = $row['sektori'] ?: 'Muu';
        if (!isset($data[$sektori])) {
            $data[$sektori] = [
                'years' => [],
                'yhteensa' => [],
                'ulkomaiset' => [],
                'vieraskieliset' => []
            ];
        }
        $data[$sektori]['years'][] = $row['vuosi'];
        $data[$sektori]['yhteensa'][] = (int)$row['yhteensa'];
        $data[$sektori]['ulkomaiset'][] = (int)$row['ulkomaiset'];
        $data[$sektori]['vieraskieliset'][] = (int)$row['vieraskieliset'];
    }
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Tietokantavirhe', 'details' => $e->getMessage()]);
}