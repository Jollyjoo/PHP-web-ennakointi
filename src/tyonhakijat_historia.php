<?php
// tyonhakijat_historia.php
/* 
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
require_once 'db.php';

$maakunta_id = isset($_GET['maakunta_id']) ? $_GET['maakunta_id'] : null;

$where = '';
$params = [];
if ($maakunta_id === '1') {
    $where = 'WHERE maakunta_id = 1';
    $params[] = 1;
} elseif ($maakunta_id === '2') {
    $where = 'WHERE maakunta_id = 2';
    $params[] = 2;
}


// Query: get all rows, group by Aika (month) and stat_label (kunta)
$sql = "SELECT Aika, stat_label AS kunta_nimi, SUM(tyottomatlopussa) AS tyottomat
        FROM Tyonhakijat
        $where
        GROUP BY Aika, stat_label
        ORDER BY Aika, stat_label";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
$updateRow = $pdo->query("SELECT MAX(paivitys) as latest_update FROM tyonhakijat")->fetch(PDO::FETCH_ASSOC);
if ($updateRow && $updateRow['latest_update']) {
    $latest_update = $updateRow['latest_update'];
}

echo json_encode([
    'labels' => $labels,
    'datasets' => $datasets,
    'latest_update' => $latest_update
]);
