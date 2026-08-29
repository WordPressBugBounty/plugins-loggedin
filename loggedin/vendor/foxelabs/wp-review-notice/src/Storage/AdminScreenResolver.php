<?php
/**
 * WordPress admin screen resolver.
 *
 * Default {@see \FoxeLabs\Reviews\Contracts\ScreenResolverInterface}
 * implementation. Reads the current screen via WP's
 * `get_current_screen()` and matches it against the consumer's
 * allow-list.
 *
 * @link       https://foxelabs.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      2.0.0
 * @package    Reviews
 * @subpackage Storage
 */

namespace FoxeLabs\Reviews\Storage;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use FoxeLabs\Reviews\Contracts\ScreenResolverInterface;

/**
 * Class AdminScreenResolver.
 */
class AdminScreenResolver implements ScreenResolverInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param array<int, string> $allowed Allow-list of screen IDs.
	 *
	 * @return bool
	 */
	public function is_allowed( array $allowed ): bool {
		// Empty allow-list keeps v1's "show everywhere" semantics.
		if ( empty( $allowed ) ) {
			return true;
		}

		// `get_current_screen()` returns null very early in the admin
		// bootstrap. Treat that as "no, not yet" so we don't render
		// against an unknown screen.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return null !== $screen
			&& ! empty( $screen->id )
			&& in_array( $screen->id, $allowed, true );
	}
}
