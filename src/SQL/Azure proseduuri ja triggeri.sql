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


-- edellinen triggeri oli BLOCKING, eli se piti Azure SQL:n odottamassa HTTP-kutsun valmistumista ennenkuin lisäys hyväksyttiin.
-- Tämä aiheutti ongelmia, koska Power Automate odotti triggerin valmistumista, ja jos HTTP-kutsu kesti liian kauan, Power Automate aikakatkaisi odotuksen ja lisäys epäonnistuisi kokonaan.
-- Ratkaisu on tehdä triggeristä NON-BLOCKING, eli sen ei tarvitse odottaa HTTP-kutsun valmistumista.
-- Drop the existing trigger first
DROP TRIGGER IF EXISTS tr_Mediaseuranta_ForwardToMySQL;

-- Create a new NON-BLOCKING trigger
CREATE TRIGGER tr_Mediaseuranta_ForwardToMySQL
ON Mediaseuranta
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Insert into a queue table instead of making HTTP calls directly
    INSERT INTO MediaseurantaQueue (record_id, created_at, status)
    SELECT ID, GETDATE(), 'pending'
    FROM INSERTED;
    
    -- The trigger finishes immediately, allowing Power Automate to complete
END;