# VC Nexudus – Agents Guide

This document is a concise guide for AI coding agents working on the **VC Nexudus** WordPress plugin.  It supplements the repository’s general README with step‑by‑step development instructions, conventions and boundaries specifically tailored for automated agents.  Follow this guide when interacting with the codebase to build or modify features, run tests and ensure quality.

## Purpose of the repository

The repository contains a WordPress plugin that integrates **Nexudus** coworking data into a WordPress site.  It fetches membership plans and bookable room/space products from Nexudus via OAuth‑protected APIs and exposes them through shortcodes and a dynamic Gutenberg block【464544480119729†L0-L13】.  An admin settings screen lets site owners configure API endpoints and credentials, test the connection and control caching【464544480119729†L20-L30】.  Products can be browsed in the dashboard with search and shortcode generation, and the plugin caches responses for 24 hours with manual invalidation【464544480119729†L9-L13】.

## Setup and development workflow

### Prerequisites

1. **PHP ≥ 8.0** – ensure the `php` CLI points to PHP 8.0 or newer.
2. **Composer** – install dependencies if a `composer.json` file exists (future dependency management).  Run `composer install` at the project root.
3. **WordPress 6.9 or later** – tests and development should be performed against the current supported WordPress version.
4. **Node/npm (optional)** – if build steps for assets are added later.

### Local plugin installation

1. Clone or copy this repository into your WordPress installation’s `wp-content/plugins/` directory.
2. Activate **VC Nexudus** in the WordPress admin panel.
3. Navigate to **Settings → VC Nexudus** and provide:
   - API base URL and token URL
   - OAuth client ID and secret
   - Endpoint paths for memberships and room bookings
   - Visibility or product filters as required
   Save and click **Test Connection**【464544480119729†L20-L30】.
4. Configure cache duration (default 24 hours) or clear caches via the settings screen.

### Dependency management

If the repository contains a `composer.json`, run:

```bash
composer install
```

to install development dependencies such as PHPUnit and PHP CodeSniffer.  Agents **must ask before adding new composer packages** to avoid unnecessary bloat.

### Building assets

There is currently no build step.  If JavaScript or CSS assets are introduced (e.g. compiling the Gutenberg block or admin scripts), document the build commands here:

```bash
npm install
npm run build
```

Agents should **not** invent build steps without explicit instruction.

## Running tests

### Installing the WordPress test suite

This project uses **PHPUnit** for unit and integration tests.  To run tests locally:

1. Ensure development dependencies are installed (run `composer install` if a composer file exists).
2. Install the WordPress test suite (one‑time setup):

   ```bash
   # From the plugin root
   mkdir -p tmp/wordpress-tests
   git clone --depth=1 https://github.com/WordPress/wordpress-develop.git tmp/wordpress
   bash tmp/wordpress/tools/local-env/scripts/install.sh --db_name=wp_test --db_user=root --skip-content
   ```

   Adjust credentials as needed.  Agents should ask before changing database names or credentials.

3. Copy or create a `phpunit.xml.dist` configuration file if one doesn’t exist.  A typical configuration defines the test suite and bootstrap (`includes/bootstrap.php`).

4. Run the test suite:

   ```bash
   # Run all tests
   vendor/bin/phpunit
   ```

5. Tests **must pass** before proposing changes.  The continuous integration workflow executes PHPUnit in the same way, so local failures will block CI.

### Generating a test scaffold

If the plugin doesn’t yet have a tests directory, use WP‑CLI to generate the WordPress plugin test scaffold.  This command creates a `phpunit.xml.dist` configuration, an installation script, and a sample test file【366895289477893†L75-L85】:

```bash
# Replace <plugin-slug> with the plugin directory name
wp scaffold plugin-tests <plugin-slug>
```

By default this produces:

