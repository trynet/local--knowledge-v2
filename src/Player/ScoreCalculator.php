<?php
/**
 * Calculates Game points from authoritative completion data.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

use JoyOfCode\LocalKnowledge\Frontend\GameState;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side scoring only — never trusts client-submitted points.
 */
final class ScoreCalculator {

	/**
	 * Points for a correct answer by completed view (1–5).
	 *
	 * @var array<int, int>
	 */
	private const POINTS_BY_VIEW = array(
		1 => 4,
		2 => 3,
		3 => 2,
		4 => 1,
		5 => 0,
	);

	/**
	 * Calculate points from authoritative view and result type.
	 *
	 * @param int    $current_view Completed view 1–5.
	 * @param string $result_type  correct|idk.
	 */
	public function calculate( int $current_view, string $result_type ): int {
		$result_type = sanitize_key( $result_type );
		$view        = max( GameState::VIEW_MIN, min( GameState::VIEW_COMPARISON, $current_view ) );

		if ( 'idk' === $result_type ) {
			return 0;
		}

		if ( 'correct' !== $result_type ) {
			return 0;
		}

		return self::POINTS_BY_VIEW[ $view ] ?? 0;
	}
}
