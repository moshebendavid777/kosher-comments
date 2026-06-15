<?php
/**
 * Plugin Name: Kosher Comments
 * Description: AI-powered commenting system for Kosher.com
 * Version: 2.1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Kosher.com
 * Text Domain: kosher-comments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOSHER_COMMENTS_VERSION', '2.1.1' );
define( 'KOSHER_COMMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KOSHER_COMMENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'KOSHER_COMMENTS_BASENAME', plugin_basename( __FILE__ ) );

if ( ! function_exists( 'kosher_comments_get_table_name' ) ) {
	/**
	 * Build the plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	function kosher_comments_get_table_name( $suffix ) {
		global $wpdb;

		return $wpdb->prefix . 'kc_' . sanitize_key( $suffix );
	}
}

if ( ! function_exists( 'kosher_comments_get_settings' ) ) {
	/**
	 * Retrieve plugin settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	function kosher_comments_get_settings() {
		$defaults = array(
			'openai_api_key'         => '',
			'moderation_model'       => 'gpt-4.1-mini',
			'moderation_enabled'     => 'yes',
			'comments_per_page'      => 5,
			'max_images_per_comment' => 5,
			'max_image_size_mb'      => 5,
			'lock_threshold'         => 3,
			'notification_email'     => 'info@kosher.com',
		);

		$settings = get_option( 'kosher_comments_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}
}

if ( ! function_exists( 'kosher_comments_get_setting' ) ) {
	/**
	 * Retrieve a single plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	function kosher_comments_get_setting( $key, $default = '' ) {
		$settings = kosher_comments_get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}
}

if ( ! function_exists( 'kosher_comments_get_rating_summary' ) ) {
	/**
	 * Return the canonical rating summary for a post.
	 *
	 * Live ratings come from Kosher Comments comment meta. Legacy rating-only
	 * feedback can be layered in through aggregate post meta without creating
	 * fake comments.
	 *
	 * @param int  $post_id        Post ID.
	 * @param bool $include_legacy Whether to include migrated legacy rating aggregates.
	 * @return array<string, mixed>
	 */
	function kosher_comments_get_rating_summary( $post_id, $include_legacy = true ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return kosher_comments_normalize_rating_summary();
		}

		$comments_table = $wpdb->comments;
		$commentmeta    = $wpdb->commentmeta;

		$ratings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS ratings_count,
					SUM(CAST(meta.meta_value AS DECIMAL(10,2))) AS rating_sum,
					AVG(CAST(meta.meta_value AS DECIMAL(10,2))) AS average_rating
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')",
				'_kosher_comments_rating',
				$post_id
			),
			ARRAY_A
		);

		$rating_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT CAST(meta.meta_value AS UNSIGNED) AS rating, COUNT(*) AS total
				FROM {$comments_table} comments
				INNER JOIN {$commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
				GROUP BY CAST(meta.meta_value AS UNSIGNED)
				ORDER BY rating ASC",
				'_kosher_comments_rating',
				$post_id
			),
			ARRAY_A
		);

		$summary = kosher_comments_normalize_rating_summary(
			array(
				'ratings_count'       => (int) ( $ratings['ratings_count'] ?? 0 ),
				'rating_sum'          => (float) ( $ratings['rating_sum'] ?? 0 ),
				'average_rating'      => (float) ( $ratings['average_rating'] ?? 0 ),
				'rating_distribution' => kosher_comments_normalize_rating_distribution( $rating_distribution ),
			)
		);

		if ( $include_legacy ) {
			$summary = kosher_comments_merge_legacy_rating_summary( $post_id, $summary );
		}

		return $summary;
	}
}

