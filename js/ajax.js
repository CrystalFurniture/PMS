// Quotation cart actions. Every request carries the page's CSRF token (#qt_csrf).

function qtCsrf() {
	return $('#qt_csrf').val() || '';
}

function qtAjaxError(xhr) {
	var message = 'Something went wrong. Please reload the page and try again.';
	if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
		message = xhr.responseJSON.error;
	}
	alert(message);
	if (xhr && xhr.status === 401) {
		window.location.href = 'index.php';
	}
}

function qtShowMessage(html) {
	var box = $('#messages');
	box.html(html);
	clearTimeout(box.data('qtTimer'));
	box.data('qtTimer', setTimeout(function () { box.children('.alert').fadeOut(300); }, 5000));
}

function qtAddProduct(button, action, confirmZero) {
	// Create screens with the cart panel: JSON reply refreshes the panel in place.
	if ($('#qt-cart-summary').length) {
		$(button).prop('disabled', true);
		$.ajax({
			url: "action.php",
			method: "POST",
			dataType: "json",
			data: {
				product_id: $(button).attr("id"),
				action: action,
				view: 'summary',
				confirm_zero: confirmZero ? '1' : '',
				csrf_token: qtCsrf()
			},
			success: function (data) {
				// Pilot rule: a 0 price is added only after the employee confirms it.
				if (data.needs_confirm) {
					if (window.confirm(data.confirm_text)) {
						qtAddProduct(button, action, true);
					}
					return;
				}
				qtShowMessage(data.message);
				$('#qt-cart-summary').html(data.summary);
				if (data.success) {
					$(button).addClass('is-added').text('Added');
				}
			},
			error: qtAjaxError,
			complete: function () { $(button).prop('disabled', false); }
		});
		return;
	}
	$.ajax({
		url: "action.php",
		method: "POST",
		dataType: "html",
		data: {
			product_id: $(button).attr("id"),
			action: action,
			csrf_token: qtCsrf()
		},
		success: function (data) {
			$('#messages').html(data);
		},
		error: qtAjaxError
	});
}

function qtCartRequest(data, tableSelector, tableKey) {
	data.csrf_token = qtCsrf();
	$.ajax({
		url: "action.php",
		method: "POST",
		dataType: "json",
		data: data,
		success: function (response) {
			$(tableSelector).html(response[tableKey]);
			if (response.error) {
				alert(response.error);
			}
		},
		error: qtAjaxError
	});
}

// Add Product DP
$(document).on('click', '.add_dp', function () {
	qtAddProduct(this, "add_dp");
});

// Add Product MRP
$(document).on('click', '.add_mrp', function () {
	qtAddProduct(this, "add_mrp");
});

// Quantity Change DP
$(document).on('change', '.quantityDP', function () {
	if ($(this).val() !== '') {
		qtCartRequest({ line: $(this).data("line"), quantity: $(this).val(), action: "quantityDP_change" }, '#order_table', 'order_table');
	}
});

// Remove Product DP
$(document).on('click', '.delete', function () {
	if (!confirm("Are you sure you want to remove this product?")) {
		return false;
	}
	qtCartRequest({ line: $(this).data("line"), action: "removeDP" }, '#order_table', 'order_table');
});

// Quantity Change MRP
$(document).on('change', '.quantityMRP', function () {
	if ($(this).val() !== '') {
		qtCartRequest({ line: $(this).data("line"), quantity: $(this).val(), action: "quantityMRP_change" }, '#order_tableMRP', 'order_tableMRP');
	}
});

// Remove Product MRP
$(document).on('click', '.deleteMRP', function () {
	if (!confirm("Are you sure you want to remove this product?")) {
		return false;
	}
	qtCartRequest({ line: $(this).data("line"), action: "removeMRP" }, '#order_tableMRP', 'order_tableMRP');
});

// Cart panel on the create screens (#qt-cart-summary): quantity -/+, typed quantity, remove.
function qtSummaryRequest(data) {
	var type = $('#qt-cart-summary').data('type');
	data.csrf_token = qtCsrf();
	data.action = data.kind === 'remove' ? ('remove' + type) : ('quantity' + type + '_change');
	delete data.kind;
	$.ajax({
		url: "action.php",
		method: "POST",
		dataType: "json",
		data: data,
		success: function (response) {
			$('#qt-cart-summary').html(response.summary);
			if (response.error) {
				alert(response.error);
			}
			qtSyncAddButtons();
		},
		error: qtAjaxError
	});
}

// Products removed from the panel get their "Add" label back.
function qtSyncAddButtons() {
	var inCart = {};
	$('#qt-cart-summary .qt-sum-remove').each(function () { inCart[String($(this).data('line'))] = true; });
	$('.pms-add-btn.is-added').each(function () {
		if (!inCart[String(this.id)]) {
			$(this).removeClass('is-added').text('Add');
		}
	});
}

$(document).on('click', '#qt-cart-summary .qt-sum-qty', function () {
	var line = $(this).data('line');
	var input = $('#qt-cart-summary .qt-sum-qty-input').filter(function () { return String($(this).data('line')) === String(line); });
	var qty = parseInt(input.val(), 10) || 1;
	var next = Math.min(Math.max(qty + parseInt($(this).data('step'), 10), 1), 9999);
	if (next !== qty) {
		qtSummaryRequest({ kind: 'quantity', line: line, quantity: next });
	}
});

$(document).on('change', '#qt-cart-summary .qt-sum-qty-input', function () {
	if ($(this).val() !== '') {
		qtSummaryRequest({ kind: 'quantity', line: $(this).data('line'), quantity: $(this).val() });
	}
});

$(document).on('click', '#qt-cart-summary .qt-sum-remove', function () {
	if (!confirm("Remove this product from the quotation?")) {
		return false;
	}
	qtSummaryRequest({ kind: 'remove', line: $(this).data('line') });
});
