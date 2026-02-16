(function ($) {
	'use strict';

	$(document).on('submit', '.wbfsm-form', function () {
		var $button = $(this).find('button[type="submit"]');
		if ($button.length) {
			$button.prop('disabled', true);
			setTimeout(function () {
				$button.prop('disabled', false);
			}, 3000);
		}
	});

	$(document).on('change', '.wbfsm-select-all', function () {
		$('.wbfsm-select-product').prop('checked', $(this).is(':checked'));
	});

	$(document).on('submit', '.wbfsm-bulk-form', function (event) {
		if ($('.wbfsm-select-product:checked').length > 0) {
			return;
		}

		event.preventDefault();
		window.alert('Select at least one product to apply bulk changes.');
	});

	$(document).on('change', 'select[name="product_type"]', function () {
		var isVariable = $(this).val() === 'variable';
		$('.wbfsm-variation-blueprint').toggleClass('is-hidden', !isVariable);
	});

	$(document).on('click', '.wbfsm-add-attr-row', function () {
		var $rows = $(this).closest('.wbfsm-variation-blueprint').find('.wbfsm-variation-blueprint-rows');
		var html = [
			'<div class="wbfsm-variation-blueprint-row">',
			'<input type="text" name="variation_attr_name[]" placeholder="Attribute name (e.g. Size)" />',
			'<input type="text" name="variation_attr_values[]" placeholder="Values (e.g. s,m,l)" />',
			'<button type="button" class="wbfsm-btn wbfsm-btn-secondary wbfsm-remove-attr-row">Remove</button>',
			'</div>'
		].join('');
		$rows.append(html);
	});

	$(document).on('click', '.wbfsm-remove-attr-row', function () {
		var $rows = $(this).closest('.wbfsm-variation-blueprint-rows');
		if ($rows.find('.wbfsm-variation-blueprint-row').length <= 1) {
			$rows.find('input').val('');
			return;
		}
		$(this).closest('.wbfsm-variation-blueprint-row').remove();
	});
})(jQuery);