if ( ! function_exists( 'kosher_comments_normalize_rating_summary' ) ) {
	/**
	 * Normalize a rating summary payload.
	 *
	 * @param array<string, mixed> $summary Summary data.
	 * @return array<string, mixed>
	 */
	function kosher_comments_normalize_rating_summary( $summary = array() ) {
		$count        = max( 0, (int) ( $summary['ratings_count'] ?? 0 ) );
		$sum          = max( 0, (float) ( $summary['rating_sum'] ?? 0 ) );
		$average      = $count > 0 ? $sum / $count : (float) ( $summary['average_rating'] ?? 0 );
		$distribution = isset( $summary['rating_distribution'] ) && is_array( $summary['rating_distribution'] ) ? $summary['rating_distribution'] : array();

		return array(
			'ratings_count'       => $count,
			'rating_sum'          => $sum,
			'average_rating'      => round( $average, 2 ),
			'rating_distribution' => kosher_comments_normalize_rating_distribution( $distribution ),
		);
	}
}

if ( ! function_exists( 'kosher_comments_normalize_rating_distribution' ) ) {
	/**
	 * Normalize rating distribution rows or arrays.
	 *
	 * @param array<mixed> $rows Distribution rows.
	 * @return array<int, int>
	 */
	function kosher_comments_normalize_rating_distribution( $rows ) {
		$distribution = array_fill( 1, 5, 0 );

		foreach ( (array) $rows as $key => $row ) {
			if ( is_array( $row ) ) {
				$rating = absint( $row['rating'] ?? $key );
				$total  = absint( $row['total'] ?? $row['count'] ?? 0 );
			} else {
				$rating = absint( $key );
				$total  = absint( $row );
			}

			if ( $rating >= 1 && $rating <= 5 ) {
				$distribution[ $rating ] += $total;
			}
		}

		return $distribution;
	}
}

if ( ! function_exists( 'kosher_comments_get_legacy_rmp_rating_distribution' ) ) {
	/**
	 * Return real legacy Rate My Post star buckets from its analytics table.
	 *
	 * Rate My Post stores aggregate count/sum on post meta, but the per-vote
	 * star value only exists when its analytics table was enabled.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, int>
	 */
	function kosher_comments_get_legacy_rmp_rating_distribution( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return kosher_comments_normalize_rating_distribution( array() );
		}

		$table_name = $wpdb->prefix . 'rmp_analytics';
		$table_like = $wpdb->esc_like( $table_name );
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_like
			)
		);

		if ( $table_exists !== $table_name ) {
			return kosher_comments_normalize_rating_distribution( array() );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT value AS rating, COUNT(*) AS total
				FROM {$table_name}
				WHERE post = %d
					AND action = 1
					AND value BETWEEN 1 AND 5
				GROUP BY value
				ORDER BY value ASC",
				$post_id
			),
			ARRAY_A
		);

		return kosher_comments_normalize_rating_distribution( $rows );
	}
}

if ( ! function_exists( 'kosher_comments_merge_legacy_rating_summary' ) ) {
	/**
	 * Merge migrated legacy rating aggregates into the canonical summary.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $summary Current summary.
	 * @return array<string, mixed>
	 */
	function kosher_comments_merge_legacy_rating_summary( $post_id, $summary ) {
		$legacy_count = (int) get_post_meta( $post_id, '_kayco_legacy_rating_count', true );

		if ( $legacy_count <= 0 ) {
			return $summary;
		}

		$legacy_sum = (float) get_post_meta( $post_id, '_kayco_legacy_rating_sum', true );

		if ( $legacy_sum <= 0 ) {
			$legacy_average = (float) get_post_meta( $post_id, '_kayco_legacy_rating_average', true );
			$legacy_sum     = $legacy_average > 0 ? $legacy_average * $legacy_count : 0;
		}

		$legacy_distribution = get_post_meta( $post_id, '_kayco_legacy_rating_distribution', true );

		if ( is_string( $legacy_distribution ) ) {
			$decoded = json_decode( $legacy_distribution, true );

			if ( is_array( $decoded ) ) {
				$legacy_distribution = $decoded;
			}
		}

		$current_distribution = isset( $summary['rating_distribution'] ) && is_array( $summary['rating_distribution'] ) ? $summary['rating_distribution'] : array();
		$legacy_distribution  = kosher_comments_normalize_rating_distribution( is_array( $legacy_distribution ) ? $legacy_distribution : array() );

		if ( 0 === array_sum( $legacy_distribution ) ) {
			$legacy_distribution = kosher_comments_get_legacy_rmp_rating_distribution( $post_id );
		}

		foreach ( $legacy_distribution as $rating => $total ) {
			$current_distribution[ $rating ] = (int) ( $current_distribution[ $rating ] ?? 0 ) + (int) $total;
		}

		return kosher_comments_normalize_rating_summary(
			array(
				'ratings_count'       => (int) ( $summary['ratings_count'] ?? 0 ) + $legacy_count,
				'rating_sum'          => (float) ( $summary['rating_sum'] ?? 0 ) + $legacy_sum,
				'rating_distribution' => $current_distribution,
			)
		);
	}
}

