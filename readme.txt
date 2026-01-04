=== CosmautDL ===
Contributors: cosmaut
Donate link: https://cosmaut.com/cosmautdl/sponsor/
Tags: download, download-manager, file-download, download-page, cloud-drive, baidu-pan, aliyundrive, lanzou, quark, 123pan, file-tree, click-stats, wechat-unlock
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Multi-cloud download manager: unified download cards + download pages + file-tree index + click statistics. (多网盘下载管理：下载卡片 / 独立下载页 / 文件树 / 统计)

== Description ==

CosmautDL is a multi-cloud download manager plugin for WordPress.
It turns scattered cloud-drive links into a clean "download card" experience, and provides dedicated download pages, a site-wide file-tree index, and click statistics for site owners.

（中文概述）把零散的网盘链接统一整理成下载卡片，并提供独立下载页、文件树索引与下载统计，让资源分享更规范、更好用。

Key features (核心能力):

* Unified download card UI for posts/pages（下载卡片：文章/页面统一展示）
* Dedicated download page route per post（独立下载页：每篇文章一个下载页）
* Redirect route to keep outbound links tidy（跳转中转：可配置路由前缀）
* File-tree page to browse all shared resources（文件树：全站资源索引）
* Click statistics stored in your own database（下载统计：记录点击次数，后台可查看）
* Optional WeChat QR unlock workflow（可选微信扫码解锁）
* Assets loaded only when needed（按需加载，减少全站负担）

Supported providers (built-in defaults / 内置默认网盘):

Baidu Pan, 123Pan, Aliyun Drive, Tianyi Cloud, Quark, PikPak, Lanzou, Xunlei, Weiyun, OneDrive, Google Drive, Dropbox, MEGA, MediaFire, Box, and “Other”.

（说明）你可以在后台启用/禁用、重命名、排序网盘，并支持“其他网盘”用于自定义链接类型。

Routes (pretty permalinks recommended / 建议开启固定链接):

* Download page: /downloads/{post_id}.html (or ?cosmdl_download=1&post_id={id})
* File tree: /downloads/tree.html (or ?cosmdl_tree=1)
* Stats entry: /downloads/stats.html (or ?cosmdl_stats=1; redirects to wp-admin stats page)
* Redirect: /{prefix}/{post_id}/{type}.html (or ?cosmdl_redirect=1&post_id={id}&type={type})

Data & privacy (overview / 数据与隐私概述):

* Stores download click logs in a custom table: {wp_prefix}cosmdl_clicks (post_id, type, attach_id, user_id, ip, ua, referer, success, created_at).（用于统计；可在后台删除记录）
* Core features do not require external services.（核心功能不依赖外部服务）

== External Services ==

This plugin can connect to external services only when you enable related options.
（中文说明）仅在你启用相关功能后才会发起外部请求。

1) WeChat (微信) OAuth & subscription check - used for "WeChat unlock" mode.

* Service: WeChat / Tencent（微信/腾讯）
* Endpoints:
  * https://open.weixin.qq.com/ (OAuth authorize)
  * https://api.weixin.qq.com/ (OAuth token, access_token, user/info)
* When: Only if you enable "WeChat unlock" in CosmautDL settings and visitors scan the unlock QR code.
* Data sent: appid, appsecret, OAuth code, openid, and related request parameters.（由站点服务器向微信接口发起请求）
* Data stored: Does not store openid permanently; uses a short-lived transient unlock flag.（不长期保存 openid，仅短期缓存解锁状态）
* Privacy policy: https://www.tencent.com/en-us/privacy-policy.html

2) IP geolocation lookup — used to display “IP location” in admin download stats.

* Services (selectable in settings / 后台可选服务商):
  * https://ipapi.co/
  * http://ip-api.com/
  * https://ipinfo.io/
* When: Only if you enable “Show IP location in stats” and open stats details in wp-admin.
* Data sent: IP address from your click logs, from your server to the chosen provider (requests are cached).（仅用于展示归属地，且有缓存减少请求）
* Privacy policies:
  * ipapi: https://ipapi.co/privacy/
  * ipinfo: https://ipinfo.io/privacy-policy
  * ip-api docs: https://ip-api.com/docs/

== Installation ==

1. Upload the `cosmautdl` folder to `/wp-content/plugins/`.
2. Activate the plugin in “Plugins”.
3. Go to “CosmautDL” in wp-admin and configure:
   * Drive management（网盘管理：启用/重命名/排序）
   * Route prefix（跳转路由前缀）
   * Download page modules and optional unlock settings（下载页模块与可选解锁设置）
4. Edit a post/page, fill in cloud-drive links in the CosmautDL meta box, and publish.

== Frequently Asked Questions ==

= How do I add a download card to a post?（如何生成下载卡片？） =

Edit a post/page, find the CosmautDL meta box, paste cloud-drive links (and extraction codes if any), then update/publish. The plugin renders the card automatically on the frontend.

= I see 404 on /downloads/{id}.html. What should I do?（下载页 404 怎么办？） =

Go to “Settings → Permalinks” and click “Save Changes” once to refresh rewrite rules. Also ensure your site supports pretty permalinks.（建议开启固定链接）

= What data does CosmautDL store for statistics?（统计会记录哪些数据？） =

It stores click logs in your own database table, including post_id, drive type, IP, user agent, referer, timestamp, etc. This is used only for download statistics and can be deleted from the stats page.

= Does the plugin make external requests by default?（默认会对外请求吗？） =

No. External requests happen only if you enable:

* "WeChat unlock"（微信扫码解锁）
* "IP geolocation in stats"（统计页 IP 归属地展示）

= Can I customize the redirect prefix and drive list?（能自定义跳转前缀和网盘列表吗？） =

Yes. You can change the redirect prefix in settings, and enable/rename/reorder providers in “Drive management”. There is also an “Other” type for custom links.

= Does this plugin depend on a specific theme?（兼容主题吗？） =

No. CosmautDL uses WordPress routing and template hooks and aims to be compatible with most themes. If your theme or cache plugin is aggressive, clear caches after changes.

= Is multisite supported?（支持多站点吗？） =

CosmautDL is primarily tested on single-site installations. For multisite, please test in a staging site first.

== Screenshots ==

1. Download card UI（下载卡片样式）
2. Post editor meta box（编辑器下载面板）
3. File tree page（文件树页面）
4. Download statistics（下载统计）

== Changelog ==

= 1.0.3 =
* 2026-01-03 Smart recognition of cloud drive links in the editor meta box.（编辑器网盘链接智能识别）

= 1.0.2 =
* 2026-01-02 Initial release with download page, file tree, and click statistics.
