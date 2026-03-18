<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Client-side license management for BD Speed Optimizer.
 * Communicates with the BDShield License Server REST API.
 */
class BDSO_License {

    const API_URL    = 'https://getbdshield.com/wp-json/bdls/v1/validate';
    const CACHE_KEY  = 'bdso_license_data';
    const CACHE_TTL  = DAY_IN_SECONDS;

    /** @var bool|null Per-request static cache to avoid multiple checks. */
    private static $is_active_cache = null;

    /**
     * Tier -> feature mapping.
     */
    const FEATURES = array(
        'starter' => array(
            'sites' => 1,
        ),
        'professional' => array(
            'sites' => 3,
        ),
        'agency' => array(
            'sites' => 25,
        ),
    );

    /**
     * Check if the plugin has a valid, activated license.
     */
    public static function is_active() {
        if ( null !== self::$is_active_cache ) {
            return self::$is_active_cache;
        }

        $settings = get_option( 'bdso_settings', array() );
        $key      = $settings['license_key'] ?? '';
        if ( empty( $key ) ) {
            self::$is_active_cache = false;
            return false;
        }

        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            self::$is_active_cache = ! empty( $cached['valid'] );
            return self::$is_active_cache;
        }

        // Try a remote check to repopulate cache.
        $result = self::remote_check( $key );
        self::$is_active_cache = $result && ! empty( $result['valid'] );
        return self::$is_active_cache;
    }

    /**
     * Get the current license tier.
     */
    public static function get_tier() {
        $cached = get_transient( self::CACHE_KEY );
        if ( $cached && ! empty( $cached['tier'] ) ) {
            return $cached['tier'];
        }

        $settings = get_option( 'bdso_settings', array() );
        $key      = $settings['license_key'] ?? '';
        if ( empty( $key ) ) {
            return 'starter';
        }

        $result = self::remote_check( $key );
        if ( $result && ! empty( $result['tier'] ) ) {
            return $result['tier'];
        }

        return 'starter';
    }

    /**
     * Check if the current tier has a specific feature.
     */
    public static function has_feature( $feature ) {
        $tier     = self::get_tier();
        $features = self::FEATURES[ $tier ] ?? self::FEATURES['starter'];
        return ! empty( $features[ $feature ] );
    }

    /**
     * Get all cached license info (for admin display).
     */
    public static function get_info() {
        $cached = get_transient( self::CACHE_KEY );
        if ( $cached ) {
            return $cached;
        }

        $settings = get_option( 'bdso_settings', array() );
        $key      = $settings['license_key'] ?? '';
        if ( ! empty( $key ) ) {
            return self::remote_check( $key );
        }

        return array(
            'valid'           => false,
            'tier'            => 'starter',
            'max_sites'       => 1,
            'activated_count' => 0,
            'expires_at'      => '',
            'features'        => self::FEATURES['starter'],
        );
    }

    /**
     * Activate a license key.
     */
    public static function activate( $key ) {
        $result = self::api_call( $key, 'activate' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! empty( $result['valid'] ) ) {
            set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );
            return $result;
        }

        return new WP_Error( 'activation_failed', $result['message'] ?? 'Activation failed.' );
    }

    /**
     * Deactivate the current license key.
     */
    public static function deactivate( $key ) {
        $result = self::api_call( $key, 'deactivate' );
        delete_transient( self::CACHE_KEY );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result;
    }

    /**
     * Perform a remote check (no site activation change).
     */
    public static function remote_check( $key ) {
        $result = self::api_call( $key, 'check' );

        if ( is_wp_error( $result ) ) {
            set_transient( self::CACHE_KEY, array( 'valid' => false, 'error' => true ), HOUR_IN_SECONDS );
            return null;
        }

        if ( ! empty( $result['valid'] ) ) {
            set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );
            return $result;
        }

        set_transient( self::CACHE_KEY, array( 'valid' => false ), HOUR_IN_SECONDS );
        return null;
    }

    /**
     * Call the BDShield License API.
     */
    private static function api_call( $key, $action ) {
        $timeout = in_array( $action, array( 'activate', 'deactivate' ), true ) ? 15 : 5;

        $response = wp_remote_post( self::API_URL, array(
            'timeout'   => $timeout,
            'sslverify' => true,
            'body'      => array(
                'license_key'  => sanitize_text_field( $key ),
                'site_url'     => home_url(),
                'action'       => $action,
                'product_slug' => 'bd-speed-optimizer',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code ) {
            return $body;
        }

        $message = $body['message'] ?? 'License validation failed.';
        $error   = $body['code'] ?? 'license_error';

        return new WP_Error( $error, $message );
    }
}
