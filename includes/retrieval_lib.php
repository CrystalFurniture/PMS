<?php
/**
 * Hybrid product retrieval (exact code/name + keyword + multilingual semantic) and the
 * approved-document store used by the AI Assistant.
 *
 * Storage: MySQL tables (cli/migrate_pi.php) and brute-force dot products in PHP. The catalogue
 * has about 4,000 quotable products; at 512 dimensions a search takes milliseconds, so no vector
 * database or extra service is needed (MariaDB 10.4 has no vector type).
 *
 * Embeddings: Voyage AI (Anthropic offers no embedding model). Configured in ai_config.php:
 *   embed_api_key, embed_api_url (https://ai.mongodb.com/v1/embeddings for MongoDB Atlas model keys,
 *   https://api.voyageai.com/v1/embeddings for Voyage platform keys), embed_model (voyage-4-lite),
 *   embed_dimension (512). Without a key semantic search is off and keyword search works as before.
 *
 * Similarity only proposes candidates: every id is checked against the current catalogue, and the
 * model plus the server's size/finish checks decide whether a product fits.
 */

define('RT_INDEX_FORMAT', 'product-v1');   // change when the indexed text format changes: forces re-indexing
define('RT_RRF_K', 60);

function rt_enabled($ai)
{
	return isset($ai['embed_api_key']) && trim((string) $ai['embed_api_key']) !== '' && trim((string) $ai['embed_api_url']) !== '';
}

/** Index identity: provider model, dimension and text format. Rows with another identity are outdated. */
function rt_index_model($ai)
{
	return $ai['embed_model'] . '@' . (int) $ai['embed_dimension'] . '/' . RT_INDEX_FORMAT;
}

/** The text indexed for one product: verified catalogue fields only. */
function rt_product_text($p)
{
	$parts = array($p['name']);
	foreach (array('description' => '', 'tags' => 'Tags: ', 'category' => 'Category: ', 'color' => 'Colour: ', 'dimension' => 'Size: ') as $k => $label) {
		if (isset($p[$k]) && trim((string) $p[$k]) !== '') {
			$parts[] = $label . trim((string) $p[$k]);
		}
	}
	return implode(' | ', $parts);
}

/**
 * Call the embeddings API. $type: 'document' or 'query'. Returns array(vectors, error, tokens).
 * Bounded like the Claude calls: timeouts, response-size cap, no key in logs.
 */
function rt_embed($ai, $texts, $type)
{
	if (!rt_enabled($ai)) {
		return array(null, 'Semantic search is not configured.', 0);
	}
	$body = json_encode(array('input' => array_values($texts), 'model' => $ai['embed_model'], 'input_type' => $type,
		'output_dimension' => (int) $ai['embed_dimension'], 'truncation' => true));
	$cfg = array_merge($ai, array('api_url' => $ai['embed_api_url'], 'max_response_bytes' => max((int) $ai['max_response_bytes'], 64 * 1024 * 1024)));
	$started = microtime(true);
	$r = qt_ai_http_post($cfg, $body, array('Content-Type: application/json', 'Authorization: Bearer ' . $ai['embed_api_key']));
	$ms = (int) ((microtime(true) - $started) * 1000);
	if ($r['curl_error'] !== '' || $r['status'] !== 200) {
		qt_ai_log($ai, 'embed_call', array('type' => $type, 'count' => count($texts), 'http' => $r['status'], 'ms' => $ms, 'outcome' => 'error'));
		return array(null, 'The embeddings service failed (' . ($r['curl_error'] !== '' ? 'network' : 'HTTP ' . $r['status']) . ').', 0);
	}
	$j = json_decode($r['body'], true);
	if (!isset($j['data']) || !is_array($j['data']) || count($j['data']) !== count($texts)) {
		return array(null, 'The embeddings service returned an unexpected response.', 0);
	}
	$out = array();
	foreach ($j['data'] as $d) {
		$v = isset($d['embedding']) && is_array($d['embedding']) ? $d['embedding'] : null;
		if ($v === null || count($v) !== (int) $ai['embed_dimension'] || !isset($d['index'])) {
			return array(null, 'The embeddings service returned vectors of the wrong size.', 0);
		}
		$out[(int) $d['index']] = rt_normalize($v);
	}
	ksort($out);
	$tokens = isset($j['usage']['total_tokens']) ? (int) $j['usage']['total_tokens'] : 0;
	qt_ai_log($ai, 'embed_call', array('type' => $type, 'count' => count($texts), 'http' => 200, 'ms' => $ms, 'tokens' => $tokens, 'outcome' => 'ok'));
	return array(array_values($out), null, $tokens);
}

