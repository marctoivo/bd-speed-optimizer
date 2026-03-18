<?php
/**
 * Admin dashboard for BD Speed Optimizer.
 *
 * @package BD_Speed_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BDSO_Admin {

    /** @var BDSO_Admin|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_bdso_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_bdso_run_scan', array( $this, 'ajax_run_scan' ) );
        add_action( 'wp_ajax_bdso_clean_revisions', array( $this, 'ajax_clean_revisions' ) );
        add_action( 'wp_ajax_bdso_clean_spam', array( $this, 'ajax_clean_spam' ) );
        add_action( 'wp_ajax_bdso_clean_transients', array( $this, 'ajax_clean_transients' ) );
        add_action( 'wp_ajax_bdso_optimize_tables', array( $this, 'ajax_optimize_tables' ) );
        add_action( 'wp_ajax_bdso_activate_license', array( $this, 'ajax_activate_license' ) );
        add_action( 'wp_ajax_bdso_deactivate_license', array( $this, 'ajax_deactivate_license' ) );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . BDSO_PLUGIN_BASENAME, array( $this, 'action_links' ) );

        // Activation redirect.
        add_action( 'admin_init', array( $this, 'activation_redirect' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_menu() {
        add_menu_page(
            __( 'BD Speed', 'bd-speed-optimizer' ),
            __( 'BD Speed', 'bd-speed-optimizer' ),
            'manage_options',
            'bd-speed-optimizer',
            array( $this, 'render_dashboard' ),
            'dashicons-performance',
            84
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'bd-speed-optimizer' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'bdso-admin',
            BDSO_PLUGIN_URL . 'assets/css/bdso-admin.css',
            array(),
            BDSO_VERSION
        );

        wp_enqueue_script(
            'bdso-admin',
            BDSO_PLUGIN_URL . 'assets/js/bdso-admin.js',
            array( 'jquery' ),
            BDSO_VERSION,
            true
        );

        wp_localize_script( 'bdso-admin', 'bdsoAdmin', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'bdso_admin_nonce' ),
            'licenseInfo' => BDSO_License::get_info(),
            'strings'     => array(
                'saving'     => __( 'Saving...', 'bd-speed-optimizer' ),
                'saved'      => __( 'Settings saved!', 'bd-speed-optimizer' ),
                'error'      => __( 'Error saving settings.', 'bd-speed-optimizer' ),
                'scanning'   => __( 'Scanning...', 'bd-speed-optimizer' ),
                'cleaning'   => __( 'Cleaning...', 'bd-speed-optimizer' ),
                'optimizing' => __( 'Optimizing...', 'bd-speed-optimizer' ),
            ),
        ) );
    }

    /**
     * Plugin action links.
     */
    public function action_links( $links ) {
        $custom = array(
            '<a href="' . admin_url( 'admin.php?page=bd-speed-optimizer' ) . '">' . __( 'Settings', 'bd-speed-optimizer' ) . '</a>',
        );
        return array_merge( $custom, $links );
    }

    /**
     * Redirect to dashboard on activation.
     */
    public function activation_redirect() {
        if ( get_transient( 'bdso_activation_redirect' ) ) {
            delete_transient( 'bdso_activation_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=bd-speed-optimizer' ) );
                exit;
            }
        }
    }

    /**
     * Render the main dashboard page.
     */
    public function render_dashboard() {
        $settings = get_option( 'bdso_settings', bdso_get_defaults() );
        include BDSO_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    // ──────────────────────────────────────────────────────────────────
    // AJAX Handlers
    // ──────────────────────────────────────────────────────────────────

    public function ajax_save_settings() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $raw     = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
        $current = get_option( 'bdso_settings', bdso_get_defaults() );

        $clean = array();

        // Boolean fields.
        $booleans = array(
            'defer_js', 'delay_js', 'remove_jquery_migrate', 'minify_html',
            'lazy_load_images', 'lazy_load_iframes', 'disable_emojis',
            'disable_embeds', 'remove_query_strings',
        );
        foreach ( $booleans as $key ) {
            $clean[ $key ] = ! empty( $raw[ $key ] );
        }

        // Textarea fields.
        $clean['dns_prefetch']      = isset( $raw['dns_prefetch'] ) ? sanitize_textarea_field( wp_unslash( $raw['dns_prefetch'] ) ) : '';
        $clean['preconnect_domains'] = isset( $raw['preconnect_domains'] ) ? sanitize_textarea_field( wp_unslash( $raw['preconnect_domains'] ) ) : '';

        // Preserve license keys.
        $clean['license_key']   = $current['license_key'] ?? '';
        $clean['license_email'] = $current['license_email'] ?? '';

        update_option( 'bdso_settings', $clean );

        wp_send_json_success( array( 'message' => 'Settings saved.' ) );
    }

    public function ajax_run_scan() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $result = BDSO_Scanner::scan();
        wp_send_json_success( $result );
    }

    public function ajax_clean_revisions() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $deleted = BDSO_Database::clean_revisions();
        wp_send_json_success( array( 'deleted' => $deleted, 'message' => sprintf( '%d revisions deleted.', $deleted ) ) );
    }

    public function ajax_clean_spam() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $deleted = BDSO_Database::clean_spam_comments();
        wp_send_json_success( array( 'deleted' => $deleted, 'message' => sprintf( '%d spam/trash comments deleted.', $deleted ) ) );
    }

    public function ajax_clean_transients() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $deleted = BDSO_Database::clean_transients();
        wp_send_json_success( array( 'deleted' => $deleted, 'message' => sprintf( '%d expired transients deleted.', $deleted ) ) );
    }

    public function ajax_optimize_tables() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $count = BDSO_Database::optimize_tables();
        wp_send_json_success( array( 'count' => $count, 'message' => sprintf( '%d tables optimized.', $count ) ) );
    }

    // ──────────────────────────────────────────────────────────────────
    // License AJAX Handlers
    // ──────────────────────────────────────────────────────────────────

    public function ajax_activate_license() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
        if ( empty( $key ) ) {
            wp_send_json_error( 'Please enter a license key.' );
        }

        $result = BDSO_License::activate( $key );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $settings = get_option( 'bdso_settings', bdso_get_defaults() );
        $settings['license_key'] = $key;
        update_option( 'bdso_settings', $settings );

        wp_send_json_success( array( 'message' => 'License activated successfully!', 'info' => $result ) );
    }

    public function ajax_deactivate_license() {
        check_ajax_referer( 'bdso_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $settings = get_option( 'bdso_settings', bdso_get_defaults() );
        $key      = $settings['license_key'] ?? '';

        if ( ! empty( $key ) ) {
            BDSO_License::deactivate( $key );
        }

        $settings['license_key'] = '';
        update_option( 'bdso_settings', $settings );

        wp_send_json_success( array( 'message' => 'License deactivated.' ) );
    }
}

// Initialize admin.
BDSO_Admin::instance();
