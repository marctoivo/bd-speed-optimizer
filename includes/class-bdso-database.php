<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Database cleanup operations for BD Speed Optimizer.
 * Static methods for one-time cleanup actions (AJAX-triggered).
 */
class BDSO_Database {

    /**
     * Get counts of cleanable items.
     *
     * @return array
     */
    public static function get_counts() {
        global $wpdb;

        $revisions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        $spam_comments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')"
        );

        $time = time();
        $expired_transients = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_value < %d",
            $wpdb->esc_like( '_transient_timeout_' ) . '%',
            $time
        ) );

        return array(
            'revisions'          => $revisions,
            'spam_comments'      => $spam_comments,
            'expired_transients' => $expired_transients,
        );
    }

    /**
     * Delete all post revisions.
     *
     * @return int Number of deleted revisions.
     */
    public static function clean_revisions() {
        global $wpdb;

        // Delete revision meta first.
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'revision'"
        );

        $deleted = (int) $wpdb->query(
            "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        return $deleted;
    }

    /**
     * Delete spam and trash comments.
     *
     * @return int Number of deleted comments.
     */
    public static function clean_spam_comments() {
        global $wpdb;

        // Delete comment meta first.
        $wpdb->query(
            "DELETE cm FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
             WHERE c.comment_approved IN ('spam', 'trash')"
        );

        $deleted = (int) $wpdb->query(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')"
        );

        return $deleted;
    }

    /**
     * Delete expired transients.
     *
     * @return int Number of deleted transients.
     */
    public static function clean_transients() {
        global $wpdb;

        $time = time();

        // Get expired transient names.
        $expired = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_value < %d",
            $wpdb->esc_like( '_transient_timeout_' ) . '%',
            $time
        ) );

        $count = 0;
        foreach ( $expired as $transient_timeout ) {
            $transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
            delete_transient( $transient_name );
            $count++;
        }

        return $count;
    }

    /**
     * Optimize all WordPress database tables.
     *
     * @return int Number of tables optimized.
     */
    public static function optimize_tables() {
        global $wpdb;

        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
        $count  = 0;

        foreach ( $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE `{$table}`" );
            $count++;
        }

        return $count;
    }
}
