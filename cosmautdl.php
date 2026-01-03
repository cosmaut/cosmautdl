<?php
/*
Plugin Name: CosmautDL
Plugin URI: https://cosmaut.com/cosmautdl
Description: 专业的多网盘下载管理插件，支持百度网盘、阿里云盘、蓝奏云等主流网盘。提供智能下载卡片、扫码解锁、独立下载页面、下载统计等完整解决方案。采用现代化UI设计，支持自定义主题色和响应式布局。
Version: 1.0.3
Author: Cosmaut
Author URI: https://cosmaut.com/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: cosmautdl
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.4
*/
/**
 * 插件主文件：cosmautdl
 * 作用：插件核心入口，负责初始化多网盘下载管理系统。包括路由系统注册、后台管理界面加载、文章元框集成、前端模板分发、样式资源管理等核心功能。
 * 说明：本插采用现代化模块化架构，专注于多网盘下载管理场景。提供完整的下载解决方案，包括智能下载卡片、扫码解锁、独立下载页面等特色功能。
 * 作者：Cosmaut（cosmaut.com / cosmaut@hotmail.com）
 */

// 防止直接访问
if (!defined('ABSPATH')) { exit; }

// 插件常量（中文注释：提供目录与版本标识，便于后续引用与资源定位）
define('COSMDL_VERSION', '1.0.3');
define('COSMDL_PLUGIN_FILE', __FILE__);
define('COSMDL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COSMDL_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!function_exists('cosmdl_asset_version')) {
    function cosmdl_asset_version($relative_path) {
        $relative_path = ltrim((string) $relative_path, '/');
        $full_path = COSMDL_PLUGIN_DIR . $relative_path;
        if (is_file($full_path)) {
            $mtime = filemtime($full_path);
            if ($mtime) {
                return (string) $mtime;
            }
        }
        return defined('COSMDL_VERSION') ? COSMDL_VERSION : null;
    }
}

function cosmdl_admin_cap(){
    if (function_exists('current_user_can') && current_user_can('manage_options')) {
        return 'manage_options';
    }
    return 'edit_posts';
}

// 定义日志目录
if (!defined('COSMDL_LOG_DIR')) {
    $cosmdl_upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : array();
    $cosmdl_basedir = isset($cosmdl_upload_dir['basedir']) ? (string) $cosmdl_upload_dir['basedir'] : '';
    if ($cosmdl_basedir === '') {
        $cosmdl_basedir = WP_CONTENT_DIR . '/uploads';
    }
    define('COSMDL_LOG_DIR', trailingslashit($cosmdl_basedir) . 'cosmautdl-logs/');
}

if (!function_exists('cosmdl_get_filesystem')) {
    function cosmdl_get_filesystem() {
        static $fs = null;
        static $tried = false;

        if ($tried) {
            return $fs;
        }

        $tried = true;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $initialized = WP_Filesystem(false, false, true);
        if (!$initialized) {
            return null;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return null;
        }

        $fs = $wp_filesystem;
        return $fs;
    }
}

/**
 * 检查并创建日志目录
 * @return bool 目录是否存在或创建成功
 */
function cosmdl_ensure_log_dir() {
    $fs = cosmdl_get_filesystem();
    if (!$fs) {
        return false;
    }

    if (!$fs->is_dir(COSMDL_LOG_DIR)) {
        $fs->mkdir(COSMDL_LOG_DIR, defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755);
    }

    $htaccess_path = COSMDL_LOG_DIR . '.htaccess';
    if (!$fs->exists($htaccess_path)) {
        $fs->put_contents($htaccess_path, "Deny from all\n", defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
    }

    return $fs->exists(COSMDL_LOG_DIR) && $fs->is_writable(COSMDL_LOG_DIR);
}

/**
 * 日志记录函数
 * @param string $message 日志消息
 * @param string $type 日志类型 (info, warning, error, debug)
 * @param array $context 上下文数据
 * @return bool 日志是否成功写入
 */
function cosmdl_write_log($message, $type = 'info', $context = array()) {
    // 检查是否启用日志记录
    $options = get_option('cosmdl_options', array());
    
    if (!isset($options['enable_logging']) || $options['enable_logging'] !== 'yes') {
        return false;
    }
    
    if (!cosmdl_ensure_log_dir()) {
        return false;
    }

    $fs = cosmdl_get_filesystem();
    if (!$fs) {
        return false;
    }
    
    // 准备日志数据
    $timestamp = current_time('mysql');
    $user_id = get_current_user_id();
    $user_info = $user_id ? get_user_by('id', $user_id) : null;
    $username = $user_info ? $user_info->user_login : 'guest';
    
    // 格式化消息
    $log_message = "[$timestamp] [$type] [$username] $message";
    
    // 如果有上下文数据，进行序列化
    if (!empty($context)) {
        $context_json = wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        if (is_string($context_json) && $context_json !== '') {
            $log_message .= " | Context: " . $context_json;
        }
    }
    
    $log_file = COSMDL_LOG_DIR . 'activity.log';
    $limit = 5 * 1024 * 1024;

    $existing = '';
    if ($fs->exists($log_file)) {
        $existing = $fs->get_contents($log_file);
        if (!is_string($existing)) {
            $existing = '';
        }
    }

    $existing_size = 0;
    if (method_exists($fs, 'size') && $fs->exists($log_file)) {
        $size = $fs->size($log_file);
        $existing_size = is_numeric($size) ? (int) $size : 0;
    } else {
        $existing_size = strlen($existing);
    }

    if ($existing_size > $limit && $fs->exists($log_file)) {
        $backup = COSMDL_LOG_DIR . 'activity_' . gmdate('Ymd_His') . '.log';
        if (method_exists($fs, 'move')) {
            $fs->move($log_file, $backup, true);
        } else {
            $contents_to_move = $fs->get_contents($log_file);
            if (is_string($contents_to_move)) {
                $fs->put_contents($backup, $contents_to_move, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
            }
            $fs->delete($log_file);
        }
        $existing = '';
    }

    $new_contents = $existing . $log_message . "\n";
    return (bool) $fs->put_contents($log_file, $new_contents, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
}

/**
 * 调试信息输出函数
 * @param mixed $data 要输出的调试数据
 * @param string $label 数据标签
 * @return bool 是否输出了调试信息
 */
function cosmdl_debug($data, $label = 'Debug') {
    // 仅在开发环境下启用调试输出
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return false;
    }
    
    // 检查是否启用调试模式
    $options = get_option('cosmdl_options', array());
    if (!isset($options['debug_mode']) || $options['debug_mode'] !== 'yes') {
        return false;
    }
    
    $payload = array(
        'label' => (string) $label,
        'type'  => is_object($data) ? get_class($data) : gettype($data),
        'data'  => $data,
    );

    if (isset($options['enable_logging']) && $options['enable_logging'] === 'yes') {
        cosmdl_write_log((string) $label, 'debug', $payload);
    }
    
    return true;
}

function cosmdl_allow_wechat_redirect_hosts($hosts) {
    $hosts = is_array($hosts) ? $hosts : array();
    $hosts[] = 'open.weixin.qq.com';
    return array_values(array_unique(array_filter($hosts)));
}

/**
 * 统一错误处理函数 - 改进版本
 * 提供用户友好的错误页面和多种处理方式
 * @param string $message 错误消息
 * @param array $context 上下文信息
 * @param string $default 默认处理方式 (message|hide|redirect)
 */
function cosmdl_handle_error($message, $context = array(), $default = 'message') {
    // 记录错误信息到日志
    cosmdl_write_log($message, 'error', $context);
    
    // 调试输出错误信息
    cosmdl_debug(array(
        'message' => $message,
        'context' => $context
    ), '错误处理详情');
    
    // 读取插件选项
    $opts = get_option('cosmdl_options', array());
    $mode = isset($opts['error_handling']) ? $opts['error_handling'] : $default;
    
    // 根据错误类型确定错误代码
    $error_code = 'general';
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, '参数') !== false || strpos($message_lower, 'invalid') !== false) {
        $error_code = 'invalid_params';
    } elseif (strpos($message_lower, '不存在') !== false || strpos($message_lower, 'not found') !== false) {
        $error_code = 'download_not_found';
    } elseif (strpos($message_lower, '未知') !== false || strpos($message_lower, 'unknown') !== false) {
        $error_code = 'unknown_type';
    } elseif (strpos($message_lower, '网络') !== false || strpos($message_lower, 'network') !== false || strpos($message_lower, '连接') !== false) {
        $error_code = 'network_error';
    } elseif (strpos($message_lower, '权限') !== false || strpos($message_lower, 'unauthorized') !== false || strpos($message_lower, 'permission') !== false) {
        $error_code = 'unauthorized';
    }
    
    switch ($mode) {
        case 'hide':
            // 隐藏错误，静默失败
            break;
        case 'redirect':
            // 重定向到首页
            wp_safe_redirect(home_url());
            exit;
        default:
            // 显示用户友好的错误页面
            $error_url = add_query_arg(array(
                'cosmdl_error' => '1',
                'error_code' => $error_code,
                'message' => rawurlencode((string) $message),
                'context' => rawurlencode((string) wp_json_encode($context, JSON_UNESCAPED_UNICODE))
            ), home_url('/'));
            
            wp_safe_redirect($error_url);
            exit;
            break;
    }
}

