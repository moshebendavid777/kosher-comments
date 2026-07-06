<?php
$is_highlighted = (int) $comment->id === (int) $target_comment_id;
$can_report     = is_user_logged_in();
?>
<article class="kosher-comment<?php echo $is_highlighted ? ' is-highlighted' : ''; ?>" id="kosher-comment-<?php echo esc_attr( $comment->id ); ?>" data-comment-id="<?php echo esc_attr( $comment->id ); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
		<div class="kosher-comment-card">
			<div class="kosher-comment-header">
				<div class="kosher-comment-author">
					<div class="kosher-comment-avatar-shell" aria-hidden="true">
						<div class="kosher-comment-avatar is-fallback"><?php echo esc_html( $comment->avatar_initials ); ?></div>
						<?php if ( ! empty( $comment->avatar_url ) ) : ?>
							<img class="kosher-comment-avatar" src="<?php echo esc_url( $comment->avatar_url ); ?>" alt="" loading="lazy" onerror="this.hidden=true;">
						<?php endif; ?>
					</div>
					<div>
						<div class="kosher-comment-author-line">
							<strong><?php echo esc_html( $comment->author_name ); ?></strong>
							<?php if ( ! empty( $comment->is_staff ) ) : ?>
								<span class="kosher-comment-staff-badge"><i class="bi bi-shield-check" aria-hidden="true"></i><?php esc_html_e( 'Kosher.com Team', 'kosher-comments' ); ?></span>
							<?php endif; ?>
						<span>&bull;</span>
						<time datetime="<?php echo esc_attr( mysql2date( 'c', $comment->created_at ) ); ?>"><?php echo esc_html( human_time_diff( strtotime( $comment->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'kosher-comments' ) ); ?></time>
					</div>
					<div class="kosher-comment-meta">
						<?php if ( ! empty( $comment->parent_id ) && ! empty( $comment->reply_to_name ) ) : ?>
							<span><?php echo esc_html( sprintf( __( 'Reply to %s', 'kosher-comments' ), $comment->reply_to_name ) ); ?></span>
						<?php elseif ( ! empty( $comment->parent_id ) ) : ?>
							<span><?php esc_html_e( 'Reply', 'kosher-comments' ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $comment->rating ) ) : ?>
							<?php echo wp_kses_post( Kosher_Comments_Public::render_rating_stars( $comment->rating ) ); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="kosher-comment-top-actions">
				<button type="button" class="kosher-comments-share" data-share-url="<?php echo esc_url( $comment->share_url ); ?>"><?php esc_html_e( 'Copy & share', 'kosher-comments' ); ?></button>
				<?php if ( ! empty( $comment->can_edit ) ) : ?>
					<button type="button" class="kosher-comments-admin-action" data-edit-comment="<?php echo esc_attr( $comment->id ); ?>"><?php echo $comment->can_moderate ? esc_html__( 'Edit', 'kosher-comments' ) : esc_html__( 'Edit Rating', 'kosher-comments' ); ?></button>
				<?php endif; ?>
				<?php if ( $comment->can_moderate ) : ?>
					<button type="button" class="kosher-comments-admin-action is-danger" data-delete-comment="<?php echo esc_attr( $comment->id ); ?>"><?php esc_html_e( 'Delete', 'kosher-comments' ); ?></button>
				<?php elseif ( $can_report && empty( $comment->can_edit ) ) : ?>
					<button type="button" class="kosher-comments-report-button" data-report-type="comment" data-comment-id="<?php echo esc_attr( $comment->id ); ?>"><?php esc_html_e( 'Report', 'kosher-comments' ); ?></button>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $comment->is_question ) ) : ?>
			<div class="kosher-comment-question-tag"><?php esc_html_e( 'Marked as Question', 'kosher-comments' ); ?></div>
		<?php endif; ?>

		<div class="kosher-comment-body" data-comment-body>
			<?php echo wp_kses_post( wpautop( $comment->content ) ); ?>
		</div>

		<?php if ( ! empty( $comment->images ) ) : ?>
			<div class="kosher-comment-images" data-photo-collection data-photo-collection-label="<?php echo esc_attr( sprintf( __( 'Photos from %s', 'kosher-comments' ), $comment->author_name ) ); ?>">
				<?php foreach ( $comment->images as $index => $image ) : ?>
					<button
						type="button"
						class="kosher-comment-image-thumb"
						data-open-photo-modal
						data-photo-index="<?php echo esc_attr( $index ); ?>"
						data-image-id="<?php echo esc_attr( $image['image_id'] ); ?>"
						data-comment-id="<?php echo esc_attr( $comment->id ); ?>"
						data-photo-url="<?php echo esc_url( $image['url'] ); ?>"
						data-photo-thumb="<?php echo esc_url( $image['thumb'] ?: $image['url'] ); ?>"
						data-author-name="<?php echo esc_attr( $comment->author_name ); ?>"
						data-avatar-url="<?php echo esc_url( $comment->avatar_url ); ?>"
						data-rating="<?php echo esc_attr( (int) $comment->rating ); ?>"
						data-excerpt="<?php echo esc_attr( $comment->excerpt ); ?>"
						data-share-url="<?php echo esc_url( $comment->share_url ); ?>"
					>
						<img src="<?php echo esc_url( $image['thumb'] ?: $image['url'] ); ?>" alt="">
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form class="kosher-comments-edit-form" data-edit-form="<?php echo esc_attr( $comment->id ); ?>" hidden>
			<?php if ( $comment->can_moderate ) : ?>
				<textarea id="kosher-comments-edit-text-<?php echo esc_attr( $comment->id ); ?>" class="kosher-comments-editor-field" name="comment_text" rows="4"><?php echo esc_textarea( $comment->content ); ?></textarea>
			<?php endif; ?>
			<?php if ( empty( $comment->parent_id ) && ! empty( $comment->rating ) ) : ?>
				<div class="kosher-comments-rating-picker kosher-comments-edit-rating-picker" data-rating-picker>
					<div class="kosher-comments-rating-buttons kayco-recipe-rating__stars">
						<?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?>
							<button type="button" class="kosher-comments-rating-button<?php echo $rating <= (int) $comment->rating ? ' is-active' : ''; ?>" data-rating="<?php echo esc_attr( $rating ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Set rating to %d', 'kosher-comments' ), $rating ) ); ?>">
								<span class="bi bi-star-fill" aria-hidden="true"></span>
							</button>
						<?php endfor; ?>
					</div>
					<input type="hidden" name="rating" value="<?php echo esc_attr( (int) $comment->rating ); ?>">
				</div>
			<?php endif; ?>
			<div class="kosher-comments-inline-buttons">
				<button type="button" class="kosher-comments-cancel-edit"><?php esc_html_e( 'Cancel', 'kosher-comments' ); ?></button>
				<button type="submit" class="kosher-comments-submit" data-save-comment="<?php echo esc_attr( $comment->id ); ?>"><?php esc_html_e( 'Save', 'kosher-comments' ); ?></button>
			</div>
		</form>

		<div class="kosher-comment-actions">
			<div class="kosher-comment-votes">
				<button type="button" class="kosher-comments-vote<?php echo 'like' === $comment->user_vote ? ' is-active' : ''; ?>" data-vote-type="like" data-comment-id="<?php echo esc_attr( $comment->id ); ?>">
					<span class="kosher-comments-vote-count"><?php echo esc_html( (int) $comment->like_count ); ?></span>
					<i class="kosher-comments-vote-icon bi <?php echo 'like' === $comment->user_vote ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up'; ?>" aria-hidden="true"></i>
				</button>
				<button type="button" class="kosher-comments-vote<?php echo 'dislike' === $comment->user_vote ? ' is-active' : ''; ?>" data-vote-type="dislike" data-comment-id="<?php echo esc_attr( $comment->id ); ?>">
					<span class="kosher-comments-vote-count"><?php echo esc_html( (int) $comment->dislike_count ); ?></span>
					<i class="kosher-comments-vote-icon bi <?php echo 'dislike' === $comment->user_vote ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-down'; ?>" aria-hidden="true"></i>
				</button>
			</div>
			<div class="kosher-comment-secondary-actions">
				<button type="button" class="kosher-comments-reply-toggle" data-comment-id="<?php echo esc_attr( $comment->id ); ?>"><?php esc_html_e( 'Reply', 'kosher-comments' ); ?></button>
				<?php if ( ! empty( $comment->replies ) ) : ?>
					<button type="button" class="kosher-comments-toggle-replies" data-toggle-replies="<?php echo esc_attr( $comment->id ); ?>"><?php esc_html_e( 'Hide Replies', 'kosher-comments' ); ?></button>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( is_user_logged_in() ) : ?>
			<form class="kosher-comments-form kosher-comments-reply-form" data-parent-id="<?php echo esc_attr( $comment->id ); ?>" hidden>
				<textarea
					id="kosher-comments-reply-text-<?php echo esc_attr( $comment->id ); ?>"
					class="kosher-comments-editor-field"
					name="comment_text"
					rows="3"
					placeholder="<?php esc_attr_e( 'Write a reply...', 'kosher-comments' ); ?>"
					required
				></textarea>
				<div class="kosher-comments-inline-actions">
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
					<div class="kosher-comments-inline-buttons">
						<button type="button" class="kosher-comments-cancel-reply"><?php esc_html_e( 'Cancel', 'kosher-comments' ); ?></button>
						<button type="submit" class="kosher-comments-submit"><?php esc_html_e( 'Post Reply', 'kosher-comments' ); ?></button>
					</div>
				</div>
			</form>
		<?php endif; ?>
	</div>

	<div class="kosher-comments-replies" data-replies-container="<?php echo esc_attr( $comment->id ); ?>">
		<?php
		if ( ! empty( $comment->replies ) ) {
			foreach ( $comment->replies as $reply ) {
				echo Kosher_Comments_Public::render_single_comment( $reply, $depth + 1, $target_comment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		?>
	</div>
</article>
