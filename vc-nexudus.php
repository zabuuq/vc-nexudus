<?php
/**
 * Plugin Name: VC Nexudus
 * Plugin URI:  https://github.com/zabuuq/vc-nexudus
 * Description: Connects WordPress to Nexudus products for memberships and room bookings.
 * Version:     0.1.0
 * Author:      VC
 * License:     MIT
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Text Domain: vc-nexudus
 *
 * @package VC\Nexudus
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

spl_autoload_register(
	static function (string $class): void {
		$prefix = 'VC\\Nexudus\\';
		if (0 !== strpos($class, $prefix)) {
			return;
		}

		$relative = substr($class, strlen($prefix));
		$path     = plugin_dir_path(__FILE__) . 'src/' . str_replace('\\', '/', $relative) . '.php';

		if (file_exists($path)) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new VC\Nexudus\Plugin(__FILE__);
		$plugin->register();
	}
);
