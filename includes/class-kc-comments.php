<?php
/**
 * Comment operations.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Comments {

	const META_RATING            = '_kosher_comments_rating';
	const META_IS_QUESTION       = '_kosher_comments_is_question';
	const META_NOTIFY_REPLIES    = '_kosher_comments_notify_replies';
	const META_LOCATION_COUNTRY  = '_kosher_comments_location_country';
	const META_MODERATION_REASON = '_kosher_comments_moderation_reason';
	const COMMENT_TYPE_RATING    = 'kosher_rating';

	/**
	 * Moderation API.
	 *
	 * @var Kosher_Comments_API
	 */
	protected $api;

	/**
	 * Strike service.
	 *
	 * @var Kosher_Comments_Strikes
	 */
	protected $strikes;

	/**
	 * Analytics service.
	 *
	 * @var Kosher_Comments_Analytics
	 */
	protected $analytics;

	/**
	 * Constructor.
	 *
	 * @param Kosher_Comments_API       $api Moderation API.
	 * @param Kosher_Comments_Strikes   $strikes Strike service.
	 * @param Kosher_Comments_Analytics $analytics Analytics service.
	 */
	public function __construct( Kosher_Comments_API $api, Kosher_Comments_Strikes $strikes, Kosher_Comments_Analytics $analytics ) {
		$this->api       = $api;
		$this->strikes   = $strikes;
		$this->analytics = $analytics;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_kosher_comments_submit_comment', array( $this, 'handle_submit_comment' ) );
		add_action( 'wp_ajax_kosher_comments_vote_comment', array( $this, 'handle_vote_comment' ) );
		add_action( 'wp_ajax_kosher_comments_load_comments', array( $this, 'handle_load_comments' ) );
		add_action( 'wp_ajax_nopriv_kosher_comments_load_comments', array( $this, 'handle_load_comments' ) );
		add_action( 'wp_ajax_kosher_comments_edit_comment', array( $this, 'handle_edit_comment' ) );
		add_action( 'wp_ajax_kosher_comments_update_rating', array( $this, 'handle_update_rating' ) );
		add_action( 'wp_ajax_kosher_comments_delete_comment', array( $this, 'handle_delete_comment' ) );
		add_action( 'wp_ajax_kosher_comments_report_item', array( $this, 'handle_report_item' ) );
	}

	/**
	 * Submit a new comment.
	 *
	 * @return void
	 */
	public function handle_submit_comment() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Login required.', 'kosher-comments' ),
				),
				403
			);
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to load the current user.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( $this->strikes->is_user_locked( $user_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your account was locked for misconduct. Contact info@kosher.com', 'kosher-comments' ),
				),
				403
			);
		}

		if ( $this->strikes->is_email_banned( $user->user_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This email has been banned from posting comments.', 'kosher-comments' ),
				),
				403
			);
		}

		$post_id        = absint( $_POST['post_id'] ?? 0 );
		$parent_id      = absint( $_POST['parent_id'] ?? 0 );
		$comment_text   = $this->sanitize_comment_content( $_POST['comment_text'] ?? '' );
		$comment_plain  = $this->get_plain_comment_text( $comment_text );
		$rating         = isset( $_POST['rating'] ) && '' !== wp_unslash( $_POST['rating'] ) ? absint( $_POST['rating'] ) : null;
		$rating_only    = ! empty( $_POST['rating_only'] );
		$is_question    = ! empty( $_POST['is_question'] ) ? 1 : 0;
		$notify_replies = ! empty( $_POST['notify_replies'] ) ? 1 : 0;
		$post           = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid post.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( '' === $comment_plain && ! $rating_only ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please write a comment before posting.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( $rating_only ) {
			$is_question    = 0;
			$notify_replies = 0;
		}

		$disallowed_hosts = $rating_only || $this->current_user_can_post_restricted_links() ? array() : $this->get_disallowed_comment_link_hosts( $comment_text );

		if ( ! empty( $disallowed_hosts ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Links are only allowed to this site, kosher.com, kayco.com, manischewitz.com, and royalwine.com.', 'kosher-comments' ),
					'hosts'   => $disallowed_hosts,
				),
				400
			);
		}

		$parent_comment = null;

		if ( $parent_id ) {
			$parent_comment = $this->get_comment_row( $parent_id );

			if ( ! $parent_comment || (int) $parent_comment->post_id !== $post_id ) {
				wp_send_json_error(
					array(
						'message' => __( 'The comment you are replying to could not be found.', 'kosher-comments' ),
					),
					404
				);
			}

			$rating      = null;
			$rating_only = false;
			$is_question = 0;
		}

		if ( $rating_only && null === $rating ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please choose a rating before submitting.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( null !== $rating && ( $rating < 1 || $rating > 5 ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ratings must be between 1 and 5.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( null !== $rating && $this->user_has_post_rating( $post_id, $user_id ) ) {
			if ( $rating_only ) {
				wp_send_json_error(
					array(
						'message' => __( 'You already rated this post.', 'kosher-comments' ),
					),
					400
				);
			}

			$rating = null;
		}

		$moderation = $rating_only
			? array(
				'is_toxic' => false,
				'reason'   => '',
			)
			: $this->api->moderate_comment(
				$comment_plain,
				(bool) $parent_id,
				$parent_comment ? $this->get_plain_comment_text( (string) $parent_comment->content ) : ''
			);

		if ( ! empty( $moderation['is_toxic'] ) ) {
			$strikes = $this->strikes->add_strike( $user_id, (string) ( $moderation['reason'] ?? '' ) );

			$this->analytics->track(
				array(
					'post_id' => $post_id,
					'user_id' => $user_id,
					'action'  => 'blocked_comment',
					'meta'    => array(
						'is_reply' => (bool) $parent_id,
						'reason'   => (string) ( $moderation['reason'] ?? '' ),
						'strikes'  => $strikes,
					),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'You got 1 strike. Please be mindful of your comment.', 'kosher-comments' ),
					'strikes' => $strikes,
					'locked'  => $this->strikes->is_user_locked( $user_id ),
				),
				403
			);
		}

		$comment_date = current_time( 'mysql' );
		$comment_id   = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_parent'       => $parent_id,
				'user_id'              => $user_id,
				'comment_author'       => sanitize_text_field( $user->display_name ),
				'comment_author_email' => sanitize_email( $user->user_email ),
				'comment_author_IP'    => $this->analytics->get_request_ip(),
				'comment_content'      => $rating_only ? '' : $comment_text,
				'comment_type'         => $rating_only ? self::COMMENT_TYPE_RATING : '',
				'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'comment_date'         => $comment_date,
				'comment_date_gmt'     => get_gmt_from_date( $comment_date ),
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'We could not save your comment. Please try again.', 'kosher-comments' ),
				),
				500
			);
		}

		$country_code = $this->analytics->get_country_code();

		update_comment_meta( $comment_id, self::META_NOTIFY_REPLIES, $notify_replies ? 1 : 0 );

		if ( null !== $rating ) {
			update_comment_meta( $comment_id, self::META_RATING, $rating );
		}

		if ( $is_question ) {
			update_comment_meta( $comment_id, self::META_IS_QUESTION, 1 );
		}

		if ( '' !== $country_code ) {
			update_comment_meta( $comment_id, self::META_LOCATION_COUNTRY, $country_code );
		}

		if ( ! empty( $moderation['reason'] ) ) {
			update_comment_meta( $comment_id, self::META_MODERATION_REASON, sanitize_text_field( (string) $moderation['reason'] ) );
		}

		$image_urls = $rating_only ? array() : $this->handle_image_uploads( $comment_id, $post_id );

		if ( ! $rating_only && $parent_id ) {
			$this->maybe_notify_parent_author( $parent_comment, $comment_id, $post, $comment_plain );
		}

		if ( ! $rating_only && $is_question ) {
			$this->maybe_notify_post_author( $post, $comment_id, $comment_plain );
		}

		$this->analytics->track(
			array(
				'post_id'    => $post_id,
				'comment_id' => $comment_id,
				'action'     => $rating_only ? 'rating_posted' : ( $parent_id ? 'reply_posted' : 'comment_posted' ),
				'meta'       => array(
					'rating'   => $rating,
					'question' => (bool) $is_question,
					'images'   => count( $image_urls ),
					'is_reply' => (bool) $parent_id,
					'rating_only' => (bool) $rating_only,
				),
			)
		);

		$comment = $rating_only ? null : $this->get_comment_for_render( $comment_id );
		$depth   = $parent_id ? $this->get_comment_depth( $comment_id ) : 0;
		$html    = $comment ? Kosher_Comments_Public::render_single_comment( $comment, $depth, 0 ) : '';

		wp_send_json_success(
			array(
				'message'    => $rating_only ? __( 'Rating submitted successfully.', 'kosher-comments' ) : __( 'Comment posted successfully.', 'kosher-comments' ),
				'commentId'  => $comment_id,
				'parentId'   => $parent_id,
				'html'       => $html,
				'ratingOnly' => (bool) $rating_only,
				'summary'    => null !== $rating && empty( $parent_id ) ? $this->get_rating_summary_payload( $post_id ) : null,
			)
		);
	}

	/**
	 * Toggle a like or dislike.
	 *
	 * @return void
	 */
	public function handle_vote_comment() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Login required.', 'kosher-comments' ),
				),
				403
			);
		}

		$comment_id = absint( $_POST['comment_id'] ?? 0 );
		$type       = sanitize_key( (string) ( $_POST['vote_type'] ?? '' ) );

		if ( ! in_array( $type, array( 'like', 'dislike' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid vote type.', 'kosher-comments' ),
				),
				400
			);
		}

		$comment = $this->get_comment_row( $comment_id );

		if ( ! $comment ) {
			wp_send_json_error(
				array(
					'message' => __( 'Comment not found.', 'kosher-comments' ),
				),
				404
			);
		}

		global $wpdb;

		$votes_table   = kosher_comments_get_table_name( 'comment_votes' );
		$existing_vote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$votes_table} WHERE comment_id = %d AND user_id = %d",
				$comment_id,
				$user_id
			)
		);
		$active_vote   = $type;

		if ( $existing_vote && $existing_vote->vote_type === $type ) {
			$wpdb->delete(
				$votes_table,
				array(
					'comment_id' => $comment_id,
					'user_id'    => $user_id,
				),
				array( '%d', '%d' )
			);

			$active_vote = '';
		} elseif ( $existing_vote ) {
			$wpdb->update(
				$votes_table,
				array( 'vote_type' => $type ),
				array(
					'comment_id' => $comment_id,
					'user_id'    => $user_id,
				),
				array( '%s' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$votes_table,
				array(
					'comment_id' => $comment_id,
					'user_id'    => $user_id,
					'vote_type'  => $type,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		$this->analytics->track(
			array(
				'post_id'    => (int) $comment->post_id,
				'comment_id' => $comment_id,
				'action'     => '' === $active_vote ? 'vote_removed' : 'comment_' . $active_vote,
				'meta'       => array(
					'user_id' => $user_id,
				),
			)
		);

		$counts = $this->get_vote_counts_map( array( $comment_id ) );
		$totals = $counts[ $comment_id ] ?? array(
			'like_count'    => 0,
			'dislike_count' => 0,
		);

		wp_send_json_success(
			array(
				'commentId'    => $comment_id,
				'likeCount'    => (int) $totals['like_count'],
				'dislikeCount' => (int) $totals['dislike_count'],
				'activeVote'   => $active_vote,
			)
		);
	}

	/**
	 * Load paginated comments.
	 *
	 * @return void
	 */
	public function handle_load_comments() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$post_id           = absint( $_POST['post_id'] ?? 0 );
		$page              = max( 1, absint( $_POST['page'] ?? 1 ) );
		$target_comment_id = absint( $_POST['target_comment_id'] ?? 0 );

		wp_send_json_success( $this->get_comments_page( $post_id, $page, $target_comment_id ) );
	}

	/**
	 * Edit a comment from the frontend for admins/editors.
	 *
	 * @return void
	 */
	public function handle_edit_comment() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$comment_id    = absint( $_POST['comment_id'] ?? 0 );
		$comment_text  = $this->sanitize_comment_content( $_POST['comment_text'] ?? '' );
		$comment_plain = $this->get_plain_comment_text( $comment_text );
		$rating        = isset( $_POST['rating'] ) && '' !== wp_unslash( $_POST['rating'] ) ? absint( $_POST['rating'] ) : null;
		$existing      = $this->get_comment_row( $comment_id, false );

		if ( ! $existing ) {
			wp_send_json_error(
				array(
					'message' => __( 'Comment not found.', 'kosher-comments' ),
				),
				404
			);
		}

		$current_user_id = get_current_user_id();
		$can_moderate    = $this->current_user_can_frontend_moderate();
		$is_owner_review = $current_user_id && (int) $existing->user_id === (int) $current_user_id && empty( $existing->parent_id ) && ! empty( $existing->rating );

		if ( ! $can_moderate && ! $is_owner_review ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to edit comments.', 'kosher-comments' ),
				),
				403
			);
		}

		if ( ! $can_moderate && $is_owner_review ) {
			$comment_text  = (string) $existing->content;
			$comment_plain = $this->get_plain_comment_text( $comment_text );
		}

		if ( '' === $comment_plain ) {
			wp_send_json_error(
				array(
					'message' => __( 'Comment text cannot be empty.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( null !== $rating && ( $rating < 1 || $rating > 5 ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ratings must be between 1 and 5.', 'kosher-comments' ),
				),
				400
			);
		}

		$disallowed_hosts = $this->current_user_can_post_restricted_links() ? array() : $this->get_disallowed_comment_link_hosts( $comment_text );

		if ( ! empty( $disallowed_hosts ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Links are only allowed to this site, kosher.com, kayco.com, manischewitz.com, and royalwine.com.', 'kosher-comments' ),
					'hosts'   => $disallowed_hosts,
				),
				400
			);
		}

		$updated = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => $comment_text,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Comment update failed.', 'kosher-comments' ),
				),
				500
			);
		}

		$rating_updated = null !== $rating && empty( $existing->parent_id ) && ! empty( $existing->rating );

		if ( $rating_updated ) {
			update_comment_meta( $comment_id, self::META_RATING, $rating );
		}

		$this->analytics->track(
			array(
				'post_id'    => (int) $existing->post_id,
				'comment_id' => $comment_id,
				'action'     => 'comment_edited',
				'meta'       => array(
					'rating' => null !== $rating ? $rating : null,
				),
			)
		);

		$comment = $this->get_comment_for_render( $comment_id );

		wp_send_json_success(
			array(
				'message'   => __( 'Comment updated.', 'kosher-comments' ),
				'commentId' => $comment_id,
				'html'      => $comment ? Kosher_Comments_Public::render_single_comment( $comment, $this->get_comment_depth( $comment_id ), 0 ) : '',
				'summary'   => $rating_updated ? $this->get_rating_summary_payload( (int) $existing->post_id ) : null,
			)
		);
	}

	/**
	 * Update the current user's rating for a post.
	 *
	 * @return void
	 */
	public function handle_update_rating() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Login required.', 'kosher-comments' ),
				),
				403
			);
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;

		if ( ! get_post( $post_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid post.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ratings must be between 1 and 5.', 'kosher-comments' ),
				),
				400
			);
		}

		$comment_id = $this->get_user_post_rating_comment_id( $post_id, $user_id );

		if ( ! $comment_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'No rating was found to update.', 'kosher-comments' ),
				),
				404
			);
		}

		update_comment_meta( $comment_id, self::META_RATING, $rating );

		$this->analytics->track(
			array(
				'post_id'    => $post_id,
				'comment_id' => $comment_id,
				'action'     => 'rating_edited',
				'meta'       => array(
					'rating' => $rating,
				),
			)
		);

		$comment = $this->get_comment_for_render( $comment_id );

		wp_send_json_success(
			array(
				'message'   => __( 'Rating updated.', 'kosher-comments' ),
				'commentId' => $comment_id,
				'rating'    => $rating,
				'html'      => $comment ? Kosher_Comments_Public::render_single_comment( $comment, $this->get_comment_depth( $comment_id ), 0 ) : '',
				'summary'   => $this->get_rating_summary_payload( $post_id ),
			)
		);
	}

	/**
	 * Delete a comment from the frontend for admins/editors.
	 *
	 * @return void
	 */
	public function handle_delete_comment() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		if ( ! $this->current_user_can_frontend_moderate() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to delete comments.', 'kosher-comments' ),
				),
				403
			);
		}

		$comment_id = absint( $_POST['comment_id'] ?? 0 );
		$deleted    = $this->delete_comment( $comment_id );

		if ( ! $deleted ) {
			wp_send_json_error(
				array(
					'message' => __( 'The comment could not be deleted.', 'kosher-comments' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Comment deleted.', 'kosher-comments' ),
				'commentId' => $comment_id,
			)
		);
	}

	/**
	 * Report a comment or image.
	 *
	 * @return void
	 */
	public function handle_report_item() {
		check_ajax_referer( 'kosher_comments_public_nonce', 'nonce' );

		$user_id     = get_current_user_id();
		$report_type = sanitize_key( (string) ( $_POST['report_type'] ?? '' ) );
		$comment_id  = absint( $_POST['comment_id'] ?? 0 );
		$image_id    = absint( $_POST['image_id'] ?? 0 );
		$subject     = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$reason      = mb_substr( $reason, 0, 140 );
		$subject     = mb_substr( $subject, 0, 60 );

		if ( ! $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Login required.', 'kosher-comments' ),
				),
				403
			);
		}

		if ( '' === $subject ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please add a subject for this report.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( '' === $reason ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please tell us why you are reporting this item.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( 'image' === $report_type ) {
			$image = $this->get_image_row( $image_id );

			if ( ! $image ) {
				wp_send_json_error(
					array(
						'message' => __( 'Image not found.', 'kosher-comments' ),
					),
					404
				);
			}

			$comment_id = (int) $image->comment_id;
		} elseif ( 'comment' === $report_type ) {
			$comment = $this->get_comment_row( $comment_id );

			if ( ! $comment ) {
				wp_send_json_error(
					array(
						'message' => __( 'Comment not found.', 'kosher-comments' ),
					),
					404
				);
			}

			$image_id = 0;
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid report type.', 'kosher-comments' ),
				),
				400
			);
		}

		if ( $this->has_open_report( $user_id, $comment_id, $image_id, $report_type ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You already reported this item.', 'kosher-comments' ),
				),
				409
			);
		}

		global $wpdb;

		$stored_reason = sprintf(
			/* translators: 1: report subject, 2: report reason */
			__( "Subject: %1\$s\nComment: %2\$s", 'kosher-comments' ),
			$subject,
			$reason
		);

		$wpdb->insert(
			kosher_comments_get_table_name( 'reports' ),
			array(
				'reporter_user_id' => $user_id,
				'comment_id'       => $comment_id ? $comment_id : null,
				'image_id'         => $image_id ? $image_id : null,
				'report_type'      => $report_type,
				'reason'           => $stored_reason,
				'status'           => 'open',
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$comment = $this->get_comment_row( $comment_id );

		$this->analytics->track(
			array(
				'post_id'    => $comment ? (int) $comment->post_id : 0,
				'comment_id' => $comment_id,
				'action'     => 'item_reported',
				'meta'       => array(
					'report_type' => $report_type,
					'image_id'    => $image_id,
				),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Thanks. Your report was sent to moderation.', 'kosher-comments' ),
			)
		);
	}

	/**
	 * Return comments for a page.
	 *
	 * @param int $post_id Post ID.
	 * @param int $page Page number.
	 * @param int $target_comment_id Optional target comment ID.
	 * @return array<string, mixed>
	 */
	public function get_comments_page( $post_id, $page = 1, $target_comment_id = 0 ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$page    = max( 1, absint( $page ) );
		$limit   = $this->get_comments_per_page();
		$offset  = ( $page - 1 ) * $limit;

		$top_comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
					AND comment_parent = 0
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
				ORDER BY comment_date_gmt DESC, comment_ID DESC
				LIMIT %d OFFSET %d",
				$post_id,
				$limit,
				$offset
			)
		);

		$total_top_level = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
					AND comment_parent = 0
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')",
				$post_id
			)
		);

		$threads = $this->hydrate_threads( $post_id, $top_comments );

		return array(
			'html'        => Kosher_Comments_Public::render_comments_markup( $threads, $target_comment_id ),
			'page'        => $page,
			'hasMore'     => ( $offset + count( $top_comments ) ) < $total_top_level,
			'foundTarget' => $target_comment_id ? $this->threads_contain_comment( $threads, $target_comment_id ) : false,
			'total'       => $total_top_level,
		);
	}

	/**
	 * Build the frontend overview data.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_frontend_overview( $post_id ) {
		global $wpdb;

		$post_id        = absint( $post_id );
		$summary        = $this->analytics->get_post_summary( $post_id );
		$commentmeta    = $wpdb->commentmeta;
		$images_table   = kosher_comments_get_table_name( 'comment_images' );
		$votes_table    = kosher_comments_get_table_name( 'comment_votes' );
		$top_images     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					images.id AS image_id,
					images.attachment_id,
					images.image_url,
					comments.comment_ID AS comment_id,
					comments.comment_content,
					comments.comment_author,
					comments.comment_author_email,
					comments.user_id,
					rating_meta.meta_value AS rating
				FROM {$images_table} images
				INNER JOIN {$wpdb->comments} comments ON comments.comment_ID = images.comment_id
				LEFT JOIN {$commentmeta} rating_meta
					ON rating_meta.comment_id = comments.comment_ID
					AND rating_meta.meta_key = %s
				WHERE comments.comment_post_ID = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
				ORDER BY comments.comment_date_gmt DESC, comments.comment_ID DESC, images.sort_order ASC
				LIMIT 12",
				self::META_RATING,
				$post_id
			)
		);
		$featured_ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comments.comment_ID
				FROM {$wpdb->comments} comments
				LEFT JOIN {$commentmeta} rating_meta
					ON rating_meta.comment_id = comments.comment_ID
					AND rating_meta.meta_key = %s
				LEFT JOIN (
					SELECT comment_id, SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) AS like_count
					FROM {$votes_table}
					GROUP BY comment_id
				) votes ON votes.comment_id = comments.comment_ID
				WHERE comments.comment_post_ID = %d
					AND comments.comment_parent = 0
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment')
				ORDER BY CAST(COALESCE(rating_meta.meta_value, 0) AS UNSIGNED) DESC, COALESCE(votes.like_count, 0) DESC, comments.comment_date_gmt DESC, comments.comment_ID DESC
				LIMIT 3",
				self::META_RATING,
				$post_id
			)
		);
		$featured_rows  = array();
		$country_label  = ! empty( $summary['location_distribution'][0]['country'] ) ? $summary['location_distribution'][0]['country'] : __( 'the community', 'kosher-comments' );
		$images_payload = array();

		if ( ! empty( $top_images ) ) {
			$user_ids = array_map( 'absint', wp_list_pluck( $top_images, 'user_id' ) );
			$users    = $this->get_users_map( $user_ids );

			foreach ( $top_images as $row ) {
				$resolved_image = $this->resolve_comment_image_urls( (int) $row->attachment_id, (string) $row->image_url );
				$user           = $users[ (int) $row->user_id ] ?? null;
				$author_name    = $user ? $user->display_name : sanitize_text_field( (string) $row->comment_author );
				$author_email   = sanitize_email( (string) $row->comment_author_email );

				if ( '' === $author_name ) {
					$author_name = __( 'Anonymous', 'kosher-comments' );
				}

				$images_payload[] = array(
					'image_id'    => (int) $row->image_id,
					'comment_id'  => (int) $row->comment_id,
					'url'         => esc_url( $resolved_image['url'] ),
					'thumb'       => esc_url( $resolved_image['thumb'] ),
					'author_name' => $author_name,
					'avatar_url'  => $this->get_avatar_request_url( $user ? (int) $user->ID : ( $author_email ? $author_email : 0 ) ),
					'excerpt'     => wp_trim_words( wp_strip_all_tags( (string) $row->comment_content ), 28, '...' ),
					'rating'      => '' !== (string) $row->rating ? absint( $row->rating ) : 0,
					'share_url'   => add_query_arg( 'comment_id', (int) $row->comment_id, get_permalink( $post_id ) ),
				);
			}
		}

		if ( ! empty( $featured_ids ) ) {
			$featured_id_sql = implode( ',', array_map( 'absint', $featured_ids ) );
			$featured_rows   = $wpdb->get_results(
				"SELECT *
				FROM {$wpdb->comments}
				WHERE comment_ID IN ({$featured_id_sql})
				ORDER BY FIELD(comment_ID, {$featured_id_sql})"
			);
		}

		return array(
			'summary'          => $summary,
			'rating_bars'      => $this->build_rating_bars( $summary['rating_distribution'], $summary['ratings_count'] ),
			'image_reviews'    => $images_payload,
			'featured_reviews' => $this->hydrate_threads( $post_id, $featured_rows ),
			'country_label'    => $country_label,
			'user_rating'      => get_current_user_id() ? $this->get_user_post_rating( $post_id, get_current_user_id() ) : null,
		);
	}

	/**
	 * Get moderation reports.
	 *
	 * @return array<int, object>
	 */
	public function get_reports() {
		global $wpdb;

		$reports_table = kosher_comments_get_table_name( 'reports' );
		$images_table  = kosher_comments_get_table_name( 'comment_images' );

		return $wpdb->get_results(
			"SELECT reports.*,
				reporter.display_name AS reporter_name,
				reporter.user_email AS reporter_email,
				comments.comment_content AS comment_content,
				comments.comment_post_ID AS post_id,
				comments.comment_approved AS comment_status,
				images.image_url AS image_url
			FROM {$reports_table} reports
			LEFT JOIN {$wpdb->users} reporter ON reporter.ID = reports.reporter_user_id
			LEFT JOIN {$wpdb->comments} comments ON comments.comment_ID = reports.comment_id
			LEFT JOIN {$images_table} images ON images.id = reports.image_id
			WHERE reports.status = 'open'
			ORDER BY reports.created_at DESC"
		);
	}

	/**
	 * Update a report status.
	 *
	 * @param int    $report_id Report ID.
	 * @param string $status New status.
	 * @return void
	 */
	public function update_report_status( $report_id, $status ) {
		global $wpdb;

		$wpdb->update(
			kosher_comments_get_table_name( 'reports' ),
			array(
				'status'     => sanitize_key( $status ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $report_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a native WordPress comment and related plugin data.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public function delete_comment( $comment_id ) {
		global $wpdb;

		$comment_id = absint( $comment_id );
		$comment    = $this->get_comment_row( $comment_id, false );

		if ( ! $comment ) {
			return false;
		}

		$thread_ids = $this->get_comment_thread_ids( $comment_id );

		if ( ! empty( $thread_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $thread_ids ), '%d' ) );
			$image_rows   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, attachment_id FROM " . kosher_comments_get_table_name( 'comment_images' ) . " WHERE comment_id IN ({$placeholders})",
					$thread_ids
				)
			);

			foreach ( $image_rows as $image_row ) {
				if ( ! empty( $image_row->attachment_id ) ) {
					wp_delete_attachment( (int) $image_row->attachment_id, true );
				}
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM " . kosher_comments_get_table_name( 'comment_images' ) . " WHERE comment_id IN ({$placeholders})",
					$thread_ids
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM " . kosher_comments_get_table_name( 'comment_votes' ) . " WHERE comment_id IN ({$placeholders})",
					$thread_ids
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE " . kosher_comments_get_table_name( 'reports' ) . " SET status = 'resolved', updated_at = %s WHERE comment_id IN ({$placeholders}) AND status = 'open'",
					array_merge( array( current_time( 'mysql' ) ), $thread_ids )
				)
			);
		}

		$deleted = wp_delete_comment( $comment_id, true );

		if ( $deleted ) {
			$this->analytics->track(
				array(
					'post_id'    => (int) $comment->post_id,
					'comment_id' => $comment_id,
					'action'     => 'comment_deleted',
				)
			);
		}

		return (bool) $deleted;
	}

	/**
	 * Delete an image row and attachment.
	 *
	 * @param int $image_id Image ID.
	 * @return bool
	 */
	public function delete_image( $image_id ) {
		global $wpdb;

		$image_id = absint( $image_id );
		$image    = $this->get_image_row( $image_id );

		if ( ! $image ) {
			return false;
		}

		if ( ! empty( $image->attachment_id ) ) {
			wp_delete_attachment( (int) $image->attachment_id, true );
		}

		$deleted = $wpdb->delete(
			kosher_comments_get_table_name( 'comment_images' ),
			array( 'id' => $image_id ),
			array( '%d' )
		);

		$wpdb->update(
			kosher_comments_get_table_name( 'reports' ),
			array(
				'status'     => 'resolved',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'image_id' => $image_id,
				'status'   => 'open',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $deleted;
	}

	/**
	 * Get a hydrated single comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return object|null
	 */
	public function get_comment_for_render( $comment_id ) {
		$comment = get_comment( absint( $comment_id ) );

		if ( ! $comment || 'comment' !== $comment->comment_type && '' !== $comment->comment_type ) {
			return null;
		}

		$threads = $this->hydrate_threads( (int) $comment->comment_post_ID, array( $comment ) );

		return ! empty( $threads ) ? $threads[0] : null;
	}

	/**
	 * Get the page containing the target comment.
	 *
	 * @param int $post_id Post ID.
	 * @param int $comment_id Comment ID.
	 * @return int
	 */
	public function get_target_page( $post_id, $comment_id ) {
		global $wpdb;

		$post_id    = absint( $post_id );
		$comment_id = absint( $comment_id );
		$root       = $this->get_root_comment( $comment_id );

		if ( ! $root || (int) $root->post_id !== $post_id ) {
			return 1;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
					AND comment_parent = 0
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
					AND (comment_date_gmt > %s OR (comment_date_gmt = %s AND comment_ID > %d))",
				$post_id,
				get_gmt_from_date( $root->created_at ),
				get_gmt_from_date( $root->created_at ),
				$root->id
			)
		);

		return (int) floor( $count / $this->get_comments_per_page() ) + 1;
	}

	/**
	 * Determine whether an approved comment belongs to a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public function comment_belongs_to_post( $post_id, $comment_id ) {
		$comment = $this->get_comment_row( $comment_id );

		return $comment && (int) $comment->post_id === absint( $post_id );
	}

	/**
	 * Fetch a raw comment row.
	 *
	 * @param int  $comment_id Comment ID.
	 * @param bool $approved_only Whether to restrict to approved comments.
	 * @return object|null
	 */
	public function get_comment_row( $comment_id, $approved_only = true ) {
		$comment_id = absint( $comment_id );

		if ( ! $comment_id ) {
			return null;
		}

		$raw = get_comment( $comment_id );

		if ( ! $raw ) {
			return null;
		}

		if ( ! in_array( (string) $raw->comment_type, array( '', 'comment' ), true ) ) {
			return null;
		}

		if ( $approved_only && '1' !== (string) $raw->comment_approved ) {
			return null;
		}

		$comments = $this->hydrate_threads( (int) $raw->comment_post_ID, array( $raw ) );

		return ! empty( $comments ) ? $comments[0] : null;
	}

	/**
	 * Fetch an image row.
	 *
	 * @param int $image_id Image ID.
	 * @return object|null
	 */
	public function get_image_row( $image_id ) {
		global $wpdb;

		$image_id = absint( $image_id );

		if ( ! $image_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . kosher_comments_get_table_name( 'comment_images' ) . ' WHERE id = %d',
				$image_id
			)
		);
	}

	/**
	 * Determine whether the current user can moderate from the frontend.
	 *
	 * @return bool
	 */
	public function current_user_can_frontend_moderate() {
		return current_user_can( 'manage_options' ) || current_user_can( 'moderate_comments' ) || current_user_can( 'edit_others_posts' );
	}

	/**
	 * Get top-level comments per page.
	 *
	 * @return int
	 */
	protected function get_comments_per_page() {
		return max( 1, absint( kosher_comments_get_setting( 'comments_per_page', 5 ) ) );
	}

	/**
	 * Build rating bar percentages.
	 *
	 * @param array<int, int> $distribution Distribution.
	 * @param int             $ratings_count Count.
	 * @return array<int, array<string, mixed>>
	 */
	protected function build_rating_bars( $distribution, $ratings_count ) {
		$bars = array();

		for ( $rating = 5; $rating >= 1; $rating-- ) {
			$count          = isset( $distribution[ $rating ] ) ? (int) $distribution[ $rating ] : 0;
			$bars[ $rating ] = array(
				'count'   => $count,
				'percent' => $ratings_count ? round( ( $count / $ratings_count ) * 100 ) : 0,
			);
		}

		return $bars;
	}

	/**
	 * Build a frontend rating summary payload after rating mutations.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	protected function get_rating_summary_payload( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! function_exists( 'kosher_comments_get_rating_summary' ) ) {
			return array();
		}

		$summary = kosher_comments_get_rating_summary( $post_id );
		$summary = function_exists( 'kosher_comments_normalize_rating_summary' ) ? kosher_comments_normalize_rating_summary( $summary ) : $summary;

		return array(
			'averageRating' => isset( $summary['average_rating'] ) ? (float) $summary['average_rating'] : 0.0,
			'ratingsCount'  => isset( $summary['ratings_count'] ) ? (int) $summary['ratings_count'] : 0,
			'ratingBars'    => $this->build_rating_bars( $summary['rating_distribution'] ?? array(), (int) ( $summary['ratings_count'] ?? 0 ) ),
		);
	}

	/**
	 * Get the current user's rating for a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return int|null
	 */
	protected function get_user_post_rating( $post_id, $user_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$user_id = absint( $user_id );

		if ( ! $post_id || ! $user_id ) {
			return null;
		}

		$rating = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta.meta_value
				FROM {$wpdb->comments} comments
				INNER JOIN {$wpdb->commentmeta} meta
					ON meta.comment_id = comments.comment_ID
					AND meta.meta_key = %s
				WHERE comments.comment_post_ID = %d
					AND comments.user_id = %d
					AND comments.comment_approved = '1'
					AND comments.comment_type IN ('', 'comment', %s)
				ORDER BY comments.comment_date_gmt ASC, comments.comment_ID ASC
				LIMIT 1",
				self::META_RATING,
				$post_id,
				$user_id,
				self::COMMENT_TYPE_RATING
			)
		);

		if ( null === $rating || '' === $rating ) {
			return null;
		}

		return absint( $rating );
	}

	/**
	 * Get the current user's rating comment ID for a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return int
	 */
	protected function get_user_post_rating_comment_id( $post_id, $user_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$user_id = absint( $user_id );

		if ( ! $post_id || ! $user_id ) {
			return 0;
		}

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT comments.comment_ID
					FROM {$wpdb->comments} comments
					INNER JOIN {$wpdb->commentmeta} meta
						ON meta.comment_id = comments.comment_ID
						AND meta.meta_key = %s
					WHERE comments.comment_post_ID = %d
						AND comments.user_id = %d
						AND comments.comment_approved = '1'
						AND comments.comment_type IN ('', 'comment', %s)
					ORDER BY comments.comment_date_gmt ASC, comments.comment_ID ASC
					LIMIT 1",
					self::META_RATING,
					$post_id,
					$user_id,
					self::COMMENT_TYPE_RATING
				)
			)
		);
	}

	/**
	 * Check whether a user already rated a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function user_has_post_rating( $post_id, $user_id ) {
		return null !== $this->get_user_post_rating( $post_id, $user_id );
	}

	/**
	 * Hydrate threads with nested replies, user data, images, and votes.
	 *
	 * @param int               $post_id Post ID.
	 * @param array<int, object> $top_comments Root comments.
	 * @return array<int, object>
	 */
	protected function hydrate_threads( $post_id, $top_comments ) {
		if ( empty( $top_comments ) ) {
			return array();
		}

		$all_comments = $top_comments;
		$parent_ids   = array_map( array( $this, 'get_raw_comment_id' ), $top_comments );

		while ( ! empty( $parent_ids ) ) {
			$children = $this->get_children_for_parents( $post_id, $parent_ids );

			if ( empty( $children ) ) {
				break;
			}

			$all_comments = array_merge( $all_comments, $children );
			$parent_ids   = array_map( array( $this, 'get_raw_comment_id' ), $children );
		}

		$comment_ids   = array_filter( array_map( array( $this, 'get_raw_comment_id' ), $all_comments ) );
		$user_ids      = array_filter( array_map( array( $this, 'get_raw_user_id' ), $all_comments ) );
		$images        = $this->get_images_map( $comment_ids );
		$users         = $this->get_users_map( $user_ids );
		$votes         = $this->get_votes_map( $comment_ids, get_current_user_id() );
		$vote_counts   = $this->get_vote_counts_map( $comment_ids );
		$meta_bundle   = $this->get_comment_meta_bundle( $comment_ids );
		$map           = array();
		$top_ids       = array();

		foreach ( $top_comments as $top_comment ) {
			$top_ids[] = $this->get_raw_comment_id( $top_comment );
		}

		foreach ( $all_comments as $raw_comment ) {
			$comment                = $this->normalize_comment_row( $raw_comment, $meta_bundle, $images, $users, $votes, $vote_counts );
			$map[ $comment->id ]    = $comment;
		}

		foreach ( $all_comments as $raw_comment ) {
			$comment_id = $this->get_raw_comment_id( $raw_comment );

			if ( ! isset( $map[ $comment_id ] ) ) {
				continue;
			}

			$comment = $map[ $comment_id ];

			if ( ! empty( $comment->parent_id ) && isset( $map[ $comment->parent_id ] ) ) {
				$comment->reply_to_name               = $map[ $comment->parent_id ]->author_name;
				$map[ $comment->parent_id ]->replies[] = $comment;
			}
		}

		foreach ( $map as $comment_id => $comment ) {
			$map[ $comment_id ]->reply_count = ! empty( $comment->replies ) ? count( $comment->replies ) : 0;
		}

		$threads = array();

		foreach ( $top_ids as $top_id ) {
			if ( isset( $map[ $top_id ] ) ) {
				$threads[] = $map[ $top_id ];
			}
		}

		return $threads;
	}

	/**
	 * Fetch child comments for parents.
	 *
	 * @param int        $post_id Post ID.
	 * @param array<int> $parent_ids Parent IDs.
	 * @return array<int, object>
	 */
	protected function get_children_for_parents( $post_id, $parent_ids ) {
		global $wpdb;

		$parent_ids = array_values( array_filter( array_map( 'absint', $parent_ids ) ) );

		if ( empty( $parent_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
		$params       = array_merge( array( absint( $post_id ) ), $parent_ids );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
					AND comment_approved = '1'
					AND comment_type IN ('', 'comment')
					AND comment_parent IN ({$placeholders})
				ORDER BY comment_date_gmt ASC, comment_ID ASC",
				$params
			)
		);
	}

	/**
	 * Fetch image rows grouped by comment.
	 *
	 * @param array<int> $comment_ids Comment IDs.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	protected function get_images_map( $comment_ids ) {
		global $wpdb;

		$comment_ids = array_values( array_filter( array_map( 'absint', $comment_ids ) ) );

		if ( empty( $comment_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, comment_id, attachment_id, image_url
				FROM " . kosher_comments_get_table_name( 'comment_images' ) . "
				WHERE comment_id IN ({$placeholders})
				ORDER BY comment_id ASC, sort_order ASC, id ASC",
				$comment_ids
			),
			ARRAY_A
		);
		$images       = array();

		foreach ( $rows as $row ) {
			$comment_id = (int) $row['comment_id'];
			$resolved   = $this->resolve_comment_image_urls( (int) $row['attachment_id'], (string) $row['image_url'] );

			if ( ! isset( $images[ $comment_id ] ) ) {
				$images[ $comment_id ] = array();
			}

			$images[ $comment_id ][] = array(
				'image_id'      => (int) $row['id'],
				'attachment_id' => (int) $row['attachment_id'],
				'url'           => esc_url( $resolved['url'] ),
				'thumb'         => esc_url( $resolved['thumb'] ),
			);
		}

		return $images;
	}

	/**
	 * Fetch user map.
	 *
	 * @param array<int> $user_ids User IDs.
	 * @return array<int, WP_User>
	 */
	protected function get_users_map( $user_ids ) {
		$user_ids = array_values( array_filter( array_map( 'absint', $user_ids ) ) );

		if ( empty( $user_ids ) ) {
			return array();
		}

		$users = get_users(
			array(
				'include' => $user_ids,
				'fields'  => array( 'ID', 'display_name' ),
			)
		);
		$map   = array();

		foreach ( $users as $user ) {
			$map[ $user->ID ] = $user;
		}

		return $map;
	}

	/**
	 * Fetch vote map for the current user.
	 *
	 * @param array<int> $comment_ids Comment IDs.
	 * @param int        $user_id User ID.
	 * @return array<int, string>
	 */
	protected function get_votes_map( $comment_ids, $user_id ) {
		global $wpdb;

		$comment_ids = array_values( array_filter( array_map( 'absint', $comment_ids ) ) );
		$user_id     = absint( $user_id );

		if ( empty( $comment_ids ) || ! $user_id ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) );
		$params       = array_merge( array( $user_id ), $comment_ids );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_id, vote_type
				FROM " . kosher_comments_get_table_name( 'comment_votes' ) . "
				WHERE user_id = %d AND comment_id IN ({$placeholders})",
				$params
			),
			ARRAY_A
		);
		$votes        = array();

		foreach ( $rows as $row ) {
			$votes[ (int) $row['comment_id'] ] = sanitize_key( $row['vote_type'] );
		}

		return $votes;
	}

	/**
	 * Fetch aggregate vote counts by comment.
	 *
	 * @param array<int> $comment_ids Comment IDs.
	 * @return array<int, array<string, int>>
	 */
	protected function get_vote_counts_map( $comment_ids ) {
		global $wpdb;

		$comment_ids = array_values( array_filter( array_map( 'absint', $comment_ids ) ) );

		if ( empty( $comment_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					comment_id,
					SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) AS like_count,
					SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislike_count
				FROM " . kosher_comments_get_table_name( 'comment_votes' ) . "
				WHERE comment_id IN ({$placeholders})
				GROUP BY comment_id",
				$comment_ids
			),
			ARRAY_A
		);
		$map          = array();

		foreach ( $rows as $row ) {
			$map[ (int) $row['comment_id'] ] = array(
				'like_count'    => (int) $row['like_count'],
				'dislike_count' => (int) $row['dislike_count'],
			);
		}

		return $map;
	}

	/**
	 * Fetch plugin comment meta in one query.
	 *
	 * @param array<int> $comment_ids Comment IDs.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_comment_meta_bundle( $comment_ids ) {
		global $wpdb;

		$comment_ids = array_values( array_filter( array_map( 'absint', $comment_ids ) ) );

		if ( empty( $comment_ids ) ) {
			return array();
		}

		$meta_keys     = array(
			self::META_RATING,
			self::META_IS_QUESTION,
			self::META_NOTIFY_REPLIES,
			self::META_LOCATION_COUNTRY,
			self::META_MODERATION_REASON,
		);
		$key_sql       = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$comment_sql   = implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) );
		$params        = array_merge( $meta_keys, $comment_ids );
		$rows          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_id, meta_key, meta_value
				FROM {$wpdb->commentmeta}
				WHERE meta_key IN ({$key_sql})
					AND comment_id IN ({$comment_sql})",
				$params
			),
			ARRAY_A
		);
		$bundle        = array();

		foreach ( $comment_ids as $comment_id ) {
			$bundle[ $comment_id ] = array(
				'rating'            => null,
				'is_question'       => 0,
				'notify_replies'    => 0,
				'location_country'  => '',
				'moderation_reason' => '',
			);
		}

		foreach ( $rows as $row ) {
			$comment_id = (int) $row['comment_id'];
			$key        = (string) $row['meta_key'];
			$value      = $row['meta_value'];

			switch ( $key ) {
				case self::META_RATING:
					$bundle[ $comment_id ]['rating'] = '' !== (string) $value ? absint( $value ) : null;
					break;
				case self::META_IS_QUESTION:
					$bundle[ $comment_id ]['is_question'] = ! empty( $value ) ? 1 : 0;
					break;
				case self::META_NOTIFY_REPLIES:
					$bundle[ $comment_id ]['notify_replies'] = ! empty( $value ) ? 1 : 0;
					break;
				case self::META_LOCATION_COUNTRY:
					$bundle[ $comment_id ]['location_country'] = sanitize_text_field( (string) $value );
					break;
				case self::META_MODERATION_REASON:
					$bundle[ $comment_id ]['moderation_reason'] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $bundle;
	}

	/**
	 * Normalize a WordPress comment into the plugin render shape.
	 *
	 * @param object                         $raw_comment Raw WP comment row.
	 * @param array<int, array<string,mixed>> $meta_bundle Meta bundle.
	 * @param array<int, array<int, array<string, mixed>>> $images Images by comment.
	 * @param array<int, WP_User>            $users Users map.
	 * @param array<int, string>             $votes Current user votes.
	 * @param array<int, array<string, int>> $vote_counts Vote counts.
	 * @return object
	 */
	protected function normalize_comment_row( $raw_comment, $meta_bundle, $images, $users, $votes, $vote_counts ) {
		$comment_id    = $this->get_raw_comment_id( $raw_comment );
		$user_id       = $this->get_raw_user_id( $raw_comment );
		$meta          = $meta_bundle[ $comment_id ] ?? array();
		$comment_user  = $users[ $user_id ] ?? null;
		$author_name   = $comment_user ? $comment_user->display_name : sanitize_text_field( (string) $raw_comment->comment_author );
		$author_email  = sanitize_email( (string) $raw_comment->comment_author_email );
		$avatar_data   = $this->get_author_avatar_data( $user_id, $author_email, $author_name );
		$like_counts   = $vote_counts[ $comment_id ] ?? array(
			'like_count'    => 0,
			'dislike_count' => 0,
		);

		if ( '' === $author_name ) {
			$author_name = __( 'Anonymous', 'kosher-comments' );
		}

		return (object) array(
			'id'                => $comment_id,
			'wp_comment_id'     => $comment_id,
			'post_id'           => (int) $raw_comment->comment_post_ID,
			'user_id'           => $user_id,
			'author_name'       => $author_name,
			'author_email'      => $author_email,
			'is_staff'          => $this->is_staff_comment_user( $comment_user ),
			'parent_id'         => (int) $raw_comment->comment_parent,
			'content'           => (string) $raw_comment->comment_content,
			'rating'            => isset( $meta['rating'] ) ? $meta['rating'] : null,
			'is_question'       => ! empty( $meta['is_question'] ) ? 1 : 0,
			'notify_replies'    => ! empty( $meta['notify_replies'] ) ? 1 : 0,
			'like_count'        => (int) $like_counts['like_count'],
			'dislike_count'     => (int) $like_counts['dislike_count'],
			'reply_count'       => 0,
			'image_count'       => ! empty( $images[ $comment_id ] ) ? count( $images[ $comment_id ] ) : 0,
			'status'            => '1' === (string) $raw_comment->comment_approved ? 'approved' : sanitize_key( (string) $raw_comment->comment_approved ),
			'moderation_reason' => sanitize_text_field( (string) ( $meta['moderation_reason'] ?? '' ) ),
			'location_country'  => sanitize_text_field( (string) ( $meta['location_country'] ?? '' ) ),
			'user_ip'           => sanitize_text_field( (string) $raw_comment->comment_author_IP ),
			'created_at'        => ! empty( $raw_comment->comment_date ) ? (string) $raw_comment->comment_date : current_time( 'mysql' ),
			'updated_at'        => ! empty( $raw_comment->comment_date ) ? (string) $raw_comment->comment_date : current_time( 'mysql' ),
			'images'            => $images[ $comment_id ] ?? array(),
			'replies'           => array(),
			'avatar_url'        => $avatar_data['url'],
			'has_avatar'        => $avatar_data['has_avatar'],
			'avatar_initials'   => $avatar_data['initials'],
			'share_url'         => add_query_arg( 'comment_id', $comment_id, get_permalink( (int) $raw_comment->comment_post_ID ) ),
			'user_vote'         => $votes[ $comment_id ] ?? '',
			'can_moderate'      => $this->current_user_can_frontend_moderate(),
			'can_edit'          => $this->current_user_can_frontend_moderate() || ( get_current_user_id() && (int) get_current_user_id() === (int) $user_id && empty( $raw_comment->comment_parent ) && ! empty( $meta['rating'] ) ),
			'excerpt'           => wp_trim_words( wp_strip_all_tags( (string) $raw_comment->comment_content ), 28, '...' ),
		);
	}

	/**
	 * Resolve avatar information for a comment author.
	 *
	 * @param int    $user_id User ID.
	 * @param string $author_email Author email.
	 * @param string $author_name Author name.
	 * @return array<string, mixed>
	 */
	protected function get_author_avatar_data( $user_id, $author_email, $author_name ) {
		$avatar_target = $user_id ? $user_id : ( $author_email ? $author_email : 0 );

		return array(
			'url'        => $this->get_avatar_request_url( $avatar_target ),
			'has_avatar' => ! empty( $avatar_target ),
			'initials'   => $this->get_author_initials( $author_name ),
		);
	}

	/**
	 * Build an avatar URL that 404s when no real avatar exists.
	 *
	 * @param int|string $avatar_target Avatar lookup target.
	 * @return string
	 */
	protected function get_avatar_request_url( $avatar_target ) {
		return (string) get_avatar_url(
			$avatar_target,
			array(
				'size'    => 64,
				'default' => '404',
			)
		);
	}

	/**
	 * Build a two-letter fallback avatar label from the author name.
	 *
	 * @param string $author_name Author name.
	 * @return string
	 */
	protected function get_author_initials( $author_name ) {
		$author_name = trim( wp_strip_all_tags( (string) $author_name ) );

		if ( '' === $author_name ) {
			return 'AN';
		}

		$letters_only = preg_replace( '/[^\p{L}\p{N}]+/u', '', $author_name );

		if ( is_string( $letters_only ) && '' !== $letters_only ) {
			$author_name = $letters_only;
		}

		$initials = function_exists( 'wp_html_excerpt' ) ? wp_html_excerpt( $author_name, 2, '' ) : substr( $author_name, 0, 2 );

		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $initials, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		}

		return strtoupper( $initials );
	}

	/**
	 * Persist comment image uploads.
	 *
	 * @param int $comment_id Comment ID.
	 * @param int $post_id Post ID.
	 * @return array<int, string>
	 */
	protected function handle_image_uploads( $comment_id, $post_id ) {
		if ( empty( $_FILES['images']['name'] ) || ! is_array( $_FILES['images']['name'] ) ) {
			return array();
		}

		$max_images = max( 0, absint( kosher_comments_get_setting( 'max_images_per_comment', 5 ) ) );

		if ( 0 === $max_images ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		global $wpdb;

		$uploaded_urls = array();
		$total_files   = min( count( $_FILES['images']['name'] ), $max_images );
		$max_bytes     = max( 1, absint( kosher_comments_get_setting( 'max_image_size_mb', 5 ) ) ) * MB_IN_BYTES;

		for ( $index = 0; $index < $total_files; $index++ ) {
			if ( empty( $_FILES['images']['name'][ $index ] ) || UPLOAD_ERR_OK !== (int) $_FILES['images']['error'][ $index ] ) {
				continue;
			}

			if ( (int) $_FILES['images']['size'][ $index ] > $max_bytes ) {
				continue;
			}

			$file = array(
				'name'     => sanitize_file_name( wp_unslash( $_FILES['images']['name'][ $index ] ) ),
				'type'     => sanitize_mime_type( wp_unslash( $_FILES['images']['type'][ $index ] ) ),
				'tmp_name' => wp_unslash( $_FILES['images']['tmp_name'][ $index ] ),
				'error'    => (int) $_FILES['images']['error'][ $index ],
				'size'     => (int) $_FILES['images']['size'][ $index ],
			);

			$upload = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'          => 'image/png',
						'gif'          => 'image/gif',
						'webp'         => 'image/webp',
					),
				)
			);

			if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['url'] ) ) {
				continue;
			}

			$attachment = array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
				wp_update_attachment_metadata( $attachment_id, $metadata );
			} else {
				$attachment_id = 0;
			}

			$stored_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

			if ( empty( $stored_url ) ) {
				$stored_url = $upload['url'];
			}

			$wpdb->insert(
				kosher_comments_get_table_name( 'comment_images' ),
				array(
					'comment_id'    => $comment_id,
					'attachment_id' => $attachment_id,
					'image_url'     => esc_url_raw( $stored_url ),
					'sort_order'    => $index,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s' )
			);

			$uploaded_urls[] = esc_url_raw( $stored_url );
		}

		return $uploaded_urls;
	}

	/**
	 * Resolve full and thumbnail URLs for a comment image.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $stored_url Stored fallback URL.
	 * @return array<string, string>
	 */
	protected function resolve_comment_image_urls( $attachment_id, $stored_url ) {
		$attachment_id = absint( $attachment_id );
		$stored_url    = esc_url_raw( $stored_url );
		$full_url      = '';
		$thumb_url     = '';

		if ( $attachment_id ) {
			$full_url  = (string) wp_get_attachment_url( $attachment_id );
			$thumb_url = (string) wp_get_attachment_image_url( $attachment_id, 'medium' );

			if ( empty( $thumb_url ) ) {
				$thumb_url = (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			}
		}

		if ( empty( $full_url ) ) {
			$full_url = $stored_url;
		}

		if ( empty( $thumb_url ) ) {
			$thumb_url = $full_url ? $full_url : $stored_url;
		}

		return array(
			'url'   => $full_url ? $full_url : '',
			'thumb' => $thumb_url ? $thumb_url : '',
		);
	}

	/**
	 * Notify the assigned chef about a question.
	 *
	 * @param WP_Post $post Post.
	 * @param int     $comment_id Comment ID.
	 * @param string  $comment_text Content.
	 * @return void
	 */
	protected function maybe_notify_post_author( WP_Post $post, $comment_id, $comment_text ) {
		$recipient = $this->get_question_notification_recipient( $post );

		if ( empty( $recipient['email'] ) ) {
			return;
		}

		$comment_url  = add_query_arg( 'comment_id', $comment_id, get_permalink( $post ) ) . '#kosher-comment-' . absint( $comment_id );
		$recipe_image = get_the_post_thumbnail_url( $post, 'medium_large' );
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$logo_url     = $this->get_notification_logo_url();
		$footer_email = sanitize_email( kosher_comments_get_setting( 'notification_email', get_option( 'admin_email' ) ) );

		$subject = sprintf(
			/* translators: %s: post title */
			__( 'New question on "%s"', 'kosher-comments' ),
			$post->post_title
		);

		$message = $this->build_question_notification_email(
			array(
				'author_name'  => $recipient['name'],
				'comment_url'  => $comment_url,
				'footer_email' => $footer_email,
				'logo_url'     => $logo_url,
				'post_title'   => get_the_title( $post ),
				'recipe_image' => $recipe_image,
				'site_name'    => $site_name,
				'question'     => $comment_text,
			)
		);

		wp_mail(
			$recipient['email'],
			$subject,
			$message,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Resolve the user who should receive question notifications.
	 *
	 * @param WP_Post $post Post.
	 * @return array{name: string, email: string}
	 */
	protected function get_question_notification_recipient( WP_Post $post ) {
		$fallback = array(
			'name'  => __( 'Kosher.com Team', 'kosher-comments' ),
			'email' => 'hello@kosher.com',
		);
		$chef_ids = array();

		if ( function_exists( 'get_field' ) ) {
			$chef_field = get_field( 'chef', $post->ID );

			if ( function_exists( 'kayco_get_user_ids_from_field' ) ) {
				$chef_ids = kayco_get_user_ids_from_field( $chef_field );
			} else {
				$chef_ids = $this->extract_user_ids_from_field_value( $chef_field );
			}
		}

		$recipient_id = ! empty( $chef_ids ) ? absint( $chef_ids[0] ) : 0;
		$recipient    = $recipient_id ? get_userdata( $recipient_id ) : false;

		if ( ! $recipient instanceof WP_User || empty( $recipient->user_email ) ) {
			return $fallback;
		}

		$receives_questions = get_user_meta( (int) $recipient->ID, 'kayco_receive_question_notifications', true );

		if ( '0' === (string) $receives_questions ) {
			return $fallback;
		}

		return array(
			'name'  => $recipient->display_name,
			'email' => $recipient->user_email,
		);
	}

	/**
	 * Extract user IDs from common ACF user field return formats.
	 *
	 * @param mixed $value Field value.
	 * @return array<int, int>
	 */
	protected function extract_user_ids_from_field_value( $value ) {
		$ids = array();

		if ( $value instanceof WP_User ) {
			$ids[] = (int) $value->ID;
		} elseif ( is_numeric( $value ) ) {
			$ids[] = absint( $value );
		} elseif ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) ) {
				$ids[] = absint( $value['ID'] );
			} elseif ( isset( $value['id'] ) ) {
				$ids[] = absint( $value['id'] );
			} else {
				foreach ( $value as $item ) {
					$ids = array_merge( $ids, $this->extract_user_ids_from_field_value( $item ) );
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Build the branded HTML email for new recipe questions.
	 *
	 * @param array<string, string> $data Email data.
	 * @return string
	 */
	protected function build_question_notification_email( $data ) {
		$site_name    = ! empty( $data['site_name'] ) ? $data['site_name'] : __( 'Kosher.com', 'kosher-comments' );
		$post_title   = ! empty( $data['post_title'] ) ? $data['post_title'] : __( 'your recipe', 'kosher-comments' );
		$author_name  = ! empty( $data['author_name'] ) ? $data['author_name'] : __( 'Chef', 'kosher-comments' );
		$question     = ! empty( $data['question'] ) ? $this->get_plain_comment_text( $data['question'] ) : '';
		$comment_url  = ! empty( $data['comment_url'] ) ? $data['comment_url'] : home_url( '/' );
		$footer_email = ! empty( $data['footer_email'] ) ? $data['footer_email'] : get_option( 'admin_email' );
		$logo_url     = ! empty( $data['logo_url'] ) ? $data['logo_url'] : '';
		$recipe_image = ! empty( $data['recipe_image'] ) ? $data['recipe_image'] : '';
		$preview_text = sprintf(
			/* translators: %s: recipe title */
			__( 'A reader asked a question about %s.', 'kosher-comments' ),
			$post_title
		);

		ob_start();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $preview_text ); ?></title>
		</head>
		<body style="margin:0;padding:0;background:#f5f1eb;color:#202020;font-family:Arial,Helvetica,sans-serif;">
			<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;"><?php echo esc_html( $preview_text ); ?></div>
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f5f1eb;margin:0;padding:28px 12px;">
				<tr>
					<td align="center">
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #eadfd2;box-shadow:0 14px 34px rgba(31,25,20,0.08);">
							<tr>
								<td style="padding:28px 32px 20px;background:#fffaf4;border-bottom:1px solid #efe3d6;">
									<?php if ( $logo_url ) : ?>
										<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="150" style="display:block;max-width:150px;height:auto;margin:0 0 18px;">
									<?php else : ?>
										<div style="font-size:22px;line-height:1.2;font-weight:800;color:#1f1b16;margin:0 0 18px;"><?php echo esc_html( $site_name ); ?></div>
									<?php endif; ?>
									<div style="display:inline-block;padding:7px 12px;border-radius:8px;background:#f0e2d2;color:#7a4e22;font-size:12px;line-height:1;font-weight:700;text-transform:uppercase;letter-spacing:0;"><?php esc_html_e( 'New Recipe Question', 'kosher-comments' ); ?></div>
									<h1 style="margin:16px 0 8px;font-size:28px;line-height:1.18;color:#1f1b16;font-weight:800;"><?php esc_html_e( 'Someone asked about your recipe', 'kosher-comments' ); ?></h1>
									<p style="margin:0;font-size:16px;line-height:1.6;color:#5f554c;"><?php echo esc_html( sprintf( __( 'Hi %s, a reader marked their comment as a question.', 'kosher-comments' ), $author_name ) ); ?></p>
								</td>
							</tr>
							<?php if ( $recipe_image ) : ?>
								<tr>
									<td>
										<img src="<?php echo esc_url( $recipe_image ); ?>" alt="<?php echo esc_attr( $post_title ); ?>" width="640" style="display:block;width:100%;max-width:640px;height:auto;">
									</td>
								</tr>
							<?php endif; ?>
							<tr>
								<td style="padding:30px 32px 8px;">
									<p style="margin:0 0 8px;font-size:13px;line-height:1.4;color:#8b7b6c;font-weight:700;text-transform:uppercase;letter-spacing:0;"><?php esc_html_e( 'Recipe', 'kosher-comments' ); ?></p>
									<h2 style="margin:0 0 22px;font-size:23px;line-height:1.28;color:#231f1a;font-weight:800;"><?php echo esc_html( $post_title ); ?></h2>
									<div style="margin:0 0 26px;padding:22px 24px;background:#fbf7f1;border-left:4px solid #c57d35;border-radius:8px;">
										<p style="margin:0 0 10px;font-size:13px;line-height:1.4;color:#8b6a4a;font-weight:700;text-transform:uppercase;letter-spacing:0;"><?php esc_html_e( 'Question', 'kosher-comments' ); ?></p>
										<p style="margin:0;font-size:17px;line-height:1.65;color:#2b2824;"><?php echo esc_html( $question ); ?></p>
									</div>
									<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 22px;">
										<tr>
											<td bgcolor="#1f1b16" style="border-radius:8px;">
												<a href="<?php echo esc_url( $comment_url ); ?>" style="display:inline-block;padding:14px 22px;border-radius:8px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;"><?php esc_html_e( 'View and Reply to Comment', 'kosher-comments' ); ?></a>
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td style="padding:22px 32px 28px;background:#2a241f;color:#dfd4c8;">
									<p style="margin:0 0 8px;font-size:14px;line-height:1.6;"><?php echo esc_html( sprintf( __( 'Sent by %s because this comment was marked as a question.', 'kosher-comments' ), $site_name ) ); ?></p>
									<?php if ( $footer_email ) : ?>
										<p style="margin:0;font-size:13px;line-height:1.5;color:#bdaea0;"><?php echo esc_html( sprintf( __( 'Need help? Contact %s.', 'kosher-comments' ), $footer_email ) ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php

		return trim( ob_get_clean() );
	}

	/**
	 * Resolve the site logo URL for notification emails.
	 *
	 * @return string
	 */
	protected function get_notification_logo_url() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );

			if ( $logo_url ) {
				return $logo_url;
			}
		}

		$site_icon_url = get_site_icon_url( 192 );

		return $site_icon_url ? $site_icon_url : '';
	}

	/**
	 * Notify a parent comment author about a reply.
	 *
	 * @param object  $parent_comment Parent comment.
	 * @param int     $comment_id New comment ID.
	 * @param WP_Post $post Post.
	 * @param string  $comment_text Reply content.
	 * @return void
	 */
	protected function maybe_notify_parent_author( $parent_comment, $comment_id, WP_Post $post, $comment_text ) {
		if ( empty( $parent_comment->notify_replies ) ) {
			return;
		}

		$parent_author = get_userdata( (int) $parent_comment->user_id );
		$email         = '';

		if ( $parent_author instanceof WP_User ) {
			$email = $parent_author->user_email;
		} elseif ( ! empty( $parent_comment->author_email ) ) {
			$email = sanitize_email( $parent_comment->author_email );
		}

		if ( empty( $email ) || ( $parent_author instanceof WP_User && (int) $parent_author->ID === get_current_user_id() ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: post title */
			__( 'New reply on "%s"', 'kosher-comments' ),
			$post->post_title
		);

		$message = sprintf(
			/* translators: 1: post title, 2: reply text, 3: url */
			__( "Someone replied to your comment on \"%1\$s\".\n\nReply:\n%2\$s\n\nView it here: %3\$s", 'kosher-comments' ),
			$post->post_title,
			$comment_text,
			add_query_arg( 'comment_id', $comment_id, get_permalink( $post ) )
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Sanitize rich comment content from the frontend editor.
	 *
	 * @param mixed $comment_text Raw comment content.
	 * @return string
	 */
	protected function sanitize_comment_content( $comment_text ) {
		return trim( wp_kses_post( (string) wp_unslash( $comment_text ) ) );
	}

	/**
	 * Get a list of disallowed link hosts found in comment content.
	 *
	 * @param string $comment_text Comment content.
	 * @return array<int, string>
	 */
	protected function get_disallowed_comment_link_hosts( $comment_text ) {
		$disallowed_hosts = array();

		foreach ( $this->get_comment_link_urls( $comment_text ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );

			if ( ! is_string( $host ) || '' === $host ) {
				continue;
			}

			$normalized_host = $this->normalize_comment_link_host( $host );

			if ( '' === $normalized_host || $this->is_allowed_comment_link_host( $normalized_host ) ) {
				continue;
			}

			$disallowed_hosts[] = $normalized_host;
		}

		return array_values( array_unique( $disallowed_hosts ) );
	}

	/**
	 * Extract URLs from comment content.
	 *
	 * @param string $comment_text Comment content.
	 * @return array<int, string>
	 */
	protected function get_comment_link_urls( $comment_text ) {
		$comment_text = (string) $comment_text;
		$urls         = array();
		$charset      = get_bloginfo( 'charset' ) ?: 'UTF-8';
		$raw_urls     = wp_extract_urls( $comment_text );

		if ( is_array( $raw_urls ) ) {
			$urls = array_merge( $urls, $raw_urls );
		}

		if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\']?)([^"\'\s>]+)\1/i', $comment_text, $matches ) ) {
			foreach ( $matches[2] as $match ) {
				$urls[] = html_entity_decode( (string) $match, ENT_QUOTES, $charset );
			}
		}

		$urls = array_map( 'esc_url_raw', $urls );
		$urls = array_filter( $urls );

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Determine whether a link host is allowed in comments.
	 *
	 * @param string $host Link host.
	 * @return bool
	 */
	protected function is_allowed_comment_link_host( $host ) {
		foreach ( $this->get_allowed_comment_link_hosts() as $allowed_host ) {
			if ( $host === $allowed_host ) {
				return true;
			}

			if ( strlen( $host ) > strlen( $allowed_host ) && substr( $host, -strlen( '.' . $allowed_host ) ) === '.' . $allowed_host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the current user can post links outside the allow list.
	 *
	 * @return bool
	 */
	protected function current_user_can_post_restricted_links() {
		return current_user_can( 'edit_others_posts' ) || current_user_can( 'moderate_comments' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Determine whether a comment author should show a staff badge.
	 *
	 * @param WP_User|null $user Comment author user.
	 * @return bool
	 */
	protected function is_staff_comment_user( $user ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return user_can( $user, 'edit_others_posts' ) || user_can( $user, 'moderate_comments' ) || user_can( $user, 'manage_options' );
	}

	/**
	 * Build the list of allowed comment link hosts.
	 *
	 * @return array<int, string>
	 */
	protected function get_allowed_comment_link_hosts() {
		$allowed_hosts = array(
			'kosher.com',
			'kayco.com',
			'manischewitz.com',
			'royalwine.com',
		);
		$site_host     = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( is_string( $site_host ) && '' !== $site_host ) {
			$allowed_hosts[] = $site_host;
		}

		$allowed_hosts = array_map( array( $this, 'normalize_comment_link_host' ), $allowed_hosts );
		$allowed_hosts = array_filter( $allowed_hosts );

		return array_values( array_unique( $allowed_hosts ) );
	}

	/**
	 * Normalize a host so domain comparisons stay consistent.
	 *
	 * @param string $host Raw host.
	 * @return string
	 */
	protected function normalize_comment_link_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '/:\d+$/', '', $host );

		if ( ! is_string( $host ) ) {
			return '';
		}

		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Convert stored comment markup to plain text for validation and notifications.
	 *
	 * @param string $comment_text Comment content.
	 * @return string
	 */
	protected function get_plain_comment_text( $comment_text ) {
		$plain_text = html_entity_decode( wp_strip_all_tags( (string) $comment_text, true ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$plain_text = str_replace( "\xc2\xa0", ' ', $plain_text );
		return trim( $plain_text );
	}

	/**
	 * Get the root comment in a thread.
	 *
	 * @param int $comment_id Comment ID.
	 * @return object|null
	 */
	protected function get_root_comment( $comment_id ) {
		$current = $this->get_comment_row( $comment_id );

		while ( $current && ! empty( $current->parent_id ) ) {
			$current = $this->get_comment_row( (int) $current->parent_id );
		}

		return $current;
	}

	/**
	 * Get a comment depth.
	 *
	 * @param int $comment_id Comment ID.
	 * @return int
	 */
	protected function get_comment_depth( $comment_id ) {
		$depth   = 0;
		$current = $this->get_comment_row( $comment_id, false );

		while ( $current && ! empty( $current->parent_id ) ) {
			$current = $this->get_comment_row( (int) $current->parent_id, false );
			++$depth;
		}

		return $depth;
	}

	/**
	 * Determine whether threads contain a comment.
	 *
	 * @param array<int, object> $threads Threads.
	 * @param int                $target_comment_id Target comment ID.
	 * @return bool
	 */
	protected function threads_contain_comment( $threads, $target_comment_id ) {
		foreach ( $threads as $thread ) {
			if ( (int) $thread->id === (int) $target_comment_id ) {
				return true;
			}

			if ( ! empty( $thread->replies ) && $this->threads_contain_comment( $thread->replies, $target_comment_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether an open report already exists.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $comment_id Comment ID.
	 * @param int    $image_id Image ID.
	 * @param string $report_type Report type.
	 * @return bool
	 */
	protected function has_open_report( $user_id, $comment_id, $image_id, $report_type ) {
		global $wpdb;

		if ( $comment_id && $image_id ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . kosher_comments_get_table_name( 'reports' ) . ' WHERE reporter_user_id = %d AND comment_id = %d AND image_id = %d AND report_type = %s AND status = %s',
					$user_id,
					$comment_id,
					$image_id,
					$report_type,
					'open'
				)
			);
		} elseif ( $comment_id ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . kosher_comments_get_table_name( 'reports' ) . ' WHERE reporter_user_id = %d AND comment_id = %d AND image_id IS NULL AND report_type = %s AND status = %s',
					$user_id,
					$comment_id,
					$report_type,
					'open'
				)
			);
		} else {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . kosher_comments_get_table_name( 'reports' ) . ' WHERE reporter_user_id = %d AND comment_id = %d AND image_id = %d AND report_type = %s AND status = %s',
					$user_id,
					$comment_id,
					$image_id,
					$report_type,
					'open'
				)
			);
		}

		return (int) $count > 0;
	}

	/**
	 * Get a comment thread IDs list.
	 *
	 * @param int $comment_id Root comment ID.
	 * @return array<int>
	 */
	protected function get_comment_thread_ids( $comment_id ) {
		global $wpdb;

		$comment_id = absint( $comment_id );

		if ( ! $comment_id ) {
			return array();
		}

		$all_ids    = array( $comment_id );
		$parent_ids = array( $comment_id );

		while ( ! empty( $parent_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
			$child_ids    = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID
					FROM {$wpdb->comments}
					WHERE comment_parent IN ({$placeholders})
						AND comment_type IN ('', 'comment')",
					$parent_ids
				)
			);

			$child_ids = array_values( array_filter( array_map( 'absint', $child_ids ) ) );

			if ( empty( $child_ids ) ) {
				break;
			}

			$all_ids    = array_merge( $all_ids, $child_ids );
			$parent_ids = $child_ids;
		}

		return array_values( array_unique( $all_ids ) );
	}

	/**
	 * Return a raw comment ID from a WP comment object.
	 *
	 * @param object $comment Raw comment.
	 * @return int
	 */
	protected function get_raw_comment_id( $comment ) {
		if ( isset( $comment->comment_ID ) ) {
			return absint( $comment->comment_ID );
		}

		if ( isset( $comment->id ) ) {
			return absint( $comment->id );
		}

		return 0;
	}

	/**
	 * Return a raw user ID from a WP comment object.
	 *
	 * @param object $comment Raw comment.
	 * @return int
	 */
	protected function get_raw_user_id( $comment ) {
		if ( isset( $comment->user_id ) ) {
			return absint( $comment->user_id );
		}

		return 0;
	}
}
