=== BD Speed Optimizer ===
Contributors: bestdid
Tags: speed, performance, optimization, database, cleanup
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Performance scanner that analyzes your WordPress site, calculates a speed score, and provides toggle-based frontend optimizations and database cleanup.

== Description ==

BD Speed Optimizer scans your WordPress site and provides a 0-100 speed score based on 14 performance checks. Toggle frontend optimizations on/off and clean up your database with one click.

**Frontend Optimizations:**

* Defer JavaScript loading
* Delay JS execution until user interaction
* Remove jQuery Migrate
* Minify HTML output
* Lazy load images and iframes
* Disable WordPress emojis and embeds
* Remove query strings from static assets
* DNS prefetch and preconnect for external domains

**Database Cleanup:**

* Clean post revisions
* Remove spam and trash comments
* Delete expired transients
* Optimize database tables

== Installation ==

1. Upload the `bd-speed-optimizer` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to BD Speed > License and activate your license key
4. Run a scan from the Scanner tab
5. Enable optimizations from the Settings tab

== Changelog ==

= 1.0.0 =
* Initial release