// 中文注释：获取跳转路由前缀（可在后台设置修改，如 get、dl），只允许小写字母、数字与短横线
function cosmdl_get_redirect_prefix(){
    $opts = get_option('cosmdl_options', array());
    $prefix = isset($opts['route_prefix']) ? strtolower(trim($opts['route_prefix'])) : 'get';
    $prefix = preg_replace('/[^a-z0-9\-]/', '', $prefix);
    if (!$prefix) { $prefix = 'get'; }
    return $prefix;
}

// 路由URL构造：根据是否启用固定链接返回对应地址（支持可配置的跳转路由前缀）
function cosmdl_route_url($route, $post_id = 0, $type = ''){
    $post_id = intval($post_id);
    $type = $type ? sanitize_text_field($type) : '';
    $pretty = get_option('permalink_structure');
    $is_pretty = !empty($pretty);
    $redirect_prefix = cosmdl_get_redirect_prefix();
    switch($route){
        case 'download':
            // 下载页：/downloads/{id}.html 或 /?cosmdl_download=1&post_id={id}
            return $is_pretty ? home_url('downloads/' . $post_id . '.html') : home_url('/?cosmdl_download=1&post_id=' . $post_id);
        case 'redirect':
            // 跳转路由：/{prefix}/{id}/{type}.html 或 /?cosmdl_redirect=1&post_id={id}&type={type}
            return $is_pretty ? home_url($redirect_prefix . '/' . $post_id . '/' . $type . '.html') : home_url('/?cosmdl_redirect=1&post_id=' . $post_id . '&type=' . $type);
        case 'stats':
            // 统计页：/downloads/stats.html 或 /?cosmdl_stats=1（前台仅做权限检查与重定向到后台统计页）
            return $is_pretty ? home_url('downloads/stats.html') : home_url('/?cosmdl_stats=1');
        case 'tree':
            // 文件树：/downloads/tree.html 或 /?cosmdl_tree=1
            return $is_pretty ? home_url('downloads/tree.html') : home_url('/?cosmdl_tree=1');
        default:
            return home_url('/');
    }
}

// 中文注释：将自定义重写规则提取为可复用的函数，便于激活时注册后再刷新
function cosmdl_register_rewrites(){
    // 下载详情页：/downloads/{id}.html
    add_rewrite_rule('^downloads/([0-9]+)\.html/?$', 'index.php?cosmdl_download=1&post_id=$matches[1]', 'top');
    // 跳转路由：/{prefix}/{id}/{type}.html 例如 baidu/lanzou/360/official/local/normal/other，prefix 可配置（默认 get）
    $prefix = cosmdl_get_redirect_prefix();
    add_rewrite_rule('^' . $prefix . '/([0-9]+)/([^/]+)\.html/?$', 'index.php?cosmdl_redirect=1&post_id=$matches[1]&type=$matches[2]', 'top');
    // 文件树与统计页：/downloads/tree.html 与 /downloads/stats.html
    add_rewrite_rule('^downloads/tree\.html$', 'index.php?cosmdl_tree=1', 'top');
    add_rewrite_rule('^downloads/stats\.html$', 'index.php?cosmdl_stats=1', 'top');
}

