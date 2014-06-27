<?php
/*
Plugin Name: DsgnWrks Twitter Importer
Plugin URI: http://dsgnwrks.pro/twitter-importer/
Description: Helps you to backup your tweets while allowing you to have a site to display your twitter archive. Built-in support for importing to custom post types and attaching custom taxonomies.
Author URI: http://dsgnwrks.pro
Author: DsgnWrks
Donate link: http://dsgnwrks.pro/give/
Version: 2.0.0
*/

define( '_DWTW_PATH', plugin_dir_path( __FILE__ ) );
define( '_DWTW_URL', plugins_url('/', __FILE__ ) );

class DsgnWrksTwitter {
	public $version =  '2.0.0';
	public $options;
	public $capability;

	protected $prefix      = 'dp_twimport';
	protected $slug;

	protected $import_messages;


	protected $pre         = 'dsgnwrks_tweet_';
	protected $optkey      = 'dsgnwrks_tweet_options';
	protected $users       = false;
	protected $tw          = false;

	function __construct() {

		$this->slug = $this->name( 'settings' );
		$this->import_messages = new WP_Error();

		// i18n
		load_plugin_textdomain( 'dsgnwrks', false, dirname( plugin_basename( __FILE__ ) ) );

		// Register settings and get options
		add_action( 'init', array( $this, 'init' ), 10 );

		// Register cron
		add_action( 'init', array( $this, 'setup_schedule' ) );

		add_action( $this->name( 'event' ), array( $this, 'cron' ) );

		add_action( 'admin_menu', array( $this, 'admin_setup' ) );

		// For options page
		add_action( 'admin_init', array( $this, 'admin_init' ), 10 );

		// Handle actions (import, delete)
		add_action( 'admin_init', array ( $this, 'process_action' ), 11 );

		// Display messages
		add_action( 'all_admin_notices', array( $this, 'admin_notice' ) );

		// Load the plugin settings link shortcut.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );

		// Make sure we have our Twitter class
		if ( ! class_exists( 'TwitterWP' ) )
			include( _DWTW_PATH .'TwitterWP/lib/TwitterWP.php' );
	}

	/**
	 * Add Settings page to plugin action links in the Plugins table.
	 *
	 * @since 1.1.0
	 * @param  array $links Default plugin action links.
	 *
	 * @return array $links Amended plugin action links.
	 */
	public function settings_link( $links ) {
		$links[] = sprintf( '<a href="%s">%s</a>', $this->plugin_page(), __( 'Settings', 'dsgnwrks' ) );
		return $links;
	}

	public function admin_setup() {
		$hook = add_submenu_page(
			'tools.php',
			__( 'DsgnWrks Twitter Import Settings' ),
			__( 'Twitter Importer' ),
			$this->capability,
			$this->slug,
			array( $this, 'options_page' )
		);

		add_action( 'admin_print_styles-' . $hook, array( $this, 'styles' ) );
		add_action( 'admin_print_scripts-' . $hook, array( $this, 'scripts' ) );
	}

	/**
	 * The template for the options page
	 */
	public function options_page() {
		include( 'form-settings.php' );
	}

	/**
	 * Enqueue style
	 */
	public function styles() {
		wp_enqueue_style(
			$this->name( 'admin' ),
			plugins_url( 'css/admin.css', __FILE__ ),
			array(),
			$this->version
		);
	}

	/**
	 * Enqueue js and pass a variable to js.
	 */
	public function scripts() {
		wp_enqueue_script(
			$this->name( 'admin' ),
			plugins_url( 'js/admin.js', __FILE__ ),
			array( 'jquery' ),
			$this->version,
			true
		);
		$post_types = get_post_types( array( 'public' => true ) );

		foreach ( $post_types as $post_type ) {
			$taxonomies = get_object_taxonomies( $post_type );
			if ( !empty( $taxonomies ) )
				$data['cpts'][$post_type][] = $taxonomies;
		}

		if ( !empty( $data ) )
			wp_localize_script( $this->name( 'admin' ), 'dwtwitter', $data );
	}
	/**
	 * Run on init, easier to filter by other plugins.
	 */
	public function init() {
		$this->capability = apply_filters( $this->name( 'capability' ), 'manage_options' );

		$this->options = $this->get_option( 'options', array() );
	}

	public function setup_schedule() {
		if ( ! wp_next_scheduled( $this->name( 'event' ) ) ) {
			wp_schedule_event( time(), 'hourly', $this->name( 'event' ) );
		}
	}

