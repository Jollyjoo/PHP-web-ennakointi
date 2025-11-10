# AI Configuration Setup

## Security Notice
The AI system uses OpenAI API keys which should be kept secure. This folder contains configuration files to manage API keys safely.

## Configuration Files

### 1. `config.php` - PHP Configuration
- Handles secure loading of API keys
- Supports environment variables and JSON config
- Used by PHP-based AI components

### 2. `ai_config.py` - Python Configuration  
- Secure configuration loading for Python scripts
- Priority: Environment variables > JSON config file
- Used by Python AI analysis scripts

### 3. `ai_config.json` - API Key Storage (SENSITIVE)
- Contains actual OpenAI API key
- **DO NOT commit this file to version control**
- Should be added to .gitignore

## Setup Instructions

### Option 1: Environment Variable (Recommended)
Set the environment variable on your server:
```bash
export OPENAI_API_KEY="your-api-key-here"
```

### Option 2: Config File
1. Ensure `ai_config.json` exists with your API key
2. Make sure it's excluded from git (check .gitignore)
3. Set proper file permissions (readable only by web server)

### Option 3: Server Environment
For production deployment, set the environment variable in:
- cPanel environment variables
- Server configuration
- Docker environment files

## File Structure
```
Ai/
├── config.php          # PHP secure config loader
├── ai_config.py        # Python secure config loader  
├── ai_config.json      # API key storage (keep private!)
├── ai_dashboard.html   # Main AI dashboard
├── database_news_collector.php
└── ... other AI files
```

## Security Best Practices
1. ✅ API key stored in separate config file
2. ✅ Config files excluded from version control
3. ✅ Multiple fallback options (env vars > config file)
4. ✅ Error handling for missing configuration
5. ✅ Environment variable support for production

## Testing Configuration
After setup, test by accessing the AI dashboard and checking if OpenAI features work without hardcoded keys in source code.