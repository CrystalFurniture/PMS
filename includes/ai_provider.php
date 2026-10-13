<?php
/**
 * AI provider client for the quotation assistant (includes/ai_import_lib.php).
 *
 * Calls the Claude Messages API (POST /v1/messages) over HTTPS with ext-curl.
 * The official Anthropic PHP SDK needs PHP 8.1+ and Composer, neither of which
 * this application uses, so the request follows the documented wire format:
 *   headers  x-api-key, anthropic-version: 2023-06-01, content-type: application/json
 *            (+ anthropic-beta only when refusal fallback is switched on)
 *   body     model, max_tokens, system, messages[] (base64 image blocks + text),
 *            output_config.format {type: json_schema, schema}, optional output_config.effort,
 *            optional fallbacks
 * Config keys (ai_config.php) are internal names; see docs/ai-quotation-assistant.md
 * for how each maps to an API field.
 *
 * Every call is bounded: connect/total timeouts, a response-size cap, retries
 * only for retryable statuses, and an overall deadline. Nothing here logs
 * prompt or response content.
 */

define('QT_AI_ANTHROPIC_VERSION', '2023-06-01');
define('QT_AI_FALLBACK_BETA', 'server-side-fallback-2026-07-01');

function qt_ai_error($code, $message, $retryable = false)
{
	return array('code' => $code, 'message' => $message, 'retryable' => $retryable);
}

/** Build the Messages API request body and headers from internal config. */
function qt_ai_build_request($ai, $system, $content, $schema)
{
	$body = array(
		'model' => $ai['model'],
		'max_tokens' => (int) $ai['max_tokens'],
		'system' => $system,
		'messages' => array(array('role' => 'user', 'content' => $content)),
		'output_config' => array('format' => array('type' => 'json_schema', 'schema' => $schema)),
	);
	if ($ai['effort'] !== '') {
		$body['output_config']['effort'] = $ai['effort'];
	}
	$headers = array(
		'content-type: application/json',
		'x-api-key: ' . $ai['api_key'],
		'anthropic-version: ' . QT_AI_ANTHROPIC_VERSION,
	);
	if ($ai['refusal_fallback'] !== '') {
		// Opt-in server-side refusal fallback (off by default so results come from one model).
		$body['fallbacks'] = $ai['refusal_fallback'];
		$headers[] = 'anthropic-beta: ' . QT_AI_FALLBACK_BETA;
	}
	return array($body, $headers);
}

/**
 * Ask the model for JSON matching $schema.
 * Returns array(data, error, meta). meta holds what a benchmark needs:
 * served_model, request_id, stop_reason, input/output tokens, ms, attempts,
 * served_by_fallback, refusal_category.
 */
function qt_ai_call_json($ai, $purpose, $system, $content, $schema, $logContext = array())
{
	$out = qt_ai_call_json_once($ai, $purpose, $system, $content, $schema, $logContext);
	// Optional collector used by the benchmark runner to attribute time, tokens and cost.
	if (isset($GLOBALS['qt_ai_meta_sink']) && is_array($GLOBALS['qt_ai_meta_sink'])) {
		$GLOBALS['qt_ai_meta_sink'][] = array_merge($out[2], array('error' => $out[1] !== null ? $out[1]['code'] : null));
	}
	return $out;
}

