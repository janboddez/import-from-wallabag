<?php
/**
 * Main plugin class.
 *
 * @package Import_From_Wallabag
 */

namespace Import_From_Wallabag;

/**
 * Main plugin class.
 */
class Import_From_Wallabag {
	/**
	 * This plugin's single instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Import_From_Wallabag $instance Plugin instance.
	 */
	private static $instance;

	/**
	 * `Options_Handler` instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Options_Handler $instance `Options_Handler` instance.
	 */
	private $options_handler;

	/**
	 * Returns the single instance of this class.
	 *
	 * @since 0.1.0
	 *
	 * @return Import_From_Wallabag Single class instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->options_handler = new Options_Handler();
		$this->options_handler->register();
	}

	/**
	 * Interacts with WordPress's APIs.
	 *
	 * @since 0.1.0
	 */
	public function register() {
		register_activation_hook( dirname( dirname( __FILE__ ) ) . '/import-from-wallabag.php', array( $this, 'activate' ) );
		register_deactivation_hook( dirname( dirname( __FILE__ ) ) . '/import-from-wallabag.php', array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'import_from_wallabag', array( $this, 'import' ) );
	}

	/**
	 * Runs the importer after a file was uploaded.
	 *
	 * @since 0.1.0
	 */
	public function import() {
		// Like `get_option()` but with sensible (?) defaults.
		$options = $this->get_options_handler()->get_options();

		if ( empty( $options['host'] ) ) {
			return;
		}

		if ( empty( $options['client_id'] ) ) {
			return;
		}

		if ( empty( $options['client_secret'] ) ) {
			return;
		}

		if ( defined( 'IMPORT_FROM_WALLABAG_USER' ) ) {
			$options['user'] = IMPORT_FROM_WALLABAG_USER;
		}

		if ( defined( 'IMPORT_FROM_WALLABAG_PASS' ) ) {
			$options['pass'] = IMPORT_FROM_WALLABAG_PASS;
		}

		if ( empty( $options['user'] ) ) {
			return;
		}

		if ( empty( $options['pass'] ) ) {
			return;
		}

		// Grab an auth token.
		$response = wp_remote_post(
			esc_url_raw( $options['host'] . '/oauth/v2/token' ),
			array(
				'body' => array(
					'grant_type'    => 'password',
					'client_id'     => $options['client_id'],
					'client_secret' => $options['client_secret'],
					'username'      => $options['user'],
					'password'      => $options['pass'],
				),
			)
		);

		if ( ! isset( $response['body'] ) ) {
			return;
		}

		$data = @json_decode( $response['body'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! isset( $data->access_token ) ) {
			return;
		}

		// Fetch the (latest) entries.
		$args = array(
			'perPage' => 30,
		);

		if ( ! empty( $options['tags'] ) ) {
			$args['tags'] = $options['tags'];
		}

		if ( ! empty( $options['last_run'] ) ) {
			$args['since'] = $options['last_run'];
		}

		$args = (array) apply_filters( 'import_from_wallabag_api_args', $args );

		$response = wp_remote_get(
			esc_url_raw(
				add_query_arg(
					$args,
					$options['host'] . '/api/entries.json'
				)
			),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $data->access_token,
				),
			)
		);

		if ( ! isset( $response['body'] ) ) {
			return;
		}

