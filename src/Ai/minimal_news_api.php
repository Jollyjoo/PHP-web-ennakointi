<?php
// Working News Intelligence API - Step by step enhancement
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

/**
 * Simple AI Alert System - Real functionality without complex class structure
 */

// Helper function to get recent news from database
function getRecentNewsForAlerts($db_connection, $hours = 24, $limit = 1) {
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

// Helper function to get analyzed articles for competitive intelligence
function getAnalyzedArticlesForCompetitive($db_connection, $days = 30, $limit = 1) {
    if (!$db_connection) {
        return [];
    }
    
    $stmt = $db_connection->prepare("
        SELECT id, title, content, published_date 
        FROM news_articles 
        WHERE published_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND analysis_status = 'analyzed'
        AND (competitive_analysis_status IS NULL OR competitive_analysis_status = 'pending')
        ORDER BY published_date DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $days, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function for competitive analysis
function runCompetitiveAnalysis($article, $openai_key = null) {
    if ($openai_key) {
        return runCompetitiveOpenAIAnalysis($article, $openai_key);
    } else {
        return runCompetitiveFallbackAnalysis($article);
    }
}

// OpenAI competitive analysis
function runCompetitiveOpenAIAnalysis($article, $openai_key) {
    try {
        $prompt = "Analysoi tämä uutisartikkeli kilpailutiedustelun näkökulmasta. Anna vastaus JSON-muodossa:
{
    \"competitors_mentioned\": [\"yritys1\", \"yritys2\"],
    \"competitive_moves\": [\"siirto1\", \"siirto2\"],
    \"market_opportunities\": [\"mahdollisuus1\", \"mahdollisuus2\"],
    \"funding_intelligence\": {
        \"sources\": [\"rahoituslähde1\"],
        \"amounts\": [\"summa1\"],
        \"purposes\": [\"tarkoitus1\"]
    },
    \"partnership_opportunities\": [\"kumppanuus1\", \"kumppanuus2\"],
    \"strategic_insights\": \"strategiset oivallukset\",
    \"action_recommendations\": [\"suositus1\", \"suositus2\"]
}

Otsikko: {$article['title']}
Sisältö: " . substr($article['content'], 0, 2000);

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Olet kokenut kilpailutiedustelu-analyytikko Hämeen ELY-keskukselle.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.3
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $content = $result['choices'][0]['message']['content'];
                
                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $analysis = json_decode($matches[0], true);
                    if ($analysis) {
                        return $analysis;
                    }
                }
            }
        }
        
        return runCompetitiveFallbackAnalysis($article);
        
    } catch (Exception $e) {
        return runCompetitiveFallbackAnalysis($article);
    }
}

// Fallback competitive analysis
function runCompetitiveFallbackAnalysis($article) {
    $text = strtolower($article['title'] . ' ' . $article['content']);
    
    // Simple keyword-based competitive analysis
    $competitors = [];
    $funding = ['sources' => [], 'amounts' => [], 'purposes' => []];
    $opportunities = [];
    
    // Look for company mentions
    if (preg_match_all('/(\w+\s+oy|\w+\s+oyj|\w+\s+ltd|\w+\s+ab)/i', $article['content'], $matches)) {
        $competitors = array_unique($matches[0]);
    }
    
    // Look for funding mentions
    if (preg_match_all('/(\d+(?:\.\d+)?\s*(?:miljoonaa|million|M€|€))/i', $article['content'], $matches)) {
        $funding['amounts'] = $matches[0];
    }
    
    // Look for opportunities
    $opportunity_keywords = ['investointi', 'mahdollisuus', 'kasvu', 'kehitys', 'hanke'];
    foreach ($opportunity_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $opportunities[] = ucfirst($keyword) . ' havaittu';
        }
    }
    
    return [
        'competitors_mentioned' => array_slice($competitors, 0, 5),
        'competitive_moves' => [],
        'market_opportunities' => $opportunities,
        'funding_intelligence' => $funding,
        'partnership_opportunities' => [],
        'strategic_insights' => 'Automaattinen analyysi suoritettu',
        'action_recommendations' => []
    ];
}

