<?php
/**
 * Reusable Game screen markup.
 *
 * Expected variables (provided by GameRenderer):
 * - int    $game_number
 * - string $image_url
 * - string $image_alt
 * - array  $locations
 * - bool   $is_preview
 * - bool   $playable
 * - int    $game_id
 * - string $nonce_action
 * - string $nonce_field
 * - string $form_action_value
 * - string $feedback
 * - string $selected_choice
 * - bool   $game_locked
 * - string $correct_location_label
 * - int    $current_view
 * - bool   $show_large_image
 * - bool   $show_comparison
 * - bool   $show_idk
 * - array  $comparison_images
 * - bool   $show_completion
 * - string $completion_result
 * - bool   $show_registration
 * - string $registration_prompt
 * - bool   $registration_success
 * - string $registration_success_message
 * - array  $registration_errors
 * - array  $registration_values
 * - string $registration_nonce_action
 * - string $registration_nonce_field
 * - string $registration_form_action_value
 *
 * @package JoyOfCode\LocalKnowledge
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $game_number, $locations, $is_preview ) ) {
	return;
}

$playable               = ! empty( $playable );
$game_locked            = ! empty( $game_locked );
$feedback               = isset( $feedback ) ? (string) $feedback : '';
$selected_choice        = isset( $selected_choice ) ? (string) $selected_choice : '';
$correct_location_label = isset( $correct_location_label ) ? (string) $correct_location_label : '';
$game_id                = isset( $game_id ) ? (int) $game_id : 0;
$nonce_action           = isset( $nonce_action ) ? (string) $nonce_action : '';
$nonce_field            = isset( $nonce_field ) ? (string) $nonce_field : '';
$form_action_value      = isset( $form_action_value ) ? (string) $form_action_value : '';
$image_url              = isset( $image_url ) ? (string) $image_url : '';
$image_alt              = isset( $image_alt ) ? (string) $image_alt : '';
$show_large_image       = ! empty( $show_large_image );
$show_comparison        = ! empty( $show_comparison );
$show_idk               = ! empty( $show_idk );
$comparison_images      = isset( $comparison_images ) && is_array( $comparison_images ) ? $comparison_images : array();
$show_completion        = ! empty( $show_completion );
$completion_result      = isset( $completion_result ) ? (string) $completion_result : '';
$show_registration      = ! empty( $show_registration );
$registration_prompt    = isset( $registration_prompt ) ? (string) $registration_prompt : '';
$registration_success   = ! empty( $registration_success );
$registration_success_message = isset( $registration_success_message ) ? (string) $registration_success_message : '';
$registration_errors    = isset( $registration_errors ) && is_array( $registration_errors ) ? $registration_errors : array();
$registration_values    = isset( $registration_values ) && is_array( $registration_values ) ? $registration_values : array();
$reg_first              = isset( $registration_values['first_name'] ) ? (string) $registration_values['first_name'] : '';
$reg_last               = isset( $registration_values['last_name'] ) ? (string) $registration_values['last_name'] : '';
$reg_email              = isset( $registration_values['email'] ) ? (string) $registration_values['email'] : '';
$reg_username           = isset( $registration_values['username'] ) ? (string) $registration_values['username'] : '';
$registration_nonce_action = isset( $registration_nonce_action ) ? (string) $registration_nonce_action : '';
$registration_nonce_field  = isset( $registration_nonce_field ) ? (string) $registration_nonce_field : 'lk_register_nonce';
$registration_form_action_value = isset( $registration_form_action_value ) ? (string) $registration_form_action_value : '';
$has_reg_errors         = array() !== $registration_errors;
$registration_info      = isset( $registration_info ) ? (string) $registration_info : '';
$show_game1_handoff     = ! empty( $show_game1_handoff );
if ( ! isset( $game1_handoff_points ) || null === $game1_handoff_points ) {
	$game1_handoff_points = null;
} else {
	$game1_handoff_points = (int) $game1_handoff_points;
}
if ( ! isset( $current_total_points ) || null === $current_total_points ) {
	$current_total_points = $game1_handoff_points;
} else {
	$current_total_points = (int) $current_total_points;
}
$handoff_game_number = isset( $handoff_game_number ) ? (int) $handoff_game_number : 1;
$handoff_historical_information = isset( $handoff_historical_information ) ? (string) $handoff_historical_information : '';
$historical_information = isset( $historical_information ) ? (string) $historical_information : '';
$show_proceed_next_game = ! empty( $show_proceed_next_game );
$proceed_next_game_url  = isset( $proceed_next_game_url ) ? (string) $proceed_next_game_url : '';
$proceed_next_game_number = isset( $proceed_next_game_number ) ? (int) $proceed_next_game_number : 0;
$total_games = isset( $total_games ) ? max( 1, (int) $total_games ) : 10;
$show_post_registration = ! empty( $show_post_registration );
$post_registration_title = isset( $post_registration_title ) ? (string) $post_registration_title : '';
$post_registration_messages = isset( $post_registration_messages ) && is_array( $post_registration_messages ) ? $post_registration_messages : array();
if ( ! isset( $player_points ) || null === $player_points ) {
	$player_points = null;
} else {
	$player_points = (int) $player_points;
}
$show_continue_game_2   = ! empty( $show_continue_game_2 );
$continue_game_2_url    = isset( $continue_game_2_url ) ? (string) $continue_game_2_url : '';
$continue_game_2_label  = isset( $continue_game_2_label ) ? (string) $continue_game_2_label : '';
$game_2_unavailable     = isset( $game_2_unavailable ) ? (string) $game_2_unavailable : '';
?>
<main class="lk-game<?php echo $show_comparison ? ' lk-game--comparison' : ''; ?>">
	<?php if ( $is_preview ) : ?>
		<div class="lk-game__preview-notice" role="status">
			<?php esc_html_e( 'Preview Mode — No player progress or scores will be recorded.', 'local-knowledge' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_game1_handoff && null !== $game1_handoff_points ) : ?>
		<section class="lk-game__handoff" aria-labelledby="lk-game-handoff-heading">
			<h2 id="lk-game-handoff-heading" class="lk-game__handoff-title">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: completed game number */
						__( 'Game %d Complete', 'local-knowledge' ),
						max( 1, $handoff_game_number )
					)
				);
				?>
			</h2>
			<p>
				<?php
				printf(
					/* translators: 1: game number, 2: points earned */
					esc_html__( 'You earned %2$d points in Game %1$d.', 'local-knowledge' ),
					max( 1, $handoff_game_number ),
					$game1_handoff_points
				);
				?>
			</p>
			<p class="lk-game__handoff-current">
				<?php
				printf(
					/* translators: %d: current total points */
					esc_html__( 'Current Score: %d points', 'local-knowledge' ),
					null !== $current_total_points ? $current_total_points : $game1_handoff_points
				);
				?>
			</p>
			<?php if ( '' !== $handoff_historical_information ) : ?>
				<div class="lk-game__historical lk-game__historical--handoff">
					<?php echo wp_kses_post( $handoff_historical_information ); ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ( $show_large_image && '' !== $image_url ) || ( $show_comparison && array() !== $comparison_images ) ) : ?>
		<section class="lk-game__rules" aria-labelledby="lk-game-rules-heading">
			<h2 id="lk-game-rules-heading" class="lk-game__rules-title">
				<?php esc_html_e( 'Rules', 'local-knowledge' ); ?>
			</h2>
			<div class="lk-game__rules-placeholder" aria-hidden="true"></div>
		</section>
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

	<div class="lk-game__progress" role="group" aria-labelledby="lk-game-progress-label">
		<p id="lk-game-progress-label" class="lk-game__progress-label">
			<?php
			printf(
				/* translators: 1: current game number, 2: total games */
				esc_html__( 'Game %1$d of %2$d', 'local-knowledge' ),
				(int) $game_number,
				(int) $total_games
			);
			?>
		</p>
		<progress
			class="lk-game__progress-bar"
			value="<?php echo esc_attr( (string) (int) $game_number ); ?>"
			max="<?php echo esc_attr( (string) (int) $total_games ); ?>"
		>
			<?php
			printf(
				/* translators: 1: current game number, 2: total games */
				esc_html__( 'Game %1$d of %2$d', 'local-knowledge' ),
				(int) $game_number,
				(int) $total_games
			);
			?>
		</progress>
	</div>

	<?php if ( $show_large_image && '' !== $image_url ) : ?>
		<figure class="lk-game__image-frame">
			<img
				class="lk-game__image"
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $image_alt ); ?>"
			/>
		</figure>
	<?php endif; ?>

	<?php if ( $show_comparison && array() !== $comparison_images ) : ?>
		<section class="lk-game__comparison" aria-label="<?php esc_attr_e( 'Compare all game images', 'local-knowledge' ); ?>">
			<ul class="lk-game__comparison-grid">
				<?php foreach ( $comparison_images as $index => $item ) : ?>
					<?php
					$cell_url  = isset( $item['image_url'] ) ? (string) $item['image_url'] : '';
					$cell_full = isset( $item['full_url'] ) ? (string) $item['full_url'] : $cell_url;
					$cell_alt  = isset( $item['image_alt'] ) ? (string) $item['image_alt'] : '';
					$cell_n    = isset( $item['stage'] ) ? (int) $item['stage'] : ( (int) $index + 1 );

					if ( '' === $cell_url ) {
						continue;
					}

					$label = sprintf(
						/* translators: %d: image number */
						__( 'Enlarge game image %d', 'local-knowledge' ),
						$cell_n
					);
					?>
					<li class="lk-game__comparison-cell">
						<button
							type="button"
							class="lk-game__comparison-trigger"
							aria-haspopup="dialog"
							aria-controls="lk-comparison-dialog"
							data-lk-full-src="<?php echo esc_url( $cell_full ); ?>"
							data-lk-full-alt="<?php echo esc_attr( $cell_alt ); ?>"
						>
							<span class="lk-game__comparison-frame">
								<img
									class="lk-game__comparison-image"
									src="<?php echo esc_url( $cell_url ); ?>"
									alt="<?php echo esc_attr( $cell_alt ); ?>"
									draggable="false"
								/>
							</span>
							<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<dialog class="lk-game__comparison-dialog" id="lk-comparison-dialog" aria-labelledby="lk-comparison-dialog-title">
				<div class="lk-game__comparison-dialog-inner">
					<h2 id="lk-comparison-dialog-title" class="lk-game__comparison-dialog-title">
						<?php esc_html_e( 'Enlarged game image', 'local-knowledge' ); ?>
					</h2>
					<button type="button" class="lk-game__comparison-dialog-close" data-lk-dialog-close>
						<?php esc_html_e( 'Close', 'local-knowledge' ); ?>
					</button>
					<figure class="lk-game__comparison-dialog-figure">
						<img class="lk-game__comparison-dialog-image" src="" alt="" />
					</figure>
				</div>
			</dialog>
		</section>
	<?php endif; ?>

	<?php
	// Locked correct/IDK completion — gate on game_locked, not an unrelated flag
	// (e.g. show_comparison / show_idk) that is false after a View 1 correct answer.
	$completion_type = '' !== $completion_result ? $completion_result : $feedback;
	?>
	<?php if ( $playable && $game_locked && in_array( $completion_type, array( 'correct', 'idk' ), true ) ) : ?>
		<section class="lk-game__completion" aria-labelledby="lk-game-complete-heading">
			<h2 id="lk-game-complete-heading" class="lk-game__completion-title">
				<?php esc_html_e( 'Game Complete', 'local-knowledge' ); ?>
			</h2>

			<div class="lk-game__feedback lk-game__feedback--<?php echo esc_attr( $completion_type ); ?>" role="status">
				<?php if ( 'correct' === $completion_type ) : ?>
					<p class="lk-game__feedback-message">
						<?php esc_html_e( 'Correct! You identified the location.', 'local-knowledge' ); ?>
					</p>
				<?php else : ?>
					<p class="lk-game__feedback-message">
						<?php esc_html_e( 'Game complete. No correct location guess was submitted.', 'local-knowledge' ); ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $correct_location_label ) : ?>
					<p class="lk-game__correct-answer">
						<?php
						printf(
							/* translators: %s: correct location label */
							esc_html__( 'The correct location is: %s', 'local-knowledge' ),
							esc_html( $correct_location_label )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( in_array( $completion_type, array( 'correct', 'idk' ), true ) && '' !== $historical_information ) : ?>
					<div class="lk-game__historical">
						<?php echo wp_kses_post( $historical_information ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $show_registration && '' !== $registration_prompt ) : ?>
				<p class="lk-game__registration-prompt">
					<?php echo esc_html( $registration_prompt ); ?>
				</p>
			<?php endif; ?>
		</section>

		<?php if ( $show_proceed_next_game && '' !== $proceed_next_game_url ) : ?>
			<p class="lk-game__proceed">
				<a class="lk-game__submit lk-game__submit--proceed" href="<?php echo esc_url( $proceed_next_game_url ); ?>">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: next game number */
							__( 'Go on to Game %d', 'local-knowledge' ),
							max( 2, $proceed_next_game_number > 0 ? $proceed_next_game_number : ( (int) $game_number + 1 ) )
						)
					);
					?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $show_post_registration ) ) : ?>
			<section class="lk-game__post-registration" aria-labelledby="lk-post-registration-heading">
				<h2 id="lk-post-registration-heading" class="lk-game__post-registration-title">
					<?php echo esc_html( '' !== $post_registration_title ? $post_registration_title : __( 'Account created', 'local-knowledge' ) ); ?>
				</h2>

				<div class="lk-game__post-registration-body" role="status">
					<?php if ( isset( $post_registration_messages ) && is_array( $post_registration_messages ) ) : ?>
						<?php foreach ( $post_registration_messages as $post_msg ) : ?>
							<p><?php echo esc_html( (string) $post_msg ); ?></p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( null !== $player_points ) : ?>
						<p class="lk-game__player-points">
							<?php
							printf(
								/* translators: %d: points earned */
								esc_html__( 'Game 1 score: %d points', 'local-knowledge' ),
								(int) $player_points
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $show_continue_game_2 ) && '' !== $continue_game_2_url ) : ?>
					<p class="lk-game__continue">
						<a class="lk-game__submit lk-game__submit--continue" href="<?php echo esc_url( $continue_game_2_url ); ?>">
							<?php echo esc_html( '' !== $continue_game_2_label ? $continue_game_2_label : __( 'Continue to Game 2', 'local-knowledge' ) ); ?>
						</a>
					</p>
				<?php elseif ( '' !== $game_2_unavailable ) : ?>
					<p class="lk-game__game2-unavailable" role="status">
						<?php echo esc_html( $game_2_unavailable ); ?>
					</p>
				<?php endif; ?>
			</section>

		<?php elseif ( '' !== $registration_info ) : ?>
			<div class="lk-game__registration-info" role="status">
				<p><?php echo esc_html( $registration_info ); ?></p>
			</div>

		<?php elseif ( $show_registration ) : ?>
			<?php if ( $registration_success && '' !== $registration_success_message ) : ?>
				<div class="lk-game__registration-success" role="status" aria-live="polite">
					<p><?php echo esc_html( $registration_success_message ); ?></p>
				</div>
			<?php else : ?>
				<section class="lk-game__registration" aria-labelledby="lk-registration-heading">
					<h2 id="lk-registration-heading" class="lk-game__registration-title">
						<?php esc_html_e( 'Register', 'local-knowledge' ); ?>
					</h2>

					<?php if ( $has_reg_errors ) : ?>
						<div class="lk-game__registration-errors" role="alert" id="lk-registration-errors">
							<p class="lk-game__registration-errors-heading">
								<?php esc_html_e( 'Please fix the following:', 'local-knowledge' ); ?>
							</p>
							<ul>
								<?php foreach ( $registration_errors as $reg_error ) : ?>
									<li><?php echo esc_html( (string) $reg_error ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<form class="lk-game__registration-form" method="post" action="" autocomplete="on">
						<input type="hidden" name="lk_game_action" value="<?php echo esc_attr( $registration_form_action_value ); ?>" />
						<input type="hidden" name="lk_game_id" value="<?php echo esc_attr( (string) $game_id ); ?>" />
						<?php wp_nonce_field( $registration_nonce_action, $registration_nonce_field ); ?>

						<p class="lk-game__field">
							<label for="lk-first-name"><?php esc_html_e( 'First Name', 'local-knowledge' ); ?></label>
							<input
								type="text"
								id="lk-first-name"
								name="lk_first_name"
								value="<?php echo esc_attr( $reg_first ); ?>"
								autocomplete="given-name"
								<?php echo $has_reg_errors ? 'aria-describedby="lk-registration-errors"' : ''; ?>
								required
							/>
						</p>

						<p class="lk-game__field">
							<label for="lk-last-name"><?php esc_html_e( 'Last Name', 'local-knowledge' ); ?></label>
							<input
								type="text"
								id="lk-last-name"
								name="lk_last_name"
								value="<?php echo esc_attr( $reg_last ); ?>"
								autocomplete="family-name"
								<?php echo $has_reg_errors ? 'aria-describedby="lk-registration-errors"' : ''; ?>
								required
							/>
						</p>

						<p class="lk-game__field">
							<label for="lk-email"><?php esc_html_e( 'Email Address', 'local-knowledge' ); ?></label>
							<input
								type="email"
								id="lk-email"
								name="lk_email"
								value="<?php echo esc_attr( $reg_email ); ?>"
								autocomplete="email"
								<?php echo $has_reg_errors ? 'aria-describedby="lk-registration-errors"' : ''; ?>
								required
							/>
						</p>

						<p class="lk-game__field">
							<label for="lk-username"><?php esc_html_e( 'Username', 'local-knowledge' ); ?></label>
							<input
								type="text"
								id="lk-username"
								name="lk_username"
								value="<?php echo esc_attr( $reg_username ); ?>"
								autocomplete="username"
								<?php echo $has_reg_errors ? 'aria-describedby="lk-registration-errors"' : ''; ?>
								required
							/>
						</p>

						<p class="lk-game__field">
							<label for="lk-password"><?php esc_html_e( 'Password', 'local-knowledge' ); ?></label>
							<input
								type="password"
								id="lk-password"
								name="lk_password"
								autocomplete="new-password"
								<?php echo $has_reg_errors ? 'aria-describedby="lk-registration-errors"' : ''; ?>
								required
							/>
						</p>

						<button type="submit" class="lk-game__submit lk-game__submit--register">
							<?php esc_html_e( 'Register', 'local-knowledge' ); ?>
						</button>
					</form>
				</section>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( $playable && '' !== $feedback ) : ?>
		<div class="lk-game__feedback lk-game__feedback--<?php echo esc_attr( $feedback ); ?>" role="status" aria-live="polite">
			<?php if ( 'incorrect' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'That answer is incorrect. Please try again.', 'local-knowledge' ); ?>
				</p>
			<?php elseif ( 'missing' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Please select a location before submitting.', 'local-knowledge' ); ?>
				</p>
			<?php elseif ( 'invalid_choice' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'The submitted answer is not valid. Please choose one of the listed locations.', 'local-knowledge' ); ?>
				</p>
			<?php else : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Your submission could not be processed. Please try again.', 'local-knowledge' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $playable && ! $game_locked ) : ?>
		<form class="lk-game__form" method="post" action="">
			<input type="hidden" name="lk_game_action" value="<?php echo esc_attr( $form_action_value ); ?>" />
			<input type="hidden" name="lk_game_id" value="<?php echo esc_attr( (string) $game_id ); ?>" />
			<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

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
							<?php checked( $selected_choice, (string) $index ); ?>
						/>
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( (string) $label ); ?>
						</label>
					</div>
				<?php endforeach; ?>

				<?php if ( $show_idk ) : ?>
					<div class="lk-game__choice lk-game__choice--idk">
						<input
							type="radio"
							id="lk-location-idk"
							name="lk_location"
							value="idk"
							<?php checked( $selected_choice, 'idk' ); ?>
						/>
						<label for="lk-location-idk">
							<?php esc_html_e( 'I Don\'t Know', 'local-knowledge' ); ?>
						</label>
					</div>
				<?php endif; ?>
			</fieldset>

			<button type="submit" class="lk-game__submit">
				<?php
				if ( 'incorrect' === $feedback ) {
					esc_html_e( 'Try Again', 'local-knowledge' );
				} else {
					esc_html_e( 'Submit', 'local-knowledge' );
				}
				?>
			</button>
		</form>
	<?php elseif ( ! $playable ) : ?>
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
	<?php endif; ?>
</main>
