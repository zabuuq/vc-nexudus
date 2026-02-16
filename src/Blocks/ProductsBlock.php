<?php

declare(strict_types=1);

namespace VC\Nexudus\Blocks;

use VC\Nexudus\Services\ProductService;

final class ProductsBlock {
	private ProductService $product_service;
	private string $plugin_file;

	public function __construct(ProductService $product_service, string $plugin_file) {
		$this->product_service = $product_service;
		$this->plugin_file     = $plugin_file;
	}

	public function register(): void {
		add_action('init', [$this, 'register_block']);
	}

	public function register_block(): void {
		register_block_type(
			plugin_dir_path($this->plugin_file) . 'build',
			[
				'render_callback' => [$this, 'render_block'],
			]
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_block(array $attributes): string {
		$ids              = isset($attributes['ids']) && is_array($attributes['ids']) ? array_map('strval', $attributes['ids']) : [];
		$layout           = isset($attributes['layout']) ? (string) $attributes['layout'] : 'grid';
		$columns          = isset($attributes['columns']) ? absint($attributes['columns']) : 3;
		$show_price       = ! empty($attributes['showPrice']);
		$show_description = ! empty($attributes['showDescription']);

		$shortcode = sprintf(
			'[vc_nexudus_products ids="%s" layout="%s" columns="%d" show_price="%d" show_description="%d"]',
			esc_attr(implode(',', $ids)),
			esc_attr($layout),
			$columns,
			$show_price ? 1 : 0,
			$show_description ? 1 : 0
		);

		return do_shortcode($shortcode);
	}
}
