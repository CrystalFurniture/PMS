<?php
/**
 * Sample configuration for the AI quotation assistant.
 *
 * Copy this file OUTSIDE the web root and outside Git, then fill in api_key
 * and storage_dir. The application finds it at:
 *   1. the path in the PMS_AI_CONFIG environment variable, or
 *   2. <parent of the web root>/pms_private/ai_config.php
 *      (for XAMPP: C:/xampp/pms_private/ai_config.php).
 * Restrict the file so only the web server account can read it.
 * See docs/ai-quotation-assistant.md.
 */
return array(
	'enabled' => true,
	'provider' => 'anthropic',
	'api_key' => '',                         // Claude API key (Claude Console). Never commit it.
	'model' => 'claude-opus-5-5',            // Required. Change only after testing on real requirements.
	'effort' => 'medium',                    // Sent as output_config.effort. medium is the documented default for claude-opus-5-5;
	                                         // set it explicitly so benchmark results are tied to one setting.
	'refusal_fallback' => '',                // '' = off (recommended for the pilot/benchmark: every answer comes from the
	                                         // configured model). 'default' sends fallbacks: "default" with the
	                                         // anthropic-beta: server-side-fallback-2026-07-01 header.

	// Private folder for uploads, drafts and metadata logs. Must be outside the web root.
	'storage_dir' => 'C:/xampp/pms_private/ai_storage',
	'retention_hours' => 24,                 // drafts and images older than this are deleted

	// Timeouts and limits
	'connect_timeout' => 10,                 // seconds
	'timeout' => 120,                        // seconds per attempt
	'max_retries' => 2,                      // retries for rate limits, overload, 5xx, timeouts
	'total_deadline' => 300,                 // seconds for one call including retries
	'max_response_bytes' => 2097152,
	'max_tokens' => 16000,

	// Upload limits (checked on the server)
	'max_images' => 5,
	'max_image_bytes' => 7340032,            // 7 MB per image
	'max_total_image_bytes' => 20971520,     // 20 MB per requirement
	'min_image_px' => 200,
	'max_image_px' => 8000,
	'max_text_chars' => 20000,
	'max_rows' => 100,
	'default_quantity' => 0,                 // e.g. 1: lines with no quantity get 1 (shown as a default on the row);
	                                         // 0 leaves them blank.
	'default_quantity_scope' => 'missing',   // 'missing': only lines with no quantity written get the default
	                                         // (unclear/contradictory ones stay blank for the employee);
	                                         // 'all': every line without a clean quantity gets it, with a row
	                                         // warning saying what the source had (e.g. "read as 1800 ... size").
	'candidates_per_row' => 12,
	'alternatives_shown' => 5,
);
