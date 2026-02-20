<?php

declare(strict_types=1);

namespace VC\Nexudus\Admin;

use VC\Nexudus\Api\NexudusClient;
use VC\Nexudus\Auth\TokenManager;
use VC\Nexudus\Services\ProductService;

final class SettingsPage {
	private ProductService $product_service;
	private TokenManager $token_manager;
	private string $option_key;
	private string $plugin_file;

	public function __construct(ProductService $product_service, TokenManager $token_manager, string $option_key, string $plugin_file) {
		$this->product_service = $product_service;
		$this->token_manager   = $token_manager;
		$this->option_key      = $option_key;
		$this->plugin_file     = $plugin_file;
	}

	public function register(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('wp_ajax_vc_nexudus_test_connection', [$this, 'ajax_test_connection']);
		add_action('wp_ajax_vc_nexudus_connect_oauth', [$this, 'ajax_connect_oauth']);
		add_action('wp_ajax_vc_nexudus_refresh_oauth', [$this, 'ajax_refresh_oauth']);
		add_action('wp_ajax_vc_nexudus_disconnect_oauth', [$this, 'ajax_disconnect_oauth']);
	}

	public function register_menu(): void {
		add_menu_page(
			__('VC Nexudus Settings', 'vc-nexudus'),
			__('VC Nexudus', 'vc-nexudus'),
			'manage_options',
			'vc-nexudus',
			[$this, 'render_settings_page'],
			'dashicons-admin-generic'
		);

		add_submenu_page(
			'vc-nexudus',
			__('VC Nexudus Product Browser', 'vc-nexudus'),
			__('VC Nexudus Products', 'vc-nexudus'),
			'manage_options',
			'vc-nexudus-products',
			[$this, 'render_products_page']
		);
	}