// 中文注释：激活时注册重写规则后刷新，确保首次安装即刻可用，无需手动保存固定链接
function cosmdl_activate(){
    // 先注册自定义重写规则，再刷新路由缓存（关键：避免首次安装时规则未入库）
    cosmdl_register_rewrites();
    flush_rewrite_rules();

    // 创建点击统计表
    global $wpdb;
    $table = $wpdb->prefix . 'cosmdl_clicks';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        type VARCHAR(32) NOT NULL,
        attach_id INT UNSIGNED DEFAULT 1 COMMENT '附件编号：1-6',
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        ip VARCHAR(64) DEFAULT '',
        ua TEXT,
        referer TEXT,
        success TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY post_type (post_id, type),
        KEY post_attach (post_id, attach_id),
        KEY created_at (created_at)
    ) $charset;";
    dbDelta($sql);

    // 初始化所有默认设置
    $opts_after = get_option('cosmdl_options', array());
    if (!is_array($opts_after)) { $opts_after = array(); }

    require_once COSMDL_PLUGIN_DIR . 'includes/default-value.php';
    $defaults = function_exists('cosmdl_get_all_defaults') ? cosmdl_get_all_defaults() : array();
    if (!is_array($defaults)) { $defaults = array(); }

    // 合并默认值：确保缺失键使用默认值补齐；若已存在用户设置，则保留用户设置
    $opts_after = array_merge($defaults, $opts_after);
    update_option('cosmdl_options', $opts_after, false);
    
    // 初始化侧边栏为空，防止 WordPress 自动填充默认小工具
    cosmdl_clear_default_widgets();
}
register_activation_hook(__FILE__, 'cosmdl_activate');

