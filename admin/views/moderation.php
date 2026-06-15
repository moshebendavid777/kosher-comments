<div class="wrap kosher-comments-admin">
	<div class="kosher-comments-admin-hero">
		<div>
			<p class="kosher-comments-admin-kicker"><?php esc_html_e( 'Moderation', 'kosher-comments' ); ?></p>
			<h1><?php esc_html_e( 'Kosher Comments Moderation', 'kosher-comments' ); ?></h1>
			<p><?php esc_html_e( 'Review reports, manage strike history, and control permanent bans from one branded moderation center.', 'kosher-comments' ); ?></p>
		</div>
	</div>

	<div class="kosher-comments-admin-panel">
		<div class="kosher-comments-admin-panel-head">
			<h2><?php esc_html_e( 'Open Moderation Queue', 'kosher-comments' ); ?></h2>
		</div>
		<?php if ( empty( $reports ) ) : ?>
			<p class="kosher-comments-admin-empty"><?php esc_html_e( 'No open reports remain in the moderation queue.', 'kosher-comments' ); ?></p>
		<?php else : ?>
			<table class="widefat striped kosher-comments-admin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Reporter', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Preview', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Created', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'kosher-comments' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $reports as $report ) : ?>
						<tr>
							<td><span class="kosher-comments-admin-badge"><?php echo esc_html( ucfirst( $report->report_type ) ); ?></span></td>
							<td>
								<strong><?php echo esc_html( $report->reporter_name ?: __( 'Unknown user', 'kosher-comments' ) ); ?></strong><br>
								<small><?php echo esc_html( $report->reporter_email ); ?></small>
							</td>
							<td class="kosher-comments-admin-preview">
								<?php if ( 'image' === $report->report_type && ! empty( $report->image_url ) ) : ?>
									<img src="<?php echo esc_url( $report->image_url ); ?>" alt="" class="kosher-comments-admin-thumb">
								<?php endif; ?>
								<?php if ( ! empty( $report->comment_content ) ) : ?>
									<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $report->comment_content ), 18, '...' ) ); ?>
								<?php else : ?>
									<?php esc_html_e( 'No preview available', 'kosher-comments' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $report->reason ); ?></td>
							<td><?php echo esc_html( $report->created_at ); ?></td>
							<td>
								<div class="kosher-comments-admin-actions">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'kosher_comments_update_report_nonce' ); ?>
										<input type="hidden" name="action" value="kosher_comments_update_report">
										<input type="hidden" name="report_id" value="<?php echo esc_attr( $report->id ); ?>">
										<input type="hidden" name="status" value="dismissed">
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Dismiss Report', 'kosher-comments' ); ?></button>
									</form>
									<?php if ( ! empty( $report->comment_id ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'kosher_comments_remove_reported_comment_nonce' ); ?>
											<input type="hidden" name="action" value="kosher_comments_remove_reported_comment">
											<input type="hidden" name="report_id" value="<?php echo esc_attr( $report->id ); ?>">
											<input type="hidden" name="comment_id" value="<?php echo esc_attr( $report->comment_id ); ?>">
											<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Remove Comment', 'kosher-comments' ); ?></button>
										</form>
									<?php endif; ?>
									<?php if ( ! empty( $report->image_id ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'kosher_comments_remove_reported_image_nonce' ); ?>
											<input type="hidden" name="action" value="kosher_comments_remove_reported_image">
											<input type="hidden" name="report_id" value="<?php echo esc_attr( $report->id ); ?>">
											<input type="hidden" name="image_id" value="<?php echo esc_attr( $report->image_id ); ?>">
											<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Remove Image', 'kosher-comments' ); ?></button>
										</form>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="kosher-comments-admin-panel">
		<div class="kosher-comments-admin-panel-head">
			<h2><?php esc_html_e( 'User Strikes', 'kosher-comments' ); ?></h2>
		</div>
		<?php if ( empty( $strike_rows ) ) : ?>
			<p class="kosher-comments-admin-empty"><?php esc_html_e( 'No users currently have strikes or account locks.', 'kosher-comments' ); ?></p>
		<?php else : ?>
			<table class="widefat striped kosher-comments-admin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Email', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Strikes', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Locked', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Last Updated', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'kosher-comments' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $strike_rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->display_name ?: __( 'Unknown user', 'kosher-comments' ) ); ?></td>
							<td><?php echo esc_html( $row->user_email ); ?></td>
							<td><strong><?php echo esc_html( (int) $row->strikes ); ?></strong></td>
							<td><span class="kosher-comments-admin-badge <?php echo ! empty( $row->is_locked ) ? 'is-status-resolved' : 'is-status-dismissed'; ?>"><?php echo esc_html( ! empty( $row->is_locked ) ? __( 'Yes', 'kosher-comments' ) : __( 'No', 'kosher-comments' ) ); ?></span></td>
							<td><?php echo esc_html( $row->updated_at ); ?></td>
							<td>
								<div class="kosher-comments-admin-actions">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'kosher_comments_reset_strikes_nonce' ); ?>
										<input type="hidden" name="action" value="kosher_comments_reset_strikes">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $row->user_id ); ?>">
										<button type="submit" class="button"><?php esc_html_e( 'Reset Strikes', 'kosher-comments' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'kosher_comments_unlock_user_nonce' ); ?>
										<input type="hidden" name="action" value="kosher_comments_unlock_user">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $row->user_id ); ?>">
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Unlock User', 'kosher-comments' ); ?></button>
									</form>
									<?php if ( ! empty( $row->user_email ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'kosher_comments_ban_email_nonce' ); ?>
											<input type="hidden" name="action" value="kosher_comments_ban_email">
											<input type="hidden" name="email" value="<?php echo esc_attr( $row->user_email ); ?>">
											<input type="hidden" name="reason" value="<?php esc_attr_e( 'Banned from moderation screen', 'kosher-comments' ); ?>">
											<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Ban Email', 'kosher-comments' ); ?></button>
										</form>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="kosher-comments-admin-panel">
		<div class="kosher-comments-admin-panel-head">
			<h2><?php esc_html_e( 'Permanent Email Ban List', 'kosher-comments' ); ?></h2>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kosher-comments-admin-inline-form">
			<?php wp_nonce_field( 'kosher_comments_ban_email_nonce' ); ?>
			<input type="hidden" name="action" value="kosher_comments_ban_email">
			<input type="email" name="email" class="regular-text" placeholder="<?php esc_attr_e( 'user@example.com', 'kosher-comments' ); ?>" required>
			<input type="text" name="reason" class="regular-text" placeholder="<?php esc_attr_e( 'Reason for the ban', 'kosher-comments' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Ban Email', 'kosher-comments' ); ?></button>
		</form>

		<?php if ( empty( $banned_emails ) ) : ?>
			<p class="kosher-comments-admin-empty"><?php esc_html_e( 'No email addresses are permanently banned yet.', 'kosher-comments' ); ?></p>
		<?php else : ?>
			<table class="widefat striped kosher-comments-admin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Created', 'kosher-comments' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'kosher-comments' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $banned_emails as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->email ); ?></td>
							<td><?php echo esc_html( $row->reason ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'kosher_comments_unban_email_nonce' ); ?>
									<input type="hidden" name="action" value="kosher_comments_unban_email">
									<input type="hidden" name="email" value="<?php echo esc_attr( $row->email ); ?>">
									<button type="submit" class="button"><?php esc_html_e( 'Remove Ban', 'kosher-comments' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