if ( ! function_exists( 'kosher_comments_sync_post_rating_summary' ) ) {
	/**
	 * Store the canonical rating summary as sortable post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function kosher_comments_sync_post_rating_summary( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! function_exists( 'kosher_comments_get_rating_summary' ) ) {
			return;
		}

		$summary = kosher_comments_get_rating_summary( $post_id );

		update_post_meta( $post_id, '_kosher_comments_rating_average', (float) ( $summary['average_rating'] ?? 0 ) );
		update_post_meta( $post_id, '_kosher_comments_rating_count', (int) ( $summary['ratings_count'] ?? 0 ) );
		update_post_meta( $post_id, '_kosher_comments_rating_sum', (float) ( $summary['rating_sum'] ?? 0 ) );
	}
}

if ( ! function_exists( 'kosher_comments_sync_post_rating_summary_for_comment' ) ) {
	/**
	 * Sync rating summary meta for a comment's post.
	 *
	 * @param int|WP_Comment $comment Comment ID or object.
	 * @return void
	 */
	function kosher_comments_sync_post_rating_summary_for_comment( $comment ) {
		$comment = get_comment( $comment );

		if ( $comment && ! empty( $comment->comment_post_ID ) ) {
			kosher_comments_sync_post_rating_summary( (int) $comment->comment_post_ID );
		}
	}
}

if ( ! function_exists( 'kosher_comments_migrate_legacy_rmp_ratings' ) ) {
	/**
	 * Migrate legacy Rate My Post rating aggregates into Kosher Comments rating meta.
	 *
	 * This intentionally does not create comments. It preserves rating-only legacy
	 * feedback as aggregate data that can be combined with live Kosher Comments ratings.
	 *
	 * @param array<string, mixed> $args Migration args.
	 * @return array<string, int>
	 */
	function kosher_comments_migrate_legacy_rmp_ratings( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_types'     => array( 'recipes', 'articles', 'episodes' ),
				'posts_per_page' => 200,
				'paged'          => 1,
				'dry_run'        => true,
				'force'          => false,
			)
		);

		$query = new WP_Query(
			array(
				'post_type'              => array_map( 'sanitize_key', (array) $args['post_types'] ),
				'post_status'            => 'any',
				'posts_per_page'         => max( 1, absint( $args['posts_per_page'] ) ),
				'paged'                  => max( 1, absint( $args['paged'] ) ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'     => 'rmp_vote_count',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'rmp_avg_rating',
						'compare' => 'EXISTS',
					),
				),
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$results = array(
			'found'    => (int) $query->found_posts,
			'checked'  => 0,
			'migrated' => 0,
			'skipped'  => 0,
		);

		foreach ( $query->posts as $post_id ) {
			$post_id              = absint( $post_id );
			$results['checked']++;
			$already_migrated     = (bool) get_post_meta( $post_id, '_kayco_legacy_rating_migrated_from_rmp', true );
			$legacy_vote_count    = (int) get_post_meta( $post_id, 'rmp_vote_count', true );
			$legacy_rating_sum    = (float) get_post_meta( $post_id, 'rmp_rating_val_sum', true );
			$legacy_average       = (float) get_post_meta( $post_id, 'rmp_avg_rating', true );
			$legacy_distribution  = kosher_comments_get_legacy_rmp_rating_distribution( $post_id );

			if ( $already_migrated && ! $args['force'] ) {
				$results['skipped']++;
				continue;
			}

			if ( $legacy_vote_count <= 0 || $legacy_average <= 0 ) {
				$results['skipped']++;
				continue;
			}

			if ( $legacy_rating_sum <= 0 ) {
				$legacy_rating_sum = $legacy_average * $legacy_vote_count;
			}

			if ( ! $args['dry_run'] ) {
				update_post_meta( $post_id, '_kayco_legacy_rating_count', $legacy_vote_count );
				update_post_meta( $post_id, '_kayco_legacy_rating_sum', $legacy_rating_sum );
				update_post_meta( $post_id, '_kayco_legacy_rating_average', $legacy_average );
				update_post_meta( $post_id, '_kayco_legacy_rating_distribution', $legacy_distribution );
				update_post_meta( $post_id, '_kayco_legacy_rating_source', 'rate-my-post' );
				update_post_meta( $post_id, '_kayco_legacy_rating_migrated_from_rmp', 1 );
				update_post_meta( $post_id, '_kayco_legacy_rating_migrated_at', current_time( 'mysql' ) );
				update_post_meta(
					$post_id,
					'_kayco_legacy_rating_original_payload',
					array(
						'rmp_vote_count'     => $legacy_vote_count,
						'rmp_rating_val_sum' => $legacy_rating_sum,
						'rmp_avg_rating'     => $legacy_average,
						'rmp_distribution'   => $legacy_distribution,
					)
				);

				kosher_comments_sync_post_rating_summary( $post_id );
			}

			$results['migrated']++;
		}

		return $results;
	}
}

