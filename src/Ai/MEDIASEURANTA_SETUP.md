# Mediaseuranta AI Analysis Setup Guide

## 🎯 Overview
This system analyzes the existing `Mediaseuranta` table with AI to provide intelligent insights about regional news and developments in Häme.

## 📋 Setup Steps

### 1. **Database Setup**
Run the SQL script to add AI analysis columns:
```sql
-- Execute this file:
src/SQL/add_mediaseuranta_ai_columns.sql
```

### 2. **Configure Database Connection**
Update `mediaseuranta_analyzer.php` with your database credentials:
```php
// Line 12-13: Update these values
$this->db = new mysqli('localhost', 'your_username', 'your_password', 'your_database');
```

### 3. **Test the System**
1. Open AI Dashboard: `src/Ai/ai_dashboard.html`
2. Click **"📰 Analyze Mediaseuranta"** to start analysis
3. Click **"📊 Mediaseuranta Insights"** to view results

## 🔧 New Features Added

### **Database Columns Added:**
- `ai_analysis_status` - Track analysis progress
- `ai_relevance_score` - Regional relevance (1-10)
- `ai_economic_impact` - Economic impact assessment
- `ai_employment_impact` - Employment effects analysis
- `ai_key_sectors` - Relevant business sectors (JSON)
- `ai_sentiment` - Sentiment analysis
- `ai_crisis_probability` - Crisis detection score
- `ai_summary` - AI-generated summary
- `ai_keywords` - Extracted keywords (JSON)
- `ai_full_analysis` - Complete analysis (JSON)

### **New API Endpoints:**
- `mediaseuranta_analyzer.php?action=analyze` - Run AI analysis
- `mediaseuranta_analyzer.php?action=stats` - Get statistics
- `mediaseuranta_analyzer.php?action=insights` - View results

### **Dashboard Features:**
- **📰 Analyze Mediaseuranta** - Batch process entries with AI
- **📊 Mediaseuranta Insights** - View analyzed results with scores

## 🎨 Analysis Features

### **AI Analyzes:**
- **Regional Relevance** (1-10 score)
- **Economic Impact** (positive/neutral/negative)
- **Employment Effects**
- **Key Sectors** affected
- **Sentiment Analysis**
- **Crisis Probability** (0.0-1.0)
- **Keyword Extraction**
- **Intelligent Summary**

### **Smart Processing:**
- **Batch Processing** (5 entries at a time)
- **Duplicate Prevention** (only analyzes unprocessed entries)
- **Error Handling** (robust failure recovery)
- **Progress Tracking** (real-time status updates)

## 💰 Cost Optimization

### **Efficiency Features:**
- **One-time Analysis** - Each entry analyzed only once
- **Batch Processing** - Prevents timeouts and API overload
- **Smart Filtering** - Only processes relevant entries
- **Rate Limiting** - 0.5s delays prevent API abuse

### **Estimated Costs:**
- **Per Entry**: ~$0.003-0.005 (700 tokens)
- **100 Entries**: ~$0.30-0.50
- **1000 Entries**: ~$3.00-5.00

## 📊 Data Integration

### **Existing Data Preserved:**
All your existing Mediaseuranta data remains unchanged. New AI columns are added alongside:
- `Maakunta_ID` - Region identifier
- `Teema` - Theme/topic
- `uutisen_pvm` - News date
- `Uutinen` - News content
- `Url` - Source URL
- `Hankkeen_luokitus` - Project classification

### **Enhanced Insights:**
The AI analysis provides deeper understanding of:
- **Regional Impact** - How news affects Häme specifically
- **Economic Implications** - Business and employment effects
- **Sector Analysis** - Which industries are affected
- **Trend Detection** - Patterns and emerging issues
- **Crisis Monitoring** - Early warning signals

## 🚀 Usage Workflow

1. **Setup Database** - Run SQL script once
2. **Configure Connection** - Update database credentials
3. **Run Analysis** - Click "Analyze Mediaseuranta"
4. **View Insights** - Click "Mediaseuranta Insights"
5. **Continuous Use** - System only analyzes new/unprocessed entries

## ✅ Benefits

### **For Decision Making:**
- **Prioritized Insights** - Relevance scores help focus attention
- **Trend Analysis** - Identify patterns in regional developments
- **Impact Assessment** - Understand economic and employment effects
- **Crisis Detection** - Early warning for potential issues

### **For Efficiency:**
- **Automated Analysis** - No manual content review needed
- **Consistent Scoring** - Objective relevance assessment
- **Searchable Data** - JSON fields enable complex queries
- **Historical Analysis** - Build trends over time

The system transforms your existing media monitoring data into actionable intelligence for regional development planning!