function rt_normalize($v)
{
	$n = 0.0;
	foreach ($v as $x) {
		$n += $x * $x;
	}
	$n = sqrt($n);
	if ($n == 0.0) {
		return $v;
	}
	foreach ($v as $k => $x) {
		$v[$k] = $x / $n;
	}
	return $v;
}

function rt_pack($v)
{
	return pack('g*', ...$v);
}

function rt_unpack($blob)
{
	return array_values(unpack('g*', $blob));
}

/* ---------------------------------------------------------------- product index */

/**
 * Bring the product index up to date: embed new and changed products (content hash or index model
 * differs), delete products that are no longer quotable. $limit bounds the embedding work per call.
 * Returns array(stats, error).
 */
function rt_index_sync($ai, $connect, $limit = 5000)
{
	$stats = array('indexed' => 0, 'deleted' => 0, 'pending' => 0, 'tokens' => 0);
	if (!rt_enabled($ai)) {
		return array($stats, 'Semantic search is not configured.');
	}
	$cat = qt_ai_catalogue($connect);
	$model = rt_index_model($ai);
	$have = array();
	$r = mysqli_query($connect, "SELECT product_id, source_hash, model FROM product_index");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$have[(int) $row['product_id']] = $row;
	}
	$todo = array();
	foreach ($cat['products'] as $id => $p) {
		$text = rt_product_text($p);
		$hash = sha1($text);
		if (!isset($have[$id]) || $have[$id]['source_hash'] !== $hash || $have[$id]['model'] !== $model) {
			$todo[$id] = $text;
		}
	}
	$gone = array_diff(array_keys($have), array_keys($cat['products']));
	if (count($gone)) {
		mysqli_query($connect, "DELETE FROM product_index WHERE product_id IN (" . implode(',', array_map('intval', $gone)) . ")");
		$stats['deleted'] = count($gone);
	}
	$stats['pending'] = max(0, count($todo) - $limit);
	$todo = array_slice($todo, 0, $limit, true);
	foreach (array_chunk($todo, 128, true) as $batch) {
		list($vectors, $error, $tokens) = rt_embed($ai, array_values($batch), 'document');
		if ($error !== null) {
			$stats['pending'] += count($batch);
			return array($stats, $error);
		}
		$stats['tokens'] += $tokens;
		$i = 0;
		foreach ($batch as $id => $text) {
			mysqli_query($connect, "REPLACE INTO product_index (product_id, source_hash, source_text, model, dims, vector, indexed_at) VALUES ("
				. (int) $id . ", '" . sha1($text) . "', '" . mysqli_real_escape_string($connect, $text) . "', '" . mysqli_real_escape_string($connect, $model)
				. "', " . (int) $ai['embed_dimension'] . ", '" . mysqli_real_escape_string($connect, rt_pack($vectors[$i++])) . "', NOW())");
			$stats['indexed']++;
		}
	}
	mysqli_query($connect, "REPLACE INTO retrieval_meta (k, v) VALUES ('product_index_synced_at', NOW())");
	return array($stats, null);
}

