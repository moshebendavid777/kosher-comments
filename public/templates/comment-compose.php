<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$review_copy       = isset( $review_copy ) && is_array( $review_copy ) ? $review_copy : array();
$compose_title     = isset( $review_copy['compose_title'] ) ? $review_copy['compose_title'] : __( 'Tell Us What You Think', 'kosher-comments' );
$logged_out_notice = isset( $review_copy['logged_out_notice'] ) ? $review_copy['logged_out_notice'] : __( "Your review could help someone decide what's for dinner tonight. Log in to join the conversation.", 'kosher-comments' );

$compose_name         = trim( wp_strip_all_tags( (string) ( $current_user->display_name ?: $current_user->user_login ) ) );
$compose_name_letters = preg_replace( '/[^\p{L}\p{N}]+/u', '', $compose_name );

if ( is_string( $compose_name_letters ) && '' !== $compose_name_letters ) {
	$compose_name = $compose_name_letters;
}

$compose_avatar_label = function_exists( 'wp_html_excerpt' ) ? wp_html_excerpt( $compose_name, 2, '' ) : substr( $compose_name, 0, 2 );

if ( '' === $compose_avatar_label ) {
	$compose_avatar_label = 'AN';
}

if ( function_exists( 'mb_strtoupper' ) ) {
	$compose_avatar_label = mb_strtoupper( $compose_avatar_label, get_bloginfo( 'charset' ) ?: 'UTF-8' );
} else {
	$compose_avatar_label = strtoupper( $compose_avatar_label );
}
?>
<div class="kosher-comments-compose">
	<div class="kosher-comments-compose-head">
		<h2><?php echo esc_html( $compose_title ); ?></h2>
		<?php if ( is_user_logged_in() ) : ?>
			<p><?php echo esc_html( sprintf( __( 'You are logged in as %s', 'kosher-comments' ), $current_user->display_name ) ); ?> <a href="<?php echo esc_url( wp_logout_url( get_permalink( $post_id ) ) ); ?>"><?php esc_html_e( 'Log out', 'kosher-comments' ); ?></a></p>
		<?php endif; ?>
	</div>

	<?php if ( is_user_logged_in() ) : ?>
		<form id="kosher-comments-form" class="kosher-comments-form kosher-comments-form-main" data-parent-id="0">
			<div class="kosher-comments-form-shell">
				<div class="kosher-comments-form-avatar"><?php echo esc_html( $compose_avatar_label ); ?></div>
				<div class="kosher-comments-form-main-area">
					<?php
					$editor_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'kosher-comments-comment-text-' ) : 'kosher-comments-comment-text-' . (int) $post_id;
					wp_editor(
						'',
						$editor_id,
						array(
							'textarea_name' => 'comment_text',
							'textarea_rows' => 5,
							'teeny'         => true,
							'media_buttons' => false,
							'quicktags'     => false,
							'editor_class'  => 'kosher-comments-editor-field',
							'tinymce'       => array(
								'toolbar1'      => 'bold,italic,bullist,numlist,blockquote,link,undo,redo',
								'toolbar2'      => '',
								'statusbar'     => false,
								'resize'        => false,
								'branding'      => false,
								'wp_autoresize_on' => true,
							),
						)
					);
					?>
					<div class="kosher-comments-form-toolbar">
						<div class="kosher-comments-form-toolbar-main">
							<?php if ( $user_rating ) : ?>
								<div class="kosher-comments-user-rated" data-user-rated-state data-user-rating="<?php echo esc_attr( (int) $user_rating ); ?>">
									<span class="kosher-comments-user-rated-label"><?php esc_html_e( 'You rated this', 'kosher-comments' ); ?></span>
									<span class="kayco-recipe-rating__stars"><?php echo wp_kses_post( Kosher_Comments_Public::render_rating_stars( $user_rating ) ); ?></span>
									<button type="button" class="kosher-comments-user-rated-edit" data-edit-user-rating><?php esc_html_e( 'Edit', 'kosher-comments' ); ?></button>
								</div>
								<input type="hidden" name="rating" value="">
							<?php else : ?>
								<div class="kosher-comments-rating-picker" data-rating-picker>
									<div class="kosher-comments-rating-buttons kayco-recipe-rating__stars">
										<?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?>
											<button type="button" class="kosher-comments-rating-button" data-rating="<?php echo esc_attr( $rating ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Set rating to %d', 'kosher-comments' ), $rating ) ); ?>">
												<span class="bi bi-star-fill" aria-hidden="true"></span>
											</button>
										<?php endfor; ?>
									</div>
									<input type="hidden" name="rating" value="">
									<button type="button" class="kosher-comments-submit kosher-comments-rating-only-submit" data-rating-only-submit><?php esc_html_e( 'Submit Rating Only', 'kosher-comments' ); ?></button>
								</div>
							<?php endif; ?>
							<div class="kosher-comments-upload-group">
								<label class="kosher-comments-upload">
									<i class="bi bi-paperclip" aria-hidden="true"></i>
									<span><?php esc_html_e( 'Add photos', 'kosher-comments' ); ?></span>
									<input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
								</label>
								<div class="kosher-comments-selected-files" aria-live="polite"></div>
							</div>
						</div>
					</div>
					<div class="kosher-comments-form-footer">
						<label class="kosher-comments-question-toggle">
							<input type="checkbox" name="is_question" value="1">
							<span class="kosher-comments-question-switch" aria-hidden="true"><i></i></span>
							<span><?php esc_html_e( 'Mark your comment as a question', 'kosher-comments' ); ?></span>
						</label>
							<div class="kosher-comments-form-footer-actions">
								<label class="kosher-comments-bell-toggle">
									<input type="checkbox" name="notify_replies" value="1" checked>
									<span class="kosher-comments-bell-button">
										<span class="kosher-comments-bell-icon" aria-hidden="true">
											<svg viewBox="0 0 24 24" focusable="false">
												<path d="M12 3.75a4.5 4.5 0 0 0-4.5 4.5v1.02c0 .94-.28 1.85-.82 2.61L5.1 14.1a1.5 1.5 0 0 0 1.22 2.4h11.36a1.5 1.5 0 0 0 1.22-2.4l-1.58-2.22A4.49 4.49 0 0 1 16.5 9.27V8.25A4.5 4.5 0 0 0 12 3.75Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"/>
												<path d="M9.75 18a2.25 2.25 0 0 0 4.5 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"/>
											</svg>
										</span>
										<span class="kosher-comments-bell-text"><?php esc_html_e( 'Notify me of replies', 'kosher-comments' ); ?></span>
									</span>
								</label>
								<button type="submit" class="kosher-comments-submit"><?php esc_html_e( 'Post Comment', 'kosher-comments' ); ?></button>
							</div>
					</div>
				</div>
			</div>
		</form>
	<?php else : ?>
		<div class="kosher-comments-login-box">
			<p><?php echo esc_html( $logged_out_notice ); ?></p>
			<a class="kosher-comments-login-link" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to join the discussion', 'kosher-comments' ); ?></a>
		</div>
	<?php endif; ?>
</div>
