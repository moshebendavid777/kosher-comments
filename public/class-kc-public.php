<?php
/**
 * Public-facing rendering.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Public {

	/**
	 * Comments service.
	 *
	 * @var Kosher_Comments_Comments
	 */
	protected $comments;

	/**
	 * Constructor.
	 *
	 * @param Kosher_Comments_Comments $comments Comments service.
	 */
	public function __construct( Kosher_Comments_Comments $comments ) {
		$this->comments = $comments;
	}

	/**
	 * Register public hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'kosher_comments', array( $this, 'render_shortcode' ) );
		add_shortcode( 'kosher_comments_form', array( $this, 'render_form_shortcode' ) );
		add_shortcode( 'kosher-comments-form', array( $this, 'render_form_shortcode' ) );
		add_filter( 'comments_template', array( $this, 'filter_comments_template' ) );
	}

	/**
	 * Enqueue the public UI assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			wp_enqueue_editor();
		}

		if ( ! wp_style_is( 'bootstrap-icons', 'registered' ) ) {
			wp_register_style(
				'bootstrap-icons',
				'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
				array(),
				'1.11.3'
			);
		}

		wp_enqueue_style( 'bootstrap-icons' );

		wp_enqueue_style(
			'kosher-comments-public',
			KOSHER_COMMENTS_URL . 'assets/css/kosher-comments.css',
			array(),
			KOSHER_COMMENTS_VERSION
		);

		wp_enqueue_script(
			'kosher-comments-public',
			KOSHER_COMMENTS_URL . 'assets/js/kosher-comments.js',
			array(),
			KOSHER_COMMENTS_VERSION,
			true
		);

		wp_localize_script(
			'kosher-comments-public',
			'kosherComments',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'kosher_comments_public_nonce' ),
				'strings' => array(
					'loginRequired' => __( 'Please log in to interact with comments.', 'kosher-comments' ),
					'loginTitle'    => __( 'Login required', 'kosher-comments' ),
					'shareCopied'   => __( 'Comment link copied to clipboard.', 'kosher-comments' ),
					'shareFailed'   => __( 'Copy failed. You can still use the share link manually.', 'kosher-comments' ),
					'shareManualTitle' => __( 'Copy link manually', 'kosher-comments' ),
					'loadMore'      => __( 'Load more comments', 'kosher-comments' ),
					'loadingMore'   => __( 'Loading...', 'kosher-comments' ),
					'deleteConfirm' => __( 'Delete this comment?', 'kosher-comments' ),
					'deleteTitle'   => __( 'Delete comment?', 'kosher-comments' ),
					'deleteAction'  => __( 'Delete', 'kosher-comments' ),
					'deleteSuccess' => __( 'Comment deleted.', 'kosher-comments' ),
					'deleteError'   => __( 'Unable to delete the comment.', 'kosher-comments' ),
					'reportTitle'   => __( 'Report this item', 'kosher-comments' ),
					'reportSuccess' => __( 'Thanks. Your report was sent to moderation.', 'kosher-comments' ),
					'reportError'   => __( 'Unable to send the report.', 'kosher-comments' ),
					'reportTooLong' => __( 'Report comments must be 140 characters or less.', 'kosher-comments' ),
					'postSuccess'   => __( 'Comment posted.', 'kosher-comments' ),
					'postError'     => __( 'Unable to post your comment.', 'kosher-comments' ),
					'editSuccess'   => __( 'Comment updated.', 'kosher-comments' ),
					'editError'     => __( 'Unable to update the comment.', 'kosher-comments' ),
					'networkError'  => __( 'The network request failed. Please try again.', 'kosher-comments' ),
					'dialogTitle'   => __( 'Kosher Comments', 'kosher-comments' ),
					'dialogConfirm' => __( 'Okay', 'kosher-comments' ),
					'dialogCancel'  => __( 'Cancel', 'kosher-comments' ),
					'postingPrepare' => __( 'Your comment will be posted in a moment...', 'kosher-comments' ),
					'postingPrepareDetail' => '',
					'postingRejected' => __( 'Comment could not be posted', 'kosher-comments' ),
					'postingRejectedDetail' => __( 'The comment was rejected or needs another try.', 'kosher-comments' ),
					'loadMoreError' => __( 'Unable to load more comments right now.', 'kosher-comments' ),
					'userRatedThis' => __( 'You rated this', 'kosher-comments' ),
					'editRating'    => __( 'Edit', 'kosher-comments' ),
				),
			)
		);
	}

	/**
	 * Render the comments shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$post_id = $this->resolve_post_id( $atts );

		if ( ! $post_id ) {
			return '';
		}

		return $this->render_template( 'comments.php', $this->build_render_context( $post_id ) );
	}

	/**
	 * Render the compose-only shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form_shortcode( $atts = array() ) {
		$post_id = $this->resolve_post_id( $atts );

		if ( ! $post_id ) {
			return '';
		}

		return $this->render_template( 'comments-form.php', $this->build_render_context( $post_id ) );
	}

	/**
	 * Resolve a shortcode post ID.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return int
	 */
	protected function resolve_post_id( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			is_array( $atts ) ? $atts : array(),
			'kosher_comments'
		);

		$post_id = absint( $atts['post_id'] );

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return absint( $post_id );
	}

	/**
	 * Build the render context for frontend templates.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	protected function build_render_context( $post_id ) {
		$target_comment_id = absint( $_GET['comment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $target_comment_id && ! $this->comments->comment_belongs_to_post( $post_id, $target_comment_id ) ) {
			$target_comment_id = 0;
		}

		$target_page  = $target_comment_id ? $this->comments->get_target_page( $post_id, $target_comment_id ) : 1;
		$page_payload = $this->comments->get_comments_page( $post_id, 1, $target_comment_id );
		$overview     = $this->comments->get_frontend_overview( $post_id );
		$current_user = wp_get_current_user();
		$login_url    = wp_login_url( get_permalink( $post_id ) );
		$review_copy  = $this->get_review_copy( $post_id );

		return array(
			'post_id'           => $post_id,
			'target_comment_id' => $target_comment_id,
			'target_page'       => $target_page,
			'page_payload'      => $page_payload,
			'overview'          => $overview,
			'current_user'      => $current_user,
			'login_url'         => $login_url,
			'review_copy'       => $review_copy,
		);
	}

	/**
	 * Get page-specific copy for the reviews UI.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	protected function get_review_copy( $post_id ) {
		$post_type = get_post_type( $post_id );

		$copy = array(
			'summary_title'     => __( 'What Readers Are Saying', 'kosher-comments' ),
			'compose_title'     => __( 'Tell Us What You Think', 'kosher-comments' ),
			'photos_title'      => __( 'Photos from Our Community', 'kosher-comments' ),
			'photos_empty'      => __( "See how these recipes turned out in real kitchens. Photos from home cooks will appear here as they're shared.", 'kosher-comments' ),
			'logged_out_notice' => __( "Your review could help someone decide what's for dinner tonight. Log in to join the conversation.", 'kosher-comments' ),
		);

		if ( 'recipes' === $post_type ) {
			$copy['summary_title'] = __( 'What Cooks Are Saying', 'kosher-comments' );
			$copy['compose_title'] = __( 'Tell Us How It Went: Share Your Experience', 'kosher-comments' );
		} elseif ( 'episodes' === $post_type || 'videos' === $post_type || 'video' === $post_type ) {
			$copy['summary_title'] = __( 'What Viewers Are Saying', 'kosher-comments' );
		}

		return $copy;
	}

	/**
	 * Render a public template with a prepared context.
	 *
	 * @param string               $template Template filename.
	 * @param array<string, mixed> $context Template variables.
	 * @return string
	 */
	protected function render_template( $template, $context ) {
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		extract( $context, EXTR_SKIP );

		ob_start();
		include KOSHER_COMMENTS_PATH . 'public/templates/' . $template;
		return ob_get_clean();
	}

	/**
	 * Replace the native WordPress comments template on singular content.
	 *
	 * @param string $template Existing template path.
	 * @return string
	 */
	public function filter_comments_template( $template ) {
		if ( is_admin() || ! is_singular() ) {
			return $template;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return $template;
		}

		return KOSHER_COMMENTS_PATH . 'public/templates/comments-template.php';
	}

	/**
	 * Render a batch of comments into HTML.
	 *
	 * @param array<int, object> $comments Comments.
	 * @param int                $target_comment_id Highlighted comment ID.
	 * @return string
	 */
	public static function render_comments_markup( $comments, $target_comment_id = 0 ) {
		ob_start();

		foreach ( $comments as $comment ) {
			echo self::render_single_comment( $comment, 0, $target_comment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ob_get_clean();
	}

	/**
	 * Render a single comment node.
	 *
	 * @param object $comment Comment object.
	 * @param int    $depth Depth.
	 * @param int    $target_comment_id Highlighted comment ID.
	 * @return string
	 */
	public static function render_single_comment( $comment, $depth = 0, $target_comment_id = 0 ) {
		ob_start();
		include KOSHER_COMMENTS_PATH . 'public/templates/single-comment.php';
		return ob_get_clean();
	}

	/**
	 * Render star markup for a rating.
	 *
	 * @param int|float|string|null $rating Rating value.
	 * @return string
	 */
	public static function render_rating_stars( $rating ) {
		$rating = null === $rating ? null : (float) $rating;

		if ( null === $rating || $rating <= 0 ) {
			return '';
		}

		$rating = max( 0, min( 5, round( $rating * 2 ) / 2 ) );
		$label  = number_format_i18n( $rating, floor( $rating ) === $rating ? 0 : 1 );
		$output = '<span class="kosher-comments-stars" aria-label="' . esc_attr( sprintf( __( '%s out of 5 stars', 'kosher-comments' ), $label ) ) . '">';

		for ( $index = 1; $index <= 5; $index++ ) {
			$star_value = $rating - ( $index - 1 );

			if ( $star_value >= 1 ) {
				$state      = ' is-filled';
			} elseif ( $star_value >= 0.5 ) {
				$state      = ' is-partial';
			} else {
				$state      = ' is-empty';
			}

			$output .= '<span class="kosher-comments-star bi bi-star-fill' . esc_attr( $state ) . '" aria-hidden="true"></span>';
		}

		$output .= '</span>';

		return $output;
	}

	/**
	 * Determine whether the current user can moderate from the frontend.
	 *
	 * @return bool
	 */
	public static function current_user_can_moderate() {
		return current_user_can( 'manage_options' ) || current_user_can( 'moderate_comments' ) || current_user_can( 'edit_others_posts' );
	}
}
