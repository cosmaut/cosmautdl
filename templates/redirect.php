<?php
/**
 * 跳转模板：根据路由参数类型，读取文章对应的下载链接并执行重定向
 * 功能：将 /get/{id}/{type}.html 路由映射到具体的网盘或下载地址。
 * 说明：读取 cosmdl_ 前缀；保留可扩展的解锁判断过滤器。
 */

if (!defined('ABSPATH')) { exit; }

// 依赖 includes/download-page.php 中的辅助函数
require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php';

// 获取参数并做基础校验
$cosmdl_post_id = isset($GLOBALS['cosmdl_redirect_post_id']) ? absint($GLOBALS['cosmdl_redirect_post_id']) : 0;
if ($cosmdl_post_id <= 0) {
    $cosmdl_post_id = absint(get_query_var('post_id'));
}

$cosmdl_type = isset($GLOBALS['cosmdl_redirect_type']) ? (string) $GLOBALS['cosmdl_redirect_type'] : '';
if ($cosmdl_type === '') {
    $cosmdl_type = sanitize_text_field((string) get_query_var('type'));
}
// 支持多附件：attach 1-6（超出范围自动归并）
$cosmdl_attach = isset($GLOBALS['cosmdl_redirect_attach']) ? absint($GLOBALS['cosmdl_redirect_attach']) : 0;
if ($cosmdl_attach <= 0) {
    $cosmdl_attach = absint(get_query_var('attach'));
}
if ($cosmdl_attach <= 0) { $cosmdl_attach = 1; }
if ($cosmdl_attach < 1) { $cosmdl_attach = 1; }
if ($cosmdl_attach > 6) { $cosmdl_attach = 6; }

// 调试输出请求参数（仅在开发环境）
if (defined('WP_DEBUG') && WP_DEBUG) {
    cosmdl_debug(
        array(
            'post_id' => $cosmdl_post_id,
            'type'    => $cosmdl_type,
            'attach'  => $cosmdl_attach,
        ),
        '下载重定向请求参数'
    );

    // 记录下载请求（仅在开发环境）
    cosmdl_write_log("收到下载请求", 'info', array(
        'post_id' => $cosmdl_post_id,
        'type'    => $cosmdl_type,
        'attach'  => $cosmdl_attach,
    ));
}

if ($cosmdl_post_id <= 0 || $cosmdl_type === '') { 
    cosmdl_handle_error(
        __('参数无效', 'cosmautdl'),
        array(
            'post_id' => $cosmdl_post_id,
            'type'    => $cosmdl_type,
            'attach'  => $cosmdl_attach,
        )
    );
}

// 规范化类型
$cosmdl_normalized = strtolower(trim($cosmdl_type));

// 从配置中查找网盘
$cosmdl_opts = get_option('cosmdl_options');
$cosmdl_opts = is_array($cosmdl_opts) ? $cosmdl_opts : array();
$cosmdl_drive_management = isset($cosmdl_opts['drive_management']) && is_array($cosmdl_opts['drive_management']) ? $cosmdl_opts['drive_management'] : array();

$cosmdl_matched_key = null;
$cosmdl_matched_drive = null;

// 1. 尝试直接匹配 key
if (isset($cosmdl_drive_management[$cosmdl_normalized])) {
    $cosmdl_matched_key = $cosmdl_normalized;
    $cosmdl_matched_drive = $cosmdl_drive_management[$cosmdl_normalized];
} else {
    // 2. 尝试匹配 alias
    foreach($cosmdl_drive_management as $cosmdl_drive_key => $cosmdl_drive) {
        $cosmdl_alias = isset($cosmdl_drive['alias']) ? strtolower(trim($cosmdl_drive['alias'])) : '';
        if ($cosmdl_alias !== '' && $cosmdl_alias === $cosmdl_normalized) {
            $cosmdl_matched_key = $cosmdl_drive_key;
            $cosmdl_matched_drive = $cosmdl_drive;
            break;
        }
    }
}

// 3. 尝试处理 custom_ 前缀 (如果未直接匹配)
if ($cosmdl_matched_key === null && strpos($cosmdl_normalized, 'custom_') === 0) {
    $cosmdl_clean = substr($cosmdl_normalized, 7);
    if (isset($cosmdl_drive_management[$cosmdl_clean])) {
        $cosmdl_matched_key = $cosmdl_clean;
        $cosmdl_matched_drive = $cosmdl_drive_management[$cosmdl_clean];
    }
}

