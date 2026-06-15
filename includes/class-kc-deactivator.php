<?php
/**
 * Deactivation logic.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Deactivator {

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'kosher_comments_cleanup' );
	}
}
