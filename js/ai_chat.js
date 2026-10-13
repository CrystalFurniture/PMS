// AI Assistant chat screen (AiAssistant.php). The server (ai_chat.php) owns the conversation and the
// draft; this script renders what it returns and sends messages and button actions back.
// All text from the server is inserted as text, never as HTML.
(function () {
	'use strict';
	var app = document.getElementById('chat-app');
	if (!app) { return; }
	var maxImages = parseInt(app.dataset.maxImages, 10) || 5;
	var maxImageBytes = parseInt(app.dataset.maxImageBytes, 10) || 7340032;
	var listEl = document.getElementById('chat-conversations');
	var msgsEl = document.getElementById('chat-messages');
	var welcomeEl = document.getElementById('chat-welcome');
	var statusEl = document.getElementById('chat-status');
	var form = document.getElementById('chat-composer');
	var input = document.getElementById('chat-input');
	var filesInput = document.getElementById('chat-files');
	var previewsEl = document.getElementById('chat-previews');
	var sendBtn = document.getElementById('chat-send');
	var draftEl = document.getElementById('chat-draft');
	var draftVerEl = document.getElementById('chat-draft-ver');

	var conv = null;          // current conversation id
	var state = null;         // last state from the server
	var files = [];           // images waiting to be sent
	var busy = false;
	var pending = null;       // {clientId, text, files} of a message that failed to send (for Retry)

	function csrf() { var el = document.getElementById('qt_csrf'); return el ? el.value : ''; }
	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			var v = attrs[k];
			if (v === null || v === undefined || v === false) { return; }
			if (k === 'text') { node.textContent = v; }
			else if (k === 'class') { node.className = v; }
			else if (k.indexOf('on') === 0) { node.addEventListener(k.slice(2), v); }
			else { node.setAttribute(k, v === true ? '' : v); }
		});
		[].concat.apply([], children || []).forEach(function (c) { if (c !== null && c !== undefined) { node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
		return node;
	}
	function money(n) {
		if (n === null || n === undefined) { return '—'; }
		return '₹ ' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function clientId() { return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }
	function setStatus(text, kind, retry) {
		statusEl.hidden = !text;
		statusEl.className = 'chat-status' + (kind ? ' is-' + kind : '');
		statusEl.textContent = '';
		if (!text) { return; }
		statusEl.appendChild(el('span', { text: text }));
		if (retry) { statusEl.appendChild(el('button', { type: 'button', 'class': 'btn btn-default btn-xs', text: 'Retry', onclick: retry })); }
	}
	function setBusy(on, text) {
		busy = on;
		sendBtn.disabled = on;
		input.disabled = on;
		if (on) { setStatus(text || 'Working…', 'busy'); }
	}

	function request(method, params, body) {
		var url = 'ai_chat.php?' + new URLSearchParams(params).toString();
		var opts = { method: method, credentials: 'same-origin' };
		if (body) { body.append('csrf_token', csrf()); opts.body = body; }
		return fetch(url, opts).then(function (res) {
			return res.json().catch(function () { return { success: false, error: 'Unexpected server response (HTTP ' + res.status + ').' }; })
				.then(function (data) { data.httpStatus = res.status; return data; });
		}).catch(function () { return { success: false, error: 'The server could not be reached. Check the connection and try again.' }; });
	}
	function post(action, fields) {
		var fd = new FormData();
		fd.append('action', action);
		if (conv) { fd.append('conv', conv); }
		Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
		return request('POST', { action: action }, fd);
	}

	/* ------------------------------------------------------------ conversations */
	function renderList(items) {
		listEl.textContent = '';
		if (!items.length) { listEl.appendChild(el('p', { 'class': 'text-muted', style: 'padding:8px 12px', text: 'No conversations yet.' })); }
		items.forEach(function (c) {
			var menu = el('details', { 'class': 'chat-conv-menu' }, [
				el('summary', { 'aria-label': 'Options for ' + c.title, title: 'Rename, pin or delete', text: '⋯' }),
				el('div', { 'class': 'chat-conv-menu-list', role: 'menu' }, [
					el('button', { type: 'button', role: 'menuitem', text: 'Rename', onclick: function () { renameConversation(c); } }),
					el('button', { type: 'button', role: 'menuitem', text: c.pinned ? 'Unpin' : 'Pin', onclick: function () { pinConversation(c, !c.pinned); } }),
					el('button', { type: 'button', role: 'menuitem', 'class': 'is-danger', text: 'Delete', onclick: function () { deleteConversation(c); } })])]);
			listEl.appendChild(el('div', { 'class': 'chat-conv-row' + (c.id === conv ? ' is-on' : '') + (c.pinned ? ' is-pinned' : '') }, [
				el('a', { href: '?conv=' + c.id, 'class': 'chat-conv' + (c.id === conv ? ' is-on' : ''), 'aria-current': c.id === conv ? 'page' : null,
					onclick: function (e) { e.preventDefault(); open(c.id); } }, [
					el('span', { text: (c.pinned ? '📌 ' : '') + c.title }), el('small', { text: c.pinned ? 'Pinned · ' + c.updated_at : c.updated_at })]),
				menu]));
		});
	}
	// Close an open conversation menu when clicking elsewhere.
	document.addEventListener('click', function (e) {
		Array.prototype.forEach.call(listEl.querySelectorAll('.chat-conv-menu[open]'), function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } });
	});
	function convAction(action, id, fields) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('conv', id);
		Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
		return request('POST', { action: action }, fd).then(function (res) {
			if (!res.success) { setStatus(res.error, 'error'); return res; }
			renderList(res.conversations);
			return res;
		});
	}
	function renameConversation(c) {
		var title = window.prompt('Name of this conversation:', c.title);
		if (title === null) { return; }
		title = title.trim();
		if (!title) { setStatus('The name cannot be empty.', 'error'); return; }
		convAction('rename', c.id, { title: title }).then(function (res) { if (res.success) { setStatus('Conversation renamed.', 'ok'); } });
	}
	function pinConversation(c, on) {
		convAction('pin', c.id, { pinned: on ? '1' : '0' }).then(function (res) {
			if (res.success) { setStatus(on ? 'Pinned: it stays at the top and is never removed by the automatic clean-up.' : 'Unpinned.', 'ok'); }
		});
	}
	function deleteConversation(c) {
		if (!window.confirm('Delete "' + c.title + '"? Its messages, draft and attached images are deleted. Finalized PIs and saved quotations are kept.')) { return; }
		convAction('delete', c.id).then(function (res) {
			if (!res.success) { return; }
			setStatus('Conversation deleted.', 'ok');
			if (c.id === conv) {
				conv = null;
				state = null;
				history.replaceState(null, '', location.pathname);
				msgsEl.textContent = '';
				msgsEl.appendChild(welcomeEl);
				draftEl.textContent = '';
				draftVerEl.textContent = '';
			}
		});
	}
	function loadList() {
		return request('GET', { action: 'list' }).then(function (res) { if (res.success) { renderList(res.conversations); } });
	}
	function open(id) {
		conv = id;
		history.replaceState(null, '', '?conv=' + id);
		setStatus('');
		return request('GET', { action: 'state', conv: id }).then(function (res) {
			if (!res.success) { conv = null; setStatus(res.error, 'error'); return; }
			render(res);
			loadList();
		});
	}
	function newConversation() {
		return post('new').then(function (res) {
			if (!res.success) { setStatus(res.error, 'error'); return; }
			conv = res.conversation;
			history.replaceState(null, '', '?conv=' + conv);
			render(res);
			renderList(res.conversations);
			input.focus();
		});
	}

	/* ------------------------------------------------------------ messages */
	function render(res) {
		state = res;
		msgsEl.textContent = '';
		if (!res.messages.length) { msgsEl.appendChild(welcomeEl); }
		res.messages.forEach(function (m) { msgsEl.appendChild(renderMessage(m)); });
		renderDraft(res);
		msgsEl.scrollTop = msgsEl.scrollHeight;
	}
	function renderMessage(m) {
		var b = m.body || {};
		if (m.role === 'user') {
			var imgs = (b.images || []).map(function (img) {
				var src = 'ai_chat.php?action=image&conv=' + encodeURIComponent(conv) + '&file=' + encodeURIComponent(img.file);
				return el('a', { href: src, target: '_blank', rel: 'noopener', title: 'Open full size' }, [el('img', { src: src, alt: 'Attached image', 'class': 'chat-img' })]);
			});
			return el('div', { 'class': 'chat-msg is-user' }, [el('div', { 'class': 'chat-bubble' }, [
				imgs.length ? el('div', { 'class': 'chat-imgs' }, imgs) : null,
				b.text ? el('div', { 'class': 'chat-text', text: b.text }) : null])]);
		}
		var wrap = el('div', { 'class': 'chat-msg ' + (m.role === 'event' ? 'is-event' : 'is-assistant') });
		var bubble = el('div', { 'class': 'chat-bubble' });
		if (b.text) { bubble.appendChild(el('div', { 'class': 'chat-text', text: b.text })); }
		if (b.questions && b.questions.length) {
			bubble.appendChild(el('ul', { 'class': 'chat-questions' }, b.questions.map(function (q) { return el('li', { text: q }); })));
		}
		(b.cards || []).forEach(function (c) { var node = renderCard(c); if (node) { bubble.appendChild(node); } });
		wrap.appendChild(bubble);
		return wrap;
	}

	/* ------------------------------------------------------------ cards */
	function renderCard(c) {
		if (c.type === 'changes') {
			if (!c.items.length && !c.errors.length) { return null; }
			return el('div', { 'class': 'chat-card chat-changes' }, c.items.map(function (t) { return el('div', { 'class': 'is-ok', text: '✓ ' + t }); })
				.concat(c.errors.map(function (t) { return el('div', { 'class': 'is-err', text: '⚠ ' + t }); })));
		}
		if (c.type === 'price_diff') {
			return el('div', { 'class': 'chat-card' }, [
				el('div', { 'class': 'chat-card-title', text: c.from === 'the preview you approved' ? 'Prices changed since the preview you approved'
					: 'Rates after changing to ' + c.to + (c.basis ? ' (' + c.basis + ')' : '') }),
				c.old_total !== undefined ? el('div', { 'class': 'chat-small', text: 'Grand total ' + money(c.old_total) + ' → ' + money(c.new_total) }) : null,
				el('table', { 'class': 'chat-table' }, [el('thead', {}, [el('tr', {}, ['Item', 'Product', 'Before', 'Now'].map(function (h) { return el('th', { text: h }); }))]),
					el('tbody', {}, c.lines.map(function (l) {
						var changed = l.old !== l['new'];
						return el('tr', { 'class': changed ? 'is-changed' : '' }, [el('td', { text: String(l.n) }), el('td', { text: l.name }),
							el('td', { text: l.old === null ? '—' : money(l.old) }), el('td', { text: l['new'] === null ? 'No rate in this list' : money(l['new']) })]);
					}))])]);
		}
		if (c.type === 'choices') { return choicesCard(c); }
		if (c.type === 'documents') {
			var dc = el('div', { 'class': 'chat-card' }, [el('div', { 'class': 'chat-card-title', text: 'Approved documents: "' + c.query + '"' + (c.state ? ' (' + c.state + ')' : '') })]);
			if (!c.excerpts.length) { dc.appendChild(el('div', { 'class': 'text-muted', text: 'No approved document applies. Ask the office for the current policy.' })); }
			c.excerpts.forEach(function (x) {
				var d = x.document;
				dc.appendChild(el('blockquote', { 'class': 'chat-excerpt' }, [el('div', { 'class': 'chat-text', text: x.text }),
					el('footer', { 'class': 'chat-small text-muted', text: d.title + ' v' + d.version + ' · ' + d.type + ' · effective ' + d.effective_from
						+ (d.effective_to ? ' to ' + d.effective_to : '') + ' · ' + (d.states === 'ALL' ? 'all states' : 'states ' + d.states) + (d.source ? ' · ' + d.source : '') })]));
			});
			return dc;
		}
		if (c.type === 'preview') { return previewCard(c); }
		if (c.type === 'document') {
			var d = c.document;
			return el('div', { 'class': 'chat-card chat-doc' }, [
				el('div', { 'class': 'chat-card-title', text: (d.kind === 'PI' ? 'Proforma Invoice ' : 'Quotation ') + d.number }),
				el('div', { text: 'Total ' + money(d.total) + ' · created ' + d.created_at }),
				el('a', { 'class': 'btn btn-primary btn-sm', href: d.url, target: '_blank', text: d.kind === 'PI' ? 'Open PI (print / Excel)' : 'Open quotation' })]);
		}
		return null;
	}
	function choicesCard(c) {
		var card = el('div', { 'class': 'chat-card chat-choices' }, [el('div', { 'class': 'chat-card-title', text: c.title })]);
		if (c.reason) { card.appendChild(el('div', { 'class': 'text-muted chat-small', text: c.reason })); }
		if (!c.products.length) { card.appendChild(el('div', { 'class': 'text-muted', text: 'No matching products found. Type the product code, e.g. "Use Ariana-OT for item ' + (c.n || 1) + '".' })); }
		var grid = el('div', { 'class': 'chat-products' });
		c.products.forEach(function (p) {
			grid.appendChild(el('div', { 'class': 'chat-product' }, [
				el('strong', { text: p.name }),
				el('span', { 'class': 'chat-small', text: [p.description, p.size ? 'Size ' + p.size : '', p.color ? 'Colour ' + p.color : ''].filter(Boolean).join(' · ') }),
				el('span', { 'class': 'chat-rate', text: p.rate !== null ? money(p.rate) : (p.rate_note || '') }),
				c.row_id ? el('button', { type: 'button', 'class': 'btn btn-default btn-sm', text: 'Use for item ' + c.n,
					onclick: function () { action('pick', { row: c.row_id, product_id: p.id }); } }) : null]));
		});
		card.appendChild(grid);
		return card;
	}
	function rateTag(l) {
		var bits = [];
		if (l.rate_type === 'NET') { bits.push('NET'); }
		if (l.variant) { bits.push(l.variant.toLowerCase()); }
		return bits.length ? ' (' + bits.join(', ') + ')' : '';
	}
	function listText(pi) {
		var l = pi.price_list;
		var t = l.name + ' · ' + (pi.basis || '?') + ' · version ' + l.version;
		if (l.master) { t += ' (master v' + l.master.version + ')'; }
		if (l.fallback && l.fallback.master) { t += '; then ' + l.fallback.name + ' (master v' + l.fallback.master.version + ')'; }
		return t + (pi.pricing && pi.pricing.auto ? ' — automatic' : '');
	}
	function previewCard(c) {
		var pi = c.pi;
		var current = state && state.draft && state.draft.version === c.version;
		var incomplete = pi.blockers.length > 0;
		var finalized = current && state.draft.finalized ? state.draft.finalized : null;
		var kind = c.doc_type === 'PI' ? 'PI' : 'quotation';
		var title = 'Draft ' + kind + (finalized ? ' — finalized as ' + finalized.number : (!current ? ' (out of date)' : (incomplete ? ' — incomplete' : ' — ready for approval')));
		var card = el('div', { 'class': 'chat-card chat-preview' + (current ? '' : ' is-stale') + (incomplete ? ' is-incomplete' : '') }, [
			el('div', { 'class': 'chat-card-title', text: title })]);
		if (incomplete && current) {
			card.appendChild(el('div', { 'class': 'chat-small text-muted', text: 'Missing values are shown as —. Nothing is filled in by guesswork.' }));
		}
		var cu = pi.customer;
		card.appendChild(el('dl', { 'class': 'chat-facts' }, [
			el('dt', { text: 'Customer' }), el('dd', { text: cu.name || '—' }),
			el('dt', { text: 'Address' }), el('dd', { text: cu.address || '—' }),
			el('dt', { text: 'Customer state' }), el('dd', { text: cu.state_name || '—' }),
			el('dt', { text: 'GSTIN' }), el('dd', { text: cu.gstin || '—' }),
			el('dt', { text: 'Price list' }), el('dd', { text: pi.price_list ? listText(pi) : '—' })]));
		card.appendChild(el('table', { 'class': 'chat-table' }, [
			el('thead', {}, [el('tr', {}, ['#', 'Product', 'Qty', 'Rate', 'Amount'].map(function (h) { return el('th', { text: h }); }))]),
			el('tbody', {}, pi.lines.map(function (l) {
				return el('tr', { 'class': l.issues.length ? 'is-problem' : '' }, [el('td', { text: String(l.n) }),
					el('td', {}, [el('span', { text: l.product ? l.product.name : '(' + l.requirement + ')' }),
						l.issues.length ? el('small', { 'class': 'is-err', text: ' ' + l.issues.join(', ') }) : null,
						l.requested ? el('div', { 'class': 'chat-small text-muted', text: 'Requested: ' + l.requested }) : null,
						l.spec_warnings && l.spec_warnings.length ? el('div', { 'class': 'chat-small chat-specwarn', text: '⚠ ' + l.spec_warnings.join(' ') }) : null]),
					el('td', { text: l.quantity === null ? '—' : String(l.quantity) }), el('td', { text: money(l.rate) + rateTag(l) }), el('td', { text: money(l.amount) })]);
			}))]));
		var totals = [['Total', pi.subtotal]];
		if (pi.discount_percent > 0) { totals.push(['Discount ' + pi.discount_percent + '%' + (pi.net_subtotal > 0 ? ' (not on NET items)' : ''), -pi.discount]); }
		pi.charges.forEach(function (ch) { totals.push([ch.label, ch.amount]); });
		totals.push(['Basic amount', pi.basic]);
		totals.push([pi.tax_label + ' ' + (pi.tax_rate === null ? '?' : pi.tax_rate) + '%', pi.tax]);
		if (pi.round_off) { totals.push(['Round off', pi.round_off]); }
		totals.push(['Grand total', pi.total]);
		card.appendChild(el('table', { 'class': 'chat-totals' }, [el('tbody', {}, totals.map(function (t, i) {
			return el('tr', { 'class': i === totals.length - 1 ? 'is-total' : '' }, [el('td', { text: t[0] }), el('td', { text: money(t[1]) })]);
		}))]));
		if (pi.discount_note) {
			card.appendChild(el('div', { 'class': 'chat-small text-muted', text: pi.discount_note }));
		}
		if (pi.blockers.length) {
			card.appendChild(el('div', { 'class': 'chat-blockers' }, [el('strong', { text: 'Needed before it can be generated:' }),
				el('ul', {}, pi.blockers.map(function (b) { return el('li', { text: b }); }))]));
		}
		if (pi.warnings.length) {
			card.appendChild(el('details', { 'class': 'chat-warnings' }, [el('summary', { text: pi.warnings.length + ' note' + (pi.warnings.length === 1 ? '' : 's') }),
				el('ul', {}, pi.warnings.map(function (w) { return el('li', { text: w }); }))]));
		}
		if (finalized) {
			card.appendChild(el('a', { 'class': 'btn btn-default chat-generate', href: finalized.url, target: '_blank', text: 'Open ' + finalized.number }));
		} else if (current) {
			card.appendChild(el('button', { type: 'button', 'class': 'btn btn-primary chat-generate', disabled: pi.blockers.length > 0,
				text: kind === 'PI' ? 'Approve and finalize PI' : 'Approve and save quotation',
				title: pi.blockers.length ? 'Resolve the items listed above first' : 'Saves this exact version; any later change needs a new approval',
				onclick: function (e) { generate(c, e.target); } }));
		} else {
			card.appendChild(el('div', { 'class': 'text-muted chat-small', text: 'The draft changed after this preview. Use the latest preview to confirm.' }));
		}
		return card;
	}

	/* ------------------------------------------------------------ draft panel */
	function renderDraft(res) {
		var d = res.draft;
		draftVerEl.textContent = (d.status === 'finalized' ? 'Finalized · ' : 'Draft · ') + 'v' + d.version;
		draftEl.textContent = '';
		var pi = d.pi;
		draftEl.appendChild(el('dl', { 'class': 'chat-facts' }, [
			el('dt', { text: 'Document' }), el('dd', { text: d.doc_type === 'PI' ? 'Proforma Invoice' : 'Quotation' }),
			el('dt', { text: 'Price basis' }), el('dd', { text: d.basis || 'not chosen' }),
			el('dt', { text: 'Price list' }), el('dd', { text: pi.pricing ? pi.pricing.name + (pi.pricing.auto ? ' (automatic)' : '') : 'not chosen' }),
			el('dt', { text: 'Customer' }), el('dd', { text: d.customer.name || 'not given' }),
			el('dt', { text: 'Customer state' }), el('dd', { text: d.customer.state_name || 'not given' })]));
		if (!pi.lines.length) {
			draftEl.appendChild(el('p', { 'class': 'text-muted', text: 'No items yet.' }));
		}
		pi.lines.forEach(function (l) {
			var row = d.rows.filter(function (r) { return r.row_id === l.row_id; })[0] || {};
			var qty = el('input', { type: 'number', min: '1', max: '9999', value: l.quantity === null ? '' : String(l.quantity), 'class': 'chat-qty',
				'aria-label': 'Quantity of item ' + l.n });
			qty.addEventListener('change', function () { action('quantity', { row: l.row_id, quantity: qty.value }); });
			var rate = el('input', { type: 'text', inputmode: 'decimal', value: l.rate === null ? '' : String(l.rate), 'class': 'chat-rate-input' + (l.manual_rate ? ' is-manual' : ''),
				'aria-label': 'Rate of item ' + l.n + ' in rupees', placeholder: 'Rate', disabled: !l.product || !d.basis,
				title: !d.basis ? 'Choose DP or MRP first' : 'Type the correct rate per piece; the amount follows' });
			rate.addEventListener('change', function () {
				var v = rate.value.replace(/[,₹\s]/g, '');
				if (v === '' || (l.rate !== null && Number(v) === Number(l.rate))) { return; }
				action('rate', { row: l.row_id, rate: v });
			});
			var actions = el('div', { 'class': 'chat-item-actions' }, [
				el('button', { type: 'button', 'class': 'btn btn-link btn-xs', text: l.product ? 'Change product' : 'Choose product', onclick: function () { showChoices(l.row_id); } }),
				l.manual_rate ? el('button', { type: 'button', 'class': 'btn btn-link btn-xs', text: l.list_rate !== null && l.list_rate !== undefined ? 'Use list rate ' + money(l.list_rate) : 'Clear typed rate',
					onclick: function () { action('rate', { row: l.row_id, rate: '' }); } }) : null,
				l.zero_price && !l.zero_confirmed ? el('button', { type: 'button', 'class': 'btn btn-warning btn-xs', text: 'Confirm ₹0 rate',
					onclick: function () { if (confirm('"' + l.product.name + '" has a ₹0 rate. Put it on the document at ₹0?')) { action('confirm_zero', { row: l.row_id }); } } }) : null,
				l.missing_rate && l.standard_rate_available ? el('button', { type: 'button', 'class': 'btn btn-warning btn-xs', text: 'Use ' + l.fallback_label + ' rate',
					onclick: function () { if (confirm('The chosen state list has no rate for this product. Use the ' + l.fallback_label + ' rate for this item?')) { action('use_standard', { row: l.row_id }); } } }) : null,
				(l.needs_variant || []).map(function (v) {
					return el('button', { type: 'button', 'class': 'btn btn-warning btn-xs', text: v.charAt(0) + v.slice(1).toLowerCase() + ' wood',
						onclick: function () { action('variant', { row: l.row_id, variant: v }); } });
				}),
				el('button', { type: 'button', 'class': 'btn btn-link btn-xs chat-remove', text: 'Remove', onclick: function () { if (confirm('Remove item ' + l.n + '?')) { action('remove_row', { row: l.row_id }); } } })]);
			draftEl.appendChild(el('div', { 'class': 'chat-item' + (l.issues.length ? ' is-problem' : '') }, [
				el('div', { 'class': 'chat-item-top' }, [el('strong', { text: l.n + '. ' + (l.product ? l.product.name : 'No product yet') }), el('span', { text: money(l.amount) })]),
				el('div', { 'class': 'chat-small text-muted', text: '“' + (row.original_text || l.requirement) + '”' + (row.quantity_defaulted ? ' · quantity set by default' : '') }),
				l.requested ? el('div', { 'class': 'chat-small text-muted', text: 'Requested: ' + l.requested }) : null,
				l.spec_warnings && l.spec_warnings.length ? el('div', { 'class': 'chat-small chat-specwarn', text: '⚠ ' + l.spec_warnings.join(' ') }) : null,
				el('div', { 'class': 'chat-item-ctl' }, [qty, el('span', { 'class': 'chat-small', text: '× ₹' }), rate]),
				l.rate_source ? el('div', { 'class': 'chat-small text-muted' + (l.manual_rate ? ' chat-manual-note' : ''), text: (l.manual_rate ? '✎ ' : '') + l.rate_source }) : null,
				l.issues.length ? el('div', { 'class': 'is-err chat-small', text: l.issues.join(', ') }) : null,
				actions]));
		});
		if (pi.lines.length) {
			draftEl.appendChild(el('div', { 'class': 'chat-draft-total' }, [el('span', { text: 'Grand total' }), el('strong', { text: money(pi.total) })]));
		}
		if (pi.blockers.length) {
			draftEl.appendChild(el('div', { 'class': 'chat-blockers' }, [el('strong', { text: 'Still needed' }),
				el('ul', {}, pi.blockers.map(function (b) { return el('li', { text: b }); }))]));
		}
		draftEl.appendChild(el('button', { type: 'button', 'class': 'btn btn-default btn-block', text: 'Show preview', onclick: function () { action('preview', {}); } }));
		(res.documents || []).forEach(function (doc) {
			draftEl.appendChild(el('a', { 'class': 'chat-doc-link', href: doc.url, target: '_blank' }, [
				el('span', { text: (doc.kind === 'PI' ? 'PI ' : 'Quotation ') + doc.number }), el('small', { text: money(doc.total) })]));
		});
	}
	function showChoices(rowId) {
		request('GET', { action: 'choices', conv: conv, row: rowId }).then(function (res) {
			if (!res.success) { setStatus(res.error, 'error'); return; }
			var node = el('div', { 'class': 'chat-msg is-event' }, [el('div', { 'class': 'chat-bubble' }, [choicesCard(res.card)])]);
			msgsEl.appendChild(node);
			msgsEl.scrollTop = msgsEl.scrollHeight;
		});
	}

	/* ------------------------------------------------------------ actions */
	function action(name, fields) {
		if (busy || !conv) { return; }
		fields.version = state ? state.draft.version : '';
		setBusy(true, 'Updating the draft…');
		post(name, fields).then(function (res) {
			setBusy(false);
			setStatus('');
			if (res.state) { render(res.state); }
			if (!res.success) { setStatus(res.error, 'error'); return; }
			render(res);
		});
	}
	function generate(card, button) {
		if (busy) { return; }
		button.disabled = true;
		setBusy(true, card.doc_type === 'PI' ? 'Generating the Proforma Invoice…' : 'Saving the quotation…');
		post('generate', { version: card.version, hash: card.hash }).then(function (res) {
			setBusy(false);
			setStatus('');
			if (res.state) { render(res.state); }
			if (!res.success) { setStatus(res.error, 'error'); return; }
			render(res);
			if (res.already_generated) { setStatus('This preview was already generated: ' + res.document.number + '.', 'ok'); }
		});
	}
	function send(retryOf) {
		if (busy) { return; }
		var msg = retryOf || { clientId: clientId(), text: input.value.trim(), files: files.slice() };
		if (!msg.text && !msg.files.length) { return; }
		var start = conv ? Promise.resolve() : newConversation();
		start.then(function () {
			var fd = new FormData();
			fd.append('action', 'send');
			fd.append('conv', conv);
			fd.append('client_id', msg.clientId);
			fd.append('text', msg.text);
			msg.files.forEach(function (f) { fd.append('images[]', f); });
			// Show the message straight away; the server's copy replaces it when the reply arrives.
			if (welcomeEl.parentNode) { welcomeEl.parentNode.removeChild(welcomeEl); }
			msgsEl.appendChild(renderMessage({ role: 'user', body: { text: msg.text, images: [] } }));
			msgsEl.scrollTop = msgsEl.scrollHeight;
			input.value = '';
			files = [];
			renderPreviews();
			setBusy(true, msg.files.length ? 'Reading the attached images and matching products… (up to a minute)' : 'Thinking…');
			return request('POST', { action: 'send' }, fd).then(function (res) {
				setBusy(false);
				if (!res.success) {
					pending = msg;
					if (state) { render(state); }
					input.value = msg.text;
					files = msg.files;
					renderPreviews();
					setStatus(res.error, 'error', res.httpStatus >= 500 || res.httpStatus === 0 || res.httpStatus === 409 ? function () { input.value = ''; files = []; renderPreviews(); send(msg); } : null);
					return;
				}
				pending = null;
				setStatus('');
				render(res);
				if (res.conversations) { renderList(res.conversations); }
			});
		});
	}

	/* ------------------------------------------------------------ composer */
	function renderPreviews() {
		previewsEl.textContent = '';
		files.forEach(function (f, i) {
			var url = URL.createObjectURL(f);
			previewsEl.appendChild(el('div', { 'class': 'chat-preview-img' }, [el('img', { src: url, alt: f.name }),
				el('button', { type: 'button', 'class': 'chat-preview-x', 'aria-label': 'Remove ' + f.name, text: '×', onclick: function () { files.splice(i, 1); renderPreviews(); } })]));
		});
	}
	filesInput.addEventListener('change', function () {
		var problems = [];
		Array.prototype.forEach.call(filesInput.files, function (f) {
			if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) < 0) { problems.push(f.name + ' is not a JPG, PNG or WebP image.'); return; }
			if (f.size > maxImageBytes) { problems.push(f.name + ' is larger than ' + Math.round(maxImageBytes / 1048576) + ' MB.'); return; }
			if (files.length >= maxImages) { problems.push('At most ' + maxImages + ' images per message.'); return; }
			files.push(f);
		});
		filesInput.value = '';
		renderPreviews();
		setStatus(problems.join(' '), problems.length ? 'error' : '');
	});
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
	});
	input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 200) + 'px'; });
	input.addEventListener('paste', function (e) {
		// Screenshots pasted from the clipboard become attachments.
		var items = (e.clipboardData && e.clipboardData.items) || [];
		Array.prototype.forEach.call(items, function (it) {
			if (it.kind === 'file' && /^image\/(png|jpeg|webp)$/.test(it.type) && files.length < maxImages) {
				var f = it.getAsFile();
				if (f) { files.push(new File([f], 'pasted-' + Date.now() + '.' + it.type.split('/')[1], { type: it.type })); }
			}
		});
		renderPreviews();
	});
	form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
	document.getElementById('chat-new').addEventListener('click', function () { newConversation(); });

	var fromUrl = new URLSearchParams(location.search).get('conv');
	loadList().then(function () { if (fromUrl) { open(fromUrl); } });
})();
