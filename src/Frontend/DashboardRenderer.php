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
		if ( ! is_user_logged_in() ) {
			return '<div class="lk-dashboard lk-dashboard--guest">'
				. '<h2 class="lk-how-to-play">' . esc_html__( 'How To Play The Game', 'local-knowledge' ) . '</h2>'
				. $this->how_to_play_instructions_html()
				. '<p>'
				. esc_html__( 'Please log in to view your Dashboard.', 'local-knowledge' )
				. '</p></div>';
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
		?>
		<div class="lk-dashboard lk-dashboard--player">
			<h2 class="lk-how-to-play"><?php esc_html_e( 'How To Play The Game', 'local-knowledge' ); ?></h2>
			<?php echo $this->how_to_play_instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
			<p class="lk-dashboard__name">
				<?php
				printf(
					/* translators: %s: player display name */
					esc_html__( 'Player: %s', 'local-knowledge' ),
					esc_html( $name )
				);
				?>
			</p>
			<p class="lk-dashboard__total">
				<?php
				printf(
					/* translators: %d: total points */
					esc_html__( 'Total score: %d points', 'local-knowledge' ),
					$total
				);
				?>
			</p>
			<p class="lk-dashboard__completed">
				<?php
				printf(
					/* translators: %d: number of completed games */
					esc_html__( 'Completed Games: %d', 'local-knowledge' ),
					$count
				);
				?>
			</p>
			<p class="lk-dashboard__current">
				<?php
				printf(
					/* translators: %s: current game number or status */
					esc_html__( 'Current Game: %s', 'local-knowledge' ),
					esc_html( $current_label )
				);
				?>
			</p>
		</div>
		<?php
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Escaped How To Play instruction paragraphs.
	 */
	private function how_to_play_instructions_html(): string {
		return '<p>' . esc_html__( 'You’ll see a photo of a location and four possible answers. Choose the location you think is correct and click Submit.', 'local-knowledge' ) . '</p>'
			. '<p>' . esc_html__( 'A correct answer on the first photo earns 4 points. If you’re wrong, another photo is revealed and the possible score drops by one point with each additional photo.', 'local-knowledge' ) . '</p>'
			. '<p>' . esc_html__( 'After all four photos have been revealed, you can continue guessing or choose I Don’t Know. An incorrect final answer or I Don’t Know earns 0 points.', 'local-knowledge' ) . '</p>'
			. '<p>' . esc_html__( 'There are 10 games. Your scores from all 10 games are added together for your final score.', 'local-knowledge' ) . '</p>';
	}
}
