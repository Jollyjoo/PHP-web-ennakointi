<?php
// Include the centralized OpenAI limits configuration
require_once 'openai_limits_config.php';

/**
 * Enhanced News Collection System with Database Storage
 * Saves real news articles to database for AI analysis
 */

header('Content-Type: application/json; charset=utf-8');

class DatabaseNewsCollector {
    
    private $db;
    
    public function __construct() {
        // Create database connection
        try {
            $this->db = new mysqli('tulevaisuusluotain.fi', 'catbxjbt_Christian', 'Juustonaksu5', 'catbxjbt_ennakointi');
            $this->db->set_charset("utf8mb4");
            
            if ($this->db->connect_error) {
                throw new Exception('Database connection failed: ' . $this->db->connect_error);
            }
            
            // Check if tables exist
            $result = $this->db->query("SHOW TABLES LIKE 'news_articles'");
            if ($result->num_rows == 0) {
                throw new Exception('news_articles table does not exist. Please run create_news_tables.sql first.');
            }
            
        } catch (Exception $e) {
            throw new Exception('Database setup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if article already has analysis in database
     */
    private function hasAnalysis($article_id) {
        $stmt = $this->db->prepare("SELECT id FROM news_analysis WHERE article_id = ?");
        $stmt->bind_param("i", $article_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    /**
     * Store analysis results in news_analysis table
     */
    private function storeAnalysis($article_id, $analysis_data) {
        try {
            // Extract key fields from analysis data
            $sentiment = $analysis_data['sentiment'] ?? 'neutral';
            $themes = isset($analysis_data['themes']) ? json_encode($analysis_data['themes']) : '[]';
            $entities = isset($analysis_data['entities']) ? json_encode($analysis_data['entities']) : '[]';
            $crisis_probability = $analysis_data['crisis_probability'] ?? 0.0;
            
            $stmt = $this->db->prepare("
                INSERT INTO news_analysis 
                (article_id, analysis_data, sentiment, themes, entities, crisis_probability) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                analysis_data = VALUES(analysis_data),
                sentiment = VALUES(sentiment),
                themes = VALUES(themes),
                entities = VALUES(entities),
                crisis_probability = VALUES(crisis_probability),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $analysis_json = json_encode($analysis_data);
            $stmt->bind_param("issssd", 
                $article_id, 
                $analysis_json, 
                $sentiment, 
                $themes, 
                $entities, 
                $crisis_probability
            );
            
            $stmt->execute();
            
            // Update analysis status in news_articles
            $update_stmt = $this->db->prepare("UPDATE news_articles SET analysis_status = 'analyzed' WHERE id = ?");
            $update_stmt->bind_param("i", $article_id);
            $update_stmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to store analysis for article $article_id: " . $e->getMessage());
            
            // Mark analysis as failed
            $fail_stmt = $this->db->prepare("UPDATE news_articles SET analysis_status = 'failed' WHERE id = ?");
            $fail_stmt->bind_param("i", $article_id);
            $fail_stmt->execute();
            
            return false;
        }
    }
    
    /**
     * Run AI analysis on unanalyzed articles
     */
    public function analyzeUnanalyzedArticles() {
        set_time_limit(300); // 5 minutes max
        
        // Get batch size from request parameter, default to 5 for stability
        $batch_size = isset($_GET['batch_size']) ? max(1, min(10, (int)$_GET['batch_size'])) : NEWS_ANALYSIS_LIMIT;
        $unanalyzed = $this->getUnanalyzedArticles($batch_size);
        
        $results = [
            'success' => true,
            'total_unanalyzed' => count($unanalyzed),
            'analyzed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'processing_time' => null,
            'batch_size' => $batch_size
        ];
        
        $start_time = microtime(true);
        
        if (empty($unanalyzed)) {
            $results['message'] = 'No articles need analysis';
            return $results;
        }
        
        foreach ($unanalyzed as $article) {
            try {
                // Check if analysis already exists (double-check)
                if ($this->hasAnalysis($article['id'])) {
                    $results['skipped']++;
                    continue;
                }
                
                // Run basic analysis 
                $analysis_result = $this->runBasicAnalysis($article);
                
                if ($analysis_result && !isset($analysis_result['error'])) {
                    // Store successful analysis
                    if ($this->storeAnalysis($article['id'], $analysis_result)) {
                        $results['analyzed']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to store analysis for article {$article['id']}";
                    }
                } else {
                    $results['failed']++;
                    $error_msg = $analysis_result['error'] ?? 'Analysis failed with unknown error';
                    $results['errors'][] = "Article {$article['id']}: {$error_msg}";
                }
                
                // Add small delay to prevent API rate limiting
                usleep(500000); // 0.5 seconds
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Article {$article['id']}: Exception - " . $e->getMessage();
            }
        }
        
        $results['processing_time'] = round(microtime(true) - $start_time, 2) . ' seconds';
        $results['success_rate'] = $results['analyzed'] > 0 ? round(($results['analyzed'] / ($results['analyzed'] + $results['failed'])) * 100, 1) : 0;
        
        return $results;
    }
    
    /**
     * Get unanalyzed articles for AI processing
     */
    public function getUnanalyzedArticles($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT id, title, content, source, published_date, collected_at
            FROM news_articles 
            WHERE analysis_status = 'pending' 
            ORDER BY collected_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $articles = [];
        while ($row = $result->fetch_assoc()) {
            $articles[] = $row;
        }
        
        return $articles;
    }
    
    /**
     * Collect and store news articles
     */
    public function collectAndStoreNews() {
        $collection_stats = [
            'sources_attempted' => 0,
            'total_articles_found' => 0,
            'new_articles_stored' => 0,
            'duplicates_skipped' => 0,
            'hame_relevant_articles' => 0,
            'errors' => [],
            'source_details' => []
        ];
        
        // RSS sources with correct URLs
        $rss_sources = [
            'YLE Uutiset' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_UUTISET',
            'YLE News' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_NEWS',
            'YLE Talous' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_UUTISET&categories=18-276'
        ];
        
        foreach ($rss_sources as $source_name => $rss_url) {
            $collection_stats['sources_attempted']++;
            
            try {
                $articles = $this->fetchArticlesFromRSS($rss_url, $source_name);
                $collection_stats['total_articles_found'] += count($articles);
                
                $source_stats = [
                    'articles_found' => count($articles),
                    'new_stored' => 0,
                    'duplicates' => 0,
                    'hame_relevant' => 0,
                    'status' => 'success'
                ];
                
                foreach ($articles as $article) {
                    $store_result = $this->storeArticle($article);
                    
                    if ($store_result === 'new') {
                        $collection_stats['new_articles_stored']++;
                        $source_stats['new_stored']++;
                        
                        if ($article['relevant_to_hame']) {
                            $collection_stats['hame_relevant_articles']++;
                            $source_stats['hame_relevant']++;
                        }
                    } elseif ($store_result === 'duplicate') {
                        $collection_stats['duplicates_skipped']++;
                        $source_stats['duplicates']++;
                    }
                }
                
                $collection_stats['source_details'][$source_name] = $source_stats;
                
            } catch (Exception $e) {
                $collection_stats['errors'][] = "Error with $source_name: " . $e->getMessage();
                $collection_stats['source_details'][$source_name] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Get latest database statistics
        $db_stats = $this->getDatabaseStats();
        
        return [
            'success' => true,
            'collection_time' => date('Y-m-d H:i:s'),
            'collection_stats' => $collection_stats,
            'database_stats' => $db_stats,
            'message' => "Collected {$collection_stats['new_articles_stored']} new articles, {$collection_stats['hame_relevant_articles']} relevant to Häme"
        ];
    }
    
    /**
     * Fetch articles from RSS feed
     */
    private function fetchArticlesFromRSS($rss_url, $source_name) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL not available');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $rss_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ELY-keskus-newsbot/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/xml, text/xml',
                'Accept-Language: fi,en',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $rss_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($rss_content === false || !empty($curl_error)) {
            throw new Exception("cURL error: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP error: $http_code");
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rss_content);
        
        if ($xml === false) {
            $xml_errors = libxml_get_errors();
            $error_messages = array_map(function($e) { return trim($e->message); }, $xml_errors);
            throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        // Extract articles
        $articles = [];
        $items = $xml->channel->item ?? $xml->item ?? [];
        
        foreach ($items as $item) {
            $title = (string)$item->title;
            $description = (string)($item->description ?? $item->summary ?? '');
            $link = (string)$item->link;
            $pub_date = (string)$item->pubDate;
            
            // Clean content
            $clean_description = strip_tags($description);
            $clean_description = html_entity_decode($clean_description, ENT_QUOTES, 'UTF-8');
            
            // Check relevance to Häme
            $relevance_result = $this->checkHameRelevance($title, $clean_description);
            
            $articles[] = [
                'title' => $title,
                'content' => $clean_description,
                'url' => $link,
                'source' => $source_name,
                'published_date' => $this->parseDate($pub_date),
                'collected_at' => date('Y-m-d H:i:s'),
                'relevant_to_hame' => $relevance_result['is_relevant'],
                'matched_keywords' => $relevance_result['keywords'],
                'region_relevance' => json_encode($relevance_result)
            ];
        }
        
        return $articles;
    }
    
    /**
     * Check if article is relevant to Häme region
     */
    private function checkHameRelevance($title, $content) {
        $hame_keywords = [
            'location' => ['häme', 'hämeenlinna', 'riihimäki', 'forssa', 'janakkala', 'hattula', 'hausjärvi', 'loppi', 'tammela'],
            'business' => ['ely-keskus', 'elinkeinoelämä', 'yritystuki', 'innovaatio', 'teknologia', 'startup'],
            'employment' => ['työllisyys', 'työpaikat', 'rekrytointi', 'työnhaku', 'ammattikoulutus'],
            'education' => ['koulutus', 'opiskelu', 'korkeakoulu', 'ammattikoulu', 'HAMK'],
            'economy' => ['talous', 'investointi', 'rahoitus', 'kasvu', 'kehitysohjelma']
        ];
        
        $text_to_check = strtolower($title . ' ' . $content);
        $matched_keywords = [];
        $relevance_score = 0;
        
        foreach ($hame_keywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_to_check, $keyword) !== false) {
                    $matched_keywords[] = $keyword;
                    $relevance_score += ($category === 'location') ? 3 : 1; // Location keywords more important
                }
            }
        }
        
        return [
            'is_relevant' => $relevance_score >= 2, // Requires at least 2 points or 1 location match
            'keywords' => $matched_keywords,
            'score' => $relevance_score,
            'categories' => array_keys($hame_keywords)
        ];
    }
    
    /**
     * Store article in database
     */
    private function storeArticle($article) {
        try {
            // Check for duplicates
            $check_stmt = $this->db->prepare("
                SELECT id FROM news_articles 
                WHERE title = ? AND source = ? 
                LIMIT 1
            ");
            
            $check_stmt->bind_param("ss", $article['title'], $article['source']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                return 'duplicate';
            }
            
            // Insert new article
            $insert_stmt = $this->db->prepare("
                INSERT INTO news_articles 
                (title, content, url, source, published_date, collected_at, analysis_status, region_relevance) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $insert_stmt->bind_param("sssssss", 
                $article['title'],
                $article['content'],
                $article['url'],
                $article['source'],
                $article['published_date'],
                $article['collected_at'],
                $article['region_relevance']
            );
            
            if ($insert_stmt->execute()) {
                return 'new';
            } else {
                throw new Exception('Insert failed: ' . $insert_stmt->error);
            }
            
        } catch (Exception $e) {
            throw new Exception('Storage error: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse publication date
     */
    private function parseDate($date_string) {
        if (empty($date_string)) {
            return date('Y-m-d H:i:s');
        }
        
        $timestamp = strtotime($date_string);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        $stats = [];
        
        // Total articles
        $result = $this->db->query("SELECT COUNT(*) as total FROM news_articles");
        $stats['total_articles'] = $result->fetch_assoc()['total'];
        
        // Articles by source
        $result = $this->db->query("
            SELECT source, COUNT(*) as count 
            FROM news_articles 
            GROUP BY source 
            ORDER BY count DESC
        ");
        
        $stats['by_source'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_source'][$row['source']] = $row['count'];
        }
        
        // Recent articles (last 24 hours)
        $result = $this->db->query("
            SELECT COUNT(*) as recent 
            FROM news_articles 
            WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['recent_24h'] = $result->fetch_assoc()['recent'];
        
        // Pending analysis
        $result = $this->db->query("
            SELECT COUNT(*) as pending 
            FROM news_articles 
            WHERE analysis_status = 'pending'
        ");
        $stats['pending_analysis'] = $result->fetch_assoc()['pending'];
        
        return $stats;
    }
    
    /**
     * Get recent articles for display
     */
    public function getRecentArticles($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT id, title, content, source, published_date, collected_at, region_relevance
            FROM news_articles 
            ORDER BY collected_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $articles = [];
        while ($row = $result->fetch_assoc()) {
            $row['content'] = substr($row['content'], 0, 200) . '...';
            $articles[] = $row;
        }
        
        return $articles;
    }

    /**
     * Get weekly statistics and analyzed articles for reporting
     */
    public function getWeeklyStats($start_date, $end_date) {
        try {
            // Get analyzed articles within the date range
            $stmt = $this->db->prepare("
                SELECT 
                    na.id,
                    na.title,
                    na.content,
                    na.source,
                    na.published_date,
                    na.collected_at,
                    nana.analysis_data,
                    nana.sentiment,
                    nana.themes,
                    nana.entities,
                    nana.crisis_probability,
                    nana.created_at as analysis_date,
                    na.region_relevance
                FROM news_articles na
                LEFT JOIN news_analysis nana ON na.id = nana.article_id
                WHERE na.collected_at >= ? AND na.collected_at <= ?
                AND na.analysis_status = 'analyzed'
                ORDER BY na.collected_at DESC
            ");
            
            $start_datetime = $start_date . ' 00:00:00';
            $end_datetime = $end_date . ' 23:59:59';
            $stmt->bind_param("ss", $start_datetime, $end_datetime);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $analyzed_articles = [];
            while ($row = $result->fetch_assoc()) {
                // Parse JSON fields
                $analysis_data = $row['analysis_data'] ? json_decode($row['analysis_data'], true) : null;
                $themes = $row['themes'] ? json_decode($row['themes'], true) : [];
                $entities = $row['entities'] ? json_decode($row['entities'], true) : [];
                
                // Format for dashboard consumption
                $article = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'content' => substr($row['content'], 0, 300) . '...',
                    'source' => $row['source'],
                    'published_date' => $row['published_date'],
                    'collected_at' => $row['collected_at'],
                    'analysis_date' => $row['analysis_date'],
                    // Sentiment data
                    'sentiment' => $row['sentiment'],
                    'ai_sentiment' => $row['sentiment'],
                    // Sectors/themes data 
                    'themes' => $row['themes'],
                    'ai_key_sectors_parsed' => $themes,
                    // Entities/keywords
                    'entities' => $row['entities'],
                    'ai_keywords_parsed' => $entities,
                    // Crisis probability
                    'crisis_probability' => $row['crisis_probability'],
                    'ai_crisis_probability' => $row['crisis_probability'],
                    // Region relevance
                    'region_relevance' => $row['region_relevance'],
                    // Full analysis data
                    'analysis_data' => $analysis_data
                ];
                
                $analyzed_articles[] = $article;
            }
            
            // Calculate summary statistics
            $stats = [
                'total' => count($analyzed_articles),
                'date_range' => [
                    'start' => $start_date,
                    'end' => $end_date
                ],
                'sources' => [],
                'sentiment_distribution' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0
                ],
                'crisis_levels' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ]
            ];
            
            // Calculate statistics
            foreach ($analyzed_articles as $article) {
                // Count by source
                $source = $article['source'];
                $stats['sources'][$source] = ($stats['sources'][$source] ?? 0) + 1;
                
                // Count sentiment
                $sentiment = strtolower($article['sentiment'] ?? 'neutral');
                if (strpos($sentiment, 'positive') !== false || strpos($sentiment, 'myönteinen') !== false) {
                    $stats['sentiment_distribution']['positive']++;
                } elseif (strpos($sentiment, 'negative') !== false || strpos($sentiment, 'kielteinen') !== false) {
                    $stats['sentiment_distribution']['negative']++;
                } else {
                    $stats['sentiment_distribution']['neutral']++;
                }
                
                // Count crisis levels
                $crisis_prob = $article['crisis_probability'] ?? 0;
                if ($crisis_prob > 0.7) {
                    $stats['crisis_levels']['high']++;
                } elseif ($crisis_prob > 0.3) {
                    $stats['crisis_levels']['medium']++;
                } else {
                    $stats['crisis_levels']['low']++;
                }
            }
            
            return [
                'analyzed_articles' => $analyzed_articles,
                'stats' => $stats,
                'success' => true
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Failed to get weekly stats: ' . $e->getMessage(),
                'analyzed_articles' => [],
                'stats' => ['total' => 0],
                'success' => false
            ];
        }
    }

    /**
     * Run basic analysis on an article using OpenAI
     */
    private function runBasicAnalysis($article) {
        try {
            // Load OpenAI configuration
            require_once __DIR__ . '/config.php';
            $openai_key = getOpenAIKey();
            
            if (empty($openai_key)) {
                throw new Exception('OpenAI API key not configured');
            }
            
            // Prepare analysis prompt
            $prompt = "Analysoi seuraava uutisartikkeli Hämeen alueen näkökulmasta. Anna analyysi JSON-muodossa:

Artikkeli:
Otsikko: " . $article['title'] . "
Sisältö: " . substr($article['content'], 0, 1000) . "

Vastaa JSON-muodossa:
{
    \"relevance_score\": 1-10,
    \"region_impact\": \"korkea/keskitaso/matala\",
    \"economic_impact\": \"positiivinen/neutraali/negatiivinen\",
    \"sectors\": [\"lista relevanteista sektoreista\"],
    \"employment_impact\": \"kuvaus työllisyysvaikutuksista\",
    \"key_insights\": [\"lista tärkeimmistä havainnoista\"],
    \"summary\": \"lyhyt yhteenveto\"
}";

            // Make OpenAI API call
            $data = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Olet Hämeen alueen kehitysasiantuntija. Analysoi uutisia alueen talouden ja työllisyyden näkökulmasta.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 800,
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
            
            // If JSON parsing fails, return raw analysis
            return [
                'raw_analysis' => $analysis_text,
                'relevance_score' => 5,
                'region_impact' => 'keskitaso',
                'summary' => 'Automaattinen analyysi epäonnistui, manuaalinen tarkistus suositeltava'
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Analysis failed: ' . $e->getMessage(),
                'relevance_score' => 1,
                'region_impact' => 'matala'
            ];
        }
    }
}

// Handle requests
try {
    $action = $_GET['action'] ?? 'collect';
    
    switch ($action) {
        case 'collect':
            $collector = new DatabaseNewsCollector();
            $result = $collector->collectAndStoreNews();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'recent':
            $collector = new DatabaseNewsCollector();
            $limit = $_GET['limit'] ?? 10;
            $articles = $collector->getRecentArticles($limit);
            echo json_encode([
                'recent_articles' => $articles,
                'count' => count($articles),
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'stats':
            $collector = new DatabaseNewsCollector();
            $stats = $collector->getDatabaseStats();
            echo json_encode([
                'database_stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'analyze':
            $collector = new DatabaseNewsCollector();
            $result = $collector->analyzeUnanalyzedArticles();
            echo json_encode([
                'analysis_result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'weekly_stats':
            $collector = new DatabaseNewsCollector();
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            $result = $collector->getWeeklyStats($start_date, $end_date);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        default:
            echo json_encode([
                'available_actions' => [
                    'collect' => 'Collect and store news articles',
                    'recent' => 'Get recent articles from database',
                    'stats' => 'Get database statistics',
                    'analyze' => 'Analyze unanalyzed articles with AI',
                    'weekly_stats' => 'Get weekly statistics and analyzed articles (supports start_date and end_date parameters)'
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