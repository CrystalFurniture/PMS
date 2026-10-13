// "Import customer requirement" panel (templates/ai_import_panel.php).
// Browser checks here are for convenience only; the server validates everything again.
(function () {
	'use strict';

	var configEl = document.getElementById('ai-import-config');
	if (!configEl) { return; }
	var cfg = JSON.parse(configEl.textContent);
	var files = [];
	var allowed = ['image/jpeg', 'image/png', 'image/webp'];
	var input = document.getElementById('ai-files');
	var previews = document.getElementById('ai-previews');
	var status = document.getElementById('ai-import-status');
	var button = document.getElementById('ai-extract');

	function setStatus(text, isError) {
		status.textContent = text;
		status.className = isError ? 'text-danger' : 'text-muted';
	}

	function renderPreviews() {
		previews.innerHTML = '';
		files.forEach(function (f, i) {
			var url = URL.createObjectURL(f);
			var img = document.createElement('img');
			img.src = url;
			img.alt = 'Image ' + (i + 1);
			img.style.cssText = 'height:90px;max-width:140px;object-fit:contain;border:1px solid #ddd;display:block';
			img.onload = function () { URL.revokeObjectURL(url); };
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'btn btn-default btn-xs';
			remove.textContent = 'Remove';
			remove.addEventListener('click', function () { files.splice(i, 1); renderPreviews(); });
			var label = document.createElement('div');
			label.textContent = 'Image ' + (i + 1);
			var wrap = document.createElement('div');
			wrap.className = 'ai-preview';
			wrap.style.cssText = 'display:inline-block;margin:0 8px 8px 0;text-align:center';
			wrap.appendChild(img);
			wrap.appendChild(label);
			wrap.appendChild(remove);
			previews.appendChild(wrap);
		});
	}

	input.addEventListener('change', function () {
		var problems = [];
		Array.prototype.forEach.call(input.files, function (f) {
			if (allowed.indexOf(f.type) === -1) { problems.push(f.name + ' is not a JPG, PNG or WebP image'); return; }
			if (f.size > cfg.max_image_bytes) { problems.push(f.name + ' is too large'); return; }
			if (files.length >= cfg.max_images) { problems.push('only ' + cfg.max_images + ' images are allowed'); return; }
			files.push(f);
		});
		input.value = '';
		renderPreviews();
		setStatus(problems.join('; '), problems.length > 0);
	});

	function post(fields) {
		return fetch('ai_import.php', { method: 'POST', body: fields, credentials: 'same-origin' })
			.then(function (res) {
				return res.json().catch(function () {
					return { success: false, error: res.status === 413 ? 'The upload is too large for this server.' : 'The server returned an unexpected response (HTTP ' + res.status + ').' };
				});
			})
			.catch(function () { return { success: false, error: 'The connection was interrupted. Please try again.' }; });
	}

	button.addEventListener('click', function () {
		var text = document.getElementById('ai-text').value;
		if (!text.trim() && files.length === 0) {
			setStatus('Paste the customer message or add at least one image.', true);
			return;
		}
		var csrf = document.getElementById('qt_csrf').value;
		var form = new FormData();
		form.append('action', 'create');
		form.append('type', cfg.type);
		form.append('csrf_token', csrf);
		form.append('text', text);
		var instructions = document.getElementById('ai-instructions');
		form.append('instructions', instructions ? instructions.value : '');
		files.forEach(function (f) { form.append('images[]', f, f.name); });

		button.disabled = true;
		setStatus('Uploading…', false);
		post(form).then(function (res) {
			if (!res.success) { throw res; }
			setStatus('Reading the requirement and matching catalogue products. This can take a minute…', false);
			var next = new FormData();
			next.append('action', 'process');
			next.append('draft', res.draft);
			next.append('csrf_token', csrf);
			return post(next).then(function (done) {
				if (done.success || done.review_url) { window.location.href = done.review_url || res.review_url; return; }
				throw done;
			});
		}).catch(function (err) {
			button.disabled = false;
			setStatus((err && err.error) || 'Something went wrong. Please try again.', true);
		});
	});
}());