function qt_ai_call_json_once($ai, $purpose, $system, $content, $schema, $logContext)
{
	$meta = array('purpose' => $purpose, 'requested_model' => $ai['model'], 'served_model' => null, 'request_id' => null,
		'stop_reason' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'ms' => 0, 'attempts' => 0,
		'served_by_fallback' => false, 'refusal_category' => null);
	if ($ai['provider'] !== 'anthropic') {
		return array(null, qt_ai_error('not_configured', 'The configured AI provider is not supported.'), $meta);
	}
	list($body, $headers) = qt_ai_build_request($ai, $system, $content, $schema);
	$json = json_encode($body);
	if ($json === false) {
		return array(null, qt_ai_error('invalid_input', 'The requirement could not be prepared for the AI service.'), $meta);
	}

	$deadline = microtime(true) + (int) $ai['total_deadline'];
	$attempts = (int) $ai['max_retries'] + 1;
	$error = null;
	$callStarted = microtime(true);
	for ($attempt = 1; $attempt <= $attempts; $attempt++) {
		$meta['attempts'] = $attempt;
		$started = microtime(true);
		$response = qt_ai_http_post($ai, $json, $headers);
		$status = $response['status'];
		$meta['request_id'] = $response['request_id'];
		$log = array_merge($logContext, array('purpose' => $purpose, 'attempt' => $attempt, 'http' => $status,
			'ms' => (int) ((microtime(true) - $started) * 1000), 'bytes_sent' => strlen($json), 'request_id' => $response['request_id']));

		if ($response['curl_error'] !== '') {
			$error = $response['too_large']
				? qt_ai_error('invalid_response', 'The AI service returned more data than allowed.')
				: ($response['timed_out']
					? qt_ai_error('timeout', 'The AI service did not respond in time.', true)
					: qt_ai_error('network', 'The AI service could not be reached.', true));
		} elseif ($status === 200) {
			list($data, $error, $info) = qt_ai_parse_response($response['body'], $ai);
			// Tokens add up across attempts: a retried unusable response is still billed.
			$inputSoFar = $meta['input_tokens'];
			$outputSoFar = $meta['output_tokens'];
			$meta = array_merge($meta, $info);
			$meta['input_tokens'] += $inputSoFar;
			$meta['output_tokens'] += $outputSoFar;
			$log = array_merge($log, array('stop_reason' => $info['stop_reason'], 'served_model' => $info['served_model'],
				'fallback' => $info['served_by_fallback'] ? 1 : 0, 'input_tokens' => $info['input_tokens'],
				'output_tokens' => $info['output_tokens'], 'refusal_category' => $info['refusal_category']));
			if ($error === null) {
				$meta['ms'] = (int) ((microtime(true) - $callStarted) * 1000);
				qt_ai_log($ai, 'provider_call', array_merge($log, array('outcome' => 'ok')));
				return array($data, null, $meta);
			}
		} else {
			$error = qt_ai_status_error($status, $response);
		}

		qt_ai_log($ai, 'provider_call', array_merge($log, array('outcome' => $error['code'])));
		if (!$error['retryable'] || $attempt === $attempts) {
			break;
		}
		$wait = $response['retry_after'] !== null ? $response['retry_after'] : min(8, pow(2, $attempt - 1));
		$wait = min($wait, (int) $ai['retry_max_wait']);
		if (microtime(true) + $wait + (int) $ai['timeout'] > $deadline) {
			break;
		}
		if ($wait > 0) {
			usleep((int) ($wait * 1000000));
		}
	}
	$meta['ms'] = (int) ((microtime(true) - $callStarted) * 1000);
	if ($meta['request_id']) {
		$error['message'] .= ' (Reference: ' . $meta['request_id'] . ')';
	}
	return array(null, $error, $meta);
}

/** Map a non-200 HTTP status to an error, following the API's documented error types. */
function qt_ai_status_error($status, $response)
{
	$body = json_decode($response['body'], true);
	$type = isset($body['error']['type']) && is_string($body['error']['type']) ? $body['error']['type'] : '';
	$apiMessage = isset($body['error']['message']) && is_string($body['error']['message']) ? $body['error']['message'] : '';
	switch (true) {
		case $status === 429 && $response['retry_after'] === null:
			// A spend-cap 429 has no retry-after and keeps failing until access resumes.
			return qt_ai_error('spend_limit', 'The AI account has reached a rate or spend limit. Ask the administrator to check the Claude Console.');
		case $status === 429:
			return qt_ai_error('rate_limited', 'The AI service is rate limiting requests. Please try again shortly.', true);
		case $status === 504:
			return qt_ai_error('timeout', 'The AI service timed out while processing.', true);
		case $status === 529 || $status >= 500:
			return qt_ai_error('unavailable', 'The AI service is temporarily unavailable.', true);
		case $status === 401 || $status === 403:
			return qt_ai_error('not_configured', 'The AI service rejected the configured credentials.');
		case $status === 402:
			return qt_ai_error('billing', 'The AI account has a billing problem. Ask the administrator to check the Claude Console.');
		case $status === 404:
			return qt_ai_error('not_configured', 'The configured AI model is not available to this API key.');
		case $status === 413:
			return qt_ai_error('too_large', 'The images are too large for the AI service. Use fewer or smaller images.');
		case $status === 400 && stripos($apiMessage, 'spend limit') !== false:
			return qt_ai_error('spend_limit', 'The AI account has reached its spend limit. Ask the administrator to check the Claude Console.');
		default:
			return qt_ai_error('request_rejected', 'The AI service rejected the request (HTTP ' . $status . ($type !== '' ? ', ' . $type : '') . ').');
	}
}