/** Index health for the settings page and for the assistant's warnings. */
function rt_index_status($ai, $connect)
{
	$cat = qt_ai_catalogue($connect);
	$model = rt_index_model($ai);
	$fresh = $stale = $otherModel = 0;
	$have = array();
	$r = mysqli_query($connect, "SELECT product_id, source_hash, model FROM product_index");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$have[(int) $row['product_id']] = $row;
	}
	foreach ($cat['products'] as $id => $p) {
		if (!isset($have[$id])) {
			continue;
		}
		if ($have[$id]['model'] !== $model) {
			$otherModel++;
		} elseif ($have[$id]['source_hash'] !== sha1(rt_product_text($p))) {
			$stale++;
		} else {
			$fresh++;
		}
	}
	$m = mysqli_query($connect, "SELECT v FROM retrieval_meta WHERE k = 'product_index_synced_at'");
	$synced = $m ? mysqli_fetch_row($m) : null;
	return array('enabled' => rt_enabled($ai), 'model' => $model, 'products' => count($cat['products']), 'fresh' => $fresh, 'stale' => $stale,
		'other_model' => $otherModel, 'missing' => count($cat['products']) - $fresh - $stale - $otherModel,
		'orphans' => count(array_diff(array_keys($have), array_keys($cat['products']))), 'synced_at' => $synced ? $synced[0] : null);
}

/**
 * Up-to-date product vectors only (current model, unchanged source text, still in the catalogue).
 * Outdated rows are never searched, so a changed product cannot be found by its old description.
 */
function rt_fresh_vectors($ai, $connect)
{
	$cat = qt_ai_catalogue($connect);
	$model = rt_index_model($ai);
	$out = array();
	$r = mysqli_query($connect, "SELECT product_id, source_hash, vector FROM product_index WHERE model = '" . mysqli_real_escape_string($connect, $model)
		. "' AND dims = " . (int) $ai['embed_dimension']);
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$id = (int) $row['product_id'];
		if (isset($cat['products'][$id]) && $row['source_hash'] === sha1(rt_product_text($cat['products'][$id]))) {
			$out[$id] = rt_unpack($row['vector']);
		}
	}
	return $out;
}

/** Top-k product ids by dot product (vectors are unit length). */
function rt_top($queryVector, $vectors, $k)
{
	$scores = array();
	foreach ($vectors as $id => $v) {
		$s = 0.0;
		foreach ($queryVector as $i => $q) {
			$s += $q * $v[$i];
		}
		$scores[$id] = $s;
	}
	arsort($scores);
	return array_slice($scores, 0, $k, true);
}

/**
 * Semantic candidates for requirement rows (one batched embeddings call). Small index gaps are
 * filled first (auto-sync up to embed_auto_index products). Returns array(row_id => [ids], status).
 */
function rt_semantic_for_rows($ai, $connect, $rows, $rowIds = null)
{
	$status = array('used' => false, 'note' => '');
	if (!rt_enabled($ai)) {
		$status['note'] = 'semantic search off (no embeddings key)';
		return array(array(), $status);
	}
	list($sync, $syncError) = rt_index_sync($ai, $connect, (int) $ai['embed_auto_index']);
	$queries = array();
	foreach ($rows as $row) {
		if (!empty($row['removed']) || ($rowIds !== null && !in_array($row['row_id'], $rowIds, true))) {
			continue;
		}
		$b = qt_ai_row_basis($row);
		$text = trim(implode(' | ', array_filter(array($row['original_text'], $b['description'], $b['terms'], $b['finish'], $b['dimensions']))));
		if ($text !== '') {
			$queries[$row['row_id']] = $text;
		}
	}
	if (!count($queries)) {
		return array(array(), $status);
	}
	list($vectors, $error) = rt_embed($ai, array_values($queries), 'query');
	if ($error !== null) {
		$status['note'] = $error . ' Keyword search only.';
		return array(array(), $status);
	}
	$index = rt_fresh_vectors($ai, $connect);
	$out = array();
	$i = 0;
	foreach ($queries as $rowId => $q) {
		$out[$rowId] = array_keys(rt_top($vectors[$i++], $index, (int) $ai['semantic_candidates']));
	}
	$status['used'] = true;
	$status['note'] = ($sync['pending'] > 0 ? $sync['pending'] . ' changed products not indexed yet (left out of semantic search). ' : '')
		. ($syncError !== null ? 'Index update failed: ' . $syncError : '');
	return array($out, $status);
}

