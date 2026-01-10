<?php
/**
 * CosmautDL 错误处理页面模板
 * 提供用户友好的错误提示和操作指引
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取错误信息（兼容旧参数名，并与 cosmdl_handle_error 保持一致）
$cosmdl_error_code = sanitize_key((string) get_query_var('error_code'));
if ($cosmdl_error_code === '') {
    $cosmdl_error_code = 'unknown_error';
}

// 优先读取统一的 message 参数，兼容早期使用 error_message 的链接
$cosmdl_error_message_raw = (string) get_query_var('message');
if ($cosmdl_error_message_raw === '') {
    $cosmdl_error_message_raw = (string) get_query_var('error_message');
}
$cosmdl_error_message = $cosmdl_error_message_raw !== '' ? sanitize_text_field($cosmdl_error_message_raw) : '';

// 解析 context：原始为 json_encode 后 urlencode，仅在需要时解码
$cosmdl_context_raw = (string) get_query_var('context');
$cosmdl_context_json = $cosmdl_context_raw;

// 错误类型映射（默认中文，作为插件基础语言）
$cosmdl_error_types = array(
    'invalid_params' => array(
        'title' => __('参数无效', 'cosmautdl'),
        'description' => __('您提供的参数不正确，请检查链接或联系管理员。', 'cosmautdl'),
        'icon' => '⚠️',
        'color' => '#ffc107'
    ),
    'download_not_found' => array(
        'title' => __('下载地址不存在', 'cosmautdl'),
        'description' => __('指定的下载资源不存在或已被删除。', 'cosmautdl'),
        'icon' => '❌',
        'color' => '#dc3545'
    ),
    'network_error' => array(
        'title' => __('网络错误', 'cosmautdl'),
        'description' => __('网络连接出现问题，请稍后重试。', 'cosmautdl'),
        'icon' => '🌐',
        'color' => '#17a2b8'
    ),
    'permission_denied' => array(
        'title' => __('权限不足', 'cosmautdl'),
        'description' => __('您没有权限访问此资源。', 'cosmautdl'),
        'icon' => '🔒',
        'color' => '#6c757d'
    ),
    'service_unavailable' => array(
        'title' => __('服务不可用', 'cosmautdl'),
        'description' => __('下载服务当前不可用，请稍后重试。', 'cosmautdl'),
        'icon' => '🔧',
        'color' => '#fd7e14'
    ),
    'unknown_error' => array(
        'title' => __('未知错误', 'cosmautdl'),
        'description' => __('发生了未知错误，请联系技术支持。', 'cosmautdl'),
        'icon' => '❓',
        'color' => '#6f42c1'
    )
);

$cosmdl_error_info = isset($cosmdl_error_types[$cosmdl_error_code]) ? $cosmdl_error_types[$cosmdl_error_code] : $cosmdl_error_types['unknown_error'];

$cosmdl_home_url = home_url('/');
$cosmdl_site_name = get_bloginfo('name');
$cosmdl_charset = get_bloginfo('charset');

$cosmdl_error_code_class = 'cosmdl-error-code-' . sanitize_html_class($cosmdl_error_code);

$cosmdl_back_url = wp_get_referer();
if (!is_string($cosmdl_back_url) || $cosmdl_back_url === '') {
    $cosmdl_back_url = $cosmdl_home_url;
}
$cosmdl_back_url = wp_validate_redirect($cosmdl_back_url, $cosmdl_home_url);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr($cosmdl_charset); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($cosmdl_error_info['title']); ?> - <?php echo esc_html($cosmdl_site_name); ?></title>

    <?php wp_head(); ?>
</head>
<body class="cosmdl-error-body <?php echo esc_attr($cosmdl_error_code_class); ?>">
    <div class="cosmdl-error-container">
        <div class="cosmdl-error-header">
            <div class="cosmdl-error-icon"><?php echo esc_html($cosmdl_error_info['icon']); ?></div>
            <h1 class="cosmdl-error-title"><?php echo esc_html($cosmdl_error_info['title']); ?></h1>
        </div>
        
        <div class="cosmdl-error-body-inner">
            <p class="cosmdl-error-description"><?php echo esc_html($cosmdl_error_info['description']); ?></p>
            
            <?php if (!empty($cosmdl_error_message)): ?>
            <div class="cosmdl-error-details">
                <strong><?php esc_html_e('错误详情：', 'cosmautdl'); ?></strong>
                <br><?php echo esc_html($cosmdl_error_message); ?>
            </div>
            <?php endif; ?>
            
            <?php
            // 仅在调试模式且当前用户为管理员时展示上下文 JSON，避免向普通访客暴露敏感信息
            $cosmdl_context_array = array();
            $cosmdl_context_fallback = '';
            if ($cosmdl_context_json !== '') {
                $cosmdl_decoded = json_decode($cosmdl_context_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($cosmdl_decoded)) {
                    $cosmdl_context_array = $cosmdl_decoded;
                } else {
                    $cosmdl_context_fallback = $cosmdl_context_json;
                }
            }
            $cosmdl_show_context_debug = (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options'));
            if ($cosmdl_show_context_debug && (!empty($cosmdl_context_array) || $cosmdl_context_fallback !== '')): ?>
            <div class="cosmdl-error-details">
                <strong><?php esc_html_e('上下文调试信息（仅管理员可见）：', 'cosmautdl'); ?></strong>
                <br>
                <?php if (!empty($cosmdl_context_array)): ?>
                    <pre class="cosmdl-error-pre">
<?php
$cosmdl_context_pretty = wp_json_encode($cosmdl_context_array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!is_string($cosmdl_context_pretty) || $cosmdl_context_pretty === '') {
    $cosmdl_context_pretty = wp_json_encode($cosmdl_context_array);
}
echo esc_html((string) $cosmdl_context_pretty);
?>
                    </pre>
                <?php else: ?>
                    <?php echo esc_html($cosmdl_context_fallback); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="cosmdl-error-actions">
                <a href="<?php echo esc_url($cosmdl_back_url); ?>" class="cosmdl-error-btn cosmdl-error-btn-primary cosmdl-error-pulse">
                    <?php esc_html_e('返回上一页', 'cosmautdl'); ?>
                </a>
                <a href="<?php echo esc_url($cosmdl_home_url); ?>" class="cosmdl-error-btn cosmdl-error-btn-secondary">
                    <?php esc_html_e('返回首页', 'cosmautdl'); ?>
                </a>
                <?php if (current_user_can('manage_options')): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cosmdl-settings')); ?>" class="cosmdl-error-btn cosmdl-error-btn-secondary">
                    <?php esc_html_e('插件设置', 'cosmautdl'); ?>
                </a>
                <?php endif; ?>
            </div>
            
        </div>
        
        <div class="cosmdl-error-footer">
            <p>
                <?php
                /* translators: 1: 错误代码, 2: 时间 */
                printf(esc_html__('错误代码：%1$s | 时间：%2$s', 'cosmautdl'),
                    esc_html($cosmdl_error_code), 
                    esc_html(current_time('Y-m-d H:i:s'))
                ); ?>
            </p>
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <p class="cosmdl-error-debug">
                <?php esc_html_e('调试模式已启用', 'cosmautdl'); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
