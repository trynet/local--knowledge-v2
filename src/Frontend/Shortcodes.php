<?php
/**
 * Public shortcodes for Play, Dashboard, and direct Game rendering.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Player\CurrentGameResolver;
use JoyOfCode\LocalKnowledge\Player\PlayerResultRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Registers [lk_current_game], [lk_dashboard], and [lk_game].
 */
final class Shortcodes {

	/**
	 * Whether the current request already processed a Game POST via shortcode.
	 */
	private bool $processed_post = false;

	/**
	 * Wire shortcodes and Play-page POST handling.
	 */
	public function register(): void {
		add_shortcode( 'lk_current_game', array( $this, 'render_current_game' ) );
		add_shortcode( 'lk_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'lk_game', array( $this, 'render_direct_game' ) );

		add_action( 'template_redirect', array( $this, 'maybe_process_play_page_post' ), 5 );
		add_action( 'save_post_page', array( $this, 'clear_play_page_cache' ) );
		add_action( 'trashed_post', array( $this, 'clear_play_page_cache_on_trash' ) );
		add_action( 'deleted_post', array( $this, 'clear_play_page_cache_on_trash' ) );
	}

	/**
	 * Process gameplay/registration POSTs on the Play page before output.
	 */
	public function maybe_process_play_page_post(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$play_id = PlayPage::get_page_id();

		if ( $play_id < 1 || ! is_page( $play_id ) ) {
			return;
		}

		$action = isset( $_POST['lk_game_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['lk_game_action'] ) )
			: '';

		$is_gameplay     = GamePlay::FORM_ACTION === $action;
		$is_registration = RegistrationGateway::FORM_ACTION === $action;

		if ( ! $is_gameplay && ! $is_registration ) {
			return;
		}

		$game_id = isset( $_POST['lk_game_id'] ) ? absint( wp_unslash( $_POST['lk_game_id'] ) ) : 0;

		if ( $game_id < 1 ) {
			return;
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return;
		}

		$game_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( $game_number < 1 ) {
			return;
		}

		nocache_headers();

		$screen = new PublicGameScreen();
		$screen->process_request( $game_id, $game_number );

		if ( $is_gameplay ) {
			$this->processed_post = true;
		}
	}

	/**
	 * [lk_current_game] — resolve and render the eligible Game.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_current_game( $atts = array() ): string {
		unset( $atts );

		if ( AuthNav::is_post_logout_view() ) {
			return $this->render_logged_out_notice();
		}

		$completion_view = $this->maybe_render_completion_flag_view();

		if ( null !== $completion_view ) {
			return $completion_view;
		}

		$resolver = new CurrentGameResolver();
		$user_id  = is_user_logged_in() ? get_current_user_id() : 0;
		$resolved = $resolver->resolve( $user_id );

		if ( 'unavailable' === ( $resolved['status'] ?? '' ) ) {
			return $this->message_box(
				(string) ( $resolved['message'] ?? __( 'No Games are available to play right now.', 'local-knowledge' ) )
			);
		}

		if ( 'awaiting_next' === ( $resolved['status'] ?? '' ) ) {
			$points = isset( $resolved['previous_points'] ) ? absint( $resolved['previous_points'] ) : 0;

			ob_start();
			?>
			<section class="lk-game__handoff lk-game__handoff--unavailable" role="status">
				<h2 class="lk-game__handoff-title"><?php esc_html_e( 'Game 1 Complete', 'local-knowledge' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %d: points earned */
						esc_html__( 'You earned %d points in Game 1.', 'local-knowledge' ),
						$points
					);
					?>
				</p>
				<p>
					<?php
					echo esc_html(
						(string) ( $resolved['message'] ?? __( 'Game 2 is not available yet. Please check back later.', 'local-knowledge' ) )
					);
					?>
				</p>
			</section>
			<?php
			$html = ob_get_clean();

			return is_string( $html ) ? $html : '';
		}

		$game_id     = isset( $resolved['game_id'] ) ? absint( $resolved['game_id'] ) : 0;
		$game_number = isset( $resolved['game_number'] ) ? absint( $resolved['game_number'] ) : 0;

		if ( $game_id < 1 || $game_number < 1 ) {
			return $this->message_box( __( 'No Games are available to play right now.', 'local-knowledge' ) );
		}

		$overlay = array();

		if ( $game_number > 1 && isset( $resolved['previous_points'] ) ) {
			$prev_num = isset( $resolved['previous_number'] )
				? absint( $resolved['previous_number'] )
				: ( $game_number - 1 );

			$overlay['show_game1_handoff']   = true;
			$overlay['game1_handoff_points'] = absint( $resolved['previous_points'] );
			$overlay['handoff_game_number']  = $prev_num;
			$overlay['current_total_points'] = ( new PlayerResultRepository() )
				->get_total_points( $user_id );
		}

		return $this->render_game_html( $game_id, $game_number, $overlay );
	}

