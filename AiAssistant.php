<?php
/**
 * AI Assistant: chat screen for turning customer requirements into a Proforma Invoice or quotation.
 * The conversation and the draft live on the server (ai_chat.php); this page only shows them.
 */
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
		include_once('includes/ai_provider.php');
		include_once('includes/ai_import_lib.php');
		include_once('includes/pi_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		list($qtAi, $qtAiError) = qt_ai_settings();
		$piReady = pi_tables_exist($connect);

		include_once('templates/header.php');
		include_once('templates/header_menu.php');
		include_once('templates/side_menubar.php');
?>
  <div class="content-wrapper">
    <section class="content-header chat-page-head">
      <h1>AI Assistant</h1>
      <span class="pms-badge <?= qt_ai_mode($qtAi) === 'live' ? 'pms-badge-dp' : 'pms-badge-warn' ?>" id="chat-mode"
        title="<?= qt_e(qt_ai_mode_label($qtAi)) ?>"><?= qt_ai_mode($qtAi) === 'live' ? 'Live AI' : qt_e(qt_ai_mode_label($qtAi)) ?></span>
    </section>

    <section class="content">
      <?php echo qt_csrf_field(); ?>
      <?php echo qt_flash_render(); ?>
      <?php if ($qtAiError !== null): ?>
        <div class="alert alert-warning"><?= qt_e($qtAiError) ?> Create quotations manually from New DP / MRP quotation meanwhile.</div>
      <?php elseif (!$piReady): ?>
        <div class="alert alert-warning">The AI Assistant is not set up on this database yet. An administrator needs to run <code>php cli/migrate_pi.php --database=...</code>.</div>
      <?php else: ?>
      <div class="chat-app" id="chat-app"
        data-max-images="<?= (int) $qtAi['max_images'] ?>" data-max-image-bytes="<?= (int) $qtAi['max_image_bytes'] ?>"
        data-max-text="<?= (int) $qtAi['max_text_chars'] ?>">
        <aside class="chat-list box" aria-label="Conversations">
          <div class="chat-list-head">
            <strong>Conversations</strong>
            <button type="button" class="btn btn-primary btn-sm" id="chat-new">New</button>
          </div>
          <nav id="chat-conversations"></nav>
        </aside>

        <div class="chat-main box">
          <div class="chat-messages" id="chat-messages" aria-live="polite">
            <div class="chat-welcome" id="chat-welcome">
              <h2>How can I help?</h2>
              <p>Paste the customer's message or attach a photo of their list. You can write in English, Hindi or Marathi. For example:</p>
              <ul>
                <li>"Create a PI from this image for Maharashtra, dealer prices."</li>
                <li>"Change the second item quantity to 12."</li>
                <li>"Use the Gujarat price list."</li>
                <li>"Replace item 3 with Ariana-OT."</li>
                <li>"Customer is Sharma Interiors, Plot 12, MIDC Nagpur, GSTIN 27…"</li>
                <li>"Show the PI preview."</li>
              </ul>
            </div>
          </div>
          <div class="chat-status" id="chat-status" role="status" hidden></div>
          <form class="chat-composer" id="chat-composer" autocomplete="off">
            <div class="chat-previews" id="chat-previews"></div>
            <div class="chat-compose-row">
              <label class="btn btn-default chat-attach" title="Attach photos or screenshots (JPG, PNG, WebP)">
                <input type="file" id="chat-files" accept="image/jpeg,image/png,image/webp" multiple hidden>
                <span aria-hidden="true">&#128206;</span><span class="sr-only">Attach images</span>
              </label>
              <label for="chat-input" class="sr-only">Message</label>
              <textarea id="chat-input" rows="1" placeholder="Type a message, paste a requirement, or attach photos…" maxlength="<?= (int) $qtAi['max_text_chars'] ?>"></textarea>
              <button type="submit" class="btn btn-primary" id="chat-send">Send</button>
            </div>
            <div class="chat-hint">Enter to send · Shift+Enter for a new line · Nothing is saved as a document until you confirm the preview.</div>
          </form>
        </div>

        <aside class="chat-draft box" aria-label="Current draft">
          <div class="chat-draft-head"><strong>Draft</strong><span id="chat-draft-ver" class="text-muted"></span></div>
          <div id="chat-draft"><p class="pms-empty">No draft yet.</p></div>
        </aside>
      </div>
      <?php endif; ?>
    </section>
  </div>
<?php include_once('templates/footer.php'); ?>
<script src="js/ai_chat.js?v=8"></script>
