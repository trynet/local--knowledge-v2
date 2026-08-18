<?php
/**
 * WordPress player account creation.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

defined( 'ABSPATH' ) || exit;

/**
 * Creates subscriber accounts from validated registration fields.
 *
 * Does not authenticate the new player or send a password-setup email.
 */
final class PlayerRegistration {

	/**
	 * Create a player account. Does not log in or store Game results.
	 *
	 * @param array{first_name: string, last_name: string, email: string, username: string, password: string} $data Validated fields.
	 * @return array{user_id: int}|\WP_Error
	 */
	public function create_account( array $data ) {
		$username = isset( $data['username'] ) ? (string) $data['username'] : '';
		$email    = isset( $data['email'] ) ? (string) $data['email'] : '';
		$first    = isset( $data['first_name'] ) ? (string) $data['first_name'] : '';
		$last     = isset( $data['last_name'] ) ? (string) $data['last_name'] : '';
		$password = isset( $data['password'] ) ? (string) $data['password'] : '';

		if ( '' === $username || '' === $email || '' === $password ) {
			$password = '';
			return new \WP_Error( 'lk_missing_fields', __( 'Registration fields are incomplete.', 'local-knowledge' ) );
		}

		if ( username_exists( $username ) ) {
			$password = '';
			return new \WP_Error( 'lk_username_exists', __( 'That Username is already taken.', 'local-knowledge' ) );
		}

		if ( email_exists( $email ) ) {
			$password = '';
			return new \WP_Error( 'lk_email_exists', __( 'That Email Address is already registered.', 'local-knowledge' ) );
		}

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

		$password = '';

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return array(
			'user_id' => (int) $user_id,
		);
	}
}
