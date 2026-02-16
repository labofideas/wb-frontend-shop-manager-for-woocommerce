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
})(jQuery);
