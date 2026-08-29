<?php
/**
 * Freemius container / library entry point.
 *
 * The {@see Freemius} class is the single class host plugins
 * interact with directly. It wires together every collaborator
 * defined in this library:
 *
 *   Plugin (immutable host info)
 *     ├── ActivationRepository (option-backed persistence)
 *     ├── TransientCache       (per-plugin cache + throttle)
 *     ├── ApiFactory           (signed / unsigned HTTP clients)
 *     └── SiteIdentity         (deterministic site UID)
 *
 *   ── into ──
 *
 *   License (activate / deactivate)
 *   Update  (WordPress update hooks)
 *   Addon   (addon listing)
 *
 * Construction has no side effects: filters and actions are
 * registered later inside {@see boot()}, which is called once
 * automatically by {@see get_instance()} the first time it builds an
 * instance for a given plugin ID.
 *
 * This library performs no permission or nonce checks. Host plugins
 * MUST verify capabilities and nonces before forwarding form input.
 *
 * References:
 * - https://github.com/Freemius/wp-sdk-lite
 * - https://github.com/gambitph/freemius-lite-activation
 *
 * @link    https://foxelabs.com/
 * @license http://www.gnu.org/licenses/ GNU General Public License
 * @author  Joel James <me@joelsays.com>
 * @since   1.0.0
 * @package Freemius
 */

namespace FoxeLabs\Freemius;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use FoxeLabs\Freemius\Api\ApiFactory;
use FoxeLabs\Freemius\Data\Plugin;
use FoxeLabs\Freemius\Services\Addon;
use FoxeLabs\Freemius\Services\License;
use FoxeLabs\Freemius\Services\Update;
use FoxeLabs\Freemius\Storage\ActivationRepository;
use FoxeLabs\Freemius\Storage\TransientCache;
use FoxeLabs\Freemius\Support\SiteIdentity;

/**
 * Class Freemius.
 */
class Freemius {

	/**
	 * Plugin data instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * License service.
	 *
	 * @since 1.0.0
	 *
	 * @var License
	 */
	private License $license;

	/**
	 * Update service.
	 *
	 * @since 1.0.0
	 *
	 * @var Update
	 */
	private Update $update;

	/**
	 * Addon service.
	 *
	 * @since 1.0.0
	 *
	 * @var Addon
	 */
	private Addon $addon;

	/**
	 * Whether {@see boot()} has run for this container.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * Marked protected so that consumers go through
	 * {@see get_instance()} — there is one container per plugin ID
	 * and reuse matters because boot() registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Freemius product ID.
	 * @param array $args Arguments forwarded to {@see Plugin}.
	 */
	protected function __construct( int $id, array $args ) {
		$this->plugin = new Plugin( $id, $args );

		// Compose the collaborator graph.
		$activations = new ActivationRepository();
		$cache       = new TransientCache( $this->plugin );
		$api_factory = new ApiFactory();
		$site        = new SiteIdentity();

		// Wire each service with the collaborators it actually needs.
		$this->license = new License( $this->plugin, $activations, $api_factory, $site );
		$this->update  = new Update( $this->plugin, $activations, $cache, $api_factory );
		$this->addon   = new Addon( $this->plugin, $cache, $api_factory );
	}

	/**
	 * Get (or create) the container for a given plugin ID.
	 *
	 * The first call MUST supply $args. Subsequent calls may omit
	 * them — the second argument is only consulted when a fresh
	 * instance is being constructed.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Freemius product ID.
	 * @param array $args Plugin arguments. Required on first call.
	 *
	 * @return self
	 */
	public static function get_instance( int $id, array $args = array() ): self {
		static $instances = array();

		if ( ! isset( $instances[ $id ] ) ) {
			$instances[ $id ] = new self( $id, $args );
			$instances[ $id ]->boot();
		}

		return $instances[ $id ];
	}

	/**
	 * Boot every service — register WordPress hooks.
	 *
	 * Idempotent: a second call is a no-op. Normally invoked
	 * automatically by {@see get_instance()}; exposed publicly only
	 * so unusual host integrations can defer hook registration.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->license->boot();
		$this->update->boot();
		$this->addon->boot();

		$this->booted = true;
	}

	/**
	 * Get the plugin data instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	public function plugin(): Plugin {
		return $this->plugin;
	}

	/**
	 * Get the license service.
	 *
	 * @since 1.0.0
	 *
	 * @return License
	 */
	public function license(): License {
		return $this->license;
	}

	/**
	 * Get the update service.
	 *
	 * @since 1.0.0
	 *
	 * @return Update
	 */
	public function update(): Update {
		return $this->update;
	}

	/**
	 * Get the addon service.
	 *
	 * @since 1.0.0
	 *
	 * @return Addon
	 */
	public function addon(): Addon {
		return $this->addon;
	}
}
