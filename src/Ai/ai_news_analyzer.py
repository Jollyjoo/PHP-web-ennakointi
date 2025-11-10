#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
AI News Analysis using OpenAI API
Analyzes mediaseuranta news for insights, sentiment, and trends
"""

import json
import sys
import requests
from datetime import datetime
import re

def analyze_news_with_openai(news_text, api_key, analysis_type="sentiment"):
    """
    Analyze news text using OpenAI API
    
    Args:
        news_text (str): The news content to analyze
        api_key (str): OpenAI API key
        analysis_type (str): Type of analysis - 'sentiment', 'themes', 'summary'
    """
    
    # Define analysis prompts
    prompts = {
        "sentiment": """
        Analysoi seuraavan uutistekstin tunnelma ja sentiment. 
        Anna vastaus JSON-muodossa suomeksi:
        {
            "sentiment": "positiivinen/negatiivinen/neutraali",
            "confidence": 0.0-1.0,
            "key_emotions": ["tunne1", "tunne2"],
            "reasoning": "lyhyt perustelu"
        }
        
        Uutisteksti:
        """,
        
        "themes": """
        Tunnista seuraavan uutistekstin pääaiheet ja teemat.
        Anna vastaus JSON-muodossa suomeksi:
        {
            "main_themes": ["teema1", "teema2", "teema3"],
            "categories": ["kategoria1", "kategoria2"],
            "key_topics": ["aihe1", "aihe2"],
            "business_impact": "korkea/keskitaso/matala",
            "region_focus": "alue jos mainittu"
        }
        
        Uutisteksti:
        """,
        
        "summary": """
        Luo lyhyt tiivistelmä seuraavasta uutistekstistä.
        Anna vastaus JSON-muodossa suomeksi:
        {
            "summary": "2-3 lauseen tiivistelmä",
            "key_points": ["pääkohta1", "pääkohta2", "pääkohta3"],
            "importance": "korkea/keskitaso/matala",
            "action_items": ["toimenpide1", "toimenpide2"]
        }
        
        Uutisteksti:
        """
    }
    
    try:
        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json"
        }
        
        prompt = prompts.get(analysis_type, prompts["sentiment"])
        full_prompt = prompt + news_text
        
        payload = {
            "model": "gpt-3.5-turbo",
            "messages": [
                {"role": "system", "content": "Olet asiantuntija, joka analysoi suomalaisia uutisia ja mediasisältöä."},
                {"role": "user", "content": full_prompt}
            ],
            "max_tokens": 500,
            "temperature": 0.3
        }
        
        response = requests.post(
            "https://api.openai.com/v1/chat/completions",
            headers=headers,
            json=payload,
            timeout=30
        )
        
        if response.status_code == 200:
            result = response.json()
            ai_response = result['choices'][0]['message']['content']
            
            # Try to extract JSON from the response
            try:
                # Look for JSON in the response
                json_match = re.search(r'\{.*\}', ai_response, re.DOTALL)
                if json_match:
                    analysis_result = json.loads(json_match.group())
                else:
                    analysis_result = {"raw_response": ai_response}
            except:
                analysis_result = {"raw_response": ai_response}
                
            return {
                "success": True,
                "analysis": analysis_result,
                "analysis_type": analysis_type,
                "timestamp": datetime.now().isoformat()
            }
        else:
            return {
                "success": False,
                "error": f"OpenAI API error: {response.status_code}",
                "message": response.text
            }
            
    except Exception as e:
        return {
            "success": False,
            "error": "Request failed",
            "message": str(e)
        }

def main():
    """Main function for CGI execution"""
    print("Content-Type: application/json; charset=utf-8")
    print()
    
    try:
        # Get parameters from environment or command line
        # In real use, these would come from POST data or environment variables
        
        # Example usage - you'd pass real data here
        sample_news = """
        Hämeen ELY-keskus on myöntänyt avustusta paikallisille yrityksille 
        vihreän siirtymän edistämiseksi. Avustukset tukevat yritysten 
        investointeja uusiin teknologioihin ja ympäristöystävällisiin ratkaisuihin. 
        Yhteensä myönnettiin 2,5 miljoonaa euroa avustuksia 15 yritykselle.
        """
        
        # Note: In production, API key should come from environment variable
        # api_key = os.environ.get('OPENAI_API_KEY')
        api_key = "your-openai-api-key-here"  # Replace with actual key
        
        if api_key == "your-openai-api-key-here":
            result = {
                "success": False,
                "error": "OpenAI API key not configured",
                "demo_mode": True,
                "message": "Set up OpenAI API key to enable AI analysis",
                "sample_analysis": {
                    "sentiment": "positiivinen",
                    "confidence": 0.8,
                    "key_emotions": ["optimismi", "toiveikas"],
                    "reasoning": "Uutinen kertoo myönteisestä avustuspäätöksestä"
                }
            }
        else:
            # Perform actual analysis
            result = analyze_news_with_openai(sample_news, api_key, "sentiment")
        
        print(json.dumps(result, ensure_ascii=False, indent=2))
        
    except Exception as e:
        error_result = {
            "success": False,
            "error": "Script execution failed",
            "message": str(e),
            "timestamp": datetime.now().isoformat()
        }
        print(json.dumps(error_result, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()