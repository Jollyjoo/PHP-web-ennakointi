<?php
/**
 * OpenAI Analysis Limits Configuration
 * 
 * This file contains the central configuration for all OpenAI API call limits.
 * Change OPENAI_ANALYSIS_LIMIT to adjust limits across all functions:
 * - 1 for development (minimal costs)
 * - 5 for production (balanced performance)
 * - 10+ for high-volume production
 */

// ===== MAIN CONFIGURATION =====
define('OPENAI_ANALYSIS_LIMIT', 1); // Change this single value to adjust all OpenAI limits

// ===== DERIVED CONSTANTS =====
define('NEWS_ANALYSIS_LIMIT', OPENAI_ANALYSIS_LIMIT);
define('MEDIASEURANTA_ANALYSIS_LIMIT', OPENAI_ANALYSIS_LIMIT);
define('COMPETITIVE_ANALYSIS_LIMIT', OPENAI_ANALYSIS_LIMIT);
define('ALERTS_ANALYSIS_LIMIT', OPENAI_ANALYSIS_LIMIT);

// ===== HELPER FUNCTIONS =====

/**
 * Get the current analysis limit
 */
function getAnalysisLimit() {
    return OPENAI_ANALYSIS_LIMIT;
}

/**
 * Get cost protection message
 */
function getCostProtectionMessage() {
    return "Limited to " . OPENAI_ANALYSIS_LIMIT . " " . (OPENAI_ANALYSIS_LIMIT === 1 ? "article" : "articles") . " maximum";
}

/**
 * Get development status message
 */
function getDevelopmentStatusMessage() {
    if (OPENAI_ANALYSIS_LIMIT === 1) {
        return "🔧 DEVELOPMENT MODE: 1 article limit for cost protection";
    } elseif (OPENAI_ANALYSIS_LIMIT <= 5) {
        return "⚖️ BALANCED MODE: " . OPENAI_ANALYSIS_LIMIT . " articles limit";
    } else {
        return "🚀 PRODUCTION MODE: " . OPENAI_ANALYSIS_LIMIT . " articles limit";
    }
}

?>