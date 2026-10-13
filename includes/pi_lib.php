<?php
/**
 * AI Assistant: conversations, the authoritative Proforma Invoice (PI) draft, pricing,
 * totals, validated operations and document generation.
 *
 * Requires DB/conn.php, includes/quotation_lib.php, includes/ai_provider.php and
 * includes/ai_import_lib.php (row format, extraction, catalogue matching are reused).
 * Tables: cli/migrate_pi.php. Prices always come from the database; the model never
 * supplies a rate, tax, discount, bank detail or term.
 */

define('PI_DRAFT_SCHEMA', 1);
define('PI_MAX_ROWS', 100);
define('PI_MAX_CHARGE', 10000000);

/* ---------------------------------------------------------------- reference data */

/** GST state codes (first two digits of a GSTIN) and names. */
function pi_states()
{
	return array(
		'01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
		'06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
		'12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
		'18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
		'24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
		'31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands',
		'36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
	);
}

/** Common short forms and Hindi/Marathi names, mapped to GST state codes. */
function pi_state_aliases()
{
	return array(
		'mh' => '27', 'maha' => '27', 'महाराष्ट्र' => '27', 'gj' => '24', 'गुजरात' => '24', 'mp' => '23', 'मध्य प्रदेश' => '23', 'मध्यप्रदेश' => '23',
		'cg' => '22', 'ct' => '22', 'छत्तीसगढ़' => '22', 'छत्तीसगढ' => '22', 'ts' => '36', 'tg' => '36', 'तेलंगाना' => '36', 'ka' => '29', 'कर्नाटक' => '29',
		'ga' => '30', 'गोवा' => '30', 'rj' => '08', 'राजस्थान' => '08', 'dl' => '07', 'दिल्ली' => '07', 'new delhi' => '07', 'up' => '09',
		'उत्तर प्रदेश' => '09', 'ap' => '37', 'आंध्र प्रदेश' => '37', 'tn' => '33', 'तमिलनाडु' => '33', 'kl' => '32', 'wb' => '19', 'od' => '21',
		'orissa' => '21', 'jh' => '20', 'br' => '10', 'hr' => '06', 'pb' => '03', 'uk' => '05', 'uttaranchal' => '05', 'hp' => '02',
		'jk' => '01', 'j&k' => '01', 'as' => '18', 'chattisgarh' => '22', 'chhatisgarh' => '22',
	);
}

/** Resolve a state from a code, GST code, name or alias. Returns array(code as a 2-character string, name) or null. */
function pi_find_state($text)
{
	$found = pi_find_state_raw($text);
	// PHP turns array keys like "27" into integers: always hand out the code as text ("27", "08").
	return $found === null ? null : array(str_pad((string) $found[0], 2, '0', STR_PAD_LEFT), $found[1]);
}

function pi_find_state_raw($text)
{
	$t = trim(qt_ai_lower((string) $text));
	if ($t === '') {
		return null;
	}
	$states = pi_states();
	if (preg_match('/^\d{1,2}$/', $t)) {
		$code = str_pad($t, 2, '0', STR_PAD_LEFT);
		return isset($states[$code]) ? array($code, $states[$code]) : null;
	}
	foreach ($states as $code => $name) {
		if (qt_ai_lower($name) === $t) {
			return array($code, $name);
		}
	}
	$aliases = pi_state_aliases();
	if (isset($aliases[$t])) {
		return array($aliases[$t], $states[$aliases[$t]]);
	}
	return null;
}

function pi_valid_gstin($gstin)
{
	return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', (string) $gstin);
}

/* ---------------------------------------------------------------- conversations */

function pi_tables_exist($connect)
{
	$r = mysqli_query($connect, "SHOW TABLES LIKE 'ai_pi_drafts'");
	return $r && mysqli_num_rows($r) > 0;
}

function pi_esc($connect, $v)
{
	return mysqli_real_escape_string($connect, (string) $v);
}

function pi_conv_create($connect, $title = '')
{
	$id = bin2hex(random_bytes(16));
	$uid = (int) $_SESSION['user_id'];
	$now = date('Y-m-d H:i:s');
	$ok = mysqli_query($connect, "INSERT INTO ai_conversations (id, user_id, username, title, created_at, updated_at) VALUES ('"
		. $id . "', " . $uid . ", '" . pi_esc($connect, $_SESSION['username']) . "', '" . pi_esc($connect, qt_ai_cut($title, 200)) . "', '$now', '$now')")
		&& mysqli_query($connect, "INSERT INTO ai_pi_drafts (conversation_id, version, data, updated_at) VALUES ('" . $id . "', 1, '"
			. pi_esc($connect, json_encode(pi_draft_default())) . "', '$now')");
	return $ok ? $id : null;
}

/** The conversation, only for its owner. Returns array(row, error). */
function pi_conv_get($connect, $id)
{
	if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/', $id)) {
		return array(null, 'Conversation not found.');
	}
	$r = mysqli_query($connect, "SELECT * FROM ai_conversations WHERE id = '" . $id . "'");
	$row = $r ? mysqli_fetch_assoc($r) : null;
	if (!$row || (int) $row['archived'] === 1) {
		return array(null, 'Conversation not found.');
	}
	if ((int) $row['user_id'] !== (int) $_SESSION['user_id']) {
		return array(null, 'You do not have access to this conversation.');
	}
	return array($row, null);
}

function pi_has_pinned($connect)
{
	static $has = null;
	if ($has === null) {
		$has = ($c = mysqli_query($connect, "SHOW COLUMNS FROM ai_conversations LIKE 'pinned'")) && mysqli_num_rows($c) > 0;
	}
	return $has;
}

/**
 * Delete one conversation with its messages, draft and attached images. Finalized PIs (pi_documents) and their
 * quotation rows are business records and are kept. Returns true when it was deleted.
 */
function pi_conv_delete($connect, $storageDir, $id)
{
	if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/', $id)) {
		return false;
	}
	mysqli_query($connect, "DELETE FROM ai_messages WHERE conversation_id = '$id'");
	mysqli_query($connect, "DELETE FROM ai_pi_drafts WHERE conversation_id = '$id'");
	$ok = mysqli_query($connect, "DELETE FROM ai_conversations WHERE id = '$id'") && mysqli_affected_rows($connect) > 0;
	$dir = $storageDir !== '' ? $storageDir . '/chat/' . $id : '';
	if ($dir !== '' && is_dir($dir)) {
		foreach ((array) glob($dir . '/*') as $f) {
			@unlink($f);
		}
		@rmdir($dir);
	}
	return $ok;
}

/**
 * Keep only a user's latest $keep conversations (by last use); delete older ones. Pinned conversations are
 * never deleted and do not count towards $keep. A conversation that is busy (a turn running) is skipped.
 * Returns the number deleted.
 */
function pi_conv_prune($connect, $storageDir, $userId, $keep)
{
	$keep = (int) $keep;
	if ($keep <= 0) {
		return 0;
	}
	$r = mysqli_query($connect, "SELECT c.id, (d.busy_until IS NOT NULL AND d.busy_until >= NOW()) AS busy FROM ai_conversations c
		LEFT JOIN ai_pi_drafts d ON d.conversation_id = c.id
		WHERE c.user_id = " . (int) $userId . (pi_has_pinned($connect) ? ' AND c.pinned = 0' : '') . "
		ORDER BY c.updated_at DESC, c.created_at DESC LIMIT 1000000 OFFSET " . $keep);
	$n = 0;
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		if (!(int) $row['busy'] && pi_conv_delete($connect, $storageDir, $row['id'])) {
			$n++;
		}
	}
	return $n;
}

function pi_conv_list($connect)
{
	$out = array();
	$pin = pi_has_pinned($connect);
	$r = mysqli_query($connect, "SELECT id, title, updated_at" . ($pin ? ', pinned' : '') . " FROM ai_conversations WHERE user_id = " . (int) $_SESSION['user_id']
		. " AND archived = 0 ORDER BY " . ($pin ? 'pinned DESC, ' : '') . "updated_at DESC LIMIT 200");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$out[] = array('id' => $row['id'], 'title' => $row['title'] !== '' ? $row['title'] : 'New conversation', 'updated_at' => $row['updated_at'],
			'pinned' => $pin && (int) $row['pinned'] === 1);
	}
	return $out;
}

function pi_conv_touch($connect, $id, $title = null)
{
	mysqli_query($connect, "UPDATE ai_conversations SET updated_at = NOW()"
		. ($title !== null ? ", title = IF(title = '', '" . pi_esc($connect, qt_ai_cut($title, 200)) . "', title)" : '')
		. " WHERE id = '" . $id . "'");
}

/** Add a message; a repeated client_id returns the existing message id instead (no duplicate). */
function pi_msg_add($connect, $convId, $role, $body, $clientId = null)
{
	$cid = ($clientId !== null && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientId)) ? "'" . $clientId . "'" : 'NULL';
	$ok = mysqli_query($connect, "INSERT INTO ai_messages (conversation_id, role, client_id, body, created_at) VALUES ('" . $convId . "', '"
		. pi_esc($connect, $role) . "', $cid, '" . pi_esc($connect, json_encode($body, JSON_UNESCAPED_UNICODE)) . "', NOW())");
	return $ok ? (int) mysqli_insert_id($connect) : null;
}

function pi_msg_by_client($connect, $convId, $clientId)
{
	if (!is_string($clientId) || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientId)) {
		return null;
	}
	$r = mysqli_query($connect, "SELECT id FROM ai_messages WHERE conversation_id = '" . $convId . "' AND client_id = '" . $clientId . "'");
	$row = $r ? mysqli_fetch_row($r) : null;
	return $row ? (int) $row[0] : null;
}

function pi_msg_list($connect, $convId, $afterId = 0, $limit = 200)
{
	$out = array();
	$r = mysqli_query($connect, "SELECT id, role, body, created_at FROM ai_messages WHERE conversation_id = '" . $convId . "' AND id > "
		. (int) $afterId . " ORDER BY id ASC LIMIT " . (int) $limit);
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$body = json_decode($row['body'], true);
		$out[] = array('id' => (int) $row['id'], 'role' => $row['role'], 'body' => is_array($body) ? $body : array('text' => ''), 'created_at' => $row['created_at']);
	}
	return $out;
}

/* ---------------------------------------------------------------- draft */

function pi_draft_default()
{
	return array(
		'schema' => PI_DRAFT_SCHEMA,
		'doc_type' => 'PI',
		'type' => null,                 // price basis: DP or MRP (named "type" so the shared row helpers can read it)
		'customer' => array('name' => '', 'address' => '', 'gstin' => '', 'phone' => '', 'state_code' => '', 'state_name' => ''),
		'delivery_state' => array('code' => '', 'name' => ''),
		'pricing' => array('mode' => null, 'code' => '', 'name' => ''),   // mode: 'state' | 'standard'
		'price_state' => '',            // state list code for the shared row helpers ('' = standard)
		'rows' => array(),
		'next_row' => 1,
		'discount_percent' => null,
		'charges' => array(),
		'questions' => array(),
		'attachments' => array(),
		'document_notes' => '',
		'instructions' => array('standing' => '', 'import' => ''),
		'documents' => array(),
	);
}

/** Returns array(data, version, meta) or array(null, 0, null). */
function pi_draft_load($connect, $convId)
{
	$r = mysqli_query($connect, "SELECT * FROM ai_pi_drafts WHERE conversation_id = '" . $convId . "'");
	$row = $r ? mysqli_fetch_assoc($r) : null;
	if (!$row) {
		return array(null, 0, null);
	}
	$data = json_decode($row['data'], true);
	$data = is_array($data) ? array_merge(pi_draft_default(), $data) : pi_draft_default();
	foreach (array(&$data['customer']['state_code'], &$data['delivery_state']['code']) as &$code) {
		if ($code !== '' && $code !== null) {
			$code = str_pad((string) $code, 2, '0', STR_PAD_LEFT);   // older drafts saved some codes as numbers
		}
	}
	unset($code);
	return array($data, (int) $row['version'], $row);
}

/**
 * Save with an optimistic version check. Every save is a new version and clears any
 * earlier confirmation, so a confirmed preview can never cover changed details.
 * Returns the new version, or null when someone else saved first.
 */
function pi_draft_save($connect, $convId, $data, $expectedVersion)
{
	$ok = mysqli_query($connect, "UPDATE ai_pi_drafts SET data = '" . pi_esc($connect, json_encode($data, JSON_UNESCAPED_UNICODE))
		. "', version = version + 1, confirmed_version = NULL, confirmed_hash = NULL, updated_at = NOW() WHERE conversation_id = '"
		. $convId . "' AND version = " . (int) $expectedVersion);
	return ($ok && mysqli_affected_rows($connect) === 1) ? (int) $expectedVersion + 1 : null;
}

