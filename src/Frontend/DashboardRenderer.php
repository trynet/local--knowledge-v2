<?php
/**
 * Basic Dashboard foundation data and markup.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Player\CurrentGameResolver;
use JoyOfCode\LocalKnowledge\Player\PlayerResultRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares a minimal logged-in Dashboard placeholder.
 */
final class DashboardRenderer {

	/**
	 * Style handle for Dashboard layout.
	 */
	private const STYLE_HANDLE = 'lk-dashboard';

	/**
	 * Permanent results.
	 */
	private PlayerResultRepository $results;

	/**
	 * Current Game resolver.
	 */
	private CurrentGameResolver $resolver;

	/**
	 * Constructor.
	 */
	public function __construct(
		?PlayerResultRepository $results = null,
		?CurrentGameResolver $resolver = null
	) {
		$this->results  = $results ?? new PlayerResultRepository();
		$this->resolver = $resolver ?? new CurrentGameResolver( $this->results );
	}

	/**
	 * Render Dashboard foundation HTML.
	 */
	public function render(): string {
		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			ob_start();
			wp_print_styles( self::STYLE_HANDLE );
			?>
			<div class="lk-dashboard lk-dashboard--guest">
				<div class="lk-dashboard__how-to-play">
					<h2 class="lk-how-to-play"><?php esc_html_e( 'How To Play The Game', 'local-knowledge' ); ?></h2>
					<?php echo HowToPlay::instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
				</div>
				<p class="lk-dashboard__login-prompt">
					<?php esc_html_e( 'Please log in to view your Dashboard.', 'local-knowledge' ); ?>
				</p>
			</div>
			<?php
			$html = ob_get_clean();

			return is_string( $html ) ? $html : '';
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$name    = $user instanceof \WP_User ? $user->display_name : '';
		$total   = $this->results->get_total_points( $user_id );
		$count   = $this->results->count_completed( $user_id );
		$resolved = $this->resolver->resolve( $user_id );

		$current_label = __( 'None available', 'local-knowledge' );

		if ( isset( $resolved['status'], $resolved['game_number'] )
			&& 'play' === $resolved['status']
		) {
			$current_label = (string) absint( $resolved['game_number'] );
		} elseif ( isset( $resolved['status'] ) && 'awaiting_next' === $resolved['status']
			&& isset( $resolved['game_number'] )
		) {
			$current_label = sprintf(
				/* translators: %d: next game number */
				__( '%d (not published yet)', 'local-knowledge' ),
				absint( $resolved['game_number'] )
			);
		}

		ob_start();
		wp_print_styles( self::STYLE_HANDLE );
		?>
		<div class="lk-dashboard lk-dashboard--player">
			<div class="lk-dashboard__score">
				<p class="lk-dashboard__name">
					<strong><?php esc_html_e( 'Player:', 'local-knowledge' ); ?></strong>
					<?php echo esc_html( $name ); ?>
				</p>
				<p class="lk-dashboard__total">
					<strong><?php esc_html_e( 'Total score:', 'local-knowledge' ); ?></strong>
					<?php
					printf(
						/* translators: %d: total points */
						esc_html__( '%d points', 'local-knowledge' ),
						$total
					);
					?>
				</p>
				<p class="lk-dashboard__completed">
					<strong><?php esc_html_e( 'Completed Games:', 'local-knowledge' ); ?></strong>
					<?php echo esc_html( (string) $count ); ?>
				</p>
				<p class="lk-dashboard__current">
					<strong><?php esc_html_e( 'Current Game:', 'local-knowledge' ); ?></strong>
					<?php echo esc_html( $current_label ); ?>
				</p>
			</div>
			<div class="lk-dashboard__how-to-play">
				<h2 class="lk-how-to-play"><?php esc_html_e( 'How To Play The Game', 'local-knowledge' ); ?></h2>
				<?php echo HowToPlay::instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Enqueue Dashboard layout styles.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			LK_PLUGIN_URL . 'assets/css/dashboard.css',
			array(),
			LK_VERSION
		);
	}
}
