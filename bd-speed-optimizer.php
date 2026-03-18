<?php
/**
 * Plugin Name: BD Speed Optimizer
 * Plugin URI: https://getbdshield.com/plugins/bd-speed-optimizer
 * Description: Performance scanner that analyzes your WordPress site, calculates a speed score, and provides toggle-based frontend optimizations and database cleanup tools.
 * Version: 1.0.2
 * Author: BestDid
 * Author URI: https://bestdid.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bd-speed-optimizer
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Tested up to: 6.7
 *
 * @package BD_Speed_Optimizer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'BDSO_VERSION', '1.0.2' );
define( 'BDSO_PLUGIN_FILE', __FILE__ );
define( 'BDSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BDSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default settings — standalone function to avoid circular dependencies.
 *
 * @return array
 */
function bdso_get_defaults() {
    return array(
        // Frontend optimizations (all boolean, default false).
        'defer_js'              => false,
        'delay_js'              => false,
        'remove_jquery_migrate' => false,
        'minify_html'           => false,
        'lazy_load_images'      => false,
        'lazy_load_iframes'     => false,
        'disable_emojis'        => false,
        'disable_embeds'        => false,
        'remove_query_strings'  => false,
        'dns_prefetch'          => '',
        'preconnect_domains'    => '',

        // License.
        'license_key'           => '',
        'license_email'         => '',
    );
}

// ─── Activation / Deactivation (top-level, runs BEFORE class) ───────

register_activation_hook( __FILE__, 'bdso_activate_plugin' );
register_deactivation_hook( __FILE__, 'bdso_deactivate_plugin' );

function bdso_activate_plugin() {
    if ( false === get_option( 'bdso_settings' ) ) {
        update_option( 'bdso_settings', bdso_get_defaults() );
    }

    // Activation redirect.
    set_transient( 'bdso_activation_redirect', true, 30 );
}

function bdso_deactivate_plugin() {
    // Nothing needed — no cron hooks or DB tables.
}

// ─── Main Plugin Class ──────────────────────────────────────────────

final class BD_Speed_Optimizer {

    /** @var BD_Speed_Optimizer|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->hooks();
    }

    /**
     * Get default settings.
     */
    public function get_defaults() {
        return bdso_get_defaults();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once BDSO_PLUGIN_DIR . 'includes/class-bdso-license.php';
        require_once BDSO_PLUGIN_DIR . 'includes/class-bdso-scanner.php';
        require_once BDSO_PLUGIN_DIR . 'includes/class-bdso-optimizer.php';
        require_once BDSO_PLUGIN_DIR . 'includes/class-bdso-database.php';

        if ( is_admin() ) {
            require_once BDSO_PLUGIN_DIR . 'admin/class-bdso-admin.php';
        }
    }

    /**
     * Register hooks.
     */
    private function hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    /**
     * Load translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'bd-speed-optimizer', false, dirname( BDSO_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Get a setting value.
     */
    public function get_setting( $key ) {
        $defaults = bdso_get_defaults();
        $settings = get_option( 'bdso_settings', $defaults );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );
    }
}

/**
 * Main plugin instance accessor.
 *
 * @return BD_Speed_Optimizer
 */
function bdso() {
    return BD_Speed_Optimizer::instance();
}

// Auto-updater.
require_once BDSO_PLUGIN_DIR . 'includes/class-bd-auto-updater.php';
new BD_Auto_Updater( array(
    'product_slug'       => 'bd-speed-optimizer',
    'plugin_basename'    => 'bd-speed-optimizer/bd-speed-optimizer.php',
    'plugin_version'     => BDSO_VERSION,
    'plugin_name'        => 'BD Speed Optimizer',
    'license_key_callback' => function() {
        $s = get_option( 'bdso_settings', array() );
        return $s['license_key'] ?? '';
    },
) );

// Launch the plugin.
bdso();
