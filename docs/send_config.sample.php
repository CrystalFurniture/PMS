<?php
/**
 * Settings for sending quotations (Quotation/PMS, Approve) and sale orders (tallyTest, Place Order)
 * by email and WhatsApp. Used by includes/send_lib.php in both applications.
 *
 * Copy this file OUTSIDE the web root and outside Git, as
 *   <parent of the web root>/pms_private/send_config.php
 * on each server:
 *   - Quotation/PMS on this PC:  F:/xampp/pms_private/send_config.php
 *   - tallyTest on 192.168.1.111: G:/Xampp/pms_private/send_config.php
 * (or set the SEND_CONFIG environment variable to its path). Never commit the filled-in file.
 *
 * WhatsApp (Meta Cloud API), set up once in Meta Business Manager > WhatsApp Manager:
 *   1. Phone number ID and a permanent access token (System user token with whatsapp_business_messaging).
 *   2. A message template, category Utility, approved by Meta, with
 *        Header:  Document
 *        Body:    any text; its {{1}}, {{2}}, ... are filled in from body_params below
 *      and put its name and language below.
 */
return array(
	// Gmail: the sending address and a Google *app password* (Google Account > Security > 2-Step Verification > App passwords).
	'smtp' => array(
		'host' => 'smtp.gmail.com',
		'port' => 587,
		'secure' => 'tls',
		'username' => '',          // e.g. sales@crystalfurniture.in
		'password' => '',          // 16 character app password, not the normal password
		'from_email' => '',        // '' = the username
		'from_name' => 'CRYSTAL FURNITECH PRIVATE LIMITED',
	),

	'whatsapp' => array(
		'api_version' => 'v21.0',
		'phone_number_id' => '',
		'access_token' => '',
		'template' => '',          // e.g. document_share
		'language' => 'en',        // the template's language code
		// Values for the template body {{1}}, {{2}}, ... in order, from: name (recipient), number (document number),
		// total (final amount, e.g. 127,579.00), label ("Quotation Cry/26-27/Q-2720"), company.
		'body_params' => array('name', 'label'),
	),

	'signature' => "Regards,\nCRYSTAL FURNITECH PRIVATE LIMITED",

	// Tally database holding customerdetails (customer email / mobile for the send form on quotations).
	'tally_db' => 'tally_io',

	'timeout' => 30,               // seconds per email / WhatsApp request
	'ca_file' => '',               // only if HTTPS/TLS fails with a certificate error: path to cacert.pem
);