/** One assistant turn at a time per conversation (also guards against double submits). */
function pi_draft_lock($connect, $convId, $seconds)
{
	$ok = mysqli_query($connect, "UPDATE ai_pi_drafts SET busy_until = DATE_ADD(NOW(), INTERVAL " . (int) $seconds . " SECOND)
		WHERE conversation_id = '" . $convId . "' AND (busy_until IS NULL OR busy_until < NOW())");
	return $ok && mysqli_affected_rows($connect) === 1;
}

function pi_draft_unlock($connect, $convId)
{
	mysqli_query($connect, "UPDATE ai_pi_drafts SET busy_until = NULL WHERE conversation_id = '" . $convId . "'");
}

/** Display numbers (1, 2, ...) of the rows still on the draft, keyed by row_id. */
function pi_row_numbers($draft)
{
	$n = 0;
	$out = array();
	foreach ($draft['rows'] as $row) {
		if (empty($row['removed'])) {
			$out[$row['row_id']] = ++$n;
		}
	}
	return $out;
}

/**
 * The draft row an operation refers to: its stable line id (row_id, e.g. "r3") first,
 * the displayed item number only as a fallback. Removed rows are never returned.
 */
function pi_op_row_index($draft, $op)
{
	if (isset($op['line']) && is_string($op['line']) && preg_match('/^r\d{1,5}$/', $op['line'])) {
		$i = qt_ai_find_row($draft, $op['line']);
		return ($i !== null && empty($draft['rows'][$i]['removed'])) ? $i : null;
	}
	return isset($op['row']) && is_int($op['row']) ? pi_row_index_by_number($draft, $op['row']) : null;
}

function pi_row_index_by_number($draft, $number)
{
	$numbers = pi_row_numbers($draft);
	$rowId = array_search((int) $number, $numbers, true);
	return $rowId === false ? null : qt_ai_find_row($draft, $rowId);
}

/* ---------------------------------------------------------------- price lists */

/**
 * Price lists with identity (code, version, upload time, file), validity and kind:
 * master (company master list, used before the software's own prices), state (one state) or region
 * (several states; covers = their GST state codes).
 */
function pi_price_lists($connect)
{
	$out = array();
	if (!qt_state_tables_exist($connect)) {
		return $out;
	}
	$cols = array();
	$c = mysqli_query($connect, 'SHOW COLUMNS FROM price_states');
	while ($c && ($x = mysqli_fetch_assoc($c))) {
		$cols[$x['Field']] = true;
	}
	$r = mysqli_query($connect, "SELECT code, name, active, source_file, rows_loaded, updated_by, updated_at"
		. (isset($cols['version']) ? ', version, valid_until' : '') . (isset($cols['kind']) ? ', kind, covers, effective_from' : '')
		. (isset($cols['priority']) ? ', priority' : '') . " FROM price_states ORDER BY " . (isset($cols['priority']) ? 'priority, ' : '') . "name");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$out[$row['code']] = array(
			'code' => $row['code'], 'name' => $row['name'], 'active' => (int) $row['active'] === 1,
			'version' => isset($row['version']) ? (int) $row['version'] : 1, 'valid_until' => isset($row['valid_until']) ? $row['valid_until'] : null,
			'source_file' => $row['source_file'], 'rows_loaded' => (int) $row['rows_loaded'], 'updated_at' => $row['updated_at'],
			'kind' => isset($row['kind']) ? $row['kind'] : 'state',
			'covers' => isset($row['covers']) && $row['covers'] !== '' ? explode(',', $row['covers']) : array(),
			'effective_from' => isset($row['effective_from']) ? $row['effective_from'] : null,
			'priority' => isset($row['priority']) ? (int) $row['priority'] : 100,
		);
	}
	return $out;
}

function pi_list_expired($l)
{
	return $l['valid_until'] !== null && $l['valid_until'] !== '' && $l['valid_until'] < date('Y-m-d');
}

/**
 * The default lists, always checked without being mentioned (e.g. the Master price list, then the Office
 * furniture price list): active, current lists of kind "master", in their priority order.
 */
function pi_master_lists($connect)
{
	$out = array();
	foreach (pi_price_lists($connect) as $l) {
		if ($l['kind'] === 'master' && $l['active'] && !pi_list_expired($l)) {
			$out[] = $l;
		}
	}
	return $out;
}

/** Name of the default pricing: the default lists in order, then the software's own prices. */
function pi_standard_name($connect)
{
	$names = array_map(function ($l) { return $l['name']; }, pi_master_lists($connect));
	return $names ? implode(', ', $names) . ', then software prices' : 'Software prices';
}

/** Identity of the default pricing: the software's prices (pricelist checksum) plus each default list's version. */
function pi_standard_identity($connect)
{
	$r = mysqli_query($connect, 'CHECKSUM TABLE pricelist');
	$row = $r ? mysqli_fetch_assoc($r) : null;
	$sum = $row && isset($row['Checksum']) ? (string) $row['Checksum'] : '';
	$masters = array_map(function ($m) { return array('code' => $m['code'], 'name' => $m['name'], 'version' => $m['version'], 'updated_at' => $m['updated_at']); },
		pi_master_lists($connect));
	return array('code' => 'STANDARD', 'name' => pi_standard_name($connect), 'version' => $sum !== '' ? substr(sha1($sum), 0, 10) : 'unknown',
		'master' => $masters ? $masters[0] : null, 'masters' => $masters);
}

/** State or region lists (not master lists) that serve a GST state code. */
function pi_lists_for_state($lists, $stateCode)
{
	$out = array();
	foreach ($lists as $code => $l) {
		if ($l['kind'] === 'master') {
			continue;
		}
		$ls = pi_find_state($l['name']) ?: pi_find_state($code);
		if (in_array($stateCode, $l['covers'], true) || ($ls !== null && $ls[0] === $stateCode)) {
			$out[$code] = $l;
		}
	}
	return $out;
}

/**
 * Resolve the price list the employee named: a state/region list by code or name, a state served by
 * an uploaded list, or "standard"/"master" (master list, then software prices). Returns
 * array(pricing, error); the error explains a missing or inactive list instead of substituting another one.
 */
function pi_resolve_price_list($connect, $text)
{
	$t = trim((string) $text);
	if ($t === '') {
		return array(null, 'Which price list should be used?');
	}
	if (preg_match('/^(standard|default|std|master|master list|software|स्टँडर्ड|सामान्य|मास्टर)$/iu', $t)) {
		return array(array('mode' => 'standard', 'code' => '', 'name' => pi_standard_name($connect)), null);
	}
	$lists = pi_price_lists($connect);
	$lt = qt_ai_lower($t);
	$match = null;
	foreach ($lists as $code => $l) {
		if (qt_ai_lower($code) === $lt || qt_ai_lower($l['name']) === $lt) {
			$match = $l;
		}
	}
	if ($match !== null && $match['kind'] === 'master') {
		return array(array('mode' => 'standard', 'code' => '', 'name' => pi_standard_name($connect)), null);
	}
	if ($match === null) {
		$state = pi_find_state($t);
		if ($state !== null) {
			$found = pi_lists_for_state($lists, $state[0]);
			if (count($found) > 1) {
				return array(null, 'Several price lists serve ' . $state[1] . ': ' . implode(', ', array_map(function ($l) { return $l['name']; }, $found)) . '. Which one?');
			}
			$match = $found ? reset($found) : null;
			if ($match === null) {
				$regions = array();
				foreach ($lists as $l) {
					if ($l['kind'] === 'region' && $l['active'] && !pi_list_expired($l)) {
						$regions[] = '"' . $l['name'] . '"';
					}
				}
				return array(null, 'There is no ' . $state[1] . ' price list in the system. An administrator can import it (cli/import_pricelists.php). '
					. 'I will not use another state\'s prices. You can also choose the master list (then software prices) explicitly'
					. ($regions ? ', or name a region list (' . implode(', ', $regions) . ') if that is what you want.' : '.'));
			}
		}
	}
	$names = array();
	foreach ($lists as $l) {
		if ($l['kind'] !== 'master') {
			$names[] = $l['name'] . ' (' . $l['code'] . ')';
		}
	}
	if ($match === null) {
		return array(null, 'I could not find a price list called "' . $t . '". Available: ' . (count($names) ? implode(', ', $names) : 'no state lists')
			. ', or the master list (then software prices).');
	}
	if (!$match['active']) {
		return array(null, 'The ' . $match['name'] . ' price list is switched off.');
	}
	if (pi_list_expired($match)) {
		return array(null, 'The ' . $match['name'] . ' price list expired on ' . date('d-m-Y', strtotime($match['valid_until']))
			. '. An administrator needs to upload a current list; I will not use another state\'s prices.');
	}
	return array(array('mode' => 'state', 'code' => $match['code'], 'name' => $match['name']), null);
}

/**
 * Pricing actually used for the draft: the state/region list the employee named (e.g. Bihar, South);
 * otherwise the default lists in order, then software prices. A state or region list is never chosen
 * automatically, not even from the customer's state. 'auto' marks a pricing the employee did not choose.
 */
function pi_effective_pricing($connect, $draft)
{
	if (isset($draft['pricing']['mode']) && $draft['pricing']['mode'] !== null) {
		return $draft['pricing'] + array('auto' => false);
	}
	return array('mode' => 'standard', 'code' => '', 'name' => pi_standard_name($connect), 'auto' => true);
}

/** Current identity and validity of the price list in use. Returns array(info, problem|null). */
function pi_price_list_status($connect, $pricing)
{
	if (!isset($pricing['mode']) || $pricing['mode'] === null) {
		return array(null, 'No price list chosen yet.');
	}
	$std = pi_standard_identity($connect);
	if ($pricing['mode'] === 'standard') {
		return array($std, null);
	}
	$lists = pi_price_lists($connect);
	if (!isset($lists[$pricing['code']])) {
		return array(null, 'The ' . $pricing['name'] . ' price list no longer exists.');
	}
	$l = $lists[$pricing['code']];
	$info = array('code' => $l['code'], 'name' => $l['name'], 'version' => $l['version'], 'updated_at' => $l['updated_at'],
		'source_file' => $l['source_file'], 'valid_until' => $l['valid_until'], 'kind' => $l['kind'], 'fallback' => $std);
	if (!$l['active']) {
		return array($info, 'The ' . $l['name'] . ' price list is switched off.');
	}
	if (pi_list_expired($l)) {
		return array($info, 'The ' . $l['name'] . ' price list expired on ' . date('d-m-Y', strtotime($l['valid_until'])) . '. Upload a current list.');
	}
	return array($info, null);
}

/** Wood variant asked for in a requirement row ('TEAK', 'MANGO' or ''), unless set explicitly. */
function pi_row_variant($row)
{
	if (isset($row['variant']) && $row['variant'] !== '') {
		return $row['variant'];
	}
	$t = qt_ai_lower(implode(' ', array($row['requirement'], $row['original_text'], $row['finish'], $row['other_specs'])));
	$teak = preg_match('/\bteak|sagwan|sagaun|सागवान|सागौन/u', $t);
	$mango = preg_match('/\bmango|आम की|आंबा|आम्बा/u', $t);
	return $teak && !$mango ? 'TEAK' : ($mango && !$teak ? 'MANGO' : '');
}

function pi_has_net_column($connect)
{
	static $has = null;
	if ($has === null) {
		$has = ($c = mysqli_query($connect, "SHOW COLUMNS FROM state_pricelist LIKE 'net_price'")) && mysqli_num_rows($c) > 0;
	}
	return $has;
}

/** Has a list any rate for this basis (DP basis: DP or NET)? A list without one does not apply to that basis. */
function pi_list_has_basis($connect, $code, $basis)
{
	$cond = $basis === 'MRP' ? 'mrp_price IS NOT NULL' : (pi_has_net_column($connect) ? '(dp_price IS NOT NULL OR net_price IS NOT NULL)' : 'dp_price IS NOT NULL');
	$r = mysqli_query($connect, "SELECT 1 FROM state_pricelist WHERE state_code = '" . pi_esc($connect, $code) . "' AND $cond LIMIT 1");
	return $r && mysqli_num_rows($r) > 0;
}

/**
 * Rate of one product in one list: array(rate, type DP|MRP|NET, variant, needs_variant list).
 * DP basis: the DP rate, else the NET rate (NET = a third category: no discount). MRP basis: the MRP rate.
 * Wood variants (TEAK/MANGO) are separate rows; without a known variant the plain row is used, otherwise
 * the variants that have a rate are returned in needs_variant.
 */
