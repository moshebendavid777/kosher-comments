<?php
/**
 * Activation logic.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Activator {

	/**
	 * Create or update plugin schema.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'comments' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_comment_id bigint(20) unsigned DEFAULT NULL,
				post_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				author_name varchar(190) NOT NULL DEFAULT '',
				author_email varchar(190) NOT NULL DEFAULT '',
				parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
				content longtext NOT NULL,
				rating tinyint(1) unsigned DEFAULT NULL,
				is_question tinyint(1) unsigned NOT NULL DEFAULT 0,
				notify_replies tinyint(1) unsigned NOT NULL DEFAULT 1,
				like_count bigint(20) unsigned NOT NULL DEFAULT 0,
				dislike_count bigint(20) unsigned NOT NULL DEFAULT 0,
				reply_count bigint(20) unsigned NOT NULL DEFAULT 0,
				image_count bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'approved',
				moderation_reason text NULL,
				location_country varchar(20) NOT NULL DEFAULT '',
				user_ip varchar(100) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY wp_comment_id (wp_comment_id),
				KEY post_parent (post_id, parent_id, status, created_at),
				KEY user_id (user_id),
				KEY status (status)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'user_strikes' ) . " (
				user_id bigint(20) unsigned NOT NULL,
				strikes bigint(20) unsigned NOT NULL DEFAULT 0,
				is_locked tinyint(1) unsigned NOT NULL DEFAULT 0,
				locked_at datetime NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (user_id),
				KEY is_locked (is_locked)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'comment_images' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				comment_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				image_url text NOT NULL,
				sort_order bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY comment_id (comment_id)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'comment_votes' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				comment_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				vote_type varchar(10) NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY unique_vote (comment_id, user_id),
				KEY vote_type (vote_type)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'analytics' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				comment_id bigint(20) unsigned DEFAULT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				ip varchar(100) NOT NULL DEFAULT '',
				country varchar(100) NOT NULL DEFAULT '',
				action varchar(50) NOT NULL,
				meta longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY post_id (post_id),
				KEY action (action),
				KEY comment_id (comment_id)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'banned_emails' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				reason text NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email)
			) $charset_collate;"
		);

		dbDelta(
			"CREATE TABLE " . kosher_comments_get_table_name( 'reports' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				reporter_user_id bigint(20) unsigned NOT NULL,
				comment_id bigint(20) unsigned DEFAULT NULL,
				image_id bigint(20) unsigned DEFAULT NULL,
				report_type varchar(20) NOT NULL,
				reason text NULL,
				status varchar(20) NOT NULL DEFAULT 'open',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY report_type (report_type),
				KEY comment_id (comment_id),
				KEY image_id (image_id)
			) $charset_collate;"
		);

		add_option( 'kosher_comments_settings', kosher_comments_get_settings() );
		update_option( 'kosher_comments_version', KOSHER_COMMENTS_VERSION );
	}
}