if ($cosmdl_matched_key !== null) {
    $cosmdl_is_custom = (isset($cosmdl_matched_drive['is_custom']) && $cosmdl_matched_drive['is_custom'] === 'yes');
    // 使用 includes/download-page.php 中的统一函数
    $cosmdl_fields = cosmdl_get_field_names_for_drive($cosmdl_matched_key, $cosmdl_attach, $cosmdl_is_custom);
    
    $cosmdl_url = cosmdl_get_meta($cosmdl_post_id, $cosmdl_fields['url']);
    if (!$cosmdl_url) { 
        cosmdl_handle_error(
            esc_html__('下载地址不存在或未配置', 'cosmautdl'), 
            array(
                'post_id'  => $cosmdl_post_id, 
                'type'     => $cosmdl_type, 
                'attach'   => $cosmdl_attach,
                'meta_field' => $cosmdl_fields['url'],
            )
        ); 
    }
    
    $cosmdl_need_unlock_one = cosmdl_get_meta($cosmdl_post_id, $cosmdl_fields['unlock']) === 'yes';
    $cosmdl_type_to_log = $cosmdl_normalized;
} else {
    // 未知类型
    cosmdl_handle_error(__('未知下载类型', 'cosmautdl'));
}

// 调试输出获取的URL
if (defined('WP_DEBUG') && WP_DEBUG) {
    cosmdl_debug(array('url' => $cosmdl_url), '获取到的下载URL');
}

$cosmdl_url_raw = is_string($cosmdl_url) ? trim($cosmdl_url) : '';
if ($cosmdl_url_raw === '') {
    cosmdl_handle_error(
        esc_html__('下载地址为空或未配置', 'cosmautdl'),
        array(
            'post_id' => $cosmdl_post_id,
            'type'    => $cosmdl_type_to_log,
            'attach'  => $cosmdl_attach,
        )
    );
}

if (preg_match('~https?://[^\s"\']+~i', $cosmdl_url_raw, $cosmdl_url_match)) {
    $cosmdl_url_raw = $cosmdl_url_match[0];
}

if (!preg_match('~^[a-z][a-z0-9+\-.]*://~i', $cosmdl_url_raw)) {
    if (strpos($cosmdl_url_raw, '//') === 0) {
        $cosmdl_url_raw = 'https:' . $cosmdl_url_raw;
    } elseif (preg_match('~^[^/]+\.[^/]+(/|$)~', $cosmdl_url_raw)) {
        $cosmdl_url_raw = 'https://' . $cosmdl_url_raw;
    }
}

$cosmdl_url = esc_url_raw($cosmdl_url_raw);
if ($cosmdl_url === '') {
    cosmdl_handle_error(
        esc_html__('下载地址无效', 'cosmautdl'),
        array(
            'post_id'  => $cosmdl_post_id,
            'type'     => $cosmdl_type_to_log,
            'attach'   => $cosmdl_attach,
            'url_raw'  => $cosmdl_url_raw,
        )
    );
}

// 中文注释：可选的解锁检查，主题或自定义代码可通过过滤器扩展
$cosmdl_need_unlock = apply_filters('cosmdl_need_unlock', $cosmdl_need_unlock_one, $cosmdl_post_id, $cosmdl_type_to_log);
if ($cosmdl_need_unlock) {
    // 中文注释：当开启“扫码解锁”时，要求当前请求携带有效的 scene 标识且已完成解锁
    $cosmdl_scene = sanitize_text_field((string) get_query_var('scene'));
    if (!$cosmdl_scene || !get_transient('cosmdl_unlocked_' . $cosmdl_scene)) {
        cosmdl_handle_error(
            esc_html__('请先通过扫码解锁后再下载此资源。', 'cosmautdl'),
            array(
                'post_id' => $cosmdl_post_id,
                'type'    => $cosmdl_type_to_log,
                'attach'  => $cosmdl_attach,
                'reason'  => 'unlock_required',
            )
        );
    }

    // 中文注释：预留过滤器，方便站点做额外权限控制（如会员等级、积分等）
    $cosmdl_can = apply_filters('cosmdl_can_redirect', true, $cosmdl_post_id, $cosmdl_type_to_log);
    if (!$cosmdl_can) {
        cosmdl_handle_error(__('您没有权限访问此下载链接。', 'cosmautdl'));
    }
}

