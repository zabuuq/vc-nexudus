<?php

declare(strict_types=1);

namespace VC\Nexudus\Admin;

use VC\Nexudus\Services\ProductService;

final class SettingsPage {
	private ProductService $product_service;
	private string $option_key;
	private string $plugin_file;

	public function __construct(ProductService $product_service, string $option_key, string $plugin_file) {
		$this->product_service = $product_service;
		$this->option_key      = $option_key;
		$this->plugin_file     = $plugin_file;
	}

	public function register(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('wp_ajax_vc_nexudus_test_connection', [$this, 'ajax_test_connection']);
	}

	public function register_menu(): void {
		add_options_page(
			__('VC Nexudus Settings', 'vc-nexudus'),
			__('VC Nexudus', 'vc-nexudus'),
			'manage_options',
			'vc-nexudus',
			[$this, 'render_settings_page']
		);

		add_submenu_page(
			'options-general.php',
			__('VC Nexudus Product Browser', 'vc-nexudus'),
			__('VC Nexudus Products', 'vc-nexudus'),
			'manage_options',
			'vc-nexudus-products',
			[$this, 'render_products_page']
		);
	}

	public function register_settings(): void {
		register_setting($this->option_key, $this->option_key, [$this, 'sanitize_settings']);

		add_settings_section('vc_nexudus_auth', __('OAuth Settings', 'vc-nexudus'), null, 'vc-nexudus');
		add_settings_section('vc_nexudus_api', __('API Settings', 'vc-nexudus'), null, 'vc-nexudus');
		add_settings_section('vc_nexudus_cache', __('Cache Settings', 'vc-nexudus'), null, 'vc-nexudus');

		$fields = [
			'api_base_url'          => 'API Base URL',
			'oauth_token_url'       => 'OAuth Token URL',
			'oauth_client_id'       => 'OAuth Client ID',
			'oauth_client_secret'   => 'OAuth Client Secret',
			'oauth_grant_type'      => 'OAuth Grant Type',
			'oauth_scope'           => 'OAuth Scope',
			'memberships_endpoint'  => 'Memberships Endpoint Path',
			'rooms_endpoint'        => 'Room Bookings Endpoint Path',
			'connection_test_path'  => 'Connection Test Endpoint Path',
			'cache_ttl'             => 'Cache TTL (seconds)',
		];

		foreach ($fields as $key => $label) {
			$section = in_array($key, ['cache_ttl'], true) ? 'vc_nexudus_cache' : (str_starts_with($key, 'oauth_') ? 'vc_nexudus_auth' : 'vc_nexudus_api');
			add_settings_field(
				$key,
				__($label, 'vc-nexudus'),
				[$this, 'render_field'],
				'vc-nexudus',
				$section,
				['key' => $key]
			);
		}
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function sanitize_settings(array $settings): array {
		$sanitized = [];
		$sanitized['api_base_url']         = esc_url_raw((string) ($settings['api_base_url'] ?? ''));
		$sanitized['oauth_token_url']      = esc_url_raw((string) ($settings['oauth_token_url'] ?? ''));
		$sanitized['oauth_client_id']      = sanitize_text_field((string) ($settings['oauth_client_id'] ?? ''));
		$sanitized['oauth_client_secret']  = sanitize_text_field((string) ($settings['oauth_client_secret'] ?? ''));
		$sanitized['oauth_grant_type']     = sanitize_text_field((string) ($settings['oauth_grant_type'] ?? 'client_credentials'));
		$sanitized['oauth_scope']          = sanitize_text_field((string) ($settings['oauth_scope'] ?? ''));
		$sanitized['memberships_endpoint'] = sanitize_text_field((string) ($settings['memberships_endpoint'] ?? '/spaces/memberships'));
		$sanitized['rooms_endpoint']       = sanitize_text_field((string) ($settings['rooms_endpoint'] ?? '/spaces/rooms'));
		$sanitized['connection_test_path'] = sanitize_text_field((string) ($settings['connection_test_path'] ?? '/'));
		$sanitized['cache_ttl']            = absint($settings['cache_ttl'] ?? DAY_IN_SECONDS);
		return $sanitized;
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function render_field(array $args): void {
		$options = get_option($this->option_key, []);
		$key     = (string) ($args['key'] ?? '');
		$value   = is_array($options) && isset($options[$key]) ? (string) $options[$key] : '';
		$type    = 'oauth_client_secret' === $key ? 'password' : 'text';
		echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr($this->option_key) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" />';
	}

	public function render_settings_page(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['vc_nexudus_clear_cache_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vc_nexudus_clear_cache_nonce'])), 'vc_nexudus_clear_cache')) {
			$this->product_service->clear_cache();
			echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared.', 'vc-nexudus') . '</p></div>';
		}
		?>
		<div class="wrap vc-nexudus-settings-wrap">
			<h1><?php echo esc_html__('VC Nexudus Settings', 'vc-nexudus'); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields($this->option_key);
				do_settings_sections('vc-nexudus');
				submit_button(__('Save Settings', 'vc-nexudus'));
				?>
			</form>

			<p>
				<button class="button button-secondary" id="vc-nexudus-test-connection" data-nonce="<?php echo esc_attr(wp_create_nonce('vc_nexudus_test_connection')); ?>">
					<?php echo esc_html__('Test Connection', 'vc-nexudus'); ?>
				</button>
				<span id="vc-nexudus-test-result" aria-live="polite"></span>
			</p>

			<form method="post">
				<?php wp_nonce_field('vc_nexudus_clear_cache', 'vc_nexudus_clear_cache_nonce'); ?>
				<?php submit_button(__('Clear Cache', 'vc-nexudus'), 'secondary', 'submit', false); ?>
			</form>
		</div>
		<?php
	}

	public function render_products_page(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		$products = $this->product_service->get_products();
		?>
		<div class="wrap vc-nexudus-products-wrap">
			<h1><?php echo esc_html__('VC Nexudus Product Browser', 'vc-nexudus'); ?></h1>
			<p><?php echo esc_html__('Select products and copy shortcode for posts/pages.', 'vc-nexudus'); ?></p>
			<input type="search" id="vc-nexudus-product-search" placeholder="<?php echo esc_attr__('Search products', 'vc-nexudus'); ?>" class="regular-text" />
			<div id="vc-nexudus-product-list">
			<?php if (is_wp_error($products)) : ?>
				<p><?php echo esc_html__('Unable to load products. Check settings and API credentials.', 'vc-nexudus'); ?></p>
			<?php else : ?>
				<?php foreach ($products as $product) : ?>
					<label class="vc-nexudus-product-row" data-name="<?php echo esc_attr(strtolower((string) $product['name'])); ?>">
						<input type="checkbox" class="vc-nexudus-product-checkbox" value="<?php echo esc_attr((string) $product['id']); ?>" />
						<?php echo esc_html((string) $product['name']); ?>
						<small>(<?php echo esc_html((string) $product['type']); ?>)</small>
					</label><br />
				<?php endforeach; ?>
			<?php endif; ?>
			</div>
			<p>
				<label for="vc-nexudus-shortcode-output"><?php echo esc_html__('Generated shortcode', 'vc-nexudus'); ?></label><br />
				<input type="text" class="large-text" id="vc-nexudus-shortcode-output" readonly value="[vc_nexudus_products ids=\"\"]" />
			</p>
		</div>
		<?php
	}

	public function enqueue_assets(string $hook): void {
		if ('settings_page_vc-nexudus' !== $hook && 'settings_page_vc-nexudus-products' !== $hook) {
			return;
		}

		wp_enqueue_script(
			'vc-nexudus-admin',
			plugins_url('assets/js/admin-settings.js', $this->plugin_file),
			['jquery'],
			'0.1.0',
			true
		);
		wp_localize_script(
			'vc-nexudus-admin',
			'vcNexudusAdmin',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
			]
		);
	}

	public function ajax_test_connection(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Not allowed.', 'vc-nexudus')], 403);
		}

		check_ajax_referer('vc_nexudus_test_connection', 'nonce');

		$client = new \VC\Nexudus\Api\NexudusClient($this->option_key);
		$test   = $client->test_connection();

		if (is_wp_error($test)) {
			wp_send_json_error(['message' => $test->get_error_message()], 400);
		}

		wp_send_json_success(['message' => __('Connection successful.', 'vc-nexudus')]);
	}
}
