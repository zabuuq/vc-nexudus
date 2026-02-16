<?php

declare(strict_types=1);

namespace VC\Nexudus\Services;

use VC\Nexudus\Api\Endpoints;
use VC\Nexudus\Api\NexudusClient;
use VC\Nexudus\Cache\CacheService;
use WP_Error;

final class ProductService {
	private NexudusClient $client;
	private CacheService $cache;
	private string $option_key;

	public function __construct(NexudusClient $client, CacheService $cache, string $option_key) {
		$this->client     = $client;
		$this->cache      = $cache;
		$this->option_key = $option_key;
	}

	/**
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_products(?string $type = null, bool $force_refresh = false): array|WP_Error {
		$cache_key = 'products_' . ($type ?: 'all');
		if (! $force_refresh) {
			$cached = $this->cache->get($cache_key);
			if (is_array($cached)) {
				return $cached;
			}
		}

		$settings = $this->get_settings();
		$ttl      = isset($settings['cache_ttl']) ? absint($settings['cache_ttl']) : DAY_IN_SECONDS;
		$items    = [];

		$types = $type ? [$type] : ['memberships', 'rooms'];
		foreach ($types as $current_type) {
			$path      = $this->get_endpoint_for_type($current_type, $settings);
			$fetched   = $this->fetch_paginated($path);
			if (is_wp_error($fetched)) {
				$fallback = $this->cache->get($cache_key);
				if (is_array($fallback)) {
					return $fallback;
				}

				if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					error_log('VC Nexudus product fetch error: ' . $fetched->get_error_code());
				}
				return $fetched;
			}

			foreach ($fetched as $raw_item) {
				if (is_array($raw_item)) {
					$items[] = $this->normalize_product($raw_item, $current_type);
				}
			}
		}

		$this->cache->set($cache_key, $items, $ttl);
		if (! $type) {
			$this->cache->set('products_memberships', array_values(array_filter($items, static fn(array $item): bool => 'memberships' === $item['type'])), $ttl);
			$this->cache->set('products_rooms', array_values(array_filter($items, static fn(array $item): bool => 'rooms' === $item['type'])), $ttl);
		}

		return $items;
	}

	public function clear_cache(): void {
		$this->cache->clear_product_cache();
	}

	/**
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function fetch_paginated(string $path): array|WP_Error {
		$page     = 1;
		$per_page = 100;
		$all      = [];

		while ($page <= 20) {
			$result = $this->client->request($path, ['page' => $page, 'page_size' => $per_page]);
			if (is_wp_error($result)) {
				return $result;
			}

			$items = $this->extract_items($result);
			if (empty($items)) {
				break;
			}

			$all = array_merge($all, $items);
			if (count($items) < $per_page) {
				break;
			}
			++$page;
		}

		return $all;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<int,array<string,mixed>>
	 */
	private function extract_items(array $payload): array {
		if (isset($payload['results']) && is_array($payload['results'])) {
			return array_values(array_filter($payload['results'], 'is_array'));
		}
		if (isset($payload['items']) && is_array($payload['items'])) {
			return array_values(array_filter($payload['items'], 'is_array'));
		}

		$all_arrays = array_filter($payload, 'is_array');
		if ([] !== $all_arrays && array_is_list($payload)) {
			return array_values(array_filter($payload, 'is_array'));
		}

		return [];
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	private function normalize_product(array $raw, string $type): array {
		$id = $raw['id'] ?? $raw['Id'] ?? $raw['ID'] ?? null;
		$id = is_scalar($id) ? (string) $id : '';

		$name = $raw['name'] ?? $raw['Name'] ?? $raw['title'] ?? '';
		$name = is_scalar($name) ? (string) $name : '';

		$description = $raw['description'] ?? $raw['Description'] ?? '';
		$description = is_scalar($description) ? (string) $description : '';

		$price = $raw['price'] ?? $raw['Price'] ?? null;
		$price = is_scalar($price) ? (string) $price : '';

		$image = $raw['imageUrl'] ?? $raw['image'] ?? $raw['Image'] ?? '';
		$image = is_scalar($image) ? (string) $image : '';

		$cta = $raw['ctaUrl'] ?? $raw['url'] ?? $raw['Url'] ?? '';
		$cta = is_scalar($cta) ? (string) $cta : '';

		return [
			'id'            => $id,
			'type'          => $type,
			'name'          => $name,
			'description'   => $description,
			'price'         => $price,
			'billingPeriod' => isset($raw['billingPeriod']) && is_scalar($raw['billingPeriod']) ? (string) $raw['billingPeriod'] : '',
			'imageUrl'      => $image,
			'ctaUrl'        => $cta,
			'availability'  => isset($raw['availability']) && is_scalar($raw['availability']) ? (string) $raw['availability'] : '',
			'metadata'      => $raw,
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function get_endpoint_for_type(string $type, array $settings): string {
		if ('rooms' === $type) {
			return (string) ($settings['rooms_endpoint'] ?? Endpoints::DEFAULT_ROOM_BOOKINGS_PATH);
		}
		return (string) ($settings['memberships_endpoint'] ?? Endpoints::DEFAULT_MEMBERSHIPS_PATH);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option($this->option_key, []);
		return is_array($settings) ? $settings : [];
	}
}
