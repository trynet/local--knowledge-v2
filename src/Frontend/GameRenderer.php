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

		$game_number = $prepared['game_number'];
		$image_url   = $prepared['image_url'];
		$image_alt   = $prepared['image_alt'];
		$locations   = $prepared['locations'];
		$is_preview  = $prepared['is_preview'];

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
<body class="lk-game-screen<?php echo $is_preview ? ' lk-game-screen--preview' : ''; ?>">
<?php
		include LK_PLUGIN_DIR . 'templates/game.php';
?>
</body>
</html>
		<?php
	}

	/**
	 * Validate and normalize renderer input.
	 *
	 * Collects every display-field problem before failing.
	 *
	 * @param array<string, mixed> $view Raw view data.
	 * @return array{
	 *     game_number: int,
	 *     image_url: string,
	 *     image_alt: string,
	 *     locations: array{1: string, 2: string, 3: string, 4: string},
	 *     is_preview: bool
	 * }
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
			$errors[] = __( 'Image 1 is required.', 'local-knowledge' );
		} elseif ( '' === $image_url ) {
			$errors[] = __( 'Image 1 is unavailable or invalid.', 'local-knowledge' );
		}

		$image_alt = isset( $view['image_alt'] )
			? sanitize_text_field( (string) $view['image_alt'] )
			: '';

		if ( '' === $image_alt && $game_number > 0 ) {
			$image_alt = sprintf(
				/* translators: %d: game number */
				__( 'Game %d image 1', 'local-knowledge' ),
				$game_number
			);
		} elseif ( '' === $image_alt ) {
			$image_alt = __( 'Game image 1', 'local-knowledge' );
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

		return array(
			'game_number' => $game_number,
			'image_url'   => $image_url,
			'image_alt'   => $image_alt,
			'locations'   => $locations,
			'is_preview'  => ! empty( $view['is_preview'] ),
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