// 中文注释：停用时刷新重写规则，清理路由缓存（避免残留规则影响站点）
function cosmdl_deactivate(){
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cosmdl_deactivate');

// 中文注释：在 init 钩子注册美观的固定链接路由（仅 cosmdl 命名）
add_action('init', 'cosmdl_register_rewrites');

add_action('admin_init', function(){
    if (!current_user_can(cosmdl_admin_cap())) {
        return;
    }

    $flag = get_option('cosmdl_force_flush_once', '');
    if ($flag !== 'done-v2') {
        cosmdl_register_rewrites();
        flush_rewrite_rules(false);
        update_option('cosmdl_force_flush_once', 'done-v2', false);
    }
});

// 中文注释：注册查询变量，便于模板捕获
add_filter('query_vars', function($vars){
    $vars[] = 'cosmdl_download';
    $vars[] = 'cosmdl_redirect';
    $vars[] = 'cosmdl_unlock';
    $vars[] = 'cosmdl_tree';
    $vars[] = 'cosmdl_stats';
    $vars[] = 'cosmdl_qr';
    $vars[] = 'cosmdl_error';
    $vars[] = 'error_code';
    $vars[] = 'message';
    $vars[] = 'context';
    $vars[] = 'error_message';
    $vars[] = 'post_id';
    $vars[] = 'type';
    // 多附件索引（非必须，但注册后可通过 get_query_var 读取）
    $vars[] = 'attach';
    $vars[] = 'scene';
    $vars[] = 'code';
    $vars[] = 'text';
    $vars[] = 'debug';
    $vars[] = 'sort';
    $vars[] = 'order';
    $vars[] = 'per';
    $vars[] = 'cat';
    $vars[] = 'tag';
    $vars[] = 'author';
    $vars[] = 'q';
    $vars[] = 'unit';
    $vars[] = 'size_min';
    $vars[] = 'size_max';
    return $vars;
});

// 中文注释：模板分发，根据查询变量加载前端下载页面或跳转处理模板
add_action('template_redirect', function(){
    $opts = get_option('cosmdl_options', array());
    if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') { return; }
    
    // 处理错误页面请求
    if (get_query_var('cosmdl_error') == '1') {
        include COSMDL_PLUGIN_DIR . 'templates/cosmdl-error-pages.php';
        exit;
    }
    // 中文注释：根据查询变量加载重构后的模板文件
    // 处理扫码解锁请求（在前端模板之前捕获并响应）
    if (get_query_var('cosmdl_unlock') == '1') {
        cosmdl_handle_unlock_request();
        exit;
    }
    if (get_query_var('cosmdl_download') == '1') {
        $post_id = absint(get_query_var('post_id'));
        if ($post_id <= 0) {
            $post_id = absint(get_query_var('id'));
        }
        $attach = absint(get_query_var('attach'));
        if ($attach < 1) { $attach = 1; }
        if ($attach > 6) { $attach = 6; }

        $GLOBALS['cosmdl_download_post_id'] = $post_id;
        $GLOBALS['cosmdl_download_attach']  = $attach;
        
        // 引入下载页模板函数
        require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php';
        
        // 标记当前请求为下载页，供后续 filter 使用
        global $cosmdl_is_download_page;
        $cosmdl_is_download_page = true;
        
        // 移除 wpautop 过滤器，防止 WordPress 自动添加空 p 标签导致布局间距异常
        remove_filter('the_content', 'wpautop');
        
        // 强制设置查询变量，使 WordPress 认为这是一个单页面
        // 这将触发主题加载 page.php 或 single.php，从而继承主题的 Header/Footer/Sidebar
        global $wp_query;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        $wp_query->is_404 = false;
        
        if ($post_id <= 0) {
            status_header(404);
            nocache_headers();
            echo esc_html__('404 页面未找到', 'cosmautdl');
            exit;
        }

        // 构造一个虚拟的 WP_Post 对象，防止主题因找不到 post 而报错或显示 404
        $dummy_post = new stdClass();
        $dummy_post->ID = -1; // 使用负数 ID 避免冲突
        $dummy_post->post_author = 1;
        $dummy_post->post_date = current_time('mysql');
        $dummy_post->post_date_gmt = current_time('mysql', 1);
        $dummy_post->post_content = ''; // 内容为空，后续通过 the_content 注入
        $dummy_post->post_title = __('下载', 'cosmautdl') . ' - ' . get_the_title($post_id);
        $dummy_post->post_excerpt = '';
        $dummy_post->post_status = 'publish';
        $dummy_post->comment_status = 'closed';
        $dummy_post->ping_status = 'closed';
        $dummy_post->post_password = '';
        $dummy_post->post_name = 'download-' . $post_id;
        $dummy_post->to_ping = '';
        $dummy_post->pinged = '';
        $dummy_post->post_modified = current_time('mysql');
        $dummy_post->post_modified_gmt = current_time('mysql', 1);
        $dummy_post->post_content_filtered = '';
        $dummy_post->post_parent = 0;
        $dummy_post->guid = home_url('/?cosmdl_download=1&post_id=' . $post_id);
        $dummy_post->menu_order = 0;
        $dummy_post->post_type = 'page'; // 伪装成页面
        $dummy_post->post_mime_type = '';
        $dummy_post->comment_count = 0;
        $dummy_post->filter = 'raw';
        
        // 将虚拟文章转换为 WP_Post 对象并设置为全局 post
        $wp_post = new WP_Post($dummy_post);
        global $post;
        $post = $wp_post;
        $wp_query->post = $wp_post;
        $wp_query->posts = array($wp_post);
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;
        
        // 不再 exit，而是让 WordPress 继续执行 template_include 流程
        // 这样主题就会加载 page.php，并调用 get_header/get_footer
        return;
    }
    // 文件树页面（根据设置控制开关与可见性）
    if (get_query_var('cosmdl_tree') == '1') {
        // 加载文件树模板（模板内部已包含开关与可见性校验）
        include COSMDL_PLUGIN_DIR . 'templates/file-tree.php';
        exit;
    }
    // 下载统计页面（改为后台页面，前台路由仅做权限检查与重定向）
    if (get_query_var('cosmdl_stats') == '1') {
        if (!is_user_logged_in()) {
            $redirect_to = home_url('/?cosmdl_stats=1');
            wp_safe_redirect(wp_login_url($redirect_to));
            exit;
        }
        if (!current_user_can(cosmdl_admin_cap())) {
            status_header(404);
            nocache_headers();
            echo esc_html__('404 页面未找到', 'cosmautdl');
            exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=cosmdl-stats'));
        exit;
    }
    if (get_query_var('cosmdl_redirect') == '1') {
        $GLOBALS['cosmdl_redirect_post_id'] = absint(get_query_var('post_id'));
        $GLOBALS['cosmdl_redirect_type']    = sanitize_text_field((string) get_query_var('type'));
        $GLOBALS['cosmdl_redirect_attach']  = absint(get_query_var('attach'));
        include COSMDL_PLUGIN_DIR . 'templates/redirect.php';
        exit;
    }
    // 站点内二维码生成：?cosmdl_qr=1&text=...
    if (get_query_var('cosmdl_qr') == '1'){
        $text = trim((string) get_query_var('text'));
        if (!$text) { cosmdl_handle_error(__('缺少二维码内容', 'cosmautdl')); }
        // 安全限制：长度不超过 2048，且仅用于站内扫码解锁 URL，防止被用作任意二维码生成导致磁盘膨胀
        if (strlen($text) > 2048) { cosmdl_handle_error(__('二维码内容过长', 'cosmautdl')); }
        $home = home_url('/');
        if (strpos($text, $home) !== 0 || strpos($text, 'cosmdl_unlock=1') === false) {
            cosmdl_handle_error(__('仅允许生成本站扫码解锁二维码', 'cosmautdl'));
        }
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'cosmdl/qr';
        $fs = cosmdl_get_filesystem();
        if ($fs && !$fs->is_dir($dir)) {
            $fs->mkdir($dir, defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755);
        }
        $hash = md5($text);
        $file = $dir . '/' . $hash . '.png';

        $pluginLib = COSMDL_PLUGIN_DIR . 'lib/phpqrcode.php';
        if (file_exists($pluginLib)) { require_once $pluginLib; }
        if (!class_exists('QRcode')){ cosmdl_handle_error(__('二维码库不可用，请在插件 lib 目录内置 phpqrcode.php 或改用静态二维码模式。', 'cosmautdl')); }

        if ($fs && $fs->exists($file)) {
            $png = $fs->get_contents($file);
            if (is_string($png) && $png !== '') {
                nocache_headers();
                header('Content-Type: image/png');
                echo $png; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
            }
        }

        ob_start();
        QRcode::png($text, false, QR_ECLEVEL_H, 5, 2);
        $png = ob_get_clean();
        if (!is_string($png) || $png === '') {
            cosmdl_handle_error(__('二维码生成失败', 'cosmautdl'));
        }

        if ($fs) {
            $fs->put_contents($file, $png, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
        }

        nocache_headers();
        header('Content-Type: image/png');
        echo $png; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
});

// 中文注释：优化 canonical，避免 .html 结尾被 WP 自动加斜杠，同时保护基于 query_var 的路由
add_filter('redirect_canonical', function($redirect_url, $requested_url){
	$prefix = cosmdl_get_redirect_prefix();
	// 中文注释：根据当前路由前缀动态匹配，避免 .html 结尾被加斜杠或域名/端口被改写
	$requested_path = wp_parse_url( (string) $requested_url, PHP_URL_PATH );
	$requested_path = is_string( $requested_path ) ? $requested_path : '';
	$pattern = '#/(downloads|' . preg_quote($prefix, '#') . ')/.+\.html/?$#';
	if ( $requested_path !== '' && preg_match( $pattern, $requested_path ) ) {
		return $requested_url;
	}
    // 中文注释：保护 query_var 路径，防止被 canonical 重定向到站点首页或其他端口
    // 例如：/?cosmdl_tree=1, /?cosmdl_download=1&post_id=123 等
    if (preg_match('#[?&](cosmdl_tree|cosmdl_download|cosmdl_redirect|cosmdl_stats|cosmdl_qr)=1#', $requested_url)){
        return $requested_url;
    }
    return $redirect_url;
}, 10, 2);

// 中文注释：前端样式注册，确保下载页拥有独立卡片样式
add_action('wp_enqueue_scripts', function(){
    $opts = get_option('cosmdl_options', array());
    if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') { return; }
    // 中文注释：注册前端卡片样式（按需加载）
    // cosmautdl.css 包含了所有样式（合并了 card.css 和 cosmdl.css）
    wp_register_style('cosmdl-style', COSMDL_PLUGIN_URL . 'assets/cosmautdl.css', array(), cosmdl_asset_version('assets/cosmautdl.css'));

    // 中文注释：注册下载页交互脚本（按需加载，避免主题过滤 the_content 时丢失 JS）
    wp_register_script('cosmdl-download', COSMDL_PLUGIN_URL . 'assets/cosmdl-download.js', array(), cosmdl_asset_version('assets/cosmdl-download.js'), true);
    
    // 中文注释：文件树样式仅在文件树页面加载，避免全站污染样式
    $is_tree = (get_query_var('cosmdl_tree') === '1');
    if ($is_tree){
        wp_enqueue_style('cosmdl-tree', COSMDL_PLUGIN_URL . 'assets/tree.css', array(), cosmdl_asset_version('assets/tree.css'));
    }

    // 中文注释：下载页样式加载（仅在下载页加载）
    $is_download = (get_query_var('cosmdl_download') === '1');
    if ($is_download){
        wp_enqueue_style('cosmdl-style');

        wp_enqueue_script('cosmdl-download');
        wp_localize_script('cosmdl-download', 'cosmdlDownload', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cosmdl_check_unlock'),
            'pollInterval' => 3000,
            'autoCloseOnUnlock' => 1,
            'autoRedirectOnUnlock' => 1,
            'i18n' => array(
                'needUnlock' => __('请先使用微信扫码二维码，完成验证后再点击下载按钮', 'cosmautdl'),
                'unlocked'   => __('已解锁，请点击下载按钮开始下载', 'cosmautdl'),
                'unlockedStatus' => __('已解锁，请点击下方网盘按钮开始下载', 'cosmautdl'),
                'copied'     => __('已复制', 'cosmautdl'),
                'copyFailed' => __('复制失败，请手动复制', 'cosmautdl'),
            ),
        ));
    }
});

function cosmdl_should_enqueue_card_style_for_post($post_id) {
    $post_id = intval($post_id);
    if ($post_id <= 0) {
        return false;
    }

    $enabled = (get_post_meta($post_id, 'cosmdl_start', true) === 'yes');
    if ($enabled) {
        return true;
    }

    $drive_management = function_exists('cosmdl_get_drive_management_settings') ? cosmdl_get_drive_management_settings() : array();
    if (!is_array($drive_management) || empty($drive_management)) {
        $options = get_option('cosmdl_options', array());
        $drive_management = (isset($options['drive_management']) && is_array($options['drive_management'])) ? $options['drive_management'] : array();
    }

    if (function_exists('cosmdl_post_has_any_link')) {
        return cosmdl_post_has_any_link($post_id, $drive_management);
    }

    return false;
}

add_action('wp_enqueue_scripts', function(){
    if (is_admin()) {
        return;
    }

    $opts = get_option('cosmdl_options', array());
    if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') {
        return;
    }

    if (!is_singular()) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $should = false;
    if (is_singular('post')) {
        $should = cosmdl_should_enqueue_card_style_for_post($post_id);
    } else {
        $post = get_post($post_id);
        if ($post instanceof WP_Post && has_shortcode($post->post_content, 'cosmdl_download_card')) {
            $should = true;
        }
    }

    if ($should) {
        wp_enqueue_style('cosmdl-style');
    }
}, 15);

add_filter('style_loader_tag', function($html, $handle, $href, $media) {
    if (in_array($handle, array('cosmdl-style', 'cosmdl-tree'), true) && strpos($html, 'data-instant-track') === false) {
        return preg_replace('/^<link\s/i', '<link data-instant-track ', $html, 1);
    }
    return $html;
}, 10, 4);

add_filter('script_loader_tag', function($tag, $handle, $src) {
    if (in_array($handle, array('cosmdl-download'), true) && strpos($tag, 'data-instant-track') === false) {
        return preg_replace('/^<script\s/i', '<script data-instant-track ', $tag, 1);
    }
    return $tag;
}, 10, 3);

function cosmdl_instant_track_seed() {
    if (is_admin()) {
        return '';
    }

    $opts = get_option('cosmdl_options', array());
    if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') {
        return '';
    }

    $is_tree = (get_query_var('cosmdl_tree') === '1');
    if ($is_tree) {
        return 'tree';
    }

    $is_download = (get_query_var('cosmdl_download') === '1');
    if ($is_download) {
        return 'download';
    }

    if (is_singular()) {
        $queried = get_queried_object();
        if ($queried instanceof WP_Post && isset($queried->post_name) && (string)$queried->post_name === 'cosmautdl-plugin') {
            return 'cosmautdl-plugin';
        }

        if (is_singular('post')) {
            $post_id = (int) get_queried_object_id();
            if ($post_id > 0 && cosmdl_should_enqueue_card_style_for_post($post_id)) {
                return 'card';
            }
        }
    }

    return 'base';
}

function cosmdl_output_instant_track_marker() {
    $seed = cosmdl_instant_track_seed();
    if ($seed === '') {
        return;
    }
    $hash = substr(md5($seed), 0, 12);
    echo '<style data-instant-track id="cosmdl-instant-track">/* cosmdl:' . esc_html($hash) . ' */</style>';
}
add_action('wp_head', 'cosmdl_output_instant_track_marker', 1);

function cosmdl_output_card_css_runtime_loader() {
    if (is_admin()) {
        return;
    }

    $opts = get_option('cosmdl_options', array());
    if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') {
        return;
    }

    $href = add_query_arg('ver', cosmdl_asset_version('assets/cosmautdl.css'), COSMDL_PLUGIN_URL . 'assets/cosmautdl.css');
    $href = esc_url($href);

    echo '<script id="cosmdl-runtime-card-css">(function(){\n'
        . 'var H=' . wp_json_encode($href) . ';\n'
        . 'function has(){var ls=document.querySelectorAll("link[rel=\\"stylesheet\\"]");for(var i=0;i<ls.length;i++){var u=ls[i].href||"";if(u.indexOf("/assets/cosmautdl.css")!==-1){return true;}}return false;}\n'
        . 'function ensure(){if(!document.querySelector(".cosmdl-card")){return;}if(has()){return;}var l=document.createElement("link");l.rel="stylesheet";l.href=H;l.setAttribute("data-instant-track","");l.setAttribute("data-cosmdl-runtime","1");document.head.appendChild(l);}\n'
        . 'function bind(){var ic=window.InstantClick;if(!ic||!ic.on){return false;}if(window.__cosmdl_bound_ic){return true;}window.__cosmdl_bound_ic=true;ic.on("change",function(){ensure();});return true;}\n'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){ensure();bind();});}else{ensure();bind();}\n'
        . 'var t=0;var it=setInterval(function(){t++;if(bind()||t>20){clearInterval(it);}},200);\n'
        . '})();</script>';
}
add_action('wp_head', 'cosmdl_output_card_css_runtime_loader', 2);

