<?php
/**
 * Secure administrator Game preview controller.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Admin;

use JoyOfCode\LocalKnowledge\Frontend\GameDisplayData;
use JoyOfCode\LocalKnowledge\Frontend\GameRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Provides nonce-protected Game preview via admin-post.php.
 */
final class GamePreview {

	/**
	 * admin-post.php action name.
	 */
	public const ACTION = 'lk_preview_game';

	/**
	 * Wire preview hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_preview' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_logged_out_preview' ) );
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_edit_screen_link' ) );
	}

	/**
	 * Add a Preview Game row action for lk_game posts.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    Current post.
	 * @return array<string, string>
	 */
	public function add_row_action( array $actions, \WP_Post $post ): array {
		if ( GamePostType::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = $this->get_preview_url( $post->ID );

		if ( '' === $url ) {
			return $actions;
		}

		$actions['lk_preview_game'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Preview Game', 'local-knowledge' )
		);

		return $actions;
	}

	/**
	 * Add a Preview Game link on the Game edit screen.
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

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$url = $this->get_preview_url( $post->ID );

		if ( '' === $url ) {
			return;
		}
		?>
		<div class="misc-pub-section lk-preview-game-link">
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Preview Game', 'local-knowledge' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Deny logged-out preview requests with a clear error page.
	 */
	public function handle_logged_out_preview(): void {
		wp_die(
			esc_html__( 'You must be logged in to preview this Game.', 'local-knowledge' ),
			esc_html__( 'Preview Unavailable', 'local-knowledge' ),
			array( 'response' => 401 )
		);
	}

	/**
	 * Handle a secure preview request.
	 */
	public function handle_preview(): void {
		if ( ! is_user_logged_in() ) {
			$this->handle_logged_out_preview();
		}

		$game_id = isset( $_GET['game_id'] ) ? absint( wp_unslash( $_GET['game_id'] ) ) : 0;

		if ( $game_id < 1 ) {
			wp_die(
				esc_html__( 'The Game ID is missing or invalid.', 'local-knowledge' ),
				esc_html__( 'Preview Unavailable', 'local-knowledge' ),
				array( 'response' => 400 )
			);
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $game_id ) ) ) {
			wp_die(
				esc_html__( 'The preview link is invalid or has expired.', 'local-knowledge' ),
				esc_html__( 'Preview Unavailable', 'local-knowledge' ),
				array( 'response' => 403 )
			);
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post ) {
			wp_die(
				esc_html__( 'The Game ID is missing or invalid.', 'local-knowledge' ),
				esc_html__( 'Preview Unavailable', 'local-knowledge' ),
				array( 'response' => 404 )
			);
		}

		if ( GamePostType::POST_TYPE !== $post->post_type ) {
			wp_die(
				esc_html__( 'The requested post is not a Game.', 'local-knowledge' ),
				esc_html__( 'Preview Unavailable', 'local-knowledge' ),
				array( 'response' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $game_id ) ) {
			wp_die(
				esc_html__( 'You are not authorized to preview this Game.', 'local-knowledge' ),
				esc_html__( 'Preview Unavailable', 'local-knowledge' ),
				array( 'response' => 403 )
			);
		}

		$display = new GameDisplayData();
		$errors  = $display->get_completeness_errors( $game_id );

		if ( array() !== $errors ) {
			$this->die_with_errors( $errors );
		}

		$view = $display->build_view( $game_id, true );

		$renderer = new GameRenderer();
		$renderer->render( $view );
		exit;
	}

	/**
	 * Build a nonce-protected preview URL for a Game.
	 *
	 * @param int $game_id Game post ID.
	 */
	public function get_preview_url( int $game_id ): string {
		if ( $game_id < 1 ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'action'  => self::ACTION,
				'game_id' => $game_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, $this->nonce_action( $game_id ) );
	}

	/**
	 * Nonce action for a specific Game.
	 *
	 * @param int $game_id Game post ID.
	 */
	private function nonce_action( int $game_id ): string {
		return self::ACTION . '_' . $game_id;
	}

	/**
	 * Stop the preview request and list every completeness problem.
	 *
	 * @param list<string> $errors Human-readable validation messages.
	 */
	private function die_with_errors( array $errors ): void {
		$message  = '<p><strong>' . esc_html__( 'This Game cannot be previewed because it is incomplete.', 'local-knowledge' ) . '</strong></p>';
		$message .= '<ul>';

		foreach ( $errors as $error ) {
			if ( ! is_string( $error ) || '' === $error ) {
				continue;
			}

			$message .= '<li>' . esc_html( $error ) . '</li>';
		}

		$message .= '</ul>';

		wp_die(
			$message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html__( 'Game Display Error', 'local-knowledge' ),
			array( 'response' => 400 )
		);
	}
}
