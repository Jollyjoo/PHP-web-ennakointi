-- Azuren SQL Database stored procedure and trigger to forward new Mediaseuranta records to MySQL server
-- This script creates a stored procedure and a trigger to send new records via HTTP POST


-- Step 1: Create a stored procedure for HTTP calls
CREATE PROCEDURE sp_ForwardToMySQL
    @RecordID INT
AS
BEGIN
    DECLARE @jsonData NVARCHAR(MAX);
    
    -- Get the newly inserted record as JSON
    SELECT @jsonData = (
        SELECT 
            ID,
            Maakunta_ID,
            Teema,
            uutisen_pvm,
            Uutinen,
            Url,
            Hankkeen_luokitus,
            ai_analysis_status,
            ai_relevance_score,
            ai_economic_impact,
            ai_employment_impact,
            ai_key_sectors,
            ai_sentiment,
            ai_crisis_probability
        FROM Mediaseuranta 
        WHERE ID = @RecordID
        FOR JSON AUTO, WITHOUT_ARRAY_WRAPPER
    );
    
    -- Make HTTP POST to your MySQL server
    DECLARE @url NVARCHAR(500) = 'https://tulevaisuusluotain.fi/receive_mediaseuranta.php?api_key=your-secret-mediaseuranta-key-2025';
    
    EXEC sp_invoke_external_rest_endpoint
        @url = @url,
        @method = 'POST',
        @headers = '{"Content-Type": "application/json"}',
        @payload = @jsonData;
END;

-- Step 2: Create trigger on INSERT
CREATE TRIGGER tr_Mediaseuranta_ForwardToMySQL
ON Mediaseuranta
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Forward each newly inserted record
    DECLARE @RecordID INT;
    
    DECLARE insert_cursor CURSOR FOR
    SELECT ID FROM INSERTED;
    
    OPEN insert_cursor;
    FETCH NEXT FROM insert_cursor INTO @RecordID;
    
    WHILE @@FETCH_STATUS = 0
    BEGIN
        -- Call the forwarding procedure (async to avoid blocking)
        EXEC sp_ForwardToMySQL @RecordID;
        
        FETCH NEXT FROM insert_cursor INTO @RecordID;
    END;
    
    CLOSE insert_cursor;
    DEALLOCATE insert_cursor;
END;