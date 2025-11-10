<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * AI News Analysis API
 * Uses Python + OpenAI API to analyze mediaseuranta news
 */

function analyzeNewsWithAI($newsText, $analysisType = 'sentiment') {
    try {
        // Escape the news text for command line
        $escapedText = escapeshellarg($newsText);
        $escapedType = escapeshellarg($analysisType);
        
        // Create temporary Python script with the news text
        $tempScript = tempnam(sys_get_temp_dir(), 'ai_analysis_');
        $pythonCode = '
import json
import sys
import requests
from datetime import datetime
import re

news_text = ' . json_encode($newsText) . '
analysis_type = ' . json_encode($analysisType) . '

# OpenAI API Configuration
OPENAI_API_KEY = "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"  # Replace with your actual API key

def analyze_with_openai(text, analysis_type):
    """Advanced AI analysis using OpenAI GPT"""
    
    if OPENAI_API_KEY == "YOUR_OPENAI_API_KEY_HERE":
        return simple_fallback_analysis(text, analysis_type)
    
    prompts = {
        "sentiment": """
Analysoi seuraavan suomalaisen uutistekstin tunnelma ja sentiment tarkkaan. 
Anna vastaus JSON-muodossa:
{
    "sentiment": "positiivinen/negatiivinen/neutraali",
    "confidence": 0.0-1.0,
    "key_emotions": ["tunne1", "tunne2"],
    "reasoning": "lyhyt perustelu suomeksi",
    "impact_assessment": "vaikutus yhteiskuntaan: korkea/keskitaso/matala",
    "tone": "virallinen/epävirallinen/neutraali"
}

Uutisteksti:
""",
        
        "themes": """
Tunnista seuraavan suomalaisen uutistekstin pääaiheet, teemat ja kategoriat tarkkaan.
Anna vastaus JSON-muodossa:
{
    "main_themes": ["teema1", "teema2", "teema3"],
    "categories": ["kategoria1", "kategoria2"],
    "key_topics": ["aihe1", "aihe2", "aihe3"],
    "business_impact": "korkea/keskitaso/matala",
    "region_focus": "alue jos mainittu tai null",
    "stakeholders": ["toimija1", "toimija2"],
    "urgency": "kiireellinen/normaali/ei_kiireellinen"
}

Uutisteksti:
""",
        
        "summary": """
Luo älykäs tiivistelmä ja analyysi seuraavasta suomalaisesta uutistekstistä.
Anna vastaus JSON-muodossa:
{
    "summary": "2-3 lauseen tiivistelmä pääkohdista",
    "key_points": ["pääkohta1", "pääkohta2", "pääkohta3"],
    "importance": "korkea/keskitaso/matala",
    "action_items": ["toimenpide1", "toimenpide2"],
    "target_audience": ["yleisö1", "yleisö2"],
    "follow_up_needed": true/false,
    "related_topics": ["aihe1", "aihe2"]
}

Uutisteksti:
"""
    }
    
    try:
        headers = {
            "Authorization": f"Bearer {OPENAI_API_KEY}",
            "Content-Type": "application/json"
        }
        
        prompt = prompts.get(analysis_type, prompts["sentiment"])
        full_prompt = prompt + text
        
        payload = {
            "model": "gpt-3.5-turbo",
            "messages": [
                {"role": "system", "content": "Olet asiantuntija, joka analysoi suomalaisia uutisia, mediasisältöä ja viranomaistiedotteita. Keskity erityisesti ELY-keskusten, työllisyyden, talouden ja aluekehityksen uutisiin."},
                {"role": "user", "content": full_prompt}
            ],
            "max_tokens": 800,
            "temperature": 0.2
        }
        
        response = requests.post(
            "https://api.openai.com/v1/chat/completions",
            headers=headers,
            json=payload,
            timeout=30
        )
        
        if response.status_code == 200:
            result = response.json()
            ai_response = result["choices"][0]["message"]["content"]
            
            # Extract JSON from response
            try:
                json_match = re.search(r"\\{.*\\}", ai_response, re.DOTALL)
                if json_match:
                    analysis_result = json.loads(json_match.group())
                    analysis_result["analysis_method"] = "openai_gpt"
                    analysis_result["model_used"] = "gpt-3.5-turbo"
                    return analysis_result
                else:
                    return {"raw_response": ai_response, "analysis_method": "openai_gpt", "parse_error": True}
            except Exception as parse_error:
                return {"raw_response": ai_response, "analysis_method": "openai_gpt", "parse_error": str(parse_error)}
                
        else:
            return {"error": f"OpenAI API error: {response.status_code}", "details": response.text}
            
    except Exception as e:
        return {"error": "OpenAI request failed", "details": str(e)}

def simple_fallback_analysis(text, analysis_type):
    """Fallback to rule-based analysis if OpenAI is not available"""
    
    if analysis_type == "sentiment":
        return simple_sentiment_analysis(text)
    elif analysis_type == "themes":
        themes = extract_themes(text)
        return {
            "main_themes": themes,
            "theme_count": len(themes),
            "analysis_method": "rule_based_fallback"
        }
    else:
        return {
            "summary": text[:200] + "..." if len(text) > 200 else text,
            "length": len(text),
            "analysis_method": "simple_truncation"
        }

# Simple rule-based analysis for fallback
def simple_sentiment_analysis(text):
    positive_words = ["hyvä", "onnistui", "kasvu", "menestys", "paranee", "nousu", "voitto", "edistys", "kehitys", "myönteinen", "avustus", "tuki", "investointi", "hyväksyttiin", "myönnettiin"]
    negative_words = ["huono", "epäonnistui", "lasku", "tappio", "heikkenee", "ongelma", "kriisi", "vaikeus", "leikkaus", "vähenee", "sulkeminen", "irtisanominen", "konkurssiin"]
    
    text_lower = text.lower()
    positive_count = sum(1 for word in positive_words if word in text_lower)
    negative_count = sum(1 for word in negative_words if word in text_lower)
    
    if positive_count > negative_count:
        sentiment = "positiivinen"
        confidence = min(0.9, 0.5 + (positive_count - negative_count) * 0.1)
    elif negative_count > positive_count:
        sentiment = "negatiivinen" 
        confidence = min(0.9, 0.5 + (negative_count - positive_count) * 0.1)
    else:
        sentiment = "neutraali"
        confidence = 0.5
    
    return {
        "sentiment": sentiment,
        "confidence": confidence,
        "positive_indicators": positive_count,
        "negative_indicators": negative_count,
        "analysis_method": "rule_based"
    }

def extract_themes(text):
    themes = []
    text_lower = text.lower()
    
    theme_keywords = {
        "työllisyys": ["työllisyys", "työpaikka", "työ", "työvoima", "rekrytointi", "irtisanominen", "työllistäminen"],
        "talous": ["talous", "investointi", "avustus", "rahoitus", "budjetti", "euro", "miljoonaa", "maksaa"],
        "koulutus": ["koulutus", "opiskelu", "kurssi", "kouluttautuminen", "opetus", "yliopisto", "ammattikoulu"],
        "yritykset": ["yritys", "yritykset", "yrittäjyys", "startup", "pk-yritys", "tehdas", "toiminta"],
        "teknologia": ["teknologia", "digitalisaatio", "tekoäly", "automatisointi", "innovaatio", "kehitys"],
        "ympäristö": ["ympäristö", "vihreä", "kestävä", "päästöt", "energia", "ilmasto", "luonto"]
    }
    
    for theme, keywords in theme_keywords.items():
        if any(keyword in text_lower for keyword in keywords):
            themes.append(theme)
    
    return themes

# Main analysis logic
try:
    if OPENAI_API_KEY != "YOUR_OPENAI_API_KEY_HERE":
        result = analyze_with_openai(news_text, analysis_type)
        method = "openai_gpt_analysis"
        note = "Advanced AI analysis powered by OpenAI GPT-3.5-turbo"
    else:
        result = simple_fallback_analysis(news_text, analysis_type)
        method = "rule_based_fallback"
        note = "Using rule-based analysis. Configure OpenAI API key for advanced AI features."
        
except Exception as analysis_error:
    # Fallback to simple analysis if OpenAI fails
    result = simple_fallback_analysis(news_text, analysis_type)
    method = "rule_based_emergency_fallback"
    note = f"OpenAI analysis failed, using fallback: {str(analysis_error)}"

output = {
    "success": True,
    "analysis": result,
    "analysis_type": analysis_type,
    "timestamp": datetime.now().isoformat(),
    "method": method,
    "note": note
}

print(json.dumps(output, ensure_ascii=False, indent=2))
';
        
        file_put_contents($tempScript, $pythonCode);
        
        // Execute Python script
        $command = "python3 $tempScript 2>&1";
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        // Clean up temp file
        unlink($tempScript);
        
        if ($returnCode === 0) {
            $result = implode("\n", $output);
            $jsonResult = json_decode($result, true);
            
            if ($jsonResult) {
                return $jsonResult;
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to parse Python output',
                    'raw_output' => $result
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => 'Python execution failed',
                'return_code' => $returnCode,
                'output' => implode("\n", $output)
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'PHP execution failed',
            'message' => $e->getMessage()
        ];
    }
}

// Handle the request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $newsText = $input['news_text'] ?? '';
        $analysisType = $input['analysis_type'] ?? 'sentiment';
    } else {
        // GET request for testing
        $newsText = $_GET['news_text'] ?? 'Hämeen ELY-keskus myönsi avustuksia paikallisille yrityksille vihreän siirtymän edistämiseksi. Yhteensä 2,5 miljoonaa euroa jaettiin 15 yritykselle uusien teknologioiden käyttöönottoa varten.';
        $analysisType = $_GET['analysis_type'] ?? 'sentiment';
    }
    
    if (empty($newsText)) {
        throw new Exception('No news text provided');
    }
    
    $result = analyzeNewsWithAI($newsText, $analysisType);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>