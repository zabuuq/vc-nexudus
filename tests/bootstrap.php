<?php

declare(strict_types=1);

if (! class_exists('WP_Error')) {
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct(string $code = '', string $message = '') {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if (! function_exists('__')) {
	function __(string $text): string {
		return $text;
	}
}

if (! function_exists('wp_json_encode')) {
	function wp_json_encode($value, int $flags = 0): string|false {
		return json_encode($value, $flags);
	}
}

if (! function_exists('absint')) {
	function absint($value): int {
		return abs((int) $value);
	}
}

if (! function_exists('is_wp_error')) {
	function is_wp_error($thing): bool {
		return $thing instanceof WP_Error;
	}
}

$GLOBALS['vc_nexudus_test_options'] = [];
$GLOBALS['vc_nexudus_mock_post_response'] = null;
$GLOBALS['vc_nexudus_last_remote_post'] = null;

if (! function_exists('get_option')) {
	function get_option(string $key, $default = false) {
		return $GLOBALS['vc_nexudus_test_options'][$key] ?? $default;
	}
}

if (! function_exists('update_option')) {
	function update_option(string $key, $value): bool {
		$GLOBALS['vc_nexudus_test_options'][$key] = $value;
		return true;
	}
}

if (! function_exists('delete_option')) {
	function delete_option(string $key): bool {
		unset($GLOBALS['vc_nexudus_test_options'][$key]);
		return true;
	}
}

if (! function_exists('wp_remote_post')) {
	function wp_remote_post(string $url, array $args = []) {
		$GLOBALS['vc_nexudus_last_remote_post'] = ['url' => $url, 'args' => $args];
		return $GLOBALS['vc_nexudus_mock_post_response'];
	}
}

if (! function_exists('wp_remote_retrieve_response_code')) {
	function wp_remote_retrieve_response_code($response): int {
		if (! is_array($response)) {
			return 0;
		}
		return (int) ($response['response']['code'] ?? 0);
	}
}

if (! function_exists('wp_remote_retrieve_body')) {
	function wp_remote_retrieve_body($response): string {
		if (! is_array($response)) {
			return '';
		}
		return (string) ($response['body'] ?? '');
	}
}

spl_autoload_register(
	static function (string $class): void {
		$prefix = 'VC\\Nexudus\\';
		if (0 !== strpos($class, $prefix)) {
			return;
		}

		$relative = substr($class, strlen($prefix));
		$path     = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
		if (file_exists($path)) {
			require_once $path;
		}
	}
);
