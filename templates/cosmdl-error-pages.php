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

$cosmdl_color_hex = isset($cosmdl_error_info['color']) ? sanitize_hex_color($cosmdl_error_info['color']) : '';
if (!$cosmdl_color_hex) {
    $cosmdl_color_hex = '#6f42c1';
}
$cosmdl_color_hex_stripped = ltrim($cosmdl_color_hex, '#');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr($cosmdl_charset); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($cosmdl_error_info['title']); ?> - <?php echo esc_html($cosmdl_site_name); ?></title>

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .error-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-header {
            padding: 40px 30px;
            text-align: center;
            background: linear-gradient(135deg, #<?php echo esc_html($cosmdl_color_hex_stripped); ?> 0%, rgba(255,255,255,0.1) 100%);
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: #2c3e50;
        }
        
        .error-body {
            padding: 40px 30px;
        }
        
        .error-description {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .error-details {
            background: #f8f9fa;
            border-left: 4px solid #<?php echo esc_html($cosmdl_color_hex_stripped); ?>;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9rem;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #<?php echo esc_html($cosmdl_color_hex_stripped); ?> 0%, #<?php echo esc_html($cosmdl_color_hex_stripped); ?>cc 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .help-section {
            background: #e3f2fd;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .help-title {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .help-content {
            color: #424242;
            font-size: 0.95rem;
        }
        
        .help-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .help-list li {
            margin: 5px 0;
        }
        
        .error-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .error-container {
                margin: 10px;
            }
            
            .error-header {
                padding: 30px 20px;
            }
            
            .error-body {
                padding: 30px 20px;
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(<?php echo esc_html(cosmdl_hex2rgb($cosmdl_color_hex)); ?>, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(<?php echo esc_html(cosmdl_hex2rgb($cosmdl_color_hex)); ?>, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(<?php echo esc_html(cosmdl_hex2rgb($cosmdl_color_hex)); ?>, 0);
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header" style="border-bottom: 1px solid #e9ecef;">
            <div class="error-icon"><?php echo esc_html($cosmdl_error_info['icon']); ?></div>
            <h1 class="error-title"><?php echo esc_html($cosmdl_error_info['title']); ?></h1>
        </div>
        
        <div class="error-body">
            <p class="error-description"><?php echo esc_html($cosmdl_error_info['description']); ?></p>
            
            <?php if (!empty($cosmdl_error_message)): ?>
            <div class="error-details">
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
            <div class="error-details">
                <strong><?php esc_html_e('上下文调试信息（仅管理员可见）：', 'cosmautdl'); ?></strong>
                <br>
                <?php if (!empty($cosmdl_context_array)): ?>
                    <pre style="white-space:pre-wrap;word-break:break-all;">
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
            
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-primary pulse">
                    <?php esc_html_e('返回上一页', 'cosmautdl'); ?>
                </a>
                <a href="<?php echo esc_url($cosmdl_home_url); ?>" class="btn btn-secondary">
                    <?php esc_html_e('返回首页', 'cosmautdl'); ?>
                </a>
                <?php if (current_user_can('manage_options')): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cosmdl-settings')); ?>" class="btn btn-secondary">
                    <?php esc_html_e('插件设置', 'cosmautdl'); ?>
                </a>
                <?php endif; ?>
            </div>
            
        </div>
        
        <div class="error-footer">
            <p>
                <?php
                /* translators: 1: 错误代码, 2: 时间 */
                printf(esc_html__('错误代码：%1$s | 时间：%2$s', 'cosmautdl'),
                    esc_html($cosmdl_error_code), 
                    esc_html(current_time('Y-m-d H:i:s'))
                ); ?>
            </p>
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <p style="font-size: 0.8rem; color: #999;">
                <?php esc_html_e('调试模式已启用', 'cosmautdl'); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 添加键盘快捷键支持
        document.addEventListener('keydown', function(e) {
            // ESC键返回上一页
            if (e.key === 'Escape') {
                history.back();
            }
            // H键返回首页
            if (e.key === 'h' || e.key === 'H') {
                window.location.href = <?php echo wp_json_encode($cosmdl_home_url); ?>;
            }
        });
        
        // 自动刷新提示（可选）
        <?php if ($cosmdl_error_code === 'service_unavailable'): ?>
        setTimeout(function() {
            if (confirm(<?php echo wp_json_encode(__('服务可能已恢复，是否刷新页面重试？', 'cosmautdl')); ?>)) {
                location.reload();
            }
        }, 30000); // 30秒后提示
        <?php endif; ?>
        
        // 错误上报（如果启用）
        <?php if (defined('COSMDL_ERROR_REPORTING') && COSMDL_ERROR_REPORTING): ?>
        if (typeof CosmautDLErrorReporting !== 'undefined') {
            CosmautDLErrorReporting.reportError({
                code: <?php echo wp_json_encode($cosmdl_error_code); ?>,
                message: <?php echo wp_json_encode($cosmdl_error_message); ?>,
                context: <?php echo wp_json_encode($cosmdl_context_json); ?>,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// 辅助函数：将十六进制颜色转换为RGB
function cosmdl_hex2rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}
?>
