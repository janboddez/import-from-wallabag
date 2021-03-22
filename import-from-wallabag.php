<?php
/**
 * Plugin Name: Import From Wallabag
 * Description: Import Wallabag Entries as WordPress posts.
 * Author:      Jan Boddez
 * Author URI:  https://jan.boddez.net/
 * License:     General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: import-from-wallabag
 * Version:     0.1.0
 *
 * @package Import_From_Wallabag
 */

namespace Import_From_Wallabag;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/includes/class-options-handler.php';
require_once dirname( __FILE__ ) . '/includes/class-import-from-wallabag.php';

$import_from_wallabag = Import_From_Wallabag::get_instance();
$import_from_wallabag->register();