function pi_list_rate($connect, $code, $productId, $basis, $variant)
{
	$net = pi_has_net_column($connect);
	$r = mysqli_query($connect, "SELECT mrp_price, dp_price" . ($net ? ', net_price, variant' : ", NULL AS net_price, '' AS variant")
		. " FROM state_pricelist WHERE state_code = '" . pi_esc($connect, $code) . "' AND p_prodId = " . (int) $productId);
	$byVariant = array();
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		if ($basis === 'MRP') {
			$pick = $row['mrp_price'] !== null ? array((float) $row['mrp_price'], 'MRP') : null;
		} else {
			$pick = $row['dp_price'] !== null ? array((float) $row['dp_price'], 'DP') : ($row['net_price'] !== null ? array((float) $row['net_price'], 'NET') : null);
		}
		if ($pick !== null) {
			$byVariant[$row['variant']] = $pick;
		}
	}
	$out = array('rate' => null, 'type' => null, 'variant' => '', 'needs_variant' => array());
	$use = $variant !== '' && isset($byVariant[$variant]) ? $variant : (isset($byVariant['']) ? '' : null);
	if ($use !== null) {
		list($out['rate'], $out['type']) = $byVariant[$use];
		$out['variant'] = $use;
	} elseif ($byVariant) {
		$out['needs_variant'] = array_keys($byVariant);
		sort($out['needs_variant']);
	}
	return $out;
}

/**
 * Rate of one product for a basis (DP/MRP), in this order: the chosen state/region list; the master
 * list; the software's own price. A product missing from a chosen state list is never priced from
 * another list without the employee's consent (missing_state_rate + fallback). A state list with no
 * rates for the basis at all (e.g. a MRP-only list and DP basis) does not apply: basis_not_in_list.
 * Returns {rate, type, source: 'state'|'master'|'standard'|null, source_name, variant, needs_variant,
 * missing_state_rate, basis_not_in_list, fallback, standard_rate, standard_rows}.
 */
function pi_product_rate($connect, $productId, $basis, $pricing, $variant = '')
{
	$col = $basis === 'MRP' ? 'mrp_price' : 'dp_price';
	$pid = (int) $productId;
	$r = mysqli_query($connect, "SELECT COUNT(*) AS n, MAX($col) AS p FROM pricelist WHERE p_prodId = $pid");
	$std = $r ? mysqli_fetch_assoc($r) : array('n' => 0, 'p' => null);
	$standardRate = (int) $std['n'] === 1 ? (float) $std['p'] : null;
	$chain = array('rate' => null, 'type' => null, 'source' => null, 'source_name' => '', 'variant' => '', 'needs_variant' => array());
	foreach (pi_master_lists($connect) as $master) {
		$m = pi_list_rate($connect, $master['code'], $pid, $basis, $variant);
		if ($m['rate'] !== null || $m['needs_variant']) {
			$chain = array_merge($chain, $m, array('source' => $m['rate'] !== null ? 'master' : null, 'source_name' => $master['name']));
			break;   // the first default list that has the product decides
		}
	}
	if ($chain['rate'] === null && !$chain['needs_variant'] && $standardRate !== null) {
		$chain = array_merge($chain, array('rate' => $standardRate, 'type' => $basis, 'source' => 'standard', 'source_name' => 'software price'));
	}
	$out = array_merge($chain, array('missing_state_rate' => false, 'basis_not_in_list' => false, 'fallback' => null,
		'standard_rate' => $standardRate, 'standard_rows' => (int) $std['n']));
	if ($pricing['mode'] !== 'state') {
		return $out;
	}
	if (!pi_list_has_basis($connect, $pricing['code'], $basis)) {
		$out['basis_not_in_list'] = true;
		return $out;
	}
	$s = pi_list_rate($connect, $pricing['code'], $pid, $basis, $variant);
	if ($s['rate'] !== null || $s['needs_variant']) {
		return array_merge($out, $s, array('source' => $s['rate'] !== null ? 'state' : null, 'source_name' => $pricing['name']));
	}
	return array_merge($out, array('rate' => null, 'type' => null, 'source' => null, 'source_name' => '', 'variant' => '', 'needs_variant' => array(),
		'missing_state_rate' => true, 'fallback' => $chain['rate'] !== null ? $chain : null));
}

/** "Bihar list (MRP)", "Master price list (NET, teak)", "software price (DP)". */
function pi_rate_label($rate)
{
	$bits = array($rate['type']);
	if ($rate['variant'] !== '') {
		$bits[] = strtolower($rate['variant']);
	}
	$name = $rate['source_name'] . (preg_match('/\blist$/i', $rate['source_name']) ? '' : ' list');
	return ($rate['source'] === 'standard' ? 'software price' : $name) . ' (' . implode(', ', $bits) . ')';
}

/* ---------------------------------------------------------------- totals, blockers, preview */

/** Approved document content (company, bank, terms, signatory, tax): latest version per key. */
function pi_templates($connect)
{
	$out = array();
	$r = mysqli_query($connect, "SELECT t.* FROM doc_templates t JOIN (SELECT doc_key, MAX(version) v FROM doc_templates GROUP BY doc_key) m
		ON m.doc_key = t.doc_key AND m.v = t.version");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$out[$row['doc_key']] = array('version' => (int) $row['version'], 'source' => $row['source'], 'status' => $row['status'],
			'content' => json_decode($row['content'], true));
	}
	return $out;
}

/**
 * Terms for a PI: the latest approved "pi_terms" document that applies to the customer state and today
 * (filtered by applicability, never chosen by similarity); otherwise the terms template in use.
 */
function pi_terms_for($connect, $stateCode, $templates)
{
	if (function_exists('rt_applicable_documents') && ($t = mysqli_query($connect, "SHOW TABLES LIKE 'approved_documents'")) && mysqli_num_rows($t) > 0) {
		$docs = rt_applicable_documents($connect, 'pi_terms', $stateCode);
		if (count($docs)) {
			$d = $docs[0];
			$lines = array();
			foreach (preg_split('/\n+/', str_replace("\r\n", "\n", $d['content'])) as $l) {
				$l = trim(preg_replace('/^\s*(\d+[.)]|[-*•])\s*/u', '', $l));
				if ($l !== '') {
					$lines[] = $l;
				}
			}
			return array('heading' => $d['title'], 'lines' => $lines, 'source' => array_merge(array('kind' => 'approved_document'), rt_doc_ref($d)));
		}
	}
	$tc = isset($templates['terms']['content']) ? $templates['terms']['content'] : array('heading' => 'Terms and conditions', 'lines' => array());
	return array('heading' => $tc['heading'], 'lines' => $tc['lines'],
		'source' => array('kind' => 'template', 'key' => 'terms', 'version' => isset($templates['terms']['version']) ? $templates['terms']['version'] : null));
}

function pi_round($n)
{
	return round((float) $n + 0.0, 2);
}

/**
 * Compute the PI from the draft with current database prices. Server-side only.
 * Returns lines, totals, price-list identity, template references, blockers, warnings
 * and a hash of everything the employee confirms.
 */