- `phpunit.xml.dist` – PHPUnit configuration【366895289477893†L75-L85】
- `bin/install-wp-tests.sh` – script to configure the WordPress test suite and test database【366895289477893†L75-L85】
- `tests/bootstrap.php` – bootstraps WordPress and activates the plugin during tests【366895289477893†L75-L85】
- `tests/test-sample.php` – sample test file【366895289477893†L75-L85】
- `.phpcs.xml.dist` – default PHP CodeSniffer ruleset【366895289477893†L75-L85】

After scaffolding, run `bin/install-wp-tests.sh` with appropriate database credentials and then execute PHPUnit as described above.

### Code coverage

To ensure adequate test coverage, run PHPUnit with coverage reporting and enforce a minimum threshold (e.g. 80 percent):

```bash
vendor/bin/phpunit --coverage-text --colors=always --min-coverage=80
```

If coverage falls below the threshold, write additional tests or discuss with the maintainer before lowering the requirement.

### Linting with php‑lint

Syntax errors are checked on every pull request.  Agents should run a recursive syntax check before committing:

```bash
# Lint all PHP files except vendor
find . -type f -name '*.php' -not -path '*/vendor/*' -print0 \
    | xargs -0 -n1 php -d display_errors=1 -l
```

Fix any parse errors reported.  **Do not bypass lint checks**.

### Code style – PSR‑12 + WordPress Coding Standards

This project adheres to both the **PSR‑12 Extended Coding Style** and the **WordPress Coding Standards (WPCS)**.

*PSR‑12* builds on PSR‑2 and PSR‑1 to provide a unified set of formatting rules for modern PHP【384879273501839†L20-L31】.  It reduces cognitive friction when scanning code from different authors and enumerates a shared set of rules and expectations【384879273501839†L20-L31】.  Important PSR‑12 requirements include using UNIX line endings, omitting closing `?>` tags on pure PHP files, adhering to a soft 120‑character line limit and indenting with four spaces【384879273501839†L93-L118】.

*WPCS* augments PSR‑12 by adding WordPress‑specific guidance on naming, translation and security.  These standards are not merely stylistic; they encompass best practices for interoperability, translatability and security【718042543619882†L110-L120】.  Key points include:

- **Full PHP tags** – always use `<?php ... ?>`, never shorthand `<? ... ?>`【718042543619882†L164-L177】.
- **Naming conventions** – functions and variables should use lowercase with underscores; classes use PascalCase with underscores as separators【718042543619882†L217-L233】.  File names should be descriptive, lowercase and hyphenated【718042543619882†L243-L247】.
- **Indentation and whitespace** – indent with four spaces and avoid trailing whitespace; wrap lines at 120 characters and avoid more than one statement per line【384879273501839†L93-L118】.
- **Includes** – use `require_once` for unconditional includes and avoid parentheses around paths【718042543619882†L192-L204】.
- **Internationalization** – wrap all user‑facing strings with translation functions like `__()` or `esc_html__()`, and load text domains appropriately.
- **Security** – always validate, sanitize and escape input/output.  Use nonces and capability checks on all POST/GET actions.

### Running style checks

Use PHP CodeSniffer with both standards:

```bash
vendor/bin/phpcs --standard=PSR12,WordPress --extensions=php --ignore=vendor/* .
```

Address any reported violations.  Agents should **not** ignore or suppress sniff errors without a justification.

### Static analysis

Run **PHPStan** for static type checking:

```bash
vendor/bin/phpstan analyse --memory-limit=1G --no-interaction
```

Aim for level 5 or higher; fix reported issues or discuss with maintainers.  Static analysis helps catch dead code, type mismatches and potential bugs early.

### Composer validation and security scanning

Validate the `composer.json` file to ensure it is well‑formed:

```bash
composer validate --strict
```

Run a security audit of dependencies:

```bash
composer audit
```

Address any reported vulnerabilities before committing changes.

## Boundaries and permissions

- **Do not commit secrets** – API keys, access tokens and credentials must never be stored in the repository.  Use environment variables or WordPress options instead.
- **Ask before adding dependencies** – adding composer packages, npm modules or external libraries requires confirmation from a maintainer.
- **No destructive actions** – do not delete data, change user roles or alter external systems without explicit instruction.
- **Do not modify CI workflows** – GitHub Actions or other CI files should not be changed unless the task specifically involves them.
- **Respect version constraints** – keep PHP ≥ 8.0 and WordPress ≥ 6.9 requirements unless asked otherwise.
- **Avoid external network calls in tests** – tests should mock HTTP requests to Nexudus; do not call live APIs during test runs.