/**
 * Merge candidate lists: exact code/name matches first, then reciprocal-rank fusion of the keyword
 * and semantic rankings. Every id must still exist in the current catalogue. Bounded to $limit.
 */
function rt_fuse($cat, $code, $keyword, $semantic, $limit)
{
	$exact = array();
	$compact = qt_ai_compact((string) $code);
	if ($compact !== '' && isset($cat['by_compact'][$compact])) {
		$exact = $cat['by_compact'][$compact];
	}
	$scores = array();
	foreach (array($keyword, $semantic) as $list) {
		foreach (array_values($list) as $rank => $id) {
			$scores[$id] = (isset($scores[$id]) ? $scores[$id] : 0) + 1 / (RT_RRF_K + $rank + 1);
		}
	}
	arsort($scores);
	$ids = array_merge($exact, array_keys($scores));
	$out = array();
	foreach ($ids as $id) {
		if (isset($cat['products'][$id]) && !in_array($id, $out, true)) {
			$out[] = $id;
		}
		if (count($out) >= $limit) {
			break;
		}
	}
	return $out;
}

/* ---------------------------------------------------------------- approved documents */

/**
 * Split approved text into chunks: each numbered point or paragraph is its own chunk (an excerpt then
 * quotes exactly one term); paragraphs longer than about 800 characters are split at sentence ends.
 */
function rt_chunks($text)
{
	$paras = preg_split('/\n\s*\n|\n(?=\s*(\d+[.)]|[-*•])\s)/u', str_replace("\r\n", "\n", (string) $text));
	$out = array();
	foreach ($paras as $p) {
		$p = trim($p);
		if ($p === '') {
			continue;
		}
		$buf = '';
		foreach (preg_split('/(?<=[.!?।])\s+/u', $p) as $sentence) {
			if ($buf !== '' && qt_ai_strlen($buf) + qt_ai_strlen($sentence) > 800) {
				$out[] = $buf;
				$buf = '';
			}
			$buf = $buf === '' ? $sentence : $buf . ' ' . $sentence;
		}
		if ($buf !== '') {
			$out[] = $buf;
		}
	}
	return $out;
}

/** Approve a document: it becomes searchable, with chunks (and vectors when configured). Returns error or null. */
function rt_doc_approve($ai, $connect, $id, $user)
{
	$r = mysqli_query($connect, "SELECT * FROM approved_documents WHERE id = " . (int) $id);
	$doc = $r ? mysqli_fetch_assoc($r) : null;
	if (!$doc) {
		return 'Document not found.';
	}
	if ($doc['status'] !== 'draft') {
		return 'Only a draft document can be approved.';
	}
	$chunks = rt_chunks($doc['content']);
	if (!count($chunks)) {
		return 'The document has no text.';
	}
	$vectors = null;
	if (rt_enabled($ai)) {
		list($vectors, $error) = rt_embed($ai, $chunks, 'document');
		if ($error !== null) {
			$vectors = null;   // keyword ranking still works; vectors are added on the next index update
		}
	}
	mysqli_query($connect, 'START TRANSACTION');
	mysqli_query($connect, "DELETE FROM document_chunks WHERE document_id = " . (int) $id);
	foreach ($chunks as $i => $c) {
		mysqli_query($connect, "INSERT INTO document_chunks (document_id, seq, text, model, vector) VALUES (" . (int) $id . ", " . ($i + 1) . ", '"
			. mysqli_real_escape_string($connect, $c) . "', " . ($vectors ? "'" . mysqli_real_escape_string($connect, rt_index_model($ai)) . "'" : 'NULL') . ", "
			. ($vectors ? "'" . mysqli_real_escape_string($connect, rt_pack($vectors[$i])) . "'" : 'NULL') . ")");
	}
	// Approving a new version retires the previous approved version of the same document.
	mysqli_query($connect, "UPDATE approved_documents SET status = 'retired' WHERE doc_key = '" . mysqli_real_escape_string($connect, $doc['doc_key'])
		. "' AND status = 'approved' AND id <> " . (int) $id);
	mysqli_query($connect, "UPDATE approved_documents SET status = 'approved', approved_by = '" . mysqli_real_escape_string($connect, $user)
		. "', approved_at = NOW() WHERE id = " . (int) $id);
	mysqli_query($connect, 'COMMIT');
	return null;
}

