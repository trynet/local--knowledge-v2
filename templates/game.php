<?php
/**
 * Reusable Game screen markup.
 *
 * Expected variables (provided by GameRenderer):
 * - int    $game_number
 * - string $image_url
 * - string $image_alt
 * - array  $locations  Keys "1"–"4" with location labels.
 * - bool   $is_preview
 *
 * @package JoyOfCode\LocalKnowledge
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $game_number, $image_url, $image_alt, $locations, $is_preview ) ) {
	return;
}
?>
<main class="lk-game">
	<?php if ( $is_preview ) : ?>
		<div class="lk-game__preview-notice" role="status">
			<?php esc_html_e( 'Preview Mode — No player progress or scores will be recorded.', 'local-knowledge' ); ?>
		</div>
	<?php endif; ?>

	<header class="lk-game__header">
		<h1 class="lk-game__title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: game number */
					__( 'Game %d', 'local-knowledge' ),
					(int) $game_number
				)
			);
			?>
		</h1>
	</header>

	<figure class="lk-game__image-frame">
		<img
			class="lk-game__image"
			src="<?php echo esc_url( (string) $image_url ); ?>"
			alt="<?php echo esc_attr( (string) $image_alt ); ?>"
		/>
	</figure>

	<form class="lk-game__form" action="#" method="post">
		<fieldset class="lk-game__choices">
			<legend class="lk-game__choices-legend">
				<?php esc_html_e( 'Choose a location', 'local-knowledge' ); ?>
			</legend>

			<?php foreach ( $locations as $index => $label ) : ?>
				<?php
				$input_id = 'lk-location-' . sanitize_key( (string) $index );
				?>
				<div class="lk-game__choice">
					<input
						type="radio"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="lk_location"
						value="<?php echo esc_attr( (string) $index ); ?>"
					/>
					<label for="<?php echo esc_attr( $input_id ); ?>">
						<?php echo esc_html( (string) $label ); ?>
					</label>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<button type="button" class="lk-game__submit">
			<?php esc_html_e( 'Submit', 'local-knowledge' ); ?>
		</button>
	</form>
</main>
