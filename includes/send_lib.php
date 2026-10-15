<?php
/**
 * Send a document (PDF) by email (Gmail SMTP) and WhatsApp (Meta Cloud API).
 * The same file is used by Quotation/PMS (quotations) and tallyTest (sale orders); keep both copies identical.
 * Written for PHP 5.5 (the tallyTest server).
 *
 * Settings: <parent of the web root>/pms_private/send_config.php (or the path in SEND_CONFIG), outside Git
 * and the web root. See docs/send_config.sample.php.
 *
 * Optional tables in the pms database (cli/migrate_pi.php): send_log (one row per recipient) and
 * send_managers (the manager last entered by each user). Without them sending still works.
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/** The six contacts of the send form: name => array(label, kind, required). */
function send_fields()
{
	return array(
		'cust_email' => array('Customer email', 'email', true),
		'cust_wa' => array('Customer WhatsApp no.', 'wa', true),
		'sales_email' => array('Salesperson email', 'email', true),
		'sales_wa' => array('Salesperson WhatsApp no.', 'wa', true),
		'mgr_email' => array('Manager email', 'email', false),
		'mgr_wa' => array('Manager WhatsApp no.', 'wa', false),
	);
}

/** Settings array, or null when the file is missing or not filled in. */
function send_config()
{
	$path = getenv('SEND_CONFIG');
	if (!$path) {
		$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
		$path = $docRoot !== '' ? dirname($docRoot) . '/pms_private/send_config.php' : '';
	}
	if ($path === '' || !is_file($path)) {
		return null;
	}
	$cfg = include $path;
	return is_array($cfg) ? $cfg : null;
}

/** What is missing in the settings to send on each channel ('' = ready). */
function send_config_problem($cfg, $channel)
{
	if ($cfg === null) {
		return 'Sending is not set up: pms_private/send_config.php is missing.';
	}
	if ($channel === 'email') {
		$s = isset($cfg['smtp']) ? $cfg['smtp'] : array();
		return empty($s['username']) || empty($s['password']) ? 'Email is not set up (Gmail address and app password).' : '';
	}
	$w = isset($cfg['whatsapp']) ? $cfg['whatsapp'] : array();
	return empty($w['phone_number_id']) || empty($w['access_token']) || empty($w['template'])
		? 'WhatsApp is not set up (phone number ID, access token and template).' : '';
}

/** Indian mobile numbers: 10 digits get 91 in front. Returns digits only, or '' when not a phone number. */
function send_phone($s)
{
	$d = preg_replace('/\D+/', '', (string) $s);
	if (strlen($d) === 11 && $d[0] === '0') {
		$d = substr($d, 1);
	}
	if (strlen($d) === 10) {
		$d = '91' . $d;
	}
	return strlen($d) >= 11 && strlen($d) <= 15 ? $d : '';
}

/**
 * Check the posted form. Returns array(contacts, errors): contacts holds the cleaned value of each field
 * ('' when left empty), errors one message per wrong or missing field.
 */
function send_read_form($post)
{
	$contacts = array();
	$errors = array();
	foreach (send_fields() as $name => $f) {
		$v = isset($post[$name]) ? trim((string) $post[$name]) : '';
		if ($v === '') {
			if ($f[2]) {
				$errors[$name] = $f[0] . ' is required.';
			}
			$contacts[$name] = '';
			continue;
		}
		if ($f[1] === 'email') {
			if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
				$errors[$name] = $f[0] . ' is not a valid email address.';
			}
		} elseif (send_phone($v) === '') {
			$errors[$name] = $f[0] . ' must be a 10 digit mobile number (or with country code).';
		} else {
			$v = send_phone($v);
		}
		$contacts[$name] = $v;
	}
	return array($contacts, $errors);
}

/**
 * One SMTP connection for all the emails of a send (kept open between them). The office network sometimes
 * drops new connections to Gmail, so connecting is tried a few times before giving up.
 */