function pi_compute($connect, $draft)
{
	$numbers = pi_row_numbers($draft);
	$basis = $draft['type'];
	$pricing = pi_effective_pricing($connect, $draft);
	list($listInfo, $listProblem) = pi_price_list_status($connect, $pricing);
	$templates = pi_templates($connect);
	$tax = isset($templates['tax']['content']['rate']) ? (float) $templates['tax']['content']['rate'] : null;
	$blockers = array();
	$warnings = array();
	$lines = array();
	$subtotal = 0.0;
	$netSubtotal = 0.0;   // NET-rate lines: a third category that carries no discount

	if ($basis !== 'DP' && $basis !== 'MRP') {
		$blockers[] = 'Price basis not chosen: DP (dealer) or MRP (retail)?';
	}
	if ($listProblem !== null) {
		$blockers[] = $listProblem;
	} elseif ($pricing['auto']) {
		$warnings[] = 'Price list: ' . $pricing['name'] . '.';
		$c0 = $draft['customer'];
		$served = $c0['state_code'] !== '' ? pi_lists_for_state(pi_price_lists($connect), $c0['state_code']) : array();
		foreach ($served as $l) {
			if ($l['active'] && !pi_list_expired($l)) {
				$warnings[] = 'There is a ' . $l['name'] . ' price list for ' . $c0['state_name'] . '. It is used only when you ask for it ("use ' . $l['name'] . ' prices").';
			}
		}
	}
	if ($pricing['mode'] === 'state' && ($basis === 'DP' || $basis === 'MRP') && $listProblem === null && !pi_list_has_basis($connect, $pricing['code'], $basis)) {
		$warnings[] = 'The ' . $pricing['name'] . ' list has no ' . $basis . ' rates, so ' . $basis . ' rates come from ' . pi_standard_name($connect) . '.';
	}
	$hasMaster = count(pi_master_lists($connect)) > 0;

	$views = array();
	$ids = array();
	foreach ($draft['rows'] as $row) {
		if (empty($row['removed']) && $row['product_id'] !== null) {
			$ids[] = (int) $row['product_id'];
		}
	}
	if (count($ids)) {
		$views = qt_ai_product_views($connect, $ids, $basis === 'MRP' ? 'MRP' : 'DP', '');
	}
	foreach ($draft['rows'] as $row) {
		if (!empty($row['removed'])) {
			continue;
		}
		$n = $numbers[$row['row_id']];
		$line = array('n' => $n, 'row_id' => $row['row_id'], 'requirement' => $row['requirement'], 'source_ref' => $row['source_ref'],
			'original_text' => $row['original_text'],
			'requested' => trim(implode('; ', array_filter(array($row['dimensions'], $row['finish'], $row['other_specs'])))),
			'quantity' => $row['quantity'], 'product' => null, 'rate' => null, 'amount' => null, 'rate_source' => null, 'issues' => array(),
			'spec_warnings' => array(),
			'source' => array('product_source' => $row['product_source'], 'ai_suggested_id' => isset($row['match']['suggested_id']) ? $row['match']['suggested_id'] : null,
				'match_status' => isset($row['match']['status']) ? $row['match']['status'] : null,
				'retrieval' => isset($row['retrieval']) ? $row['retrieval'] : null));
		if ($row['quantity'] === null) {
			$line['issues'][] = 'quantity missing';
			$blockers[] = 'Item ' . $n . ': quantity missing.';
		} elseif (!empty($row['quantity_checks'])) {
			$line['issues'][] = 'quantity needs checking';
			$blockers[] = 'Item ' . $n . ': check the quantity (' . implode('; ', $row['quantity_checks']) . ').';
		} elseif (!empty($row['quantity_defaulted'])) {
			$warnings[] = 'Item ' . $n . ': quantity set to ' . $row['quantity'] . ' by default (' . (isset($row['quantity_default_reason']) ? $row['quantity_default_reason'] : 'not given') . ').';
		}
		if ($row['product_id'] === null || !isset($views[(int) $row['product_id']])) {
			$line['issues'][] = 'product not chosen';
			$blockers[] = 'Item ' . $n . ' ("' . qt_ai_cut($row['requirement'], 60) . '"): product not chosen.';
		} else {
			$v = $views[(int) $row['product_id']];
			$line['product'] = array('id' => $v['id'], 'name' => $v['name'], 'color' => $v['color'], 'size' => $v['dimension'],
				'description' => $v['description'], 'thumb' => $v['thumb']);
			$line['source']['product_record'] = substr(sha1(json_encode(array($v['name'], $v['description'], $v['color'], $v['dimension']))), 0, 12);
			$line['spec_warnings'] = pi_spec_warnings($connect, $row, (int) $v['id']);
			if (count($line['spec_warnings'])) {
				$warnings[] = 'Item ' . $n . ': ' . implode(' ', $line['spec_warnings']);
			}
			if (($basis === 'DP' || $basis === 'MRP') && $listProblem === null) {
				$rate = pi_product_rate($connect, $v['id'], $basis, $pricing, pi_row_variant($row));
				$consent = isset($row['standard_price_ok']) && $row['standard_price_ok'] === ($pricing['code'] . ':' . $v['id']);
				if ($rate['missing_state_rate'] && $consent && $rate['fallback'] !== null) {
					$rate = array_merge($rate, $rate['fallback']);
					$line['rate_accepted'] = true;
					$warnings[] = 'Item ' . $n . ': the ' . $pricing['name'] . ' list has no rate; the ' . pi_rate_label($rate) . ' rate is used as you accepted.';
				}
				$manual = isset($row['manual_rate']['rate']) ? $row['manual_rate'] : null;
				if ($manual !== null && ((int) $manual['product_id'] !== (int) $v['id'] || $manual['basis'] !== $basis)) {
					$warnings[] = 'Item ' . $n . ': the rate typed for ' . $manual['basis'] . ' (' . pi_inr_text($manual['rate']) . ') no longer applies after the change; the list rate is used.';
					$manual = null;
				}
				if ($manual !== null) {
					// Rate typed by the employee: replaces the list rate for this line only. A NET list rate stays NET (no discount).
					$line['rate'] = (float) $manual['rate'];
					$line['rate_type'] = $rate['rate'] !== null && $rate['type'] === 'NET' ? 'NET' : $basis;
					$line['variant'] = $rate['variant'];
					$line['manual_rate'] = true;
					$line['list_rate'] = $rate['rate'];
					$line['rate_source'] = 'typed by ' . $manual['by'] . ($rate['rate'] !== null ? '; list rate ' . pi_inr_text($rate['rate']) . ' (' . pi_rate_label($rate) . ')' : '; no list rate');
					$warnings[] = 'Item ' . $n . ': rate ' . pi_inr_text($manual['rate']) . ' typed by ' . $manual['by']
						. ($rate['rate'] !== null ? ' instead of the list rate ' . pi_inr_text($rate['rate']) : ' (no list rate)') . '.';
				} elseif ($rate['needs_variant']) {
					$line['issues'][] = 'wood not chosen';
					$line['needs_variant'] = $rate['needs_variant'];
					$blockers[] = 'Item ' . $n . ' (' . $v['name'] . '): ' . implode(' or ', array_map(function ($x) { return strtolower($x) . ' wood'; }, $rate['needs_variant']))
						. '? The price list has a different rate for each.';
				} elseif ($rate['missing_state_rate'] && $rate['rate'] === null) {
					$line['issues'][] = 'no ' . $pricing['name'] . ' rate';
					$blockers[] = 'Item ' . $n . ' (' . $v['name'] . '): the ' . $pricing['name'] . ' price list has no ' . $basis . ' rate for this product.'
						. ($rate['fallback'] !== null ? ' Choose another product, or explicitly accept the ' . pi_rate_label($rate['fallback']) . ' rate.' : ' The master list and the software have no rate either.');
					$line['missing_rate'] = true;
					$line['standard_rate_available'] = $rate['fallback'] !== null;
					$line['fallback_label'] = $rate['fallback'] !== null ? pi_rate_label($rate['fallback']) : '';
				} elseif ($rate['rate'] === null) {
					$line['issues'][] = 'no rate';
					$blockers[] = 'Item ' . $n . ' (' . $v['name'] . '): no ' . $basis . ' rate in ' . ($hasMaster ? 'the master list or ' : '') . 'the software\'s prices.';
				} else {
					$line['rate'] = $rate['rate'];
					$line['rate_type'] = $rate['type'];
					$line['variant'] = $rate['variant'];
					$line['rate_source'] = pi_rate_label($rate) . (!empty($line['rate_accepted']) ? '; accepted by employee' : '');
				}
				if ($line['rate'] !== null && $line['rate'] <= 0) {
					$zeroOk = isset($row['zero_price_confirmed']) && (int) $row['zero_price_confirmed'] === (int) $v['id'];
					$line['zero_price'] = true;
					$line['zero_confirmed'] = $zeroOk;
					if (!$zeroOk) {
						$blockers[] = 'Item ' . $n . ' (' . $v['name'] . '): rate is ₹0. Confirm the ₹0 rate or choose another product.';
					}
				}
			}
		}
		if ($line['rate'] !== null && $row['quantity'] !== null) {
			$line['amount'] = pi_round($line['rate'] * (int) $row['quantity']);
			$subtotal += $line['amount'];
			if (isset($line['rate_type']) && $line['rate_type'] === 'NET') {
				$netSubtotal += $line['amount'];
			}
		}
		$lines[] = $line;
	}
	if (count($lines) === 0) {
		$blockers[] = 'No items yet. Paste the requirement or attach a photo.';
	}

	$c = $draft['customer'];
	if (trim($c['name']) === '') {
		$blockers[] = 'Customer name missing.';
	}
	// Billing address and state are optional (company decision): noted, not required to generate.
	if (trim($c['address']) === '') {
		$warnings[] = 'No customer billing address (optional).';
	}
	if ($c['state_code'] === '') {
		$warnings[] = 'No customer state (place of supply) given (optional).';
	}
	if ($c['gstin'] !== '') {
		if (!pi_valid_gstin($c['gstin'])) {
			$blockers[] = 'Customer GSTIN "' . $c['gstin'] . '" is not a valid GSTIN.';
		} elseif ($c['state_code'] !== '' && substr($c['gstin'], 0, 2) !== $c['state_code']) {
			$warnings[] = 'GSTIN state code ' . substr($c['gstin'], 0, 2) . ' differs from the customer state ' . $c['state_name'] . '.';
		}
	} else {
		$warnings[] = 'No customer GSTIN (unregistered customer?).';
	}
	if ($pricing['mode'] === 'state' && $c['state_code'] !== '') {
		$lists = pi_price_lists($connect);
		$served = pi_lists_for_state($lists, $c['state_code']);
		$l = isset($lists[$pricing['code']]) ? $lists[$pricing['code']] : null;
		if ($l !== null && !isset($served[$l['code']]) && ($l['covers'] || pi_find_state($l['name']) || pi_find_state($l['code']))) {
			$warnings[] = 'Prices are from the ' . $pricing['name'] . ' list but the customer state is ' . $c['state_name'] . '. Check this is intended.';
		}
	}
	if ($tax === null) {
		$blockers[] = 'Tax rate is not set up (doc_templates "tax").';
	} else {
		$warnings[] = 'Tax is shown as a single GST ' . rtrim(rtrim(number_format($tax, 2), '0'), '.') . '% line, as on current quotations; the CGST/SGST or IGST split is not set up.';
	}
	foreach (array('company', 'bank', 'terms', 'signatory') as $k) {
		if (!isset($templates[$k])) {
			$blockers[] = 'Approved ' . $k . ' details are not set up (doc_templates).';
		}
	}
	$terms = pi_terms_for($connect, $c['state_code'], $templates);
	if ($terms['source']['kind'] === 'template' && $c['state_code'] !== '') {
		$warnings[] = 'No approved PI terms document applies to ' . $c['state_name'] . ' today: the terms printed on current quotations are used.';
	}

	$discountPct = $draft['discount_percent'] === null ? 0.0 : (float) $draft['discount_percent'];
	$discount = pi_round(($subtotal - $netSubtotal) * $discountPct / 100);
	$discountNote = '';
	if ($netSubtotal > 0) {
		$discountNote = $discountPct > 0 ? rtrim(rtrim(number_format($discountPct, 2), '0'), '.') . '% discount on ' . ($basis === 'MRP' ? 'MRP' : 'DP') . ' items only. NET items carry no discount.'
			: 'NET items carry no discount.';
	}
	$chargesTotal = 0.0;
	foreach ($draft['charges'] as $ch) {
		$chargesTotal += (float) $ch['amount'];
	}
	if ($chargesTotal > 0) {
		$warnings[] = 'Charges are added before GST and taxed at the same rate (to be confirmed by accounts).';
	}
	$basic = pi_round($subtotal - $discount + $chargesTotal);
	$gst = $tax === null ? 0.0 : pi_round($basic * $tax / 100);
	// The final amount is rounded to the nearest rupee; the difference is printed as "Round off" (as on current PIs).
	$total = (float) round($basic + $gst);
	$roundOff = pi_round($total - ($basic + $gst));

	$result = array(
		'doc_type' => $draft['doc_type'],
		'basis' => $basis,
		'customer' => $c,
		'delivery_state' => $draft['delivery_state'],
		'price_list' => $listInfo,
		'lines' => $lines,
		'subtotal' => pi_round($subtotal),
		'discount_percent' => $discountPct,
		'discount' => $discount,
		'net_subtotal' => pi_round($netSubtotal),
		'discount_note' => $discountNote,
		'pricing' => array('mode' => $pricing['mode'], 'code' => $pricing['code'], 'name' => $pricing['name'], 'auto' => $pricing['auto']),
		'charges' => $draft['charges'],
		'charges_total' => pi_round($chargesTotal),
		'basic' => $basic,
		'tax_label' => isset($templates['tax']['content']['label']) ? $templates['tax']['content']['label'] : 'GST',
		'tax_rate' => $tax,
		'tax' => $gst,
		'round_off' => $roundOff,
		'total' => $total,
		'templates' => array_map(function ($t) { return $t['version']; }, $templates),
		'terms' => $terms,
		'blockers' => array_values(array_unique($blockers)),
		'warnings' => array_values(array_unique($warnings)),
	);
	$result['hash'] = hash('sha256', json_encode(array($result['doc_type'], $basis, $c, $draft['delivery_state'], $listInfo,
		array_map(function ($l) { return array($l['n'], $l['row_id'], $l['quantity'], $l['product'] ? $l['product']['id'] : null, $l['rate'],
			isset($l['rate_type']) ? $l['rate_type'] : null, isset($l['variant']) ? $l['variant'] : ''); }, $lines),
		$discountPct, $draft['charges'], $tax, $result['templates'], $terms['source'], $result['total'])));
	return $result;
}

/**
 * Requested specifications the chosen product's catalogue data does not show. Similarity or an AI
 * suggestion never counts as confirmation: size and finish are checked against the catalogue record,
 * other requested specifications are reported as unverified.
 */
function pi_spec_warnings($connect, $row, $productId)
{
	$cat = qt_ai_catalogue($connect);
	if (!isset($cat['products'][$productId])) {
		return array();
	}
	$p = $cat['products'][$productId];
	$out = array();
	$finish = qt_ai_tokens($row['finish']);
	if (count($finish) > 0 && !qt_ai_tokens_overlap($finish, $p['t_color'] + $p['t_desc'] + $p['t_name'])) {
		$out[] = 'Requested finish "' . $row['finish'] . '" is not shown for ' . $p['name'] . ' (catalogue colour: ' . ($p['color'] !== '' ? $p['color'] : 'not listed') . ').';
	}
	$numbers = qt_ai_numbers($row['dimensions']);
	$have = isset($p['n_all']) ? $p['n_all'] : $p['n_dim'];
	if (count($numbers) > 0 && count(array_diff_key($numbers, $have)) > 0) {
		$out[] = 'Requested size "' . $row['dimensions'] . '" does not match ' . $p['name'] . ' (' . ($p['dimension'] !== '' ? $p['dimension'] : 'size not listed') . ').';
	}
	if (trim((string) $row['other_specs']) !== '') {
		// Other requested specifications count as confirmed only when every word appears in the product's own
		// name, description or tags (or a spelling variant of it); otherwise they are listed as unverified.
		$known = $p['t_name'] + $p['t_desc'] + $p['t_tags'] + $p['t_cat'];
		foreach (preg_split('/\s*[;,]\s*/', trim($row['other_specs'])) as $spec) {
			$words = qt_ai_tokens($spec);
			if (count($words) && !qt_ai_all_words_known($words, $known)) {
				$out[] = 'Not confirmed by catalogue data: ' . $spec . '.';
			}
		}
	}
	return $out;
}

/** True when every word is in $known, allowing doubled letters and one-letter typos. */
function qt_ai_all_words_known($words, $known)
{
	foreach ($words as $w => $_) {
		if (!isset($known[$w]) && !qt_ai_tokens_overlap(array($w => true), $known)) {
			return false;
		}
	}
	return true;
}

/** Remember the rates of a preview the employee was shown (for showing differences at finalization). */
function pi_remember_preview($connect, $convId, $card)
{
	$rates = array();
	foreach ($card['pi']['lines'] as $l) {
		$rates[$l['row_id']] = array('n' => $l['n'], 'name' => $l['product'] ? $l['product']['name'] : $l['requirement'], 'rate' => $l['rate']);
	}
	$snap = array('version' => $card['version'], 'hash' => $card['hash'], 'rates' => $rates, 'total' => $card['pi']['total']);
	mysqli_query($connect, "UPDATE ai_pi_drafts SET preview_snapshot = '" . pi_esc($connect, json_encode($snap, JSON_UNESCAPED_UNICODE))
		. "' WHERE conversation_id = '" . $convId . "'");
}

/** Price differences between the preview the employee approved and the current prices, or null when unknown. */
function pi_preview_price_diff($connect, $convId, $hash, $pi)
{
	$r = mysqli_query($connect, "SELECT preview_snapshot FROM ai_pi_drafts WHERE conversation_id = '" . $convId . "'");
	$row = $r ? mysqli_fetch_row($r) : null;
	$snap = $row && $row[0] ? json_decode($row[0], true) : null;
	if (!is_array($snap) || $snap['hash'] !== $hash) {
		return null;
	}
	$lines = array();
	foreach ($pi['lines'] as $l) {
		$old = isset($snap['rates'][$l['row_id']]) ? $snap['rates'][$l['row_id']]['rate'] : null;
		if ($old !== $l['rate']) {
			$lines[] = array('n' => $l['n'], 'name' => $l['product'] ? $l['product']['name'] : $l['requirement'], 'old' => $old, 'new' => $l['rate']);
		}
	}
	return array('type' => 'price_diff', 'from' => 'the preview you approved', 'to' => 'current prices', 'basis' => $pi['basis'], 'lines' => $lines,
		'old_total' => $snap['total'], 'new_total' => $pi['total']);
}