/**
 * 自动修正文件大小数据的核心逻辑
 * 供 save_post 钩子自动调用，也可供 AJAX 工具批量调用
 */
function cosmdl_auto_fix_sizes($post_id) {
    // 如果是自动保存或修订版本，忽略
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // 引入辅助函数
    if (!function_exists('cosmdl_size_to_bytes')) {
        require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php';
    }

    // 遍历附件 1-6
    for ($i = 1; $i <= 6; $i++) {
        $prefix = ($i === 1) ? 'cosmdl_' : ('cosmdl' . $i . '_');
        $size_key = $prefix . 'size';
        $bytes_key = $prefix . 'size_bytes';

        $size_val = get_post_meta($post_id, $size_key, true);
        
        if (!empty($size_val)) {
            $bytes = cosmdl_size_to_bytes($size_val);
            $old_bytes = get_post_meta($post_id, $bytes_key, true);

            // 仅在值不同时更新
            if ($old_bytes === '' || intval($old_bytes) !== $bytes) {
                update_post_meta($post_id, $bytes_key, $bytes);
            }
        }
    }
}
// 挂载到 save_post 钩子，实现发布/更新文章时自动执行
add_action('save_post', 'cosmdl_auto_fix_sizes');

// 中文注释：修正文件树页面在分页时的标题与 404 误判
// 说明：部分主题在 is_paged 且主查询为空时会设置 404 并输出“你访问的资源不存在”，这里按自定义路由覆盖标题并短路 404 处理。
add_filter('document_title_parts', function($parts){
    $is_tree = (get_query_var('cosmdl_tree') === '1');
    if ($is_tree){
        $paged = max(1, absint(get_query_var('paged')));
        $base = esc_html__('文件树（所有分享文件）', 'cosmautdl');
        $parts['title'] = $base . ($paged > 1 ? (' - 第' . $paged . '页') : '');
    }
    return $parts;
}, 9999);

