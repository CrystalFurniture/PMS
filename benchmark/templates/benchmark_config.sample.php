<?php
/**
 * Benchmark / pilot configuration. Copy to benchmark/benchmark_config.local.php
 * (ignored by Git) and fill in. See benchmark/README.md.
 */
return array(
	// AI config used for the run (same format as docs/ai_config.sample.php).
	// For the benchmark: refusal_fallback must be '' and effort should be set explicitly.
	'ai_config' => 'C:/xampp/pms_private/ai_config.benchmark.php',

	// Source of the representative catalogue, opened READ-ONLY by pilot_db.php.
	'source_db' => array('host' => '127.0.0.1', 'port' => 3306, 'user' => '', 'pass' => '', 'name' => 'pms'),

	// Pilot database (built by pilot_db.php). Never "pms".
	'db' => array('host' => '127.0.0.1', 'port' => 3306, 'user' => '', 'pass' => '', 'name' => 'pms_pilot'),

	// Login accounts created in the pilot database for the quotation employee (the app stores
	// passwords as plain text, so use pilot-only passwords). group_id: an existing permission group.
	'pilot_users' => array(
		array('username' => 'pilot1', 'email' => 'pilot1@example.invalid', 'password' => 'change-me', 'firstname' => 'Pilot', 'group_id' => 6),
	),

	'samples_dir' => 'C:/pilot/benchmark/samples',   // anonymised samples + samples.csv + ground_truth.csv (keep outside Git)
	'results_dir' => 'C:/pilot/benchmark/results',
	'type' => 'DP',                                  // price list used for matching views: DP or MRP

	// Cost tracking. Prices per million tokens for the configured model; check
	// https://platform.claude.com/docs/en/about-claude/pricing before each run.
	'price_input_per_mtok' => 4.00,                  // claude-opus-5-5 input, as of 2026-10-10
	'price_output_per_mtok' => 20.00,                // claude-opus-5-5 output (includes thinking tokens), as of 2026-10-10
	'price_source' => 'Claude models overview, 2026-10-10',

	// Hard stop for the runner. Also set a spend limit on the API workspace in the Claude Console.
	'max_usd' => 15.00,
	'reserve_usd_per_sample' => 0.75,                // a sample is not started unless this much budget remains
);
