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
			return '<div class="lk-dashboard lk-dashboard--guest"><p>'
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
}