## Common pitfalls

- **API throttling and caching** – Nexudus imposes request limits; always use the plugin’s caching layer (transients) to avoid exceeding quotas and causing performance issues【40591203841889†L34-L38】.  Use webhook‑driven cache invalidation where supported【40591203841889†L39-L43】.
- **Authentication** – use the recommended OAuth2 password grant to obtain a bearer token via `POST /api/token` with a dedicated service user【40591203841889†L16-L29】.  Do not hardcode credentials; expose them via the settings page.
- **Least privilege** – assign only the minimal roles necessary (e.g. `Resource-List`, `Booking-List`, `Tariff-*`)【40591203841889†L141-L148】.
- **Multiple API surfaces** – Nexudus provides REST, Public and Marketplace APIs【40591203841889†L9-L12】.  Abstract API calls via the `Api\NexudusClient` class and do not embed endpoint paths throughout the codebase【464544480119729†L14-L18】.
* **Race conditions and caching** – be cautious when invalidating caches based on webhooks; ensure concurrency is handled so that no stale data is served.
- **Direct SQL queries** – avoid writing raw SQL; use WordPress database APIs (`$wpdb`) or high‑level functions like `WP_Query`.
- **Unsanitized output** – always escape output with functions like `esc_html()`, `esc_attr()` or `wp_kses_post()`.
- **Missing nonces and capability checks** – verify user capabilities (e.g., `current_user_can()`) and include nonces for form submissions.
- **Hard‑coding file paths** – use WordPress constants like `ABSPATH` and `plugin_dir_path( __FILE__ )`.
- **Overusing global variables** – encapsulate functionality in classes or functions and minimize global scope usage.
- **Ignoring caching** – when pulling social media feeds, cache API responses using transients or the object cache to reduce rate‑limit issues.

## WordPress‑specific guidance

- **Plugin structure** – the main plugin file (`vc-nexudus.php`) should include the plugin header and load other files via `require_once` calls.  Keep one class or interface per file【718042543619882†L215-L237】.
* **Autoloading** – classes under the `VC\Nexudus` namespace are autoloaded via a PSR‑4‑style function.  When adding new classes, place them in `src/` with the corresponding namespace and update the autoloader if necessary.
* **Settings page** – configuration data is stored under the option key `vc_nexudus_settings`.  When adding settings, sanitize and validate inputs.  Expose fields via the Settings API and REST endpoints.
* **Shortcodes and blocks** – shortcodes `[vc_nexudus_products]` and `[vc_nexudus_product]` accept attributes like IDs, layout, columns, price and description toggles【464544480119729†L35-L38】.  The Gutenberg block uses inspector controls for product selection and layout【464544480119729†L40-L45】.  Maintain backward compatibility when adjusting attribute names or defaults.
* **Caching** – the cache service uses transients to store API responses for 24 hours by default【464544480119729†L9-L13】.  Provide filters to adjust expiry and make caches group‑aware (e.g., per endpoint).  Invalidate caches upon webhook events.
* **REST routes** – the plugin registers a REST route for product lookup in the editor【464544480119729†L11-L13】.  Namespace routes appropriately and include permission callbacks.

## AI‑focused guidance

This file is written for AI coding agents such as ChatGPT, Copilot, Cursor and other automated code assistants.  When generating or modifying code in this repository:

