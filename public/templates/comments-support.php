<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$include_report_modal = ! empty( $include_report_modal );
$include_photo_modal  = ! empty( $include_photo_modal );
?>
<div class="kosher-comments-feedback" aria-live="polite"></div>
<div class="kosher-comments-toast-stack" aria-live="polite" aria-atomic="true"></div>

<div class="kosher-comments-modal kosher-comments-rating-modal" hidden>
	<div class="kosher-comments-modal-backdrop" data-modal-close></div>
	<div class="kosher-comments-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kosher-comments-modal-title">
		<h3 id="kosher-comments-modal-title"><?php esc_html_e( 'Do you want to rate this post?', 'kosher-comments' ); ?></h3>
		<p><?php esc_html_e( 'Adding a star rating makes the review more helpful. You can still post without one.', 'kosher-comments' ); ?></p>
		<div class="kosher-comments-rating-picker kosher-comments-modal-rating-picker" data-rating-picker data-modal-rating-picker>
			<div class="kosher-comments-rating-buttons kayco-recipe-rating__stars">
				<?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?>
					<button type="button" class="kosher-comments-rating-button" data-rating="<?php echo esc_attr( $rating ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Set rating to %d', 'kosher-comments' ), $rating ) ); ?>">
						<span class="bi bi-star-fill" aria-hidden="true"></span>
					</button>
				<?php endfor; ?>
			</div>
			<input type="hidden" name="rating" value="">
		</div>
		<div class="kosher-comments-modal-actions">
			<button type="button" class="button button-primary" data-rating-choice="yes"><?php esc_html_e( 'Submit', 'kosher-comments' ); ?></button>
			<button type="button" class="button" data-rating-choice="no"><?php esc_html_e( 'No', 'kosher-comments' ); ?></button>
		</div>
	</div>
</div>

<div class="kosher-comments-modal kosher-comments-edit-rating-modal" hidden>
	<div class="kosher-comments-modal-backdrop" data-edit-rating-close></div>
	<div class="kosher-comments-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kosher-comments-edit-rating-title">
		<h3 id="kosher-comments-edit-rating-title"><?php esc_html_e( 'Edit your rating', 'kosher-comments' ); ?></h3>
		<p><?php esc_html_e( 'Choose a new star rating and save it.', 'kosher-comments' ); ?></p>
		<div class="kosher-comments-rating-picker kosher-comments-modal-rating-picker" data-rating-picker data-edit-rating-picker>
			<div class="kosher-comments-rating-buttons kayco-recipe-rating__stars">
				<?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?>
					<button type="button" class="kosher-comments-rating-button" data-rating="<?php echo esc_attr( $rating ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Set rating to %d', 'kosher-comments' ), $rating ) ); ?>">
						<span class="bi bi-star-fill" aria-hidden="true"></span>
					</button>
				<?php endfor; ?>
			</div>
			<input type="hidden" name="rating" value="">
		</div>
		<div class="kosher-comments-modal-actions">
			<button type="button" class="button" data-edit-rating-close><?php esc_html_e( 'Cancel', 'kosher-comments' ); ?></button>
			<button type="button" class="button button-primary" data-edit-rating-save><?php esc_html_e( 'Save Rating', 'kosher-comments' ); ?></button>
		</div>
	</div>
</div>

