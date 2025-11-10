<?php
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
            'YLE Urheilu' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_URHEILU',
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
            
        default:
            echo json_encode([
                'available_actions' => [
                    'collect' => 'Collect and store news articles',
                    'recent' => 'Get recent articles from database',
                    'stats' => 'Get database statistics'
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