	public function register_settings(): void {
		register_setting($this->option_key, $this->option_key, [$this, 'sanitize_settings']);

		add_settings_section('vc_nexudus_api', __('Nexudus API Settings', 'vc-nexudus'), null, 'vc-nexudus');
		add_settings_section('vc_nexudus_oauth', __('OAuth Settings', 'vc-nexudus'), null, 'vc-nexudus');
		add_settings_section('vc_nexudus_cache', __('Cache Settings', 'vc-nexudus'), null, 'vc-nexudus');

		$fields = [
			'tenant_base_url'      => ['label' => 'Base Nexudus Tenant URL', 'section' => 'vc_nexudus_api'],
			'oauth_token_url'      => ['label' => 'OAuth Token URL', 'section' => 'vc_nexudus_oauth'],
			'oauth_client_id_header' => ['label' => 'OAuth Client ID Header', 'section' => 'vc_nexudus_oauth'],
			'memberships_endpoint' => ['label' => 'Memberships Endpoint Path', 'section' => 'vc_nexudus_api'],
			'rooms_endpoint'       => ['label' => 'Room Bookings Endpoint Path', 'section' => 'vc_nexudus_api'],
			'connection_test_path' => ['label' => 'Connection Test Endpoint Path', 'section' => 'vc_nexudus_api'],
			'cache_ttl'            => ['label' => 'Cache TTL (seconds)', 'section' => 'vc_nexudus_cache'],
		];

		foreach ($fields as $key => $field) {
			add_settings_field(
				$key,
				__($field['label'], 'vc-nexudus'),
				[$this, 'render_field'],
				'vc-nexudus',
				$field['section'],
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
		$sanitized['tenant_base_url']      = esc_url_raw((string) ($settings['tenant_base_url'] ?? ''));
		$sanitized['oauth_token_url']      = esc_url_raw((string) ($settings['oauth_token_url'] ?? 'https://spaces.nexudus.com/api/token'));
		$sanitized['oauth_client_id_header'] = sanitize_text_field((string) ($settings['oauth_client_id_header'] ?? wp_generate_uuid4()));
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
		echo '<input type="text" class="regular-text" name="' . esc_attr($this->option_key) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" />';

		if ('oauth_client_id_header' === $key) {
			echo '<p class="description">' . esc_html__('Required for refresh. Keep this stable for this site connection.', 'vc-nexudus') . '</p>';
		}
	}

	public function render_settings_page(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['vc_nexudus_clear_cache_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vc_nexudus_clear_cache_nonce'])), 'vc_nexudus_clear_cache')) {
			$this->product_service->clear_cache();
			echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared.', 'vc-nexudus') . '</p></div>';
		}

		$status = $this->token_manager->get_status();
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

			<hr />
			<h2><?php echo esc_html__('Nexudus OAuth Connection', 'vc-nexudus'); ?></h2>
			<p><strong><?php echo esc_html__('Status:', 'vc-nexudus'); ?></strong> <span id="vc-nexudus-connection-state"><?php echo ! empty($status['connected']) ? esc_html__('Connected', 'vc-nexudus') : esc_html__('Not connected', 'vc-nexudus'); ?></span></p>
			<p><strong><?php echo esc_html__('Token expiry:', 'vc-nexudus'); ?></strong> <span id="vc-nexudus-token-expiry"><?php echo ! empty($status['expires_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $status['expires_at'])) : esc_html__('Unknown', 'vc-nexudus'); ?></span></p>
			<p><strong><?php echo esc_html__('Last refresh:', 'vc-nexudus'); ?></strong> <span id="vc-nexudus-last-refresh"><?php echo ! empty($status['last_refresh_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $status['last_refresh_at'])) : esc_html__('Never', 'vc-nexudus'); ?></span></p>

			<p>
				<button class="button button-primary" id="vc-nexudus-open-connect-modal"><?php echo esc_html__('Connect to Nexudus', 'vc-nexudus'); ?></button>
				<button class="button button-secondary" id="vc-nexudus-refresh-token"><?php echo esc_html__('Refresh token now', 'vc-nexudus'); ?></button>
				<button class="button button-secondary" id="vc-nexudus-disconnect"><?php echo esc_html__('Disconnect', 'vc-nexudus'); ?></button>
			</p>
			<p id="vc-nexudus-oauth-result" aria-live="polite"></p>

			<div id="vc-nexudus-connect-modal" class="vc-nexudus-modal" hidden>
				<div class="vc-nexudus-modal-content">
					<h3><?php echo esc_html__('Connect to Nexudus', 'vc-nexudus'); ?></h3>
					<p><?php echo esc_html__('Your username and password are not stored in WordPress. They are only used to initiate a secure connection to Nexudus and request OAuth tokens.', 'vc-nexudus'); ?></p>
					<p>
						<label for="vc-nexudus-username"><?php echo esc_html__('Username', 'vc-nexudus'); ?></label><br />
						<input type="text" id="vc-nexudus-username" class="regular-text" autocomplete="username" />
					</p>
					<p>
						<label for="vc-nexudus-password"><?php echo esc_html__('Password', 'vc-nexudus'); ?></label><br />
						<input type="password" id="vc-nexudus-password" class="regular-text" autocomplete="current-password" />
					</p>
					<p>
						<button class="button button-primary" id="vc-nexudus-connect-submit"><?php echo esc_html__('Connect', 'vc-nexudus'); ?></button>
						<button class="button" id="vc-nexudus-connect-cancel"><?php echo esc_html__('Cancel', 'vc-nexudus'); ?></button>
					</p>
				</div>
			</div>

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
		if ('toplevel_page_vc-nexudus' !== $hook && 'vc-nexudus_page_vc-nexudus-products' !== $hook) {
			return;
		}

		wp_enqueue_style('vc-nexudus-admin-style', plugins_url('assets/css/admin-settings.css', $this->plugin_file), [], '0.1.0');
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
				'ajaxUrl'          => admin_url('admin-ajax.php'),
				'connectNonce'     => wp_create_nonce('vc_nexudus_connect_oauth'),
				'refreshNonce'     => wp_create_nonce('vc_nexudus_refresh_oauth'),
				'disconnectNonce'  => wp_create_nonce('vc_nexudus_disconnect_oauth'),
			]
		);
	}

	public function ajax_test_connection(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Not allowed.', 'vc-nexudus')], 403);
		}

		check_ajax_referer('vc_nexudus_test_connection', 'nonce');

		$client = new NexudusClient($this->option_key, $this->token_manager);
		$test   = $client->test_connection();

		if (is_wp_error($test)) {
			wp_send_json_error(['message' => $test->get_error_message()], 400);
		}

		wp_send_json_success(['message' => __('Connection successful.', 'vc-nexudus')]);
	}

	public function ajax_connect_oauth(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Not allowed.', 'vc-nexudus')], 403);
		}

		check_ajax_referer('vc_nexudus_connect_oauth', 'nonce');

		$username = sanitize_text_field((string) wp_unslash($_POST['username'] ?? ''));
		$password = (string) wp_unslash($_POST['password'] ?? '');
		if ('' === $username || '' === $password) {
			wp_send_json_error(['message' => __('Username and password are required.', 'vc-nexudus')], 400);
		}

		$connected = $this->token_manager->connect($username, $password);
		if (is_wp_error($connected)) {
			wp_send_json_error(['message' => __('Unable to connect to Nexudus. Check credentials and try again.', 'vc-nexudus')], 400);
		}

		wp_send_json_success([
			'message' => __('Connected to Nexudus.', 'vc-nexudus'),
			'status'  => $this->token_manager->get_status(),
		]);
	}

	public function ajax_refresh_oauth(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Not allowed.', 'vc-nexudus')], 403);
		}

		check_ajax_referer('vc_nexudus_refresh_oauth', 'nonce');

		$refreshed = $this->token_manager->refresh_tokens();
		if (is_wp_error($refreshed)) {
			wp_send_json_error(['message' => $refreshed->get_error_message()], 400);
		}

		wp_send_json_success([
			'message' => __('Token refresh successful.', 'vc-nexudus'),
			'status'  => $this->token_manager->get_status(),
		]);
	}

	public function ajax_disconnect_oauth(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Not allowed.', 'vc-nexudus')], 403);
		}

		check_ajax_referer('vc_nexudus_disconnect_oauth', 'nonce');
		$this->token_manager->disconnect();
		wp_send_json_success([
			'message' => __('Disconnected from Nexudus.', 'vc-nexudus'),
			'status'  => $this->token_manager->get_status(),
		]);
	}
}
