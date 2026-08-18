<?php
/**
 * Public Login / Logout navigation item.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Injects one Log In or Log Out item into the site header Navigation block.
 *
 * Does not modify stored Navigation content, the theme, or Site Editor config.
 */
final class AuthNav {

	/**
	 * Class used to mark and detect the injected item.
	 */
	private const ITEM_CLASS = 'lk-auth-nav';

	/**
	 * Query flag indicating Play was reached via a secure WordPress logout.
	 */
	public const LOGGED_OUT_QUERY = 'lk_logged_out';

	/**
	 * Whether inner blocks of a header template part are currently rendering.
	 */
	private bool $in_header = false;

	/**
	 * Wire block-render filters.
	 */
	public function register(): void {
		add_filter( 'pre_render_block', array( $this, 'maybe_enter_header' ), 10, 2 );
		add_filter( 'render_block_core/template-part', array( $this, 'leave_header' ), 10, 1 );
		add_filter( 'render_block_core/navigation', array( $this, 'inject_auth_item' ), 10, 1 );
		add_filter( 'logout_redirect', array( $this, 'append_logged_out_flag' ), 10, 3 );
	}

	/**
	 * Track when a header template part begins rendering.
	 *
	 * @param string|null          $pre_render Existing short-circuit value.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return string|null
	 */
	public function maybe_enter_header( $pre_render, array $parsed_block ) {
		if ( 'core/template-part' === ( $parsed_block['blockName'] ?? '' )
			&& 'header' === ( $parsed_block['attrs']['slug'] ?? '' )
		) {
			$this->in_header = true;
		}

		return $pre_render;
	}

	/**
	 * Stop tagging inner output as header after the template part finishes.
	 *
	 * @param string $content Rendered template-part HTML.
	 */
	public function leave_header( string $content ): string {
		$this->in_header = false;

		return $content;
	}

	/**
	 * Append exactly one auth item to a public header Navigation block.
	 *
	 * @param string $content Rendered navigation HTML.
	 */
	public function inject_auth_item( string $content ): string {
		if ( ! $this->in_header ) {
			return $content;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return $content;
		}

		if ( '' === trim( $content ) ) {
			return $content;
		}

		if ( $this->already_has_auth_item( $content ) ) {
			return $content;
		}

		$item = $this->build_item_html();

		if ( '' === $item ) {
			return $content;
		}

		$inserted = $this->insert_into_container( $content, $item );

		return is_string( $inserted ) && '' !== $inserted ? $inserted : $content;
	}

	/**
	 * True when this Navigation block already has Local Knowledge or WP login/out markup.
	 *
	 * @param string $content Navigation HTML.
	 */
	private function already_has_auth_item( string $content ): bool {
		if ( false !== strpos( $content, self::ITEM_CLASS ) ) {
			return true;
		}

		if ( false !== strpos( $content, 'wp-login.php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether this request should show the post-logout message instead of Play.
	 */
	public static function is_post_logout_view(): bool {
		if ( is_user_logged_in() ) {
			return false;
		}

		$flag = isset( $_GET[ self::LOGGED_OUT_QUERY ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::LOGGED_OUT_QUERY ] ) )
			: '';

		return '1' === $flag;
	}

	/**
	 * Play URL used after login (no logout flag).
	 */
	public static function play_destination(): string {
		$url = PlayPage::get_url();

		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Append the post-logout flag to a Play-page logout redirect.
	 *
	 * @param string             $redirect_to           Destination after logout.
	 * @param string             $requested_redirect_to Original redirect_to request.
	 * @param \WP_User|\WP_Error $user                  User being logged out.
	 * @return string
	 */
	public function append_logged_out_flag( $redirect_to, $requested_redirect_to, $user ) {
		unset( $requested_redirect_to, $user );

		$play = self::play_destination();

		if ( ! is_string( $redirect_to ) || '' === $redirect_to || '' === $play ) {
			return $redirect_to;
		}

		if ( ! $this->redirect_is_play( $redirect_to, $play ) ) {
			return $redirect_to;
		}

		return add_query_arg( self::LOGGED_OUT_QUERY, '1', $play );
	}

	/**
	 * Whether a logout destination is the Play page (query string ignored).
	 */
	private function redirect_is_play( string $redirect_to, string $play ): bool {
		$left  = $this->destination_key( $redirect_to );
		$right = $this->destination_key( $play );

		return '' !== $left && $left === $right;
	}

	/**
	 * Host + path of a URL, ignoring query and fragment.
	 */
	private function destination_key( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path = (string) ( $parts['path'] ?? '/' );

		if ( '' === $path ) {
			$path = '/';
		}

		return $host . untrailingslashit( $path );
	}

	/**
	 * Build one navigation list item for the current auth state.
	 */
	private function build_item_html(): string {
		$play = self::play_destination();

		if ( is_user_logged_in() ) {
			$url   = wp_logout_url( $play );
			$label = __( 'Log Out', 'local-knowledge' );
		} else {
			$url   = wp_login_url( $play );
			$label = __( 'Log In', 'local-knowledge' );
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return '<li class="wp-block-navigation-item wp-block-navigation-link '
			. esc_attr( self::ITEM_CLASS ) . '">'
			. '<a class="wp-block-navigation-item__content" href="' . esc_url( $url ) . '">'
			. '<span class="wp-block-navigation-item__label">' . esc_html( $label ) . '</span>'
			. '</a></li>';
	}

	/**
	 * Insert the item into the top-level navigation container, once.
	 *
	 * @param string $content Navigation HTML.
	 * @param string $item    List-item markup.
	 */
	private function insert_into_container( string $content, string $item ): string {
		$marker = 'wp-block-navigation__container';
		$found  = strpos( $content, $marker );

		if ( false === $found ) {
			return $this->insert_new_container( $content, $item );
		}

		$ul_open = strrpos( substr( $content, 0, $found ), '<ul' );

		if ( false === $ul_open ) {
			return $content;
		}

		$depth = 0;
		$pos   = $ul_open;
		$limit = strlen( $content );

		while ( $pos < $limit ) {
			$next_open  = strpos( $content, '<ul', $pos );
			$next_close = strpos( $content, '</ul>', $pos );

			if ( false === $next_close ) {
				break;
			}

			if ( false !== $next_open && $next_open < $next_close ) {
				++$depth;
				$pos = $next_open + 3;
				continue;
			}

			--$depth;

			if ( 0 === $depth ) {
				return substr( $content, 0, $next_close ) . $item . substr( $content, $next_close );
			}

			$pos = $next_close + 5;
		}

		return $content;
	}

	/**
	 * When the header nav has no list yet, add a single container with the auth item.
	 *
	 * @param string $content Navigation HTML.
	 * @param string $item    List-item markup.
	 */
	private function insert_new_container( string $content, string $item ): string {
		$list = '<ul class="wp-block-navigation__container">' . $item . '</ul>';
		$needle = 'wp-block-navigation__responsive-container-content';
		$found  = strpos( $content, $needle );

		if ( false === $found ) {
			return $content . $list;
		}

		$gt = strpos( $content, '>', $found );

		if ( false === $gt ) {
			return $content;
		}

		return substr( $content, 0, $gt + 1 ) . $list . substr( $content, $gt + 1 );
	}
}
