<?php
/**
 * Admin-facing configuration and reports.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Admin {

	/**
	 * Comments service.
	 *
	 * @var Kosher_Comments_Comments
	 */
	protected $comments;

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
	 * @param Kosher_Comments_Comments  $comments Comments service.
	 * @param Kosher_Comments_Strikes   $strikes Strikes service.
	 * @param Kosher_Comments_Analytics $analytics Analytics service.
	 */
	public function __construct( Kosher_Comments_Comments $comments, Kosher_Comments_Strikes $strikes, Kosher_Comments_Analytics $analytics ) {
		$this->comments  = $comments;
		$this->strikes   = $strikes;
		$this->analytics = $analytics;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'admin_post_kosher_comments_reset_strikes', array( $this, 'handle_reset_strikes' ) );
		add_action( 'admin_post_kosher_comments_unlock_user', array( $this, 'handle_unlock_user' ) );
		add_action( 'admin_post_kosher_comments_ban_email', array( $this, 'handle_ban_email' ) );
		add_action( 'admin_post_kosher_comments_unban_email', array( $this, 'handle_unban_email' ) );
		add_action( 'admin_post_kosher_comments_update_report', array( $this, 'handle_update_report' ) );
		add_action( 'admin_post_kosher_comments_remove_reported_comment', array( $this, 'handle_remove_reported_comment' ) );
		add_action( 'admin_post_kosher_comments_remove_reported_image', array( $this, 'handle_remove_reported_image' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Kosher Comments', 'kosher-comments' ),
			__( 'Kosher Comments', 'kosher-comments' ),
			'manage_options',
			'kosher-comments',
			array( $this, 'render_moderation_page' ),
			'dashicons-format-chat',
			60
		);

		add_submenu_page(
			'kosher-comments',
			__( 'Moderation', 'kosher-comments' ),
			__( 'Moderation', 'kosher-comments' ),
			'manage_options',
			'kosher-comments',
			array( $this, 'render_moderation_page' )
		);

		add_submenu_page(
			'kosher-comments',
			__( 'Analytics', 'kosher-comments' ),
			__( 'Analytics', 'kosher-comments' ),
			'manage_options',
			'kosher-comments-analytics',
			array( $this, 'render_analytics_page' )
		);

		add_submenu_page(
			'kosher-comments',
			__( 'Settings', 'kosher-comments' ),
			__( 'Settings', 'kosher-comments' ),
			'manage_options',
			'kosher-comments-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'kosher_comments_settings_group',
			'kosher_comments_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Enqueue branded admin styles.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed_hooks = array(
			'toplevel_page_kosher-comments',
			'kosher-comments_page_kosher-comments-analytics',
			'kosher-comments_page_kosher-comments-settings',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'kosher-comments-admin',
			KOSHER_COMMENTS_URL . 'assets/css/kosher-comments-admin.css',
			array(),
			KOSHER_COMMENTS_VERSION
		);

		if ( 'kosher-comments_page_kosher-comments-analytics' === $hook_suffix ) {
			wp_enqueue_script(
				'kosher-comments-admin',
				KOSHER_COMMENTS_URL . 'assets/js/kosher-comments-admin.js',
				array(),
				KOSHER_COMMENTS_VERSION,
				true
			);
		}
	}

	/**
	 * Sanitize saved settings.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $settings ) {
		return array(
			'openai_api_key'         => sanitize_text_field( (string) ( $settings['openai_api_key'] ?? '' ) ),
			'moderation_model'       => sanitize_text_field( (string) ( $settings['moderation_model'] ?? 'gpt-4.1-mini' ) ),
			'moderation_enabled'     => ! empty( $settings['moderation_enabled'] ) ? 'yes' : 'no',
			'comments_per_page'      => max( 1, absint( $settings['comments_per_page'] ?? 5 ) ),
			'max_images_per_comment' => max( 0, absint( $settings['max_images_per_comment'] ?? 5 ) ),
			'max_image_size_mb'      => max( 1, absint( $settings['max_image_size_mb'] ?? 5 ) ),
			'lock_threshold'         => max( 1, absint( $settings['lock_threshold'] ?? 3 ) ),
			'notification_email'     => sanitize_email( (string) ( $settings['notification_email'] ?? 'info@kosher.com' ) ),
		);
	}

	/**
	 * Register analytics metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'kosher-comments-analytics',
				__( 'Comments Analytics', 'kosher-comments' ),
				array( $this, 'render_post_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the moderation page.
	 *
	 * @return void
	 */
	public function render_moderation_page() {
		$strike_rows   = $this->strikes->get_strike_rows();
		$banned_emails = $this->strikes->get_banned_emails();
		$reports       = $this->comments->get_reports();

		include KOSHER_COMMENTS_PATH . 'admin/views/moderation.php';
	}

	/**
	 * Render the analytics page.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		$site_summary = $this->analytics->get_sitewide_summary();

		include KOSHER_COMMENTS_PATH . 'admin/views/analytics.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$settings = kosher_comments_get_settings();
		?>
		<div class="wrap kosher-comments-admin">
			<div class="kosher-comments-admin-hero">
				<div>
					<p class="kosher-comments-admin-kicker"><?php esc_html_e( 'Configuration', 'kosher-comments' ); ?></p>
					<h1><?php esc_html_e( 'Kosher Comments Settings', 'kosher-comments' ); ?></h1>
					<p><?php esc_html_e( 'Manage AI moderation, posting limits, notifications, and comment experience defaults.', 'kosher-comments' ); ?></p>
				</div>
			</div>

			<div class="kosher-comments-admin-panel">
				<form method="post" action="options.php" class="kosher-comments-admin-form">
				<?php settings_fields( 'kosher_comments_settings_group' ); ?>
				<table class="form-table kosher-comments-admin-table-form" role="presentation">
					<tr>
						<th scope="row"><label for="kosher_comments_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_openai_api_key" name="kosher_comments_settings[openai_api_key]" type="password" class="regular-text" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_moderation_model"><?php esc_html_e( 'Moderation Model', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_moderation_model" name="kosher_comments_settings[moderation_model]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['moderation_model'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable AI Moderation', 'kosher-comments' ); ?></th>
						<td><label><input name="kosher_comments_settings[moderation_enabled]" type="checkbox" value="yes" <?php checked( 'yes', $settings['moderation_enabled'] ); ?>> <?php esc_html_e( 'Block toxic comments before saving them', 'kosher-comments' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_lock_threshold"><?php esc_html_e( 'Lock Threshold', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_lock_threshold" name="kosher_comments_settings[lock_threshold]" type="number" min="1" class="small-text" value="<?php echo esc_attr( $settings['lock_threshold'] ); ?>"> <p class="description"><?php esc_html_e( 'Users are locked after this many strikes.', 'kosher-comments' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_comments_per_page"><?php esc_html_e( 'Top-Level Comments Per Page', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_comments_per_page" name="kosher_comments_settings[comments_per_page]" type="number" min="1" class="small-text" value="<?php echo esc_attr( $settings['comments_per_page'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_max_images_per_comment"><?php esc_html_e( 'Max Images Per Comment', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_max_images_per_comment" name="kosher_comments_settings[max_images_per_comment]" type="number" min="0" class="small-text" value="<?php echo esc_attr( $settings['max_images_per_comment'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_max_image_size_mb"><?php esc_html_e( 'Max Image Size (MB)', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_max_image_size_mb" name="kosher_comments_settings[max_image_size_mb]" type="number" min="1" class="small-text" value="<?php echo esc_attr( $settings['max_image_size_mb'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kosher_comments_notification_email"><?php esc_html_e( 'Fallback Notification Email', 'kosher-comments' ); ?></label></th>
						<td><input id="kosher_comments_notification_email" name="kosher_comments_settings[notification_email]" type="email" class="regular-text" value="<?php echo esc_attr( $settings['notification_email'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the analytics metabox on the post editor.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_post_metabox( $post ) {
		$summary = $this->analytics->get_post_summary( $post->ID );
		?>
		<div class="kosher-comments-metabox">
			<div class="kosher-comments-metabox-stats">
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Total comments', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['total_comments'] ); ?></strong></div>
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Average rating', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['average_rating'] ); ?></strong></div>
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Likes / Dislikes', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['likes'] . ' / ' . $summary['dislikes'] ); ?></strong></div>
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Replies', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['replies'] ); ?></strong></div>
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Questions', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['questions'] ); ?></strong></div>
				<div class="kosher-comments-metabox-stat"><span><?php esc_html_e( 'Blocked', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $summary['blocked_comments'] ); ?></strong></div>
			</div>
			<p class="kosher-comments-metabox-title"><strong><?php esc_html_e( 'Rating distribution', 'kosher-comments' ); ?></strong></p>
			<ul class="kosher-comments-metabox-list">
			<?php foreach ( $summary['rating_distribution'] as $rating => $count ) : ?>
				<li><?php echo esc_html( sprintf( __( '%1$d stars: %2$d', 'kosher-comments' ), $rating, $count ) ); ?></li>
			<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $summary['location_distribution'] ) ) : ?>
			<p class="kosher-comments-metabox-title"><strong><?php esc_html_e( 'Top locations', 'kosher-comments' ); ?></strong></p>
			<ul class="kosher-comments-metabox-list">
				<?php foreach ( array_slice( $summary['location_distribution'], 0, 3 ) as $row ) : ?>
					<li><?php echo esc_html( sprintf( __( '%1$s: %2$d', 'kosher-comments' ), $row['country'], $row['total'] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php if ( ! empty( $summary['time_of_day'] ) ) : ?>
			<p class="kosher-comments-metabox-title"><strong><?php esc_html_e( 'Activity by hour', 'kosher-comments' ); ?></strong></p>
			<ul class="kosher-comments-metabox-list">
				<?php foreach ( array_slice( $summary['time_of_day'], 0, 4 ) as $row ) : ?>
					<li><?php echo esc_html( sprintf( __( '%1$s:00 - %2$d', 'kosher-comments' ), $row['hour_of_day'], $row['total'] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Reset strikes action.
	 *
	 * @return void
	 */
	public function handle_reset_strikes() {
		$this->handle_user_action( 'kosher_comments_reset_strikes_nonce', 'reset' );
	}

	/**
	 * Unlock user action.
	 *
	 * @return void
	 */
	public function handle_unlock_user() {
		$this->handle_user_action( 'kosher_comments_unlock_user_nonce', 'unlock' );
	}

	/**
	 * Ban email action.
	 *
	 * @return void
	 */
	public function handle_ban_email() {
		$this->assert_manage_options();
		check_admin_referer( 'kosher_comments_ban_email_nonce' );

		$email  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( $email ) {
			$this->strikes->ban_email( $email, $reason );
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => 'email_banned' ) ) );
		exit;
	}

	/**
	 * Unban email action.
	 *
	 * @return void
	 */
	public function handle_unban_email() {
		$this->assert_manage_options();
		check_admin_referer( 'kosher_comments_unban_email_nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( $email ) {
			$this->strikes->unban_email( $email );
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => 'email_unbanned' ) ) );
		exit;
	}

	/**
	 * Update a report status.
	 *
	 * @return void
	 */
	public function handle_update_report() {
		$this->assert_manage_options();
		check_admin_referer( 'kosher_comments_update_report_nonce' );

		$report_id = absint( $_POST['report_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $_POST['status'] ?? 'resolved' ) );
		$notice    = 'report_resolved';

		if ( in_array( $status, array( 'resolved', 'dismissed' ), true ) ) {
			$this->comments->update_report_status( $report_id, $status );
			$notice = 'dismissed' === $status ? 'report_dismissed' : 'report_resolved';
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => $notice ) ) );
		exit;
	}

	/**
	 * Remove a reported comment.
	 *
	 * @return void
	 */
	public function handle_remove_reported_comment() {
		$this->assert_manage_options();
		check_admin_referer( 'kosher_comments_remove_reported_comment_nonce' );

		$report_id  = absint( $_POST['report_id'] ?? 0 );
		$comment_id = absint( $_POST['comment_id'] ?? 0 );

		if ( $comment_id ) {
			$this->comments->delete_comment( $comment_id );
		}

		if ( $report_id ) {
			$this->comments->update_report_status( $report_id, 'resolved' );
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => 'comment_removed' ) ) );
		exit;
	}

	/**
	 * Remove a reported image.
	 *
	 * @return void
	 */
	public function handle_remove_reported_image() {
		$this->assert_manage_options();
		check_admin_referer( 'kosher_comments_remove_reported_image_nonce' );

		$report_id = absint( $_POST['report_id'] ?? 0 );
		$image_id  = absint( $_POST['image_id'] ?? 0 );

		if ( $image_id ) {
			$this->comments->delete_image( $image_id );
		}

		if ( $report_id ) {
			$this->comments->update_report_status( $report_id, 'resolved' );
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => 'image_removed' ) ) );
		exit;
	}

	/**
	 * Render status notices for admin actions.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		if ( empty( $_GET['kosher_comments_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['kosher_comments_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = array(
			'reset'          => __( 'User strikes were reset.', 'kosher-comments' ),
			'unlock'         => __( 'The user has been unlocked.', 'kosher-comments' ),
			'email_banned'   => __( 'The email was added to the permanent ban list.', 'kosher-comments' ),
			'email_unbanned' => __( 'The email was removed from the ban list.', 'kosher-comments' ),
			'report_resolved'=> __( 'The report was updated.', 'kosher-comments' ),
			'report_dismissed' => __( 'The report was dismissed and removed from the moderation queue.', 'kosher-comments' ),
			'comment_removed'=> __( 'The reported comment was removed.', 'kosher-comments' ),
			'image_removed'  => __( 'The reported image was removed.', 'kosher-comments' ),
		);

		if ( empty( $map[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $map[ $notice ] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle a user moderation action.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $action Action name.
	 * @return void
	 */
	protected function handle_user_action( $nonce_action, $action ) {
		$this->assert_manage_options();
		check_admin_referer( $nonce_action );

		$user_id = absint( $_POST['user_id'] ?? 0 );

		if ( 'unlock' === $action ) {
			$this->strikes->unlock_user( $user_id );
		} else {
			$this->strikes->reset_strikes( $user_id );
		}

		wp_safe_redirect( $this->get_admin_page_url( 'kosher-comments', array( 'kosher_comments_notice' => $action ) ) );
		exit;
	}

	/**
	 * Build an admin page URL.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra args.
	 * @return string
	 */
	protected function get_admin_page_url( $page, $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
	}

	/**
	 * Assert current user can manage plugin settings.
	 *
	 * @return void
	 */
	protected function assert_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Kosher Comments.', 'kosher-comments' ) );
		}
	}
}
