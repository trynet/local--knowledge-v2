<?php
/**
 * Renders the player-facing Game screen from prepared view data.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Presentation-only Game renderer.
 */
final class GameRenderer {

	/**
	 * Style handle for the Game screen.
	 */
	public const STYLE_HANDLE = 'lk-game';

	/**
	 * Script handle for comparison-image enlargement.
	 */
	public const SCRIPT_HANDLE = 'lk-game-comparison';

	/**
	 * Render a complete standalone Game page.
	 *
	 * @param array<string, mixed> $view Prepared view data.
	 */
	public function render( array $view ): void {
		$prepared = $this->prepare_view( $view );

		$this->enqueue_assets( ! empty( $prepared['show_comparison'] ) );

		$game_number            = $prepared['game_number'];
		$image_url              = $prepared['image_url'];
		$image_alt              = $prepared['image_alt'];
		$locations              = $prepared['locations'];
		$is_preview             = $prepared['is_preview'];
		$playable               = $prepared['playable'];
		$game_id                = $prepared['game_id'];
		$nonce_action           = $prepared['nonce_action'];
		$nonce_field            = $prepared['nonce_field'];
		$form_action_value      = $prepared['form_action_value'];
		$feedback               = $prepared['feedback'];
		$selected_choice        = $prepared['selected_choice'];
		$game_locked            = $prepared['game_locked'];
		$correct_location_label = $prepared['correct_location_label'];
		$clean_game_url         = $prepared['clean_game_url'];
		$strip_flash_from_url   = $prepared['strip_flash_from_url'];
		$current_view           = $prepared['current_view'];
		$show_large_image       = $prepared['show_large_image'];
		$show_comparison        = $prepared['show_comparison'];
		$show_idk               = $prepared['show_idk'];
		$comparison_images      = $prepared['comparison_images'];
		$show_completion        = $prepared['show_completion'];
		$completion_result      = $prepared['completion_result'];
		$show_registration      = $prepared['show_registration'];
		$registration_prompt    = $prepared['registration_prompt'];
		$registration_success   = $prepared['registration_success'];
		$registration_success_message = $prepared['registration_success_message'];
		$registration_errors    = $prepared['registration_errors'];
		$registration_values    = $prepared['registration_values'];
		$registration_nonce_action = $prepared['registration_nonce_action'];
		$registration_nonce_field  = $prepared['registration_nonce_field'];
		$registration_form_action_value = $prepared['registration_form_action_value'];
		$registration_info      = $prepared['registration_info'];
		$show_post_registration = $prepared['show_post_registration'];
		$post_registration_title = $prepared['post_registration_title'];
		$post_registration_messages = $prepared['post_registration_messages'];
		$player_points          = $prepared['player_points'];
		$show_continue_game_2   = $prepared['show_continue_game_2'];
		$continue_game_2_url    = $prepared['continue_game_2_url'];
		$continue_game_2_label  = $prepared['continue_game_2_label'];
		$game_2_unavailable     = $prepared['game_2_unavailable'];

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: game number */
				__( 'Game %d', 'local-knowledge' ),
				$game_number
			)
		);
		?>
	</title>
	<?php wp_print_styles( self::STYLE_HANDLE ); ?>
</head>
<body class="lk-game-screen<?php echo $is_preview ? ' lk-game-screen--preview' : ''; ?><?php echo $game_locked ? ' lk-game-screen--locked' : ''; ?><?php echo $show_comparison ? ' lk-game-screen--comparison' : ''; ?>">
<?php
		include LK_PLUGIN_DIR . 'templates/game.php';

		if ( $show_comparison ) {
			wp_print_scripts( self::SCRIPT_HANDLE );
		}

		if ( $strip_flash_from_url && '' !== $clean_game_url ) :
			?>
<script>
(function () {
	if (!window.history || typeof window.history.replaceState !== 'function') {
		return;
	}
	window.history.replaceState(null, document.title, <?php echo wp_json_encode( $clean_game_url ); ?>);
})();
</script>
			<?php
		endif;
