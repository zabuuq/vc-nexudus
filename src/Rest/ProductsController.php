<?php

declare(strict_types=1);

namespace VC\Nexudus\Rest;

use VC\Nexudus\Services\ProductService;
use WP_REST_Request;
use WP_REST_Response;

final class ProductsController {
	private ProductService $product_service;

	public function __construct(ProductService $product_service) {
		$this->product_service = $product_service;
	}

	public function register(): void {
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes(): void {
		register_rest_route(
			'vc-nexudus/v1',
			'/products',
			[
				'methods'             => 'GET',
				'callback'            => [$this, 'get_products'],
				'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
				'args'                => [
					'search' => ['type' => 'string', 'required' => false],
					'type'   => ['type' => 'string', 'required' => false],
				],
			]
		);
	}

	public function get_products(WP_REST_Request $request): WP_REST_Response {
		$type     = $request->get_param('type');
		$products = $this->product_service->get_products(is_string($type) ? $type : null);
		if (is_wp_error($products)) {
			return new WP_REST_Response(['message' => __('Unable to load products.', 'vc-nexudus')], 400);
		}

		$search = strtolower((string) $request->get_param('search'));
		if ('' !== $search) {
			$products = array_values(
				array_filter(
					$products,
					static fn(array $item): bool => str_contains(strtolower((string) ($item['name'] ?? '')), $search)
				)
			);
		}

		return new WP_REST_Response($products, 200);
	}
}
