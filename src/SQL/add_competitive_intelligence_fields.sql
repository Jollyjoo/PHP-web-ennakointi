-- SQL script to add competitive intelligence fields to existing tables
-- Run this on your MySQL database if tables already exist

-- Add competitive intelligence fields to news_articles table
-- Keep existing analysis_status field, only add competitive intelligence tracking
ALTER TABLE `news_articles` 
ADD COLUMN IF NOT EXISTS `competitive_analysis_status` enum('pending','analyzed','failed','not_applicable') DEFAULT 'pending' AFTER `analysis_status`,
ADD COLUMN IF NOT EXISTS `competitive_analyzed_at` datetime DEFAULT NULL AFTER `competitive_analysis_status`;

-- Add indexes for new fields
ALTER TABLE `news_articles` 
ADD INDEX IF NOT EXISTS `idx_competitive_analysis_status` (`competitive_analysis_status`);

-- Add competitive intelligence fields to news_analysis table
ALTER TABLE `news_analysis`
ADD COLUMN IF NOT EXISTS `competitive_analysis` json AFTER `crisis_probability`,
ADD COLUMN IF NOT EXISTS `competitors_mentioned` json AFTER `competitive_analysis`,
ADD COLUMN IF NOT EXISTS `funding_intelligence` json AFTER `competitors_mentioned`,
ADD COLUMN IF NOT EXISTS `market_opportunities` json AFTER `funding_intelligence`,
ADD COLUMN IF NOT EXISTS `partnership_opportunities` json AFTER `market_opportunities`,
ADD COLUMN IF NOT EXISTS `competitive_score` decimal(3,2) DEFAULT 0.00 AFTER `partnership_opportunities`,
ADD COLUMN IF NOT EXISTS `business_relevance` enum('high','medium','low','none') DEFAULT 'none' AFTER `competitive_score`,
ADD COLUMN IF NOT EXISTS `strategic_importance` enum('critical','important','moderate','low') DEFAULT 'low' AFTER `business_relevance`,
ADD COLUMN IF NOT EXISTS `competitive_analyzed_at` datetime DEFAULT NULL AFTER `updated_at`;

-- Add indexes for new competitive intelligence fields
ALTER TABLE `news_analysis`
ADD INDEX IF NOT EXISTS `idx_competitive_score` (`competitive_score`),
ADD INDEX IF NOT EXISTS `idx_business_relevance` (`business_relevance`),
ADD INDEX IF NOT EXISTS `idx_strategic_importance` (`strategic_importance`);

-- Show updated table structures
DESCRIBE news_articles;
DESCRIBE news_analysis;

-- Verification queries
SELECT 
    COUNT(*) as total_articles,
    COUNT(CASE WHEN competitive_analysis_status = 'pending' THEN 1 END) as pending_competitive,
    COUNT(CASE WHEN competitive_analysis_status = 'analyzed' THEN 1 END) as analyzed_competitive
FROM news_articles;

SELECT 
    COUNT(*) as total_analysis,
    COUNT(CASE WHEN competitive_analysis IS NOT NULL THEN 1 END) as with_competitive_data,
    AVG(competitive_score) as avg_competitive_score
FROM news_analysis;