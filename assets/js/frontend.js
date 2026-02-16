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
})(jQuery);
