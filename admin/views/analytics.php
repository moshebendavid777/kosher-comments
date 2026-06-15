<?php
$total_comments     = (int) $site_summary['total_comments'];
$ratings_count      = (int) $site_summary['ratings_count'];
$average_rating     = (float) $site_summary['average_rating'];
$likes              = (int) $site_summary['likes'];
$dislikes           = (int) $site_summary['dislikes'];
$replies            = (int) $site_summary['replies'];
$questions          = (int) $site_summary['questions'];
$blocked_comments   = (int) $site_summary['blocked_comments'];
$total_interactions = $likes + $dislikes + $replies;
$sentiment_total    = max( 1, $likes + $dislikes );
$approval_ratio     = (int) round( ( $likes / $sentiment_total ) * 100 );
$safe_publish_rate  = (int) round( ( $total_comments / max( 1, $total_comments + $blocked_comments ) ) * 100 );
$reply_rate         = (int) round( ( $replies / max( 1, $total_comments ) ) * 100 );
$question_rate      = (int) round( ( $questions / max( 1, $total_comments ) ) * 100 );
$review_coverage    = (int) round( ( $ratings_count / max( 1, $total_comments ) ) * 100 );
$top_location       = ! empty( $site_summary['location_distribution'][0]['country'] ) ? $site_summary['location_distribution'][0]['country'] : __( 'No data', 'kosher-comments' );
$peak_hour_label    = __( 'No data', 'kosher-comments' );
$peak_hour_total    = 0;
$top_posts_max      = 0;
$recent_days        = ! empty( $site_summary['recent_days'] ) ? $site_summary['recent_days'] : array();
$weekday_data       = ! empty( $site_summary['weekday_distribution'] ) ? $site_summary['weekday_distribution'] : array();
$growth_label       = __( 'Stable', 'kosher-comments' );
$growth_value       = 0;
$weekday_peak_label = __( 'No data', 'kosher-comments' );
$weekday_peak_total = 0;

if ( ! empty( $site_summary['time_of_day'] ) ) {
	foreach ( $site_summary['time_of_day'] as $row ) {
		if ( (int) $row['total'] > $peak_hour_total ) {
			$peak_hour_total = (int) $row['total'];
			$peak_hour_label = sprintf( __( '%s:00', 'kosher-comments' ), str_pad( (string) $row['hour_of_day'], 2, '0', STR_PAD_LEFT ) );
		}
	}
}

if ( ! empty( $site_summary['top_posts'] ) ) {
	foreach ( $site_summary['top_posts'] as $row ) {
		$top_posts_max = max( $top_posts_max, (int) $row->total_comments );
	}
}

if ( 14 === count( $recent_days ) ) {
	$first_window_total = 0;
	$last_window_total  = 0;

	foreach ( array_slice( $recent_days, 0, 7 ) as $row ) {
		$first_window_total += (int) $row['total'];
	}

	foreach ( array_slice( $recent_days, 7, 7 ) as $row ) {
		$last_window_total += (int) $row['total'];
	}

	$growth_value = (int) round( ( ( $last_window_total - $first_window_total ) / max( 1, $first_window_total ) ) * 100 );

	if ( $growth_value > 6 ) {
		$growth_label = __( 'Upward trend', 'kosher-comments' );
	} elseif ( $growth_value < -6 ) {
		$growth_label = __( 'Cooling down', 'kosher-comments' );
	}
}

if ( ! empty( $weekday_data ) ) {
	foreach ( $weekday_data as $row ) {
		if ( (int) $row['total'] > $weekday_peak_total ) {
			$weekday_peak_total = (int) $row['total'];
			$weekday_peak_label = (string) $row['label'];
		}
	}
}

