# OpenAI Analysis Limits - Centralized Configuration

## 🎯 Quick Setup

**Want to change ALL OpenAI limits from 1 to 5 articles?**

### Just change this ONE line:

**File:** `openai_limits_config.php`
```php
define('OPENAI_ANALYSIS_LIMIT', 1);  // ← Change this number
```

**Options:**
- `1` = Development mode (minimal costs)
- `5` = Production mode (balanced performance) 
- `10` = High-volume production

## 🔧 What Gets Updated Automatically

When you change `OPENAI_ANALYSIS_LIMIT`, these all update automatically:

### Backend PHP Functions:
- ✅ `getRecentNewsForAlerts()`
- ✅ `getAnalyzedArticlesForCompetitive()`  
- ✅ `getMediaseurantaEntries()`
- ✅ `getMediaseurantaForCompetitive()`
- ✅ All cost protection messages
- ✅ All batch size defaults

### Frontend JavaScript:
- ✅ All button tooltips show correct limit
- ✅ All fetch URLs use correct batch_size
- ✅ All display messages show correct maximum

### Files Updated:
- ✅ `minimal_news_api.php`
- ✅ `mediaseuranta_analyzer.php` 
- ✅ `database_news_collector.php`
- ✅ `ai_dashboard.html`

## 🚀 Usage Modes

### 🔧 Development Mode (LIMIT = 1)
- **Cost:** ~$0.002 per function call
- **Perfect for:** Testing, debugging, development
- **Message:** "🔧 DEVELOPMENT MODE: 1 article limit"

### ⚖️ Balanced Mode (LIMIT = 5) 
- **Cost:** ~$0.01 per function call
- **Perfect for:** Small production, regular use
- **Message:** "⚖️ BALANCED MODE: 5 articles limit"

### 🚀 Production Mode (LIMIT = 10+)
- **Cost:** ~$0.02+ per function call  
- **Perfect for:** High-volume production
- **Message:** "🚀 PRODUCTION MODE: X articles limit"

## 📁 File Structure

```
src/Ai/
├── openai_limits_config.php      ← MAIN CONFIG FILE
├── minimal_news_api.php           ← Uses config
├── mediaseuranta_analyzer.php     ← Uses config  
├── database_news_collector.php    ← Uses config
└── ai_dashboard.html              ← Uses config
```

## 💡 Helper Functions Available

```php
getAnalysisLimit()              // Returns current limit number
getCostProtectionMessage()      // Returns "Limited to X articles maximum"  
getDevelopmentStatusMessage()   // Returns mode-specific status
```

## 🎉 Benefits

✅ **Single source of truth** - Change one number, update everything
✅ **No more hunting** - All limits defined in one file
✅ **Consistent display** - Frontend and backend always match
✅ **Easy deployment** - Switch from dev to production instantly
✅ **Cost control** - Clear understanding of OpenAI usage