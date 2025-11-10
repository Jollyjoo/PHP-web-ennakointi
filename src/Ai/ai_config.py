"""
Secure Python configuration for OpenAI API
Reads API key from config file instead of hardcoding
"""

import json
import os
from pathlib import Path

def get_openai_config():
    """
    Get OpenAI configuration from environment or config file
    Priority: 1) Environment variable, 2) Config file
    """
    
    # Try environment variable first (most secure)
    api_key = os.getenv('OPENAI_API_KEY')
    


    if api_key:
        return {
            'openai_api_key': api_key,  # Use consistent field name
            'model': 'gpt-3.5-turbo',
            'max_tokens': 1500,
            'temperature': 0.3
        }
    
    # Fallback to config file
    config_path = Path(__file__).parent / 'ai_config.json'
    
    try:
        if config_path.exists():
            with open(config_path, 'r') as f:
                config = json.load(f)
                return config
        else:
            raise FileNotFoundError(f"Config file not found: {config_path}")
            
    except Exception as e:
        raise Exception(f"Failed to load OpenAI configuration: {e}")

def get_api_key():
    """Get just the API key"""
    config = get_openai_config()
    # Handle both 'openai_api_key' and 'api_key' field names for compatibility
    return config.get('openai_api_key') or config.get('api_key')