<?php if ( $include_report_modal ) : ?>
	<div class="kosher-comments-modal kosher-comments-report-modal" hidden>
		<div class="kosher-comments-modal-backdrop" data-report-close></div>
		<div class="kosher-comments-modal-panel kosher-comments-report-panel" role="dialog" aria-modal="true" aria-labelledby="kosher-comments-report-title">
			<p class="kosher-comments-summary-kicker"><?php esc_html_e( 'Moderation', 'kosher-comments' ); ?></p>
			<h3 id="kosher-comments-report-title"><?php esc_html_e( 'Report', 'kosher-comments' ); ?></h3>
			<form class="kosher-comments-report-form">
				<input type="hidden" name="report_type" value="">
				<input type="hidden" name="comment_id" value="">
				<input type="hidden" name="image_id" value="">
				<label class="kosher-comments-report-label" for="kosher-comments-report-subject"><?php esc_html_e( 'Subject', 'kosher-comments' ); ?></label>
				<input id="kosher-comments-report-subject" type="text" name="subject" maxlength="60" placeholder="<?php esc_attr_e( 'Briefly describe the issue', 'kosher-comments' ); ?>" required>
				<label class="kosher-comments-report-label" for="kosher-comments-report-reason"><?php esc_html_e( 'Comment', 'kosher-comments' ); ?></label>
				<textarea id="kosher-comments-report-reason" name="reason" rows="4" maxlength="140" placeholder="<?php esc_attr_e( 'Leave a short comment for the moderation team.', 'kosher-comments' ); ?>" required></textarea>
				<div class="kosher-comments-report-meta">
					<span class="kosher-comments-report-help"><?php esc_html_e( 'Maximum 140 characters.', 'kosher-comments' ); ?></span>
					<strong class="kosher-comments-report-count" data-report-count>0/140</strong>
				</div>
				<div class="kosher-comments-modal-actions">
					<button type="button" class="button" data-report-close><?php esc_html_e( 'Cancel', 'kosher-comments' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Send report', 'kosher-comments' ); ?></button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>

<div class="kosher-comments-modal kosher-comments-alert-modal" hidden>
	<div class="kosher-comments-modal-backdrop" data-alert-close></div>
	<div class="kosher-comments-modal-panel kosher-comments-alert-panel" role="dialog" aria-modal="true" aria-labelledby="kosher-comments-alert-title">
		<p class="kosher-comments-summary-kicker"><?php esc_html_e( 'Kosher Comments', 'kosher-comments' ); ?></p>
		<h3 id="kosher-comments-alert-title" class="kosher-comments-alert-title"></h3>
		<p class="kosher-comments-alert-message"></p>
		<div class="kosher-comments-modal-actions kosher-comments-alert-actions">
			<button type="button" class="button" data-alert-cancel hidden><?php esc_html_e( 'Cancel', 'kosher-comments' ); ?></button>
			<button type="button" class="button button-primary" data-alert-confirm><?php esc_html_e( 'Continue', 'kosher-comments' ); ?></button>
		</div>
	</div>
</div>

<div class="kosher-comments-submit-overlay" hidden>
	<div class="kosher-comments-submit-backdrop"></div>
	<div class="kosher-comments-submit-card" role="status" aria-live="polite" aria-atomic="true">
		<div class="kosher-comments-submit-spinner" aria-hidden="true"></div>
		<p class="kosher-comments-summary-kicker"><?php esc_html_e( 'Posting comment', 'kosher-comments' ); ?></p>
		<h3 class="kosher-comments-submit-title"><?php esc_html_e( 'Your comment will be posted in a moment...', 'kosher-comments' ); ?></h3>
		<p class="kosher-comments-submit-detail"></p>
	</div>
</div>

<?php if ( $include_photo_modal ) : ?>
	<div class="kosher-comments-photo-modal" hidden>
		<div class="kosher-comments-photo-backdrop" data-photo-close></div>
		<div class="kosher-comments-photo-frame" role="dialog" aria-modal="true">
			<button type="button" class="kosher-comments-photo-close" data-photo-close>&times;</button>
			<div class="kosher-comments-photo-heading">
				<button type="button" class="kosher-comments-photo-heading-back" data-photo-close>&lsaquo;</button>
				<strong><?php esc_html_e( 'All photos', 'kosher-comments' ); ?></strong>
			</div>
			<div class="kosher-comments-photo-stage">
				<button type="button" class="kosher-comments-photo-nav is-prev" data-photo-nav="prev">&#8249;</button>
				<img src="" alt="" class="kosher-comments-photo-image">
				<button type="button" class="kosher-comments-photo-nav is-next" data-photo-nav="next">&#8250;</button>
			</div>
			<aside class="kosher-comments-photo-sidebar">
				<div class="kosher-comments-photo-counter"></div>
				<div class="kosher-comments-photo-author">
					<div class="kosher-comments-photo-avatar-shell" aria-hidden="true">
						<div class="kosher-comments-photo-avatar-fallback"></div>
						<img src="" alt="" class="kosher-comments-photo-avatar">
					</div>
					<div>
						<strong class="kosher-comments-photo-name"></strong>
						<div class="kosher-comments-photo-rating"></div>
					</div>
				</div>
				<p class="kosher-comments-photo-excerpt"></p>
				<div class="kosher-comments-photo-thumbs"></div>
				<div class="kosher-comments-photo-actions">
					<button type="button" class="kosher-comments-share kosher-comments-photo-share"><?php esc_html_e( 'Copy & share', 'kosher-comments' ); ?></button>
					<?php if ( is_user_logged_in() ) : ?>
						<button type="button" class="kosher-comments-report-button kosher-comments-photo-report"><?php esc_html_e( 'Report image', 'kosher-comments' ); ?></button>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</div>
<?php endif; ?>