function cosmdl_get_current_download_post_id() {
    if (isset($GLOBALS['cosmdl_download_post_id'])) {
        return absint($GLOBALS['cosmdl_download_post_id']);
    }
    $post_id = absint(get_query_var('post_id'));
    if ($post_id <= 0) {
        $post_id = absint(get_query_var('id'));
    }
    return $post_id;
}

// 统一处理页面标题（涵盖 document_title 和 wp_title）
function cosmdl_get_virtual_page_title($original_title = '') {
    global $cosmdl_is_download_page;
    
    // 1. 下载页逻辑
    if (isset($cosmdl_is_download_page) && $cosmdl_is_download_page){
        $post_id = cosmdl_get_current_download_post_id();
        // 防止 get_the_title 递归调用 the_title 过滤器，这里直接获取数据库字段或临时移除过滤器
        // 但由于我们无法轻易移除匿名过滤器，且 get_the_title(ID) 会传入 ID，
        // 我们的 the_title 过滤器会检查 ID=-1，所以理论上不会递归。
        // 为保险起见，我们使用 get_post_field 直接获取标题
        $real_title = get_post_field('post_title', $post_id);
        return __('下载', 'cosmautdl') . ' - ' . $real_title;
    }

    // 2. 文件树逻辑
    $is_tree = (get_query_var('cosmdl_tree') === '1');
    if ($is_tree){
        $paged = max(1, absint(get_query_var('paged')));
        $base = esc_html__('文件树（所有分享文件）', 'cosmautdl');
        return $base . ($paged > 1 ? (' - 第' . $paged . '页') : '');
    }

    return $original_title;
}

// 拦截网页标题（<title>标签）
add_filter('pre_get_document_title', function($title){
    $virtual_title = cosmdl_get_virtual_page_title('');
    if ($virtual_title) {
        return $virtual_title . ' - ' . get_bloginfo('name');
    }
    return $title;
}, 9999);

// 兼容旧主题：使用 wp_title 过滤器覆盖标题
add_filter('wp_title', function($title, $sep = '', $seplocation = ''){
    $virtual_title = cosmdl_get_virtual_page_title('');
    if ($virtual_title) {
        return $virtual_title;
    }
    return $title;
}, 9999, 3);

// 修复 wp_get_shortlink 在虚拟文章 ID (-1) 下报 Warning 的问题
add_filter('pre_get_shortlink', function($return, $id, $context, $allow_slugs){
    // 检查是否为我们的虚拟文章 ID
    $target_id = $id ? $id : get_the_ID();
    if ($target_id === -1) {
        // 返回当前页面 URL 作为短链接，避免后续逻辑调用 get_post(-1) 导致 Warning
        return cosmdl_route_url('download', -1); 
    }
    return $return;
}, 10, 4);

add_filter('pre_handle_404', function($preempt, $wp_query){
    // 下载页也需要短路 404 处理，因为我们虽然设置了 is_page，但实际上并没有对应的数据库记录
    global $cosmdl_is_download_page;
    if ((isset($cosmdl_is_download_page) && $cosmdl_is_download_page) || get_query_var('cosmdl_tree') == '1'){
        // 明确取消 404 标记，交由模板自行输出
        if ($wp_query instanceof WP_Query){ $wp_query->is_404 = false; }
        return true; // 短路 404 处理
    }
    return $preempt;
}, 10, 2);

// 中文注释：拦截内容输出，注入下载页内容
add_filter('the_content', function($content) {
    global $cosmdl_is_download_page, $post;
    // 仅针对主循环且为我们的虚拟文章 ID (-1) 时替换内容
    // 防止侧边栏或其他位置的循环（如“最新文章”）被错误替换，导致死循环
    if (isset($cosmdl_is_download_page) && $cosmdl_is_download_page && in_the_loop() && is_main_query() && isset($post->ID) && $post->ID === -1) {
        $post_id = cosmdl_get_current_download_post_id();
        // 使用静态变量防止重入
        static $is_processing = false;
        if ($is_processing) { return $content; }
        $is_processing = true;
        $generated_content = cosmdl_get_download_content($post_id);
        $is_processing = false;
        return $generated_content;
    }
    return $content;
});

// 中文注释：拦截页面标题，显示下载页标题
add_filter('the_title', function($title, $id = null) {
    global $cosmdl_is_download_page;
    // 仅在主循环中且 ID 为虚拟文章 ID 时修改标题
    if (isset($cosmdl_is_download_page) && $cosmdl_is_download_page && in_the_loop() && $id === -1) {
        $post_id = cosmdl_get_current_download_post_id();
        // 直接查询数据库字段，避开 get_the_title -> apply_filters('the_title') 的潜在递归风险
        $real_title = get_post_field('post_title', $post_id);
        return __('下载', 'cosmautdl') . ' - ' . $real_title;
    }
    return $title;
}, 10, 2);



// 中文注释：加载后台设置与文章编辑页元框
require_once COSMDL_PLUGIN_DIR . 'includes/class-icons.php';
require_once COSMDL_PLUGIN_DIR . 'includes/class-admin.php';
require_once COSMDL_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php'; // 确保先加载下载页面函数
require_once COSMDL_PLUGIN_DIR . 'includes/class-render.php';