if ( ! function_exists( 'kosher_comments_maybe_sync_rating_meta' ) ) {
	/**
	 * Sync post rating summary when a rating meta value changes.
	 *
	 * @param mixed  $meta_id    Meta ID.
	 * @param int    $comment_id Comment ID.
	 * @param string $meta_key   Meta key.
	 * @return void
	 */
	function kosher_comments_maybe_sync_rating_meta( $meta_id, $comment_id, $meta_key ) {
		if ( '_kosher_comments_rating' !== $meta_key ) {
			return;
		}

		kosher_comments_sync_post_rating_summary_for_comment( $comment_id );
	}
}

add_action( 'added_comment_meta', 'kosher_comments_maybe_sync_rating_meta', 10, 3 );
add_action( 'updated_comment_meta', 'kosher_comments_maybe_sync_rating_meta', 10, 3 );
add_action( 'deleted_comment_meta', 'kosher_comments_maybe_sync_rating_meta', 10, 3 );
add_action( 'trashed_comment', 'kosher_comments_sync_post_rating_summary_for_comment' );
add_action( 'untrashed_comment', 'kosher_comments_sync_post_rating_summary_for_comment' );
add_action( 'delete_comment', 'kosher_comments_sync_post_rating_summary_for_comment' );
add_action( 'deleted_comment', 'kosher_comments_sync_post_rating_summary_for_comment' );
add_action(
	'transition_comment_status',
	static function ( $new_status, $old_status, $comment ) {
		if ( $new_status !== $old_status ) {
			kosher_comments_sync_post_rating_summary_for_comment( $comment );
		}
	},
	10,
	3
);

require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-activator.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-deactivator.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-api.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-strikes.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-analytics.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-auth.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-comments.php';
require_once KOSHER_COMMENTS_PATH . 'admin/class-kc-admin.php';
require_once KOSHER_COMMENTS_PATH . 'public/class-kc-public.php';
require_once KOSHER_COMMENTS_PATH . 'includes/class-kc-loader.php';

register_activation_hook( __FILE__, array( 'Kosher_Comments_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Kosher_Comments_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return void
 */
function kosher_comments_run_plugin() {
	$plugin = new Kosher_Comments_Loader();
	$plugin->run();
}

kosher_comments_run_plugin();
