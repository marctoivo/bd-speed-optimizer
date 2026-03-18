<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BD_Auto_Updater' ) ) :

class BD_Auto_Updater {

    private $api_url = 'https://getbdshield.com';
    private $product_slug;
    private $plugin_basename;
    private $plugin_version;
    private $plugin_name;
    private $license_key_callback;

    public function __construct( $config ) {
        $this->api_url              = rtrim( $config['api_url'] ?? $this->api_url, '/' );
        $this->product_slug         = $config['product_slug'];
        $this->plugin_basename      = $config['plugin_basename'];
        $this->plugin_version       = $config['plugin_version'];
        $this->plugin_name          = $config['plugin_name'] ?? $config['product_slug'];
        $this->license_key_callback = $config['license_key_callback'];

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
    }

    private function get_license_key() {
        return call_user_func( $this->license_key_callback );
    }

    private function api_request( $body ) {
        $url = $this->api_url . '/wp-json/bdls/v1/check-update';

        $response = wp_remote_post( $url, array(
            'timeout'   => 15,
            'sslverify' => true,
            'body'      => $body,
            'headers'   => array( 'Accept' => 'application/json' ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $json ) ? $json : new WP_Error( 'invalid_response', 'Invalid response from update server.' );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $key = $this->get_license_key();
        if ( empty( $key ) ) {
            return $transient;
        }

        $result = $this->api_request( array(
            'license_key'     => $key,
            'site_url'        => home_url(),
            'product_slug'    => $this->product_slug,
            'current_version' => $this->plugin_version,
        ) );

        if ( is_wp_error( $result ) || empty( $result['update'] ) ) {
            return $transient;
        }

        $plugin_data              = new stdClass();
        $plugin_data->slug        = $this->product_slug;
        $plugin_data->plugin      = $this->plugin_basename;
        $plugin_data->new_version = $result['new_version'];
        $plugin_data->url         = $this->api_url;
        $plugin_data->package     = $result['download_url'] ?? '';
        $plugin_data->tested      = $result['tested'] ?? '';
        $plugin_data->requires    = $result['requires'] ?? '';
        $plugin_data->requires_php = $result['requires_php'] ?? '';

        $transient->response[ $this->plugin_basename ] = $plugin_data;

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== $this->product_slug ) {
            return $result;
        }

        $key = $this->get_license_key();
        if ( empty( $key ) ) {
            return $result;
        }

        $info = $this->api_request( array(
            'license_key'     => $key,
            'site_url'        => home_url(),
            'product_slug'    => $this->product_slug,
            'current_version' => $this->plugin_version,
        ) );

        if ( is_wp_error( $info ) ) {
            return $result;
        }

        $plugin_info                = new stdClass();
        $plugin_info->name          = $info['name'] ?? $this->plugin_name;
        $plugin_info->slug          = $this->product_slug;
        $plugin_info->version       = $info['new_version'] ?? $this->plugin_version;
        $plugin_info->tested        = $info['tested'] ?? '';
        $plugin_info->requires      = $info['requires'] ?? '';
        $plugin_info->requires_php  = $info['requires_php'] ?? '';
        $plugin_info->author        = '<a href="https://getbdshield.com">BD Shield</a>';
        $plugin_info->homepage      = $this->api_url;
        $plugin_info->download_link = $info['download_url'] ?? '';

        if ( ! empty( $info['changelog'] ) ) {
            $plugin_info->sections = array( 'changelog' => $info['changelog'] );
        }

        return $plugin_info;
    }
}

endif;
