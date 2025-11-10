#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Advanced News Intelligence System for Hämeen ELY-keskus
Uses OpenAI to provide deep insights into mediaseuranta data
"""

import json
import requests
from datetime import datetime, timedelta
import re

OPENAI_API_KEY = "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"

def analyze_news_portfolio(news_articles):
    """
    Analyze multiple news articles to generate comprehensive insights
    """
    
    # Combine all news for context analysis
    combined_text = "\n\n".join([article['content'] for article in news_articles])
    
    portfolio_prompt = f"""
    Analysoi seuraava kokoelma Hämeen alueen uutisia ja luo kattava analyysi.
    
    Anna vastaus JSON-muodossa:
    {{
        "overall_sentiment": "positiivinen/negatiivinen/neutraali",
        "key_trends": ["trendi1", "trendi2", "trendi3"],
        "emerging_themes": ["teema1", "teema2"],
        "economic_indicators": {{
            "investment_activity": "korkea/keskitaso/matala",
            "employment_outlook": "positiivinen/negatiivinen/vakaa",
            "business_confidence": "kasvava/laskeva/vakaa"
        }},
        "stakeholder_impact": {{
            "businesses": "impact_description",
            "job_seekers": "impact_description", 
            "local_government": "impact_description"
        }},
        "alerts": [
            {{"type": "opportunity/threat/neutral", "description": "alert_text", "urgency": "high/medium/low"}}
        ],
        "recommendations": [
            {{"action": "recommended_action", "target": "target_group", "timeline": "immediate/short_term/long_term"}}
        ],
        "market_intelligence": {{
            "competitive_landscape": "analysis",
            "investment_opportunities": ["opportunity1", "opportunity2"],
            "risk_factors": ["risk1", "risk2"]
        }}
    }}
    
    Uutiskokoelma:
    {combined_text[:4000]}  # Limit to avoid token limits
    """
    
    return call_openai_analysis(portfolio_prompt)

def generate_executive_summary(analysis_data):
    """
    Generate executive summary for decision makers
    """
    
    summary_prompt = f"""
    Luo johtotason yhteenveto seuraavasta uutisanalyysistä ELY-keskuksen johdolle.
    
    Analyysitiedot: {json.dumps(analysis_data, ensure_ascii=False)}
    
    Anna vastaus JSON-muodossa:
    {{
        "executive_summary": "2-3 kappaleen tiivistelmä tärkeimmistä havainnoista",
        "key_metrics": {{
            "sentiment_score": 0.0-1.0,
            "activity_level": "korkea/keskitaso/matala",
            "strategic_importance": "kriittinen/tärkeä/informatiivinen"
        }},
        "immediate_actions": ["toimenpide1", "toimenpide2"],
        "strategic_implications": "pitkän aikavälin vaikutukset",
        "budget_implications": "budjettivaikutukset jos merkittäviä",
        "stakeholder_communication": "viestintäsuositukset"
    }}
    """
    
    return call_openai_analysis(summary_prompt)

def detect_crisis_signals(news_text):
    """
    Early warning system for potential crises
    """
    
    crisis_prompt = f"""
    Analysoi seuraava uutisteksti mahdollisten kriisien tai uhkien varalta.
    
    Anna vastaus JSON-muodossa:
    {{
        "crisis_probability": 0.0-1.0,
        "crisis_type": "taloudellinen/sosiaalinen/ympäristö/maine/ei_kriisiä",
        "severity": "matala/keskitaso/korkea/kriittinen",
        "affected_areas": ["alue1", "alue2"],
        "timeline": "välitön/lyhyt_aikaväli/pitkä_aikaväli",
        "mitigation_strategies": ["strategia1", "strategia2"],
        "monitoring_indicators": ["indikaattori1", "indikaattori2"],
        "communication_needs": "viestintätarpeet"
    }}
    
    Uutisteksti: {news_text}
    """
    
    return call_openai_analysis(crisis_prompt)

def generate_competitive_intelligence(news_text):
    """
    Extract competitive intelligence from news
    """
    
    competitive_prompt = f"""
    Analysoi seuraava uutisteksti kilpailutiedustelun näkökulmasta.
    
    Anna vastaus JSON-muodossa:
    {{
        "competitors_mentioned": ["yritys1", "yritys2"],
        "competitive_moves": ["siirto1", "siirto2"],
        "market_opportunities": ["mahdollisuus1", "mahdollisuus2"],
        "funding_intelligence": {{
            "sources": ["rahoituslähde1"],
            "amounts": ["summa1"],
            "purposes": ["tarkoitus1"]
        }},
        "partnership_opportunities": ["kumppanuus1", "kumppanuus2"],
        "strategic_insights": "strategiset oivallukset",
        "action_recommendations": ["suositus1", "suositus2"]
    }}
    
    Uutisteksti: {news_text}
    """
    
    return call_openai_analysis(competitive_prompt)

def call_openai_analysis(prompt):
    """
    Helper function to call OpenAI API
    """
    try:
        headers = {
            "Authorization": f"Bearer {OPENAI_API_KEY}",
            "Content-Type": "application/json"
        }
        
        payload = {
            "model": "gpt-4",  # Using GPT-4 for more sophisticated analysis
            "messages": [
                {
                    "role": "system", 
                    "content": "Olet kokenut strateginen analyytikko, joka erikoistuu alueelliseen kehitykseen, talousanalyysiin ja päätöksenteon tukemiseen. Työskentelet Hämeen ELY-keskukselle."
                },
                {"role": "user", "content": prompt}
            ],
            "max_tokens": 1500,
            "temperature": 0.3
        }
        
        response = requests.post(
            "https://api.openai.com/v1/chat/completions",
            headers=headers,
            json=payload,
            timeout=60
        )
        
        if response.status_code == 200:
            result = response.json()
            ai_response = result["choices"][0]["message"]["content"]
            
            # Extract JSON from response
            json_match = re.search(r'\\{.*\\}', ai_response, re.DOTALL)
            if json_match:
                return json.loads(json_match.group())
            else:
                return {"raw_response": ai_response, "parse_error": True}
        else:
            return {"error": f"OpenAI API error: {response.status_code}"}
            
    except Exception as e:
        return {"error": f"Analysis failed: {str(e)}"}

# Example usage
if __name__ == "__main__":
    # Sample news articles
    sample_articles = [
        {
            "title": "ELY myönsi 2.5M€ vihreän teknologian avustuksia",
            "content": "Hämeen ELY-keskus myönsi yhteensä 2,5 miljoonaa euroa avustuksia...",
            "date": "2025-11-07"
        }
    ]
    
    # Run comprehensive analysis
    portfolio_analysis = analyze_news_portfolio(sample_articles)
    executive_summary = generate_executive_summary(portfolio_analysis)
    
    print(json.dumps({
        "portfolio_analysis": portfolio_analysis,
        "executive_summary": executive_summary,
        "timestamp": datetime.now().isoformat()
    }, ensure_ascii=False, indent=2))