/* ---------------------------------------------------------------- operations (the assistant's tools) */

/** "₹ 15,465.00" style text for notes. */
function pi_inr_text($n)
{
	$v = number_format((float) $n, 2, '.', '');
	list($w, $f) = explode('.', $v);
	if (strlen($w) > 3) {
		$w = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($w, 0, -3)) . ',' . substr($w, -3);
	}
	return '₹' . $w . '.' . $f;
}

/** A rate typed by the employee ("15,465", "₹ 15465.50"): float above 0 and up to 1 crore, else null. */
function pi_parse_rate($s)
{
	$t = str_replace(array(',', '₹', ' ', 'Rs.', 'Rs', 'rs'), '', trim((string) $s));
	if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $t)) {
		return null;
	}
	$v = (float) $t;
	return $v > 0 && $v <= 10000000 ? round($v, 2) : null;
}

/** Set (or with $rate null clear) the rate typed by the employee for one draft row. Returns an error or null. */
function pi_set_manual_rate(&$row, $rate, $basis, $user)
{
	if ($rate === null) {
		unset($row['manual_rate']);
		return null;
	}
	if ($row['product_id'] === null) {
		return 'Choose a product for that item first.';
	}
	if ($basis !== 'DP' && $basis !== 'MRP') {
		return 'Choose the price basis (DP or MRP) first.';
	}
	$row['manual_rate'] = array('rate' => $rate, 'basis' => $basis, 'product_id' => (int) $row['product_id'], 'by' => (string) $user, 'at' => date('Y-m-d H:i:s'));
	return null;
}

/** Numbers written in a message (Western or Devanagari digits), for checking discount and charge amounts. */
function pi_numbers_in($text)
{
	preg_match_all('/\d+(?:[.,]\d+)*/', qt_ai_ascii_digits((string) $text), $m);
	$out = array();
	foreach ($m[0] as $n) {
		$out[] = (float) str_replace(',', '', $n);
	}
	return $out;
}

/** Catalogue search for the assistant: quotable products with current rates for the draft's basis/list. */
function pi_search_products($connect, $draft, $query, $limit = 6)
{
	$basis = $draft['type'] === 'MRP' ? 'MRP' : 'DP';
	$pricing = pi_effective_pricing($connect, $draft);
	$products = qt_ai_search($connect, $basis, $query, $pricing['mode'] === 'state' ? $pricing['code'] : '');
	return array_slice($products, 0, $limit);
}

/** Exact product by code/name (unique), else null. */
function pi_find_exact_product($connect, $query)
{
	$cat = qt_ai_catalogue($connect);
	$compact = qt_ai_compact($query);
	if ($compact !== '' && isset($cat['by_compact'][$compact]) && count($cat['by_compact'][$compact]) === 1) {
		return $cat['by_compact'][$compact][0];
	}
	return null;
}

function pi_set_product(&$row, $productId, $source)
{
	if ($row['product_id'] !== $productId) {
		$row['product_id'] = $productId;
		$row['product_source'] = $source;
		unset($row['manual_rate']);   // a rate typed for the old product does not carry over
		$row['rev'] = isset($row['rev']) ? $row['rev'] + 1 : 1;
	}
}

/**
 * Run the validated operations for one turn. $ctx: connect, ai, user_text, attachments (paths), log.
 * Every argument is checked here; the model cannot reach SQL or files.
 * Returns array(changed (bool), cards (array), notes (list of strings for the reply), errors).
 */
function pi_apply_operations($ctx, &$draft, $ops)
{
	$connect = $ctx['connect'];
	$cards = array();
	$notes = array();
	$errors = array();
	$changed = false;
	$userNumbers = pi_numbers_in($ctx['user_text']);
	$priceSnapshot = pi_line_rates($connect, $draft);

	foreach ($ops as $op) {
		$tool = isset($op['tool']) ? (string) $op['tool'] : '';
		$row = isset($op['row']) && is_int($op['row']) ? $op['row'] : null;
		$text = isset($op['text']) && is_string($op['text']) ? trim($op['text']) : '';
		$number = isset($op['number']) && (is_int($op['number']) || is_float($op['number'])) ? $op['number'] : null;
		$field = isset($op['field']) && is_string($op['field']) ? $op['field'] : '';
		switch ($tool) {
			case 'extract_requirements':
				// With photos attached, items come from the photos (the typed text is usually an instruction such as
				// "make a PI for Maharashtra") unless the model quotes requirement lines in text.
				$source = $text !== '' ? $text : (count($ctx['attachments']) ? '' : $ctx['user_text']);
				$res = pi_extract_rows($ctx, $draft, $source, $ctx['attachments']);
				list($added, $err, $skipped) = $res;
				$readNote = isset($res[3]) ? $res[3] : '';
				if ($readNote !== '') {
					$notes[] = $readNote;
				}
				foreach ($skipped as $sk) {
					$notes[] = 'Not added: "' . qt_ai_cut($sk, 80) . '" (crossed out or not an item). Ask me to add it if it is needed.';
				}
				if ($err !== null) {
					$errors[] = $err;
				} elseif ($added > 0) {
					$changed = true;
					$notes[] = 'Read ' . $added . ' item' . ($added === 1 ? '' : 's') . ' from the requirement.';
				} elseif ($readNote === '') {
					$notes[] = 'No items were found in that message.';
				}
				break;

			case 'set_customer':
				$allowed = array('name' => 150, 'address' => 400, 'gstin' => 15, 'phone' => 30);
				if (!isset($allowed[$field]) || $text === '') {
					$errors[] = 'Customer detail not understood.';
					break;
				}
				$value = qt_ai_cut(preg_replace('/\s+/u', ' ', $text), $allowed[$field]);
				if ($field === 'gstin') {
					$value = strtoupper(str_replace(' ', '', $value));
					if (!pi_valid_gstin($value)) {
						$errors[] = '"' . $value . '" is not a valid GSTIN (15 characters, e.g. 27ABCDE1234F1Z5).';
						break;
					}
					if ($draft['customer']['state_code'] === '') {
						$states = pi_states();
						$code = substr($value, 0, 2);
						if (isset($states[$code])) {
							$draft['customer']['state_code'] = $code;
							$draft['customer']['state_name'] = $states[$code];
							$notes[] = 'Customer state set to ' . $states[$code] . ' from the GSTIN.';
						}
					}
				}
				if ($field === 'phone' && !preg_match('/^[0-9+() -]{6,30}$/', $value)) {
					$errors[] = 'The phone number "' . $value . '" does not look valid.';
					break;
				}
				if ($draft['customer'][$field] !== $value) {
					$draft['customer'][$field] = $value;
					$changed = true;
					$notes[] = 'Customer ' . $field . ' updated.';
				}
				break;

			case 'set_customer_state':
			case 'set_delivery_state':
				$st = pi_find_state($text);
				if ($st === null) {
					$errors[] = 'I could not identify the state "' . $text . '".';
					break;
				}
				if ($tool === 'set_customer_state') {
					$draft['customer']['state_code'] = $st[0];
					$draft['customer']['state_name'] = $st[1];
					$notes[] = 'Customer state (place of supply): ' . $st[1] . '.';
				} else {
					$draft['delivery_state'] = array('code' => $st[0], 'name' => $st[1]);
					$notes[] = 'Delivery state: ' . $st[1] . '.';
				}
				$changed = true;
				break;

			case 'set_pricing_state':
				list($pricing, $err) = pi_resolve_price_list($connect, $text);
				if ($err !== null) {
					$errors[] = $err;
					break;
				}
				$old = pi_effective_pricing($connect, $draft);
				if ($draft['pricing']['mode'] !== $pricing['mode'] || $draft['pricing']['code'] !== $pricing['code']) {
					$draft['pricing'] = $pricing;
					$draft['price_state'] = $pricing['mode'] === 'state' ? $pricing['code'] : '';
					$changed = true;
					$notes[] = 'Price list: ' . $pricing['name'] . '.';
					if ($old['mode'] !== $pricing['mode'] || $old['code'] !== $pricing['code']) {
						$cards[] = pi_price_diff_card($connect, $draft, $priceSnapshot, $old);
					}
				}
				break;

			case 'set_price_basis':
				$b = strtoupper($text);
				if ($b !== 'DP' && $b !== 'MRP') {
					$errors[] = 'Price basis must be DP (dealer) or MRP (retail).';
					break;
				}
				if ($draft['type'] !== $b) {
					$hadBasis = $draft['type'] !== null;
					$draft['type'] = $b;
					$changed = true;
					$notes[] = 'Price basis: ' . $b . '.';
					$cleared = false;
					foreach ($draft['rows'] as $k => $_) {
						if (isset($draft['rows'][$k]['manual_rate'])) {
							unset($draft['rows'][$k]['manual_rate']);   // a typed DP rate is not an MRP rate (and the reverse)
							$cleared = true;
						}
					}
					if ($cleared) {
						$notes[] = 'Typed rates were cleared because the price basis changed; list rates are used.';
					}
					if ($hadBasis) {
						$cards[] = pi_price_diff_card($connect, $draft, $priceSnapshot, pi_effective_pricing($connect, $draft));
					}
				}
				break;

			case 'set_document_type':
				$t = strtoupper($text);
				if ($t !== 'PI' && $t !== 'QUOTATION') {
					$errors[] = 'Document type must be PI or quotation.';
					break;
				}
				$t = $t === 'PI' ? 'PI' : 'quotation';
				if ($draft['doc_type'] !== $t) {
					$draft['doc_type'] = $t;
					$changed = true;
					$notes[] = 'Document type: ' . ($t === 'PI' ? 'Proforma Invoice' : 'quotation') . '.';
				}
				break;

			case 'set_quantity':
				$i = pi_op_row_index($draft, $op);
				if ($i === null) {
					$errors[] = 'I could not tell which item you mean' . ($row !== null ? ' (item ' . $row . ')' : '') . '. Please name it by its number on the draft.';
					break;
				}
				$numbersNow = pi_row_numbers($draft);
				$row = $numbersNow[$draft['rows'][$i]['row_id']];
				$qty = $number !== null && floor($number) == $number ? qt_parse_qty((string) (int) $number) : null;
				if ($qty === null) {
					$errors[] = 'Quantity must be a whole number from 1 to ' . QT_MAX_QTY . '.';
					break;
				}
				$err = qt_ai_edit_row($connect, $draft, $draft['rows'][$i]['row_id'], $draft['rows'][$i]['rev'], array('quantity' => (string) $qty));
				if ($err !== null) {
					$errors[] = $err;
					break;
				}
				$changed = true;
				$notes[] = 'Item ' . $row . ' quantity: ' . $qty . '.';
				$notes = array_merge($notes, pi_add_dining_chairs($connect, $draft, array($draft['rows'][$i]['row_id'])));
				break;

			case 'choose_product':
				$i = pi_op_row_index($draft, $op);
				if ($i === null) {
					$errors[] = 'I could not tell which item you mean' . ($row !== null ? ' (item ' . $row . ')' : '') . '. Please name it by its number on the draft.';
					break;
				}
				$numbersNow = pi_row_numbers($draft);
				$row = $numbersNow[$draft['rows'][$i]['row_id']];
				if ($text === '') {
					$errors[] = 'Which product should item ' . $row . ' use?';
					break;
				}
				$pid = pi_find_exact_product($connect, $text);
				$quotable = $pid !== null ? qt_ai_product_views($connect, array($pid), $draft['type'] === 'MRP' ? 'MRP' : 'DP') : array();
				if ($pid !== null && isset($quotable[$pid])) {
					pi_set_product($draft['rows'][$i], $pid, 'user');
					$changed = true;
					$notes[] = 'Item ' . $row . ': ' . $quotable[$pid]['name'] . '.';
					$notes = array_merge($notes, pi_add_dining_chairs($connect, $draft, array($draft['rows'][$i]['row_id'])));
				} else {
					$found = pi_search_products($connect, $draft, $text);
					if (count($found) === 0) {
						$errors[] = 'No catalogue product matches "' . $text . '".';
					} else {
						$cards[] = array('type' => 'choices', 'row_id' => $draft['rows'][$i]['row_id'], 'n' => $row,
							'requirement' => $draft['rows'][$i]['requirement'], 'title' => 'Products matching "' . $text . '"', 'products' => pi_card_products($connect, $draft, $found));
						$notes[] = 'Several products match "' . $text . '": choose one for item ' . $row . '.';
					}
				}
				break;

			case 'remove_row':
				$i = pi_op_row_index($draft, $op);
				if ($i === null) {
					$errors[] = 'I could not tell which item you mean' . ($row !== null ? ' (item ' . $row . ')' : '') . '. Please name it by its number on the draft.';
					break;
				}
				$numbersNow = pi_row_numbers($draft);
				$row = $numbersNow[$draft['rows'][$i]['row_id']];
				$draft['rows'][$i]['removed'] = true;
				$changed = true;
				$notes[] = 'Item ' . $row . ' removed.';
				break;

			case 'set_variant':
				$i = pi_op_row_index($draft, $op);
				if ($i === null) {
					$errors[] = 'I could not tell which item you mean' . ($row !== null ? ' (item ' . $row . ')' : '') . '. Please name it by its number on the draft.';
					break;
				}
				$numbersNow = pi_row_numbers($draft);
				$row = $numbersNow[$draft['rows'][$i]['row_id']];
				$w = qt_ai_lower($text);
				$variant = preg_match('/teak|sagwan|sagaun|सागवान|सागौन/u', $w) ? 'TEAK' : (preg_match('/mango|आम|आंबा/u', $w) ? 'MANGO' : '');
				if ($variant === '') {
					$errors[] = 'Item ' . $row . ': say teak wood or mango wood.';
					break;
				}
				$draft['rows'][$i]['variant'] = $variant;
				$changed = true;
				$notes[] = 'Item ' . $row . ': ' . strtolower($variant) . ' wood.';
				break;

			case 'search_catalogue':
				if ($text === '') {
					break;
				}
				$found = pi_search_products($connect, $draft, $text);
				$cards[] = array('type' => 'choices', 'row_id' => null, 'n' => null, 'requirement' => '', 'title' => 'Catalogue: "' . $text . '"',
					'products' => pi_card_products($connect, $draft, $found));
				break;

			case 'set_rate':
				$i = pi_op_row_index($draft, $op);
				if ($i === null) {
					$errors[] = 'I could not tell which item you mean' . ($row !== null ? ' (item ' . $row . ')' : '') . '. Please name it by its number on the draft.';
					break;
				}
				$numbersNow = pi_row_numbers($draft);
				$row = $numbersNow[$draft['rows'][$i]['row_id']];
				if ($text !== '' && preg_match('/^(list|clear|reset|original)$/i', $text)) {
					pi_set_manual_rate($draft['rows'][$i], null, $draft['type'], '');
					$changed = true;
					$notes[] = 'Item ' . $row . ': back to the list rate.';
					break;
				}
				$rateValue = $number === null ? null : pi_parse_rate((string) $number);
				if ($rateValue === null || !in_array((float) $number, $userNumbers, true)) {
					$errors[] = 'Please type the rate in rupees as a number (for example "item ' . $row . ' rate 15465").';
					break;
				}
				$err = pi_set_manual_rate($draft['rows'][$i], $rateValue, $draft['type'], isset($_SESSION['username']) ? $_SESSION['username'] : '');
				if ($err !== null) {
					$errors[] = $err;
					break;
				}
				$changed = true;
				$notes[] = 'Item ' . $row . ' rate: ' . pi_inr_text($rateValue) . ' (typed).';
				break;

			case 'set_discount':
				if ($number === null || $number < 0 || $number > QT_MAX_DISCOUNT) {
					$errors[] = 'Discount must be between 0 and ' . QT_MAX_DISCOUNT . '%.';
					break;
				}
				if (!in_array((float) $number, $userNumbers, true)) {
					$errors[] = 'Please type the discount percentage as a number (for example "discount 5%").';
					break;
				}
				$draft['discount_percent'] = (float) $number;
				$changed = true;
				$notes[] = 'Discount: ' . $number . '%.';
				break;

			case 'set_charge':
				$kinds = array('transport' => 'Transportation charges', 'installation' => 'Installation charges', 'packing' => 'Packing charges', 'other' => '');
				if (!isset($kinds[$field])) {
					$errors[] = 'Charge type not understood (transport, installation, packing or other).';
					break;
				}
				if ($number === null || $number <= 0 || $number > PI_MAX_CHARGE || !in_array((float) $number, $userNumbers, true)) {
					$errors[] = 'Please type the charge amount in rupees as a number (for example "transport 5000").';
					break;
				}
				$label = $field === 'other' ? qt_ai_cut($text, 60) : $kinds[$field];
				if ($label === '') {
					$errors[] = 'Please name the charge.';
					break;
				}
				$draft['charges'] = array_values(array_filter($draft['charges'], function ($c) use ($field, $label) {
					return !($c['kind'] === $field && ($field !== 'other' || $c['label'] === $label));
				}));
				$draft['charges'][] = array('kind' => $field, 'label' => $label, 'amount' => pi_round($number));
				$changed = true;
				$notes[] = $label . ': ₹' . pi_round($number) . '.';
				break;

			case 'remove_charge':
				$before = count($draft['charges']);
				$draft['charges'] = array_values(array_filter($draft['charges'], function ($c) use ($field) { return $c['kind'] !== $field; }));
				if (count($draft['charges']) !== $before) {
					$changed = true;
					$notes[] = 'Charge removed.';
				}
				break;

			case 'retrieve_documents':
				if ($text === '' || !function_exists('rt_search_documents')) {
					break;
				}
				$found = rt_search_documents($ctx['ai'], $connect, $text, $draft['customer']['state_code']);
				$cards[] = array('type' => 'documents', 'query' => $text, 'state' => $draft['customer']['state_name'], 'excerpts' => $found);
				$notes[] = count($found) ? 'Excerpts from approved documents are shown below.' : 'No approved document covers "' . qt_ai_cut($text, 60) . '".';
				break;

			case 'show_preview':
				$cards[] = array('type' => 'preview_request');
				break;

			case 'request_generation':
				$cards[] = array('type' => 'preview_request', 'generation' => true);
				break;

			default:
				if ($tool !== '') {
					$errors[] = 'Unknown operation "' . qt_ai_cut($tool, 40) . '" ignored.';
				}
		}
	}
	return array($changed, $cards, $notes, $errors);
}