		$data = @json_decode( $response['body'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( empty( $data->_embedded->items ) || ! is_array( $data->_embedded->items ) ) {
			return;
		}

		$options['last_run'] = time();
		update_option( 'import_from_wallabag_settings', $options );

		$imported = 0;
		$skipped  = 0;

		// And add 'em if needed.
		foreach ( $data->_embedded->items as $entry ) {
			if ( false === filter_var( $entry->url, FILTER_VALIDATE_URL ) ) {
				// Skip invalid "URLs," like those that start with `place:`.
				/* translators: %s: invalid URL */
				error_log( sprintf( __( 'Skipping %s (invalid).', 'import-from-wallabag' ), $entry->url ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			if ( apply_filters( 'import_from_wallabag_skip_duplicates', true, $entry ) ) {
				// Requires custom field support for `$post_type`! Or does it?
				/* @todo: Use a custom table to keep track of previously imported items? */
				$query = new \WP_Query(
					array(
						'post_type'           => (string) $options['post_type'] ? $options['post_type'] : 'post', // The selected post type.
						'post_status'         => get_post_stati(),
						'posts_per_page'      => -1,
						'ignore_sticky_posts' => '1',
						'fields'              => 'ids',
						'meta_key'            => 'import_from_wallabag_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'          => esc_url_raw( $entry->url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);

				if ( ! empty( $query->posts ) ) {
					$skipped++;

					/* translators: %s: duplicate URL */
					error_log( sprintf( __( 'Skipping %s (duplicate).', 'import-from-wallabag' ), $entry->url ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					continue;
				}
			}

			$post_title = sanitize_text_field( $entry->title );

			/* translators: %s: entry URL */
			$post_content = '<i>' . sprintf( __( 'Bookmarked %s.', 'import_from_wallabag' ), '<a class="u-bookmark-of" href="' . esc_url( $entry->url ) . '">' . $post_title . '</a>' ) . '</i>';

			if ( ! empty( $entry->annotations ) && is_array( $entry->annotations ) ) {
				foreach ( $entry->annotations as $annotation ) {
					if ( ! empty( $annotation->text ) ) {
						$post_content .= PHP_EOL . PHP_EOL . sanitize_textarea_field( $annotation->text );
					}

					if ( ! empty( $annotation->quote ) ) {
						$post_content .= PHP_EOL . PHP_EOL . '<blockquote>' . sanitize_textarea_field( $annotation->quote ) . '</blockquote>';
					}
				}
			}

			$post_status = ! empty( $options['post_status'] ) ? $options['post_status'] : 'draft';
			$post_type   = ! empty( $options['post_type'] ) ? $options['post_type'] : 'post';
			$post_format = ! empty( $options['post_format'] ) && 'standard' !== $options['post_format'] ? $options['post_format'] : '';

			$args = array(
				'post_title'   => $post_title,
				'post_content' => trim( $post_content ),
				'post_status'  => $post_status,
				'post_type'    => $post_type,
				'post_date'    => get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $entry->created_at ) ), 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'meta_input'   => array(
					'import_from_wallabag_uri' => esc_url_raw( $entry->url ),
				),
			);
			$args = apply_filters( 'import_from_wallabag_post_args', $args, $entry );

			$post_id = wp_insert_post( $args );

			if ( $post_id ) {
				// Success!
				$imported++;

				if ( post_type_supports( $post_type, 'post-formats' ) && ! empty( $post_format ) ) {
					set_post_format( $post_id, $post_format );
				}
			}
		}

		/* translators: %1$d number of imported bookmarks %2$d number of skipped bookmarks */
		error_log( sprintf( __( '%1$d bookmarks imported (and %2$d skipped)!', 'import-from-wallabag' ), $imported, $skipped ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Runs on activation.
	 *
	 * @since 0.1.0
	 */
	public function activate() {
		// Schedule a daily cron job.
		if ( false === wp_next_scheduled( 'import_from_wallabag' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'import_from_wallabag' );
		}
	}

	/**
	 * Runs on deactivation.
	 *
	 * @since 0.1.0
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'import_from_wallabag' );
	}

	/**
	 * Enables localization.
	 *
	 * @since 0.1.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'import-from-wallabag', false, basename( dirname( dirname( __FILE__ ) ) ) . '/languages' );
	}

	/**
	 * Returns `Options_Handler` instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Options_Handler This plugin's `Options_Handler` instance.
	 */
	public function get_options_handler() {
		return $this->options_handler;
	}
}
