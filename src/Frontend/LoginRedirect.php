<?php
/**
 * Post-login redirect to the Play page for normal players.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Sends eligible subscribers to the Play page after standard WordPress login.
 */
final class LoginRedirect {

	/**
	 * Wire login redirect filter.
	 */
	public function register(): void {
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
	}

	/**
	 * Redirect normal players to the Play page; leave admins alone.
	 *
	 * @param string             $redirect_to           Requested redirect.
	 * @param string             $requested_redirect_to Explicit redirect_to from login form.
	 * @param \WP_User|\WP_Error $user                  Authenticated user or error.
	 */
	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof \WP_User ) {
			return $redirect_to;
		}

		// Administrators keep core / role-based admin redirects.
		if ( user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}

		// Respect an explicit, safe non-admin redirect chosen by the login form
		// (e.g. password reset flows), except wp-admin destinations.
		$requested = is_string( $requested_redirect_to ) ? $requested_redirect_to : '';

		if ( '' !== $requested
			&& ! $this->is_admin_url( $requested )
			&& wp_validate_redirect( $requested, false )
		) {
			return $requested;
		}

		$play = PlayPage::get_url();

		if ( '' === $play ) {
			return $redirect_to;
		}

		$safe = wp_validate_redirect( $play, false );

		return is_string( $safe ) && '' !== $safe ? $safe : $redirect_to;
	}

	/**
	 * Whether a URL targets wp-admin.
	 *
	 * @param string $url Candidate URL.
	 */
	private function is_admin_url( string $url ): bool {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$admin = wp_parse_url( admin_url(), PHP_URL_PATH );

		if ( ! is_string( $admin ) || '' === $admin ) {
			return false;
		}

		return str_starts_with( trailingslashit( $path ), trailingslashit( $admin ) );
	}
}
