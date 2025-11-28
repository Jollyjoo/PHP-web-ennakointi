-- Azure SQL Server version of Mediaseuranta table
-- Converted from MySQL structure with Azure SQL optimizations

CREATE TABLE Mediaseuranta (
    -- Primary key with IDENTITY (Azure SQL equivalent of AUTO_INCREMENT)
    ID int IDENTITY(1,1) NOT NULL,
    
    -- Foreign key reference
    Maakunta_ID int NULL,
    
    -- Basic news information
    Teema nvarchar(200) NULL,
    uutisen_pvm date NULL,
    Uutinen nvarchar(500) NULL,
    Url nvarchar(500) NULL,
    Hankkeen_luokitus nvarchar(200) NULL,
    
    -- AI Analysis fields
    ai_analysis_status nvarchar(20) NOT NULL DEFAULT 'pending'
        CONSTRAINT CK_ai_analysis_status CHECK (ai_analysis_status IN ('pending', 'completed', 'failed')),
    ai_analyzed_at datetime2 NULL,
    ai_relevance_score tinyint NULL,
    ai_economic_impact nvarchar(50) NULL,
    ai_employment_impact nvarchar(max) NULL,
    ai_key_sectors nvarchar(max) NULL,
    ai_sentiment nvarchar(20) NULL,
    ai_crisis_probability decimal(3,2) NULL,
    ai_summary nvarchar(max) NULL,
    ai_keywords nvarchar(max) NULL,
    ai_full_analysis nvarchar(max) NULL,
    ai_processing_time decimal(5,2) NULL,
    
    -- Competitive Intelligence fields
    competitive_analysis_status nvarchar(20) NOT NULL DEFAULT 'pending'
        CONSTRAINT CK_competitive_analysis_status CHECK (competitive_analysis_status IN ('pending', 'analyzed', 'failed')),
    competitive_analyzed_at datetime2 NULL,
    competitive_analysis nvarchar(max) NULL,
    competitors_mentioned nvarchar(max) NULL,
    funding_intelligence nvarchar(max) NULL,
    market_opportunities nvarchar(max) NULL,
    partnership_opportunities nvarchar(max) NULL,
    competitive_score decimal(3,2) NULL,
    business_relevance nvarchar(20) NOT NULL DEFAULT 'none'
        CONSTRAINT CK_business_relevance CHECK (business_relevance IN ('none', 'low', 'medium', 'high')),
    strategic_importance tinyint NULL,
    competitive_threats nvarchar(max) NULL,
    market_intelligence nvarchar(max) NULL,
    action_recommendations nvarchar(max) NULL,
    
    -- Primary Key constraint
    CONSTRAINT PK_Mediaseuranta PRIMARY KEY CLUSTERED (ID)
);

-- Create indexes for better performance (equivalent to MySQL MUL keys)
CREATE NONCLUSTERED INDEX IX_Mediaseuranta_Maakunta_ID 
    ON Mediaseuranta (Maakunta_ID);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_uutisen_pvm 
    ON Mediaseuranta (uutisen_pvm);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_ai_analysis_status 
    ON Mediaseuranta (ai_analysis_status);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_ai_relevance_score 
    ON Mediaseuranta (ai_relevance_score);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_competitive_analysis_status 
    ON Mediaseuranta (competitive_analysis_status);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_competitive_score 
    ON Mediaseuranta (competitive_score);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_business_relevance 
    ON Mediaseuranta (business_relevance);

CREATE NONCLUSTERED INDEX IX_Mediaseuranta_strategic_importance 
    ON Mediaseuranta (strategic_importance);

-- Optional: Create a composite index for common queries
CREATE NONCLUSTERED INDEX IX_Mediaseuranta_Date_Status 
    ON Mediaseuranta (uutisen_pvm, ai_analysis_status);

-- Add extended properties for documentation (Azure SQL feature)
EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Main table for media monitoring with AI analysis and competitive intelligence',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Mediaseuranta';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'AI analysis status: pending=not analyzed, completed=analysis done, failed=analysis error',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Mediaseuranta',
    @level2type = N'COLUMN', @level2name = N'ai_analysis_status';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Business relevance level: none=not relevant, low=minor relevance, medium=moderate relevance, high=highly relevant',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Mediaseuranta',
    @level2type = N'COLUMN', @level2name = N'business_relevance';

-- Optional: Create view for active analyzed records
CREATE VIEW vw_Mediaseuranta_Analyzed AS
SELECT 
    ID,
    Maakunta_ID,
    Teema,
    uutisen_pvm,
    Uutinen,
    Url,
    Hankkeen_luokitus,
    ai_analysis_status,
    ai_analyzed_at,
    ai_relevance_score,
    ai_economic_impact,
    ai_employment_impact,
    ai_key_sectors,
    ai_sentiment,
    ai_crisis_probability,
    ai_summary,
    competitive_analysis_status,
    competitive_analyzed_at,
    business_relevance,
    strategic_importance,
    competitive_score
FROM Mediaseuranta 
WHERE ai_analysis_status = 'completed';

PRINT 'Mediaseuranta table created successfully for Azure SQL Server!';