// 中文注释：实例化后台类与元框类
add_action('plugins_loaded', function(){
    new CosMDL_Admin();
    $opts = get_option('cosmdl_options', array());
    if (is_array($opts) && isset($opts['plugin_active']) && $opts['plugin_active'] === 'yes'){
        new CosMDL_Meta_Box();
        new CosMDL_Render();
    }
});

// 注册小工具侧边栏：下载页-侧边栏
add_action('widgets_init', function(){
    register_sidebar(array(
        'name'          => __('下载页-侧边栏', 'cosmautdl'),
        'id'            => 'cosmdl_download_sidebar',
        'description'   => __('用于下载页右侧区域，可添加主题/WordPress提供的小工具。', 'cosmautdl'),
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
});



// 清除下载页侧边栏中的默认小工具（确保初始化为空）
function cosmdl_clear_default_widgets() {
    // 获取当前小工具配置
    $sidebars_widgets = get_option('sidebars_widgets', array());
    
    // 检查是否已初始化过（防止重复清空用户设置）
    $initialized = get_option('cosmdl_sidebar_initialized', false);
    
    // 如果未初始化，或者侧边栏不存在，则执行初始化
    if (!$initialized || !isset($sidebars_widgets['cosmdl_download_sidebar'])) {
        if (!is_array($sidebars_widgets)) {
            $sidebars_widgets = array();
        }
        
        // 强制设置为空数组，防止 WP 自动填充默认区块
        $sidebars_widgets['cosmdl_download_sidebar'] = array();
        
        // 更新配置
        update_option('sidebars_widgets', $sidebars_widgets);
        // 标记已初始化
        update_option('cosmdl_sidebar_initialized', 'yes');
        
        return __('下载页侧边栏已初始化为空', 'cosmautdl');
    }
    
    return __('下载页侧边栏已有设置，未执行清理', 'cosmautdl');
}

// 提供一个临时的管理后台操作
add_action('admin_init', function() {
    // 仅在URL中包含清除参数时执行
    $do_clear = filter_input(INPUT_GET, 'cosmdl_clear_widgets', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($do_clear !== null && $do_clear !== false && current_user_can(cosmdl_admin_cap())) {
        $do_clear = sanitize_text_field((string) $do_clear);
        if ($do_clear !== '1') { return; }

        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $nonce = ($nonce !== null && $nonce !== false) ? sanitize_text_field((string) $nonce) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'cosmdl_clear_widgets')) {
            $retry_url = wp_nonce_url(add_query_arg(array('cosmdl_clear_widgets' => '1')), 'cosmdl_clear_widgets');
            add_action('admin_notices', function() use ($retry_url) {
                echo '<div class="notice notice-warning is-dismissible"><p>'
                    . esc_html__('出于安全原因，本操作需要验证。请点击链接重新执行：', 'cosmautdl')
                    . ' <a href="' . esc_url($retry_url) . '">' . esc_html__('清理下载页侧边栏小工具', 'cosmautdl') . '</a>'
                    . '</p></div>';
            });
            return;
        }

        // 强制重置初始化标记，以便允许清理
        delete_option('cosmdl_sidebar_initialized');
        // 清除小工具
        $result = cosmdl_clear_default_widgets();
        
        // 添加管理通知
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
        });
    }
});

// 中文注释：插件列表添加“设置”直达链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    $settings_url = admin_url('admin.php?page=cosmdl-settings');
    array_unshift($links, '<a href="' . esc_url($settings_url) . '">' . esc_html__('设置', 'cosmautdl') . '</a>');
    return $links;
});

// 中文注释：当用户在设置中修改了“路由前缀”时，自动重新注册并刷新重写规则，确保立即生效
add_action('updated_option', function($option, $old_value, $new_value){
    if ($option !== 'cosmdl_options') return;
    $old = is_array($old_value) ? $old_value : array();
    $new = is_array($new_value) ? $new_value : array();
    $old_prefix = isset($old['route_prefix']) ? strtolower(trim($old['route_prefix'])) : 'get';
    $new_prefix = isset($new['route_prefix']) ? strtolower(trim($new['route_prefix'])) : 'get';
    $old_prefix = preg_replace('/[^a-z0-9\-]/', '', $old_prefix);
    $new_prefix = preg_replace('/[^a-z0-9\-]/', '', $new_prefix);
    if (!$old_prefix) $old_prefix = 'get';
    if (!$new_prefix) $new_prefix = 'get';
    if ($old_prefix !== $new_prefix) {
        cosmdl_register_rewrites();
        flush_rewrite_rules();
    }
}, 10, 3);

/**
 * 扫码解锁处理逻辑：支持微信公众号关注解锁
 * 流程：桌面端生成 scene 键轮询；移动端（微信内）访问本链接进行 OAuth 获取 openid，调用 user/info 判断是否已关注，成功后标记解锁。
 */