	public function cron() {
		foreach ( $this->options as $username => $user_options ) {
			$this->import( $username );
		}
	}

	/**
	 * Prepare options page.
	 */
	public function admin_init() {

		register_setting(
			$this->name( 'users' ),
			$this->name( 'users' ),
			array( $this, 'validate_user' )
		);
		register_setting(
			$this->name( 'options' ),
			$this->name( 'options' ),
			array( $this, 'validate_settings' )
		);
	}

	/**
	 * Default options for each twitter user.
	 *
	 * @return array
	 */
	function defaults() {
		$options = array();
		$options['tag-filter'] = '';
		$options['mm'] = 0;
		$options['dd'] = 0;
		$options['yy'] = 0;
		$options['date-filter'] = 0;
		$options['remove-date-filter'] = '';
		$options['draft'] = '';
		$options['post-type'] = '';
		$options['author'] = '';
		$options['no-replies'] = '';
		$options['no-retweets'] = '';
		$options['hashtags_as_tax'] = '';
		$options['category'] = '';
		$options['post_tag'] = '';
		$options['post_format'] = '';

		return $options;
	}

	/**
	 * Used as filter when adding a new user. Checks and adds a new user with default settings.
	 *
	 * Callback for form-user. Returns nothing, we don't need to save extra options in db.
	 *
	 * @param $user
	 *
	 * @return void
	 */
	public function validate_user( $user ) {

		if ( !empty( $user ) ) {

			if ( ! $this->valid_username( $user ) )
				$this->log( new WP_Error( $this->name( 'error' ), 'Invalid characters in username.') );

			if ( ! $this->twitterwp()->user_exists( $user ) )
				$this->log( new WP_Error( $this->name( 'error' ), 'Invalid username: <strong>' . $user . '</strong>.') );

			$options = $this->get_option( 'options' );

			$options[$user] = $this->defaults();

			$this->update_option( 'options', $options );

			$this->log( new WP_Error( $this->name( 'success' ), 'Twitter user added: <strong>' . $user . '</strong>.') );

			$this->set_active_username( $user );

		}
	}

	/**
	 * Sanitize options, sets active user and calculates date-filter if needed.
	 *
	 * @param $options
	 *
	 * @return mixed
	 */
	public function validate_settings( $options ) {

		if ( empty( $options ) )
			return $options;

		foreach ( $options as $user => $user_options ) {

			$user_options = wp_parse_args( $user_options, $this->defaults() );

			$this->set_active_username( $user );

			foreach ( $user_options as $option_name => $value ) {

				if ( $option_name === 'date-filter' ) {
					if ( empty( $options[$user]['mm'] ) && empty( $options[$user]['dd'] ) && empty( $options[$user]['yy'] ) || !empty( $options[$user]['remove-date-filter'] ) ) {
						$options[$user][$option_name] = 0;
					} else {
						$options[$user][$option_name] = strtotime( $options[$user]['mm'] .'/'. $options[$user]['dd'] .'/'. $options[$user]['yy'] );
					}
				} elseif ( $option_name === 'post-type' ) {
					$options[$user][$option_name] = $this->filter( $value, '', 'post' );
				} elseif ( $option_name === 'draft' ) {
					$options[$user][$option_name] = $this->filter( $value, '', 'draft' );
				} elseif ( $option_name === 'yy' || $option_name === 'mm' || $option_name === 'dd' ) {
					$options[$user][$option_name] = $this->filter( $value, 'absint', 0 );
				} else {
					$options[$user][$option_name] = $this->filter( $value );
				}

				if ( $option_name == 'remove-date-filter' && $value == 1 ) {
					$options[$user]['mm'] = $options[$user]['dd'] = $options[$user]['yy'] = $options[$user]['date-filter'] = 0;
					$options[$user]['remove-date-filter'] = '';
				}

			}
		}

		return $options;
	}


	/**
	 * Used on form after saving to display saved user tab.
	 *
	 * @param string $username
	 */
	protected function set_active_username( $username ) {
		setcookie( $this->name( 'active_username' ), $username, time()+ 60*24*24, COOKIEPATH, COOKIE_DOMAIN, false);
	}

	/**
	 * Make sure there are no incompatible characters.
	 *
	 * @param string $username
	 *
	 * @return int
	 */
	protected function valid_username( $username ) {
		return preg_match( '/^[A-Za-z0-9_]+$/', $username );
	}



