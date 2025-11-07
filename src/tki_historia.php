<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include 'db.php';

try {
    // Get stat_code parameter (default to all if not specified)
    $stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : '';
    
    // Build query
    $query = "SELECT vuosi, stat_code, sektori, 
                     SUM(tkmenot) as tkmenot, 
                     SUM(tkhenkilosto) as tkhenkilosto, 
                     SUM(tktyovuodet) as tktyovuodet,
                     MAX(last_data) as latest_update
              FROM Tki";
    
    if (!empty($stat_code)) {
        $query .= " WHERE stat_code = ?";
    }
    
    $query .= " GROUP BY vuosi, stat_code, sektori ORDER BY vuosi ASC, stat_code ASC, sektori ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($stat_code)) {
        $stmt->bind_param("s", $stat_code);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $latest_update = '';
    
    while ($row = $result->fetch_assoc()) {
        $year = $row['vuosi'];
        $code = $row['stat_code'];
        $sector = $row['sektori'];
        
        if (!isset($data[$code])) {
            $data[$code] = [];
        }
        
        if (!isset($data[$code][$year])) {
            $data[$code][$year] = [];
        }
        
        $data[$code][$year][$sector] = [
            'tkmenot' => intval($row['tkmenot']),
            'tkhenkilosto' => intval($row['tkhenkilosto']),
            'tktyovuodet' => floatval($row['tktyovuodet'])
        ];
        
        if (empty($latest_update) || $row['latest_update'] > $latest_update) {
            $latest_update = $row['latest_update'];
        }
    }
    
    // Format data for Chart.js
    $formatted_data = [];
    $years = [];
    
    foreach ($data as $code => $yearData) {
        $years = array_merge($years, array_keys($yearData));
    }
    $years = array_unique($years);
    sort($years);
    
    // Create datasets for each metric and region
    foreach ($data as $code => $yearData) {
        $region_name = ($code === 'MK05') ? 'Kanta-Häme' : (($code === 'MK07') ? 'Päijät-Häme' : $code);
        
        // For each sector, create datasets
        $sectors = [];
        foreach ($yearData as $year => $sectorData) {
            foreach ($sectorData as $sector => $metrics) {
                if (!in_array($sector, $sectors)) {
                    $sectors[] = $sector;
                }
            }
        }
        
        foreach ($sectors as $sector) {
            $tkmenot_data = [];
            $tkhenkilosto_data = [];
            $tktyovuodet_data = [];
            
            foreach ($years as $year) {
                $tkmenot_data[] = isset($yearData[$year][$sector]) ? $yearData[$year][$sector]['tkmenot'] : 0;
                $tkhenkilosto_data[] = isset($yearData[$year][$sector]) ? $yearData[$year][$sector]['tkhenkilosto'] : 0;
                $tktyovuodet_data[] = isset($yearData[$year][$sector]) ? $yearData[$year][$sector]['tktyovuodet'] : 0;
            }
            
            $formatted_data['tkmenot'][] = [
                'label' => $region_name . ' - ' . $sector,
                'data' => $tkmenot_data,
                'region' => $code,
                'sector' => $sector
            ];
            
            $formatted_data['tkhenkilosto'][] = [
                'label' => $region_name . ' - ' . $sector,
                'data' => $tkhenkilosto_data,
                'region' => $code,
                'sector' => $sector
            ];
            
            $formatted_data['tktyovuodet'][] = [
                'label' => $region_name . ' - ' . $sector,
                'data' => $tktyovuodet_data,
                'region' => $code,
                'sector' => $sector
            ];
        }
    }
    
    $response = [
        'labels' => $years,
        'data' => $formatted_data,
        'latest_update' => $latest_update,
        'raw_data' => $data
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Virhe TKI-datan haussa: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>