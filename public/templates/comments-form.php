<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_rating = isset( $overview['user_rating'] ) ? $overview['user_rating'] : null;
?>
<section
	class="kosher-comments kosher-comments-form-only"
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-current-page="1"
	data-target-comment-id="0"
	data-target-page="1"
	data-user-has-rated="<?php echo esc_attr( $user_rating ? 1 : 0 ); ?>"
>
	<div class="kosher-comments-shell">
		<?php include KOSHER_COMMENTS_PATH . 'public/templates/comment-compose.php'; ?>

		<?php
		$include_report_modal = false;
		$include_photo_modal  = false;
		include KOSHER_COMMENTS_PATH . 'public/templates/comments-support.php';
		?>
	</div>
</section>
