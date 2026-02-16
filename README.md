# VC Nexudus

WordPress plugin to fetch Nexudus memberships and room booking products, then render them with shortcodes or a dynamic Gutenberg block.

## Features

- OAuth-based Nexudus API integration (site-wide credentials)
- Admin settings screen with connection test and cache controls
- Product browser admin screen with searchable products and shortcode generator
- `[vc_nexudus_products]` and `[vc_nexudus_product]` shortcodes
- Dynamic Gutenberg block with product search/selection in inspector
- 24-hour transient caching with manual invalidation
- REST endpoint for editor product lookup

## Important note about API details

No verifiable information found.

The environment could not directly verify Nexudus docs endpoints/schemas from `developers.nexudus.com` due upstream access restrictions, so API endpoint paths and normalization are configurable and implemented behind adapters.

## Installation

1. Copy this plugin folder into `wp-content/plugins/vc-nexudus`.
2. Activate **VC Nexudus** in WordPress admin.
3. Go to **Settings → VC Nexudus** and provide:
   - API Base URL
   - OAuth Token URL
   - OAuth Client ID + Secret
   - Endpoint paths for memberships and room bookings
4. Save and click **Test Connection**.

## Usage

### Shortcodes

```text
[vc_nexudus_products ids="123,456" layout="grid" columns="3" show_price="1" show_description="0"]
[vc_nexudus_product id="123" show_price="1" show_description="1"]
```

### Gutenberg Block

Add **VC Nexudus Products** block and configure in inspector:
- Search products
- Select products
- Layout, columns, price, description toggles

## Development

- PHPCS: `phpcs`
- PHPStan: `phpstan analyse`

## TODO once docs are verified

- Validate real Nexudus OAuth token fields/scopes.
- Confirm memberships/rooms endpoint paths and pagination parameter names.
- Refine normalization mappings against official response schemas.
- Add rate-limit backoff behavior if headers/contracts are documented.