function send_mailer($cfg)
{
	$s = $cfg['smtp'];
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	$mail->isSMTP();
	$mail->Host = isset($s['host']) ? $s['host'] : 'smtp.gmail.com';
	$mail->Port = isset($s['port']) ? (int) $s['port'] : 587;
	$mail->SMTPSecure = isset($s['secure']) ? $s['secure'] : 'tls';
	$mail->SMTPAuth = true;
	$mail->SMTPKeepAlive = true;
	$mail->Username = $s['username'];
	$mail->Password = $s['password'];
	$mail->Timeout = isset($cfg['connect_timeout']) ? (int) $cfg['connect_timeout'] : 8;
	if (!empty($cfg['ca_file'])) {
		$mail->SMTPOptions = array('ssl' => array('cafile' => $cfg['ca_file']));
	}
	$mail->CharSet = 'UTF-8';
	$mail->setFrom(!empty($s['from_email']) ? $s['from_email'] : $s['username'], isset($s['from_name']) ? $s['from_name'] : '');
	return $mail;
}

function send_email($cfg, $mail, $to, $name, $subject, $body, $pdf, $filename)
{
	$attempts = isset($cfg['smtp_attempts']) ? max(1, (int) $cfg['smtp_attempts']) : 4;
	$error = '';
	for ($i = 1; $i <= $attempts; $i++) {
		try {
			$mail->clearAllRecipients();
			$mail->clearAttachments();
			$mail->addAddress($to, $name);
			$mail->Subject = $subject;
			$mail->Body = $body;
			$mail->addStringAttachment($pdf, $filename, 'base64', 'application/pdf');
			$mail->send();
			return array(true, '');
		} catch (Exception $e) {
			$error = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
			$mail->smtpClose();
			// Only a failed connection is worth retrying; a refused address or login is not.
			if (stripos($error, 'connect') === false) {
				break;
			}
		}
	}
	return array(false, $error);
}

/** POST to the Meta Graph API. Returns array(decoded response|null, error). */
function send_graph($cfg, $path, $fields, $json)
{
	$w = $cfg['whatsapp'];
	$url = 'https://graph.facebook.com/' . (isset($w['api_version']) ? $w['api_version'] : 'v21.0') . '/' . $path;
	$ch = curl_init($url);
	$headers = array('Authorization: Bearer ' . $w['access_token']);
	if ($json) {
		$headers[] = 'Content-Type: application/json';
		$fields = json_encode($fields);
	}
	curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_HTTPHEADER => $headers,
		CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30));
	if (!empty($cfg['ca_file'])) {
		curl_setopt($ch, CURLOPT_CAINFO, $cfg['ca_file']);
	}
	$raw = curl_exec($ch);
	$err = curl_error($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($raw === false) {
		return array(null, 'WhatsApp connection failed: ' . $err);
	}
	$data = json_decode($raw, true);
	if ($code < 200 || $code >= 300) {
		$msg = isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $code);
		if (!empty($data['error']['error_data']['details'])) {
			$msg .= ' (' . $data['error']['error_data']['details'] . ')';
		}
		return array(null, 'WhatsApp: ' . $msg);
	}
	return array($data, '');
}

/** Upload the PDF once; the media id is used for every WhatsApp recipient. Returns array(media id|null, error). */
function send_whatsapp_upload($cfg, $pdf, $filename)
{
	$tmp = tempnam(sys_get_temp_dir(), 'wa');
	file_put_contents($tmp, $pdf);
	list($data, $err) = send_graph($cfg, $cfg['whatsapp']['phone_number_id'] . '/media', array(
		'messaging_product' => 'whatsapp', 'type' => 'application/pdf',
		'file' => new CURLFile($tmp, 'application/pdf', $filename)), false);
	@unlink($tmp);
	if ($data === null || empty($data['id'])) {
		return array(null, $err !== '' ? $err : 'WhatsApp did not accept the PDF.');
	}
	return array($data['id'], '');
}