1. **Respect the architecture** – use the existing class structure (`Api`, `Cache`, `Rest`, `Services`, `Shortcodes`, `Blocks`, etc.) and avoid monolithic functions.  Create new classes or interfaces only when needed.
2. **Avoid side effects** – do not execute plugin code on file load; hook into WordPress actions like `plugins_loaded` or `init`.
3. **Write deterministic tests** – tests should be self‑contained, not reliant on external API responses.  Use mocks for HTTP calls and caches.
4. **Document your changes** – include inline comments and PHPDoc blocks with `@since`, `@param` and `@return` tags.  Use WordPress‑style docblocks.
5. **Keep changes minimal** – modify only the files relevant to the task.  Do not refactor unrelated code unless instructed.
6. **Seek clarification** – if a task requires ambiguous changes or contradicts these guidelines, ask for guidance rather than guessing.

## Commit message conventions

Use clear, standardized commit messages so that humans and tooling can understand your changes.  Follow these conventions:

- **Structure** – Commit messages consist of a subject line, an optional blank line, and a body.  Keep the subject line to **50 characters** for readability and wrap body lines at **72 characters**【715518113923408†L101-L105】.
- **Conventional Commits** – Prefix the subject with a type and optional scope, using the format `type(scope): description`【715518113923408†L115-L129】.  Common types include:
  - `feat` – adding a new feature
  - `fix` – fixing a bug
  - `docs` – documentation changes
  - `test` – adding or improving tests
  - `build`/`ci` – build or CI configuration changes
  - `refactor` – code changes that neither fix bugs nor add features
  - `perf` – performance improvements
  - `style` – formatting, linting or stylistic changes
- **Imperative mood** – Write the description as if completing the sentence “If applied, this commit will…”.  Examples: `fix(api): handle null API response`; `feat(plugin): add caching to social feeds`.  This helps maintain a clear narrative【715518113923408†L210-L232】.
- **Atomic changes** – Each commit should represent a single logical change.  Avoid bundling unrelated fixes, refactors and features into one commit【715518113923408†L202-L207】.  If work spans multiple concerns, split it into separate commits.
- **Explain the “what” and “why”** – The body of the commit message should provide context about the intent and outcome of the change【715518113923408†L210-L224】.  Include references to issues or tasks when applicable, and avoid stating only the obvious.

Following these conventions improves readability and enables tooling to automate changelog generation, semantic versioning and CI pipelines【715518113923408†L132-L136】.

## Testing and continuous integration

The project is configured with GitHub Actions to guard every pull request.  The workflow runs multiple checks that you should mirror locally to avoid CI failures:

- **PHP linting** – every PHP file is parsed using `php -l` to detect syntax errors.  Use the lint command from the **Linting with php‑lint** section before committing.
- **PHPUnit execution** – the full test suite is executed with coverage enforcement using `--min-coverage=80`.  All tests must pass with at least 80 % coverage.
- **Static analysis (PHPStan)** – the codebase is analyzed at a configured level.  Fix any reported issues or discuss exceptions with maintainers.
- **Coding standards (PHPCS)** – code is checked against PSR‑12 and WordPress standards.  See the **Code style** section for the command.
- **WordPress test scaffold** – the workflow ensures the presence of a valid test scaffold generated via `wp scaffold plugin-tests`.  Do not remove or rename scaffold files without maintaining the CI configuration.
- **WordPress plugin test suite** – `bin/install-wp-tests.sh` is executed to install WordPress and the plugin into a test database, then the PHPUnit suite is run.  Ensure this script runs without errors on your machine.
- **Composer validation** – `composer validate --strict` verifies the syntax and structure of `composer.json`.  Do not commit invalid Composer manifests.
- **Security scanning** – dependencies are scanned for known vulnerabilities using `composer audit` or similar tools.  Resolve flagged issues promptly.
- **Pull request guardrails** – merging to the main branch is blocked until all checks pass, the required reviewers approve, and commit messages follow the conventions described above.

Agents should run these checks locally and address failures before opening a pull request.  When adding new tests or tools (e.g. new static analysis rules), update CI configurations as appropriate and communicate changes to maintainers.

## Conclusion

By following this guide, AI agents can interact with the **VC Nexudus** codebase safely and effectively.  Stick to the WordPress Coding Standards and make sure that tests and lint checks pass before proposing changes.  When in doubt, prioritize security, readability and maintainability, and seek clarification from a maintainer.