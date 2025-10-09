<?php
// tyonhakijat_historia.php

/* table: Tyonhakijat

1	Maakunta_ID Indeksi	int(11)			
	2	Aika	char(100)	latin1_swedish_ci	
	3	tyottomatlopussa	int(11)				
	4	tyotosuus	decimal(11,2)			
	5	tyottomat20	int(11)				
	6	tyottomat25	int(11)				
	7	tyottomat50	int(11)				
	8	tyottomatulk	int(11)				
	9	uudetavp	int(11)			
	10	stat_code	varchar(100)	
	11	stat_label	varchar(100)		
	12	stat_update_date	timestamp			
     */

    // Returns JSON for Chart.js: unemployed by municipality and region, grouped by year

header('Content-Type: application/json; charset=utf-8');

// Use db.php for connection variables, then create PDO
require_once __DIR__ . '/db.php';
if (!isset($pdo)) {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
}

$maakunta_id = isset($_GET['maakunta_id']) ? $_GET['maakunta_id'] : null;


$where = '';
$params = [];
if ($maakunta_id === '1') {
    $where = 'WHERE Maakunta_ID = 1';
} elseif ($maakunta_id === '2') {
    $where = 'WHERE Maakunta_ID = 2';
} elseif ($maakunta_id === '1000') {
    // Koko maa: sum all regions
    $where = 'WHERE Maakunta_ID = 1000';
} else {
    $where = 'WHERE Maakunta_ID IS NOT NULL';
}




// Query: get all rows for chart
$sql = "SELECT Aika, stat_label AS kunta_nimi, SUM(tyottomatlopussa) AS tyottomat
    FROM Tyonhakijat
    $where
    GROUP BY Aika, stat_label
    ORDER BY Aika, stat_label";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$kunnat = [];
$dataByKunta = [];
foreach ($rows as $row) {
    if (!in_array($row['Aika'], $labels)) {
        $labels[] = $row['Aika'];
    }
    if (!in_array($row['kunta_nimi'], $kunnat)) {
        $kunnat[] = $row['kunta_nimi'];
    }
    $dataByKunta[$row['kunta_nimi']][$row['Aika']] = (int)$row['tyottomat'];
}

// Query: sum tyottomatulk for all selected areas per Aika
$ulkomaalaisetByAika = [];
$sql2 = "SELECT Aika, SUM(tyottomatulk) AS ulkomaalaiset_sum FROM Tyonhakijat $where GROUP BY Aika ORDER BY Aika";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute();
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ulkomaalaisetByAika[$row['Aika']] = (int)$row['ulkomaalaiset_sum'];
}

$datasets = [];
$palette = ['#0077cc', '#cc3300', '#009933', '#3366cc', '#cc9900', '#9900cc', '#00cc99', '#ff6600', '#666666', '#1976d2','#388e3c','#fbc02d','#d32f2f','#7b1fa2','#0288d1','#c2185b','#ffa000','#689f38','#f57c00','#455a64','#cddc39','#e91e63','#00bcd4','#8bc34a','#ff9800'];
$colorIdx = 0;
foreach ($kunnat as $kunta) {
    $data = [];
    foreach ($labels as $vuosi) {
        $data[] = isset($dataByKunta[$kunta][$vuosi]) ? $dataByKunta[$kunta][$vuosi] : 0;
    }
    $datasets[] = [
        'label' => $kunta,
        'data' => $data,
        'backgroundColor' => $palette[$colorIdx % count($palette)],
        'borderColor' => $palette[$colorIdx % count($palette)],
        'borderWidth' => 1
    ];
    $colorIdx++;
}

// Get latest update date
$latest_update = null;
$updateRow = $pdo->query("SELECT MAX(stat_update_date) as latest_update FROM Tyonhakijat")->fetch(PDO::FETCH_ASSOC);
if ($updateRow && $updateRow['latest_update']) {
    $latest_update = $updateRow['latest_update'];
}


// Prepare ulkomaalaiset_sum, tyottomatlopussa_sum, palvelut_yht_sum arrays in label order
$ulkomaalaiset_sum = [];
$tyottomatlopussa_sum = [];
$palvelut_yht_sum = [];
// Query: sum tyottomatlopussa and palvelut_yht for all selected areas per Aika
$tyottomatlopussaByAika = [];
$palvelutYhtByAika = [];
$sql3 = "SELECT Aika, SUM(tyottomatlopussa) AS tyottomatlopussa_sum, SUM(palvelut_yht) AS palvelut_yht_sum FROM Tyonhakijat $where GROUP BY Aika ORDER BY Aika";
$stmt3 = $pdo->prepare($sql3);
$stmt3->execute();
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $tyottomatlopussaByAika[$row['Aika']] = (int)$row['tyottomatlopussa_sum'];
    $palvelutYhtByAika[$row['Aika']] = (int)$row['palvelut_yht_sum'];
}
foreach ($labels as $aika) {
    $ulkomaalaiset_sum[] = isset($ulkomaalaisetByAika[$aika]) ? $ulkomaalaisetByAika[$aika] : 0;
    $tyottomatlopussa_sum[] = isset($tyottomatlopussaByAika[$aika]) ? $tyottomatlopussaByAika[$aika] : 0;
    $palvelut_yht_sum[] = isset($palvelutYhtByAika[$aika]) ? $palvelutYhtByAika[$aika] : 0;
}

echo json_encode([
    'labels' => $labels,
    'datasets' => $datasets,
    'ulkomaalaiset_sum' => $ulkomaalaiset_sum,
    'tyottomatlopussa' => $tyottomatlopussa_sum,
    'palvelut_yht' => $palvelut_yht_sum,
    'latest_update' => $latest_update
]);