/**
 * Template message: header = the PDF; body values in the order of whatsapp.body_params, from: name (recipient),
 * label ("Quotation Cry/26-27/Q-2720"), number ("Cry/26-27/Q-2720"), total ("127,579"), company.
 * Default array('name', 'label'). Use array() for a template whose body has no {{n}}.
 */
function send_whatsapp($cfg, $to, $name, $doc, $mediaId)
{
	$w = $cfg['whatsapp'];
	$filename = $doc['filename'];
	$values = array('name' => $name, 'label' => $doc['label'], 'number' => isset($doc['number']) ? $doc['number'] : '',
		'total' => isset($doc['total']) ? $doc['total'] : '', 'company' => 'CRYSTAL FURNITECH PRIVATE LIMITED');
	$components = array(array('type' => 'header', 'parameters' => array(array('type' => 'document', 'document' => array('id' => $mediaId, 'filename' => $filename)))));
	$body = array();
	foreach (isset($w['body_params']) && is_array($w['body_params']) ? $w['body_params'] : array('name', 'label') as $p) {
		$body[] = array('type' => 'text', 'text' => isset($values[$p]) ? $values[$p] : (string) $p);
	}
	if (count($body)) {
		$components[] = array('type' => 'body', 'parameters' => $body);
	}
	list($data, $err) = send_graph($cfg, $w['phone_number_id'] . '/messages', array(
		'messaging_product' => 'whatsapp',
		'to' => $to,
		'type' => 'template',
		'template' => array(
			'name' => $w['template'],
			'language' => array('code' => isset($w['language']) ? $w['language'] : 'en'),
			'components' => $components,
		),
	), true);
	return $data === null ? array(false, $err) : array(true, '');
}

/**
 * Send one document to the contacts of the form.
 * $doc: label ("Quotation Cry/26-27/Q-2720"), number, total (formatted amount, for WhatsApp), filename,
 * pdf (bytes), names: array(cust, sales, mgr).
 * Returns a list of array(field, label, address, ok, error), one per filled-in contact.
 */
function send_document($cfg, $contacts, $doc)
{
	$results = array();
	$fields = send_fields();
	$names = $doc['names'];
	$mediaId = null;
	$mediaErr = '';
	$mailer = null;
	$signature = isset($cfg['signature']) ? $cfg['signature'] : "Regards,\nCRYSTAL FURNITECH PRIVATE LIMITED";
	foreach ($contacts as $field => $address) {
		if ($address === '') {
			continue;
		}
		$role = substr($field, 0, strpos($field, '_'));
		$name = isset($names[$role]) && $names[$role] !== '' ? $names[$role] : 'Sir/Madam';
		if ($fields[$field][1] === 'email') {
			$problem = send_config_problem($cfg, 'email');
			if ($problem !== '') {
				$r = array(false, $problem);
			} else {
				if ($mailer === null) {
					$mailer = send_mailer($cfg);
				}
				$r = send_email($cfg, $mailer, $address, $name, $doc['label'] . ' - CRYSTAL FURNITECH',
					"Dear " . $name . ",\n\nPlease find attached " . $doc['label'] . ".\n\n" . $signature . "\n", $doc['pdf'], $doc['filename']);
			}
		} else {
			$problem = send_config_problem($cfg, 'wa');
			if ($problem !== '') {
				$r = array(false, $problem);
			} else {
				if ($mediaId === null && $mediaErr === '') {
					list($mediaId, $mediaErr) = send_whatsapp_upload($cfg, $doc['pdf'], $doc['filename']);
				}
				$r = $mediaId === null ? array(false, $mediaErr) : send_whatsapp($cfg, $address, $name, $doc, $mediaId);
			}
		}
		$results[] = array($field, $fields[$field][0], $address, $r[0], $r[1]);
	}
	if ($mailer !== null) {
		$mailer->smtpClose();
	}
	return $results;
}

