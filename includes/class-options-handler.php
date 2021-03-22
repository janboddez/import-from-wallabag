<?php
/**
 * Handles WP Admin settings pages and the like.
 *
 * @package Import_From_Wallabag
 */

namespace Import_From_Wallabag;

/**
 * Options handler class.
 */
class Options_Handler {
	/**
	 * Plugin options.
	 *
	 * @since 0.1.0
	 *
	 * @var array $options Plugin options.
	 */
	private $options = array(
		'host'          => 'https://app.wallabag.it',
		'client_id'     => '',
		'client_secret' => '',
		'user'          => '',
		'pass'          => '',
		'tags'          => '',
		'post_type'     => 'post',
		'post_status'   => 'draft',
		'post_format'   => 'standard',
	);

	/**
	 * WordPress's default post types.
	 *
	 * @since 0.1.0
	 *
	 * @var array WordPress's default post types, minus "post" itself.
	 */
	const DEFAULT_POST_TYPES = array(
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'user_request',
		'oembed_cache',
		'wp_block',
		'coblocks_pattern', // Not actually WP core.
	);

	/**
	 * Allowable post statuses.
	 *
	 * @var array POST_STATUSES Allowable post statuses.
	 *
	 * @since 0.1.0
	 */
	const POST_STATUSES = array(
		'publish',
		'draft',
		'pending',
		'private',
	);

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->options = get_option(
			'import_from_wallabag_settings',
			$this->options
		);
	}

	/**
	 * Interacts with WordPress's Plugin API.
	 *
	 * @since 0.5.0
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
	}

	/**
	 * Registers the plugin settings page.
	 *
	 * @since 0.1.0
	 */
	public function create_menu() {
		add_options_page(
			__( 'Import From Wallabag', 'import-from-wallabag' ),
			__( 'Import From Wallabag', 'import-from-wallabag' ),
			'manage_options',
			'import-from-wallabag',
			array( $this, 'settings_page' )
		);
		add_action( 'admin_init', array( $this, 'add_settings' ) );
	}

	/**
	 * Registers the actual options.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {
		register_setting(
			'import-from-wallabag-settings-group',
			'import_from_wallabag_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Handles submitted options.
	 *
	 * @since 0.1.0
	 *
	 * @param array $settings Settings as submitted through WP Admin.
	 *
	 * @return array Options to be stored.
	 */
	public function sanitize_settings( $settings ) {
		// Post types considered valid.
		$supported_post_types = array_diff( get_post_types(), self::DEFAULT_POST_TYPES );

		if ( ! empty( $settings['post_type'] ) && in_array( $settings['post_type'], $supported_post_types, true ) ) {
			$this->options['post_type'] = $settings['post_type'];
		}

		if ( ! empty( $settings['post_status'] ) && in_array( $settings['post_status'], self::POST_STATUSES, true ) ) {
			$this->options['post_status'] = $settings['post_status'];
		}

		if ( ! empty( $settings['post_format'] ) && in_array( $settings['post_format'], get_post_format_slugs(), true ) ) {
			$this->options['post_format'] = $settings['post_format'];
		}

		if ( ! empty( $settings['client_id'] ) ) {
			$this->options['client_id'] = $settings['client_id'];
		}

		if ( ! empty( $settings['client_secret'] ) ) {
			$this->options['client_secret'] = $settings['client_secret'];
		}

		if ( isset( $settings['user'] ) ) {
			$this->options['user'] = $settings['user'];
		}

		if ( isset( $settings['pass'] ) && ! defined( 'IMPORT_FROM_WALLABAG_PASS' ) ) {
			$this->options['pass'] = $settings['pass'];
		} else {
			$this->options['pass'] = '';
		}

		if ( isset( $settings['tags'] ) ) {
			$this->options['tags'] = str_replace(
				', ',
				',',
				sanitize_text_field( $settings['tags'] )
			);
		}

		if ( isset( $settings['host'] ) ) {
			$host = untrailingslashit( trim( $settings['host'] ) );

			if ( '' === $host ) {
				// Removing the instance URL. Might be done to temporarily
				// disable imports.
				$this->options['host'] = '';
			} else {
				if ( 0 !== strpos( $host, 'https://' ) && 0 !== strpos( $host, 'http://' ) ) {
					// Missing protocol. Try adding `https://`.
					$host = 'https://' . $host;
				}

				if ( false !== wp_http_validate_url( $host ) ) {
					$this->options['host'] = untrailingslashit( $host );
				} else {
					// Invalid URL. Display error message.
					add_settings_error(
						'import-from-wallabag-host',
						'invalid-url',
						esc_html__( 'Please provide a valid URL.', 'import-from-wallabag' )
					);
				}
			}
		}

		// Updated settings.
		return $this->options;
	}

	/**
	 * Echoes the plugin options form.
	 *
	 * @since 0.1.0
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import From Wallabag', 'import-from-wallabag' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				// Print nonces and such.
				settings_fields( 'import-from-wallabag-settings-group' );

				// Post types considered valid.
				$supported_post_types = array_diff(
					get_post_types(),
					self::DEFAULT_POST_TYPES
				);

				$post_formats = get_post_format_slugs();
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[host]"><?php esc_html_e( 'Instance', 'import-from-wallabag' ); ?></label></th>
						<td><input type="url" id="import_from_wallabag_settings[host]" name="import_from_wallabag_settings[host]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['host'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Your wallabag instance&rsquo;s URL.', 'import-from-wallabag' ); ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[client_id]"><?php esc_html_e( 'Client ID', 'import-from-wallabag' ); ?></label></th>
						<td><input type="text" id="import_from_wallabag_settings[client_id]" name="import_from_wallabag_settings[client_id]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['client_id'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Your wallabag API client ID.', 'import-from-wallabag' ); ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[client_secret]"><?php esc_html_e( 'Client Secret', 'import-from-wallabag' ); ?></label></th>
						<td><input type="text" id="import_from_wallabag_settings[client_secret]" name="import_from_wallabag_settings[client_secret]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['client_secret'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Your wallabag API client secret.', 'import-from-wallabag' ); ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[user]"><?php esc_html_e( 'Username', 'import-from-wallabag' ); ?></label></th>
						<td><input type="text" id="import_from_wallabag_settings[user]" name="import_from_wallabag_settings[user]" style="min-width: 33%;" value="<?php echo esc_attr( defined( 'IMPORT_FROM_WALLABAG_USER' ) ? IMPORT_FROM_WALLABAG_USER : $this->options['user'] ); ?>" <?php echo defined( 'IMPORT_FROM_WALLABAG_USER' ) ? 'disabled="disabled"' : ''; ?> />
						<p class="description"><?php esc_html_e( 'Your wallabag username.', 'import-from-wallabag' ); ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[pass]"><?php esc_html_e( 'Password', 'import-from-wallabag' ); ?></label></th>
						<td><input type="password" id="import_from_wallabag_settings[pass]" name="import_from_wallabag_settings[pass]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['pass'] ); ?>" <?php echo defined( 'IMPORT_FROM_WALLABAG_PASS' ) ? 'disabled="disabled"' : ''; ?> />
						<p class="description"><?php esc_html_e( 'Your wallabag password.', 'import-from-wallabag' ); ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[tags]"><?php esc_html_e( 'Tags', 'import-from-wallabag' ); ?></label></th>
						<td><input type="text" id="import_from_wallabag_settings[tags]" name="import_from_wallabag_settings[tags]" style="min-width: 33%;" value="<?php echo esc_attr( $this->options['tags'] ); ?>" />
						<p class="description"><?php _e( 'Only entries with <em>all</em> of these (comma-separated) tags will be imported.', 'import-from-wallabag' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></p></td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[post_type]"><?php esc_html_e( 'Post Type', 'import-from-wallabag' ); ?></label></th>
						<td>
							<select name="import_from_wallabag_settings[post_type]" id="import_from_wallabag_settings[post_type]">
								<?php
								foreach ( $supported_post_types as $post_type ) :
									$post_type_object = get_post_type_object( $post_type );
									?>
									<option value="<?php echo esc_attr( $post_type ); ?>" <?php ( ! empty( $this->options['post_type'] ) ? selected( $post_type, $this->options['post_type'] ) : '' ); ?>>
										<?php echo esc_html( $post_type_object->labels->singular_name ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will be of this type.', 'import-from-wallabag' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[post_status]"><?php esc_html_e( 'Post Status', 'import-from-wallabag' ); ?></label></th>
						<td>
							<select name="import_from_wallabag_settings[post_status]" id="import_from_wallabag_settings[post_status]">
								<?php foreach ( self::POST_STATUSES as $post_status ) : ?>
									<option value="<?php echo esc_attr( $post_status ); ?>" <?php ( ! empty( $this->options['post_status'] ) ? selected( $post_status, $this->options['post_status'] ) : '' ); ?>><?php echo esc_html( ucfirst( $post_status ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will receive this status.', 'import-from-wallabag' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="import_from_wallabag_settings[post_format]"><?php esc_html_e( 'Post Format', 'import-from-wallabag' ); ?></label></th>
						<td>
							<select name="import_from_wallabag_settings[post_format]" id="import_from_wallabag_settings[post_format]">
								<?php foreach ( $post_formats as $post_format ) : ?>
									<option value="<?php echo esc_attr( $post_format ); ?>" <?php ( ! empty( $this->options['post_format'] ) ? selected( $post_format, $this->options['post_format'] ) : '' ); ?>><?php echo esc_html( get_post_format_string( $post_format ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Affects only Post Types that actually support Post Formats. Your active theme decides how different Post Formats are displayed. Regardless, &ldquo;Link&rdquo; is probably a good idea.', 'import-from-wallabag' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit"><?php submit_button( __( 'Save Changes' ), 'primary', 'submit', false ); ?></p>
			</form>
		<?php
	}

	/**
	 * Returns the plugin options.
	 *
	 * @since 0.1.0
	 *
	 * @return array Plugin options.
	 */
	public function get_options() {
		return $this->options;
	}
}
