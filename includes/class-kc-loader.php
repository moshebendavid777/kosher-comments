<?php
/**
 * Plugin loader.
 *
 * @package KosherComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kosher_Comments_Loader {

	/**
	 * Comments service.
	 *
	 * @var Kosher_Comments_Comments
	 */
	protected $comments;

	/**
	 * Analytics service.
	 *
	 * @var Kosher_Comments_Analytics
	 */
	protected $analytics;

	/**
	 * Moderation API service.
	 *
	 * @var Kosher_Comments_API
	 */
	protected $api;

	/**
	 * Strike service.
	 *
	 * @var Kosher_Comments_Strikes
	 */
	protected $strikes;

	/**
	 * Auth service.
	 *
	 * @var Kosher_Comments_Auth
	 */
	protected $auth;

	/**
	 * Public service.
	 *
	 * @var Kosher_Comments_Public
	 */
	protected $public;

	/**
	 * Admin service.
	 *
	 * @var Kosher_Comments_Admin
	 */
	protected $admin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api       = new Kosher_Comments_API();
		$this->strikes   = new Kosher_Comments_Strikes();
		$this->analytics = new Kosher_Comments_Analytics();
		$this->comments  = new Kosher_Comments_Comments( $this->api, $this->strikes, $this->analytics );
		$this->auth      = new Kosher_Comments_Auth( $this->strikes );
		$this->public    = new Kosher_Comments_Public( $this->comments );
		$this->admin     = new Kosher_Comments_Admin( $this->comments, $this->strikes, $this->analytics );
	}

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );

		$this->auth->register_hooks();
		$this->comments->register_hooks();
		$this->public->register_hooks();

		if ( is_admin() ) {
			$this->admin->register_hooks();
		}
	}

	/**
	 * Upgrade schema after version changes.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$stored_version = get_option( 'kosher_comments_version', '' );

		if ( KOSHER_COMMENTS_VERSION !== $stored_version ) {
			Kosher_Comments_Activator::activate();
		}
	}
}
