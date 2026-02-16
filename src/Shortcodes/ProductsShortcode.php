<?php

declare(strict_types=1);

namespace VC\Nexudus\Shortcodes;

use VC\Nexudus\Services\ProductService;

final class ProductsShortcode {
	private ProductService $product_service;
	private string $plugin_file;
	private bool $assets_enqueued = false;

	public function __construct(ProductService $product_service, string $plugin_file) {
		$this->product_service = $product_service;
		$this->plugin_file     = $plugin_file;
	}

	public function register(): void {
		add_shortcode('vc_nexudus_products', [$this, 'render_products']);
		add_shortcode('vc_nexudus_product', [$this, 'render_product']);
	}

	/**
	 * @param array<string,string> $atts
	 */
	public function render_products(array $atts = []): string {
		$atts = shortcode_atts(
			[
				'ids'              => '',
				'layout'           => 'grid',
				'columns'          => '3',
				'show_price'       => '1',
				'show_description' => '0',
			],
			$atts,
			'vc_nexudus_products'
		);

		$ids      = array_filter(array_map('trim', explode(',', (string) $atts['ids'])));
		$products = $this->product_service->get_products();
		if (is_wp_error($products)) {
			return '<div class="vc-nexudus-error">' . esc_html__('Products are currently unavailable.', 'vc-nexudus') . '</div>';
		}

		if ([] !== $ids) {
			$products = array_values(array_filter($products, static fn(array $product): bool => in_array((string) $product['id'], $ids, true)));
		}

		if ([] === $products) {
			return '<div class="vc-nexudus-empty">' . esc_html__('No products found for this selection.', 'vc-nexudus') . '</div>';
		}

		$this->enqueue_assets();

		$layout  = 'list' === $atts['layout'] ? 'list' : 'grid';
		$columns = max(1, min(4, absint($atts['columns'])));
		$html    = '<div class="vc-nexudus-products vc-nexudus-layout-' . esc_attr($layout) . ' vc-nexudus-columns-' . esc_attr((string) $columns) . '">';

		foreach ($products as $product) {
			$html .= $this->render_card($product, '1' === $atts['show_price'], '1' === $atts['show_description']);
		}

		$html .= '</div>';
		return (string) apply_filters('vc_nexudus_products_html', $html, $products, $atts);
	}

	/**
	 * @param array<string,string> $atts
	 */
	public function render_product(array $atts = []): string {
		$atts = shortcode_atts(['id' => '', 'show_price' => '1', 'show_description' => '1'], $atts, 'vc_nexudus_product');
		if ('' === $atts['id']) {
			return '';
		}

		$products = $this->product_service->get_products();
		if (is_wp_error($products)) {
			return '<div class="vc-nexudus-error">' . esc_html__('Product unavailable.', 'vc-nexudus') . '</div>';
		}

		foreach ($products as $product) {
			if ((string) $product['id'] === $atts['id']) {
				$this->enqueue_assets();
				return '<div class="vc-nexudus-products vc-nexudus-layout-single">' . $this->render_card($product, '1' === $atts['show_price'], '1' === $atts['show_description']) . '</div>';
			}
		}

		return '<div class="vc-nexudus-empty">' . esc_html__('Product not found.', 'vc-nexudus') . '</div>';
	}

	/**
	 * @param array<string,mixed> $product
	 */
	private function render_card(array $product, bool $show_price, bool $show_description): string {
		$cta = (string) ($product['ctaUrl'] ?? '');
		if ('' === $cta) {
			$cta = '#';
		}

		$html  = '<article class="vc-nexudus-card">';
		$html .= '<h3>' . esc_html((string) $product['name']) . '</h3>';
		$html .= '<p class="vc-nexudus-type">' . esc_html(ucfirst((string) $product['type'])) . '</p>';
		if ($show_description && '' !== (string) $product['description']) {
			$html .= '<div class="vc-nexudus-description">' . wp_kses_post(wpautop((string) $product['description'])) . '</div>';
		}
		if ($show_price && '' !== (string) $product['price']) {
			$html .= '<p class="vc-nexudus-price">' . esc_html((string) $product['price']) . '</p>';
		}
		$html .= '<a class="vc-nexudus-cta" href="' . esc_url($cta) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View on Nexudus', 'vc-nexudus') . '</a>';
		$html .= '</article>';
		return $html;
	}

	private function enqueue_assets(): void {
		if ($this->assets_enqueued) {
			return;
		}
		$this->assets_enqueued = true;
		wp_enqueue_style('vc-nexudus-frontend', plugins_url('assets/css/frontend.css', $this->plugin_file), [], '0.1.0');
	}
}
