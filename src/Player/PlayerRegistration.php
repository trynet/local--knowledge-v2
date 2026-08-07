<?php
/**
 * WordPress player account creation and notification.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

defined( 'ABSPATH' ) || exit;

/**
 * Creates subscriber accounts and triggers core password-setup email.
 *
 * Does not authenticate the new player.
 */
final class PlayerRegistration {

	/**
	 * Create a player account. Does not log in or store Game results.
	 *
	 * @param array{first_name: string, last_name: string, email: string, username: string} $data Validated fields.
	 * @return array{user_id: int, email_sent: bool}|\WP_Error
	 */
	public function create_account( array $data ) {
		$username = isset( $data['username'] ) ? (string) $data['username'] : '';
		$email    = isset( $data['email'] ) ? (string) $data['email'] : '';
		$first    = isset( $data['first_name'] ) ? (string) $data['first_name'] : '';
		$last     = isset( $data['last_name'] ) ? (string) $data['last_name'] : '';

		if ( '' === $username || '' === $email ) {
			return new \WP_Error( 'lk_missing_fields', __( 'Registration fields are incomplete.', 'local-knowledge' ) );
		}

		if ( username_exists( $username ) ) {
			return new \WP_Error( 'lk_username_exists', __( 'That Username is already taken.', 'local-knowledge' ) );
		}

		if ( email_exists( $email ) ) {
			return new \WP_Error( 'lk_email_exists', __( 'That Email Address is already registered.', 'local-knowledge' ) );
		}

		$password = wp_generate_password( 24, true, true );

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => $first,
				'last_name'  => $last,
				'role'       => 'subscriber',
			)
		);

		// Never retain or expose the generated password.
		$password = '';

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user_id = (int) $user_id;

		$email_sent = $this->send_password_setup_email( $user_id );

		return array(
			'user_id'    => $user_id,
			'email_sent' => $email_sent,
		);
	}

	/**
	 * Send the standard WordPress new-user notification (password-setup link).
	 *
	 * Uses core `wp_new_user_notification()` so the plaintext password is never emailed.
	 *
	 * @param int $user_id New user ID.
	 */
	public function send_password_setup_email( int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}

		$before = $this->capture_mail_state();

		/*
		 * notify = 'user' sends the user a set-password / reset-style link.
		 * The second argument is deprecated and must not carry a plaintext password.
		 */
		wp_new_user_notification( $user_id, null, 'user' );

		return $this->mail_likely_succeeded( $before );
	}

	/**
	 * @return array{phpmailer_error: mixed}
	 */
	private function capture_mail_state(): array {
		global $phpmailer;

		return array(
			'phpmailer_error' => ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) )
				? (string) $phpmailer->ErrorInfo
				: '',
		);
	}

	/**
	 * Best-effort mail success check without failing the registration flow.
	 *
	 * @param array{phpmailer_error: mixed} $before Prior mailer state.
	 */
	private function mail_likely_succeeded( array $before ): bool {
		global $phpmailer;

		if ( ! isset( $phpmailer ) || ! is_object( $phpmailer ) ) {
			// Many local environments have no mailer; treat as unconfirmed failure for messaging.
			return false;
		}

		$error = isset( $phpmailer->ErrorInfo ) ? (string) $phpmailer->ErrorInfo : '';

		if ( '' !== $error && $error !== (string) ( $before['phpmailer_error'] ?? '' ) ) {
			return false;
		}

		return true;
	}
}
