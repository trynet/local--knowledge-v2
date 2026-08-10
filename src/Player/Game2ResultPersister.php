<?php
/**
 * Permanently records Game 2 completion for logged-in players.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Frontend\GameDisplayData;
use JoyOfCode\LocalKnowledge\Frontend\GameState;

defined( 'ABSPATH' ) || exit;

/**
 * Appends one Game 2 result after GamePlay has already locked GameState.
 *
 * Does not evaluate answers or mutate gameplay state.
 * Game 1 remains registration-only. Games 3–10 are out of scope.
 */
final class Game2ResultPersister {

	/**
	 * Game Number handled by this persister.
	 */
	private const GAME_NUMBER = 2;

	/**
	 * Permanent results store.
	 */
	private PlayerResultRepository $results;

	/**
	 * Score calculator.
	 */
	private ScoreCalculator $scores;

	/**
	 * Completeness helper.
	 */
	private GameDisplayData $display;

	/**
	 * Constructor.
	 */
	public function __construct(
		?PlayerResultRepository $results = null,
		?ScoreCalculator $scores = null,
		?GameDisplayData $display = null
	) {
		$this->results = $results ?? new PlayerResultRepository();
		$this->scores  = $scores ?? new ScoreCalculator();
		$this->display = $display ?? new GameDisplayData();
	}

	/**
	 * Persist Game 2 when eligibility and locked GameState are already satisfied.
	 *
	 * @param int                  $game_id     Published Game post ID.
	 * @param int                  $game_number Game Number from the play path.
	 * @param array<string, mixed> $state       Authoritative GameState after lock + save.
	 */
	public function maybe_persist( int $game_id, int $game_number, array $state ): void {
		if ( self::GAME_NUMBER !== $game_number || $game_id < 1 ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return;
		}

		if ( ! $this->results->has_result( $user_id, 1 ) ) {
			return;
		}

		if ( $this->results->has_result( $user_id, self::GAME_NUMBER ) ) {
			return;
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return;
		}

		$stored_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( self::GAME_NUMBER !== $stored_number ) {
			return;
		}

		if ( array() !== $this->display->get_completeness_errors( $game_id ) ) {
			return;
		}

		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';

		if ( ! $ended || ! in_array( $result, array( 'correct', 'idk' ), true ) ) {
			return;
		}

		$view = absint( $state['current_view'] ?? 0 );

		if ( $view < GameState::VIEW_MIN || $view > GameState::VIEW_COMPARISON ) {
			return;
		}

		$points = $this->scores->calculate( $view, $result );

		$this->results->save_result(
			$user_id,
			array(
				'game_id'        => $game_id,
				'game_number'    => self::GAME_NUMBER,
				'completed_view' => $view,
				'result_type'    => $result,
				'points'         => $points,
				'completed_at'   => gmdate( 'Y-m-d H:i:s' ),
				'status'         => 'completed',
			)
		);
	}
}
