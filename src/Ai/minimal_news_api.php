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
        AND (analysis_status IS NULL OR analysis_status != 'analyzed')
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

// Helper function for comprehensive analysis like mediaseuranta_analyzer
function runComprehensiveAnalysis($article, $openai_key = null) {
    if ($openai_key) {
        return runOpenAIAnalysis($article, $openai_key);
    } else {
        return runRuleBasedAnalysis($article);
    }
}

// Helper function for OpenAI-powered analysis
function runOpenAIAnalysis($article, $openai_key) {
    try {
        // Prepare analysis prompt like mediaseuranta_analyzer
        $prompt = "Analysoi tämä uutisartikkeli Hämeen alueen näkökulmasta. Anna analyysi JSON-muodossa:

Otsikko: {$article['title']}
Päivämäärä: {$article['published_date']}
Sisältö: " . substr($article['content'], 0, 1500) . "...

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
                ['role' => 'system', 'content' => 'Olet Hämeen alueen kehitysasiantuntija. Analysoi uutisia alueen talouden ja työllisyyden näkökulmasta.'],
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
            'summary' => 'OpenAI analyysi epäonnistui, käytetään varasuunnitelmaa',
            'error_note' => 'JSON parsing failed'
        ];
        
    } catch (Exception $e) {
        return [
            'error' => 'OpenAI Analysis failed: ' . $e->getMessage(),
            'relevance_score' => 1,
            'economic_impact' => 'neutral'
        ];
    }
}

// Helper function for rule-based analysis (fallback when no OpenAI)
function runRuleBasedAnalysis($article) {
    $text = strtolower($article['title'] . ' ' . $article['content']);
    
    // Economic impact keywords
    $positive_economic = ['kasvu', 'investointi', 'rahoitus', 'työllistää', 'uusia työpaikkoja', 'menestys', 'voitto', 'kannattava'];
    $negative_economic = ['irtisanominen', 'lomautus', 'konkurssi', 'sulkeminen', 'kriisi', 'laskusuhdanne', 'työttömyys'];
    
    // Sector keywords
    $sectors = [
        'teknologia' => ['teknologia', 'IT', 'digitalisaatio', 'tekoäly', 'ohjelmisto', 'data'],
        'teollisuus' => ['teollisuus', 'tuotanto', 'tehdas', 'valmistus', 'metalliteollisuus'],
        'palvelut' => ['palvelut', 'kauppa', 'matkailu', 'ravintola', 'hotelli'],
        'koulutus' => ['koulutus', 'opiskelu', 'yliopisto', 'ammattikoulu', 'tutkimus'],
        'terveydenhuolto' => ['terveydenhuolto', 'sairaala', 'lääkäri', 'hoitaja'],
        'liikenne' => ['liikenne', 'kuljetus', 'logistiikka', 'rautatie', 'lentokenttä']
    ];
    
    // Crisis keywords
    $crisis_keywords = [
        'high' => ['kriisi', 'katastrofi', 'romahdus', 'konkurssi', 'sulkeminen', 'irtisanominen'],
        'medium' => ['ongelma', 'vaikeudet', 'laskusuhdanne', 'supistus', 'työttömyys'],
        'low' => ['haaste', 'muutos', 'epävarmuus', 'lasku']
    ];
    
    // Calculate scores
    $economic_score = 0;
    foreach ($positive_economic as $word) {
        if (strpos($text, $word) !== false) $economic_score++;
    }
    foreach ($negative_economic as $word) {
        if (strpos($text, $word) !== false) $economic_score--;
    }
    
    // Determine economic impact
    $economic_impact = $economic_score > 0 ? 'positive' : ($economic_score < 0 ? 'negative' : 'neutral');
    $sentiment = $economic_impact; // Simple mapping
    
    // Find relevant sectors
    $relevant_sectors = [];
    foreach ($sectors as $sector => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $relevant_sectors[] = $sector;
                break;
            }
        }
    }
    
    // Calculate crisis probability
    $crisis_probability = 0.0;
    foreach ($crisis_keywords as $level => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $crisis_probability = match($level) {
                    'high' => 0.8,
                    'medium' => 0.6,
                    'low' => 0.3,
                    default => 0.0
                };
                break 2;
            }
        }
    }
    
    // Extract keywords (simple approach)
    $keywords = [];
    $title_words = explode(' ', strtolower($article['title']));
    foreach ($title_words as $word) {
        $word = trim($word, '.,!?;:()[]');
        if (strlen($word) > 4) { // Only longer words
            $keywords[] = $word;
        }
    }
    $keywords = array_slice(array_unique($keywords), 0, 5); // Max 5 keywords
    
    // Generate employment impact description
    $employment_impact = '';
    if ($economic_score > 0) {
        $employment_impact = 'Positiivinen vaikutus työllisyyteen odotettavissa';
    } elseif ($economic_score < 0) {
        $employment_impact = 'Mahdollisia negatiivisia vaikutuksia työllisyyteen';
    } else {
        $employment_impact = 'Ei merkittäviä suoria vaikutuksia työllisyyteen';
    }
    
    return [
        'relevance_score' => min(10, max(1, 5 + $economic_score)),
        'economic_impact' => $economic_impact,
        'employment_impact' => $employment_impact,
        'key_sectors' => $relevant_sectors,
        'sentiment' => $sentiment,
        'crisis_probability' => $crisis_probability,
        'summary' => substr($article['title'], 0, 150) . (strlen($article['title']) > 150 ? '...' : ''),
        'keywords' => $keywords,
        'regional_significance' => empty($relevant_sectors) ? 'Alueellinen merkitys arvioitavana' : 'Merkitystä sektoreille: ' . implode(', ', $relevant_sectors),
        'long_term_impact' => $crisis_probability > 0.5 ? 'Seurantaa vaativa tilanne' : 'Normaali kehitys',
        'analysis_method' => 'rule_based'
    ];
}

