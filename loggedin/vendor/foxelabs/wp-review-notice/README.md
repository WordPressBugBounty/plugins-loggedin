# WP Review Notice

[![Tests](https://github.com/foxelabs/wp-review-notice/actions/workflows/tests.yml/badge.svg)](https://github.com/foxelabs/wp-review-notice/actions/workflows/tests.yml)
[![PHPCS](https://github.com/foxelabs/wp-review-notice/actions/workflows/phpcs.yml/badge.svg)](https://github.com/foxelabs/wp-review-notice/actions/workflows/phpcs.yml)

A small, opinionated WordPress library that gently asks for a wp.org plugin review after a few days of usage. Built around a tiny set of focused, swappable collaborators so it stays trivially testable and easy to extend.

📖 **Full documentation:** [docs.foxelabs.com](https://docs.foxelabs.com/software/wp-libraries/wp-review-notice/overview)

## Installation

```bash
composer require foxelabs/wp-review-notice
```

Requires **PHP 7.4+** and WordPress **6.0+**.

## Quick start

```php
add_action( 'plugins_loaded', function () {
    \FoxeLabs\Reviews\Notice::create(
        'my-plugin', // wp.org plugin slug (e.g. "hello-dolly").
        'My Plugin', // Display name shown in the notice copy.
        array(
            'days'    => 7,
            'cap'     => 'manage_options',
            'screens' => array( 'dashboard', 'plugins' ), // empty = all admin screens.
        )
    )->register();
} );
```

`register()` is what hooks `admin_notices` + `admin_init` and seeds the show-time schedule. Calling `create()` without `register()` does nothing — useful for tests or for configuring a notice you want to render manually.

## Options

| Key             | Type     | Default               | Description                                                              |
| --------------- | -------- | --------------------- | ------------------------------------------------------------------------ |
| `days`          | `int`    | `7`                   | Days to wait before showing the notice for the first time.               |
| `screens`       | `array`  | `[]`                  | Allowed admin screen IDs. Empty = every admin screen.                    |
| `cap`           | `string` | `manage_options`      | Capability required to see and act on the notice.                        |
| `classes`       | `array`  | `[]`                  | Extra CSS classes appended to the `notice notice-info` wrapper.          |
| `message`       | `string` | `''` (auto-generated) | Custom HTML message. Not escaped — sanitise it yourself.                 |
| `action_labels` | `array`  | bundled labels        | Keys: `review`, `later`, `dismiss`. Set any to `''` to hide that link.   |
| `prefix`        | `string` | slug with `-` → `_`   | Storage namespace. Keys are written as `{prefix}_review_{key}`.          |

## Filters

```php
add_filter( 'foxelabs_reviews_notice_message', function ( $message, $days ) {
    return "We're glad you've been with us for {$days}+ days!";
}, 10, 2 );
```

## Architecture

The library is split into small collaborators, each behind an interface:

```
Notice (facade, DI container)
 ├── KeyPrefixer           — "{prefix}_review_{key}" namespacing
 ├── TimerStoreInterface   — when the next show is due  (default: SiteOptionTimerStore)
 ├── DismissalStoreInterface — per-user dismissal flag  (default: UserMetaDismissalStore)
 ├── ScreenResolverInterface  — current admin screen check
 ├── CapabilityCheckerInterface — capability gate
 ├── ActionRouter          — handles later/dismiss GET dispatch
 └── RendererInterface     — emits the notice HTML       (default: DefaultRenderer)
```

Every collaborator is constructor-injectable, so tests (and unusual integrations) can swap any single piece without forking the library.

### Custom storage / rendering

```php
use FoxeLabs\Reviews\Notice;
use FoxeLabs\Reviews\Support\Config;

$notice = new Notice(
    Config::fromArray( 'my-plugin', 'My Plugin' ),
    new MyRedisTimerStore(),       // implements TimerStoreInterface
    null,                          // default user-meta dismissal
    null,                          // default admin-screen resolver
    null,                          // default capability checker
    new MyBlockEditorRenderer()    // implements RendererInterface
);
$notice->register();
```

## Upgrading from v1

v1 → v3 is a clean break — there is no compatibility shim.

> [!WARNING]
> 2.0.0 renamed both storage keys (`{prefix}_reviews_*` → `{prefix}_review_*`), and the library does not migrate them
> for you. Host plugins must move the data themselves, or the timer reseeds and every admin — including those who
> already dismissed — is prompted again. The 3.0.0 rebrand itself renames no keys; upgrading from 2.x needs no data
> migration.

See [Migrating from 1.x](https://docs.foxelabs.com/software/wp-libraries/wp-review-notice/installation#migrating-from-1-x)
for the migration recipe, including the ordering trap around `register()` seeding the new timer key.

| v1                                              | v3                                                          |
| ----------------------------------------------- | ----------------------------------------------------------- |
| `Notice::get( $slug, $name, $opts )`            | `Notice::create( $slug, $name, $opts )->register()`         |
| Constructor auto-registered on `is_admin()`     | `register()` is explicit                                    |
| Storage keys `{prefix}_reviews_time` etc.       | `{prefix}_review_time`, `{prefix}_review_dismissed`, `{prefix}_review_action` |
| Message echoed raw                              | Message run through `wp_kses_post()`                        |
| `is_time()` mutated storage                     | `isDue()` is a pure read; `start()` seeds on `register()`   |
| `DuckDev\Reviews\`                              | `FoxeLabs\Reviews\`                                         |
| `duckdev_reviews_notice_message` filter         | `foxelabs_reviews_notice_message`                           |

## Development

```bash
composer install
composer test    # PHPUnit
composer phpcs   # WPCS lint
```

CI runs PHPUnit against PHP 7.4 – 8.3 and WPCS on every push.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