function cosmdl_handle_unlock_request(){
    if (!function_exists('wp_remote_get')) require_once ABSPATH . WPINC . '/http.php';
    $scene = sanitize_text_field((string) get_query_var('scene'));
    if (!$scene) { cosmdl_handle_error(__('缺少参数 scene', 'cosmautdl')); }

    // 读取设置（仅使用新键）
    $opts = get_option('cosmdl_options', array());
    $mode = isset($opts['qr_unlock_mode']) ? $opts['qr_unlock_mode'] : 'static';
    $appid = isset($opts['wechat_appid']) ? trim($opts['wechat_appid']) : '';
    $secret = isset($opts['wechat_appsecret']) ? trim($opts['wechat_appsecret']) : '';
    $follow_text = isset($opts['qr_follow_text']) ? $opts['qr_follow_text'] : '关注公众号后自动解锁下载链接';

    // 非 wechat 模式：直接标记解锁（用于静态二维码或测试）
    if ($mode !== 'wechat' || !$appid || !$secret) {
        set_transient('cosmdl_unlocked_' . $scene, 1, 10 * MINUTE_IN_SECONDS);
        echo '<!doctype html><meta charset="utf-8"><title>' . esc_html__('已解锁', 'cosmautdl') . '</title><p style="padding:20px;font:16px/1.6 system-ui">' . esc_html__('已记录解锁，请返回电脑页面继续下载。', 'cosmautdl') . '</p>';
        exit;
    }

	// 微信 OAuth：若无 code，跳转授权；scope 采用 snsapi_base 获取 openid
	$code = sanitize_text_field((string) get_query_var('code'));
	$current_url = (function(){
		$scheme = (is_ssl() ? 'https://' : 'http://');
		$host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$host = is_string($host) ? $host : '';
		$host = strtolower($host);
		$host = preg_replace('/[^a-z0-9.\-:]/', '', $host);

		$uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$uri = is_string($uri) ? $uri : '';
		$uri = preg_replace('/[\r\n]/', '', $uri);
		if ($uri !== '' && strpos($uri, '/') !== 0) {
			$uri = '/' . ltrim($uri, '/');
		}

		return esc_url_raw($scheme . $host . $uri);
	})();
	if (!$code) {
		$auth = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . rawurlencode($appid)
		      . '&redirect_uri=' . rawurlencode($current_url)
		      . '&response_type=code&scope=snsapi_base&state=cosmdl#wechat_redirect';
        add_filter('allowed_redirect_hosts', 'cosmdl_allow_wechat_redirect_hosts');
        wp_safe_redirect($auth);
        remove_filter('allowed_redirect_hosts', 'cosmdl_allow_wechat_redirect_hosts');
        exit;
    }

    // 使用 code 换取 openid
    $oauth_url = add_query_arg(array(
        'appid' => $appid,
        'secret' => $secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
    ), 'https://api.weixin.qq.com/sns/oauth2/access_token');
    $resp = wp_remote_get($oauth_url, array('timeout' => 10));
    if (is_wp_error($resp)) cosmdl_handle_error(__('网络异常，请重试', 'cosmautdl'));
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $openid = isset($data['openid']) ? $data['openid'] : '';
    if (!$openid) cosmdl_handle_error(__('未获取到 openid，请重试授权', 'cosmautdl'));

    // 获取公众号全局 access_token（增加缓存，降低远程请求频率）
    $token_cache_key = 'cosmdl_wechat_token_' . md5($appid . '|' . $secret);
    $access_token = get_transient($token_cache_key);
    if (!$access_token) {
        $token_url = add_query_arg(array(
            'grant_type' => 'client_credential',
            'appid' => $appid,
            'secret' => $secret,
        ), 'https://api.weixin.qq.com/cgi-bin/token');
        $tresp = wp_remote_get($token_url, array('timeout' => 10));
        if (is_wp_error($tresp)) cosmdl_handle_error(__('网络异常，请重试', 'cosmautdl'));
        $tdata = json_decode(wp_remote_retrieve_body($tresp), true);
        $access_token = isset($tdata['access_token']) ? $tdata['access_token'] : '';
        if (!$access_token) cosmdl_handle_error(__('未获取到 access_token，请检查 AppID/AppSecret', 'cosmautdl'));
        $expires = isset($tdata['expires_in']) ? intval($tdata['expires_in']) : 7200;
        if ($expires <= 0) { $expires = 7200; }
        // 提前一分钟过期，避免边界问题
        set_transient($token_cache_key, $access_token, max(60, $expires - 60));
    }

    // 查询用户关注状态
    $info_url = add_query_arg(array(
        'access_token' => $access_token,
        'openid' => $openid,
        'lang' => 'zh_CN',
    ), 'https://api.weixin.qq.com/cgi-bin/user/info');
    $iresp = wp_remote_get($info_url, array('timeout' => 10));
    if (is_wp_error($iresp)) cosmdl_handle_error(__('网络异常，请重试', 'cosmautdl'));
    $idata = json_decode(wp_remote_retrieve_body($iresp), true);
    $subscribed = isset($idata['subscribe']) ? intval($idata['subscribe']) : 0;

    if ($subscribed === 1) {
        set_transient('cosmdl_unlocked_' . $scene, 1, 10 * MINUTE_IN_SECONDS);
        echo '<!doctype html><meta charset="utf-8"><title>' . esc_html__('解锁成功', 'cosmautdl') . '</title><p style="padding:20px;font:16px/1.6 system-ui">' . esc_html__('已关注并解锁，请返回电脑页面继续下载。', 'cosmautdl') . '</p>';
    } else {
        // 使用自定义模板输出失败提示
        $GLOBALS['cosmdl_follow_text'] = $follow_text;
        include COSMDL_PLUGIN_DIR . 'templates/unlock-fail.php';
    }
    exit;
}

// 轮询接口：检查是否已解锁
add_action('wp_ajax_nopriv_cosmdl_check_unlock', 'cosmdl_ajax_check_unlock');
add_action('wp_ajax_cosmdl_check_unlock', 'cosmdl_ajax_check_unlock');
function cosmdl_ajax_check_unlock(){
    check_ajax_referer('cosmdl_check_unlock', '_ajax_nonce');
    $scene = filter_input(INPUT_GET, 'scene', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $scene = ($scene !== null && $scene !== false) ? sanitize_text_field((string) $scene) : '';
    $ok = $scene && get_transient('cosmdl_unlocked_' . $scene) ? 1 : 0;
    wp_send_json(array('unlocked' => $ok));
}


// 动态替换侧边栏：当处于下载页时，将主题的侧边栏替换为我们的下载页侧边栏
add_filter('sidebars_widgets', function($sidebars_widgets){
    // 必须是前台请求，且非管理后台
    if (is_admin()) { return $sidebars_widgets; }

    global $cosmdl_is_download_page;
    $is_download = (isset($cosmdl_is_download_page) && $cosmdl_is_download_page) 
                || (get_query_var('cosmdl_download') == '1');

    if ($is_download) {
        // 获取我们自己的侧边栏内容
        $my_sidebar_id = 'cosmdl_download_sidebar';
        
        // 如果我们的侧边栏没有被注册或为空（但要注意空数组也可能是有效的空侧边栏），这里我们只关心它是否存在于 widgets 列表中
        // 注意：sidebars_widgets 包含所有已注册侧边栏的 widget ID 列表
        $my_widgets = isset($sidebars_widgets[$my_sidebar_id]) ? $sidebars_widgets[$my_sidebar_id] : array();

        // 替换常见的侧边栏 ID
        // Puock 主题使用 'sidebar_page'
        // 常见主题使用 'sidebar-1', 'sidebar-2', 'sidebar_main' 等
        // 同时覆盖 sidebar_not 以防万一
        $targets = array('sidebar_page', 'sidebar-1', 'sidebar_main', 'sidebar', 'sidebar_not', 'sidebar_default');
        
        foreach ($targets as $target) {
            if (isset($sidebars_widgets[$target])) {
                $sidebars_widgets[$target] = $my_widgets;
            }
        }
    }
    
    return $sidebars_widgets;
});
