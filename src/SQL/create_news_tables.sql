-- SQL script to create news_articles table for AI News Intelligence System
-- Run this on your MySQL database

CREATE TABLE IF NOT EXISTS `news_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_date` datetime DEFAULT NULL,
  `collected_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `analysis_status` enum('pending','analyzed','failed') DEFAULT 'pending',
  `sentiment_score` decimal(3,2) DEFAULT NULL,
  `impact_level` enum('low','medium','high') DEFAULT NULL,
  `region_relevance` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_published_date` (`published_date`),
  KEY `idx_collected_at` (`collected_at`),
  KEY `idx_source` (`source`),
  KEY `idx_analysis_status` (`analysis_status`),
  FULLTEXT KEY `idx_title_content` (`title`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for storing detailed AI analysis results
CREATE TABLE IF NOT EXISTS `news_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `analysis_data` json,
  `sentiment` varchar(20),
  `themes` json,
  `entities` json,
  `crisis_probability` decimal(3,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_article_id` (`article_id`),
  KEY `idx_sentiment` (`sentiment`),
  KEY `idx_crisis_probability` (`crisis_probability`),
  FOREIGN KEY (`article_id`) REFERENCES `news_articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data for testing (optional)
INSERT INTO `news_articles` (`title`, `content`, `url`, `source`, `published_date`) VALUES
('Hämeen ELY-keskus myönsi 2.5M€ vihreän teknologian avustuksia', 
 'Hämeen elinkeino-, liikenne- ja ympäristökeskus myönsi yhteensä 2,5 miljoonaa euroa avustuksia vihreän teknologian hankkeisiin. Rahoitus kohdistuu erityisesti uusiutuvan energian ja ympäristöteknologian kehityshankkeisiin Hämeenlinnan seudulla.',
 'https://example.com/news1',
 'YLE Häme',
 '2025-11-06 14:30:00'),

('Tampereen teknologiakeskus laajentaa toimintaansa Hämeenlinnaan',
 'Tampereen teknologiakeskus ilmoitti avaavansa uuden toimipisteen Hämeenlinnaan. Uusi toimipiste keskittyy tekoälyn ja automaation tutkimukseen. Hanke tuo arviolta 50 uutta työpaikkaa alueelle.',
 'https://example.com/news2',
 'Hämeen Sanomat',
 '2025-11-05 09:15:00'),

('Riihimäen koulutuskeskukseen uusi AI-laboratorio',
 'Riihimäen ammattikorkeakouluun perustetaan uusi tekoälylaboratorio, joka palvelee koko Hämeen alueen yritysten digitalisaatiotarpeita. Investointi on yhteensä 800 000 euroa.',
 'https://example.com/news3',
 'Aamulehti Hämeenlinna',
 '2025-11-04 16:45:00');

-- Show table structure
DESCRIBE news_articles;
DESCRIBE news_analysis;