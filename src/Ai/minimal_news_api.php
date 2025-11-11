<?php
// Working News Intelligence API - Step by step enhancement
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

/**
 * Simple AI Alert System - Real functionality without complex class structure
 */

// Helper function to get recent news from database
function getRecentNewsForAlerts($db_connection, $hours = 24, $limit = 5) {
    if (!$db_connection) {
        return [];
    }
    
    $stmt = $db_connection->prepare("
        SELECT id, title, content, published_date 
        FROM news_articles 
        WHERE published_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        AND (ai_analysis_status IS NULL OR ai_analysis_status != 'analyzed')
        ORDER BY published_date DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $hours, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function for simple crisis detection
function detectCrisisSignals($text) {
    $crisis_keywords = [
        'high' => ['kriisi', 'katastrofi', 'romahdus', 'konkurssi', 'sulkeminen'],
        'medium' => ['ongelma', 'vaikeudet', 'laskusuhdanne', 'supistus', 'työttömyys'],
        'low' => ['haaste', 'muutos', 'epävarmuus', 'lasku']
    ];
    
    $text_lower = strtolower($text);
    $crisis_probability = 0;
    $crisis_type = 'none';
    $severity = 'low';
    $keywords_found = [];
    
    foreach ($crisis_keywords as $level => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $keywords_found[] = $keyword;
                $crisis_probability = match($level) {
                    'high' => 0.8,
                    'medium' => 0.6,
                    'low' => 0.3,
                    default => 0
                };
                $severity = $level;
                $crisis_type = 'economic';
                break 2; // Exit both loops when first match found
            }
        }
    }
    
    return [
        'crisis_probability' => $crisis_probability,
        'crisis_type' => $crisis_type,
        'severity' => $severity,
        'keywords_found' => $keywords_found,
        'mitigation_strategies' => $crisis_probability > 0.5 ? 
            ['Monitor situation closely', 'Prepare response plan', 'Contact stakeholders'] : 
            ['Continue normal monitoring']
    ];
}

// Helper function to mark article as analyzed
function markArticleAsAnalyzed($db_connection, $article_id, $analysis_data) {
    if (!$db_connection) {
        return false;
    }
    
    // Store analysis results
    $stmt = $db_connection->prepare("
        INSERT INTO news_analysis (article_id, analysis_data, created_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        analysis_data = VALUES(analysis_data), 
        updated_at = NOW()
    ");
    
    if ($stmt) {
        $json_data = json_encode($analysis_data);
        $stmt->bind_param("is", $article_id, $json_data);
        $analysis_stored = $stmt->execute();
        
        // Mark article as analyzed to prevent duplicate processing
        if ($analysis_stored) {
            $update_stmt = $db_connection->prepare("
                UPDATE news_articles 
                SET ai_analysis_status = 'analyzed', 
                    ai_analyzed_at = NOW() 
                WHERE id = ?
            ");
            if ($update_stmt) {
                $update_stmt->bind_param("i", $article_id);
                $update_stmt->execute();
            }
        }
        
        return $analysis_stored;
    }
    
    return false;
}

// Main processing
try {
    require_once 'config.php';
    
    // Try database connection
    $db_connection = null;
    $database_available = false;
    
    try {
        if (class_exists('mysqli')) {
            $db_connection = new mysqli('tulevaisuusluotain.fi', 'catbxjbt_Christian', 'Juustonaksu5', 'catbxjbt_ennakointi');
            
            if (!$db_connection->connect_error) {
                $db_connection->set_charset('utf8mb4');
                $database_available = true;
            }
        }
    } catch (Exception $e) {
        $database_available = false;
        error_log('Database connection failed: ' . $e->getMessage());
    }
    
    // Get OpenAI key
    $openai_api_key = null;
    try {
        $openai_api_key = getOpenAIKey();
    } catch (Exception $e) {
        error_log('OpenAI key not available: ' . $e->getMessage());
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            $result = [
                'status' => 'Working News Intelligence API',
                'timestamp' => date('Y-m-d H:i:s'),
                'database_connected' => $database_available,
                'openai_configured' => !empty($openai_api_key),
                'environment' => $database_available ? 'server_connected' : 'local_development'
            ];
            break;
            
        case 'alerts':
            if (!$database_available) {
                // Local development fallback - demo data
                $result = [
                    'status' => 'local_development',
                    'message' => 'Demo data - database not available in local environment',
                    'alerts' => [
                        [
                            'id' => 'demo_1',
                            'type' => 'trend',
                            'severity' => 'medium',
                            'title' => 'Demo: Tekoäly kehitys kiihtyy',
                            'description' => 'Tämä on demo-hälyytys paikallista kehitysympäristöä varten.',
                            'timestamp' => date('Y-m-d H:i:s'),
                            'source' => 'local_demo'
                        ]
                    ],
                    'count' => 1,
                    'environment' => 'local'
                ];
            } else {
                // Real server processing
                $high_priority_alerts = [];
                $processed_count = 0;
                
                try {
                    // Get recent unanalyzed news (limit to 5 for cost control)
                    $recent_news = getRecentNewsForAlerts($db_connection, 24, 5);
                    
                    foreach ($recent_news as $article) {
                        $processed_count++;
                        
                        // Analyze for crisis signals
                        $crisis_signals = detectCrisisSignals($article['content']);
                        
                        // Store analysis results and mark as analyzed
                        $analysis_data = [
                            'article_id' => $article['id'],
                            'crisis_analysis' => $crisis_signals,
                            'analyzed_for' => 'alerts',
                            'processed_at' => date('Y-m-d H:i:s'),
                            'api_version' => 'working_minimal'
                        ];
                        
                        markArticleAsAnalyzed($db_connection, $article['id'], $analysis_data);
                        
                        // Generate alert if crisis probability is high enough
                        if ($crisis_signals['crisis_probability'] > 0.4) { // Lower threshold for demo
                            $high_priority_alerts[] = [
                                'type' => 'crisis_detected',
                                'article_id' => $article['id'],
                                'title' => $article['title'],
                                'probability' => $crisis_signals['crisis_probability'],
                                'crisis_type' => $crisis_signals['crisis_type'],
                                'severity' => $crisis_signals['severity'],
                                'keywords_found' => $crisis_signals['keywords_found'],
                                'recommended_actions' => $crisis_signals['mitigation_strategies'],
                                'detected_at' => date('Y-m-d H:i:s'),
                                'source' => 'ai_analysis'
                            ];
                        }
                    }
                    
                    $result = [
                        'status' => 'analysis_complete',
                        'message' => "Analyzed $processed_count recent articles",
                        'alerts' => $high_priority_alerts,
                        'count' => count($high_priority_alerts),
                        'processed_articles' => $processed_count,
                        'environment' => 'server',
                        'analysis_method' => 'rule_based_crisis_detection'
                    ];
                    
                } catch (Exception $e) {
                    $result = [
                        'status' => 'analysis_error',
                        'error' => $e->getMessage(),
                        'alerts' => [],
                        'count' => 0,
                        'environment' => 'server'
                    ];
                }
            }
            break;
            
        default:
            $result = [
                'status' => 'Working AI News Intelligence System Active', 
                'timestamp' => date('Y-m-d H:i:s'),
                'available_actions' => ['test', 'alerts'],
                'database_available' => $database_available,
                'openai_available' => !empty($openai_api_key)
            ];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'type' => 'Exception'], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'type' => 'Error'], JSON_UNESCAPED_UNICODE);
}
?>