	/**
	 * Handle actions like import or delete
	 *
	 * @since 2.0.0
	 */
	public function process_action() {

		if ( isset ( $_GET['twitter_username'] ) && isset( $_REQUEST['action'] ) && check_admin_referer( $this->name( 'options' ) ) ) {
			$redirect_to = wp_get_referer() ? wp_get_referer() : $this->plugin_page() ;

			if ( ! $this->twitterwp()->user_exists( $_GET['twitter_username'] ) ) {
				$this->log( new WP_Error( $this->name( 'error' ), 'Invalid username: <strong>' . $_GET['twitter_username'] . '</strong>.' ) );
				wp_redirect( $redirect_to );
				exit;
			}

			switch ( $_REQUEST['action'] ) {
				case 'delete' :

					$this->log( new WP_Error( $this->name( 'success' ), 'User deleted: <strong>' . $_GET['twitter_username'] . '</strong>.' ) );
					break;

				case 'import' :
					$this->import( $_GET['twitter_username'] );
					break;

			}

			wp_redirect( $redirect_to );
			exit;
		}
	}

	/**
	 * Actual importer
	 *
	 * @param string $username
	 *
	 * @return bool
	 */
	public function import( $username = '' ) {

		$this->import_messages->add( $this->name( 'success' ), 'Import started.' );

		if ( ! $username ) {
			$this->log( new WP_Error( $this->name( 'error' ), 'Could not import. Empty username.' ) );
			return false;
		}

		if ( ! $this->twitterwp()->user_exists( $username ) ) {
			$this->log( new WP_Error( $this->name( 'error' ), 'Could not import. Invalid twitter username.' ) );
			return false;
		}

		if ( ! isset( $this->options[$username] ) ) {
			$this->log( new WP_Error( $this->name( 'error' ), 'Could not import. No options saved for this username: <strong>' . $username . '</strong>.' ) );
			return false;
		}

		// Filter to override TwitterWP method for getting tweets
		$tweets = apply_filters( $this->name( 'get_tweets' ), null, $this );

		// If no override, proceed as usual
		if ( null === $tweets ) {
			// @TODO https://dev.twitter.com/docs/working-with-timelines

			// get latest 200 tweets
			$tweets = $this->twitterwp()->get_tweets( $username, 200 );
		}

		if ( is_wp_error( $tweets ) ) {
			$this->log( new WP_Error( $this->name( 'error' ), $tweets->get_error_messages() ) );
			return false;
		}

		// pre-import filter
		$tweets = apply_filters( $this->name( 'filter_tweets' ), $tweets );

		$count = 0;


		foreach ( $tweets as $tweet ) {
			$count++;

			// filter by date
			if ( $this->options[$username]['date-filter'] > strtotime( $tweet->created_at ) ) {
				if ( $count == 1 ) {
					$this->import_messages->add( $this->name( 'success' ), 'No new tweets.' );
				}
				break;
			}

			// filter by hashtag
			if ( !empty( $this->options[$username]['tag-filter'] ) ) {
				$tags = explode( ', ', $this->options[$username]['tag-filter'] );
				$has_hashtag = false;
				if ( $tags ) {
					foreach ( $tags as $tag ) {
						if ( strpos( $tweet->text, '#'.$tag ) !== false )
							$has_hashtag = true;
					}
				}

				if ( !$has_hashtag )  {
					continue;
				}
			}

			// filter retweets
			if ( isset( $this->options[$username]['no-retweets'] ) && $this->options[$username]['no-retweets'] ) {
				if ( $tweet->retweeted || ( strpos( $tweet->text, 'RT @' ) !== false ) ) {
					continue;
				}
			}

			// filter replies
			if ( isset( $this->options[$username]['no-replies'] ) && $this->options[$username]['no-replies'] ) {
				if ( $tweet->in_reply_to_user_id || ( strpos( $tweet->text, '@' ) === 0 ) ) {
					continue;
				}
			}

			// filter by existence
			$already_stored = new WP_Query(
				array(
					 'post_type' => $this->options[$username]['post-type'],
					 'meta_query' => array(
						 array(
							 'key' => 'tweet_id',
							 'value' => $tweet->id_str
						 )
					 )
				)
			);

			if ( $already_stored->have_posts() ) {
				$count--;
				continue;
			}

			// all good. save
			$this->save_tweet( $tweet, $this->options[$username] );
		}

		$this->log( $this->import_messages );

		return true;
	}