/** One POST with bounded time and response size. */
function qt_ai_http_post($ai, $json, $headers)
{
	$result = array('status' => 0, 'body' => '', 'retry_after' => null, 'request_id' => null,
		'curl_error' => '', 'timed_out' => false, 'too_large' => false);
	$max = (int) $ai['max_response_bytes'];

	$ch = curl_init($ai['api_url']);
	curl_setopt_array($ch, array(
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $json,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_CONNECTTIMEOUT => (int) $ai['connect_timeout'],
		CURLOPT_TIMEOUT => (int) $ai['timeout'],
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$result) {
			if (preg_match('/^retry-after:\s*(\d+)/i', $line, $m)) {
				$result['retry_after'] = (int) $m[1];
			} elseif (preg_match('/^request-id:\s*(\S+)/i', $line, $m)) {
				$result['request_id'] = substr($m[1], 0, 100);
			}
			return strlen($line);
		},
		CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$result, $max) {
			if (strlen($result['body']) + strlen($chunk) > $max) {
				$result['too_large'] = true;
				return 0; // aborts the transfer
			}
			$result['body'] .= $chunk;
			return strlen($chunk);
		},
	));
	if (defined('CURLOPT_PROTOCOLS')) {
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
	}
	if (defined('CURLOPT_TCP_KEEPALIVE')) {
		// Keeps long non-streaming requests alive through idle-connection timeouts.
		curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
	}
	$ok = curl_exec($ch);
	$result['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($ok === false) {
		$errno = curl_errno($ch);
		$result['curl_error'] = $errno . ' ' . curl_error($ch);
		$result['timed_out'] = ($errno === 28);
	}
	curl_close($ch);
	return $result;
}

/**
 * Returns array(data, error, info). Handles every documented stop_reason:
 * end_turn (use), max_tokens / model_context_window_exceeded (truncated),
 * refusal (shown with its category), anything else (unexpected for JSON output).
 */
function qt_ai_parse_response($body, $ai)
{
	$info = array('served_model' => null, 'stop_reason' => null, 'input_tokens' => 0, 'output_tokens' => 0,
		'served_by_fallback' => false, 'refusal_category' => null);
	$message = json_decode($body, true);
	if (!is_array($message) || !isset($message['content']) || !is_array($message['content'])) {
		return array(null, qt_ai_error('invalid_response', 'The AI service returned an unreadable response.', true), $info);
	}
	$usage = isset($message['usage']) && is_array($message['usage']) ? $message['usage'] : array();
	$info['served_model'] = isset($message['model']) && is_string($message['model']) ? $message['model'] : null;
	$info['stop_reason'] = isset($message['stop_reason']) && is_string($message['stop_reason']) ? $message['stop_reason'] : '';
	$info['input_tokens'] = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0;
	$info['output_tokens'] = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0;
	foreach (isset($usage['iterations']) && is_array($usage['iterations']) ? $usage['iterations'] : array() as $it) {
		if (is_array($it) && isset($it['type']) && $it['type'] === 'fallback_message') {
			$info['served_by_fallback'] = true;
		}
	}
	if ($info['served_model'] !== null && $info['served_model'] !== $ai['model']) {
		$info['served_by_fallback'] = true;
	}

	switch ($info['stop_reason']) {
		case 'end_turn':
			break;
		case 'refusal':
			$details = isset($message['stop_details']) && is_array($message['stop_details']) ? $message['stop_details'] : array();
			$info['refusal_category'] = isset($details['category']) && is_string($details['category']) ? $details['category'] : null;
			$explanation = isset($details['explanation']) && is_string($details['explanation']) ? ' ' . trim($details['explanation']) : '';
			return array(null, qt_ai_error('refused', 'The AI service declined to process this input'
				. ($info['refusal_category'] !== null ? ' (category: ' . $info['refusal_category'] . ').' : '.') . $explanation), $info);
		case 'max_tokens':
		case 'model_context_window_exceeded':
			return array(null, qt_ai_error('truncated', 'The requirement is too long to process in one go. Split it into smaller parts.'), $info);
		default:
			return array(null, qt_ai_error('invalid_response', 'The AI service stopped unexpectedly (' . ($info['stop_reason'] !== '' ? $info['stop_reason'] : 'no stop reason') . ').', true), $info);
	}

	$text = '';
	foreach ($message['content'] as $block) {
		if (is_array($block) && isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
			$text .= $block['text'];
		}
	}
	$data = json_decode($text, true);
	if (!is_array($data)) {
		return array(null, qt_ai_error('invalid_response', 'The AI service returned data in an unexpected format.', true), $info);
	}
	return array($data, null, $info);
}