/**
 * Approved documents that apply: status approved, effective on $date, and for the given GST state code
 * (or all states). Filtering happens before any ranking. $type null = any type.
 */
function rt_applicable_documents($connect, $type, $stateCode, $date = null)
{
	$date = $date === null ? date('Y-m-d') : $date;
	$where = "status = 'approved' AND effective_from <= '" . mysqli_real_escape_string($connect, $date) . "'"
		. " AND (effective_to IS NULL OR effective_to >= '" . mysqli_real_escape_string($connect, $date) . "')";
	if ($type !== null) {
		$where .= " AND doc_type = '" . mysqli_real_escape_string($connect, $type) . "'";
	}
	$out = array();
	$r = mysqli_query($connect, "SELECT * FROM approved_documents WHERE $where ORDER BY version DESC");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$states = array_map('trim', explode(',', $row['states']));
		if (in_array('ALL', $states, true) || ($stateCode !== '' && in_array($stateCode, $states, true))) {
			$out[] = $row;
		}
	}
	return $out;
}

/** Document reference kept with a PI or shown with an excerpt. */
function rt_doc_ref($doc)
{
	return array('id' => (int) $doc['id'], 'key' => $doc['doc_key'], 'title' => $doc['title'], 'type' => $doc['doc_type'], 'version' => (int) $doc['version'],
		'effective_from' => $doc['effective_from'], 'effective_to' => $doc['effective_to'], 'states' => $doc['states'], 'source' => $doc['source_location']);
}

/**
 * Search approved documents: filter by applicability first, then rank chunks by semantic similarity
 * (when vectors exist) or keyword overlap. Returns excerpts with their document reference.
 */
function rt_search_documents($ai, $connect, $query, $stateCode, $type = null, $k = 3)
{
	$docs = rt_applicable_documents($connect, $type, $stateCode);
	if (!count($docs) || trim($query) === '') {
		return array();
	}
	$byId = array();
	foreach ($docs as $d) {
		$byId[(int) $d['id']] = $d;
	}
	$chunks = array();
	$r = mysqli_query($connect, "SELECT id, document_id, seq, text, model, vector FROM document_chunks WHERE document_id IN (" . implode(',', array_keys($byId)) . ")");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$chunks[] = $row;
	}
	$scores = array();
	$qv = null;
	if (rt_enabled($ai)) {
		list($v, $error) = rt_embed($ai, array($query), 'query');
		$qv = $error === null ? $v[0] : null;
	}
	$qt = qt_ai_tokens($query);
	foreach ($chunks as $i => $c) {
		// Hybrid: keyword overlap plus semantic similarity when the chunk has a vector from the current model.
		$s = count(array_intersect_key($qt, qt_ai_tokens($c['text']))) / max(1, count($qt));
		if ($qv !== null && $c['vector'] !== null && $c['model'] === rt_index_model($ai)) {
			$cv = rt_unpack($c['vector']);
			foreach ($qv as $j => $x) {
				$s += $x * $cv[$j];
			}
		}
		$scores[$i] = $s;
	}
	arsort($scores);
	$out = array();
	foreach (array_slice($scores, 0, $k, true) as $i => $s) {
		if ($s <= 0) {
			continue;
		}
		$c = $chunks[$i];
		$out[] = array('chunk_id' => (int) $c['id'], 'text' => $c['text'], 'document' => rt_doc_ref($byId[(int) $c['document_id']]));
	}
	return $out;
}