// Helper function to store comprehensive analysis like mediaseuranta_analyzer
function storeComprehensiveAnalysis($db_connection, $article_id, $analysis_data) {
    if (!$db_connection) {
        return false;
    }
    
    try {
        // Store full analysis in news_analysis table (JSON format)
        $stmt = $db_connection->prepare("
            INSERT INTO news_analysis (article_id, analysis_data, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            analysis_data = VALUES(analysis_data), 
            updated_at = NOW()
        ");
        
        if ($stmt) {
            $full_analysis_json = json_encode($analysis_data);
            $stmt->bind_param("is", $article_id, $full_analysis_json);
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
    } catch (Exception $e) {
        error_log('Failed to store comprehensive analysis: ' . $e->getMessage());
        return false;
    }
    
    return false;
}

// Helper function for simple crisis detection (kept for backward compatibility)
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
                SET analysis_status = 'analyzed'
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
                        
                        // Run comprehensive analysis like mediaseuranta_analyzer
                        $comprehensive_analysis = runComprehensiveAnalysis($article, $openai_api_key);
                        
                        // Store comprehensive analysis results
                        storeComprehensiveAnalysis($db_connection, $article['id'], $comprehensive_analysis);
                        
                        // Generate alert based on comprehensive analysis
                        $crisis_probability = $comprehensive_analysis['crisis_probability'] ?? 0.0;
                        $relevance_score = $comprehensive_analysis['relevance_score'] ?? 5;
                        
                        // Generate alert if crisis probability is high enough OR high relevance
                        if ($crisis_probability > 0.4 || $relevance_score >= 8) {
                            $alert_type = $crisis_probability > 0.6 ? 'crisis_detected' : 'high_relevance';
                            
                            $high_priority_alerts[] = [
                                'type' => $alert_type,
                                'article_id' => $article['id'],
                                'title' => $article['title'],
                                'probability' => $crisis_probability,
                                'relevance_score' => $relevance_score,
                                'economic_impact' => $comprehensive_analysis['economic_impact'] ?? 'neutral',
                                'employment_impact' => $comprehensive_analysis['employment_impact'] ?? '',
                                'key_sectors' => $comprehensive_analysis['key_sectors'] ?? [],
                                'sentiment' => $comprehensive_analysis['sentiment'] ?? 'neutral',
                                'summary' => $comprehensive_analysis['summary'] ?? '',
                                'keywords' => $comprehensive_analysis['keywords'] ?? [],
                                'severity' => $crisis_probability > 0.6 ? 'high' : ($crisis_probability > 0.3 ? 'medium' : 'low'),
                                'detected_at' => date('Y-m-d H:i:s'),
                                'source' => 'comprehensive_ai_analysis',
                                'analysis_method' => $comprehensive_analysis['analysis_method'] ?? 'unknown'
                            ];
                        }
                    }
                    
                    $result = [
                        'status' => 'analysis_complete',
                        'message' => "Comprehensive analysis completed for $processed_count recent articles",
                        'alerts' => $high_priority_alerts,
                        'count' => count($high_priority_alerts),
                        'processed_articles' => $processed_count,
                        'environment' => 'server',
                        'analysis_method' => $openai_api_key ? 'openai_comprehensive' : 'rule_based_comprehensive',
                        'features' => [
                            'relevance_scoring',
                            'economic_impact_analysis',
                            'employment_impact_assessment',
                            'sector_identification',
                            'sentiment_analysis',
                            'crisis_probability_assessment',
                            'keyword_extraction',
                            'comprehensive_storage'
                        ]
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