// Helper function to store competitive results
function storeCompetitiveResults($db_connection, $article_id, $competitive_data) {
    try {
        // Try to update news_analysis table with competitive data
        $competitive_json = json_encode($competitive_data);
        $competitors_json = json_encode($competitive_data['competitors_mentioned'] ?? []);
        $funding_json = json_encode($competitive_data['funding_intelligence'] ?? []);
        $opportunities_json = json_encode($competitive_data['market_opportunities'] ?? []);
        
        // Calculate simple competitive score
        $score = 0.0;
        if (!empty($competitive_data['competitors_mentioned'])) $score += 0.3;
        if (!empty($competitive_data['funding_intelligence']['amounts'])) $score += 0.4;
        if (!empty($competitive_data['market_opportunities'])) $score += 0.3;
        
        $business_relevance = $score >= 0.7 ? 'high' : ($score >= 0.4 ? 'medium' : ($score > 0 ? 'low' : 'none'));
        
        // Update or insert competitive analysis
        $stmt = $db_connection->prepare("
            INSERT INTO news_analysis (
                article_id, competitive_analysis, competitors_mentioned, 
                funding_intelligence, market_opportunities, competitive_score, 
                business_relevance, competitive_analyzed_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                competitive_analysis = VALUES(competitive_analysis),
                competitors_mentioned = VALUES(competitors_mentioned),
                funding_intelligence = VALUES(funding_intelligence),
                market_opportunities = VALUES(market_opportunities),
                competitive_score = VALUES(competitive_score),
                business_relevance = VALUES(business_relevance),
                competitive_analyzed_at = NOW(),
                updated_at = NOW()
        ");
        
        if ($stmt) {
            $stmt->bind_param("issssds", $article_id, $competitive_json, $competitors_json, 
                            $funding_json, $opportunities_json, $score, $business_relevance);
            $result = $stmt->execute();
            
            // Mark article as competitively analyzed
            if ($result) {
                $update_stmt = $db_connection->prepare("
                    UPDATE news_articles 
                    SET competitive_analysis_status = 'analyzed', 
                        competitive_analyzed_at = NOW() 
                    WHERE id = ?
                ");
                if ($update_stmt) {
                    $update_stmt->bind_param("i", $article_id);
                    $update_stmt->execute();
                }
            }
            
            return $result;
        }
        
        return false;
        
    } catch (Exception $e) {
        // If database storage fails, still return true to continue processing
        error_log("Competitive analysis storage failed: " . $e->getMessage());
        return false;
    }
}

// Helper function to get mediaseuranta entries for analysis
function getMediaseurantaEntries($db_connection, $days = 330, $limit = 1) {
    if (!$db_connection) {
        return [];
    }
    
    $stmt = $db_connection->prepare("
        SELECT * FROM Mediaseuranta 
        WHERE uutisen_pvm >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND (ai_analysis_status IS NULL OR ai_analysis_status = 'pending')
        ORDER BY uutisen_pvm DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $days, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function to get analyzed mediaseuranta data for insights
function getAnalyzedMediaseurantaData($db_connection, $days = 30) {
    if (!$db_connection) {
        return [];
    }
    
    $stmt = $db_connection->prepare("
        SELECT 
            Teema,
            uutisen_pvm,
            Uutinen,
            ai_relevance_score,
            ai_economic_impact,
            ai_sentiment,
            ai_crisis_probability,
            ai_key_sectors,
            ai_summary
        FROM Mediaseuranta 
        WHERE uutisen_pvm >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ai_analysis_status = 'completed'
        ORDER BY uutisen_pvm DESC
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function for mediaseuranta analysis
function runMediaseurantaAnalysis($entry, $openai_key = null) {
    if ($openai_key) {
        return runMediaseurantaOpenAIAnalysis($entry, $openai_key);
    } else {
        return runMediaseurantaFallbackAnalysis($entry);
    }
}

// OpenAI mediaseuranta analysis
function runMediaseurantaOpenAIAnalysis($entry, $openai_key) {
    try {
        $prompt = "Analysoi tämä mediaseurantauutinen Hämeen alueen näkökulmasta. Anna vastaus JSON-muodossa:
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
}

Teema: {$entry['Teema']}
Uutinen: {$entry['Uutinen']}
Päivämäärä: {$entry['uutisen_pvm']}
Hankkeen luokitus: {$entry['Hankkeen_luokitus']}";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Olet Hämeen alueen kehitysasiantuntija. Analysoi mediaseurantauutisia alueen talouden ja työllisyyden näkökulmasta.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 800,
            'temperature' => 0.3
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $content = $result['choices'][0]['message']['content'];
                
                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $analysis = json_decode($matches[0], true);
                    if ($analysis) {
                        return $analysis;
                    }
                }
            }
        }
        
        return runMediaseurantaFallbackAnalysis($entry);
        
    } catch (Exception $e) {
        return runMediaseurantaFallbackAnalysis($entry);
    }
}

// Fallback mediaseuranta analysis
function runMediaseurantaFallbackAnalysis($entry) {
    $text = strtolower($entry['Teema'] . ' ' . $entry['Uutinen']);
    
    // Determine economic impact based on theme and content
    $positive_keywords = ['investointi', 'kasvu', 'uudet työpaikat', 'laajennus', 'kehitys'];
    $negative_keywords = ['irtisanominen', 'konkurssi', 'supistus', 'kriisi', 'sulkeminen', 'lomautus'];
    
    $positive_score = 0;
    $negative_score = 0;
    
    foreach ($positive_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) $positive_score++;
    }
    
    foreach ($negative_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) $negative_score++;
    }
    
    $economic_impact = 'neutral';
    $sentiment = 'neutral';
    $crisis_probability = 0.0;
    $relevance_score = 5;
    
    if ($positive_score > $negative_score) {
        $economic_impact = 'positive';
        $sentiment = 'positive';
        $relevance_score = min(10, 6 + $positive_score);
    } elseif ($negative_score > $positive_score) {
        $economic_impact = 'negative';
        $sentiment = 'negative';
        $crisis_probability = min(1.0, 0.3 + ($negative_score * 0.2));
        $relevance_score = min(10, 7 + $negative_score);
    }
    
    // Extract sectors from theme
    $sectors = [];
    $theme = $entry['Teema'] ?? '';
    if (strpos($theme, 'Investoinnit') !== false) $sectors[] = 'Investoinnit';
    if (strpos($theme, 'Julkinen talous') !== false) $sectors[] = 'Julkinen sektori';
    if (strpos($theme, 'Muutosneuvottelut') !== false) $sectors[] = 'Yritystoiminta';
    
    return [
        'relevance_score' => $relevance_score,
        'economic_impact' => $economic_impact,
        'employment_impact' => $economic_impact === 'positive' ? 'Positiivinen vaikutus työllisyyteen' : 
                              ($economic_impact === 'negative' ? 'Negatiivinen vaikutus työllisyyteen' : 'Neutraali vaikutus'),
        'key_sectors' => $sectors,
        'sentiment' => $sentiment,
        'crisis_probability' => $crisis_probability,
        'summary' => substr($entry['Uutinen'], 0, 150) . '...',
        'keywords' => explode(' ', substr(str_replace([',', '.', '!', '?'], '', $text), 0, 100)),
        'regional_significance' => 'Merkitys Hämeen alueelle arvioitu',
        'long_term_impact' => $crisis_probability > 0.5 ? 'Seurantaa vaativa tilanne' : 'Normaali kehitys',
        'analysis_method' => 'rule_based'
    ];
}

// Helper function to store mediaseuranta analysis results
function storeMediaseurantaResults($db_connection, $entry_url, $analysis_data) {
    try {
        $stmt = $db_connection->prepare("
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
            WHERE Url = ?
        ");
        
        if ($stmt) {
            $sectors_json = json_encode($analysis_data['key_sectors'] ?? []);
            $keywords_json = json_encode(array_slice($analysis_data['keywords'] ?? [], 0, 10));
            $full_analysis_json = json_encode($analysis_data);
            
            $stmt->bind_param(
                "issssdssss", 
                $analysis_data['relevance_score'],
                $analysis_data['economic_impact'],
                $analysis_data['employment_impact'],
                $sectors_json,
                $analysis_data['sentiment'],
                $analysis_data['crisis_probability'],
                $analysis_data['summary'],
                $keywords_json,
                $full_analysis_json,
                $entry_url
            );
            
            return $stmt->execute();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Mediaseuranta analysis storage failed: " . $e->getMessage());
        return false;
    }
}

// ================= MEDIASEURANTA COMPETITIVE INTELLIGENCE FUNCTIONS =================

// Helper function to get mediaseuranta entries for competitive analysis
function getMediaseurantaForCompetitive($db_connection, $days = 30, $limit = 1) {
    if (!$db_connection) {
        return [];
    }
    
    // Get latest entries that haven't been competitively analyzed yet
    // Since table has no ID column, we'll use URL as identifier (should be unique)
    $stmt = $db_connection->prepare("
        SELECT Maakunta_ID, Teema, Uutinen, uutisen_pvm, Url, Hankkeen_luokitus 
        FROM Mediaseuranta 
        WHERE (competitive_analysis_status IS NULL OR competitive_analysis_status = 'pending')
        AND Url IS NOT NULL AND Url != ''
        ORDER BY uutisen_pvm DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper function for mediaseuranta competitive analysis
function runMediaseurantaCompetitiveAnalysis($entry, $openai_key = null) {
    if ($openai_key) {
        return runMediaseurantaCompetitiveOpenAIAnalysis($entry, $openai_key);
    } else {
        return runMediaseurantaCompetitiveFallbackAnalysis($entry);
    }
}

// OpenAI competitive analysis for mediaseuranta
function runMediaseurantaCompetitiveOpenAIAnalysis($entry, $openai_key) {
    try {
        $prompt = "Analysoi tämä mediaseurantauutinen kilpailutiedustelun näkökulmasta Hämeen alueelle. Anna vastaus JSON-muodossa:
{
    \"competitors_mentioned\": [\"yritys1\", \"yritys2\"],
    \"competitive_moves\": [\"strateginen siirto1\", \"siirto2\"],
    \"market_opportunities\": [\"markkinamahdollisuus1\", \"mahdollisuus2\"],
    \"funding_intelligence\": {
        \"sources\": [\"rahoituslähde1\"],
        \"amounts\": [\"summa1\"],
        \"purposes\": [\"tarkoitus1\"]
    },
    \"partnership_opportunities\": [\"kumppanuusmahdollisuus1\"],
    \"competitive_threats\": [\"kilpailuuhka1\", \"uhka2\"],
    \"strategic_importance\": 1-5,
    \"market_intelligence\": {
        \"trends\": [\"trendi1\"],
        \"disruptions\": [\"häiriö1\"],
        \"growth_areas\": [\"kasvualue1\"]
    },
    \"action_recommendations\": [\"suositus1\", \"suositus2\"]
}

Teema: {$entry['Teema']}
Uutinen: {$entry['Uutinen']}
Päivämäärä: {$entry['uutisen_pvm']}
Hankkeen luokitus: {$entry['Hankkeen_luokitus']}";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Olet kokenut kilpailutiedustelu-analyytikko Hämeen ELY-keskukselle. Analysoi mediaseurantauutisia kilpailutiedustelun ja liiketoimintamahdollisuuksien näkökulmasta.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1200,
            'temperature' => 0.3
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $content = $result['choices'][0]['message']['content'];
                
                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $analysis = json_decode($matches[0], true);
                    if ($analysis) {
                        return $analysis;
                    }
                }
            }
        }
        
        return runMediaseurantaCompetitiveFallbackAnalysis($entry);
        
    } catch (Exception $e) {
        return runMediaseurantaCompetitiveFallbackAnalysis($entry);
    }
}