?>
</body>
</html>
		<?php
	}

	/**
	 * Validate and normalize renderer input.
	 *
	 * @param array<string, mixed> $view Raw view data.
	 * @return array<string, mixed>
	 */
	private function prepare_view( array $view ): array {
		$errors = array();

		$game_number = isset( $view['game_number'] ) ? absint( $view['game_number'] ) : 0;

		if ( $game_number < 1 ) {
			$errors[] = __( 'Game Number is required.', 'local-knowledge' );
		}

		$current_view    = isset( $view['current_view'] ) ? absint( $view['current_view'] ) : 1;
		$current_view    = max( 1, min( GameState::VIEW_COMPARISON, $current_view ) );
		$show_comparison = ! empty( $view['show_comparison'] ) || GameState::VIEW_COMPARISON === $current_view;
		$show_large      = ! $show_comparison && ( ! isset( $view['show_large_image'] ) || ! empty( $view['show_large_image'] ) );

		$image_id  = isset( $view['image_id'] ) ? absint( $view['image_id'] ) : 0;
		$image_url = isset( $view['image_url'] ) ? esc_url_raw( (string) $view['image_url'] ) : '';
		$image_alt = isset( $view['image_alt'] )
			? sanitize_text_field( (string) $view['image_alt'] )
			: '';

		if ( $show_large ) {
			if ( $image_id < 1 && '' === $image_url ) {
				$errors[] = __( 'Current image is required.', 'local-knowledge' );
			} elseif ( '' === $image_url ) {
				$errors[] = __( 'Current image is unavailable or invalid.', 'local-knowledge' );
			}

			if ( '' === $image_alt && $game_number > 0 ) {
				$image_alt = sprintf(
					/* translators: %d: game number */
					__( 'Game %d image', 'local-knowledge' ),
					$game_number
				);
			} elseif ( '' === $image_alt ) {
				$image_alt = __( 'Game image', 'local-knowledge' );
			}
		} else {
			$image_url = '';
			$image_alt = '';
		}

		$raw_locations = isset( $view['locations'] ) && is_array( $view['locations'] )
			? $view['locations']
			: array();

		$locations = array();

		for ( $i = 1; $i <= 4; $i++ ) {
			$key      = (string) $i;
			$location = isset( $raw_locations[ $key ] )
				? sanitize_text_field( (string) $raw_locations[ $key ] )
				: '';

			if ( '' === $location ) {
				$errors[] = sprintf(
					/* translators: %d: location choice number */
					__( 'Location Choice %d is required.', 'local-knowledge' ),
					$i
				);
			}

			$locations[ $key ] = $location;
		}

		$comparison_images = array();

		if ( $show_comparison ) {
			$raw_images = isset( $view['comparison_images'] ) && is_array( $view['comparison_images'] )
				? $view['comparison_images']
				: array();

			foreach ( $raw_images as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$url  = isset( $item['image_url'] ) ? esc_url_raw( (string) $item['image_url'] ) : '';
				$full = isset( $item['full_url'] ) ? esc_url_raw( (string) $item['full_url'] ) : $url;
				$alt  = isset( $item['image_alt'] ) ? sanitize_text_field( (string) $item['image_alt'] ) : '';

				if ( '' === $url ) {
					continue;
				}

				if ( '' === $full ) {
					$full = $url;
				}

				$comparison_images[] = array(
					'stage'     => isset( $item['stage'] ) ? max( 1, min( 4, absint( $item['stage'] ) ) ) : 0,
					'image_url' => $url,
					'full_url'  => $full,
					'image_alt' => $alt,
				);
			}

			if ( count( $comparison_images ) < 4 ) {
				$errors[] = __( 'Comparison images are incomplete.', 'local-knowledge' );
			}
		}

		if ( array() !== $errors ) {
			$this->fail( $errors );
		}

		$is_preview  = ! empty( $view['is_preview'] );
		$playable    = ! $is_preview && ! empty( $view['playable'] );
		$game_locked = $playable && (
			! empty( $view['game_locked'] )
			|| ! empty( $view['show_post_registration'] )
		);
		$show_idk    = $playable && ! $game_locked && $show_comparison && ! empty( $view['show_idk'] );

		$correct_label = '';

		if ( $game_locked && isset( $view['correct_location_label'] ) ) {
			$correct_label = sanitize_text_field( (string) $view['correct_location_label'] );
		}

		$selected = isset( $view['selected_choice'] )
			? sanitize_text_field( (string) $view['selected_choice'] )
			: '';

		if ( ! in_array( $selected, array( '1', '2', '3', '4', 'idk' ), true ) ) {
			$selected = '';
		}

		$feedback_key = isset( $view['feedback'] ) ? sanitize_key( (string) $view['feedback'] ) : '';
		$completion_result = isset( $view['completion_result'] ) ? sanitize_key( (string) $view['completion_result'] ) : '';

		if ( ! in_array( $completion_result, array( 'correct', 'idk' ), true ) ) {
			// Fall back to authoritative locked feedback from GamePlay (Views 1–5).
			$completion_result = in_array( $feedback_key, array( 'correct', 'idk' ), true )
				? $feedback_key
				: '';
		}

		// Completion follows game_locked + result type — not an unrelated view flag
		// that can stay false on View 1 after a correct lock.
		$show_completion = $playable && $game_locked && (
			! empty( $view['show_completion'] )
			|| in_array( $completion_result, array( 'correct', 'idk' ), true )
		);

		$show_registration = $show_completion && ! empty( $view['show_registration'] ) && empty( $view['show_post_registration'] );
		$registration_prompt = isset( $view['registration_prompt'] )
			? sanitize_text_field( (string) $view['registration_prompt'] )
			: '';
		$registration_success = $show_registration && ! empty( $view['registration_success'] );
		$registration_success_message = isset( $view['registration_success_message'] )
			? sanitize_text_field( (string) $view['registration_success_message'] )
			: '';

		$registration_errors = array();

		if ( isset( $view['registration_errors'] ) && is_array( $view['registration_errors'] ) ) {
			foreach ( $view['registration_errors'] as $error ) {
				if ( is_string( $error ) && '' !== $error ) {
					$registration_errors[] = sanitize_text_field( $error );
				}
			}
		}

		$raw_values = isset( $view['registration_values'] ) && is_array( $view['registration_values'] )
			? $view['registration_values']
			: array();

		$registration_values = array(
			'first_name' => isset( $raw_values['first_name'] ) ? sanitize_text_field( (string) $raw_values['first_name'] ) : '',
			'last_name'  => isset( $raw_values['last_name'] ) ? sanitize_text_field( (string) $raw_values['last_name'] ) : '',
			'email'      => isset( $raw_values['email'] ) ? sanitize_text_field( (string) $raw_values['email'] ) : '',
			'username'   => isset( $raw_values['username'] ) ? sanitize_text_field( (string) $raw_values['username'] ) : '',
		);

		$registration_info = isset( $view['registration_info'] )
			? sanitize_text_field( (string) $view['registration_info'] )
			: '';

		$show_post_registration = $playable && $game_locked && ! empty( $view['show_post_registration'] );
		$post_registration_title = isset( $view['post_registration_title'] )
			? sanitize_text_field( (string) $view['post_registration_title'] )
			: '';

		$post_registration_messages = array();

		if ( isset( $view['post_registration_messages'] ) && is_array( $view['post_registration_messages'] ) ) {
			foreach ( $view['post_registration_messages'] as $message ) {
				if ( is_string( $message ) && '' !== $message ) {
					$post_registration_messages[] = sanitize_text_field( $message );
				}
			}
		}

		$player_points = null;

		if ( $show_post_registration && isset( $view['player_points'] ) && is_numeric( $view['player_points'] ) ) {
			$player_points = max( 0, min( 4, absint( $view['player_points'] ) ) );
		}

		$show_continue_game_2 = $show_post_registration && ! empty( $view['show_continue_game_2'] );
		$continue_game_2_url  = isset( $view['continue_game_2_url'] ) ? esc_url_raw( (string) $view['continue_game_2_url'] ) : '';
		$continue_game_2_label = isset( $view['continue_game_2_label'] )
			? sanitize_text_field( (string) $view['continue_game_2_label'] )
			: '';
		$game_2_unavailable = isset( $view['game_2_unavailable'] )
			? sanitize_text_field( (string) $view['game_2_unavailable'] )
			: '';

		if ( $show_continue_game_2 && '' === $continue_game_2_url ) {
			$show_continue_game_2 = false;
		}

		return array(
			'game_number'                     => $game_number,
			'image_url'                       => $image_url,
			'image_alt'                       => $image_alt,
			'locations'                       => $locations,
			'is_preview'                      => $is_preview,
			'playable'                        => $playable,
			'game_id'                         => isset( $view['game_id'] ) ? absint( $view['game_id'] ) : 0,
			'nonce_action'                    => isset( $view['nonce_action'] ) ? sanitize_text_field( (string) $view['nonce_action'] ) : '',
			'nonce_field'                     => isset( $view['nonce_field'] ) ? sanitize_key( (string) $view['nonce_field'] ) : '',
			'form_action_value'               => isset( $view['form_action_value'] ) ? sanitize_key( (string) $view['form_action_value'] ) : '',
			'feedback'                        => isset( $view['feedback'] ) ? sanitize_key( (string) $view['feedback'] ) : '',
			'selected_choice'                 => $selected,
			'game_locked'                     => $game_locked,
			'correct_location_label'          => $correct_label,
			'clean_game_url'                  => isset( $view['clean_game_url'] ) ? esc_url_raw( (string) $view['clean_game_url'] ) : '',
			'strip_flash_from_url'            => ! empty( $view['strip_flash_from_url'] ),
			'current_view'                    => $current_view,
			'show_large_image'                => $show_large,
			'show_comparison'                 => $show_comparison,
			'show_idk'                        => $show_idk,
			'comparison_images'               => $comparison_images,
			'show_completion'                 => $show_completion,
			'completion_result'               => $completion_result,
			'show_registration'               => $show_registration,
			'registration_prompt'             => $registration_prompt,
			'registration_success'            => $registration_success,
			'registration_success_message'    => $registration_success_message,
			'registration_errors'             => $registration_errors,
			'registration_values'             => $registration_values,
			'registration_info'               => $registration_info,
			'show_post_registration'          => $show_post_registration,
			'post_registration_title'         => $post_registration_title,
			'post_registration_messages'      => $post_registration_messages,
			'player_points'                   => $player_points,
			'show_continue_game_2'            => $show_continue_game_2,
			'continue_game_2_url'             => $continue_game_2_url,
			'continue_game_2_label'           => $continue_game_2_label,
			'game_2_unavailable'              => $game_2_unavailable,
			'registration_nonce_action'       => isset( $view['registration_nonce_action'] ) ? sanitize_text_field( (string) $view['registration_nonce_action'] ) : '',
			'registration_nonce_field'        => isset( $view['registration_nonce_field'] ) ? sanitize_key( (string) $view['registration_nonce_field'] ) : 'lk_register_nonce',
			'registration_form_action_value'  => isset( $view['registration_form_action_value'] ) ? sanitize_key( (string) $view['registration_form_action_value'] ) : RegistrationGateway::FORM_ACTION,
		);
	}

	/**
	 * Enqueue Game screen styles and optional comparison script.
	 *
	 * @param bool $with_comparison Whether to load comparison enlargement JS.
	 */
	private function enqueue_assets( bool $with_comparison ): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			LK_PLUGIN_URL . 'assets/css/game.css',
			array(),
			LK_VERSION
		);

		if ( $with_comparison ) {
			wp_enqueue_script(
				self::SCRIPT_HANDLE,
				LK_PLUGIN_URL . 'assets/js/game-comparison.js',
				array(),
				LK_VERSION,
				true
			);
		}
	}

	/**
	 * Stop rendering and list every collected validation problem.
	 *
	 * @param list<string> $errors Human-readable validation messages.
	 */
	private function fail( array $errors ): void {
		$message  = '<p><strong>' . esc_html__( 'This Game cannot be displayed because it is incomplete.', 'local-knowledge' ) . '</strong></p>';
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
