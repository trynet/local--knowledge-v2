<?php
/**
 * Post-completion registration gateway (validation only).
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;

defined( 'ABSPATH' ) || exit;

/**
 * Validates Game 1 registration without creating WordPress users.
 *
 * Eligibility and completion come only from authoritative GameState.
 */
final class RegistrationGateway {

	/**
	 * Form action for registration POST.
	 */
	public const FORM_ACTION = 'lk_register';

	/**
	 * Temporary state store.
	 */
	private GameState $state_store;

	/**
	 * Result of the current request's registration attempt, if any.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $request_result = null;

	/**
	 * Constructor.
	 */
	public function __construct( ?GameState $state_store = null ) {
		$this->state_store = $state_store ?? new GameState();
	}

	/**
	 * Wire hooks (processing runs from GameRoute).
	 */
	public function register(): void {
		// Intentionally empty: GameRoute invokes this class while rendering.
	}

	/**
	 * If this is a registration POST, validate it (no user creation).
	 *
	 * On validation failure the response is rendered in-place (no redirect)
	 * so field values can be preserved without flash tokens. Success also
	 * stays request-local.
	 *
	 * @param int $game_id     Published Game post ID.
	 * @param int $game_number Game Number from the route.
	 */
	public function maybe_process( int $game_id, int $game_number ): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$posted_action = isset( $_POST['lk_game_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['lk_game_action'] ) )
			: '';

		if ( self::FORM_ACTION !== $posted_action ) {
			return;
		}

		$this->request_result = $this->evaluate_registration( $game_id, $game_number );
	}

	/**
	 * Build registration / completion extras for the current GET or POST render.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @param array<string, mixed> $state       Authoritative public state.
	 * @return array<string, mixed>
	 */
	public function get_view_extras( int $game_id, int $game_number, array $state ): array {
		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';
		$locked = $ended && in_array( $result, array( 'correct', 'idk' ), true );

		// Do not return show_completion => false here: merging that over GamePlay's
		// locked completion flags would hide the completion/registration UI.
		if ( ! $locked ) {
			return null !== $this->request_result ? $this->request_result : array();
		}

		$extras                          = $this->default_extras( $game_id );
		$extras['show_completion']       = true;
		$extras['completion_result']     = $result;
		$extras['show_registration']     = $this->is_eligible( $game_id, $game_number, $state );
		$extras['registration_prompt']   = __(
			'Register to see your score and continue to Game 2.',
			'local-knowledge'
		);

		if ( null !== $this->request_result ) {
			// Preserve eligibility-based visibility; request_result must not hide the form
			// after a failed nonce or field validation on an eligible completed Game 1.
			$eligible = ! empty( $extras['show_registration'] );
			$extras   = array_merge( $extras, $this->request_result );

			if ( $eligible ) {
				$extras['show_registration'] = true;
			}
		}

		return $extras;
	}

	/**
	 * Whether registration may be offered / accepted for this Game attempt.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number from the route.
	 * @param array<string, mixed> $state       Authoritative state.
	 */
	public function is_eligible( int $game_id, int $game_number, array $state ): bool {
		if ( $game_id < 1 || $game_number < 1 ) {
			return false;
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return false;
		}

		// Eligibility uses stored Game Number meta as an integer (not post ID).
		$stored_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( 1 !== $stored_number || $stored_number !== absint( $game_number ) ) {
			return false;
		}

		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';

		return $ended && in_array( $result, array( 'correct', 'idk' ), true );
	}

	/**
	 * Evaluate a registration POST.
	 *
	 * @param int $game_id     Game post ID.
	 * @param int $game_number Game Number.
	 * @return array<string, mixed>
	 */
	private function evaluate_registration( int $game_id, int $game_number ): array {
		$values = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'username'   => '',
		);

		$out = array(
			'registration_errors'  => array(),
			'registration_values'  => $values,
			'registration_success' => false,
		);

		$posted_game_id = isset( $_POST['lk_game_id'] ) ? absint( wp_unslash( $_POST['lk_game_id'] ) ) : 0;

		if ( $posted_game_id !== $game_id || $game_id < 1 ) {
			$out['registration_errors'][] = __( 'This registration request is not valid for this Game.', 'local-knowledge' );
			return $out;
		}

		$nonce = isset( $_POST['lk_register_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_register_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $game_id ) ) ) {
			$out['registration_errors'][] = __( 'Your registration session expired. Please try again.', 'local-knowledge' );
			return $out;
		}

		$state = $this->state_store->get_public_state( $game_id );

		if ( ! $this->is_eligible( $game_id, $game_number, $state ) ) {
			$out['registration_errors'][] = __( 'Registration is only available after completing Game 1.', 'local-knowledge' );
			return $out;
		}

		$first = isset( $_POST['lk_first_name'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_first_name'] ) )
			: '';
		$last  = isset( $_POST['lk_last_name'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_last_name'] ) )
			: '';
		$email_raw = isset( $_POST['lk_email'] )
			? trim( (string) wp_unslash( $_POST['lk_email'] ) )
			: '';
		$email     = sanitize_email( $email_raw );

		$raw_username = isset( $_POST['lk_username'] )
			? trim( (string) wp_unslash( $_POST['lk_username'] ) )
			: '';
		$user         = sanitize_user( $raw_username, true );

		$values['first_name'] = $first;
		$values['last_name']  = $last;
		$values['email']      = sanitize_text_field( $email_raw );
		$values['username']   = sanitize_text_field( $raw_username );
		$out['registration_values'] = $values;

		$errors = array();

		if ( '' === $first ) {
			$errors[] = __( 'First Name is required.', 'local-knowledge' );
		}

		if ( '' === $last ) {
			$errors[] = __( 'Last Name is required.', 'local-knowledge' );
		}

		if ( '' === $email_raw ) {
			$errors[] = __( 'Email Address is required.', 'local-knowledge' );
		} elseif ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'A valid Email Address is required.', 'local-knowledge' );
		} elseif ( email_exists( $email ) ) {
			$errors[] = __( 'That Email Address is already registered.', 'local-knowledge' );
		}

		if ( '' === $raw_username ) {
			$errors[] = __( 'Username is required.', 'local-knowledge' );
		} elseif ( '' === $user || $user !== $raw_username ) {
			$errors[] = __( 'Username contains invalid characters.', 'local-knowledge' );
		} elseif ( username_exists( $user ) ) {
			$errors[] = __( 'That Username is already taken.', 'local-knowledge' );
		}

		if ( array() !== $errors ) {
			$out['registration_errors'] = $errors;
			return $out;
		}

		/*
		 * Milestone 7A: validation only — no wp_create_user / wp_insert_user,
		 * no password generation, no email, no login, no permanent result storage.
		 * Milestone 7B will create the account and send the password setup email.
		 */
		$out['registration_success'] = true;
		$out['registration_errors']  = array();
		$out['registration_values']  = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'username'   => '',
		);
		$out['registration_success_message'] = __(
			'Registration validated successfully. Your account will be created in the next milestone, where you will receive an email to create your password.',
			'local-knowledge'
		);

		return $out;
	}

	/**
	 * Default registration extras.
	 *
	 * @param int $game_id Game post ID.
	 * @return array<string, mixed>
	 */
	private function default_extras( int $game_id ): array {
		return array(
			'show_completion'                => false,
			'completion_result'              => '',
			'show_registration'              => false,
			'registration_prompt'            => '',
			'registration_success'           => false,
			'registration_success_message'   => '',
			'registration_errors'            => array(),
			'registration_values'            => array(
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
				'username'   => '',
			),
			'registration_nonce_action'      => $this->nonce_action( $game_id ),
			'registration_nonce_field'       => 'lk_register_nonce',
			'registration_form_action_value' => self::FORM_ACTION,
		);
	}

	/**
	 * Nonce action for registration.
	 *
	 * @param int $game_id Game post ID.
	 */
	private function nonce_action( int $game_id ): string {
		return 'lk_register_' . $game_id;
	}
}
