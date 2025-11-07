<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

// Debug logging function
function log_debug($msg) {
    file_put_contents(__DIR__ . '/tki_log.txt', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

try {
    // Create MySQLi connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        log_debug('Tietokantayhteys epäonnistui: ' . $conn->connect_error);
        throw new Exception("Tietokantayhteys epäonnistui: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    
    // Get stat_code parameter (default to all if not specified)
    $stat_code = isset($_GET['stat_code']) ? $_GET['stat_code'] : '';
    log_debug('Haetaan TKI-dataa, stat_code: ' . ($stat_code ?: 'kaikki'));
    
    // First, let's check if the table exists and has data
    $check_query = "SELECT COUNT(*) as count FROM Tki";
    $check_result = $conn->query($check_query);
    if (!$check_result) {
        throw new Exception("Tki table query failed: " . $conn->error);
    }
    $table_check = $check_result->fetch_assoc();
    log_debug('Tki table row count: ' . $table_check['count']);
    
    if ($table_check['count'] == 0) {
        throw new Exception("Tki table is empty");
    }
    
    // Build query with JOIN to get region names
    $query = "SELECT t.vuosi, t.stat_code, t.sektori, 
                     t.tkmenot, 
                     t.tkhenkilosto, 
                     t.tktyovuodet,
                     t.last_data as latest_update,
                     m.Maakunta as region_name
              FROM Tki t
              LEFT JOIN Maakunnat m ON t.stat_code = m.stat_code";
    
    if (!empty($stat_code)) {
        $query .= " WHERE t.stat_code = ?";
    }
    
    $query .= " ORDER BY t.vuosi ASC, t.stat_code ASC, t.sektori ASC";
    
    log_debug('SQL query: ' . $query);
    
    if (!empty($stat_code)) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $stat_code);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
    }
    
    $data = [];
    $latest_update = '';
    $row_count = 0;
    $regions = []; // To store available regions
    
    while ($row = $result->fetch_assoc()) {
        $year = $row['vuosi'];
        $code = $row['stat_code'];
        $sector = $row['sektori'];
        $region_name = $row['region_name'] ?: $code; // Fallback to code if name not found
        $row_count++;
        
        // Store region info
        if (!isset($regions[$code])) {
            $regions[$code] = $region_name;
        }
        
        if (!isset($data[$code])) {
            $data[$code] = [];
        }
        
        if (!isset($data[$code][$year])) {
            $data[$code][$year] = [];
        }
        
        $data[$code][$year][$sector] = [
            'tkmenot' => floatval($row['tkmenot']),
            'tkhenkilosto' => intval($row['tkhenkilosto']),
            'tktyovuodet' => floatval($row['tktyovuodet'])
        ];
        
        if (empty($latest_update) || $row['latest_update'] > $latest_update) {
            $latest_update = $row['latest_update'];
        }
    }
    
    log_debug('Processed ' . $row_count . ' rows from database');
    log_debug('Found regions: ' . implode(', ', array_keys($regions)));
    
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
        $region_name = $regions[$code]; // Use proper region name from Maakunta table
        
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
                'region_name' => $region_name,
                'sector' => $sector
            ];
            
            $formatted_data['tkhenkilosto'][] = [
                'label' => $region_name . ' - ' . $sector,
                'data' => $tkhenkilosto_data,
                'region' => $code,
                'region_name' => $region_name,
                'sector' => $sector
            ];
            
            $formatted_data['tktyovuodet'][] = [
                'label' => $region_name . ' - ' . $sector,
                'data' => $tktyovuodet_data,
                'region' => $code,
                'region_name' => $region_name,
                'sector' => $sector
            ];
        }
    }
    
    $response = [
        'labels' => $years,
        'data' => $formatted_data,
        'latest_update' => $latest_update,
        'regions' => $regions, // Available regions with proper names
        'raw_data' => $data,
        'debug_info' => [
            'total_rows' => $row_count,
            'regions_found' => array_keys($data),
            'years_found' => $years
        ]
    ];
    
    log_debug('Response prepared with ' . count($years) . ' years and ' . count($data) . ' regions');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    log_debug('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Virhe TKI-datan haussa: ' . $e->getMessage(),
        'debug' => [
            'stat_code' => $stat_code ?? 'not set',
            'query' => $query ?? 'not set'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($conn)) {
    $conn->close();
}
?>