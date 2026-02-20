(function ($) {
	'use strict';

	function updateStatus(status) {
		if (!status) {
			return;
		}

		$('#vc-nexudus-connection-state').text(status.connected ? 'Connected' : 'Not connected');
		$('#vc-nexudus-token-expiry').text(status.expires_at ? new Date(status.expires_at * 1000).toLocaleString() : 'Unknown');
		$('#vc-nexudus-last-refresh').text(status.last_refresh_at ? new Date(status.last_refresh_at * 1000).toLocaleString() : 'Never');
	}

	function setResult(message) {
		$('#vc-nexudus-oauth-result').text(message || '');
	}

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

	$(document).on('click', '#vc-nexudus-open-connect-modal', function (event) {
		event.preventDefault();
		$('#vc-nexudus-connect-modal').removeAttr('hidden');
	});

	$(document).on('click', '#vc-nexudus-connect-cancel', function (event) {
		event.preventDefault();
		$('#vc-nexudus-connect-modal').attr('hidden', 'hidden');
	});

	$(document).on('click', '#vc-nexudus-connect-submit', function (event) {
		event.preventDefault();
		setResult('Connecting...');

		$.post(vcNexudusAdmin.ajaxUrl, {
			action: 'vc_nexudus_connect_oauth',
			nonce: vcNexudusAdmin.connectNonce,
			username: $('#vc-nexudus-username').val(),
			password: $('#vc-nexudus-password').val()
		}).done(function (response) {
			if (response.success) {
				setResult(response.data.message);
				updateStatus(response.data.status);
				$('#vc-nexudus-connect-modal').attr('hidden', 'hidden');
				$('#vc-nexudus-password').val('');
			} else {
				setResult(response.data.message || 'Unable to connect.');
			}
		}).fail(function () {
			setResult('Unable to connect.');
		});
	});

	$(document).on('click', '#vc-nexudus-refresh-token', function (event) {
		event.preventDefault();
		setResult('Refreshing token...');

		$.post(vcNexudusAdmin.ajaxUrl, {
			action: 'vc_nexudus_refresh_oauth',
			nonce: vcNexudusAdmin.refreshNonce
		}).done(function (response) {
			if (response.success) {
				setResult(response.data.message);
				updateStatus(response.data.status);
			} else {
				setResult(response.data.message || 'Refresh failed.');
			}
		}).fail(function () {
			setResult('Refresh failed.');
		});
	});

	$(document).on('click', '#vc-nexudus-disconnect', function (event) {
		event.preventDefault();
		setResult('Disconnecting...');

		$.post(vcNexudusAdmin.ajaxUrl, {
			action: 'vc_nexudus_disconnect_oauth',
			nonce: vcNexudusAdmin.disconnectNonce
		}).done(function (response) {
			if (response.success) {
				setResult(response.data.message);
				updateStatus(response.data.status);
			} else {
				setResult(response.data.message || 'Disconnect failed.');
			}
		}).fail(function () {
			setResult('Disconnect failed.');
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