/** Current rate per row (for showing differences when the price list or basis changes). */
function pi_line_rates($connect, $draft)
{
	$out = array();
	if ($draft['type'] === null) {
		return $out;
	}
	$pricing = pi_effective_pricing($connect, $draft);
	foreach ($draft['rows'] as $row) {
		if (empty($row['removed']) && $row['product_id'] !== null) {
			$rate = pi_product_rate($connect, $row['product_id'], $draft['type'], $pricing, pi_row_variant($row));
			$out[$row['row_id']] = $rate['rate'];
		}
	}
	return $out;
}

function pi_price_diff_card($connect, $draft, $before, $oldPricing)
{
	$after = pi_line_rates($connect, $draft);
	$numbers = pi_row_numbers($draft);
	$views = array();
	$ids = array();
	foreach ($draft['rows'] as $row) {
		if (empty($row['removed']) && $row['product_id'] !== null) {
			$ids[] = (int) $row['product_id'];
		}
	}
	if ($ids) {
		$views = qt_ai_product_views($connect, $ids, $draft['type'] === 'MRP' ? 'MRP' : 'DP');
	}
	$lines = array();
	foreach ($draft['rows'] as $row) {
		if (!empty($row['removed']) || $row['product_id'] === null) {
			continue;
		}
		$lines[] = array('n' => $numbers[$row['row_id']], 'name' => isset($views[$row['product_id']]) ? $views[$row['product_id']]['name'] : '#' . $row['product_id'],
			'old' => isset($before[$row['row_id']]) ? $before[$row['row_id']] : null, 'new' => isset($after[$row['row_id']]) ? $after[$row['row_id']] : null);
	}
	return array('type' => 'price_diff', 'from' => $oldPricing['mode'] === null ? '' : $oldPricing['name'], 'to' => pi_effective_pricing($connect, $draft)['name'],
		'basis' => $draft['type'], 'lines' => $lines);
}

/** Product cards with the current rate for the draft (or a note when no rate is available). */
function pi_card_products($connect, $draft, $products)
{
	$out = array();
	$pricing = pi_effective_pricing($connect, $draft);
	foreach ($products as $p) {
		$rate = $draft['type'] !== null ? pi_product_rate($connect, $p['id'], $draft['type'], $pricing) : null;
		$note = '';
		if ($rate === null) {
			$note = 'Rate shown once the price basis (DP or MRP) is chosen';
		} elseif ($rate['missing_state_rate']) {
			$note = 'No ' . $pricing['name'] . ' rate';
		} elseif ($rate['needs_variant']) {
			$note = 'Rate depends on the wood (' . strtolower(implode(' / ', $rate['needs_variant'])) . ')';
		} elseif ($rate['rate'] !== null) {
			$note = pi_rate_label($rate);
		}
		$out[] = array('id' => $p['id'], 'name' => $p['name'], 'color' => $p['color'], 'size' => $p['dimension'], 'description' => $p['description'],
			'thumb' => $p['thumb'], 'rate' => $rate ? $rate['rate'] : null, 'rate_note' => $note);
	}
	return $out;
}

/** Cards for rows that still need a product: the AI's alternatives as selectable options. */
function pi_unresolved_choice_cards($connect, $draft, $onlyRowIds = null)
{
	$cards = array();
	$numbers = pi_row_numbers($draft);
	foreach ($draft['rows'] as $row) {
		if (!empty($row['removed']) || $row['product_id'] !== null || ($onlyRowIds !== null && !in_array($row['row_id'], $onlyRowIds, true))) {
			continue;
		}
		$m = isset($row['match']) && is_array($row['match']) ? $row['match'] : null;
		$ids = array();
		if ($m) {
			if ($m['suggested_id'] !== null) {
				$ids[] = $m['suggested_id'];
			}
			$ids = array_merge($ids, $m['alternatives']);
		}
		$views = $ids ? qt_ai_product_views($connect, array_unique($ids), $draft['type'] === 'MRP' ? 'MRP' : 'DP') : array();
		$cards[] = array('type' => 'choices', 'row_id' => $row['row_id'], 'n' => $numbers[$row['row_id']], 'requirement' => $row['requirement'],
			'title' => 'Item ' . $numbers[$row['row_id']] . ': choose the product for "' . qt_ai_cut($row['requirement'], 80) . '"',
			'reason' => $m ? $m['reason'] : '', 'products' => pi_card_products($connect, $draft, array_values($views)));
	}
	return $cards;
}

/* ---------------------------------------------------------------- extraction (reuses the import services) */

/**
 * Read requirement rows from text and/or images with the existing extraction prompt, then match
 * them to the catalogue with the existing matcher. New rows are appended with unique ids.
 * Returns array(added_count, error|null).
 */
