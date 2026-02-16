<?php

declare(strict_types=1);

namespace VC\Nexudus\Cache;

final class CacheService {
	/**
	 * @return mixed
	 */
	public function get(string $key): mixed {
		return get_transient($this->prefix($key));
	}

	/**
	 * @param mixed $value
	 */
	public function set(string $key, mixed $value, int $ttl): bool {
		return set_transient($this->prefix($key), $value, $ttl);
	}

	public function delete(string $key): bool {
		return delete_transient($this->prefix($key));
	}

	public function clear_product_cache(): void {
		$this->delete('products_memberships');
		$this->delete('products_rooms');
		$this->delete('products_all');
	}

	private function prefix(string $key): string {
		return 'vc_nexudus_' . $key;
	}
}
