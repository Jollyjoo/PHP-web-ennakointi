<?php
// talous_historia.php
header('Content-Type: application/json; charset=utf-8');
require_once "db.php";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    $sql = "SELECT kuukausi, stat_code, 
                   SUM(kuluttajienluottamus) AS kuluttajienluottamus, 
                   SUM(omatalous) AS omatalous, 
                   SUM(kuluttajahinnat) AS kuluttajahinnat, 
                   SUM(tyottomyydenuhka) AS tyottomyydenuhka
            FROM Talous
            GROUP BY kuukausi, stat_code
            ORDER BY kuukausi, stat_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'labels' => [],
        'stat_codes' => [],
        'kuluttajienluottamus' => [],
        'omatalous' => [],
        'kuluttajahinnat' => [],
        'tyottomyydenuhka' => []
    ];
    $dataByStat = [];
    foreach ($rows as $row) {
        $key = $row['stat_code'];
        if (!isset($dataByStat[$key])) {
            $dataByStat[$key] = [
                'labels' => [],
                'kuluttajienluottamus' => [],
                'omatalous' => [],
                'kuluttajahinnat' => [],
                'tyottomyydenuhka' => []
            ];
        }
        $dataByStat[$key]['labels'][] = $row['kuukausi'];
        $dataByStat[$key]['kuluttajienluottamus'][] = (float)$row['kuluttajienluottamus'];
        $dataByStat[$key]['omatalous'][] = (float)$row['omatalous'];
        $dataByStat[$key]['kuluttajahinnat'][] = (float)$row['kuluttajahinnat'];
        $dataByStat[$key]['tyottomyydenuhka'][] = (float)$row['tyottomyydenuhka'];
    }
    $result['data'] = $dataByStat;
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
