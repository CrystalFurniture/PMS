// Review screen for an imported customer requirement (AiReview.php).
// All data is rendered with textContent / DOM APIs: customer text and product
// data are never inserted as HTML. Nothing here changes the cart except the
// final "Confirm and add" step, which the server validates again.
(function () {
	'use strict';

	var state = JSON.parse(document.getElementById('ai-state').textContent);
	var root = document.getElementById('ai-review');
	var messages = document.getElementById('ai-messages');
	var approved = {};      // row_id -> rev approved by the employee
	var busy = false;

	function csrf() {
		var el = document.getElementById('qt_csrf');
		return el ? el.value : '';
	}

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		attrs = attrs || {};
		Object.keys(attrs).forEach(function (k) {
			if (k === 'text') { node.textContent = attrs[k]; }
			else if (k === 'class') { node.className = attrs[k]; }
			else if (k.indexOf('on') === 0) { node.addEventListener(k.substring(2), attrs[k]); }
			else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) { node.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
		});
		(children || []).forEach(function (c) {
			if (c === null || c === undefined) { return; }
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}

	function message(text, kind) {
		messages.innerHTML = '';
		if (text) {
			messages.appendChild(el('div', { 'class': 'alert alert-' + (kind || 'warning'), role: 'alert', text: text }));
			window.scrollTo(0, 0);
		}
	}

	function post(action, fields) {
		var body = new FormData();
		body.append('action', action);
		body.append('draft', state.draft);
		body.append('csrf_token', csrf());
		Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
		return fetch('ai_import.php', { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) {
				return res.json().catch(function () { return { success: false, error: 'The server returned an unexpected response (HTTP ' + res.status + ').' }; })
					.then(function (data) {
						if (res.status === 401) { window.location.href = 'index.php'; }
						return data;
					});
			})
			.catch(function () {
				return { success: false, error: 'The connection was interrupted. Your earlier changes are saved; reload the page to continue.' };
			});
	}

	function rowById(id) {
		for (var i = 0; i < state.rows.length; i++) {
			if (state.rows[i].row_id === id) { return i; }
		}
		return -1;
	}

	function replaceRow(view) {
		var i = rowById(view.row_id);
		if (i >= 0) { state.rows[i] = view; }
		delete approved[view.row_id];
		renderTable();
	}

	function updateRow(row, fields) {
		if (busy) {
			message('Still saving the previous change. Try again in a moment.', 'info');
			renderTable();
			return;
		}
		busy = true;
		fields.row_id = row.row_id;
		fields.rev = row.rev;
		post('update_row', fields).then(function (res) {
			busy = false;
			if (res.success) { message(''); replaceRow(res.row); }
			else { message(res.error); renderTable(); }
		});
	}

	function canApprove(row) {
		return !row.removed && row.quantity !== null && row.quantity_checks.length === 0 && row.product !== null
			&& (!row.zero_price || row.zero_price_confirmed);
	}

	function approvalHint(row) {
		if (row.quantity === null) { return 'Enter a quantity'; }
		if (row.quantity_checks.length > 0) { return 'Confirm the quantity'; }
		if (row.product !== null && row.zero_price && !row.zero_price_confirmed) { return 'Confirm the ₹0 price'; }
		return 'Choose a product';
	}

	function productBox(p, extra) {
		return el('div', { 'class': 'clearfix' }, [
			p.thumb ? el('img', { 'class': 'ai-thumb', src: p.thumb, alt: '', onerror: function () { this.style.display = 'none'; } }) : null,
			el('strong', { text: p.name }), ' ',
			el('span', { 'class': 'text-muted', text: '#' + p.id }), el('br'),
			el('span', { text: 'Colour: ' + (p.color || 'not listed') }), el('br'),
			el('span', { text: 'Size: ' + (p.dimension || 'not listed') }), el('br'),
			el('span', { text: state.type + ' price: ₹ ' + p.price }),
			extra || null
		]);
	}

	function searchControl(row) {
		var input = el('input', { type: 'text', 'class': 'form-control input-sm', placeholder: 'Search catalogue (name, code, size)' });
		var results = el('div', { 'class': 'ai-search-results', style: 'display:none' });
		function run() {
			if (!input.value.trim()) { return; }
			post('search', { q: input.value }).then(function (res) {
				results.innerHTML = '';
				results.style.display = '';
				if (!res.success) { results.appendChild(el('div', { 'class': 'text-danger', text: res.error })); return; }
				if (res.products.length === 0) { results.appendChild(el('div', { 'class': 'text-muted', text: 'No quotable products found.' })); return; }
				res.products.forEach(function (p) {
					results.appendChild(el('div', { 'class': 'ai-choice', title: 'Select this product', onclick: function () { updateRow(row, { product_id: p.id }); } }, [productBox(p)]));
				});
			});
		}
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
		return el('div', { style: 'margin-top:6px' }, [
			el('div', { 'class': 'input-group input-group-sm' }, [input,
				el('span', { 'class': 'input-group-btn' }, [el('button', { type: 'button', 'class': 'btn btn-default', text: 'Search', onclick: run })])]),
			results
		]);
	}

	function renderRow(row, index) {
		var chosen = row.product !== null && row.product_source === 'user';
		var statusKey = chosen ? 'chosen' : row.status;
		var statusText = { suggested: 'Suggested', needs_review: 'Needs review', unmatched: 'Unmatched', chosen: 'Chosen by you' }[statusKey] || statusKey;

		// Requirement (editable) + original wording
		var req = el('textarea', { 'class': 'form-control input-sm', rows: 2, disabled: row.removed });
		req.value = row.requirement;
		req.addEventListener('change', function () { updateRow(row, { requirement: req.value }); });
		var reqCell = el('td', {}, [
			el('div', { 'class': 'ai-original', text: 'Row ' + (index + 1) + ' · ' + row.source_ref + (row.original_text ? ': “' + row.original_text + '”' : '') }),
			req,
			row.specs ? el('div', { 'class': 'ai-original', text: 'Stated: ' + row.specs }) : null,
			row.quantity_text ? el('div', { 'class': 'ai-original', text: 'Quantity as written: ' + row.quantity_text + (row.unit ? ' (' + row.unit + ')' : '') }) : null,
			row.notes ? el('div', { 'class': 'ai-original', text: 'Note: ' + row.notes }) : null,
			row.applied_instructions ? el('div', { 'class': 'ai-original', style: 'color:#31708f', text: 'Staff instruction applied: ' + row.applied_instructions }) : null,
			row.basis ? el('div', { 'class': 'ai-original', text: (row.basis.source === 'correction' ? 'Matched on the reading: ' : 'Matched on the words as typed: ') + row.basis.description }) : null,
			el('button', { type: 'button', 'class': 'btn btn-default btn-xs', style: 'margin-top:4px', disabled: row.removed, text: 'Rematch', onclick: function () {
				if (busy) { return; }
				busy = true;
				message('Matching row ' + (index + 1) + '…', 'info');
				post('rematch', { row_id: row.row_id, rev: row.rev }).then(function (res) {
					busy = false;
					if (res.success) { message(res.warning || ''); replaceRow(res.row); } else { message(res.error); }
				});
			} })
		]);

		// Quantity
		var qty = el('input', { type: 'number', min: 1, max: state.max_qty, step: 1, 'class': 'form-control input-sm', style: 'width:100%;max-width:80px', disabled: row.removed });
		qty.value = row.quantity === null ? '' : row.quantity;
		qty.addEventListener('change', function () { updateRow(row, { quantity: qty.value }); });
		var qtyCell = el('td', {}, [qty]);
		if (row.quantity !== null && row.quantity_checks.length > 0 && !row.removed) {
			qtyCell.appendChild(el('button', { type: 'button', 'class': 'btn btn-warning btn-xs', style: 'margin-top:4px', text: 'Confirm ' + row.quantity,
				title: 'The quantity was read as ' + row.quantity + '. Confirm it matches the customer request, or type the correct number.',
				onclick: function () { updateRow(row, { quantity: String(row.quantity) }); } }));
		}

		// Product
		var productCell = el('td', {}, [el('span', { 'class': 'ai-status ai-status-' + statusKey, text: statusText }), ' ']);
		if (row.reason) { productCell.appendChild(el('div', { 'class': 'ai-original', text: row.reason })); }
		if (row.product) {
			productCell.appendChild(productBox(row.product, el('div', {}, [
				el('small', { 'class': 'text-muted', text: row.product_source === 'user' ? 'Chosen by you' : 'AI suggestion' }), ' ',
				el('button', { type: 'button', 'class': 'btn btn-link btn-xs', text: 'Clear', disabled: row.removed, onclick: function () { updateRow(row, { product_id: '' }); } })
			])));
			if (row.zero_price && !row.zero_price_confirmed) {
				productCell.appendChild(el('button', { type: 'button', 'class': 'btn btn-warning btn-xs ai-confirm-zero', style: 'margin-top:4px', disabled: row.removed,
					text: 'Confirm ₹0 price', title: 'This product has no price in the price list. Confirm only if it should be quoted at ₹0.',
					onclick: function () {
						if (window.confirm('"' + row.product.name + '" has no price in the price list. Add it at ₹0?')) {
							updateRow(row, { confirm_zero_price: '1' });
						}
					} }));
			}
		} else {
			productCell.appendChild(el('div', { 'class': 'text-danger', text: 'No product selected' }));
		}
		var others = row.alternatives.filter(function (p) { return !row.product || p.id !== row.product.id; });
		if (others.length && !row.removed) {
			productCell.appendChild(el('div', { 'class': 'ai-original', style: 'margin-top:6px', text: 'Possible matches (click to choose):' }));
			others.forEach(function (p) {
				productCell.appendChild(el('div', { 'class': 'ai-choice', onclick: function () { updateRow(row, { product_id: p.id }); } },
					[productBox(p, p.id === row.ai_suggested_id ? el('div', { 'class': 'ai-original', text: 'AI’s closest pick' }) : null)]));
			});
		}
		if (!row.removed) { productCell.appendChild(searchControl(row)); }

		// Warnings
		var warnCell = el('td', {});
		row.warnings.forEach(function (w) { warnCell.appendChild(el('div', { 'class': 'ai-warn', text: '⚠ ' + w })); });

		// Approval
		var box = el('input', { type: 'checkbox', disabled: !canApprove(row) || !!state.context_error });
		box.checked = approved[row.row_id] === row.rev;
		box.addEventListener('change', function () {
			if (box.checked) { approved[row.row_id] = row.rev; } else { delete approved[row.row_id]; }
			renderFooter();
		});
		var approveCell = el('td', {}, [
			el('label', { style: 'font-weight:normal' }, [box, ' Approve']),
			!canApprove(row) && !row.removed ? el('div', { 'class': 'ai-original', text: approvalHint(row) }) : null,
			el('button', { type: 'button', 'class': 'btn btn-link btn-xs', text: row.removed ? 'Restore row' : 'Remove row',
				onclick: function () { updateRow(row, { removed: row.removed ? '0' : '1' }); } })
		]);

		return el('tr', { 'class': row.removed ? 'ai-row-removed' : '', 'data-row': row.row_id },
			[reqCell, qtyCell, productCell, warnCell, approveCell]);
	}

	var tableHolder = el('div', {});
	var footer = el('div', { style: 'margin-top:10px' });
	var planHolder = el('div', {});

	function renderTable() {
		tableHolder.innerHTML = '';
		if (state.rows.length === 0) {
			tableHolder.appendChild(el('p', { text: 'No items were found in this requirement. Check the source on the left and add products manually from the product list.' }));
		} else {
			var tbody = el('tbody', {}, state.rows.map(renderRow));
			tableHolder.appendChild(el('table', { 'class': 'table table-bordered ai-review-table' }, [
				el('thead', {}, [el('tr', {}, ['Requirement', 'Quantity', 'Catalogue product', 'Warnings', 'Approval'].map(function (h) { return el('th', { text: h }); }))]),
				tbody
			]));
		}
		renderFooter();
	}

	function approvals() {
		return state.rows.filter(function (r) { return approved[r.row_id] === r.rev && canApprove(r); }).map(function (r) {
			return { row_id: r.row_id, rev: r.rev, product_id: r.product.id, quantity: r.quantity, price: r.product.price };
		});
	}

	function renderFooter() {
		footer.innerHTML = '';
		planHolder.innerHTML = '';
		var n = approvals().length;
		footer.appendChild(el('button', { type: 'button', 'class': 'btn btn-primary', disabled: n === 0 || !!state.context_error,
			text: 'Add approved items (' + n + ')', onclick: preview }));
		footer.appendChild(el('span', { 'class': 'text-muted', style: 'margin-left:10px',
			text: 'Only approved rows are added. Prices come from the database; the quotation is saved later from the cart as usual.' }));
	}

	function preview() {
		if (busy) { return; }
		busy = true;
		post('preview', { approvals: JSON.stringify(approvals()) }).then(function (res) {
			busy = false;
			if (!res.success) {
				if (res.code === 'price_changed') {
					var lines = [];
					(res.price_changes || []).forEach(function (c) {
						var i = rowById(c.row_id);
						if (i >= 0 && state.rows[i].product) {
							state.rows[i].product.price = c.current_price;
							lines.push('Row ' + (i + 1) + ': ₹ ' + c.reviewed_price + ' → ₹ ' + c.current_price);
						}
						delete approved[c.row_id];
					});
					renderTable();
					message(res.error + ' ' + lines.join('; '));
				} else {
					message(res.error);
				}
				return;
			}
			message('');
			showPlan(res.plan, res.plan_token);
		});
	}

	function showPlan(plan, token) {
		planHolder.innerHTML = '';
		var decisions = {};
		var box = el('div', { 'class': 'box box-primary', style: 'margin-top:12px' }, [
			el('div', { 'class': 'box-header' }, [el('h3', { 'class': 'box-title', text: 'Check before adding to the cart' })])
		]);
		var body = el('div', { 'class': 'box-body' });
		plan.items.forEach(function (it) {
			var d = decisions[it.product_id] = {};
			var item = el('div', { 'class': 'ai-plan-item' }, [
				el('strong', { text: it.name }), el('span', { 'class': 'text-muted', text: ' #' + it.product_id + ' · ' + (it.color || '') + ' · ' + (it.dimension || '') }), el('br'),
				el('span', { text: 'Quantity: ' + (it.rows.length > 1 ? it.rows.map(function (r) { return r.quantity; }).join(' + ') + ' = ' : '') + it.quantity + ' at ₹ ' + it.price })
			]);
			function radios(name, options) {
				var group = el('div', {});
				options.forEach(function (o) {
					var input = el('input', { type: 'radio', name: name + '-' + it.product_id, value: o[0] });
					input.addEventListener('change', function () { d[name] = o[0]; });
					group.appendChild(el('label', { style: 'font-weight:normal; margin-right:14px' }, [input, ' ' + o[1]]));
				});
				return group;
			}
			if (it.rows.length > 1) {
				item.appendChild(el('div', { 'class': 'ai-warn', text: it.rows.length + ' rows chose this product. The cart keeps one line per product.' }));
				if (it.variant_note) { item.appendChild(el('div', { 'class': 'ai-warn', text: it.variant_note })); }
				item.appendChild(radios('repeat', [['combine', 'Combine into one line (' + it.quantity + ')'], ['first', 'Use only the first row (' + it.rows[0].quantity + ')']]));
			}
			if (it.existing_quantity !== null) {
				item.appendChild(el('div', { 'class': 'ai-warn', text: 'Already in the cart: quantity ' + it.existing_quantity + ' at ₹ ' + it.existing_price + ' (the cart line keeps its price).' }));
				item.appendChild(radios('existing', [['add', 'Add to the cart quantity'], ['replace', 'Replace the cart quantity'], ['skip', 'Leave the cart line unchanged']]));
			}
			body.appendChild(item);
		});
		var confirm = el('button', { type: 'button', 'class': 'btn btn-success', text: 'Confirm and add to cart', onclick: function () {
			if (busy) { return; }
			for (var i = 0; i < plan.items.length; i++) {
				var it = plan.items[i], d = decisions[it.product_id];
				if ((it.rows.length > 1 && !d.repeat) || (it.existing_quantity !== null && !d.existing)) {
					message('Choose how to handle "' + it.name + '" before adding.');
					return;
				}
			}
			busy = true;
			confirm.disabled = true;
			post('apply', { plan_token: token, decisions: JSON.stringify(decisions) }).then(function (res) {
				if (res.success) { window.location.href = res.redirect; return; }
				busy = false;
				confirm.disabled = false;
				message(res.error);
			});
		} });
		body.appendChild(el('div', { style: 'margin-top:10px' }, [confirm, ' ',
			el('button', { type: 'button', 'class': 'btn btn-default', text: 'Back to review', onclick: function () { planHolder.innerHTML = ''; } })]));
		box.appendChild(body);
		planHolder.appendChild(box);
		planHolder.scrollIntoView();
	}

	function renderStatus() {
		root.innerHTML = '';
		if (state.status === 'processing') {
			root.appendChild(el('p', { text: 'Reading the requirement… this page refreshes automatically.' }));
			setTimeout(function () { window.location.reload(); }, 4000);
			return;
		}
		if (state.status === 'applied') {
			root.appendChild(el('p', {}, ['These items were added to the cart. ', el('a', { href: state.cart_page, text: 'Open the cart' })]));
			return;
		}
		if (state.status !== 'ready') {
			var text = state.status === 'interrupted' ? 'Reading this requirement was interrupted.'
				: (state.error || 'This requirement has not been read yet.');
			root.appendChild(el('div', { 'class': 'alert alert-warning', text: text + ' You can retry, or add products manually from the product list.' }));
			var retry = el('button', { type: 'button', 'class': 'btn btn-primary', text: 'Retry reading', onclick: function () {
				retry.disabled = true;
				retry.textContent = 'Reading…';
				post('process', {}).then(function (res) {
					if (res.success) { window.location.reload(); return; }
					retry.disabled = false;
					retry.textContent = 'Retry reading';
					message(res.error);
				});
			} });
			root.appendChild(retry);
			return;
		}
		if (state.context_error) {
			root.appendChild(el('div', { 'class': 'alert alert-danger', text: state.context_error }));
		}
		if (state.document_notes) {
			root.appendChild(el('div', { 'class': 'alert alert-info', text: 'Notes on the source: ' + state.document_notes }));
		}
		root.appendChild(el('p', { 'class': 'text-muted', text: 'Check every row against the source. Matches are suggestions from catalogue data, not confirmations. Rows need a quantity and a product before they can be approved.' }));
		root.appendChild(tableHolder);
		root.appendChild(footer);
		root.appendChild(planHolder);
		renderTable();
	}

	document.getElementById('ai-discard').addEventListener('click', function () {
		if (!confirm('Discard this import? Nothing has been added to the cart.')) { return; }
		post('discard', {}).then(function (res) {
			if (res.success) { window.location.href = res.redirect; } else { message(res.error); }
		});
	});

	renderStatus();
}());
