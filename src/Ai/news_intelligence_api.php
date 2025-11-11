<?php
// Set error handling to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors as HTML

header('Content-Type: application/json; charset=utf-8');

// Try to load config with error handling
try {
    if (file_exists('config.php')) {
        require_once 'config.php';
    } else {
        // Fallback configuration if config.php not found
        function getOpenAIKey() {
            // Try environment variable first
            $api_key = getenv('OPENAI_API_KEY');
            if ($api_key) {
                return $api_key;
            }
            
            // Try JSON config file
            $config_file = __DIR__ . '/ai_config.json';
            if (file_exists($config_file)) {
                $config = json_decode(file_get_contents($config_file), true);
                if (isset($config['openai_api_key'])) {
                    return $config['openai_api_key'];
                }
            }
            
            return null; // Return null instead of throwing error
        }
        
        function getDatabaseConfig() {
            return [
                'host' => 'tulevaisuusluotain.fi',
                'username' => 'catbxjbt_Christian',
                'password' => 'Juustonaksu5',
                'database' => 'catbxjbt_ennakointi',
                'charset' => 'utf8mb4'
            ];
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

/**
 * Advanced AI-Powered News Monitoring System
 * Real-time analysis and alerting for Hämeen ELY-keskus
 */

class NewsIntelligenceSystem {
    
    private $db;
    private $openai_key;
    
    public function __construct($db_connection, $openai_api_key) {
        $this->db = $db_connection;
        $this->openai_key = $openai_api_key;
    }
    
    /**
     * Process new news articles and generate alerts
     */
    public function processNewNews($news_articles) {
        $results = [];
        
        foreach ($news_articles as $article) {
            // Basic analysis
            $sentiment = $this->analyzeSentiment($article['content']);
            $themes = $this->extractThemes($article['content']);
            $entities = $this->extractEntities($article['content']);
            
            // Advanced analysis
            $crisis_signals = $this->detectCrisisSignals($article['content']);
            $competitive_intel = $this->generateCompetitiveIntelligence($article['content']);
            $impact_assessment = $this->assessImpact($article['content']);
            
            // Generate alerts if needed
            $alerts = $this->generateAlerts($sentiment, $crisis_signals, $impact_assessment);
            
            $results[] = [
                'article_id' => $article['id'],
                'analysis' => [
                    'sentiment' => $sentiment,
                    'themes' => $themes,
                    'entities' => $entities,
                    'crisis_signals' => $crisis_signals,
                    'competitive_intel' => $competitive_intel,
                    'impact_assessment' => $impact_assessment
                ],
                'alerts' => $alerts,
                'processed_at' => date('Y-m-d H:i:s')
            ];
            
            // Store in database
            $this->storeAnalysis($article['id'], $results[count($results)-1]);
        }
        
        return $results;
    }
    
    /**
     * Generate weekly intelligence report
     */
    public function generateWeeklyReport($start_date, $end_date) {
        // Get all news from period
        $news_data = $this->getNewsFromPeriod($start_date, $end_date);
        
        // Run portfolio analysis via Python
        $portfolio_analysis = $this->runPortfolioAnalysis($news_data);
        
        // Generate executive summary
        $executive_summary = $this->generateExecutiveSummary($portfolio_analysis);
        
        // Create visualizations data
        $charts_data = $this->prepareChartsData($news_data);
        
        return [
            'period' => ['start' => $start_date, 'end' => $end_date],
            'portfolio_analysis' => $portfolio_analysis,
            'executive_summary' => $executive_summary,
            'charts_data' => $charts_data,
            'total_articles_analyzed' => count($news_data),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Real-time alert system
     */
    public function checkForAlerts() {
        // Get recent news (last 24 hours) - LIMITED TO 5 ARTICLES for cost control
        $recent_news = $this->getRecentNews(24);
        
        $high_priority_alerts = [];
        
        foreach ($recent_news as $article) {
            $crisis_signals = $this->detectCrisisSignals($article['content']);
            
            // Store full analysis results for each article
            $analysis_data = [
                'article_id' => $article['id'],
                'crisis_analysis' => $crisis_signals,
                'analyzed_for' => 'alerts',
                'processed_at' => date('Y-m-d H:i:s')
            ];
            
            // Store analysis to prevent duplicate processing and mark as analyzed
            $this->storeAnalysis($article['id'], $analysis_data);
            
            if ($crisis_signals['crisis_probability'] > 0.7) {
                $high_priority_alerts[] = [
                    'type' => 'crisis_detected',
                    'article_id' => $article['id'],
                    'title' => $article['title'],
                    'probability' => $crisis_signals['crisis_probability'],
                    'crisis_type' => $crisis_signals['crisis_type'],
                    'severity' => $crisis_signals['severity'],
                    'recommended_actions' => $crisis_signals['mitigation_strategies'],
                    'detected_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $high_priority_alerts;
    }
    
    /**
     * Competitive intelligence dashboard
     */
    public function getCompetitiveIntelligence($time_period = 30) {
        $news_data = $this->getNewsFromDays($time_period);
        
        $companies_mentioned = [];
        $funding_activities = [];
        $market_moves = [];
        
        foreach ($news_data as $article) {
            $intel = $this->generateCompetitiveIntelligence($article['content']);
            
            // Aggregate competitive data
            if (!empty($intel['competitors_mentioned'])) {
                foreach ($intel['competitors_mentioned'] as $company) {
                    $companies_mentioned[$company] = ($companies_mentioned[$company] ?? 0) + 1;
                }
            }
            
            if (!empty($intel['funding_intelligence']['amounts'])) {
                $funding_activities[] = [
                    'article_id' => $article['id'],
                    'sources' => $intel['funding_intelligence']['sources'],
                    'amounts' => $intel['funding_intelligence']['amounts'],
                    'purposes' => $intel['funding_intelligence']['purposes']
                ];
            }
        }
        
        return [
            'period_days' => $time_period,
            'companies_activity' => $companies_mentioned,
            'funding_activities' => $funding_activities,
            'market_intelligence' => $this->summarizeMarketIntelligence($news_data),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Helper function to call Python analysis
     */
    private function runPythonAnalysis($script_name, $data) {
        $temp_file = tempnam(sys_get_temp_dir(), 'ai_analysis_');
        file_put_contents($temp_file, json_encode($data));
        
        $command = "python3 $script_name $temp_file 2>&1";
        $output = [];
        exec($command, $output, $return_code);
        
        unlink($temp_file);
        
        if ($return_code === 0) {
            return json_decode(implode("\n", $output), true);
        } else {
            return ['error' => 'Python analysis failed', 'output' => $output];
        }
    }
    
    /**
     * Analyze sentiment using OpenAI or fallback rules
     */
    private function analyzeSentiment($text) {
        $python_result = $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'sentiment_analysis',
            'text' => $text,
            'openai_key' => $this->openai_key
        ]);
        
        if (isset($python_result['sentiment'])) {
            return $python_result['sentiment'];
        }
        
        // Fallback: Simple rule-based sentiment
        $positive_words = ['hyvä', 'loistava', 'onnistunut', 'kasvu', 'parantunut', 'kehitys'];
        $negative_words = ['huono', 'laskussa', 'ongelma', 'kriisi', 'lasku', 'heikentynyt'];
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }
        
        if ($positive_count > $negative_count) {
            return ['label' => 'positive', 'score' => 0.7];
        } elseif ($negative_count > $positive_count) {
            return ['label' => 'negative', 'score' => 0.7];
        } else {
            return ['label' => 'neutral', 'score' => 0.5];
        }
    }
    
    /**
     * Extract themes from text
     */
    private function extractThemes($text) {
        $python_result = $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'theme_extraction',
            'text' => $text,
            'openai_key' => $this->openai_key
        ]);
        
        if (isset($python_result['themes'])) {
            return $python_result['themes'];
        }
        
        // Fallback: Keyword-based themes
        $themes = [];
        $theme_keywords = [
            'työllisyys' => ['työllisyys', 'työpaikat', 'rekrytointi', 'työnhaku'],
            'koulutus' => ['koulutus', 'opiskelu', 'korkeakoulu', 'ammattikoulu'],
            'teknologia' => ['teknologia', 'digitalisaatio', 'tekoäly', 'automaatio'],
            'talous' => ['talous', 'investointi', 'rahoitus', 'kasvu'],
            'ympäristö' => ['ympäristö', 'ilmasto', 'kestävyys', 'kierrätys']
        ];
        
        $text_lower = strtolower($text);
        foreach ($theme_keywords as $theme => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text_lower, $keyword);
            }
            if ($score > 0) {
                $themes[] = ['theme' => $theme, 'relevance_score' => min($score / 10, 1.0)];
            }
        }
        
        return $themes;
    }
    
    /**
     * Extract entities (organizations, locations, etc.)
     */
    private function extractEntities($text) {
        $python_result = $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'entity_extraction',
            'text' => $text,
            'openai_key' => $this->openai_key
        ]);
        
        if (isset($python_result['entities'])) {
            return $python_result['entities'];
        }
        
        // Fallback: Simple pattern matching
        $entities = [];
        
        // Organizations (simple pattern)
        preg_match_all('/\b[A-ZÄÖÅ][a-zäöå]+ Oy\b/', $text, $companies);
        if (!empty($companies[0])) {
            $entities['organizations'] = array_unique($companies[0]);
        }
        
        // Locations (Finnish cities/regions)
        $finnish_places = ['Helsinki', 'Tampere', 'Turku', 'Hämeenlinna', 'Lahti', 'Heinola'];
        $found_places = [];
        foreach ($finnish_places as $place) {
            if (strpos($text, $place) !== false) {
                $found_places[] = $place;
            }
        }
        if (!empty($found_places)) {
            $entities['locations'] = $found_places;
        }
        
        return $entities;
    }
    
    /**
     * Detect crisis signals in text
     */
    private function detectCrisisSignals($text) {
        $python_result = $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'crisis_detection',
            'text' => $text,
            'openai_key' => $this->openai_key
        ]);
        
        if (isset($python_result['crisis_analysis'])) {
            return $python_result['crisis_analysis'];
        }
        
        // Fallback: Rule-based crisis detection
        $crisis_keywords = [
            'high' => ['kriisi', 'katastrofi', 'romahdus', 'konkurssi', 'sulkeminen'],
            'medium' => ['ongelma', 'vaikeudet', 'laskusuhdanne', 'supistus'],
            'low' => ['haaste', 'muutos', 'epävarmuus']
        ];
        
        $text_lower = strtolower($text);
        $crisis_probability = 0;
        $crisis_type = 'none';
        $severity = 'low';
        
        foreach ($crisis_keywords as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $crisis_probability = match($level) {
                        'high' => 0.8,
                        'medium' => 0.6,
                        'low' => 0.3
                    };
                    $severity = $level;
                    $crisis_type = 'economic';
                }
            }
        }
        
        return [
            'crisis_probability' => $crisis_probability,
            'crisis_type' => $crisis_type,
            'severity' => $severity,
            'mitigation_strategies' => $crisis_probability > 0.5 ? 
                ['Monitor situation closely', 'Prepare response plan'] : 
                ['Continue monitoring']
        ];
    }
    
    /**
     * Generate competitive intelligence
     */
    private function generateCompetitiveIntelligence($text) {
        $python_result = $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'competitive_intelligence',
            'text' => $text,
            'openai_key' => $this->openai_key
        ]);
        
        if (isset($python_result['competitive_intel'])) {
            return $python_result['competitive_intel'];
        }
        
        // Fallback analysis
        return [
            'competitors_mentioned' => [],
            'funding_intelligence' => [
                'sources' => [],
                'amounts' => [],
                'purposes' => []
            ],
            'market_movements' => []
        ];
    }
    
    /**
     * Assess impact of news
     */
    private function assessImpact($text) {
        $impact_keywords = [
            'high' => ['merkittävä', 'suurvaikutus', 'mullistava', 'historiallinen'],
            'medium' => ['tärkeä', 'huomattava', 'oleellinen'],
            'low' => ['pieni', 'vähäinen', 'marginaalinen']
        ];
        
        $text_lower = strtolower($text);
        $impact_level = 'low';
        
        foreach ($impact_keywords as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $impact_level = $level;
                    break 2;
                }
            }
        }
        
        return [
            'impact_level' => $impact_level,
            'affected_sectors' => ['general'],
            'timeline' => 'short-term'
        ];
    }
    
    /**
     * Generate alerts based on analysis
     */
    private function generateAlerts($sentiment, $crisis_signals, $impact_assessment) {
        $alerts = [];
        
        if ($crisis_signals['crisis_probability'] > 0.7) {
            $alerts[] = [
                'type' => 'crisis_alert',
                'severity' => 'high',
                'message' => 'High probability crisis detected'
            ];
        }
        
        if ($sentiment['label'] === 'negative' && $impact_assessment['impact_level'] === 'high') {
            $alerts[] = [
                'type' => 'negative_impact',
                'severity' => 'medium',
                'message' => 'Negative sentiment with high impact detected'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Store analysis results in database and mark as analyzed
     */
    private function storeAnalysis($article_id, $analysis_data) {
        // Store analysis results
        $stmt = $this->db->prepare("
            INSERT INTO news_analysis (article_id, analysis_data, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            analysis_data = VALUES(analysis_data), 
            updated_at = NOW()
        ");
        
        $json_data = json_encode($analysis_data);
        $stmt->bind_param("is", $article_id, $json_data);
        $analysis_stored = $stmt->execute();
        
        // Mark article as analyzed to prevent duplicate processing
        if ($analysis_stored) {
            $update_stmt = $this->db->prepare("
                UPDATE news_articles 
                SET ai_analysis_status = 'analyzed', 
                    ai_analyzed_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->bind_param("i", $article_id);
            $update_stmt->execute();
        }
        
        return $analysis_stored;
    }
    
    /**
     * Get news from specific period
     */
    private function getNewsFromPeriod($start_date, $end_date) {
        $stmt = $this->db->prepare("
            SELECT id, title, content, published_date 
            FROM news_articles 
            WHERE published_date BETWEEN ? AND ?
            ORDER BY published_date DESC
        ");
        
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get recent news (last X hours) - LIMITED TO 5 ARTICLES for cost control
     * Only returns unanalyzed articles to avoid duplicate OpenAI API calls
     */
    private function getRecentNews($hours) {
        $stmt = $this->db->prepare("
            SELECT id, title, content, published_date 
            FROM news_articles 
            WHERE published_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND (ai_analysis_status IS NULL OR ai_analysis_status != 'analyzed')
            ORDER BY published_date DESC
            LIMIT 5
        ");
        
        $stmt->bind_param("i", $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get news from last X days - only unanalyzed articles to avoid duplicate processing
     */
    private function getNewsFromDays($days) {
        $stmt = $this->db->prepare("
            SELECT id, title, content, published_date 
            FROM news_articles 
            WHERE published_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND (ai_analysis_status IS NULL OR ai_analysis_status != 'analyzed')
            ORDER BY published_date DESC
        ");
        
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Run portfolio analysis via Python
     */
    private function runPortfolioAnalysis($news_data) {
        return $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'portfolio_analysis',
            'news_data' => $news_data,
            'openai_key' => $this->openai_key
        ]);
    }
    
    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary($portfolio_analysis) {
        return $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'executive_summary',
            'portfolio_data' => $portfolio_analysis,
            'openai_key' => $this->openai_key
        ]);
    }
    
    /**
     * Prepare charts data for visualization
     */
    private function prepareChartsData($news_data) {
        $sentiment_counts = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        $theme_counts = [];
        $daily_counts = [];
        
        foreach ($news_data as $article) {
            // Analyze each article
            $sentiment = $this->analyzeSentiment($article['content']);
            $themes = $this->extractThemes($article['content']);
            
            // Count sentiments
            $sentiment_counts[$sentiment['label']]++;
            
            // Count themes
            foreach ($themes as $theme_data) {
                $theme = $theme_data['theme'];
                $theme_counts[$theme] = ($theme_counts[$theme] ?? 0) + 1;
            }
            
            // Daily counts
            $date = date('Y-m-d', strtotime($article['published_date']));
            $daily_counts[$date] = ($daily_counts[$date] ?? 0) + 1;
        }
        
        return [
            'sentiment_distribution' => $sentiment_counts,
            'theme_distribution' => $theme_counts,
            'daily_volume' => $daily_counts
        ];
    }
    
    /**
     * Summarize market intelligence
     */
    private function summarizeMarketIntelligence($news_data) {
        return $this->runPythonAnalysis('advanced_news_intelligence.py', [
            'action' => 'market_intelligence',
            'news_data' => $news_data,
            'openai_key' => $this->openai_key
        ]);
    }
    
}

// API endpoints
try {
    // Get secure configuration with error handling
    try {
        $db_config = getDatabaseConfig();
        $db_connection = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
        $db_connection->set_charset($db_config['charset']);
        
        if ($db_connection->connect_error) {
            throw new Exception('Database connection failed: ' . $db_connection->connect_error);
        }
    } catch (Exception $e) {
        throw new Exception('Database configuration error: ' . $e->getMessage());
    }
    
    try {
        $openai_api_key = getOpenAIKey();
        if (!$openai_api_key) {
            throw new Exception('OpenAI API key not configured. Please set up ai_config.json or environment variable.');
        }
    } catch (Exception $e) {
        // Continue without OpenAI - system will use fallback methods
        $openai_api_key = null;
        error_log('OpenAI configuration warning: ' . $e->getMessage());
    }
    
    $intelligence_system = new NewsIntelligenceSystem($db_connection, $openai_api_key);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            // Simple test endpoint to verify API is working
            $result = [
                'status' => 'API Working',
                'timestamp' => date('Y-m-d H:i:s'),
                'database_connected' => $db_connection->ping(),
                'openai_configured' => !empty($openai_api_key),
                'config_method' => empty($openai_api_key) ? 'none' : 'configured'
            ];
            break;
            
        case 'weekly_report':
            if (!$openai_api_key) {
                $result = [
                    'error' => 'OpenAI API key not configured',
                    'message' => 'Weekly report requires OpenAI integration. Please upload ai_config.json to server.',
                    'fallback_available' => false
                ];
            } else {
                $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
                $end_date = $_GET['end_date'] ?? date('Y-m-d');
                $result = $intelligence_system->generateWeeklyReport($start_date, $end_date);
            }
            break;
            
        case 'alerts':
            $result = $intelligence_system->checkForAlerts();
            break;
            
        case 'competitive_intelligence':
            $days = $_GET['days'] ?? 30;
            $result = $intelligence_system->getCompetitiveIntelligence($days);
            break;
            
        default:
            $result = ['status' => 'AI News Intelligence System Active', 'timestamp' => date('Y-m-d H:i:s')];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>