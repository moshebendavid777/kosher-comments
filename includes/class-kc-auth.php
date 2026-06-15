<?php
/**
 * Authentication guards.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Auth {

	/**
	 * Strike service.
	 *
	 * @var Kosher_Comments_Strikes
	 */
	protected $strikes;

	/**
	 * Constructor.
	 *
	 * @param Kosher_Comments_Strikes $strikes Strike service.
	 */
	public function __construct( Kosher_Comments_Strikes $strikes ) {
		$this->strikes = $strikes;
	}

	/**
	 * Register auth hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'authenticate', array( $this, 'block_locked_user' ), 30, 3 );
		add_filter( 'registration_errors', array( $this, 'block_banned_email_registration' ), 10, 3 );
	}

	/**
	 * Block logins for locked users or banned emails.
	 *
	 * @param WP_User|WP_Error|null $user Authenticated user.
	 * @param string                $username Submitted username.
	 * @param string                $password Submitted password.
	 * @return WP_User|WP_Error|null
	 */
	public function block_locked_user( $user, $username, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$resolved_user = $user;

		if ( ! $resolved_user instanceof WP_User && ! empty( $username ) ) {
			$resolved_user = get_user_by( 'login', $username );

			if ( ! $resolved_user && is_email( $username ) ) {
				$resolved_user = get_user_by( 'email', $username );
			}
		}

		if ( ! $resolved_user instanceof WP_User ) {
			return $user;
		}

		if ( $this->strikes->is_user_locked( $resolved_user->ID ) ) {
			return new WP_Error(
				'kosher_comments_locked',
				__( 'Your account was locked for misconduct. Contact info@kosher.com', 'kosher-comments' )
			);
		}

		if ( $this->strikes->is_email_banned( $resolved_user->user_email ) ) {
			return new WP_Error(
				'kosher_comments_banned_email',
				__( 'This email is banned from accessing the site.', 'kosher-comments' )
			);
		}

		return $user;
	}

	/**
	 * Block registration for banned emails.
	 *
	 * @param WP_Error $errors Existing errors.
	 * @param string   $sanitized_user_login User login.
	 * @param string   $user_email Email.
	 * @return WP_Error
	 */
	public function block_banned_email_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( $this->strikes->is_email_banned( $user_email ) ) {
			$errors->add(
				'kosher_comments_banned_email',
				__( 'This email is banned from registering.', 'kosher-comments' )
			);
		}

		return $errors;
	}
}