function pi_extract_rows($ctx, &$draft, $text, $attachments)
{
	$ai = $ctx['ai'];
	$images = array();
	$hashes = array();
	$repeated = array();
	if (!isset($draft['read_images'])) {
		// Drafts from before this record: rebuild it from the photos attached to earlier messages.
		$draft['read_images'] = array();
		foreach (isset($draft['attachments']) ? $draft['attachments'] : array() as $att) {
			$f = isset($ctx['conv_id']) ? $ai['storage_dir'] . '/chat/' . $ctx['conv_id'] . '/' . basename($att['file']) : '';
			if ($f !== '' && is_file($f) && (!isset($ctx['message_id']) || (int) $att['message_id'] !== (int) $ctx['message_id'])) {
				$h = sha1_file($f);
				if (!isset($draft['read_images'][$h])) {
					$draft['read_images'][$h] = (int) $att['message_id'];
				}
			}
		}
	}
	// A photo already read in this conversation (sent again with a follow-up such as "add 20% discount") is not
	// read again: its items are on the draft already. Once all its items were removed, it can be read again.
	foreach ($attachments as $a) {
		$h = is_file($a['path']) ? sha1_file($a['path']) : '';
		if ($h !== '' && isset($draft['read_images'][$h]) && pi_rows_from_message($draft, $draft['read_images'][$h]) > 0) {
			$repeated[] = (int) $draft['read_images'][$h];
			continue;
		}
		$images[] = array('path' => $a['path'], 'mime' => $a['mime']);
		$hashes[] = $h;
	}
	$note = '';
	$codeNotes = array();
	if ($repeated) {
		$note = (count($repeated) === 1 ? 'This photo was' : count($repeated) . ' of the photos were') . ' already read earlier in this conversation; '
			. 'its items are on the draft, so they were not added again. To read it again, remove those items first.';
	}
	if (trim($text) === '' && count($images) === 0) {
		return array(0, null, array(), $note);
	}
	$instructions = qt_ai_instruction_text($draft);
	list($content, $error) = qt_ai_build_extraction_content($images, $text, $instructions);
	if ($error !== null) {
		return array(0, $error, array());
	}
	// The catalogue's collection and series names help read handwritten names (Bostone -> Boston).
	$known = qt_ai_known_names(qt_ai_catalogue($ctx['connect']), qt_ai_series_map($ctx['connect']));
	if ($known) {
		array_splice($content, count($content) - 1, 0, array(array('type' => 'text', 'text' => "<known_names>\n" . implode(', ', $known) . "\n</known_names>")));
	}
	list($data, $error) = qt_ai_call_json($ai, 'extract', qt_ai_extraction_system_prompt(), $content, qt_ai_extraction_schema(), $ctx['log']);
	if ($error !== null) {
		return array(0, 'Reading the requirement failed: ' . $error['message'], array());
	}
	$pseudo = array('input' => array('text' => $text, 'images' => $images), 'type' => $draft['type'] === 'MRP' ? 'MRP' : 'DP');
	list($rows, $notes, $cleanError) = qt_ai_clean_extraction($ai, $pseudo, $data);
	if ($cleanError !== null) {
		return array(0, $cleanError, array());
	}
	// Crossed-out lines and lines that are not items (greetings, instructions) are not put on the draft.
	$skipped = array();
	$rows = array_values(array_filter($rows, function ($r) use (&$skipped) {
		if (count(array_intersect($r['flags'], array('crossed_out', 'not_an_item'))) > 0) {
			$skipped[] = $r['original_text'] !== '' ? $r['original_text'] : $r['requirement'];
			return false;
		}
		return true;
	}));
	// Codes misread from handwriting (CS1-130 for CSI-130) are corrected when exactly one catalogue code fits.
	$catalogue = qt_ai_catalogue($ctx['connect']);
	foreach ($rows as &$fixRow) {
		$fixed = qt_ai_fix_code($catalogue, $fixRow['product_code']);
		if ($fixed !== null) {
			$asRead = $fixRow['product_code'];
			foreach (array('product_code', 'item_description', 'requirement') as $f) {
				$fixRow[$f] = str_ireplace($asRead, $fixed, $fixRow[$f]);
			}
			$fixRow['product_code'] = $fixed;
			if (isset($fixRow['basis']) && is_array($fixRow['basis'])) {
				$fixRow['basis']['code'] = $fixed;
				$fixRow['basis']['description'] = str_ireplace($asRead, $fixed, $fixRow['basis']['description']);
			}
			$fixRow['notes'] = trim((isset($fixRow['notes']) ? $fixRow['notes'] . ' ' : '') . 'Read as "' . $asRead . '"; the catalogue code is ' . $fixed . '.');
			$codeNotes[] = 'Code "' . $asRead . '" read as ' . $fixed . ' (the catalogue code).';
		}
	}
	unset($fixRow);
	if (count($draft['rows']) + count($rows) > PI_MAX_ROWS) {
		return array(0, 'A draft can hold at most ' . PI_MAX_ROWS . ' items.', $skipped);
	}
	// Unique ids across the whole conversation; source references point at the message's images.
	foreach ($rows as &$r) {
		$r['row_id'] = 'r' . $draft['next_row']++;
		if (isset($ctx['message_id']) && preg_match('/^image (\d+)$/', $r['source_ref'], $m)) {
			$r['source_ref'] = 'message ' . $ctx['message_id'] . ', image ' . $m[1];
		} elseif (isset($ctx['message_id']) && $r['source_ref'] === 'text') {
			$r['source_ref'] = 'message ' . $ctx['message_id'] . ', text';
		}
	}
	unset($r);
	$matchOk = true;
	if (count($rows) > 0) {
		$matchOk = qt_ai_match_rows($ai, $ctx['connect'], $rows, null, $ctx['log'], $instructions);
		$rows = pi_split_seat_combos($ai, qt_ai_catalogue($ctx['connect']), $rows, $draft);
	}
	foreach ($rows as $r) {
		$draft['rows'][] = $r;
	}
	$chairNotes = pi_add_dining_chairs($ctx['connect'], $draft, array_map(function ($r) { return $r['row_id']; }, $rows));
	if ($chairNotes) {
		$codeNotes = array_merge($codeNotes, $chairNotes);
	}
	if (isset($ctx['message_id'])) {
		foreach ($hashes as $h) {
			if ($h !== '') {
				$draft['read_images'][$h] = (int) $ctx['message_id'];
			}
		}
	}
	if ($notes !== '') {
		$draft['document_notes'] = trim($draft['document_notes'] . ' ' . $notes);
	}
	if ($codeNotes) {
		$note = trim($note . ' ' . implode(' ', $codeNotes));
	}
	return array(count($rows), null, $skipped, $note);
}

/**
 * A sofa combination ("Clayton 3+2 sofa set") with no set product in the catalogue, where the series is sold as
 * separate seaters (CLAYTON 3 Seater, 2 Seater, 1 Seater), becomes one row per seater: 3+2 -> 3 seater x1 and
 * 2 seater x1; 3+1+1 -> 3 seater x1 and 1 seater x2 (times the number of sets). A combination the AI already matched
 * to a set product (Boston Smart 3+1+1 Sofa) is left as it is.
 */
function pi_split_seat_combos($ai, $cat, $rows, &$draft)
{
	$out = array();
	foreach ($rows as $row) {
		$text = $row['requirement'] . ' ' . $row['original_text'];
		$status = isset($row['match']['status']) ? $row['match']['status'] : '';
		if ($status === 'suggested' || !preg_match('/\b([1-6](?:\s*\+\s*[1-6])+)\b/', $text, $m) || !preg_match('/sofa|seater|\d\s*\+\s*\d/i', $text)) {
			$out[] = $row;
			continue;
		}
		$seats = array_count_values(array_map('intval', preg_split('/\s*\+\s*/', $m[1])));
		// Seater products among this row's candidates, grouped by product name (the series).
		$bySeries = array();
		foreach (isset($row['match']['candidate_ids']) ? $row['match']['candidate_ids'] : array() as $pid) {
			if (!isset($cat['products'][$pid])) {
				continue;
			}
			$p = $cat['products'][$pid];
			if (preg_match('/\b([1-6])\s*seater\b/i', $p['name'] . ' ' . $p['description'], $sm) && !preg_match('/\d\s*\+\s*\d/', $p['name'] . ' ' . $p['description'])) {
				$bySeries[$p['compact']][(int) $sm[1]][] = $pid;
			}
		}
		$split = null;
		foreach ($bySeries as $series => $byN) {
			$ok = true;
			foreach ($seats as $n => $_) {
				if (!isset($byN[$n]) || count($byN[$n]) !== 1) {
					$ok = false;
				}
			}
			$seriesWord = $cat['products'][reset($byN)[0]]['name'];
			if ($ok && stripos(qt_ai_compact($text), qt_ai_compact($seriesWord)) !== false) {
				$split = $byN;
				break;
			}
		}
		if ($split === null) {
			$out[] = $row;
			continue;
		}
		$sets = $row['quantity'] !== null ? (int) $row['quantity'] : null;
		foreach ($seats as $n => $count) {
			$pid = $split[$n][0];
			$new = $row;
			$new['row_id'] = 'r' . $draft['next_row']++;
			$new['requirement'] = $cat['products'][$pid]['name'] . ' ' . $n . ' seater (from "' . $m[1] . '")';
			$new['quantity'] = $sets !== null ? $sets * $count : null;
			$new['product_id'] = null;
			$new['match'] = qt_ai_match_result('suggested', $pid, array(), array($pid), 'The catalogue sells ' . $cat['products'][$pid]['name']
				. ' as separate seaters (no ' . $m[1] . ' set product), so the combination is quoted seater by seater.', array());
			qt_ai_apply_match_policy($ai, $cat, $new);
			if ($new['product_id'] === null) {
				// Same-name products (three "CLAYTON") are told apart here by their seater count, which is exact.
				$new['match']['status'] = 'suggested';
				$new['match']['warnings'] = array();
				pi_set_product($new, $pid, 'ai');
			}
			$out[] = $new;
		}
	}
	return $out;
}

/** Dining table product id => its chair product id, from product_companions (price-list dining sheets). */
function pi_dining_chair_map($connect, $cat)
{
	static $map = null;
	if ($map !== null) {
		return $map;
	}
	$map = array();
	if (!(($t = mysqli_query($connect, "SHOW TABLES LIKE 'product_companions'")) && mysqli_num_rows($t) > 0)) {
		return $map;
	}
	$r = mysqli_query($connect, "SELECT product_code, companion_code FROM product_companions WHERE kind = 'chair'");
	$pairs = array();
	while ($r && ($x = mysqli_fetch_assoc($r))) {
		$pairs[qt_ai_compact($x['product_code'])] = qt_ai_compact($x['companion_code']);
	}
	// A code matches a product whose name is that code, optionally followed by a collection word (SWDN-07(B) SABI).
	$find = function ($code) use ($cat) {
		$ids = array();
		foreach ($cat['by_compact'] as $k => $list) {
			if ($k === $code || (strncmp($k, $code, strlen($code)) === 0 && ctype_alpha(substr($k, strlen($code))))) {
				$ids = array_merge($ids, $list);
			}
		}
		return $ids;
	};
	foreach ($pairs as $table => $chair) {
		$chairs = $find($chair);
		if (count($chairs) !== 1) {
			continue;   // chair code missing or ambiguous in the catalogue: chairs are not added automatically
		}
		foreach ($find($table) as $tid) {
			$map[$tid] = $chairs[0];
		}
	}
	return $map;
}

/**
 * Add the chairs of an "N seater" dining table as the next line (N chairs per table), or update them when the
 * table, its quantity or the seat count changed. Chairs come from the price list's table -> chair pairs; nothing
 * is added for "without chairs" / "table only", or when the seat count is not written. Returns notes.
 */
function pi_add_dining_chairs($connect, &$draft, $rowIds = null)
{
	$cat = qt_ai_catalogue($connect);
	$map = pi_dining_chair_map($connect, $cat);
	$notes = array();
	if (!$map) {
		return $notes;
	}
	foreach ($draft['rows'] as $i => $row) {
		if (!empty($row['removed']) || !empty($row['companion_of']) || $row['product_id'] === null || ($rowIds !== null && !in_array($row['row_id'], $rowIds, true))) {
			continue;
		}
		$text = $row['requirement'] . ' ' . $row['original_text'] . ' ' . (isset($row['other_specs']) ? $row['other_specs'] : '');
		$chairIndex = null;
		foreach ($draft['rows'] as $j => $other) {
			if (empty($other['removed']) && isset($other['companion_of']) && $other['companion_of'] === $row['row_id']) {
				$chairIndex = $j;
			}
		}
		$pid = (int) $row['product_id'];
		$seats = preg_match('/\b(\d{1,2})\s*-?\s*(?:str|seater|seats?)\b/i', $text, $m) ? (int) $m[1] : 0;
		$wanted = isset($map[$pid]) && $seats > 0 && !preg_match('/without\s+chairs?|table\s+only|no\s+chairs?/i', $text);
		if (!$wanted) {
			if ($chairIndex !== null && empty($draft['rows'][$chairIndex]['chairs_edited'])) {
				$draft['rows'][$chairIndex]['removed'] = true;   // table changed to one without chairs
			}
			continue;
		}
		$qty = $row['quantity'] !== null ? $seats * (int) $row['quantity'] : null;
		$chairId = $map[$pid];
		if ($chairIndex !== null) {
			$c = &$draft['rows'][$chairIndex];
			if ((int) $c['product_id'] !== $chairId || $c['quantity'] !== $qty) {
				pi_set_product($c, $chairId, 'set');
				$c['quantity'] = $qty;
				$c['rev'] = isset($c['rev']) ? $c['rev'] + 1 : 1;
			}
			unset($c);
			continue;
		}
		$chair = $row;
		foreach (array('manual_rate', 'variant', 'standard_price_ok', 'zero_price_confirmed', 'retrieval', 'basis') as $k) {
			unset($chair[$k]);
		}
		$chair['row_id'] = 'r' . $draft['next_row']++;
		$chair['rev'] = 1;
		$chair['companion_of'] = $row['row_id'];
		$chair['requirement'] = 'Dining chairs for ' . $cat['products'][$pid]['name'] . ' (' . $seats . ' seater)';
		$chair['item_description'] = $chair['requirement'];
		$chair['product_code'] = $cat['products'][$chairId]['name'];
		$chair['quantity'] = $qty;
		$chair['quantity_text'] = $qty === null ? '' : (string) $qty;
		$chair['quantity_defaulted'] = false;
		$chair['quantity_checks'] = array();
		$chair['dimensions'] = '';
		$chair['other_specs'] = '';
		$chair['product_id'] = null;
		$chair['match'] = qt_ai_match_result('suggested', $chairId, array(), array($chairId), 'The chair of ' . $cat['products'][$pid]['name']
			. ' in the price list; ' . $seats . ' per table for a ' . $seats . ' seater.', array());
		pi_set_product($chair, $chairId, 'set');
		array_splice($draft['rows'], $i + 1, 0, array($chair));
		$notes[] = 'Added ' . ($qty === null ? '' : $qty . ' ') . 'chairs (' . $cat['products'][$chairId]['name'] . ') for the ' . $seats . ' seater '
			. $cat['products'][$pid]['name'] . '. Remove them if the table is wanted without chairs.';
		return array_merge($notes, pi_add_dining_chairs($connect, $draft, $rowIds));   // indexes moved: continue with a fresh pass
	}
	return $notes;
}

