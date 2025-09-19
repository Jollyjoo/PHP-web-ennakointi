<?php
// alueentyopaikat_historia.php
// Returns grouped job counts by Kunta and vuosi for stacked bar chart
header('Content-Type: application/json; charset=utf-8');
require_once('db.php'); // adjust if your DB connection file is named differently

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query: get all years and kunta, sum tyopaikat
    $sql = "SELECT vuosi, Kunta, SUM(tyopaikat) AS tyopaikat
            FROM Alueentyopaikat
            GROUP BY vuosi, Kunta
            ORDER BY vuosi ASC, Kunta ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Structure data for stacked chart: labels = vuosi, datasets = Kunta
    $labels = [];
    $kuntaSet = [];
    $dataMap = [];
    foreach ($rows as $row) {
        $vuosi = $row['vuosi'];
        $kunta = $row['Kunta'];
        $tyopaikat = (int)$row['tyopaikat'];
        if (!in_array($vuosi, $labels)) $labels[] = $vuosi;
        if (!in_array($kunta, $kuntaSet)) $kuntaSet[] = $kunta;
        $dataMap[$kunta][$vuosi] = $tyopaikat;
    }
    // Build datasets for Chart.js
    $datasets = [];
    foreach ($kuntaSet as $kunta) {
        $data = [];
        foreach ($labels as $vuosi) {
            $data[] = isset($dataMap[$kunta][$vuosi]) ? $dataMap[$kunta][$vuosi] : 0;
        }
        $datasets[] = [
            'label' => $kunta,
            'data' => $data
        ];
    }
    echo json_encode([
        'labels' => $labels,
        'datasets' => $datasets
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
