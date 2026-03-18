<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend optimizer for BD Speed Optimizer.
 * Applies performance optimizations via WordPress hooks.
 */
class BDSO_Optimizer {

    /** @var BDSO_Optimizer|null */
    private static $instance = null;

    /** @var array Cached settings. */
    private $settings = array();

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only run on frontend, not admin or AJAX.
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        // Only apply optimizations if license is active.
        add_action( 'wp', array( $this, 'init_optimizations' ) );
    }

    /**
     * Initialize optimizations after WP is loaded (so we can check license).
     */
    public function init_optimizations() {
        if ( ! BDSO_License::is_active() ) {
            return;
        }

        $this->settings = get_option( 'bdso_settings', bdso_get_defaults() );

        // Defer JS.
        if ( ! empty( $this->settings['defer_js'] ) ) {
            add_filter( 'script_loader_tag', array( $this, 'defer_js' ), 10, 3 );
        }

        // Delay JS.
        if ( ! empty( $this->settings['delay_js'] ) ) {
            add_filter( 'script_loader_tag', array( $this, 'delay_js' ), 11, 3 );
            add_action( 'wp_footer', array( $this, 'output_delay_loader' ), 999 );
        }

        // Remove jQuery Migrate.
        if ( ! empty( $this->settings['remove_jquery_migrate'] ) ) {
            add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
        }

        // Minify HTML.
        if ( ! empty( $this->settings['minify_html'] ) ) {
            add_action( 'template_redirect', array( $this, 'start_html_minify' ), 1 );
        }

        // Lazy load images.
        if ( ! empty( $this->settings['lazy_load_images'] ) ) {
            add_filter( 'the_content', array( $this, 'lazy_load_images' ), 99 );
            add_filter( 'post_thumbnail_html', array( $this, 'lazy_load_images' ), 99 );
        }

        // Lazy load iframes.
        if ( ! empty( $this->settings['lazy_load_iframes'] ) ) {
            add_filter( 'the_content', array( $this, 'lazy_load_iframes' ), 99 );
        }

        // Disable emojis.
        if ( ! empty( $this->settings['disable_emojis'] ) ) {
            $this->disable_emojis();
        }

        // Disable embeds.
        if ( ! empty( $this->settings['disable_embeds'] ) ) {
            $this->disable_embeds();
        }

        // Remove query strings.
        if ( ! empty( $this->settings['remove_query_strings'] ) ) {
            add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ), 10, 2 );
            add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ), 10, 2 );
        }

        // DNS Prefetch.
        $dns = trim( $this->settings['dns_prefetch'] ?? '' );
        if ( ! empty( $dns ) ) {
            add_action( 'wp_head', array( $this, 'output_dns_prefetch' ), 1 );
        }

        // Preconnect.
        $preconnect = trim( $this->settings['preconnect_domains'] ?? '' );
        if ( ! empty( $preconnect ) ) {
            add_action( 'wp_head', array( $this, 'output_preconnect' ), 1 );
        }
    }

    // ─── Defer JS ───────────────────────────────────────────────────

    public function defer_js( $tag, $handle, $src ) {
        // Skip jQuery core — many plugins depend on it being available immediately.
        if ( 'jquery-core' === $handle || 'jquery' === $handle ) {
            return $tag;
        }

        // Skip WooCommerce scripts — required for product pages, cart, checkout.
        if ( strpos( $handle, 'wc-' ) === 0 || strpos( $handle, 'woocommerce' ) === 0 ) {
            return $tag;
        }

        // Skip if already has defer or async.
        if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, ' async' ) !== false ) {
            return $tag;
        }

        return str_replace( ' src=', ' defer src=', $tag );
    }

    // ─── Delay JS ───────────────────────────────────────────────────

    public function delay_js( $tag, $handle, $src ) {
        // Skip jQuery core.
        if ( 'jquery-core' === $handle || 'jquery' === $handle ) {
            return $tag;
        }

        // Skip WooCommerce scripts — required for product pages, cart, checkout.
        if ( strpos( $handle, 'wc-' ) === 0 || strpos( $handle, 'woocommerce' ) === 0 ) {
            return $tag;
        }

        // Skip active theme scripts — themes often use IntersectionObserver for
        // scroll-reveal animations (opacity:0 → visible). Delaying these causes
        // content to stay invisible until user interaction.
        $theme_url = get_template_directory_uri();
        $child_url = get_stylesheet_directory_uri();
        if ( strpos( $src, $theme_url ) !== false || strpos( $src, $child_url ) !== false ) {
            return $tag;
        }

        // Skip if already delayed or is the delay loader itself.
        if ( strpos( $tag, 'data-bdso-src' ) !== false ) {
            return $tag;
        }

        // Replace src with data-bdso-src.
        $tag = preg_replace( '/\ssrc=[\'"]([^\'"]+)[\'"]/', ' data-bdso-src="$1"', $tag );

        // Remove defer if we added it (delay takes precedence).
        $tag = str_replace( ' defer data-bdso-src=', ' data-bdso-src=', $tag );

        return $tag;
    }

    /**
     * Output the delay loader script in wp_footer.
     */
    public function output_delay_loader() {
        ?>
        <style>
        /* Safety net: ensure scroll-reveal elements become visible even if delayed JS hasn't loaded them yet */
        .fade-in, .animate-on-scroll, [data-animate], .reveal, .scroll-reveal {
            animation: bdso-safety-reveal 0s 3s forwards;
        }
        .fade-in.visible, .animate-on-scroll.animated, .reveal.revealed, .scroll-reveal.is-visible {
            animation: none;
        }
        @keyframes bdso-safety-reveal { to { opacity: 1 !important; transform: none !important; } }
        </style>
        <script>
        (function(){
            var loaded = false;
            function loadAll(){
                if(loaded)return;
                loaded=true;
                document.querySelectorAll('script[data-bdso-src]').forEach(function(el){
                    var s=document.createElement('script');
                    s.src=el.getAttribute('data-bdso-src');
                    if(el.getAttribute('id'))s.id=el.getAttribute('id');
                    el.parentNode.replaceChild(s,el);
                });
            }
            ['mouseover','scroll','keydown','touchstart'].forEach(function(e){
                window.addEventListener(e,loadAll,{once:true,passive:true});
            });
            setTimeout(loadAll,5000);
        })();
        </script>
        <?php
    }

    // ─── Remove jQuery Migrate ──────────────────────────────────────

    public function remove_jquery_migrate( $scripts ) {
        if ( ! empty( $scripts->registered['jquery'] ) ) {
            $scripts->registered['jquery']->deps = array_diff(
                $scripts->registered['jquery']->deps,
                array( 'jquery-migrate' )
            );
        }
    }

    // ─── Minify HTML ────────────────────────────────────────────────

    public function start_html_minify() {
        ob_start( array( $this, 'minify_html_callback' ) );
    }

    public function minify_html_callback( $html ) {
        if ( empty( $html ) ) {
            return $html;
        }

        // Remove HTML comments (except IE conditionals and script/style content).
        $html = preg_replace( '/<!--(?!\[if)(?!<!)[^\[>].*?-->/s', '', $html );

        // Remove extra whitespace between tags.
        $html = preg_replace( '/>\s+</', '> <', $html );

        // Collapse multiple spaces.
        $html = preg_replace( '/\s{2,}/', ' ', $html );

        return $html;
    }

    // ─── Lazy Load Images ───────────────────────────────────────────

    public function lazy_load_images( $content ) {
        if ( empty( $content ) ) {
            return $content;
        }

        // Add loading="lazy" to img tags that don't already have it.
        $content = preg_replace(
            '/<img(?![^>]*loading=)([^>]*)>/i',
            '<img loading="lazy"$1>',
            $content
        );

        return $content;
    }

    // ─── Lazy Load Iframes ──────────────────────────────────────────

    public function lazy_load_iframes( $content ) {
        if ( empty( $content ) ) {
            return $content;
        }

        // Add loading="lazy" to iframe tags that don't already have it.
        $content = preg_replace(
            '/<iframe(?![^>]*loading=)([^>]*)>/i',
            '<iframe loading="lazy"$1>',
            $content
        );

        return $content;
    }

    // ─── Disable Emojis ─────────────────────────────────────────────

    private function disable_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
        add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_dns_prefetch' ), 10, 2 );
    }

    public function disable_emojis_tinymce( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        }
        return array();
    }

    public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, function( $url ) {
                return strpos( $url, 'https://s.w.org/images/core/emoji/' ) === false;
            } );
        }
        return $urls;
    }

    // ─── Disable Embeds ─────────────────────────────────────────────

    private function disable_embeds() {
        remove_action( 'rest_api_init', 'wp_oembed_register_route' );
        remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );
        add_filter( 'tiny_mce_plugins', array( $this, 'disable_embeds_tinymce' ) );
        add_filter( 'rewrite_rules_array', array( $this, 'disable_embeds_rewrites' ) );

        wp_deregister_script( 'wp-embed' );
    }

    public function disable_embeds_tinymce( $plugins ) {
        return array_diff( $plugins, array( 'wpembed' ) );
    }

    public function disable_embeds_rewrites( $rules ) {
        foreach ( $rules as $rule => $rewrite ) {
            if ( strpos( $rewrite, 'embed=true' ) !== false ) {
                unset( $rules[ $rule ] );
            }
        }
        return $rules;
    }

    // ─── Remove Query Strings ───────────────────────────────────────

    public function remove_query_strings( $src, $handle ) {
        if ( strpos( $src, '?ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    // ─── DNS Prefetch ───────────────────────────────────────────────

    public function output_dns_prefetch() {
        $domains = $this->parse_domains( $this->settings['dns_prefetch'] ?? '' );
        foreach ( $domains as $domain ) {
            printf( '<link rel="dns-prefetch" href="%s">' . "\n", esc_url( $domain ) );
        }
    }

    // ─── Preconnect ─────────────────────────────────────────────────

    public function output_preconnect() {
        $domains = $this->parse_domains( $this->settings['preconnect_domains'] ?? '' );
        foreach ( $domains as $domain ) {
            printf( '<link rel="preconnect" href="%s" crossorigin>' . "\n", esc_url( $domain ) );
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function parse_domains( $text ) {
        $lines = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
        $domains = array();
        foreach ( $lines as $line ) {
            // Ensure it starts with // or https://.
            if ( strpos( $line, '//' ) === false ) {
                $line = '//' . $line;
            }
            $domains[] = $line;
        }
        return $domains;
    }
}

// Initialize optimizer.
BDSO_Optimizer::instance();
