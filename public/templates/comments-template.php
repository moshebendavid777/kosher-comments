<?php
/**
 * Native comments template override.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo do_shortcode( '[kosher_comments]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
