-- Add Competitive Intelligence columns to Mediaseuranta table
-- These are separate from the general AI analysis columns to avoid conflicts
-- This enables storing competitive intelligence results for media monitoring data

ALTER TABLE Mediaseuranta 
ADD COLUMN competitive_analysis_status ENUM('pending', 'analyzed', 'failed') DEFAULT 'pending' COMMENT 'Status of competitive intelligence analysis',
ADD COLUMN competitive_analyzed_at DATETIME NULL COMMENT 'When competitive analysis was completed',
ADD COLUMN competitive_analysis JSON DEFAULT NULL COMMENT 'Complete competitive intelligence analysis (JSON)',
ADD COLUMN competitors_mentioned JSON DEFAULT NULL COMMENT 'Companies and competitors mentioned (JSON array)',
ADD COLUMN funding_intelligence JSON DEFAULT NULL COMMENT 'Funding sources, amounts, and purposes (JSON)',
ADD COLUMN market_opportunities JSON DEFAULT NULL COMMENT 'Market opportunities identified (JSON array)',
ADD COLUMN partnership_opportunities JSON DEFAULT NULL COMMENT 'Partnership opportunities identified (JSON array)',
ADD COLUMN competitive_score DECIMAL(3,2) DEFAULT NULL COMMENT 'Competitive relevance score 0.00-1.00',
ADD COLUMN business_relevance ENUM('none', 'low', 'medium', 'high') DEFAULT 'none' COMMENT 'Business relevance level',
ADD COLUMN strategic_importance INT(1) DEFAULT NULL COMMENT 'Strategic importance score 1-5',
ADD COLUMN competitive_threats JSON DEFAULT NULL COMMENT 'Competitive threats identified (JSON array)',
ADD COLUMN market_intelligence JSON DEFAULT NULL COMMENT 'Market intelligence insights (JSON)',
ADD COLUMN action_recommendations JSON DEFAULT NULL COMMENT 'Recommended actions based on analysis (JSON array)';

-- Create indexes for efficient competitive intelligence querying
CREATE INDEX idx_mediaseuranta_comp_status ON Mediaseuranta(competitive_analysis_status);
CREATE INDEX idx_mediaseuranta_comp_score ON Mediaseuranta(competitive_score);
CREATE INDEX idx_mediaseuranta_comp_relevance ON Mediaseuranta(business_relevance);
CREATE INDEX idx_mediaseuranta_comp_date_status ON Mediaseuranta(uutisen_pvm, competitive_analysis_status);
CREATE INDEX idx_mediaseuranta_strategic_importance ON Mediaseuranta(strategic_importance);

-- Create a compound index for efficient competitive intelligence dashboard queries
CREATE INDEX idx_mediaseuranta_comp_dashboard ON Mediaseuranta(competitive_analysis_status, business_relevance, competitive_score);

-- Show the updated table structure
DESCRIBE Mediaseuranta;

-- Query to check both AI analysis and competitive intelligence status
SELECT 
    COUNT(*) as total_entries,
    SUM(CASE WHEN ai_analysis_status = 'completed' THEN 1 ELSE 0 END) as ai_analyzed,
    SUM(CASE WHEN competitive_analysis_status = 'analyzed' THEN 1 ELSE 0 END) as competitively_analyzed,
    SUM(CASE WHEN ai_analysis_status = 'completed' AND competitive_analysis_status = 'analyzed' THEN 1 ELSE 0 END) as fully_analyzed
FROM Mediaseuranta;