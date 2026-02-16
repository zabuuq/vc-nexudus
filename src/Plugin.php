<?php

declare(strict_types=1);

namespace VC\Nexudus;

use VC\Nexudus\Admin\SettingsPage;
use VC\Nexudus\Api\NexudusClient;
use VC\Nexudus\Blocks\ProductsBlock;
use VC\Nexudus\Cache\CacheService;
use VC\Nexudus\Rest\ProductsController;
use VC\Nexudus\Services\ProductService;
use VC\Nexudus\Shortcodes\ProductsShortcode;

final class Plugin {
	public const OPTION_KEY = 'vc_nexudus_settings';

	private string $plugin_file;

	public function __construct(string $plugin_file) {
		$this->plugin_file = $plugin_file;
	}

	public function register(): void {
		$cache_service  = new CacheService();
		$client         = new NexudusClient(self::OPTION_KEY);
		$product_service = new ProductService($client, $cache_service, self::OPTION_KEY);

		$settings_page = new SettingsPage($product_service, self::OPTION_KEY, $this->plugin_file);
		$settings_page->register();

		$shortcodes = new ProductsShortcode($product_service, $this->plugin_file);
		$shortcodes->register();

		$rest_controller = new ProductsController($product_service);
		$rest_controller->register();

		$block = new ProductsBlock($product_service, $this->plugin_file);
		$block->register();
	}
}
