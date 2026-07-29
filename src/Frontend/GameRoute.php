<?php
/**
 * Public front-end route for published Games.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles /local-knowledge/game/{game-number}/.
 */
final class GameRoute {

	/**
	 * Query var for the requested Game Number.
	 */
	public const QUERY_VAR = 'lk_game_number';

	/**
	 * Rewrite rule version for controlled flush.
	 */
	public const REWRITE_VERSION = 1;

	/**
	 * Option key storing the last flushed rewrite version.
	 */
	public const REWRITE_OPTION = 'lk_rewrite_version';

	/**
	 * Wire public route hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 11, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_edit_screen_link' ), 11 );
	}

	/**
	 * Register the dedicated Game rewrite rule.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^local-knowledge/game/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules once when the plugin rewrite version changes.
	 */
	public function maybe_flush_rewrite_rules(): void {
		$stored = (int) get_option( self::REWRITE_OPTION, 0 );

		if ( self::REWRITE_VERSION === $stored ) {
			return;
		}

		$this->add_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION );
	}

	/**
	 * Register rewrite rules and flush during plugin activation.
	 */
	public static function activate_rewrites(): void {
		$route = new self();
		$route->add_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION );
	}

	/**
	 * Remove rewrite version marker and flush on deactivation.
	 */
	public static function deactivate_rewrites(): void {
		delete_option( self::REWRITE_OPTION );
		flush_rewrite_rules( false );
	}

	/**
	 * Expose the Game Number query variable.
	 *
	 * @param list<string> $vars Existing query vars.
	 * @return list<string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Build the public URL for a Game Number.
	 *
	 * @param int $game_number Saved Game Number.
	 */
	public static function get_public_url( int $game_number ): string {
		if ( $game_number < 1 ) {
			return '';
		}

		return home_url( user_trailingslashit( 'local-knowledge/game/' . $game_number ) );
	}

	/**
	 * Render a published Game when the dedicated route is requested.
	 */
	public function maybe_render(): void {
		$raw_number = get_query_var( self::QUERY_VAR, '' );

		if ( '' === $raw_number && '0' !== $raw_number ) {
			return;
		}

		$game_number = absint( $raw_number );

		if ( $game_number < 1 ) {
			$this->render_not_found();
			return;
		}

		$game_id = $this->find_published_game_id( $game_number );

		if ( null === $game_id ) {
			$this->render_not_found();
			return;
		}

		$display = new GameDisplayData();
		$errors  = $display->get_completeness_errors( $game_id );

		if ( array() !== $errors ) {
			$this->render_not_found();
			return;
		}

		$play = new GamePlay();
		$play->maybe_redirect_after_post( $game_id, $game_number );

		$extras = $play->get_view_extras( $game_id, $game_number );
		$stage  = isset( $extras['image_stage'] ) ? absint( $extras['image_stage'] ) : 1;

		$view = $display->build_view( $game_id, false, $stage );
		$view = array_merge( $view, $extras );

		$renderer = new GameRenderer();
		$renderer->render( $view );
		exit;
	}

	/**
	 * Locate a published lk_game by Game Number.
	 *
	 * @param int $game_number Game Number to find.
	 */
	private function find_published_game_id( int $game_number ): ?int {
		$query = new \WP_Query(
			array(
				'post_type'              => GamePostType::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => GameDisplayData::META_KEYS['game_number'],
						'value'   => $game_number,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		return (int) $query->posts[0];
	}

	/**
	 * Send a proper 404 response for unavailable Games.
	 */
	private function render_not_found(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_404_template();

		if ( is_string( $template ) && '' !== $template ) {
			include $template;
		} else {
			wp_die(
				esc_html__( 'Game not found.', 'local-knowledge' ),
				esc_html__( 'Not Found', 'local-knowledge' ),
				array( 'response' => 404 )
			);
		}

		exit;
	}

	/**
	 * Add a View Game row action for published Games.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    Current post.
	 * @return array<string, string>
	 */
	public function add_row_action( array $actions, \WP_Post $post ): array {
		if ( GamePostType::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( 'publish' !== $post->post_status ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$game_number = absint( get_post_meta( $post->ID, GameDisplayData::META_KEYS['game_number'], true ) );
		$url         = self::get_public_url( $game_number );

		if ( '' === $url ) {
			return $actions;
		}

		$actions['lk_view_game'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'View Game', 'local-knowledge' )
		);

		return $actions;
	}

	/**
	 * Add a View Game link on the edit screen for published Games.
	 *
	 * @param \WP_Post|null $post Current post when available.
	 */
	public function render_edit_screen_link( ?\WP_Post $post = null ): void {
		if ( ! $post instanceof \WP_Post ) {
			global $post;
		}

		if ( ! $post instanceof \WP_Post || GamePostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$game_number = absint( get_post_meta( $post->ID, GameDisplayData::META_KEYS['game_number'], true ) );
		$url         = self::get_public_url( $game_number );

		if ( '' === $url ) {
			return;
		}
		?>
		<div class="misc-pub-section lk-view-game-link">
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Game', 'local-knowledge' ); ?>
			</a>
		</div>
		<?php
	}
}
