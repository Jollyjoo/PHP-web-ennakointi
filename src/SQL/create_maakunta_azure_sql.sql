-- Azure SQL Server version of Maakunta table
-- Regional/Province reference table for Finnish regions

CREATE TABLE Maakunnat (
    -- Primary key (no auto-increment, using predefined IDs)
    Maakunta_ID int NOT NULL,
    
    -- Region information
    Maakunta nvarchar(100) NULL,
    Maa nvarchar(100) NULL,
    stat_code nvarchar(100) NULL,
    
    -- Primary Key constraint
    CONSTRAINT PK_Maakunta PRIMARY KEY CLUSTERED (Maakunta_ID)
);

-- Create indexes for better performance
CREATE NONCLUSTERED INDEX IX_Maakunta_Name 
    ON Maakunnat (Maakunta);

CREATE NONCLUSTERED INDEX IX_Maakunta_StatCode 
    ON Maakunnat (stat_code);

-- Add extended properties for documentation
EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Reference table for Finnish regions (maakunnat) and administrative areas',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Maakunnat';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Unique identifier for the region (predefined values, not auto-increment)',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Maakunnat',
    @level2type = N'COLUMN', @level2name = N'Maakunta_ID';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Official statistical code for the region (Statistics Finland codes)',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'Maakunnat',
    @level2type = N'COLUMN', @level2name = N'stat_code';

-- Insert the reference data
INSERT INTO Maakunnat (Maakunta_ID, Maakunta, Maa, stat_code) VALUES
(1, N'Päijät-Häme', N'Suomi', N'MK07'),
(2, N'Kanta-Häme', N'Suomi', N'MK05'),
(3, N'Uusimaa', N'Suomi', N'MK01'),
(4, N'Kymenlaakso', N'Suomi', N'MK08'),
(5, N'Etelä-Karjala', N'Suomi', N'MK09'),
(6, N'Pirkanmaa', N'Suomi', N'MK06'),
(1000, N'Koko Maa', N'Suomi', N'SSS'),
(1001, N'Hämeen Ely-keskus', N'Suomi', N'ELY04'),
(1002, N'Pirkanmaan ELY-keskus', N'Suomi', N'ELY05');

-- Verify the data was inserted correctly
SELECT 
    Maakunta_ID,
    Maakunta,
    Maa,
    stat_code
FROM Maakunnat
ORDER BY 
    CASE 
        WHEN Maakunta_ID >= 1000 THEN 1000 + Maakunta_ID  -- Put special codes at end
        ELSE Maakunta_ID 
    END;

-- Create a view for easy region lookups
CREATE VIEW vw_Maakunta_Lookup AS
SELECT 
    Maakunta_ID,
    Maakunta,
    Maa,
    stat_code,
    CASE 
        WHEN Maakunta_ID >= 1000 THEN 'Administrative'
        ELSE 'Region'
    END AS Region_Type
FROM Maakunnat;

-- Now we can create the foreign key relationship to Mediaseuranta table
-- (Run this after both tables are created)
/*
ALTER TABLE Mediaseuranta
ADD CONSTRAINT FK_Mediaseuranta_Maakunta
FOREIGN KEY (Maakunta_ID) REFERENCES Maakunnat(Maakunta_ID);
*/

PRINT 'Maakunta table created and populated successfully!';
PRINT 'Total regions inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- Display summary of inserted data
SELECT 
    'Finnish Regions' AS Category,
    COUNT(*) AS Count
FROM Maakunnat 
WHERE Maakunta_ID < 1000
UNION ALL
SELECT 
    'Administrative Areas' AS Category,
    COUNT(*) AS Count
FROM Maakunnat 
WHERE Maakunta_ID >= 1000;