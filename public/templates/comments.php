<?php
$summary          = $overview['summary'];
$rating_bars      = $overview['rating_bars'];
$image_reviews    = $overview['image_reviews'];
$featured_reviews = $overview['featured_reviews'];
$can_moderate     = Kosher_Comments_Public::current_user_can_moderate();
$user_rating      = isset( $overview['user_rating'] ) ? $overview['user_rating'] : null;
$rating_breakdown_count = ! empty( $summary['rating_distribution'] ) ? array_sum( array_map( 'intval', $summary['rating_distribution'] ) ) : 0;
$has_rating_breakdown   = $rating_breakdown_count > 0 && $rating_breakdown_count === (int) $summary['ratings_count'];
$has_customer_reviews   = ! empty( $summary['ratings_count'] ) || ! empty( $page_payload['total'] );
$has_image_reviews      = ! empty( $image_reviews );
$review_copy            = isset( $review_copy ) && is_array( $review_copy ) ? $review_copy : array();
$summary_title          = isset( $review_copy['summary_title'] ) ? $review_copy['summary_title'] : __( 'What Readers Are Saying', 'kosher-comments' );
$photos_title           = isset( $review_copy['photos_title'] ) ? $review_copy['photos_title'] : __( 'Photos from Our Community', 'kosher-comments' );
$photos_empty           = isset( $review_copy['photos_empty'] ) ? $review_copy['photos_empty'] : __( "See how these recipes turned out in real kitchens. Photos from home cooks will appear here as they're shared.", 'kosher-comments' );
?>
<section
	class="kosher-comments"
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-current-page="1"
	data-target-comment-id="<?php echo esc_attr( $target_comment_id ); ?>"
	data-target-page="<?php echo esc_attr( $target_page ); ?>"
	data-user-has-rated="<?php echo esc_attr( $user_rating ? 1 : 0 ); ?>"
>
	<div class="kosher-comments-shell">
		<?php if ( $has_customer_reviews || $has_image_reviews ) : ?>
			<div class="kosher-comments-top">
				<?php if ( $has_customer_reviews ) : ?>
					<div class="kosher-comments-summary-card">
						<p class="kosher-comments-summary-kicker"><?php echo esc_html( $summary_title ); ?></p>
						<div class="kosher-comments-summary-score">
							<?php echo wp_kses_post( Kosher_Comments_Public::render_rating_stars( (float) $summary['average_rating'] ) ); ?>
							<strong><?php echo esc_html( number_format_i18n( (float) $summary['average_rating'], 1 ) ); ?></strong>
							<span><?php esc_html_e( 'out of 5', 'kosher-comments' ); ?></span>
						</div>
						<p class="kosher-comments-summary-count">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: ratings count */
									_n( '%d global rating', '%d global ratings', (int) $summary['ratings_count'], 'kosher-comments' ),
									(int) $summary['ratings_count']
								)
							);
							?>
						</p>
						<?php if ( $has_rating_breakdown ) : ?>
							<div class="kosher-comments-summary-bars">
								<?php foreach ( $rating_bars as $rating => $bar ) : ?>
									<div class="kosher-comments-summary-bar-row">
										<span><?php echo esc_html( $rating ); ?> <?php esc_html_e( 'star', 'kosher-comments' ); ?></span>
										<div class="kosher-comments-summary-bar"><i style="width:<?php echo esc_attr( (int) $bar['percent'] ); ?>%;"></i></div>
										<em><?php echo esc_html( (int) $bar['percent'] ); ?>%</em>
									</div>
								<?php endforeach; ?>
							</div>
						<?php elseif ( ! empty( $summary['ratings_count'] ) ) : ?>
							<p class="kosher-comments-summary-count"><?php esc_html_e( 'Rating breakdown is not available for migrated legacy ratings.', 'kosher-comments' ); ?></p>
						<?php endif; ?>

					</div>
				<?php endif; ?>

				<?php if ( $has_image_reviews ) : ?>
					<div class="kosher-comments-photos-card">
						<div class="kosher-comments-section-heading">
							<div>
								<p class="kosher-comments-summary-kicker"><?php echo esc_html( $photos_title ); ?></p>
							</div>
							<button type="button" class="kosher-comments-open-all-photos"><?php esc_html_e( 'See all photos', 'kosher-comments' ); ?></button>
						</div>

						<div class="kosher-comments-photo-strip" data-photo-collection data-photo-collection-label="<?php esc_attr_e( 'All photos', 'kosher-comments' ); ?>">
							<?php foreach ( $image_reviews as $index => $image ) : ?>
								<button
									type="button"
									class="kosher-comments-photo-thumb"
									data-open-photo-modal
									data-photo-index="<?php echo esc_attr( $index ); ?>"
									data-image-id="<?php echo esc_attr( $image['image_id'] ); ?>"
									data-comment-id="<?php echo esc_attr( $image['comment_id'] ); ?>"
									data-photo-url="<?php echo esc_url( $image['url'] ); ?>"
									data-photo-thumb="<?php echo esc_url( $image['thumb'] ); ?>"
									data-author-name="<?php echo esc_attr( $image['author_name'] ); ?>"
									data-avatar-url="<?php echo esc_url( $image['avatar_url'] ); ?>"
									data-rating="<?php echo esc_attr( $image['rating'] ); ?>"
									data-excerpt="<?php echo esc_attr( $image['excerpt'] ); ?>"
									data-share-url="<?php echo esc_url( $image['share_url'] ); ?>"
								>
									<img src="<?php echo esc_url( $image['thumb'] ); ?>" alt="">
								</button>
							<?php endforeach; ?>
						</div>

					</div>
				<?php elseif ( $has_customer_reviews ) : ?>
					<div class="kosher-comments-photos-card">
						<div class="kosher-comments-section-heading">
							<div>
								<p class="kosher-comments-summary-kicker"><?php echo esc_html( $photos_title ); ?></p>
							</div>
						</div>

						<p class="kosher-comments-summary-count"><?php echo esc_html( $photos_empty ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php include KOSHER_COMMENTS_PATH . 'public/templates/comment-compose.php'; ?>

		<div class="kosher-comments-thread-head">
			<h3><?php echo esc_html( sprintf( _n( '%d Comment', '%d Comments', (int) $page_payload['total'], 'kosher-comments' ), (int) $page_payload['total'] ) ); ?></h3>
			<?php if ( $can_moderate ) : ?>
				<span class="kosher-comments-moderator-note"><?php esc_html_e( 'Frontend moderation enabled for admins and editors', 'kosher-comments' ); ?></span>
			<?php endif; ?>
		</div>

		<?php
		$include_report_modal = true;
		$include_photo_modal  = true;
		include KOSHER_COMMENTS_PATH . 'public/templates/comments-support.php';
		?>

		<div class="kosher-comments-list" data-comments-list>
			<?php echo $page_payload['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php if ( ! empty( $page_payload['hasMore'] ) ) : ?>
			<div class="kosher-comments-footer">
				<button type="button" class="kosher-comments-load-more"><?php esc_html_e( 'Load more comments', 'kosher-comments' ); ?></button>
			</div>
		<?php endif; ?>
	</div>
</section>
