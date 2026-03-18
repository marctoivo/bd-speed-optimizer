<?php
/**
 * Dashboard view for BD Speed Optimizer.
 *
 * @package BD_Speed_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'scanner';
$license_active = BDSO_License::is_active();
$license_info   = BDSO_License::get_info();
$license_tier   = $license_info['tier'] ?? 'starter';
$db_counts      = BDSO_Database::get_counts();
?>

<div class="wrap bdso-wrap">

    <!-- Header -->
    <div class="bdso-header">
        <div class="bdso-header-left">
            <div class="bdso-logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="6" fill="#00D4FF" fill-opacity="0.15"/>
                    <path d="M16 6L22 12L16 10L10 12L16 6Z" fill="#00D4FF" fill-opacity="0.6"/>
                    <path d="M16 10L22 12V20L16 26L10 20V12L16 10Z" stroke="#00D4FF" stroke-width="1.5" fill="none"/>
                    <path d="M16 16L20 14V18L16 22L12 18V14L16 16Z" fill="#00D4FF" fill-opacity="0.3"/>
                    <circle cx="16" cy="16" r="2" fill="#00D4FF"/>
                </svg>
            </div>
            <div>
                <h1 class="bdso-title">BD <span class="bdso-accent">Speed Optimizer</span></h1>
                <span class="bdso-version">v<?php echo esc_html( BDSO_VERSION ); ?></span>
            </div>
        </div>
        <div class="bdso-header-right">
            <?php if ( $license_active ) : ?>
                <span class="bdso-status-pill active">
                    <span class="bdso-pulse"></span>
                    Licensed
                </span>
            <?php else : ?>
                <span class="bdso-status-pill inactive">Unlicensed</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bdso-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bd-speed-optimizer&tab=scanner' ) ); ?>" class="bdso-tab <?php echo 'scanner' === $active_tab ? 'active' : ''; ?>">
            <span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Scanner', 'bd-speed-optimizer' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bd-speed-optimizer&tab=settings' ) ); ?>" class="bdso-tab <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'bd-speed-optimizer' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bd-speed-optimizer&tab=license' ) ); ?>" class="bdso-tab <?php echo 'license' === $active_tab ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'License', 'bd-speed-optimizer' ); ?>
        </a>
    </div>

    <!-- Toast notification -->
    <div id="bdso-toast" class="bdso-toast" style="display:none;"></div>

    <?php if ( ! $license_active && 'license' !== $active_tab ) : ?>
    <div class="bdso-license-notice">
        <span class="dashicons dashicons-warning"></span>
        <div>
            <strong><?php esc_html_e( 'License Required', 'bd-speed-optimizer' ); ?></strong>
            <p><?php esc_html_e( 'Frontend optimizations are disabled. Activate your license key to enable them. You can still scan and clean the database.', 'bd-speed-optimizer' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bd-speed-optimizer&tab=license' ) ); ?>"><?php esc_html_e( 'Activate License', 'bd-speed-optimizer' ); ?></a> |
            <a href="https://getbdshield.com/shop/" target="_blank"><?php esc_html_e( 'Purchase License', 'bd-speed-optimizer' ); ?></a></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( 'scanner' === $active_tab ) : ?>
    <!-- ═══════════════ SCANNER TAB ═══════════════ -->
    <div class="bdso-scanner-tab">

        <!-- Score Circle -->
        <div class="bdso-card bdso-score-card">
            <div class="bdso-card-body" style="text-align:center;padding:40px 24px;">
                <div class="bdso-score-circle" id="bdso-score-circle">
                    <svg width="160" height="160" viewBox="0 0 160 160">
                        <circle cx="80" cy="80" r="70" fill="none" stroke="#21262D" stroke-width="8"/>
                        <circle id="bdso-score-ring" cx="80" cy="80" r="70" fill="none" stroke="url(#bdso-gradient)" stroke-width="8"
                                stroke-linecap="round" stroke-dasharray="0 440" transform="rotate(-90 80 80)"/>
                        <defs>
                            <linearGradient id="bdso-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#00D4FF"/>
                                <stop offset="100%" stop-color="#7B61FF"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="bdso-score-number" id="bdso-score-number">--</div>
                    <div class="bdso-score-label">Speed Score</div>
                </div>
                <button type="button" class="bdso-btn bdso-btn-primary bdso-btn-lg" id="bdso-scan-btn" style="margin-top:24px;">
                    <span class="dashicons dashicons-search"></span> Run Scan
                </button>
            </div>
        </div>

        <!-- Results (hidden until scan) -->
        <div id="bdso-results" style="display:none;">

            <!-- Frontend Checks -->
            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3>Frontend Optimizations</h3>
                </div>
                <div class="bdso-card-body" style="padding:0;">
                    <div id="bdso-frontend-checks" class="bdso-checklist"></div>
                </div>
            </div>

            <!-- Database Checks -->
            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3>Database Cleanup</h3>
                </div>
                <div class="bdso-card-body" style="padding:0;">
                    <div id="bdso-database-checks" class="bdso-checklist"></div>

                    <!-- DB Cleanup Actions -->
                    <div class="bdso-db-actions">
                        <div class="bdso-db-action-row">
                            <div class="bdso-db-action-info">
                                <strong>Post Revisions</strong>
                                <span class="bdso-db-count" id="bdso-count-revisions"><?php echo esc_html( $db_counts['revisions'] ); ?></span>
                            </div>
                            <button type="button" class="bdso-btn bdso-btn-ghost bdso-btn-sm bdso-clean-btn" data-action="bdso_clean_revisions" <?php echo $db_counts['revisions'] === 0 ? 'disabled' : ''; ?>>Clean Now</button>
                        </div>
                        <div class="bdso-db-action-row">
                            <div class="bdso-db-action-info">
                                <strong>Spam/Trash Comments</strong>
                                <span class="bdso-db-count" id="bdso-count-spam"><?php echo esc_html( $db_counts['spam_comments'] ); ?></span>
                            </div>
                            <button type="button" class="bdso-btn bdso-btn-ghost bdso-btn-sm bdso-clean-btn" data-action="bdso_clean_spam" <?php echo $db_counts['spam_comments'] === 0 ? 'disabled' : ''; ?>>Clean Now</button>
                        </div>
                        <div class="bdso-db-action-row">
                            <div class="bdso-db-action-info">
                                <strong>Expired Transients</strong>
                                <span class="bdso-db-count" id="bdso-count-transients"><?php echo esc_html( $db_counts['expired_transients'] ); ?></span>
                            </div>
                            <button type="button" class="bdso-btn bdso-btn-ghost bdso-btn-sm bdso-clean-btn" data-action="bdso_clean_transients" <?php echo $db_counts['expired_transients'] === 0 ? 'disabled' : ''; ?>>Clean Now</button>
                        </div>
                        <div class="bdso-db-action-row">
                            <div class="bdso-db-action-info">
                                <strong>Optimize Tables</strong>
                                <span class="bdso-db-count" style="background:rgba(0,212,255,0.1);color:var(--bdso-primary);">Tune-up</span>
                            </div>
                            <button type="button" class="bdso-btn bdso-btn-ghost bdso-btn-sm" id="bdso-optimize-tables">Optimize</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php elseif ( 'settings' === $active_tab ) : ?>
    <!-- ═══════════════ SETTINGS TAB ═══════════════ -->
    <div class="bdso-settings">
        <form id="bdso-settings-form">

            <!-- Frontend Optimization Toggles -->
            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3><?php esc_html_e( 'JavaScript', 'bd-speed-optimizer' ); ?></h3>
                </div>
                <div class="bdso-card-body">
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[defer_js]" value="1" <?php checked( ! empty( $settings['defer_js'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Defer JavaScript Loading', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Add <code>defer</code> attribute to non-critical scripts. jQuery core is excluded.</p>
                    </div>
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[delay_js]" value="1" <?php checked( ! empty( $settings['delay_js'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Delay JS Execution', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Delay loading of scripts until first user interaction (scroll, click, touch). Improves initial page load.</p>
                    </div>
                    <div class="bdso-setting-row" style="border-bottom:none;">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[remove_jquery_migrate]" value="1" <?php checked( ! empty( $settings['remove_jquery_migrate'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Remove jQuery Migrate', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Remove the jQuery Migrate script. Only disable if your theme/plugins don't rely on deprecated jQuery functions.</p>
                    </div>
                </div>
            </div>

            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3><?php esc_html_e( 'HTML & Media', 'bd-speed-optimizer' ); ?></h3>
                </div>
                <div class="bdso-card-body">
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[minify_html]" value="1" <?php checked( ! empty( $settings['minify_html'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Minify HTML Output', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Strip whitespace and comments from HTML output to reduce page size.</p>
                    </div>
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[lazy_load_images]" value="1" <?php checked( ! empty( $settings['lazy_load_images'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Lazy Load Images', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Add native <code>loading="lazy"</code> to images. Defers offscreen images until they're near the viewport.</p>
                    </div>
                    <div class="bdso-setting-row" style="border-bottom:none;">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[lazy_load_iframes]" value="1" <?php checked( ! empty( $settings['lazy_load_iframes'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Lazy Load Iframes', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Add native <code>loading="lazy"</code> to iframes (YouTube, maps, etc.).</p>
                    </div>
                </div>
            </div>

            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3><?php esc_html_e( 'Cleanup', 'bd-speed-optimizer' ); ?></h3>
                </div>
                <div class="bdso-card-body">
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[disable_emojis]" value="1" <?php checked( ! empty( $settings['disable_emojis'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Disable WordPress Emojis', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Remove emoji detection script and styles. Saves an extra HTTP request on every page.</p>
                    </div>
                    <div class="bdso-setting-row">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[disable_embeds]" value="1" <?php checked( ! empty( $settings['disable_embeds'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Disable WordPress Embeds', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Remove oEmbed discovery, scripts, and rewrite rules. Disable if you don't embed WordPress posts on other sites.</p>
                    </div>
                    <div class="bdso-setting-row" style="border-bottom:none;">
                        <label class="bdso-toggle-label">
                            <input type="checkbox" name="settings[remove_query_strings]" value="1" <?php checked( ! empty( $settings['remove_query_strings'] ) ); ?> />
                            <span class="bdso-toggle"></span>
                            <strong><?php esc_html_e( 'Remove Query Strings', 'bd-speed-optimizer' ); ?></strong>
                        </label>
                        <p class="bdso-setting-desc">Strip <code>?ver=</code> from CSS/JS URLs. Improves caching by CDNs and proxies.</p>
                    </div>
                </div>
            </div>

            <div class="bdso-card">
                <div class="bdso-card-header">
                    <h3><?php esc_html_e( 'Resource Hints', 'bd-speed-optimizer' ); ?></h3>
                </div>
                <div class="bdso-card-body">
                    <div class="bdso-setting-row">
                        <label><strong><?php esc_html_e( 'DNS Prefetch Domains', 'bd-speed-optimizer' ); ?></strong></label>
                        <textarea name="settings[dns_prefetch]" class="bdso-input" rows="3" style="max-width:500px;" placeholder="fonts.googleapis.com&#10;cdn.example.com"><?php echo esc_textarea( $settings['dns_prefetch'] ?? '' ); ?></textarea>
                        <p class="bdso-setting-desc">One domain per line. Pre-resolves DNS for external domains used on your pages.</p>
                    </div>
                    <div class="bdso-setting-row" style="border-bottom:none;">
                        <label><strong><?php esc_html_e( 'Preconnect Domains', 'bd-speed-optimizer' ); ?></strong></label>
                        <textarea name="settings[preconnect_domains]" class="bdso-input" rows="3" style="max-width:500px;" placeholder="fonts.gstatic.com&#10;api.example.com"><?php echo esc_textarea( $settings['preconnect_domains'] ?? '' ); ?></textarea>
                        <p class="bdso-setting-desc">One domain per line. Establishes early connections (DNS + TCP + TLS) to critical external origins.</p>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="bdso-save-bar">
                <button type="submit" class="bdso-btn bdso-btn-primary bdso-btn-lg" id="bdso-save-btn">
                    <span class="dashicons dashicons-saved"></span> Save Settings
                </button>
            </div>
        </form>
    </div>

    <?php elseif ( 'license' === $active_tab ) : ?>
    <!-- ═══════════════ LICENSE TAB ═══════════════ -->
    <div class="bdso-license-tab">

        <div class="bdso-card">
            <div class="bdso-card-header">
                <h3>License Key</h3>
                <?php if ( $license_active ) : ?>
                    <?php
                    $tier_class = 'bdso-tier-starter';
                    if ( 'professional' === $license_tier ) $tier_class = 'bdso-tier-pro';
                    if ( 'agency' === $license_tier ) $tier_class = 'bdso-tier-agency';
                    ?>
                    <span class="bdso-tier-badge <?php echo esc_attr( $tier_class ); ?>"><?php echo esc_html( ucfirst( $license_tier ) ); ?></span>
                <?php endif; ?>
            </div>
            <div class="bdso-card-body">
                <?php if ( $license_active ) : ?>
                    <div class="bdso-license-input-row">
                        <input type="text" id="bdso-license-key" value="<?php echo esc_attr( substr( $settings['license_key'] ?? '', 0, 8 ) . '...' ); ?>" class="bdso-input" readonly style="max-width:320px;" />
                        <button id="bdso-deactivate-license" class="bdso-btn bdso-btn-danger bdso-btn-sm">Deactivate</button>
                        <span id="bdso-license-status"></span>
                    </div>
                    <div class="bdso-license-meta">
                        <?php if ( ! empty( $license_info['expires_at'] ) ) : ?>
                            <span>Expires: <strong><?php echo esc_html( $license_info['expires_at'] ); ?></strong></span>
                        <?php endif; ?>
                        <?php if ( isset( $license_info['activated_count'] ) && isset( $license_info['max_sites'] ) ) : ?>
                            <span>Sites: <strong><?php echo esc_html( $license_info['activated_count'] . ' / ' . ( $license_info['max_sites'] ?: 'Unlimited' ) ); ?></strong></span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="bdso-license-input-row">
                        <input type="text" id="bdso-license-key" placeholder="Enter your license key..." class="bdso-input" style="max-width:320px;" />
                        <button id="bdso-activate-license" class="bdso-btn bdso-btn-primary">Activate</button>
                        <span id="bdso-license-status"></span>
                    </div>
                    <p style="color:var(--bdso-muted);font-size:13px;margin-top:12px;">
                        Enter a valid license key to enable frontend optimizations.
                        <a href="https://getbdshield.com/shop/" target="_blank" style="color:var(--bdso-primary);">Purchase a license</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bdso-card">
            <div class="bdso-card-header">
                <h3>Plan Comparison</h3>
            </div>
            <div class="bdso-card-body">
                <table class="bdso-table" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Starter</th>
                            <th>Professional</th>
                            <th>Agency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sites</td>
                            <td>1</td>
                            <td>3</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>Frontend Optimizations</td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                        </tr>
                        <tr>
                            <td>Database Cleanup</td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                        </tr>
                        <tr>
                            <td>Speed Scanner</td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                        </tr>
                        <tr>
                            <td>Priority Support</td>
                            <td><span style="color:var(--bdso-muted);">--</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                            <td><span class="bdso-badge bdso-badge-success">Yes</span></td>
                        </tr>
                    </tbody>
                </table>
                <div style="text-align:center;margin-top:16px;">
                    <a href="https://getbdshield.com/shop/" target="_blank" style="color:var(--bdso-primary);font-weight:500;font-size:14px;">View all plans &rarr;</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="bdso-footer">
        <span>BD Speed Optimizer &mdash; Powered by BestDid</span>
    </div>

</div>
