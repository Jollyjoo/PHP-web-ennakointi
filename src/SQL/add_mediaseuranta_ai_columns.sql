-- Add AI analysis columns to Mediaseuranta table
-- This will enable storing AI analysis results for existing media monitoring data

ALTER TABLE Mediaseuranta 
ADD COLUMN ai_analysis_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Status of AI analysis',
ADD COLUMN ai_analyzed_at DATETIME NULL COMMENT 'When AI analysis was completed',
ADD COLUMN ai_relevance_score INT(2) DEFAULT NULL COMMENT 'AI relevance score 1-10 for regional impact',
ADD COLUMN ai_economic_impact VARCHAR(50) DEFAULT NULL COMMENT 'AI assessment: positive/neutral/negative',
ADD COLUMN ai_employment_impact TEXT DEFAULT NULL COMMENT 'AI analysis of employment effects',
ADD COLUMN ai_key_sectors JSON DEFAULT NULL COMMENT 'AI identified relevant sectors (JSON array)',
ADD COLUMN ai_sentiment VARCHAR(20) DEFAULT NULL COMMENT 'AI sentiment analysis: positive/neutral/negative',
ADD COLUMN ai_crisis_probability DECIMAL(3,2) DEFAULT NULL COMMENT 'AI crisis probability score 0.00-1.00',
ADD COLUMN ai_summary TEXT DEFAULT NULL COMMENT 'AI-generated summary of the article',
ADD COLUMN ai_keywords JSON DEFAULT NULL COMMENT 'AI-extracted keywords (JSON array)',
ADD COLUMN ai_full_analysis JSON DEFAULT NULL COMMENT 'Complete AI analysis result (JSON)',
ADD COLUMN ai_processing_time DECIMAL(5,2) DEFAULT NULL COMMENT 'Processing time in seconds';

-- Create index for efficient querying
CREATE INDEX idx_mediaseuranta_ai_status ON Mediaseuranta(ai_analysis_status);
CREATE INDEX idx_mediaseuranta_ai_score ON Mediaseuranta(ai_relevance_score);
CREATE INDEX idx_mediaseuranta_date_status ON Mediaseuranta(uutisen_pvm, ai_analysis_status);

-- Show the updated table structure
DESCRIBE Mediaseuranta;