	/**
	 * When lk_game_complete is present, show the validated completed game before resolver advances.
	 */
	private function maybe_render_completion_flag_view(): ?string {
		if ( ! isset( $_GET[ GamePlay::COMPLETE_QUERY ] ) ) {
			return null;
		}

		if ( ! is_user_logged_in() ) {
			return null;
		}

		$game_number = absint( wp_unslash( (string) $_GET[ GamePlay::COMPLETE_QUERY ] ) );

		if ( $game_number < 2 || $game_number > 10 ) {
			return null;
		}

		$user_id   = get_current_user_id();
		$results   = new PlayerResultRepository();
		$permanent = $results->get_result( $user_id, $game_number );

		if ( null === $permanent ) {
			return null;
		}

		$result_type = isset( $permanent['result_type'] ) ? sanitize_key( (string) $permanent['result_type'] ) : '';

		if ( ! in_array( $result_type, array( 'correct', 'idk' ), true ) ) {
			return null;
		}

		$game_id = isset( $permanent['game_id'] ) ? absint( $permanent['game_id'] ) : 0;

		if ( $game_id < 1 ) {
			return null;
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return null;
		}

		$stored_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( $stored_number !== $game_number ) {
			return null;
		}

		$display = new GameDisplayData();

		if ( array() !== $display->get_completeness_errors( $game_id ) ) {
			return null;
		}

		$overlay = $this->build_completion_overlay( $game_id, $game_number, $permanent );

		return $this->render_game_html( $game_id, $game_number, $overlay );
	}

	/**
	 * View overlay for a logged-in player's locked completion (from permanent result).
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @param array<string, mixed> $permanent   Stored player result.
	 * @return array<string, mixed>
	 */
	private function build_completion_overlay( int $game_id, int $game_number, array $permanent ): array {
		$display = new GameDisplayData();
		$key     = $display->get_correct_location_key( $game_id );

		$view = isset( $permanent['completed_view'] ) ? absint( $permanent['completed_view'] ) : 1;
		$view = max( 1, min( GameState::VIEW_COMPARISON, $view ) );

		$result_type = isset( $permanent['result_type'] ) ? sanitize_key( (string) $permanent['result_type'] ) : 'correct';

		if ( ! in_array( $result_type, array( 'correct', 'idk' ), true ) ) {
			$result_type = 'correct';
		}

		$overlay = array(
			'game_locked'            => true,
			'show_completion'        => true,
			'completion_result'      => $result_type,
			'feedback'               => $result_type,
			'current_view'           => $view,
			'show_idk'               => false,
			'show_registration'      => false,
			'correct_location_label' => '' !== $key ? $display->get_location_label( $game_id, $key ) : '',
		);

		$historical = $display->get_historical_information( $game_id );

		if ( '' !== $historical ) {
			$overlay['historical_information'] = $historical;
		}

		if ( $game_number >= 2 && $game_number <= 9 ) {
			$play = PlayPage::get_url();

			$overlay['show_proceed_next_game'] = true;
			$overlay['proceed_next_game_url']  = '' !== $play ? $play : home_url( '/' );
		}

		if ( GameState::VIEW_COMPARISON === $view ) {
			$overlay['show_comparison']   = true;
			$overlay['show_large_image']  = false;
			$overlay['comparison_images'] = $display->get_comparison_images( $game_id );
		}

		return $overlay;
	}

