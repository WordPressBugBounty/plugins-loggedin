<?php
/**
 * Admin module.
 *
 * Responsible for the wp-admin surface area that is *not* the React
 * app itself:
 *
 *   - Registering the menu item under Users → Loggedin and emitting
 *     the React mount point div.
 *   - The legacy section on Settings → General that deep-links to
 *     the new admin page (kept for users who bookmarked the old
 *     location).
 *   - The "force logout this user from all devices" action handler
 *     (link rendered by addon code or admin pages outside this
 *     plugin).
 *   - The review-request notice (one-time prompt asking the user to
 *     rate the plugin on wp.org).
 *
 * Asset enqueueing for the React bundle lives in a sibling class —
 * {@see Assets} — so this file doesn't have to know how the bundle
 * is built or what dependencies it declares.
 *
 * @package DuckDev\Loggedin\Admin
 */

declare( strict_types = 1 );

namespace DuckDev\Loggedin\Admin;

use DuckDev\Loggedin\Contracts\Singleton;
use DuckDev\Loggedin\Plugin;
use WP_Session_Tokens;
use WP_User;

defined( 'WPINC' ) || die;

/**
 * Admin bootstrap — menu, assets, and React app shell.
 *
 * @since 3.0.0
 */
final class Admin {

	use Singleton;

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	protected function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'old_options_page' ) );
		add_action( 'admin_init', array( $this, 'force_logout' ) );
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
		add_action( 'admin_init', array( $this, 'review_action' ) );
	}

	/**
	 * Process a "force logout" request.
	 *
	 * Linked to from outside the React app (e.g. a user-row action on
	 * the core Users list table contributed by an addon). Looks for
	 * `?loggedin_logout=1&loggedin_user=<id>&_wpnonce=...`, verifies
	 * the nonce, destroys every session for the target user, and
	 * surfaces a `settings_errors`-based admin notice with the
	 * result.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function force_logout(): void {
		if ( ! isset( $_REQUEST['loggedin_logout'], $_REQUEST['loggedin_user'] ) ) {
			return;
		}

		check_admin_referer( 'loggedin-options' );

		$user = get_userdata( (int) $_REQUEST['loggedin_user'] );

		if ( $user ) {
			WP_Session_Tokens::get_instance( $user->ID )->destroy_all();

			add_settings_error(
				'general',
				'settings_updated',
				sprintf(
					// translators: %s user login of the user being logged out.
					__( 'The user "%s" was forcefully logged out from all devices.', 'loggedin' ),
					$user->user_login
				),
				'updated'
			);
		} else {
			add_settings_error(
				'general',
				'settings_updated',
				sprintf(
					// translators: %d the invalid user id supplied in the request.
					__( 'Invalid user ID: %d', 'loggedin' ),
					(int) $_REQUEST['loggedin_user']
				)
			);
		}
	}

	/**
	 * Register the Users → Loggedin menu item.
	 *
	 * Kept under the Users menu (and not promoted to a top-level
	 * item) so existing bookmarks of `users.php?page=loggedin`
	 * keep working through this refactor.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_users_page(
			// translators: %s lock icon.
			sprintf( __( '%s Loggedin Settings', 'loggedin' ), '🔒' ),
			// translators: %s lock icon.
			sprintf( __( '%s Loggedin', 'loggedin' ), '<span class="dashicons dashicons-lock"></span>' ),
			'manage_options',
			Plugin::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * The bundle's entry (`assets/src/admin.js`) looks for
	 * `#loggedin-admin` and bails silently if it's missing. The
	 * outer `.wrap` keeps WordPress admin notices positioned the way
	 * core expects; the inner `.loggedin-wrap` is the root selector
	 * every plugin stylesheet hangs its rules under.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		echo '<div class="wrap"><div id="loggedin-admin" class="loggedin-wrap"></div></div>';
	}

	/**
	 * Add a deprecation section on Settings → General.
	 *
	 * Pre-2.0 versions registered the plugin's options on the core
	 * General screen. Users who still navigate there see a one-line
	 * pointer to the new location instead of a broken / empty
	 * section.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.0 Kept only as a navigation aid.
	 *
	 * @return void
	 */
	public function old_options_page(): void {
		add_settings_section(
			'loggedin_settings',
			// translators: %s lock icon.
			sprintf( __( '%s Loggedin Settings', 'loggedin' ), '<span class="dashicons dashicons-lock"></span>' ),
			array( $this, 'loggedin_old_settings' ),
			'general'
		);
	}

	/**
	 * Render the deep-link pointer body.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function loggedin_old_settings(): void {
		?>
		<p class="description">
			<?php
			printf(
				// translators: %1$s opening anchor, %2$s closing anchor.
				esc_attr__( 'Loggedin settings have been relocated. %1$sClick here%2$s to access the new settings page.', 'loggedin' ),
				'<a href="' . esc_url( admin_url( 'users.php?page=' . Plugin::SLUG ) ) . '">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Show the wp.org review-request notice on the plugin admin page.
	 *
	 * State machine:
	 *
	 *   - No `loggedin_rating_notice` option yet: this is the first
	 *     visit on a new install. Schedule the notice to appear in a
	 *     week and bail.
	 *
	 *   - The scheduled time is in the future: do nothing.
	 *
	 *   - The user already dismissed the notice (per-user meta): do
	 *     nothing.
	 *
	 *   - Otherwise: render the notice.
	 *
	 * @since 3.0.0
	 *
	 * @return void|bool
	 */
	public function review_notice() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users_page_' . Plugin::SLUG !== $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$notice_time = get_option( 'loggedin_rating_notice' );

		if ( empty( $notice_time ) ) {
			// First view on a fresh install — schedule the prompt
			// for a week out.
			return add_option( 'loggedin_rating_notice', time() + WEEK_IN_SECONDS );
		}

		$current_user = wp_get_current_user();
		$dismissed    = get_user_meta( $current_user->ID, 'loggedin_rating_notice_dismissed', true );

		if ( (int) $notice_time <= time() && ! $dismissed ) {
			$this->render_review_notice( $current_user );
		}
	}

	/**
	 * Render the notice markup.
	 *
	 * Three actions:
	 *   - "Rate now" → opens the wp.org review form in a new tab.
	 *   - "Maybe later" → re-schedules the notice for two weeks out.
	 *   - "No thanks" → records a per-user dismissal so the notice
	 *     never reappears for this account.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_User $current_user The logged-in admin.
	 */
	private function render_review_notice( WP_User $current_user ): void {
		$dismiss = wp_nonce_url( add_query_arg( 'loggedin_rating', 'dismiss' ), 'loggedin_rating' );
		$later   = wp_nonce_url( add_query_arg( 'loggedin_rating', 'later' ), 'loggedin_rating' );
		$rate    = 'https://wordpress.org/support/plugin/loggedin/reviews/?rate=5#new-post';
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php
				printf(
					// translators: %s the admin's display name.
					esc_html__( 'Hi %s, are you enjoying Loggedin? Could you leave a quick rating on WordPress.org?', 'loggedin' ),
					esc_html( $current_user->display_name )
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $rate ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rate now', 'loggedin' ); ?></a>
				&nbsp;
				<a class="button" href="<?php echo esc_url( $later ); ?>"><?php esc_html_e( 'Maybe later', 'loggedin' ); ?></a>
				&nbsp;
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'No thanks', 'loggedin' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Process the review-notice action links.
	 *
	 * Hooked early enough that the redirect / option-write is in
	 * place before the notice rendering runs on the same request.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function review_action(): void {
		if ( ! isset( $_REQUEST['loggedin_rating'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if (
			! isset( $_REQUEST['_wpnonce'] ) ||
			! wp_verify_nonce( $_REQUEST['_wpnonce'], 'loggedin_rating' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		) {
			return;
		}

		switch ( $_REQUEST['loggedin_rating'] ) {
			case 'later':
				// Push the prompt out two more weeks.
				update_option( 'loggedin_rating_notice', time() + ( 2 * WEEK_IN_SECONDS ) );
				break;

			case 'dismiss':
				// Hide forever for this user.
				update_user_meta( get_current_user_id(), 'loggedin_rating_notice_dismissed', 1 );
				break;
		}
	}
}