	/**
	 * Where magic happens. Uses wp_insert_post
	 *
	 * @param $tweet
	 * @param array $opts
	 */
	protected function save_tweet( $tweet, $opts = array() ) {

		global $user_ID;

		if ( !isset( $opts['draft'] ) ) $opts['draft'] = 'draft';
		if ( !isset( $opts['author'] ) ) $opts['author'] = $user_ID;

		$post_date = date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) );

		$tweet_text = apply_filters( $this->name( 'clean_tweet' ), false ) ? iconv( 'UTF-8', 'ISO-8859-1//IGNORE', $tweet->text ) : $tweet->text;

		$post = array(
		  'post_author' => $opts['author'],
		  'post_content' => $tweet_text,
		  'post_date' => $post_date,
		  'post_date_gmt' => $post_date,
		  'post_status' => $opts['draft'],
		  'post_title' => wp_kses( $tweet_text, array() ),
		  'post_type' => $opts['post-type'],
		);

		// Allow other plugins to manipulate post data before saving
		$post = apply_filters( $this->name( 'insert_tweet_postdata' ), $post, $tweet, $this->options );

		$new_post_id = wp_insert_post( $post, true );

		do_action( $this->name( 'insert_tweet' ), $new_post_id, $tweet );

		if ( is_wp_error( $new_post_id ) ) {
			$this->import_messages->add( $this->name( 'error' ), $new_post_id->get_error_messages() );
			return;
		}


		// Set taxonomy terms from options
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxes as $key => $tax ) {

			if ( $tax->label == 'Format' && !current_theme_supports( 'post-formats' ) ) continue;

			$opts[$tax->name] = !empty( $opts[$tax->name] ) ? esc_attr( $opts[$tax->name] ) : '';

			$terms = explode( ', ', $opts[$tax->name] );

			if ( !empty( $terms ) )
				wp_set_object_terms( $new_post_id, $terms, $tax->name );
		}

		// If requested, set tweet hashtags as taxonomy terms
		if ( isset( $opts['hashtags_as_tax'] ) && $opts['hashtags_as_tax'] ) {
			$terms = array();
			foreach ( $tweet->entities->hashtags as $tag ) {
				$terms[] = $tag->text;
			}
			wp_set_object_terms( $new_post_id, $terms, $opts['hashtags_as_tax'] );
		} else {

			// otherwise, we'll save it as postmeta
			update_post_meta( $new_post_id, 'tweet_hashtags', $tweet->entities->hashtags );

		}

		// tweet urls
		update_post_meta( $new_post_id, 'tweet_urls', $tweet->entities->urls );

		// user mentions
		update_post_meta( $new_post_id, 'tweet_user_mentions', $tweet->entities->user_mentions );

		// media entities @TODO option to sideload media to WP
		if ( isset( $tweet->entities->media ) )
			update_post_meta( $new_post_id, 'tweet_media', $tweet->entities->media );

		// app/site used for tweeting
		update_post_meta( $new_post_id, 'tweet_source', $tweet->source );
		// tweet id
		update_post_meta( $new_post_id, 'tweet_id', $tweet->id_str );
		// tweet @replys
		if ( !empty( $tweet->in_reply_to_status_id_str ) )
			update_post_meta( $new_post_id, 'in_reply_to_status_id_str', $tweet->in_reply_to_status_id_str );
		if ( !empty( $tweet->in_reply_to_user_id ) )
			update_post_meta( $new_post_id, 'in_reply_to_user_id', $tweet->in_reply_to_user_id );
		if ( !empty( $tweet->in_reply_to_screen_name ) )
			update_post_meta( $new_post_id, 'in_reply_to_screen_name', $tweet->in_reply_to_screen_name );

		$this->import_messages->add( $this->name( 'success' ), '<em>'. wp_trim_words( strip_tags( $tweet->text ), 10 ) .'</em> imported and created successfully.' );
	}

	/**
	 * @param string $opt
	 * @param string $filter
	 * @param string $else
	 *
	 * @return int|string
	 */
	protected function filter( $opt = '', $filter = '', $else = '' ) {
		if ( empty( $opt ) )
			return $else;
		if ( $filter == 'absint' )
			return absint( $opt );
		else
			return esc_attr( $opt );
	}

	public function twitterwp() {
		$this->tw = $this->tw ? $this->tw : TwitterWP::start( '0=KZcFkYYJdYh3DyfY4qof5vMyp&1=bbIKQ7inqvDmvn7jPL6uAi1l7IUMWOx8FreSLujXFAYI4WThl5&2=13976842-gfJIkVgcZrsXZy1Dd6cvIdc17e0KhDO0k0lHirnlI&3=iAjHVm8PSTSWvaxXNs6UBj2za5oaCMsLHZo9i2mIUjhMC' );
		/*
		 * DP
		 * KZcFkYYJdYh3DyfY4qof5vMyp
		 * bbIKQ7inqvDmvn7jPL6uAi1l7IUMWOx8FreSLujXFAYI4WThl5
		 * 13976842-gfJIkVgcZrsXZy1Dd6cvIdc17e0KhDO0k0lHirnlI
		 * iAjHVm8PSTSWvaxXNs6UBj2za5oaCMsLHZo9i2mIUjhMC
		 */

		/*
		 * DSGNWRKS
		 * m39J9KuiCEajGFwRA3VzxQ
		 * jazlUeGiKPkQVzPHZMDqlEKM9pqv84l93zyhTR6pIng
		 * 24203273-MqOWFPQZZLGf4RaZSEVLOxalZAa9rCg1NCMEoCYMw
		 * 12Ya5GLGgiHFV3YK6GnixUx50dvEEf2vMita2kOoFQ
		 */
		return $this->tw;
	}

	/**
	 * Display messages stored in transients, clean afterwards.
	 *
	 * @since 2.0.0
	 */
	public function admin_notice() {

		$t = get_transient( $this->name('message') );;

		if ( $t ) {
			foreach ( $t as $code => $messages ) {
				foreach ( $messages as $message ) {
					$class = ($code == $this->name( 'error' )) ? 'error' : 'updated';

					if ( $message )
						printf( '<div class="%s"><p>%s</p></div>', $class, $message );
				}
			}
			delete_transient( $this->name('message') );
		}

	}

	/**
	 * Get the url for the plugin admin page
	 * @since  1.1.0
	 * @return string plugin admin page url
	 */
	public function plugin_page() {
		return add_query_arg( 'page', $this->slug, admin_url( 'tools.php' ) );
	}

	public function option_name( $handle, $subhandle = '', $echo = true, $option_name = 'options' ) {
		if ( $subhandle )
			$string = 'name="%s[%s][%s]"';
		else
			$string = 'name="%s[%s]"';

		if ( $echo )
			printf( $string, $this->name( $option_name ), $handle, $subhandle );
		else
			sprintf( $string, $this->name( $option_name ), $handle );
	}

	/**
	 * Store messages in transients, so crons can also throw messages.
	 *
	 * @param WP_Error $message
	 * @since 2.0.0
	 */
	public function log( $message ) {

		if ( ! is_wp_error( $message ) )
			return;

		$t = get_transient( $this->name('message') );

		if ( ! $t )
			$t = array();

		foreach ( (array) $message->errors as $code => $messages ) {
			if ( isset( $t[$code] ) )
				$t[$code] = array_merge( $t[$code], $messages);
			else
				$t[$code] = $messages;
		}

		set_transient( $this->name('message'), $t, 60 * 60 * 24 );
	}

	/**
	 * Compose prefixed name. Helper function.
	 *
	 * @param $handle
	 * @param string $separator
	 * @since 2.0.0
	 *
	 * @return string
	 */
	function name( $handle, $separator = '_' ) {
		return $this->prefix . $separator . $handle;
	}

	/**
	 * Set prefixed options
	 *
	 * @param string $name
	 * @param mixed $value
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	function update_option( $name, $value = '' ) {
		return update_option( $this->name( $name ), $value );
	}

	/**
	 * Get prefixed options
	 *
	 * @param string $name
	 * @param mixed $default
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return get_option( $this->name( $name ), $default );
	}

	/**
	 * Delete prefixed options
	 *
	 * @param string $name
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	function delete_option( $name ) {
		return delete_option( $this->name( $name ) );
	}
}

new DsgnWrksTwitter;

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		if ( null === $more )
			$more = __( '...' );
		$original_text = $text;
		$text = wp_strip_all_tags( $text );
		$words_array = preg_split( "/[\n\r\t ]+/", $text, $num_words + 1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words_array ) > $num_words ) {
			array_pop( $words_array );
			$text = implode( ' ', $words_array );
			$text = $text . $more;
		} else {
			$text = implode( ' ', $words_array );
		}
		return apply_filters( 'wp_trim_words', $text, $num_words, $more, $original_text );
	}
}