	/**
	 * Public notice after a deliberate WordPress logout.
	 */
	private function render_logged_out_notice(): string {
		$login = wp_login_url( AuthNav::play_destination() );

		return '<div class="lk-game-message lk-logged-out" role="status">'
			. '<p>'
			. esc_html__( 'Thanks for playing Local Knowledge — at least for now.', 'local-knowledge' )
			. '</p>'
			. '<p><a href="' . esc_url( $login ) . '">'
			. esc_html__( 'Log In', 'local-knowledge' )
			. '</a></p>'
			. '</div>';
	}

	/**
	 * [lk_dashboard] — foundation only.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_dashboard( $atts = array() ): string {
		unset( $atts );

		$renderer = new DashboardRenderer();

		return $renderer->render();
	}

	/**
	 * [lk_game id="N"] — direct published Game by post ID.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_direct_game( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'lk_game'
		);

		$game_id = absint( $atts['id'] );

		if ( $game_id < 1 ) {
			return $this->message_box( __( 'This Game is not available.', 'local-knowledge' ) );
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return $this->message_box( __( 'This Game is not available.', 'local-knowledge' ) );
		}

		$display = new GameDisplayData();

		if ( array() !== $display->get_completeness_errors( $game_id ) ) {
			return $this->message_box( __( 'This Game is not available.', 'local-knowledge' ) );
		}

		$game_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( $game_number < 1 ) {
			return $this->message_box( __( 'This Game is not available.', 'local-knowledge' ) );
		}

		if ( ! $this->may_render_direct_game( $game_number ) ) {
			return $this->message_box( __( 'This Game is not available for you right now.', 'local-knowledge' ) );
		}

		return $this->render_game_html( $game_id, $game_number );
	}

	/**
	 * Clear Play page discovery cache when pages change.
	 */
	public function clear_play_page_cache(): void {
		PlayPage::clear_cache();
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function clear_play_page_cache_on_trash( int $post_id ): void {
		if ( 'page' === get_post_type( $post_id ) ) {
			PlayPage::clear_cache();
		}
	}

	/**
	 * Eligibility for [lk_game] (non-admins cannot skip ahead).
	 *
	 * @param int $game_number Game Number of the requested post.
	 */
	private function may_render_direct_game( int $game_number ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$resolver = new CurrentGameResolver();
		$user_id  = is_user_logged_in() ? get_current_user_id() : 0;
		$resolved = $resolver->resolve( $user_id );

		return isset( $resolved['status'], $resolved['game_number'] )
			&& 'play' === $resolved['status']
			&& absint( $resolved['game_number'] ) === $game_number;
	}

	/**
	 * Process (if needed) and render a Game as an HTML fragment.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @param array<string, mixed> $overlay     Extra view data.
	 */
	private function render_game_html( int $game_id, int $game_number, array $overlay = array() ): string {
		nocache_headers();

		$screen = new PublicGameScreen();

		if ( ! $this->processed_post ) {
			$screen->process_request( $game_id, $game_number );
		}

		$view     = $screen->build_view( $game_id, $game_number, $overlay );
		$renderer = new GameRenderer();

		return $renderer->render_embedded( $view );
	}

	/**
	 * Safe status message wrapper.
	 *
	 * @param string $message Message text.
	 */
	private function message_box( string $message ): string {
		return '<div class="lk-game-message" role="status"><p>'
			. esc_html( $message )
			. '</p></div>';
	}
}
