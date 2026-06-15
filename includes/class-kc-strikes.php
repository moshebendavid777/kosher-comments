<?php
/**
 * Strike and ban management.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Strikes {

	/**
	 * Add a strike to a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason Optional reason.
	 * @return int
	 */
	public function add_strike( $user_id, $reason = '' ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$threshold = max( 1, absint( kosher_comments_get_setting( 'lock_threshold', 3 ) ) );
		$table     = kosher_comments_get_table_name( 'user_strikes' );
		$row       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		$strikes = $row ? ( (int) $row->strikes + 1 ) : 1;
		$locked  = $strikes >= $threshold ? 1 : 0;
		$data    = array(
			'user_id'   => $user_id,
			'strikes'   => $strikes,
			'is_locked' => $locked,
			'locked_at' => $locked ? current_time( 'mysql' ) : null,
			'updated_at'=> current_time( 'mysql' ),
		);

		if ( $row ) {
			$wpdb->update(
				$table,
				$data,
				array( 'user_id' => $user_id ),
				array( '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				$data,
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}

		update_user_meta( $user_id, 'kosher_comments_strikes', $strikes );
		update_user_meta( $user_id, 'kosher_comments_is_locked', $locked );
		update_user_meta( $user_id, 'kosher_comments_last_strike_reason', sanitize_text_field( $reason ) );

		return $strikes;
	}

	/**
	 * Reset strikes for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function reset_strikes( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		$wpdb->update(
			kosher_comments_get_table_name( 'user_strikes' ),
			array(
				'strikes'    => 0,
				'is_locked'  => 0,
				'locked_at'  => null,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		update_user_meta( $user_id, 'kosher_comments_strikes', 0 );
		update_user_meta( $user_id, 'kosher_comments_is_locked', 0 );
	}

	/**
	 * Unlock a user without deleting history.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function unlock_user( $user_id ) {
		$this->reset_strikes( $user_id );
	}

	/**
	 * Determine whether a user is locked.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_user_locked( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT is_locked FROM " . kosher_comments_get_table_name( 'user_strikes' ) . ' WHERE user_id = %d',
				$user_id
			)
		);

		return ! empty( $row->is_locked );
	}

	/**
	 * Get strike rows for the moderation screen.
	 *
	 * @return array<int, object>
	 */
	public function get_strike_rows() {
		global $wpdb;

		$table = kosher_comments_get_table_name( 'user_strikes' );

		return $wpdb->get_results(
			"SELECT strikes_table.*, users.user_email, users.display_name
			FROM {$table} strikes_table
			LEFT JOIN {$wpdb->users} users ON users.ID = strikes_table.user_id
			WHERE strikes_table.strikes > 0 OR strikes_table.is_locked = 1
			ORDER BY strikes_table.is_locked DESC, strikes_table.strikes DESC, strikes_table.updated_at DESC"
		);
	}

	/**
	 * Ban an email address.
	 *
	 * @param string $email Email address.
	 * @param string $reason Optional reason.
	 * @return bool
	 */
	public function ban_email( $email, $reason = '' ) {
		global $wpdb;

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$wpdb->replace(
			kosher_comments_get_table_name( 'banned_emails' ),
			array(
				'email'      => $email,
				'reason'     => sanitize_text_field( $reason ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Remove a banned email.
	 *
	 * @param string $email Email address.
	 * @return void
	 */
	public function unban_email( $email ) {
		global $wpdb;

		$wpdb->delete(
			kosher_comments_get_table_name( 'banned_emails' ),
			array( 'email' => sanitize_email( $email ) ),
			array( '%s' )
		);
	}

	/**
	 * Check whether an email is banned.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function is_email_banned( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . kosher_comments_get_table_name( 'banned_emails' ) . ' WHERE email = %s',
				$email
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get banned email rows.
	 *
	 * @return array<int, object>
	 */
	public function get_banned_emails() {
		global $wpdb;

		return $wpdb->get_results(
			'SELECT * FROM ' . kosher_comments_get_table_name( 'banned_emails' ) . ' ORDER BY created_at DESC'
		);
	}
}