// Fallback competitive analysis for mediaseuranta
function runMediaseurantaCompetitiveFallbackAnalysis($entry) {
    $text = strtolower($entry['Teema'] . ' ' . $entry['Uutinen']);
    
    // Extract competitive information
    $competitors = [];
    $funding = ['sources' => [], 'amounts' => [], 'purposes' => []];
    $opportunities = [];
    $threats = [];
    
    // Look for company mentions
    if (preg_match_all('/(\w+\s+oy|\w+\s+oyj|\w+\s+ltd|\w+\s+ab|\w+\s+group)/i', $entry['Uutinen'], $matches)) {
        $competitors = array_unique($matches[0]);
    }
    
    // Look for funding mentions
    if (preg_match_all('/(\d+(?:\.\d+)?\s*(?:miljoonaa|million|M€|€|miljardia))/i', $entry['Uutinen'], $matches)) {
        $funding['amounts'] = $matches[0];
    }
    
    // Opportunities based on theme and content
    $opportunity_keywords = ['investointi', 'laajennus', 'uudet työpaikat', 'kasvu', 'kehitys', 'hanke', 'yhteistyö'];
    foreach ($opportunity_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $opportunities[] = ucfirst($keyword) . ' havaittu mediaseurannassa';
        }
    }
    
    // Threats based on negative indicators
    $threat_keywords = ['konkurssi', 'irtisanominen', 'sulkeminen', 'supistus', 'lomautus', 'kriisi'];
    foreach ($threat_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $threats[] = ucfirst($keyword) . ' havaittu mediaseurannassa';
        }
    }
    
    // Strategic importance based on theme
    $strategic_importance = 3; // Default medium
    if (strpos($text, 'investointi') !== false || strpos($text, 'kansainvälistyminen') !== false) {
        $strategic_importance = 4;
    }
    if (!empty($threats)) {
        $strategic_importance = 5;
    }
    
    return [
        'competitors_mentioned' => array_slice($competitors, 0, 5),
        'competitive_moves' => [],
        'market_opportunities' => $opportunities,
        'funding_intelligence' => $funding,
        'partnership_opportunities' => [],
        'competitive_threats' => $threats,
        'strategic_importance' => $strategic_importance,
        'market_intelligence' => [
            'trends' => [],
            'disruptions' => $threats,
            'growth_areas' => $opportunities
        ],
        'action_recommendations' => []
    ];
}

