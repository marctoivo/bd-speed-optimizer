<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Performance scanner for BD Speed Optimizer.
 * Runs checks and calculates a 0-100 speed score.
 */
class BDSO_Scanner {

    /**
     * Run all checks and return score + results.
     *
     * @return array { score: int, checks: array }
     */
    public static function scan() {
        $settings = get_option( 'bdso_settings', bdso_get_defaults() );
        $checks   = array();

        // ── Frontend Checks ──────────────────────────────────────────

        $checks[] = self::check_setting( $settings, 'defer_js', 'Defer JavaScript Loading', 'frontend', 8,
            'JavaScript files are deferred.',
            'JavaScript files are not deferred. Enable to improve page load speed.'
        );

        $checks[] = self::check_setting( $settings, 'delay_js', 'Delay JS Execution', 'frontend', 7,
            'JavaScript execution is delayed until user interaction.',
            'JavaScript executes immediately on page load. Enable to defer until interaction.'
        );

        $checks[] = self::check_setting( $settings, 'remove_jquery_migrate', 'Remove jQuery Migrate', 'frontend', 6,
            'jQuery Migrate is removed.',
            'jQuery Migrate is loaded. Remove it to reduce page weight.'
        );

        $checks[] = self::check_setting( $settings, 'minify_html', 'Minify HTML Output', 'frontend', 7,
            'HTML output is minified.',
            'HTML output contains extra whitespace and comments.'
        );

        $checks[] = self::check_setting( $settings, 'lazy_load_images', 'Lazy Load Images', 'frontend', 8,
            'Images use lazy loading.',
            'Images load immediately. Enable lazy loading to improve initial page speed.'
        );

        $checks[] = self::check_setting( $settings, 'lazy_load_iframes', 'Lazy Load Iframes', 'frontend', 6,
            'Iframes use lazy loading.',
            'Iframes load immediately. Enable lazy loading to reduce page weight.'
        );

        $checks[] = self::check_setting( $settings, 'disable_emojis', 'Disable WordPress Emojis', 'frontend', 7,
            'WordPress emoji scripts are disabled.',
            'WordPress emoji scripts are loaded. Disable to save HTTP requests.'
        );

        $checks[] = self::check_setting( $settings, 'disable_embeds', 'Disable WordPress Embeds', 'frontend', 6,
            'WordPress embed scripts are disabled.',
            'WordPress embed scripts are loaded. Disable if not using oEmbed.'
        );

        $checks[] = self::check_setting( $settings, 'remove_query_strings', 'Remove Query Strings', 'frontend', 5,
            'Query strings removed from static resources.',
            'Static resources have ?ver= query strings. Remove for better caching.'
        );

        // DNS Prefetch.
        $dns = trim( $settings['dns_prefetch'] ?? '' );
        $checks[] = array(
            'id'       => 'dns_prefetch',
            'label'    => 'DNS Prefetch Configured',
            'category' => 'frontend',
            'status'   => ! empty( $dns ) ? 'pass' : 'warning',
            'weight'   => 5,
            'message'  => ! empty( $dns ) ? 'DNS prefetch domains configured.' : 'No DNS prefetch domains configured. Add external domains to speed up DNS lookups.',
            'fix_key'  => null,
        );

        // Preconnect.
        $preconnect = trim( $settings['preconnect_domains'] ?? '' );
        $checks[] = array(
            'id'       => 'preconnect',
            'label'    => 'Preconnect Configured',
            'category' => 'frontend',
            'status'   => ! empty( $preconnect ) ? 'pass' : 'warning',
            'weight'   => 5,
            'message'  => ! empty( $preconnect ) ? 'Preconnect domains configured.' : 'No preconnect domains configured. Add external domains for faster connections.',
            'fix_key'  => null,
        );

        // ── Database Checks ─────────────────────────────────────────

        $counts = BDSO_Database::get_counts();

        $checks[] = array(
            'id'       => 'post_revisions',
            'label'    => 'Post Revisions Cleaned',
            'category' => 'database',
            'status'   => $counts['revisions'] === 0 ? 'pass' : 'fail',
            'weight'   => 10,
            'message'  => $counts['revisions'] === 0
                ? 'No post revisions found.'
                : sprintf( '%d post revisions found. Clean them to reduce database size.', $counts['revisions'] ),
            'fix_key'  => null,
            'count'    => $counts['revisions'],
        );

        $checks[] = array(
            'id'       => 'spam_comments',
            'label'    => 'Spam/Trash Comments Cleaned',
            'category' => 'database',
            'status'   => $counts['spam_comments'] === 0 ? 'pass' : 'fail',
            'weight'   => 8,
            'message'  => $counts['spam_comments'] === 0
                ? 'No spam or trash comments found.'
                : sprintf( '%d spam/trash comments found. Clean them to reduce database size.', $counts['spam_comments'] ),
            'fix_key'  => null,
            'count'    => $counts['spam_comments'],
        );

        $checks[] = array(
            'id'       => 'transients',
            'label'    => 'Expired Transients Cleaned',
            'category' => 'database',
            'status'   => $counts['expired_transients'] === 0 ? 'pass' : 'fail',
            'weight'   => 7,
            'message'  => $counts['expired_transients'] === 0
                ? 'No expired transients found.'
                : sprintf( '%d expired transients found. Clean them to optimize the options table.', $counts['expired_transients'] ),
            'fix_key'  => null,
            'count'    => $counts['expired_transients'],
        );

        // ── Calculate Score ──────────────────────────────────────────

        $score = 0;
        foreach ( $checks as $check ) {
            if ( 'pass' === $check['status'] ) {
                $score += $check['weight'];
            }
        }

        // Cap at 100.
        $score = min( 100, $score );

        return array(
            'score'  => $score,
            'checks' => $checks,
        );
    }

    /**
     * Helper to check a boolean setting.
     */
    private static function check_setting( $settings, $key, $label, $category, $weight, $pass_msg, $fail_msg ) {
        $enabled = ! empty( $settings[ $key ] );
        return array(
            'id'       => $key,
            'label'    => $label,
            'category' => $category,
            'status'   => $enabled ? 'pass' : 'fail',
            'weight'   => $weight,
            'message'  => $enabled ? $pass_msg : $fail_msg,
            'fix_key'  => $key,
        );
    }
}