// 中文注释：执行安全重定向（加入目标域白名单）
$cosmdl_target_host = wp_parse_url($cosmdl_url, PHP_URL_HOST);
if ($cosmdl_target_host){
    add_filter('allowed_redirect_hosts', function($hosts) use ($cosmdl_target_host){
        $hosts[] = $cosmdl_target_host;
        return array_values(array_unique(array_filter($hosts)));
    });
}
// 中文注释：记录点击统计
global $wpdb;
$cosmdl_clicks_table = $wpdb->prefix . 'cosmdl_clicks';
$cosmdl_user_id = get_current_user_id();
$cosmdl_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$cosmdl_ip = is_string($cosmdl_ip) ? sanitize_text_field($cosmdl_ip) : '';
if ($cosmdl_ip === '' && isset($_SERVER['REMOTE_ADDR'])) {
    $cosmdl_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
}

if ($cosmdl_ip === '') {
    $cosmdl_ip_candidates = array();
    $cosmdl_server_keys = array(
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    );

    foreach ($cosmdl_server_keys as $cosmdl_server_key) {
        if (!isset($_SERVER[$cosmdl_server_key]) || !is_string($_SERVER[$cosmdl_server_key])) {
            continue;
        }

        $cosmdl_candidate = sanitize_text_field(wp_unslash($_SERVER[$cosmdl_server_key]));
        if ($cosmdl_candidate !== '') {
            $cosmdl_ip_candidates[] = $cosmdl_candidate;
        }
    }

    foreach ($cosmdl_ip_candidates as $cosmdl_raw_ip) {
        $cosmdl_raw_ip = trim($cosmdl_raw_ip);
        if ($cosmdl_raw_ip === '') {
            continue;
        }

        if (strpos($cosmdl_raw_ip, ',') !== false) {
            $cosmdl_parts = explode(',', $cosmdl_raw_ip);
            $cosmdl_raw_ip = isset($cosmdl_parts[0]) ? trim($cosmdl_parts[0]) : '';
        }

        if ($cosmdl_raw_ip !== '' && preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $cosmdl_raw_ip)) {
            $cosmdl_raw_ip = preg_replace('/:\d+$/', '', $cosmdl_raw_ip);
        }

        $cosmdl_raw_ip = sanitize_text_field($cosmdl_raw_ip);
        if ($cosmdl_raw_ip !== '' && filter_var($cosmdl_raw_ip, FILTER_VALIDATE_IP)) {
            $cosmdl_ip = $cosmdl_raw_ip;
            break;
        }
    }
}

$cosmdl_ua = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$cosmdl_ua = is_string($cosmdl_ua) ? sanitize_text_field($cosmdl_ua) : '';
if ($cosmdl_ua === '' && isset($_SERVER['HTTP_USER_AGENT'])) {
    $cosmdl_ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
}
$cosmdl_ref = wp_get_referer();
$cosmdl_ref = is_string($cosmdl_ref) ? esc_url_raw($cosmdl_ref) : '';
// 使用WordPress的current_time函数获取GMT时间并存储到数据库，确保时间显示统一（显示时再转为本地时间）
$cosmdl_time = current_time('mysql', 1);
// 插入日志（忽略错误）
$cosmdl_insert_data = array(
    'post_id' => $cosmdl_post_id,
    'type' => $cosmdl_type_to_log,
    'attach_id' => $cosmdl_attach, // 附件编号
    'user_id' => $cosmdl_user_id ? intval($cosmdl_user_id) : 0,
    'ip' => $cosmdl_ip,
    'ua' => $cosmdl_ua,
    'referer' => $cosmdl_ref,
    'success' => 1,
    'created_at' => $cosmdl_time,
);
if (defined('WP_DEBUG') && WP_DEBUG) {
    cosmdl_write_log('Pre-Insert Data', 'info', $cosmdl_insert_data);
}

$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $cosmdl_clicks_table,
    $cosmdl_insert_data,
    array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s')
);

if (defined('WP_DEBUG') && WP_DEBUG) {
    cosmdl_write_log('Post-Insert Query', 'info', array('error' => $wpdb->last_error));
}


// 记录重定向成功
if (defined('WP_DEBUG') && WP_DEBUG) {
    cosmdl_write_log('下载重定向成功', 'success', array(
        'post_id' => $cosmdl_post_id,
        'type' => $cosmdl_type_to_log,
        'url_length' => strlen($cosmdl_url)
    ));
}

// 中文注释：执行安全重定向
wp_safe_redirect(esc_url_raw($cosmdl_url));
exit;

?>
