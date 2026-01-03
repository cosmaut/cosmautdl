=== CosmautDL ===
Contributors: cosmaut
Donate link: https://cosmaut.com/
Tags: download, cloud storage, baiduyun, aliyun, netdisk
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Organize multi-cloud download links with unified cards, download pages, file tree, and basic stats (simple).

== Description ==

CosmautDL is a multi-cloud download manager for WordPress.
It helps you organize multiple cloud storage links into a unified download experience with a consistent download card UI, a dedicated download page, optional unlock verification, a file-tree browser, and basic download statistics.

Main features:

* Manage multiple cloud storage providers (built-in and custom) from a single settings page
* Generate a unified download card style for posts and pages
* Provide a dedicated download page route (supports pretty permalinks and plain links)
* Offer a file-tree page to browse all shared files on your site
* Record download clicks in a custom database table for statistics
* Load frontend assets only when needed

== External Services ==

CosmautDL can optionally connect to WeChat (微信) APIs only when you enable and configure the “WeChat unlock” mode in the plugin settings.

When enabled, the plugin sends requests to:
* https://open.weixin.qq.com/ (OAuth)
* https://api.weixin.qq.com/ (OAuth token and user subscription status)

Data sent may include:
* Your WeChat AppID/AppSecret (configured by site admin)
* The OAuth `code` and related parameters required by WeChat

The WeChat mode is not required for the core download card / download page / file tree features.

== Installation ==

1. Upload the `cosmautdl` folder to `/wp-content/plugins/`.
2. Activate the plugin in “Plugins”.
3. Go to “Settings - CosmautDL” and configure providers and display options.
4. Edit a post/page and fill in the download fields to generate the download card and download page.

== Frequently Asked Questions ==

= Does this plugin depend on a specific theme? =

No. CosmautDL uses WordPress routing and template hooks and aims to be compatible with most themes.

= Is multisite supported? =

CosmautDL is primarily tested on single-site installations. For multisite, please test in a staging site first.

== Screenshots ==

1. Download card UI
2. Download meta box in the post editor
3. File tree page

== Changelog ==

= 1.0.3 =
* Add smart recognition of cloud drive links in the editor meta box.（增加网盘链接智能识别）

= 1.0.2 =
* Initial release with download page, file tree, and click statistics.
