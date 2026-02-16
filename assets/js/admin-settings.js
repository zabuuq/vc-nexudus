(function ($) {
	'use strict';

	$(document).on('click', '#vc-nexudus-test-connection', function () {
		var $button = $(this);
		var nonce = $button.data('nonce');
		var $result = $('#vc-nexudus-test-result');
		$result.text('Testing...');

		$.post(vcNexudusAdmin.ajaxUrl, {
			action: 'vc_nexudus_test_connection',
			nonce: nonce
		}).done(function (response) {
			if (response.success) {
				$result.text(response.data.message);
			} else {
				$result.text(response.data.message || 'Connection failed.');
			}
		}).fail(function () {
			$result.text('Connection failed.');
		});
	});

	function updateShortcode() {
		var ids = [];
		$('.vc-nexudus-product-checkbox:checked').each(function () {
			ids.push($(this).val());
		});
		$('#vc-nexudus-shortcode-output').val('[vc_nexudus_products ids="' + ids.join(',') + '" layout="grid" columns="3"]');
	}

	$(document).on('change', '.vc-nexudus-product-checkbox', updateShortcode);
	$(document).on('input', '#vc-nexudus-product-search', function () {
		var query = $(this).val().toLowerCase();
		$('.vc-nexudus-product-row').each(function () {
			var $row = $(this);
			$row.toggle($row.data('name').indexOf(query) !== -1);
		});
	});
})(jQuery);