/** Plain-text summary of the results, e.g. for an alert. */
function send_summary($results)
{
	$lines = array();
	foreach ($results as $r) {
		$lines[] = ($r[3] ? 'Sent' : 'NOT sent') . ' - ' . $r[1] . ' (' . $r[2] . ')' . ($r[3] ? '' : ': ' . $r[4]);
	}
	return implode("\n", $lines);
}

function send_has_table($db, $table)
{
	$r = @mysqli_query($db, "SHOW TABLES LIKE '" . mysqli_real_escape_string($db, $table) . "'");
	return $r && mysqli_num_rows($r) > 0;
}

/** One send_log row per recipient ($db: connection to the pms database). */
function send_log($db, $app, $docType, $docRef, $username, $results)
{
	if (!send_has_table($db, 'send_log')) {
		return;
	}
	foreach ($results as $r) {
		mysqli_query($db, "INSERT INTO send_log (app, doc_type, doc_ref, username, field, recipient, ok, error, created_at) VALUES ('"
			. mysqli_real_escape_string($db, $app) . "', '" . mysqli_real_escape_string($db, $docType) . "', '" . mysqli_real_escape_string($db, $docRef) . "', '"
			. mysqli_real_escape_string($db, $username) . "', '" . mysqli_real_escape_string($db, $r[0]) . "', '" . mysqli_real_escape_string($db, $r[2]) . "', "
			. ($r[3] ? 1 : 0) . ", '" . mysqli_real_escape_string($db, substr($r[4], 0, 500)) . "', NOW())");
	}
}

/** The manager this user entered last time: array(email, whatsapp). */
function send_manager_get($db, $app, $username)
{
	if (!send_has_table($db, 'send_managers')) {
		return array('', '');
	}
	$r = mysqli_query($db, "SELECT email, whatsapp FROM send_managers WHERE app = '" . mysqli_real_escape_string($db, $app)
		. "' AND username = '" . mysqli_real_escape_string($db, $username) . "'");
	$row = $r ? mysqli_fetch_assoc($r) : null;
	return $row ? array($row['email'], $row['whatsapp']) : array('', '');
}

function send_manager_save($db, $app, $username, $email, $whatsapp)
{
	if (!send_has_table($db, 'send_managers')) {
		return;
	}
	mysqli_query($db, "REPLACE INTO send_managers (app, username, email, whatsapp, updated_at) VALUES ('" . mysqli_real_escape_string($db, $app) . "', '"
		. mysqli_real_escape_string($db, $username) . "', '" . mysqli_real_escape_string($db, $email) . "', '" . mysqli_real_escape_string($db, $whatsapp) . "', NOW())");
}

/** Customer email and mobile from the Tally ledger, matched by name like the sale order party search. */
function send_ledger_contact($db, $tallyDb, $customerName)
{
	$candidates = array($customerName);
	if (strpos($customerName, ',') !== false) {
		$candidates[] = substr($customerName, 0, strpos($customerName, ','));   // "Name, address" (quotations from PIs)
	}
	foreach ($candidates as $c) {
		$search = preg_replace('/[^a-z0-9A-Z]/i', '', $c);
		if ($search === '') {
			continue;
		}
		$r = mysqli_query($db, "SELECT CustomerName, CustomerEmail, CustomerLedgerMobile FROM `" . str_replace('`', '', $tallyDb) . "`.customerdetails
			WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CustomerName, ' ', ''), '/', ''), ',', ''), '\"', ''), '\'', ''),
			'.', ''), '+', ''), '-', ''), '&', ''), '(', ''), ')', '') = '" . mysqli_real_escape_string($db, $search) . "' LIMIT 1");
		$row = $r ? mysqli_fetch_assoc($r) : null;
		if ($row) {
			return array('name' => trim($row['CustomerName']), 'email' => trim((string) $row['CustomerEmail']),
				'wa' => send_phone($row['CustomerLedgerMobile']) !== '' ? trim((string) $row['CustomerLedgerMobile']) : '');
		}
	}
	return null;
}