$dashboard_data = array(
	'ratingDistribution' => array_map( 'intval', $site_summary['rating_distribution'] ),
	'timeOfDay'          => array_values( $site_summary['time_of_day'] ),
	'recentDays'         => array_values( $recent_days ),
	'weekdayDistribution'=> array_values( $weekday_data ),
	'locations'          => array_values( $site_summary['location_distribution'] ),
	'likes'              => $likes,
	'dislikes'           => $dislikes,
	'replies'            => $replies,
	'questions'          => $questions,
	'blocked'            => $blocked_comments,
);
?>
<div class="wrap kosher-comments-admin kosher-comments-analytics-dashboard" data-kosher-comments-dashboard>
	<div class="kosher-comments-admin-hero kosher-comments-admin-hero-analytics">
		<div>
			<p class="kosher-comments-admin-kicker"><?php esc_html_e( 'Analytics', 'kosher-comments' ); ?></p>
			<h1><?php esc_html_e( 'Kosher Comments Analytics', 'kosher-comments' ); ?></h1>
			<p><?php esc_html_e( 'An executive dashboard for engagement quality, review behavior, moderation pressure, and audience momentum across your site.', 'kosher-comments' ); ?></p>
		</div>
		<div class="kosher-comments-admin-hero-badge">
			<span><?php esc_html_e( 'Live dashboard', 'kosher-comments' ); ?></span>
			<strong><?php echo esc_html( current_time( get_option( 'date_format' ) ) ); ?></strong>
		</div>
	</div>

	<div class="kosher-comments-admin-kpi-grid">
		<div class="kosher-comments-admin-kpi-card is-featured">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( 'Total comments', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-value" data-count-up="<?php echo esc_attr( $total_comments ); ?>">0</strong>
			<p><?php esc_html_e( 'Approved comments currently shaping the conversation footprint.', 'kosher-comments' ); ?></p>
		</div>
		<div class="kosher-comments-admin-kpi-card">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( 'Average rating', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-value" data-count-up="<?php echo esc_attr( $average_rating ); ?>" data-decimals="2">0</strong>
			<p><?php echo esc_html( sprintf( __( '%d rating submissions', 'kosher-comments' ), $ratings_count ) ); ?></p>
		</div>
		<div class="kosher-comments-admin-kpi-card">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( 'Engagement actions', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-value" data-count-up="<?php echo esc_attr( $total_interactions ); ?>">0</strong>
			<p><?php esc_html_e( 'Combined likes, dislikes, and replies generated by readers.', 'kosher-comments' ); ?></p>
		</div>
		<div class="kosher-comments-admin-kpi-card">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( 'Positive reaction rate', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-value" data-count-up="<?php echo esc_attr( $approval_ratio ); ?>">0</strong><em>%</em>
			<p><?php esc_html_e( 'Share of likes out of all direct sentiment votes.', 'kosher-comments' ); ?></p>
		</div>
		<div class="kosher-comments-admin-kpi-card">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( 'Safe publish rate', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-value" data-count-up="<?php echo esc_attr( $safe_publish_rate ); ?>">0</strong><em>%</em>
			<p><?php esc_html_e( 'How often moderation allowed submissions through without blocking them.', 'kosher-comments' ); ?></p>
		</div>
		<div class="kosher-comments-admin-kpi-card">
			<span class="kosher-comments-admin-kpi-label"><?php esc_html_e( '14-day momentum', 'kosher-comments' ); ?></span>
			<strong class="kosher-comments-admin-kpi-text"><?php echo esc_html( sprintf( '%+d%%', $growth_value ) ); ?></strong>
			<p><?php echo esc_html( $growth_label ); ?></p>
		</div>
	</div>

	<div class="kosher-comments-dashboard-grid">
		<section class="kosher-comments-admin-panel kosher-comments-admin-panel-xl">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Trend line', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Comment Momentum Over the Last 14 Days', 'kosher-comments' ); ?></h2>
				</div>
				<div class="kosher-comments-admin-panel-note"><?php esc_html_e( 'Daily publishing trend', 'kosher-comments' ); ?></div>
			</div>
			<div class="kosher-comments-trend-chart" data-trend-chart>
				<svg viewBox="0 0 900 320" preserveAspectRatio="none" aria-hidden="true">
					<defs>
						<linearGradient id="kosher-comments-trend-fill" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stop-color="#CC49CC" stop-opacity="0.45"></stop>
							<stop offset="100%" stop-color="#F5EDF5" stop-opacity="0.02"></stop>
						</linearGradient>
						<linearGradient id="kosher-comments-trend-line" x1="0%" y1="0%" x2="100%" y2="0%">
							<stop offset="0%" stop-color="#5E0F5E"></stop>
							<stop offset="50%" stop-color="#CC49CC"></stop>
							<stop offset="100%" stop-color="#7A147A"></stop>
						</linearGradient>
					</defs>
					<g class="kosher-comments-chart-grid">
						<line x1="60" y1="40" x2="860" y2="40"></line>
						<line x1="60" y1="130" x2="860" y2="130"></line>
						<line x1="60" y1="220" x2="860" y2="220"></line>
						<line x1="60" y1="290" x2="860" y2="290"></line>
					</g>
					<path class="kosher-comments-chart-area" fill="url(#kosher-comments-trend-fill)"></path>
					<path class="kosher-comments-chart-line" fill="none" stroke="url(#kosher-comments-trend-line)" stroke-width="5" stroke-linecap="round"></path>
					<g class="kosher-comments-chart-points"></g>
					<g class="kosher-comments-chart-labels"></g>
				</svg>
			</div>
		</section>

		<section class="kosher-comments-admin-panel kosher-comments-admin-panel-side">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Sentiment', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Engagement Mix', 'kosher-comments' ); ?></h2>
				</div>
			</div>
			<div class="kosher-comments-admin-donut-wrap">
				<div
					class="kosher-comments-admin-donut"
					style="--likes: <?php echo esc_attr( max( 0, $likes ) ); ?>; --dislikes: <?php echo esc_attr( max( 0, $dislikes ) ); ?>; --replies: <?php echo esc_attr( max( 0, $replies ) ); ?>; --questions: <?php echo esc_attr( max( 0, $questions ) ); ?>; --blocked: <?php echo esc_attr( max( 0, $blocked_comments ) ); ?>;"
					data-donut-chart
				>
					<div class="kosher-comments-admin-donut-center">
						<span><?php esc_html_e( 'Actions', 'kosher-comments' ); ?></span>
						<strong data-count-up="<?php echo esc_attr( $total_interactions ); ?>">0</strong>
					</div>
				</div>
				<ul class="kosher-comments-admin-legend">
					<li><i class="is-likes"></i><span><?php esc_html_e( 'Likes', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $likes ); ?></strong></li>
					<li><i class="is-dislikes"></i><span><?php esc_html_e( 'Dislikes', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $dislikes ); ?></strong></li>
					<li><i class="is-replies"></i><span><?php esc_html_e( 'Replies', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $replies ); ?></strong></li>
					<li><i class="is-questions"></i><span><?php esc_html_e( 'Questions', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $questions ); ?></strong></li>
					<li><i class="is-blocked"></i><span><?php esc_html_e( 'Blocked', 'kosher-comments' ); ?></span><strong><?php echo esc_html( $blocked_comments ); ?></strong></li>
				</ul>
			</div>
		</section>
	</div>

	<div class="kosher-comments-dashboard-grid">
		<section class="kosher-comments-admin-panel">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Behavior', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Weekday Rhythm', 'kosher-comments' ); ?></h2>
				</div>
				<div class="kosher-comments-admin-panel-note"><?php echo esc_html( sprintf( __( 'Peak day: %1$s (%2$d)', 'kosher-comments' ), $weekday_peak_label, $weekday_peak_total ) ); ?></div>
			</div>
			<div class="kosher-comments-admin-weekday-grid">
				<?php $weekday_max = max( 1, ...array_map( 'intval', wp_list_pluck( $weekday_data, 'total' ) ) ); ?>
				<?php foreach ( $weekday_data as $row ) : ?>
					<?php $height = (int) round( ( (int) $row['total'] / $weekday_max ) * 100 ); ?>
					<div class="kosher-comments-admin-weekday-card">
						<span class="kosher-comments-admin-weekday-label"><?php echo esc_html( $row['label'] ); ?></span>
						<div class="kosher-comments-admin-weekday-bar">
							<i style="height:<?php echo esc_attr( max( 6, $height ) ); ?>%"></i>
						</div>
						<strong><?php echo esc_html( (int) $row['total'] ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="kosher-comments-admin-panel">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Signals', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Conversation Health Snapshot', 'kosher-comments' ); ?></h2>
				</div>
			</div>
			<div class="kosher-comments-admin-signal-list">
				<div class="kosher-comments-admin-signal-card">
					<span><?php esc_html_e( 'Review coverage', 'kosher-comments' ); ?></span>
					<strong data-count-up="<?php echo esc_attr( $review_coverage ); ?>">0</strong><em>%</em>
					<p><?php esc_html_e( 'Comments that included a star rating.', 'kosher-comments' ); ?></p>
				</div>
				<div class="kosher-comments-admin-signal-card">
					<span><?php esc_html_e( 'Reply rate', 'kosher-comments' ); ?></span>
					<strong data-count-up="<?php echo esc_attr( $reply_rate ); ?>">0</strong><em>%</em>
					<p><?php esc_html_e( 'How often readers turn comments into threads.', 'kosher-comments' ); ?></p>
				</div>
				<div class="kosher-comments-admin-signal-card">
					<span><?php esc_html_e( 'Question rate', 'kosher-comments' ); ?></span>
					<strong data-count-up="<?php echo esc_attr( $question_rate ); ?>">0</strong><em>%</em>
					<p><?php esc_html_e( 'Share of comments asking for help or clarification.', 'kosher-comments' ); ?></p>
				</div>
				<div class="kosher-comments-admin-signal-card">
					<span><?php esc_html_e( 'Audience hotspot', 'kosher-comments' ); ?></span>
					<strong class="kosher-comments-admin-signal-text"><?php echo esc_html( $top_location ); ?></strong>
					<p><?php esc_html_e( 'Most active location based on captured origin data.', 'kosher-comments' ); ?></p>
				</div>
			</div>
		</section>
	</div>

	<div class="kosher-comments-dashboard-grid">
		<section class="kosher-comments-admin-panel kosher-comments-admin-panel-xl">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Activity curve', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Conversation Pulse by Hour', 'kosher-comments' ); ?></h2>
				</div>
				<div class="kosher-comments-admin-panel-note"><?php echo esc_html( sprintf( __( 'Peak hour: %1$s (%2$d)', 'kosher-comments' ), $peak_hour_label, $peak_hour_total ) ); ?></div>
			</div>
			<div class="kosher-comments-activity-chart" data-activity-chart>
				<svg viewBox="0 0 900 320" preserveAspectRatio="none" aria-hidden="true">
					<defs>
						<linearGradient id="kosher-comments-activity-fill" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stop-color="#CC49CC" stop-opacity="0.42"></stop>
							<stop offset="100%" stop-color="#F5EDF5" stop-opacity="0.03"></stop>
						</linearGradient>
						<linearGradient id="kosher-comments-activity-line" x1="0%" y1="0%" x2="100%" y2="0%">
							<stop offset="0%" stop-color="#5E0F5E"></stop>
							<stop offset="50%" stop-color="#CC49CC"></stop>
							<stop offset="100%" stop-color="#7A147A"></stop>
						</linearGradient>
					</defs>
					<g class="kosher-comments-chart-grid">
						<line x1="60" y1="40" x2="860" y2="40"></line>
						<line x1="60" y1="130" x2="860" y2="130"></line>
						<line x1="60" y1="220" x2="860" y2="220"></line>
						<line x1="60" y1="290" x2="860" y2="290"></line>
					</g>
					<path class="kosher-comments-chart-area" fill="url(#kosher-comments-activity-fill)"></path>
					<path class="kosher-comments-chart-line" fill="none" stroke="url(#kosher-comments-activity-line)" stroke-width="5" stroke-linecap="round"></path>
					<g class="kosher-comments-chart-points"></g>
					<g class="kosher-comments-chart-labels"></g>
				</svg>
			</div>
			<div class="kosher-comments-admin-hour-strip">
				<?php $hourly_max = max( 1, ...array_map( 'intval', wp_list_pluck( $site_summary['time_of_day'], 'total' ) ) ); ?>
				<?php foreach ( $site_summary['time_of_day'] as $row ) : ?>
					<?php $intensity = (int) round( ( (int) $row['total'] / $hourly_max ) * 100 ); ?>
					<div class="kosher-comments-admin-hour-cell" style="--cell-intensity:<?php echo esc_attr( $intensity ); ?>%;">
						<span><?php echo esc_html( str_pad( (string) $row['hour_of_day'], 2, '0', STR_PAD_LEFT ) ); ?></span>
						<strong><?php echo esc_html( (int) $row['total'] ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="kosher-comments-admin-panel kosher-comments-admin-panel-side">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Reviews', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Rating Distribution', 'kosher-comments' ); ?></h2>
				</div>
			</div>
			<div class="kosher-comments-admin-rating-bars">
				<?php $ratings_total = max( 1, $ratings_count ); ?>
				<?php for ( $rating = 5; $rating >= 1; $rating-- ) : ?>
					<?php $count = isset( $site_summary['rating_distribution'][ $rating ] ) ? (int) $site_summary['rating_distribution'][ $rating ] : 0; ?>
					<?php $width = (int) round( ( $count / $ratings_total ) * 100 ); ?>
					<div class="kosher-comments-admin-rating-row">
						<span><?php echo esc_html( sprintf( __( '%d stars', 'kosher-comments' ), $rating ) ); ?></span>
						<div class="kosher-comments-admin-rating-track"><i style="width:<?php echo esc_attr( $width ); ?>%"></i></div>
						<strong><?php echo esc_html( $count ); ?></strong>
					</div>
				<?php endfor; ?>
			</div>
		</section>
	</div>

	<div class="kosher-comments-dashboard-grid">
		<section class="kosher-comments-admin-panel">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Audience', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Top Locations', 'kosher-comments' ); ?></h2>
				</div>
			</div>
			<?php if ( empty( $site_summary['location_distribution'] ) ) : ?>
				<p class="kosher-comments-admin-empty"><?php esc_html_e( 'No location data collected yet.', 'kosher-comments' ); ?></p>
			<?php else : ?>
				<?php $max_location = max( array_map( 'intval', wp_list_pluck( $site_summary['location_distribution'], 'total' ) ) ); ?>
				<ul class="kosher-comments-admin-location-list">
					<?php foreach ( $site_summary['location_distribution'] as $row ) : ?>
						<?php $location_width = $max_location ? (int) round( ( (int) $row['total'] / $max_location ) * 100 ) : 0; ?>
						<li>
							<div class="kosher-comments-admin-location-head">
								<span><?php echo esc_html( $row['country'] ); ?></span>
								<strong><?php echo esc_html( $row['total'] ); ?></strong>
							</div>
							<div class="kosher-comments-admin-location-track"><i style="width:<?php echo esc_attr( $location_width ); ?>%"></i></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="kosher-comments-admin-panel">
			<div class="kosher-comments-admin-panel-head">
				<div>
					<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Moderation', 'kosher-comments' ); ?></p>
					<h2><?php esc_html_e( 'Safety and Intent', 'kosher-comments' ); ?></h2>
				</div>
			</div>
			<div class="kosher-comments-admin-safety-metrics">
				<div class="kosher-comments-admin-safety-card">
					<span><?php esc_html_e( 'Blocked comments', 'kosher-comments' ); ?></span>
					<strong data-count-up="<?php echo esc_attr( $blocked_comments ); ?>">0</strong>
					<p><?php esc_html_e( 'Comments prevented from being published after moderation review.', 'kosher-comments' ); ?></p>
				</div>
				<div class="kosher-comments-admin-safety-card">
					<span><?php esc_html_e( 'Questions', 'kosher-comments' ); ?></span>
					<strong data-count-up="<?php echo esc_attr( $questions ); ?>">0</strong>
					<p><?php esc_html_e( 'Readers seeking help, recommendations, or clarification.', 'kosher-comments' ); ?></p>
				</div>
				<div class="kosher-comments-admin-safety-card">
					<span><?php esc_html_e( 'Busiest hour', 'kosher-comments' ); ?></span>
					<strong class="kosher-comments-admin-signal-text"><?php echo esc_html( $peak_hour_label ); ?></strong>
					<p><?php echo esc_html( sprintf( __( '%d comments landed during the peak hour.', 'kosher-comments' ), $peak_hour_total ) ); ?></p>
				</div>
			</div>
		</section>
	</div>

	<section class="kosher-comments-admin-panel">
		<div class="kosher-comments-admin-panel-head">
			<div>
				<p class="kosher-comments-admin-section-kicker"><?php esc_html_e( 'Content ranking', 'kosher-comments' ); ?></p>
				<h2><?php esc_html_e( 'Top Posts by Comment Volume', 'kosher-comments' ); ?></h2>
			</div>
			<div class="kosher-comments-admin-panel-note"><?php esc_html_e( 'Most discussed content on the site', 'kosher-comments' ); ?></div>
		</div>
		<?php if ( empty( $site_summary['top_posts'] ) ) : ?>
			<p class="kosher-comments-admin-empty"><?php esc_html_e( 'No analytics data is available yet.', 'kosher-comments' ); ?></p>
		<?php else : ?>
			<div class="kosher-comments-admin-post-list">
				<?php foreach ( $site_summary['top_posts'] as $index => $post_row ) : ?>
					<?php $post = get_post( (int) $post_row->post_id ); ?>
					<?php $bar_width = $top_posts_max ? (int) round( ( (int) $post_row->total_comments / $top_posts_max ) * 100 ) : 0; ?>
					<div class="kosher-comments-admin-post-card">
						<div class="kosher-comments-admin-post-rank"><?php echo esc_html( $index + 1 ); ?></div>
						<div class="kosher-comments-admin-post-main">
							<h3>
								<?php if ( $post ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
								<?php else : ?>
									<?php esc_html_e( 'Unknown post', 'kosher-comments' ); ?>
								<?php endif; ?>
							</h3>
							<div class="kosher-comments-admin-post-bar"><i style="width:<?php echo esc_attr( $bar_width ); ?>%"></i></div>
						</div>
						<div class="kosher-comments-admin-post-metrics">
							<strong><?php echo esc_html( (int) $post_row->total_comments ); ?></strong>
							<span><?php echo esc_html( sprintf( __( 'Avg %s', 'kosher-comments' ), round( (float) $post_row->average_rating, 2 ) ) ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<script type="application/json" id="kosher-comments-analytics-data"><?php echo wp_json_encode( $dashboard_data ); ?></script>
</div>
