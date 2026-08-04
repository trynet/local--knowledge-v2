<?php
/**
 * Post-completion Game 1 registration gateway.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Player\PlayerRegistration;
use JoyOfCode\LocalKnowledge\Player\PlayerResultRepository;
use JoyOfCode\LocalKnowledge\Player\ScoreCalculator;

defined( 'ABSPATH' ) || exit;

/**
 * Validates registration, creates the player account, and transfers Game 1 results.
 *
 * Eligibility and scoring come only from authoritative GameState / permanent results.
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
	 * Account creation helper.
	 */
	private PlayerRegistration $accounts;

	/**
	 * Permanent result store.
	 */
	private PlayerResultRepository $results;

	/**
	 * Score calculator.
	 */
	private ScoreCalculator $scores;

	/**
	 * Result of the current request's registration attempt, if any.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $request_result = null;

	/**
	 * Constructor.
	 */
	public function __construct(
		?GameState $state_store = null,
		?PlayerRegistration $accounts = null,
		?PlayerResultRepository $results = null,
		?ScoreCalculator $scores = null
	) {
		$this->state_store = $state_store ?? new GameState();
		$this->accounts    = $accounts ?? new PlayerRegistration();
		$this->results     = $results ?? new PlayerResultRepository();
		$this->scores      = $scores ?? new ScoreCalculator();
	}

	/**
	 * Wire hooks (processing runs from GameRoute).
	 */
	public function register(): void {
		// Intentionally empty: GameRoute invokes this class while rendering.
	}

	/**
	 * If this is a registration POST, validate and create the account.
	 *
	 * Validation failures render in-place. Successful creation redirects after login.
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

		if ( ! empty( $this->request_result['should_redirect'] )
			&& ! empty( $this->request_result['redirect_url'] )
		) {
			$url = (string) $this->request_result['redirect_url'];

			if ( wp_validate_redirect( $url, false ) ) {
				wp_safe_redirect( $url, 303 );
				exit;
			}
		}
	}

	/**
	 * Build registration / completion / post-registration extras.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @param array<string, mixed> $state       Authoritative public state.
	 * @return array<string, mixed>
	 */
	public function get_view_extras( int $game_id, int $game_number, array $state ): array {
		$logged_in = is_user_logged_in();
		$user_id   = $logged_in ? get_current_user_id() : 0;

		// Logged-in player with a permanent Game 1 result: show score + Game 2.
		if ( $user_id > 0 && 1 === absint( $game_number ) ) {
			$permanent = $this->results->get_result( $user_id, 1 );

			if ( null === $permanent ) {
				$permanent = $this->maybe_retry_result_transfer( $user_id, $game_id, $state );
			}

			if ( null !== $permanent && (int) $permanent['game_id'] === $game_id ) {
				return $this->post_registration_extras( $game_id, $permanent, $this->accounts->consume_flash( $user_id ) );
			}

			// Logged in without a Game 1 result: never show guest registration.
			$ended  = ! empty( $state['ended'] );
			$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';
			$locked = $ended && in_array( $result, array( 'correct', 'idk' ), true );

			if ( $locked ) {
				$extras                      = $this->default_extras( $game_id );
				$extras['show_completion']   = true;
				$extras['completion_result'] = $result;
				$extras['show_registration'] = false;
				$extras['registration_info'] = __(
					'You are already signed in. Guest registration is not available for this account in the current milestone.',
					'local-knowledge'
				);
				return $extras;
			}
		}

		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';
		$locked = $ended && in_array( $result, array( 'correct', 'idk' ), true );

		if ( ! $locked ) {
			return null !== $this->request_result ? $this->request_result : array();
		}

		// Claimed guest attempt without matching logged-in session.
		if ( $this->state_store->is_claimed( $state ) && ! $logged_in ) {
			$extras                      = $this->default_extras( $game_id );
			$extras['show_completion']   = true;
			$extras['completion_result'] = $result;
			$extras['show_registration'] = false;
			$extras['registration_info'] = __(
				'This Game 1 attempt has already been registered. Please check your email for the password setup link, or log in to continue.',
				'local-knowledge'
			);
			return $extras;
		}

		$extras                        = $this->default_extras( $game_id );
		$extras['show_completion']     = true;
		$extras['completion_result']   = $result;
		$extras['show_registration']   = ! $logged_in && $this->is_eligible( $game_id, $game_number, $state );
		$extras['registration_prompt'] = __(
			'Register to see your score and continue to Game 2.',
			'local-knowledge'
		);

		if ( null !== $this->request_result ) {
			$eligible = ! empty( $extras['show_registration'] );
			$extras   = array_merge( $extras, $this->request_result );

			if ( $eligible && empty( $extras['show_post_registration'] ) ) {
				$extras['show_registration'] = true;
			}
		}

		return $extras;
	}

	/**
	 * Whether guest registration may be offered / accepted.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number from the route.
	 * @param array<string, mixed> $state       Authoritative state.
	 */
	public function is_eligible( int $game_id, int $game_number, array $state ): bool {
		if ( is_user_logged_in() ) {
			return false;
		}

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

		$stored_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( 1 !== $stored_number || $stored_number !== absint( $game_number ) ) {
			return false;
		}

		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';

		if ( ! $ended || ! in_array( $result, array( 'correct', 'idk' ), true ) ) {
			return false;
		}

		$view = isset( $state['current_view'] ) ? absint( $state['current_view'] ) : 0;

		if ( $view < GameState::VIEW_MIN || $view > GameState::VIEW_COMPARISON ) {
			return false;
		}

		if ( $this->state_store->is_claimed( $state ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluate registration POST and create the account when valid.
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
			'show_registration'    => true,
		);

		if ( is_user_logged_in() ) {
			$out['registration_errors'][] = __( 'You are already signed in.', 'local-knowledge' );
			$out['show_registration']     = false;
			return $out;
		}

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
			if ( $this->state_store->is_claimed( $state ) ) {
				$out['registration_errors'][] = __( 'This Game 1 attempt has already been registered.', 'local-knowledge' );
				$out['show_registration']     = false;
			} else {
				$out['registration_errors'][] = __( 'Registration is only available after completing Game 1.', 'local-knowledge' );
			}
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

		$values['first_name']           = $first;
		$values['last_name']            = $last;
		$values['email']                = sanitize_text_field( $email_raw );
		$values['username']             = sanitize_text_field( $raw_username );
		$out['registration_values']     = $values;

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

		$view        = absint( $state['current_view'] );
		$result_type = sanitize_key( (string) $state['result_type'] );
		$points      = $this->scores->calculate( $view, $result_type );

		$created = $this->accounts->create_account(
			array(
				'first_name' => $first,
				'last_name'  => $last,
				'email'      => $email,
				'username'   => $user,
			)
		);

		if ( is_wp_error( $created ) ) {
			$out['registration_errors'][] = $created->get_error_message();
			return $out;
		}

		$user_id    = (int) $created['user_id'];
		$email_sent = ! empty( $created['email_sent'] );

		$save = $this->results->save_result(
			$user_id,
			array(
				'game_id'        => $game_id,
				'game_number'    => 1,
				'completed_view' => $view,
				'result_type'    => $result_type,
				'points'         => $points,
				'completed_at'   => gmdate( 'Y-m-d H:i:s' ),
				'status'         => 'completed',
			)
		);

		if ( is_wp_error( $save ) ) {
			// Account exists; claim the attempt so a refresh cannot create a second user.
			$this->state_store->mark_claimed( $game_id, $user_id );
			$this->accounts->set_flash(
				$user_id,
				array(
					'account_created' => true,
					'email_sent'      => $email_sent,
					'login_ok'        => false,
					'result_ok'       => false,
				)
			);

			$login_ok = $this->accounts->login_user( $user_id );

			$out['registration_errors'][] = $save->get_error_message();
			$out['registration_errors'][] = __(
				'Your account was created, but your Game 1 result could not be saved. Please contact support if this continues.',
				'local-knowledge'
			);
			$out['show_registration'] = false;

			if ( $login_ok ) {
				$out['should_redirect'] = true;
				$out['redirect_url']    = GameRoute::get_public_url( 1 );
			}

			return $out;
		}

		$this->state_store->mark_claimed( $game_id, $user_id );

		$login_ok = $this->accounts->login_user( $user_id );

		$this->accounts->set_flash(
			$user_id,
			array(
				'account_created' => true,
				'email_sent'      => $email_sent,
				'login_ok'        => $login_ok,
				'result_ok'       => true,
			)
		);

		if ( $login_ok ) {
			$out['should_redirect']      = true;
			$out['redirect_url']         = GameRoute::get_public_url( 1 );
			$out['registration_success'] = true;
			$out['show_registration']    = false;
			return $out;
		}

		// Account + result saved; login failed.
		$out['show_registration'] = false;
		$out['registration_info'] = __(
			'Your account and Game 1 result were saved, but automatic sign-in failed. Please use the link in your email or log in to continue.',
			'local-knowledge'
		);

		return $out;
	}

	/**
	 * If account creation claimed the attempt but result storage failed, retry once.
	 *
	 * @param int                  $user_id Current user ID.
	 * @param int                  $game_id Game post ID.
	 * @param array<string, mixed> $state   Guest state.
	 * @return array<string, mixed>|null
	 */
	private function maybe_retry_result_transfer( int $user_id, int $game_id, array $state ): ?array {
		if ( $user_id < 1 || ! $this->state_store->is_claimed( $state ) ) {
			return null;
		}

		if ( absint( $state['claimed_user_id'] ?? 0 ) !== $user_id ) {
			return null;
		}

		$ended  = ! empty( $state['ended'] );
		$result = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';
		$view   = isset( $state['current_view'] ) ? absint( $state['current_view'] ) : 0;

		if ( ! $ended || ! in_array( $result, array( 'correct', 'idk' ), true ) ) {
			return null;
		}

		if ( $view < GameState::VIEW_MIN || $view > GameState::VIEW_COMPARISON ) {
			return null;
		}

		$points = $this->scores->calculate( $view, $result );
		$save   = $this->results->save_result(
			$user_id,
			array(
				'game_id'        => $game_id,
				'game_number'    => 1,
				'completed_view' => $view,
				'result_type'    => $result,
				'points'         => $points,
				'completed_at'   => gmdate( 'Y-m-d H:i:s' ),
				'status'         => 'completed',
			)
		);

		if ( is_wp_error( $save ) ) {
			return null;
		}

		return $this->results->get_result( $user_id, 1 );
	}

	/**
	 * View extras for a logged-in player with a permanent Game 1 result.
	 *
	 * @param int                  $game_id   Game post ID.
	 * @param array<string, mixed> $permanent Permanent result row.
	 * @param array<string, bool>  $flash     Consumed flash flags.
	 * @return array<string, mixed>
	 */
	private function post_registration_extras( int $game_id, array $permanent, array $flash ): array {
		$extras = $this->default_extras( $game_id );

		$points      = isset( $permanent['points'] ) ? absint( $permanent['points'] ) : 0;
		$result_type = isset( $permanent['result_type'] ) ? sanitize_key( (string) $permanent['result_type'] ) : '';
		$view        = isset( $permanent['completed_view'] ) ? absint( $permanent['completed_view'] ) : 1;

		$display = new GameDisplayData();
		$key     = $display->get_correct_location_key( $game_id );
		$label   = '' !== $key ? $display->get_location_label( $game_id, $key ) : '';

		$extras['game_locked']            = true;
		$extras['show_completion']        = true;
		$extras['completion_result']      = $result_type;
		$extras['correct_location_label'] = $label;
		$extras['current_view']           = $view;
		$extras['show_registration']      = false;
		$extras['show_post_registration'] = true;
		$extras['player_points']          = $points;
		$extras['show_comparison']        = GameState::VIEW_COMPARISON === $view;
		$extras['show_large_image']       = GameState::VIEW_COMPARISON !== $view;
		$extras['show_idk']               = false;

		if ( GameState::VIEW_COMPARISON === $view ) {
			$extras['comparison_images'] = $display->get_comparison_images( $game_id );
		}

		$account_created = ! empty( $flash['account_created'] );
		$email_sent      = ! empty( $flash['email_sent'] );
		$login_ok        = ! isset( $flash['login_ok'] ) || ! empty( $flash['login_ok'] );
		$result_ok       = ! isset( $flash['result_ok'] ) || ! empty( $flash['result_ok'] );

		$extras['post_registration_title'] = $account_created
			? __( 'Account created', 'local-knowledge' )
			: __( 'Game 1 complete', 'local-knowledge' );

		$messages = array();

		if ( $account_created ) {
			$messages[] = __( 'Account created successfully.', 'local-knowledge' );
		}

		if ( $result_ok ) {
			$messages[] = sprintf(
				/* translators: %d: points earned */
				__( 'You earned %d points in Game 1.', 'local-knowledge' ),
				$points
			);
			$messages[] = __( 'Game 1 is complete.', 'local-knowledge' );
		} else {
			$messages[] = __( 'Your Game 1 result could not be confirmed. Please contact support if your score does not appear.', 'local-knowledge' );
		}

		if ( $account_created && $email_sent ) {
			$messages[] = __( 'Check your email for a secure link to create your password.', 'local-knowledge' );
		} elseif ( $account_created && ! $email_sent ) {
			$messages[] = __(
				'Your account was created, but the password setup email may not have been sent. You may need to request a password reset later.',
				'local-knowledge'
			);
		} elseif ( ! $account_created ) {
			$messages[] = __( 'Check your email for a secure link to create your password if you have not set one yet.', 'local-knowledge' );
		}

		if ( $account_created && ! $login_ok ) {
			$messages[] = __(
				'Automatic sign-in did not complete. Please use your email link or log in to continue.',
				'local-knowledge'
			);
		}

		$extras['post_registration_messages'] = $messages;

		$game2_id  = $this->find_published_game_id( 2 );
		$game2_url = null !== $game2_id ? GameRoute::get_public_url( 2 ) : '';

		if ( '' !== $game2_url && $result_ok ) {
			$extras['show_continue_game_2'] = true;
			$extras['continue_game_2_url']  = $game2_url;
			$extras['continue_game_2_label'] = __( 'Continue to Game 2', 'local-knowledge' );
		} else {
			$extras['show_continue_game_2'] = false;
			$extras['game_2_unavailable']   = __(
				'Game 2 is not available yet. Please check back later.',
				'local-knowledge'
			);
		}

		return $extras;
	}

	/**
	 * Locate a published Game by Game Number.
	 *
	 * @param int $game_number Game Number.
	 */
	private function find_published_game_id( int $game_number ): ?int {
		if ( $game_number < 1 ) {
			return null;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => GamePostType::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => GameDisplayData::META_KEYS['game_number'],
						'value'   => $game_number,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		return (int) $query->posts[0];
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
			'registration_info'              => '',
			'show_post_registration'         => false,
			'post_registration_title'        => '',
			'post_registration_messages'     => array(),
			'player_points'                  => null,
			'show_continue_game_2'           => false,
			'continue_game_2_url'            => '',
			'continue_game_2_label'          => '',
			'game_2_unavailable'             => '',
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
