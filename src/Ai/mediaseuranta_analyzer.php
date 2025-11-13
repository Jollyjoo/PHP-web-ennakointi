<?php
// Include the centralized OpenAI limits configuration  
require_once 'openai_limits_config.php';

/**
 * Mediaseuranta Analyzer
 * Analyzes existing media monitoring data with AI
 * Integrates with the Mediaseuranta table to provide intelligent insights
 */

require_once 'config.php';

class MediaseurantaAnalyzer {
    public $db; // Changed from private to public for debugging
    
    public function __construct() {
        try {
            // Use same database config as other AI tools
            // $this->db = new mysqli('localhost', 'username', 'password', 'database_name');
            $this->db = new mysqli('tulevaisuusluotain.fi', 'catbxjbt_Christian', 'Juustonaksu5', 'catbxjbt_ennakointi');
                   
            if ($this->db->connect_error) {
                throw new Exception('Database connection failed: ' . $this->db->connect_error);
            }
            
            $this->db->set_charset('utf8mb4');
            
            // Check if Mediaseuranta table exists
            $result = $this->db->query("SHOW TABLES LIKE 'Mediaseuranta'");
            if ($result->num_rows === 0) {
                throw new Exception('Mediaseuranta table not found in database');
            }
            
            // Check if AI analysis columns exist
            $result = $this->db->query("SHOW COLUMNS FROM Mediaseuranta LIKE 'ai_analysis_status'");
            if ($result->num_rows === 0) {
                throw new Exception('AI analysis columns not found. Please run the SQL script: add_mediaseuranta_ai_columns.sql');
            }
            
        } catch (Exception $e) {
            throw new Exception('Database initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get unanalyzed Mediaseuranta entries
     */
    public function getUnanalyzedEntries($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Mediaseuranta 
                WHERE ai_analysis_status = 'pending' OR ai_analysis_status IS NULL
                ORDER BY uutisen_pvm DESC 
                LIMIT ?
            ");
            
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $entries = [];
            while ($row = $result->fetch_assoc()) {
                $entries[] = $row;
            }
            
            return $entries;
            
        } catch (Exception $e) {
            throw new Exception('Failed to fetch unanalyzed entries: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze Mediaseuranta entries with AI
     */
    public function analyzeEntries($batch_size = MEDIASEURANTA_ANALYSIS_LIMIT) {
        set_time_limit(300); // 5 minutes max
        
        $unanalyzed = $this->getUnanalyzedEntries($batch_size);
        $results = [
            'success' => true,
            'total_entries' => count($unanalyzed),
            'analyzed' => 0,
            'failed' => 0,
            'errors' => [],
            'processing_time' => null,
            'batch_size' => $batch_size
        ];
        
        $start_time = microtime(true);
        
        if (empty($unanalyzed)) {
            $results['message'] = 'No Mediaseuranta entries need analysis';
            return $results;
        }
        
        foreach ($unanalyzed as $entry) {
            try {
                // Mark as processing
                $this->updateAnalysisStatus($entry['Maakunta_ID'], $entry['uutisen_pvm'], $entry['Uutinen'], 'processing');
                
                $analysis_result = $this->runAIAnalysis($entry);
                
                if ($analysis_result && !isset($analysis_result['error'])) {
                    if ($this->storeAnalysisResult($entry, $analysis_result)) {
                        $results['analyzed']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to store analysis for entry: " . substr($entry['Uutinen'], 0, 100);
                    }
                } else {
                    $results['failed']++;
                    $error_msg = $analysis_result['error'] ?? 'Analysis failed with unknown error';
                    $results['errors'][] = "Entry analysis failed: {$error_msg}";
                    $this->updateAnalysisStatus($entry['Maakunta_ID'], $entry['uutisen_pvm'], $entry['Uutinen'], 'failed');
                }
                
                // Small delay to prevent API rate limiting
                usleep(500000); // 0.5 seconds
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Exception for entry: " . $e->getMessage();
                $this->updateAnalysisStatus($entry['Maakunta_ID'], $entry['uutisen_pvm'], $entry['Uutinen'], 'failed');
            }
        }
        
        $results['processing_time'] = round(microtime(true) - $start_time, 2) . ' seconds';
        $results['success_rate'] = $results['analyzed'] > 0 ? 
            round(($results['analyzed'] / ($results['analyzed'] + $results['failed'])) * 100, 1) : 0;
        
        return $results;
    }
    
    /**
     * Run AI analysis on a single Mediaseuranta entry
     */
    private function runAIAnalysis($entry) {
        try {
            $openai_key = getOpenAIKey();
            
            if (empty($openai_key)) {
                throw new Exception('OpenAI API key not configured');
            }
            
            // Prepare analysis prompt specifically for Mediaseuranta data
            $prompt = "Analysoi tämä Hämeen median seurannan merkintä. Anna analyysi JSON-muodossa:

Teema: {$entry['Teema']}
Päivämäärä: {$entry['uutisen_pvm']}
Uutinen: {$entry['Uutinen']}
URL: {$entry['Url']}
Luokitus: {$entry['Hankkeen_luokitus']}

Vastaa JSON-muodossa:
{
    \"relevance_score\": 1-10,
    \"economic_impact\": \"positive/neutral/negative\",
    \"employment_impact\": \"kuvaus työllisyysvaikutuksista\",
    \"key_sectors\": [\"lista relevanteista sektoreista\"],
    \"sentiment\": \"positive/neutral/negative\",
    \"crisis_probability\": 0.0-1.0,
    \"summary\": \"lyhyt yhteenveto (max 200 merkkiä)\",
    \"keywords\": [\"lista avainsanoista\"],
    \"regional_significance\": \"merkitys Hämeen alueelle\",
    \"long_term_impact\": \"pitkän aikavälin vaikutukset\"
}";

            // Make OpenAI API call
            $data = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Olet Hämeen alueen kehitysasiantuntija. Analysoi mediaseurannan merkintöjä alueen talouden ja työllisyyden näkökulmasta.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 700,
                'temperature' => 0.7
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openai_key
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                throw new Exception('OpenAI API error: HTTP ' . $http_code);
            }

            $result = json_decode($response, true);
            
            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception('Invalid OpenAI response format');
            }

            $analysis_text = $result['choices'][0]['message']['content'];
            
            // Try to extract JSON from the response
            $json_start = strpos($analysis_text, '{');
            $json_end = strrpos($analysis_text, '}');
            
            if ($json_start !== false && $json_end !== false) {
                $json_content = substr($analysis_text, $json_start, $json_end - $json_start + 1);
                $analysis_data = json_decode($json_content, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analysis_data;
                }
            }
            
            // If JSON parsing fails, return structured fallback
            return [
                'raw_analysis' => $analysis_text,
                'relevance_score' => 5,
                'economic_impact' => 'neutral',
                'summary' => 'Automaattinen analyysi epäonnistui, manuaalinen tarkistus suositeltava',
                'error_note' => 'JSON parsing failed'
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'AI Analysis failed: ' . $e->getMessage(),
                'relevance_score' => 1,
                'economic_impact' => 'neutral'
            ];
        }
    }
    
    /**
     * Store AI analysis results back to Mediaseuranta table
     */
    private function storeAnalysisResult($entry, $analysis) {
        try {
            // Use a more reliable WHERE clause with LIMIT to handle potential duplicates
            $stmt = $this->db->prepare("
                UPDATE Mediaseuranta SET
                    ai_analysis_status = 'completed',
                    ai_analyzed_at = NOW(),
                    ai_relevance_score = ?,
                    ai_economic_impact = ?,
                    ai_employment_impact = ?,
                    ai_key_sectors = ?,
                    ai_sentiment = ?,
                    ai_crisis_probability = ?,
                    ai_summary = ?,
                    ai_keywords = ?,
                    ai_full_analysis = ?
                WHERE Maakunta_ID = ? AND uutisen_pvm = ? AND Uutinen LIKE ? 
                LIMIT 1
            ");
            
            $sectors_json = isset($analysis['key_sectors']) ? json_encode($analysis['key_sectors']) : null;
            $keywords_json = isset($analysis['keywords']) ? json_encode($analysis['keywords']) : null;
            $full_analysis_json = json_encode($analysis);
            
            // Extract values to variables for bind_param
            $relevance_score = $analysis['relevance_score'] ?? 5;
            $economic_impact = $analysis['economic_impact'] ?? 'neutral';
            $employment_impact = $analysis['employment_impact'] ?? '';
            $sentiment = $analysis['sentiment'] ?? 'neutral';
            $crisis_probability = $analysis['crisis_probability'] ?? 0.0;
            $summary = $analysis['summary'] ?? '';
            
            // Use first 100 characters for LIKE pattern to avoid length issues
            $news_pattern = substr($entry['Uutinen'], 0, 100) . '%';
            
            $stmt->bind_param("issssdssssss",
                $relevance_score,
                $economic_impact,
                $employment_impact,
                $sectors_json,
                $sentiment,
                $crisis_probability,
                $summary,
                $keywords_json,
                $full_analysis_json,
                $entry['Maakunta_ID'],
                $entry['uutisen_pvm'],
                $news_pattern
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            throw new Exception('Failed to store analysis result: ' . $e->getMessage());
        }
    }
    
    /**
     * Update analysis status for an entry
     */
    private function updateAnalysisStatus($maakunta_id, $date, $news_text, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE Mediaseuranta SET
                    ai_analysis_status = ?
                WHERE Maakunta_ID = ? AND uutisen_pvm = ? AND Uutinen LIKE ?
                LIMIT 1
            ");
            
            $news_pattern = substr($news_text, 0, 100) . '%';
            $stmt->bind_param("siss", $status, $maakunta_id, $date, $news_pattern);
            return $stmt->execute();
            
        } catch (Exception $e) {
            // Don't throw exception for status updates to avoid breaking main process
            error_log("Failed to update analysis status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get analysis statistics
     */
    public function getAnalysisStats() {
        try {
            $stats = [];
            
            // Total entries
            $result = $this->db->query("SELECT COUNT(*) as total FROM Mediaseuranta");
            $stats['total_entries'] = $result->fetch_assoc()['total'];
            
            // Analysis status breakdown
            $result = $this->db->query("
                SELECT ai_analysis_status, COUNT(*) as count 
                FROM Mediaseuranta 
                GROUP BY ai_analysis_status
            ");
            
            $stats['by_status'] = [];
            while ($row = $result->fetch_assoc()) {
                $status = $row['ai_analysis_status'] ?: 'pending';
                $stats['by_status'][$status] = $row['count'];
            }
            
            // High relevance entries (score >= 7)
            $result = $this->db->query("
                SELECT COUNT(*) as high_relevance 
                FROM Mediaseuranta 
                WHERE ai_relevance_score >= 7
            ");
            $stats['high_relevance'] = $result->fetch_assoc()['high_relevance'];
            
            // Recent entries (last 30 days)
            $result = $this->db->query("
                SELECT COUNT(*) as recent 
                FROM Mediaseuranta 
                WHERE uutisen_pvm >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stats['recent_30days'] = $result->fetch_assoc()['recent'];
            
            return $stats;
            
        } catch (Exception $e) {
            throw new Exception('Failed to get analysis stats: ' . $e->getMessage());
        }
    }
    
    /**
     * Get analyzed entries with insights
     */
    public function getAnalyzedEntries($limit = 20, $min_relevance = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT *, 
                    CASE WHEN Maakunta_ID = 1 THEN 'Päijät-Häme' ELSE 'Kanta-Häme' END as Maakunta_Nimi
                FROM Mediaseuranta 
                WHERE ai_analysis_status = 'completed' 
                AND ai_relevance_score >= ?
                ORDER BY ai_relevance_score DESC, uutisen_pvm DESC
                LIMIT ?
            ");
            
            $stmt->bind_param("ii", $min_relevance, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $entries = [];
            while ($row = $result->fetch_assoc()) {
                // Parse JSON fields
                if ($row['ai_key_sectors']) {
                    $row['ai_key_sectors_parsed'] = json_decode($row['ai_key_sectors'], true);
                }
                if ($row['ai_keywords']) {
                    $row['ai_keywords_parsed'] = json_decode($row['ai_keywords'], true);
                }
                $entries[] = $row;
            }
            
            return $entries;
            
        } catch (Exception $e) {
            throw new Exception('Failed to fetch analyzed entries: ' . $e->getMessage());
        }
    }

    /**
     * Get weekly insights for analyzed Mediaseuranta entries within date range
     */
    public function getWeeklyInsights($start_date, $end_date, $min_relevance = 3) {
        try {
            $start_datetime = $start_date . ' 00:00:00';
            $end_datetime = $end_date . ' 23:59:59';
            
            $stmt = $this->db->prepare("
                SELECT *, 
                    CASE WHEN Maakunta_ID = 1 THEN 'Päijät-Häme' ELSE 'Kanta-Häme' END as Maakunta_Nimi
                FROM Mediaseuranta 
                WHERE ai_analysis_status = 'completed' 
                AND ai_relevance_score >= ?
                AND uutisen_pvm >= ? AND uutisen_pvm <= ?
                ORDER BY ai_relevance_score DESC, uutisen_pvm DESC
            ");
            
            $stmt->bind_param("iss", $min_relevance, $start_datetime, $end_datetime);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $insights = [];
            while ($row = $result->fetch_assoc()) {
                // Parse JSON fields for dashboard compatibility
                if ($row['ai_key_sectors']) {
                    $row['ai_key_sectors_parsed'] = json_decode($row['ai_key_sectors'], true);
                }
                if ($row['ai_keywords']) {
                    $row['ai_keywords_parsed'] = json_decode($row['ai_keywords'], true);
                }
                
                // Add compatible field mappings for dashboard
                $row['sentiment'] = $row['ai_sentiment'];
                $row['themes'] = $row['ai_key_sectors'];
                $row['entities'] = $row['ai_keywords'];
                $row['crisis_probability'] = $row['ai_crisis_probability'];
                
                $insights[] = $row;
            }
            
            // Calculate summary statistics
            $stats = [
                'total' => count($insights),
                'date_range' => [
                    'start' => $start_date,
                    'end' => $end_date
                ],
                'sentiment_distribution' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0
                ],
                'avg_relevance_score' => 0,
                'crisis_levels' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ],
                'top_themes' => []
            ];
            
            // Calculate statistics
            if (!empty($insights)) {
                $relevance_sum = 0;
                $theme_count = [];
                
                foreach ($insights as $insight) {
                    // Count sentiment
                    $sentiment = strtolower($insight['ai_sentiment'] ?? 'neutral');
                    if (strpos($sentiment, 'positive') !== false || strpos($sentiment, 'myönteinen') !== false) {
                        $stats['sentiment_distribution']['positive']++;
                    } elseif (strpos($sentiment, 'negative') !== false || strpos($sentiment, 'kielteinen') !== false) {
                        $stats['sentiment_distribution']['negative']++;
                    } else {
                        $stats['sentiment_distribution']['neutral']++;
                    }
                    
                    // Sum relevance scores
                    $relevance_sum += $insight['ai_relevance_score'] ?? 0;
                    
                    // Count crisis levels
                    $crisis_prob = $insight['ai_crisis_probability'] ?? 0;
                    if ($crisis_prob > 0.7) {
                        $stats['crisis_levels']['high']++;
                    } elseif ($crisis_prob > 0.3) {
                        $stats['crisis_levels']['medium']++;
                    } else {
                        $stats['crisis_levels']['low']++;
                    }
                    
                    // Count themes
                    if ($insight['ai_key_sectors_parsed'] && is_array($insight['ai_key_sectors_parsed'])) {
                        foreach ($insight['ai_key_sectors_parsed'] as $theme) {
                            $theme = trim($theme);
                            if ($theme) {
                                $theme_count[$theme] = ($theme_count[$theme] ?? 0) + 1;
                            }
                        }
                    }
                }
                
                $stats['avg_relevance_score'] = round($relevance_sum / count($insights), 2);
                
                // Get top 5 themes
                arsort($theme_count);
                $stats['top_themes'] = array_slice($theme_count, 0, 5, true);
            }
            
            return [
                'insights' => $insights,
                'stats' => $stats,
                'success' => true
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Failed to get weekly insights: ' . $e->getMessage(),
                'insights' => [],
                'stats' => ['total' => 0],
                'success' => false
            ];
        }
    }
}

// Handle API requests
try {
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'debug':
            // Simple debug to check database connection and table structure
            $debug_info = [];
            try {
                $analyzer = new MediaseurantaAnalyzer();
                $debug_info['database_connection'] = 'OK';
                
                // Check table existence
                $result = $analyzer->db->query("SHOW TABLES LIKE 'Mediaseuranta'");
                $debug_info['table_exists'] = $result->num_rows > 0;
                
                // Check columns
                $result = $analyzer->db->query("SHOW COLUMNS FROM Mediaseuranta");
                $columns = [];
                while ($row = $result->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
                $debug_info['columns'] = $columns;
                
                // Check if AI columns exist
                $ai_columns = ['ai_analysis_status', 'ai_relevance_score', 'ai_economic_impact'];
                $debug_info['ai_columns_exist'] = [];
                foreach ($ai_columns as $col) {
                    $debug_info['ai_columns_exist'][$col] = in_array($col, $columns);
                }
                
                // Count total entries
                $result = $analyzer->db->query("SELECT COUNT(*) as total FROM Mediaseuranta");
                $debug_info['total_entries'] = $result->fetch_assoc()['total'];
                
            } catch (Exception $e) {
                $debug_info['error'] = $e->getMessage();
            }
            
            echo json_encode([
                'debug_info' => $debug_info,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'test':
            // Simple test to try getting a few entries
            try {
                $analyzer = new MediaseurantaAnalyzer();
                
                // Test getting unanalyzed entries
                $entries = $analyzer->getUnanalyzedEntries(3);
                
                $test_info = [
                    'connection' => 'OK',
                    'entries_found' => count($entries),
                    'sample_entries' => []
                ];
                
                // Show sample of what we found
                foreach (array_slice($entries, 0, 2) as $entry) {
                    $test_info['sample_entries'][] = [
                        'Maakunta_ID' => $entry['Maakunta_ID'],
                        'Teema' => substr($entry['Teema'], 0, 50) . '...',
                        'uutisen_pvm' => $entry['uutisen_pvm'],
                        'Uutinen_preview' => substr($entry['Uutinen'], 0, 100) . '...',
                        'ai_status' => $entry['ai_analysis_status']
                    ];
                }
                
                echo json_encode([
                    'test_result' => $test_info,
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                
            } catch (Exception $e) {
                echo json_encode([
                    'test_result' => ['error' => $e->getMessage()],
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            break;
            
        case 'analyze':
            $analyzer = new MediaseurantaAnalyzer();
            $batch_size = isset($_GET['batch_size']) ? max(1, min(10, (int)$_GET['batch_size'])) : MEDIASEURANTA_ANALYSIS_LIMIT;
            $result = $analyzer->analyzeEntries($batch_size);
            echo json_encode([
                'mediaseuranta_analysis' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'stats':
            $analyzer = new MediaseurantaAnalyzer();
            $stats = $analyzer->getAnalysisStats();
            echo json_encode([
                'mediaseuranta_stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'insights':
            $analyzer = new MediaseurantaAnalyzer();
            $limit = $_GET['limit'] ?? 20;
            $min_relevance = $_GET['min_relevance'] ?? 5;
            $insights = $analyzer->getAnalyzedEntries($limit, $min_relevance);
            echo json_encode([
                'mediaseuranta_insights' => $insights,
                'count' => count($insights),
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'weekly_insights':
            $analyzer = new MediaseurantaAnalyzer();
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            $min_relevance = $_GET['min_relevance'] ?? 3;
            $result = $analyzer->getWeeklyInsights($start_date, $end_date, $min_relevance);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        default:
            echo json_encode([
                'available_actions' => [
                    'debug' => 'Debug database connection and table structure',
                    'test' => 'Test basic data retrieval from Mediaseuranta',
                    'analyze' => 'Analyze unanalyzed Mediaseuranta entries with AI',
                    'stats' => 'Get analysis statistics',
                    'insights' => 'Get analyzed entries with insights',
                    'weekly_insights' => 'Get weekly insights for analyzed entries within date range (supports start_date, end_date, min_relevance parameters)'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>