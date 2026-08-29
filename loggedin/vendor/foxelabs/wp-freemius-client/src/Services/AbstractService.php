<?php
/**
 * Abstract base for services.
 *
 * The pre-refactor `Service` class accumulated too many
 * responsibilities — option access, transient caching, throttling,
 * and base service state. Each of those has been extracted into a
 * dedicated collaborator
 * ({@see \FoxeLabs\Freemius\Storage\ActivationRepository},
 * {@see \FoxeLabs\Freemius\Storage\TransientCache},
 * {@see \FoxeLabs\Freemius\Support\SiteIdentity}) so this base class
 * now holds only the {@see \FoxeLabs\Freemius\Data\Plugin} reference
 * and a default no-op {@see boot()} implementation.
 *
 * @link       https://foxelabs.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Services
 */

namespace FoxeLabs\Freemius\Services;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use FoxeLabs\Freemius\Contracts\ServiceInterface;
use FoxeLabs\Freemius\Data\Plugin;

/**
 * Class AbstractService.
 */
abstract class AbstractService implements ServiceInterface {

	/**
	 * Plugin the service is operating on behalf of.
	 *
	 * @since 2.0.0
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function get_plugin(): Plugin {
		return $this->plugin;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Default implementation registers no hooks. Override in subclasses
	 * to attach to WordPress filters/actions during boot.
	 *
	 * @since 2.0.0
	 */
	public function boot(): void {
		// No-op by default.
	}
}
