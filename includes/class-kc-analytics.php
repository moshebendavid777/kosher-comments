<?php
/**
 * Analytics service.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Analytics {

	/**
	 * Track a plugin event.
	 *
	 * @param array<string, mixed> $data Analytics payload.
	 * @return void
	 */
	public function track( $data = array() ) {
		global $wpdb;

		$wpdb->insert(
			kosher_comments_get_table_name( 'analytics' ),
			array(
				'post_id'    => absint( $data['post_id'] ?? 0 ),
				'comment_id' => ! empty( $data['comment_id'] ) ? absint( $data['comment_id'] ) : null,
				'user_id'    => get_current_user_id() ? absint( get_current_user_id() ) : null,
				'ip'         => $this->get_request_ip(),
				'country'    => $this->get_country_code(),
				'action'     => sanitize_key( (string) ( $data['action'] ?? 'event' ) ),
				'meta'       => wp_json_encode( $data['meta'] ?? array() ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Build a post-level analytics summary.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_post_summary( $post_id ) {
		global $wpdb;

		$post_id         = absint( $post_id );
		$analytics_table = kosher_comments_get_table_name( 'analytics' );
		$votes_table     = kosher_comments_get_table_name( 'comment_votes' );
		$comments_table  = $wpdb->comments;
		$commentmeta     = $wpdb->commentmeta;

		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_comments,
					SUM(CASE WHEN comment_parent > 0 THEN 1 ELSE 0 END) AS replies
				FROM {$comments_table}
				WHERE comment_post_ID = %d
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')",
				$post_id
			),
			ARRAY_A
		);

		$rating_summary = function_exists( 'kosher_comments_get_rating_summary' ) ? kosher_comments_get_rating_summary( $post_id ) : array();

		$questions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
					AND meta.meta_value = '1'
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')",
				'_kosher_comments_is_question',
				$post_id
			)
		);

		$sentiment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN votes.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
					SUM(CASE WHEN votes.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
				FROM {$votes_table} votes
				INNER JOIN {$comments_table} comments ON comments.comment_ID = votes.comment_id
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')",
				$post_id
			),
			ARRAY_A
		);

		$blocked_comments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$analytics_table} WHERE post_id = %d AND action = %s",
				$post_id,
				'blocked_comment'
			)
		);

		$location_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta.meta_value AS country, COUNT(*) AS total
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
					AND meta.meta_value <> ''
				GROUP BY meta.meta_value
				ORDER BY total DESC
				LIMIT 10",
				'_kosher_comments_location_country',
				$post_id
			),
			ARRAY_A
		);

		$time_of_day = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(comment_date) AS hour_of_day, COUNT(*) AS total
				FROM {$comments_table}
				WHERE comment_post_ID = %d
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
				GROUP BY HOUR(comment_date)
				ORDER BY hour_of_day ASC",
				$post_id
			),
			ARRAY_A
		);

		$recent_days = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(comment_date) AS day_key, COUNT(*) AS total
				FROM {$comments_table}
				WHERE comment_post_ID = %d
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
					AND DATE(comment_date) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
				GROUP BY DATE(comment_date)
				ORDER BY day_key ASC",
				$post_id
			),
			ARRAY_A
		);

		$weekday_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT WEEKDAY(comment_date) AS weekday_index, COUNT(*) AS total
				FROM {$comments_table}
				WHERE comment_post_ID = %d
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
				GROUP BY WEEKDAY(comment_date)
				ORDER BY weekday_index ASC",
				$post_id
			),
			ARRAY_A
		);

		return array(
			'total_comments'        => (int) ( $summary['total_comments'] ?? 0 ),
			'ratings_count'         => (int) ( $rating_summary['ratings_count'] ?? 0 ),
			'average_rating'        => round( (float) ( $rating_summary['average_rating'] ?? 0 ), 2 ),
			'likes'                 => (int) ( $sentiment['likes'] ?? 0 ),
			'dislikes'              => (int) ( $sentiment['dislikes'] ?? 0 ),
			'replies'               => (int) ( $summary['replies'] ?? 0 ),
			'questions'             => $questions,
			'blocked_comments'      => $blocked_comments,
			'rating_distribution'   => isset( $rating_summary['rating_distribution'] ) && is_array( $rating_summary['rating_distribution'] ) ? $rating_summary['rating_distribution'] : array(),
			'location_distribution' => $location_distribution,
			'time_of_day'           => $this->normalize_time_distribution( $time_of_day ),
			'recent_days'           => $this->normalize_recent_days( $recent_days ),
			'weekday_distribution'  => $this->normalize_weekday_distribution( $weekday_distribution ),
		);
	}

	/**
	 * Site-wide analytics summary.
	 *
	 * @return array<string, mixed>
	 */
	public function get_sitewide_summary() {
		global $wpdb;

		$analytics_table = kosher_comments_get_table_name( 'analytics' );
		$votes_table     = kosher_comments_get_table_name( 'comment_votes' );
		$comments_table  = $wpdb->comments;
		$commentmeta     = $wpdb->commentmeta;

		$summary = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total_comments,
				SUM(CASE WHEN comment_parent > 0 THEN 1 ELSE 0 END) AS replies
			FROM {$comments_table}
			WHERE comment_approved = '1'
				AND comment_type IN ('', 'comment')",
			ARRAY_A
		);

		$ratings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS ratings_count,
					AVG(CAST(meta.meta_value AS DECIMAL(10,2))) AS average_rating
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')",
				'_kosher_comments_rating'
			),
			ARRAY_A
		);

		$questions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
					AND meta.meta_value = '1'
				WHERE comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')",
				'_kosher_comments_is_question'
			)
		);

		$sentiment = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
				SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
			FROM {$votes_table}",
			ARRAY_A
		);

		$blocked_comments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$analytics_table} WHERE action = %s",
				'blocked_comment'
			)
		);

		$top_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					comments.comment_post_ID AS post_id,
					COUNT(*) AS total_comments,
					AVG(CAST(meta.meta_value AS DECIMAL(10,2))) AS average_rating
				FROM {$comments_table} comments
				LEFT JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
				GROUP BY comments.comment_post_ID
				ORDER BY total_comments DESC
				LIMIT 10",
				'_kosher_comments_rating'
			)
		);

		$rating_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT CAST(meta.meta_value AS UNSIGNED) AS rating, COUNT(*) AS total
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
				GROUP BY CAST(meta.meta_value AS UNSIGNED)
				ORDER BY rating ASC",
				'_kosher_comments_rating'
			),
			ARRAY_A
		);

		$location_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta.meta_value AS country, COUNT(*) AS total
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
					AND meta.meta_value <> ''
				GROUP BY meta.meta_value
				ORDER BY total DESC
				LIMIT 10",
				'_kosher_comments_location_country'
			),
			ARRAY_A
		);

		$time_of_day = $wpdb->get_results(
			"SELECT HOUR(comment_date) AS hour_of_day, COUNT(*) AS total
			FROM {$comments_table}
			WHERE comment_approved = '1'
				AND comment_type IN ('', 'comment')
			GROUP BY HOUR(comment_date)
			ORDER BY hour_of_day ASC",
			ARRAY_A
		);

		$recent_days = $wpdb->get_results(
			"SELECT DATE(comment_date) AS day_key, COUNT(*) AS total
			FROM {$comments_table}
			WHERE comment_approved = '1'
				AND comment_type IN ('', 'comment')
				AND DATE(comment_date) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
			GROUP BY DATE(comment_date)
			ORDER BY day_key ASC",
			ARRAY_A
		);

		$weekday_distribution = $wpdb->get_results(
			"SELECT WEEKDAY(comment_date) AS weekday_index, COUNT(*) AS total
			FROM {$comments_table}
			WHERE comment_approved = '1'
				AND comment_type IN ('', 'comment')
			GROUP BY WEEKDAY(comment_date)
			ORDER BY weekday_index ASC",
			ARRAY_A
		);

		return array(
			'total_comments'        => (int) ( $summary['total_comments'] ?? 0 ),
			'ratings_count'         => (int) ( $ratings['ratings_count'] ?? 0 ),
			'average_rating'        => round( (float) ( $ratings['average_rating'] ?? 0 ), 2 ),
			'likes'                 => (int) ( $sentiment['likes'] ?? 0 ),
			'dislikes'              => (int) ( $sentiment['dislikes'] ?? 0 ),
			'replies'               => (int) ( $summary['replies'] ?? 0 ),
			'questions'             => $questions,
			'blocked_comments'      => $blocked_comments,
			'top_posts'             => $top_posts,
			'rating_distribution'   => $this->normalize_rating_distribution( $rating_distribution ),
			'location_distribution' => $location_distribution,
			'time_of_day'           => $this->normalize_time_distribution( $time_of_day ),
			'recent_days'           => $this->normalize_recent_days( $recent_days ),
			'weekday_distribution'  => $this->normalize_weekday_distribution( $weekday_distribution ),
		);
	}

	/**
	 * Get request IP.
	 *
	 * @return string
	 */
	public function get_request_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			if ( 'HTTP_X_FORWARDED_FOR' === $key ) {
				$parts = array_map( 'trim', explode( ',', $value ) );
				$value = (string) reset( $parts );
			}

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Get best-effort country code.
	 *
	 * @return string
	 */
	public function get_country_code() {
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
		}

		if ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) );
		}

		return (string) apply_filters( 'kosher_comments_country_code', 'Unknown' );
	}

	/**
	 * Normalize rating totals for 1-5.
	 *
	 * @param array<int, array<string, mixed>> $rows Distribution rows.
	 * @return array<int, int>
	 */
	protected function normalize_rating_distribution( $rows ) {
		$distribution = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);

		foreach ( $rows as $row ) {
			$rating = absint( $row['rating'] ?? 0 );

			if ( isset( $distribution[ $rating ] ) ) {
				$distribution[ $rating ] = (int) $row['total'];
			}
		}

		return $distribution;
	}

	/**
	 * Normalize hourly totals for 24 hours.
	 *
	 * @param array<int, array<string, mixed>> $rows Distribution rows.
	 * @return array<int, array<string, int>>
	 */
	protected function normalize_time_distribution( $rows ) {
		$distribution = array();

		for ( $hour = 0; $hour < 24; $hour++ ) {
			$distribution[ $hour ] = array(
				'hour_of_day' => $hour,
				'total'       => 0,
			);
		}

		foreach ( $rows as $row ) {
			$hour = isset( $row['hour_of_day'] ) ? absint( $row['hour_of_day'] ) : -1;

			if ( isset( $distribution[ $hour ] ) ) {
				$distribution[ $hour ]['total'] = (int) $row['total'];
			}
		}

		return array_values( $distribution );
	}

	/**
	 * Normalize recent day totals for the last 14 days.
	 *
	 * @param array<int, array<string, mixed>> $rows Distribution rows.
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalize_recent_days( $rows ) {
		$distribution = array();
		$today        = current_datetime();

		for ( $offset = 13; $offset >= 0; $offset-- ) {
			$date = clone $today;
			$date->modify( '-' . $offset . ' days' );

			$key                  = $date->format( 'Y-m-d' );
			$distribution[ $key ] = array(
				'day_key' => $key,
				'label'   => wp_date( 'M j', $date->getTimestamp() ),
				'weekday' => wp_date( 'D', $date->getTimestamp() ),
				'total'   => 0,
			);
		}

		foreach ( $rows as $row ) {
			$key = sanitize_text_field( (string) ( $row['day_key'] ?? '' ) );

			if ( isset( $distribution[ $key ] ) ) {
				$distribution[ $key ]['total'] = (int) $row['total'];
			}
		}

		return array_values( $distribution );
	}

	/**
	 * Normalize weekday totals from Monday to Sunday.
	 *
	 * @param array<int, array<string, mixed>> $rows Distribution rows.
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalize_weekday_distribution( $rows ) {
		$labels       = array(
			__( 'Mon', 'kosher-comments' ),
			__( 'Tue', 'kosher-comments' ),
			__( 'Wed', 'kosher-comments' ),
			__( 'Thu', 'kosher-comments' ),
			__( 'Fri', 'kosher-comments' ),
			__( 'Sat', 'kosher-comments' ),
			__( 'Sun', 'kosher-comments' ),
		);
		$distribution = array();

		foreach ( $labels as $index => $label ) {
			$distribution[ $index ] = array(
				'weekday_index' => $index,
				'label'         => $label,
				'total'         => 0,
			);
		}

		foreach ( $rows as $row ) {
			$index = isset( $row['weekday_index'] ) ? absint( $row['weekday_index'] ) : -1;

			if ( isset( $distribution[ $index ] ) ) {
				$distribution[ $index ]['total'] = (int) $row['total'];
			}
		}

		return array_values( $distribution );
	}
}