// Helper function to store mediaseuranta competitive results
function storeMediaseurantaCompetitiveResults($db_connection, $entry_url, $competitive_data) {
    try {
        $competitive_json = json_encode($competitive_data);
        $competitors_json = json_encode($competitive_data['competitors_mentioned'] ?? []);
        $funding_json = json_encode($competitive_data['funding_intelligence'] ?? []);
        $opportunities_json = json_encode($competitive_data['market_opportunities'] ?? []);
        $threats_json = json_encode($competitive_data['competitive_threats'] ?? []);
        $market_intel_json = json_encode($competitive_data['market_intelligence'] ?? []);
        $recommendations_json = json_encode($competitive_data['action_recommendations'] ?? []);
        
        // Calculate competitive score
        $score = 0.0;
        if (!empty($competitive_data['competitors_mentioned'])) $score += 0.25;
        if (!empty($competitive_data['funding_intelligence']['amounts'])) $score += 0.35;
        if (!empty($competitive_data['market_opportunities'])) $score += 0.25;
        if (!empty($competitive_data['competitive_threats'])) $score += 0.15;
        
        $business_relevance = $score >= 0.7 ? 'high' : ($score >= 0.4 ? 'medium' : ($score > 0 ? 'low' : 'none'));
        $strategic_importance = $competitive_data['strategic_importance'] ?? 3;
        
        $stmt = $db_connection->prepare("
            UPDATE Mediaseuranta SET
                competitive_analysis_status = 'analyzed',
                competitive_analyzed_at = NOW(),
                competitive_analysis = ?,
                competitors_mentioned = ?,
                funding_intelligence = ?,
                market_opportunities = ?,
                partnership_opportunities = ?,
                competitive_score = ?,
                business_relevance = ?,
                strategic_importance = ?,
                competitive_threats = ?,
                market_intelligence = ?,
                action_recommendations = ?
            WHERE Url = ?
        ");
        
        if ($stmt) {
            $partnerships_json = json_encode($competitive_data['partnership_opportunities'] ?? []);
            
            $stmt->bind_param(
                "sssssssissss", 
                $competitive_json,
                $competitors_json,
                $funding_json,
                $opportunities_json,
                $partnerships_json,
                $score,
                $business_relevance,
                $strategic_importance,
                $threats_json,
                $market_intel_json,
                $recommendations_json,
                $entry_url
            );
            
            return $stmt->execute();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Mediaseuranta competitive analysis storage failed: " . $e->getMessage());
        return false;
    }
}

// Helper function to get competitive intelligence insights from mediaseuranta
function getMediaseurantaCompetitiveInsights($db_connection, $days = 30) {
    if (!$db_connection) {
        return [];
    }
    
    // Get all competitively analyzed entries, not restricted by date
    $stmt = $db_connection->prepare("
        SELECT 
            Url, Teema, Uutinen, uutisen_pvm,
            competitive_score, business_relevance, strategic_importance,
            competitors_mentioned, funding_intelligence, market_opportunities,
            competitive_threats, market_intelligence, action_recommendations
        FROM Mediaseuranta 
        WHERE competitive_analysis_status = 'analyzed'
        ORDER BY competitive_score DESC, strategic_importance DESC
        LIMIT 50
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
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
                    // Get recent unanalyzed news (limit to 1 for cost control)
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
            
        case 'competitive_intelligence':
            if (!$database_available) {
                $result = [
                    'error' => 'Database not available',
                    'message' => 'Competitive intelligence requires server-side database connection.',
                    'environment' => 'local_development',
                    'fallback_available' => false
                ];
            } else {
                try {
                    // Get analyzed articles for competitive intelligence (limit 1 for cost protection)
                    $days = $_GET['days'] ?? 30;
                    $recent_articles = getAnalyzedArticlesForCompetitive($db_connection, $days, 5);
                    
                    $companies_mentioned = [];
                    $funding_activities = [];
                    $market_opportunities = [];
                    $processed_count = 0;
                    $stored_count = 0;
                    
                    foreach ($recent_articles as $article) {
                        $competitive_intel = runCompetitiveAnalysis($article, $openai_api_key);
                        $processed_count++;
                        
                        // Store competitive intelligence in database
                        if (storeCompetitiveResults($db_connection, $article['id'], $competitive_intel)) {
                            $stored_count++;
                        }
                        
                        // Aggregate data for dashboard
                        if (!empty($competitive_intel['competitors_mentioned'])) {
                            foreach ($competitive_intel['competitors_mentioned'] as $company) {
                                $companies_mentioned[$company] = ($companies_mentioned[$company] ?? 0) + 1;
                            }
                        }
                        
                        if (!empty($competitive_intel['funding_intelligence']['amounts'])) {
                            $funding_activities[] = [
                                'article_id' => $article['id'],
                                'article_title' => $article['title'],
                                'sources' => $competitive_intel['funding_intelligence']['sources'] ?? [],
                                'amounts' => $competitive_intel['funding_intelligence']['amounts'] ?? [],
                                'purposes' => $competitive_intel['funding_intelligence']['purposes'] ?? []
                            ];
                        }
                        
                        if (!empty($competitive_intel['market_opportunities'])) {
                            $market_opportunities = array_merge($market_opportunities, $competitive_intel['market_opportunities']);
                        }
                    }
                    
                    $result = [
                        'period_days' => $days,
                        'articles_analyzed' => $processed_count,
                        'analyses_stored' => $stored_count,
                        'cost_protection' => 'Limited to 1 article maximum',
                        'companies_activity' => $companies_mentioned,
                        'funding_activities' => $funding_activities,
                        'market_opportunities' => array_unique($market_opportunities),
                        'generated_at' => date('Y-m-d H:i:s'),
                        'environment' => 'server'
                    ];
                    
                } catch (Exception $e) {
                    $result = [
                        'error' => 'Competitive analysis failed: ' . $e->getMessage(),
                        'articles_analyzed' => 0,
                        'analyses_stored' => 0,
                        'cost_protection' => 'Error occurred',
                        'companies_activity' => [],
                        'funding_activities' => [],
                        'market_opportunities' => [],
                        'environment' => 'server'
                    ];
                }
            }
            break;
            
        case 'mediaseuranta_analysis':
            if (!$database_available) {
                $result = [
                    'error' => 'Database not available',
                    'message' => 'Mediaseuranta analysis requires server-side database connection.',
                    'environment' => 'local_development',
                    'fallback_available' => false
                ];
            } else {
                try {
                    // First, analyze unanalyzed entries (limit 1 for cost protection)
                    $unanalyzed_entries = getMediaseurantaEntries($db_connection, 9999, 5);
                    $analyzed_count = 0;
                    $stored_count = 0;
                    
                    // Process unanalyzed entries
                    foreach ($unanalyzed_entries as $entry) {
                        $analysis = runMediaseurantaAnalysis($entry, $openai_api_key);
                        $analyzed_count++;
                        
                        if (storeMediaseurantaResults($db_connection, $entry['Url'], $analysis)) {
                            $stored_count++;
                        }
                    }
                    
                    // Get insights from already analyzed data
                    $analyzed_data = getAnalyzedMediaseurantaData($db_connection, 9999);
                    
                    // Generate insights
                    $theme_breakdown = [];
                    $sentiment_summary = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
                    $high_impact_news = [];
                    $crisis_alerts = [];
                    $sector_activity = [];
                    
                    foreach ($analyzed_data as $item) {
                        // Theme breakdown
                        $theme = $item['Teema'] ?? 'Muut';
                        $theme_breakdown[$theme] = ($theme_breakdown[$theme] ?? 0) + 1;
                        
                        // Sentiment summary
                        $sentiment = $item['ai_sentiment'] ?? 'neutral';
                        $sentiment_summary[$sentiment]++;
                        
                        // High impact news (relevance score >= 8)
                        if (($item['ai_relevance_score'] ?? 0) >= 8) {
                            $high_impact_news[] = [
                                'title' => $item['Uutinen'],
                                'date' => $item['uutisen_pvm'],
                                'score' => $item['ai_relevance_score'],
                                'impact' => $item['ai_economic_impact'],
                                'summary' => $item['ai_summary']
                            ];
                        }
                        
                        // Crisis alerts (crisis probability >= 0.6)
                        if (($item['ai_crisis_probability'] ?? 0) >= 0.6) {
                            $crisis_alerts[] = [
                                'title' => $item['Uutinen'],
                                'date' => $item['uutisen_pvm'],
                                'probability' => $item['ai_crisis_probability'],
                                'theme' => $item['Teema']
                            ];
                        }
                        
                        // Sector activity
                        $sectors = json_decode($item['ai_key_sectors'] ?? '[]', true) ?: [];
                        foreach ($sectors as $sector) {
                            $sector_activity[$sector] = ($sector_activity[$sector] ?? 0) + 1;
                        }
                    }
                    
                    $result = [
                        'analysis_summary' => [
                            'period_days' => 'All available data',
                            'new_analyzed' => $analyzed_count,
                            'stored_analyses' => $stored_count,
                            'total_entries' => count($analyzed_data),
                            'cost_protection' => 'Limited to 1 new analysis maximum'
                        ],
                        'theme_breakdown' => $theme_breakdown,
                        'sentiment_summary' => $sentiment_summary,
                        'high_impact_news' => array_slice($high_impact_news, 0, 10), // Top 10
                        'crisis_alerts' => $crisis_alerts,
                        'sector_activity' => $sector_activity,
                        'generated_at' => date('Y-m-d H:i:s'),
                        'environment' => 'server'
                    ];
                    
                } catch (Exception $e) {
                    $result = [
                        'error' => 'Mediaseuranta analysis failed: ' . $e->getMessage(),
                        'analysis_summary' => [
                            'period_days' => 'All available data',
                            'new_analyzed' => 0,
                            'stored_analyses' => 0,
                            'total_entries' => 0,
                            'cost_protection' => 'Error occurred'
                        ],
                        'theme_breakdown' => [],
                        'sentiment_summary' => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                        'high_impact_news' => [],
                        'crisis_alerts' => [],
                        'sector_activity' => [],
                        'environment' => 'server'
                    ];
                }
            }
            break;
            
        case 'mediaseuranta_competitive':
            if (!$database_available) {
                $result = [
                    'error' => 'Database not available',
                    'message' => 'Mediaseuranta competitive intelligence requires server-side database connection.',
                    'environment' => 'local_development',
                    'fallback_available' => false
                ];
            } else {
                try {
                    // Get mediaseuranta entries ready for competitive analysis (limit 1)
                    $entries_for_analysis = getMediaseurantaForCompetitive($db_connection, 9999, 5);
                    $analyzed_count = 0;
                    $stored_count = 0;
                    
                    // Debug: Check if we found any entries
                    if (empty($entries_for_analysis)) {
                        // Try to get some basic info about the table
                        $check_query = $db_connection->query("SELECT COUNT(*) as total FROM Mediaseuranta");
                        $total_entries = $check_query ? $check_query->fetch_assoc()['total'] : 'unknown';
                        
                        $result = [
                            'analysis_summary' => [
                                'period_days' => 'All available data',
                                'new_analyzed' => 0,
                                'stored_analyses' => 0,
                                'total_competitive_insights' => 0,
                                'cost_protection' => 'No entries found to analyze',
                                'debug_info' => "Total entries in table: $total_entries"
                            ],
                            'companies_activity' => [],
                            'funding_activities' => [],
                            'market_opportunities' => [],
                            'competitive_threats' => [],
                            'strategic_high_priority' => [],
                            'action_recommendations' => [],
                            'generated_at' => date('Y-m-d H:i:s'),
                            'environment' => 'server'
                        ];
                        
                        // Still try to get existing insights
                        $competitive_insights = getMediaseurantaCompetitiveInsights($db_connection, 9999);
                        $result['analysis_summary']['total_competitive_insights'] = count($competitive_insights);
                        
                        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        return;
                    }
                    
                    // Analyze entries for competitive intelligence
                    foreach ($entries_for_analysis as $entry) {
                        $competitive_intel = runMediaseurantaCompetitiveAnalysis($entry, $openai_api_key);
                        $analyzed_count++;
                        
                        if (storeMediaseurantaCompetitiveResults($db_connection, $entry['Url'], $competitive_intel)) {
                            $stored_count++;
                        }
                    }
                    
                    // Get insights from already analyzed competitive data
                    $competitive_insights = getMediaseurantaCompetitiveInsights($db_connection, 9999);
                    
                    // Aggregate competitive intelligence
                    $companies_activity = [];
                    $funding_activities = [];
                    $market_opportunities = [];
                    $competitive_threats = [];
                    $strategic_high_priority = [];
                    $recommendations_summary = [];
                    
                    foreach ($competitive_insights as $insight) {
                        // Companies activity
                        $competitors = json_decode($insight['competitors_mentioned'] ?? '[]', true) ?: [];
                        foreach ($competitors as $company) {
                            $companies_activity[$company] = ($companies_activity[$company] ?? 0) + 1;
                        }
                        
                        // Funding activities
                        $funding = json_decode($insight['funding_intelligence'] ?? '[]', true) ?: [];
                        if (!empty($funding['amounts'])) {
                            $funding_activities[] = [
                                'entry_url' => $insight['Url'],
                                'title' => substr($insight['Uutinen'], 0, 100) . '...',
                                'date' => $insight['uutisen_pvm'],
                                'amounts' => $funding['amounts'] ?? [],
                                'sources' => $funding['sources'] ?? [],
                                'purposes' => $funding['purposes'] ?? []
                            ];
                        }
                        
                        // Market opportunities
                        $opportunities = json_decode($insight['market_opportunities'] ?? '[]', true) ?: [];
                        $market_opportunities = array_merge($market_opportunities, $opportunities);
                        
                        // Competitive threats
                        $threats = json_decode($insight['competitive_threats'] ?? '[]', true) ?: [];
                        $competitive_threats = array_merge($competitive_threats, $threats);
                        
                        // Strategic high priority (importance >= 4)
                        if (($insight['strategic_importance'] ?? 0) >= 4) {
                            $strategic_high_priority[] = [
                                'title' => $insight['Uutinen'],
                                'date' => $insight['uutisen_pvm'],
                                'importance' => $insight['strategic_importance'],
                                'score' => $insight['competitive_score'],
                                'relevance' => $insight['business_relevance']
                            ];
                        }
                        
                        // Action recommendations
                        $recommendations = json_decode($insight['action_recommendations'] ?? '[]', true) ?: [];
                        $recommendations_summary = array_merge($recommendations_summary, $recommendations);
                    }
                    
                    $result = [
                        'analysis_summary' => [
                            'period_days' => 'All available data',
                            'new_analyzed' => $analyzed_count,
                            'stored_analyses' => $stored_count,
                            'total_competitive_insights' => count($competitive_insights),
                            'cost_protection' => 'Limited to 1 new analysis maximum'
                        ],
                        'companies_activity' => $companies_activity,
                        'funding_activities' => array_slice($funding_activities, 0, 10), // Top 10
                        'market_opportunities' => array_unique(array_slice($market_opportunities, 0, 15)),
                        'competitive_threats' => array_unique(array_slice($competitive_threats, 0, 10)),
                        'strategic_high_priority' => array_slice($strategic_high_priority, 0, 8),
                        'action_recommendations' => array_unique(array_slice($recommendations_summary, 0, 12)),
                        'generated_at' => date('Y-m-d H:i:s'),
                        'environment' => 'server'
                    ];
                    
                } catch (Exception $e) {
                    $result = [
                        'error' => 'Mediaseuranta competitive analysis failed: ' . $e->getMessage(),
                        'analysis_summary' => [
                            'period_days' => 'All available data',
                            'new_analyzed' => 0,
                            'stored_analyses' => 0,
                            'total_competitive_insights' => 0,
                            'cost_protection' => 'Error occurred'
                        ],
                        'companies_activity' => [],
                        'funding_activities' => [],
                        'market_opportunities' => [],
                        'competitive_threats' => [],
                        'strategic_high_priority' => [],
                        'action_recommendations' => [],
                        'environment' => 'server'
                    ];
                }
            }
            break;
            
        default:
            $result = [
                'status' => 'Working AI News Intelligence System Active', 
                'timestamp' => date('Y-m-d H:i:s'),
                'available_actions' => ['test', 'alerts', 'competitive_intelligence', 'mediaseuranta_analysis', 'mediaseuranta_competitive'],
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