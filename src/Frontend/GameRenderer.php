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
	 * Render a complete standalone Game page.
	 *
	 * @param array<string, mixed> $view Prepared view data.
	 */
	public function render( array $view ): void {
		$prepared = $this->prepare_view( $view );

		$this->enqueue_assets();

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
		$image_stage            = $prepared['image_stage'];
		$show_idk               = $prepared['show_idk'];
		$show_thumbnails        = $prepared['show_thumbnails'];
		$thumbnails             = $prepared['thumbnails'];

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
<body class="lk-game-screen<?php echo $is_preview ? ' lk-game-screen--preview' : ''; ?><?php echo $game_locked ? ' lk-game-screen--locked' : ''; ?>">
<?php
		include LK_PLUGIN_DIR . 'templates/game.php';

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

		$image_id  = isset( $view['image_id'] ) ? absint( $view['image_id'] ) : 0;
		$image_url = isset( $view['image_url'] ) ? esc_url_raw( (string) $view['image_url'] ) : '';

		if ( $image_id < 1 && '' === $image_url ) {
			$errors[] = __( 'Current image is required.', 'local-knowledge' );
		} elseif ( '' === $image_url ) {
			$errors[] = __( 'Current image is unavailable or invalid.', 'local-knowledge' );
		}

		$image_alt = isset( $view['image_alt'] )
			? sanitize_text_field( (string) $view['image_alt'] )
			: '';

		if ( '' === $image_alt && $game_number > 0 ) {
			$image_alt = sprintf(
				/* translators: %d: game number */
				__( 'Game %d image', 'local-knowledge' ),
				$game_number
			);
		} elseif ( '' === $image_alt ) {
			$image_alt = __( 'Game image', 'local-knowledge' );
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

		if ( array() !== $errors ) {
			$this->fail( $errors );
		}

		$is_preview      = ! empty( $view['is_preview'] );
		$playable        = ! $is_preview && ! empty( $view['playable'] );
		$game_locked     = $playable && ! empty( $view['game_locked'] );
		$image_stage     = isset( $view['image_stage'] ) ? max( 1, min( 4, absint( $view['image_stage'] ) ) ) : 1;
		$show_idk        = $playable && ! $game_locked && ! empty( $view['show_idk'] ) && 4 === $image_stage;
		$show_thumbnails = $playable && ! empty( $view['show_thumbnails'] ) && 4 === $image_stage;

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

		$thumbnails = array();

		if ( $show_thumbnails && isset( $view['thumbnails'] ) && is_array( $view['thumbnails'] ) ) {
			foreach ( $view['thumbnails'] as $thumb ) {
				if ( ! is_array( $thumb ) ) {
					continue;
				}

				$url = isset( $thumb['image_url'] ) ? esc_url_raw( (string) $thumb['image_url'] ) : '';
				$alt = isset( $thumb['image_alt'] ) ? sanitize_text_field( (string) $thumb['image_alt'] ) : '';

				if ( '' === $url ) {
					continue;
				}

				$thumbnails[] = array(
					'stage'     => isset( $thumb['stage'] ) ? max( 1, min( 4, absint( $thumb['stage'] ) ) ) : 0,
					'image_url' => $url,
					'image_alt' => $alt,
				);
			}
		}

		if ( $show_thumbnails && count( $thumbnails ) < 4 ) {
			$show_thumbnails = false;
			$thumbnails      = array();
		}

		return array(
			'game_number'            => $game_number,
			'image_url'              => $image_url,
			'image_alt'              => $image_alt,
			'locations'              => $locations,
			'is_preview'             => $is_preview,
			'playable'               => $playable,
			'game_id'                => isset( $view['game_id'] ) ? absint( $view['game_id'] ) : 0,
			'nonce_action'           => isset( $view['nonce_action'] ) ? sanitize_text_field( (string) $view['nonce_action'] ) : '',
			'nonce_field'            => isset( $view['nonce_field'] ) ? sanitize_key( (string) $view['nonce_field'] ) : '',
			'form_action_value'      => isset( $view['form_action_value'] ) ? sanitize_key( (string) $view['form_action_value'] ) : '',
			'feedback'               => isset( $view['feedback'] ) ? sanitize_key( (string) $view['feedback'] ) : '',
			'selected_choice'        => $selected,
			'game_locked'            => $game_locked,
			'correct_location_label' => $correct_label,
			'clean_game_url'         => isset( $view['clean_game_url'] ) ? esc_url_raw( (string) $view['clean_game_url'] ) : '',
			'strip_flash_from_url'   => ! empty( $view['strip_flash_from_url'] ),
			'image_stage'            => $image_stage,
			'show_idk'               => $show_idk,
			'show_thumbnails'        => $show_thumbnails,
			'thumbnails'             => $thumbnails,
		);
	}

	/**
	 * Enqueue Game screen styles for the current response.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			LK_PLUGIN_URL . 'assets/css/game.css',
			array(),
			LK_VERSION
		);
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