/** Items on the draft (not removed) that were read from a given message. */
function pi_rows_from_message($draft, $messageId)
{
	$n = 0;
	foreach ($draft['rows'] as $r) {
		if (empty($r['removed']) && preg_match('/^message ' . (int) $messageId . ',/', (string) $r['source_ref'])) {
			$n++;
		}
	}
	return $n;
}

/* ---------------------------------------------------------------- document generation */

/** Indian financial year (April to March), e.g. 2026-27. */
function pi_fiscal_year($time = null)
{
	$t = $time === null ? time() : $time;
	$y = (int) date('Y', $t);
	$start = (int) date('n', $t) >= 4 ? $y : $y - 1;
	return $start . '-' . substr((string) ($start + 1), 2);
}

/**
 * Generate the document for exactly the confirmed preview (version + hash).
 * Idempotent: the same confirmation returns the same document. Returns array(doc, error, status).
 */
function pi_generate($connect, $ai, $convId, $version, $hash)
{
	list($draft, $current) = pi_draft_load($connect, $convId);
	if ($draft === null) {
		return array(null, 'Draft not found.', 404);
	}
	$key = $convId . ':' . (int) $version . ':' . substr((string) $hash, 0, 16);
	$existing = pi_document_by_key($connect, $key);
	if ($existing !== null) {
		return array($existing, null, 200);
	}
	if ((int) $version !== $current) {
		return array(null, 'The draft changed after this preview. Review the latest preview and confirm that one.', 409);
	}
	$pi = pi_compute($connect, $draft);
	if (!hash_equals($pi['hash'], (string) $hash)) {
		return array(null, 'Prices or details changed since this preview (for example a price-list update). Review the new preview and confirm it.', 409);
	}
	if (count($pi['blockers']) > 0) {
		return array(null, 'The draft is not complete: ' . implode(' ', $pi['blockers']), 422);
	}
	$templates = pi_templates($connect);
	if ($draft['doc_type'] === 'quotation') {
		return pi_generate_quotation($connect, $convId, $draft, $pi, $key, $version);
	}

	$fy = pi_fiscal_year();
	$doc = null;
	for ($attempt = 0; $attempt < 5 && $doc === null; $attempt++) {
		$r = mysqli_query($connect, "SELECT COALESCE(MAX(seq), 0) + 1 FROM pi_documents WHERE fiscal_year = '" . $fy . "'");
		$seq = (int) mysqli_fetch_row($r)[0];
		$number = 'Cry/' . substr($fy, 2) . '/PI-' . $seq;   // same style as quotation numbers: Cry/26-27/Q-2673
		$snapshot = array('number' => $number, 'date' => date('d-m-Y'), 'fiscal_year' => $fy, 'pi' => $pi, 'templates' => $templates,
			'prepared_by' => array('name' => isset($_SESSION['fullName']) ? $_SESSION['fullName'] : $_SESSION['username'],
				'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '', 'phone' => isset($_SESSION['phone']) ? $_SESSION['phone'] : ''),
			'conversation_id' => $convId, 'draft_version' => (int) $version, 'sources' => $draft['attachments'],
			'document_notes' => $draft['document_notes']);
		$ok = mysqli_query($connect, "INSERT INTO pi_documents (pi_number, fiscal_year, seq, conversation_id, user_id, username, draft_version, preview_hash,
			idempotency_key, data, created_at) VALUES ('" . pi_esc($connect, $number) . "', '" . $fy . "', " . $seq . ", '" . $convId . "', "
			. (int) $_SESSION['user_id'] . ", '" . pi_esc($connect, $_SESSION['username']) . "', " . (int) $version . ", '" . pi_esc($connect, $hash) . "', '"
			. pi_esc($connect, $key) . "', '" . pi_esc($connect, json_encode($snapshot, JSON_UNESCAPED_UNICODE)) . "', NOW())");
		if ($ok) {
			$doc = array('id' => (int) mysqli_insert_id($connect), 'number' => $number);
		} elseif (mysqli_errno($connect) === 1062) {
			$existing = pi_document_by_key($connect, $key);
			if ($existing !== null) {
				return array($existing, null, 200);   // a parallel click won the race: same document
			}
			// Sequence taken by another PI: try the next number.
		} else {
			return array(null, 'The PI could not be saved.', 500);
		}
	}
	if ($doc === null) {
		return array(null, 'The PI could not be numbered. Please try again.', 500);
	}
	// The PI is shown in the application's own document format (PiView.php, same layout as the quotation
	// print page) straight from this snapshot; no separate file is produced.
	mysqli_query($connect, "UPDATE ai_pi_drafts SET confirmed_version = " . (int) $version . ", confirmed_hash = '" . pi_esc($connect, $hash)
		. "', document_id = " . $doc['id'] . " WHERE conversation_id = '" . $convId . "' AND version = " . (int) $version);
	pi_save_to_quotations($connect, $doc['id']);
	return array(pi_document_by_key($connect, $key), null, 201);
}

/**
 * Save an approved PI as a row of tbl_quotation, the table manual quotations use, so it is listed under
 * Saved quotations, numbered in the same series as manual quotations, with the PI's customer, items and rates.
 * The quotation pages apply one discount to every line: when NET items carry no discount, the discount is stored
 * as the equivalent percentage of the whole, so the totals match the PI. Charges have no place in tbl_quotation.
 * Idempotent (pi_documents.quotation_id). Returns array(quotation id|null, error|null).
 */
function pi_save_to_quotations($connect, $docId)
{
	$has = ($c = mysqli_query($connect, "SHOW COLUMNS FROM pi_documents LIKE 'quotation_id'")) && mysqli_num_rows($c) > 0;
	if (!$has) {
		return array(null, 'Run cli/migrate_pi.php first.');
	}
	$row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM pi_documents WHERE id = " . (int) $docId));
	if (!$row) {
		return array(null, 'PI not found.');
	}
	if ($row['quotation_id'] !== null) {
		$q = mysqli_query($connect, "SELECT q_id FROM tbl_quotation WHERE q_id = " . (int) $row['quotation_id']);
		if ($q && mysqli_num_rows($q) > 0) {
			return array((int) $row['quotation_id'], null);
		}
	}
	$snap = json_decode($row['data'], true);
	if (!is_array($snap) || !isset($snap['pi']) || isset($snap['quotation_id'])) {
		return array(null, 'Not a PI (quotations from the assistant are already in tbl_quotation).');
	}
	$pi = $snap['pi'];
	$type = $pi['basis'] === 'MRP' ? 'MRP' : 'DP';
	$items = array();
	foreach ($pi['lines'] as $l) {
		list($line, $err) = qt_load_product_line($connect, $l['product']['id'], $type, '');
		if ($err !== null) {
			// Product changed since the PI: keep the PI's own description of it.
			$line = array('id' => (string) $l['product']['id'], 'name' => $l['product']['name'], 'description' => $l['product']['description'],
				'color' => $l['product']['color'], 'image' => $l['product']['thumb'], 'size' => $l['product']['size'], 'price_list' => '', 'price_note' => '');
		}
		$line['price'] = $l['rate'];
		$line['quantity'] = (int) $l['quantity'];
		$key = (string) $l['product']['id'];
		while (isset($items[$key])) {
			$key .= '-' . $l['n'];   // the same product on two PI lines stays two lines
		}
		$items[$key] = $line;
	}
	$discount = '';
	if ($pi['discount'] > 0 && $pi['subtotal'] > 0) {
		$discount = empty($pi['net_subtotal']) ? rtrim(rtrim(number_format($pi['discount_percent'], 2, '.', ''), '0'), '.')
			: rtrim(rtrim(number_format($pi['discount'] * 100 / $pi['subtotal'], 6, '.', ''), '0'), '.');
	}
	$c = $pi['customer'];
	$client = qt_ai_cut(trim($c['name'] . ', ' . str_replace("\n", ' ', $c['address']), ', '), QT_MAX_CLIENT_NAME);
	// Numbered in the same series as manual quotations (Cry/26-27/Q-n, count.txt).
	$qid = qt_insert_new_quotation($connect, $type, $client, $discount, qt_encode_items($items), $row['username'],
		isset($snap['date']) ? $snap['date'] : date('d-m-Y', strtotime($row['created_at'])));
	if ($qid === null) {
		return array(null, 'The PI could not be added to the quotations.');
	}
	$qid = (int) $qid;
	mysqli_query($connect, "UPDATE pi_documents SET quotation_id = $qid WHERE id = " . (int) $docId);
	return array($qid, null);
}

/** A quotation from the assistant is saved through the existing quotation numbering and print page. */
function pi_generate_quotation($connect, $convId, $draft, $pi, $key, $version)
{
	if ($pi['net_subtotal'] > 0 && $pi['discount_percent'] > 0) {
		return array(null, 'This draft has NET items, which carry no discount, but the quotation page applies its discount to every item. '
			. 'Create a PI instead, or remove the discount.', 422);
	}
	$items = array();
	foreach ($pi['lines'] as $l) {
		list($line, $err) = qt_load_product_line($connect, $l['product']['id'], $pi['basis'], $pi['pricing']['mode'] === 'state' ? $pi['pricing']['code'] : '');
		if ($err !== null) {
			return array(null, 'Item ' . $l['n'] . ': ' . $err, 422);
		}
		$line['price'] = $l['rate'];
		$line['quantity'] = (int) $l['quantity'];
		$items[(string) $l['product']['id']] = $line;
	}
	$client = trim($draft['customer']['name'] . ', ' . $draft['customer']['address'], ', ');
	$qid = qt_insert_new_quotation($connect, $pi['basis'], qt_ai_cut($client, QT_MAX_CLIENT_NAME),
		$pi['discount_percent'] > 0 ? (string) $pi['discount_percent'] : '', qt_encode_items($items));
	if ($qid === null) {
		return array(null, 'The quotation could not be saved.', 500);
	}
	$r = mysqli_query($connect, "SELECT q_num FROM tbl_quotation WHERE q_id = " . (int) $qid);
	$num = $r ? mysqli_fetch_row($r)[0] : ('#' . $qid);
	$fy = 'Q';
	$snapshot = array('number' => $num, 'quotation_id' => (int) $qid, 'pi' => $pi, 'conversation_id' => $convId, 'draft_version' => (int) $version);
	mysqli_query($connect, "INSERT INTO pi_documents (pi_number, fiscal_year, seq, conversation_id, user_id, username, draft_version, preview_hash,
		idempotency_key, data, created_at) VALUES ('" . pi_esc($connect, 'QUOTATION ' . $num) . "', '" . $fy . "', " . (int) $qid . ", '" . $convId . "', "
		. (int) $_SESSION['user_id'] . ", '" . pi_esc($connect, $_SESSION['username']) . "', " . (int) $version . ", '" . pi_esc($connect, $pi['hash']) . "', '"
		. pi_esc($connect, $key) . "', '" . pi_esc($connect, json_encode($snapshot, JSON_UNESCAPED_UNICODE)) . "', NOW())");
	return array(pi_document_by_key($connect, $key), null, 201);
}

function pi_document_by_key($connect, $key)
{
	$r = mysqli_query($connect, "SELECT * FROM pi_documents WHERE idempotency_key = '" . pi_esc($connect, $key) . "'");
	$row = $r ? mysqli_fetch_assoc($r) : null;
	return $row ? pi_document_view($row) : null;
}

function pi_document_view($row)
{
	$data = json_decode($row['data'], true);
	$isQuotation = isset($data['quotation_id']);
	return array('id' => (int) $row['id'], 'number' => $isQuotation ? $data['number'] : $row['pi_number'], 'kind' => $isQuotation ? 'quotation' : 'PI',
		'draft_version' => (int) $row['draft_version'],
		'created_at' => $row['created_at'], 'total' => isset($data['pi']['total']) ? $data['pi']['total'] : null,
		'url' => $isQuotation ? ($data['pi']['basis'] === 'MRP' ? 'ExportToExcelMRP.php' : 'ExportToExcelDp.php') . '?id=' . (int) $data['quotation_id']
			: 'PiView.php?id=' . (int) $row['id']);
}

/** Load a generated document for its owner only. Returns array(row, error). */
function pi_document_get($connect, $id)
{
	$id = qt_parse_id($id);
	if ($id === null) {
		return array(null, 'Document not found.');
	}
	$r = mysqli_query($connect, "SELECT * FROM pi_documents WHERE id = " . $id);
	$row = $r ? mysqli_fetch_assoc($r) : null;
	if (!$row) {
		return array(null, 'Document not found.');
	}
	if ((int) $row['user_id'] !== (int) $_SESSION['user_id']) {
		return array(null, 'You do not have access to this document.');
	}
	return array($row, null);
}
