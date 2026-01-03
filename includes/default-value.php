<?php
/**
 * CosmaUTDL 插件默认配置值
 * 
 * 本文件包含插件的默认配置值，按功能模块组织，便于维护和扩展。
 * 使用方法：在主类中通过 require_once 引入，然后调用对应的获取函数。
 * 
 * @package CosmaUTDL
 * @version 2.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取插件基础功能默认配置
 * 
 * @return array 基础功能配置数组
 */
function cosmdl_get_basic_defaults() {
    return array(
        'plugin_active' => 'yes',           // 插件启用状态
        'route_prefix' => 'get',            // 路由前缀（仅跳转路由，如 /get/123/baidu.html）
        'error_handling' => 'message',
        'enable_logging' => 'no',
        'debug_mode' => 'no',
        'download_modules_order' => array('statement', 'fileinfo', 'custom_links', 'pan_cards', 'download_tips', 'owner_statement'),
        // 中文注释：下载统计 - IP 归属地显示（仅后台管理员可见，用于辅助排查与统计）
        // 注意：启用后会将 IP 发送到第三方 IP 解析服务获取归属地信息（服务端请求，并做缓存）
        'stats_ip_geo' => 'yes',
        // 中文注释：第三方解析服务（可选：ipapi / ip-api / ipinfo）
        'stats_ip_geo_provider' => 'ipapi',
        // 中文注释：缓存小时数（减少对外部服务的请求次数，提升后台打开速度）
        'stats_ip_geo_cache_hours' => 168,
    );
}

/**
 * 获取下载声明模块默认配置
 * 
 * @return array 下载声明模块配置数组
 */
function cosmdl_get_statement_defaults() {
    return array(
        'show_statement' => 'yes',          // 是否显示下载声明模块
        'statement_title' => '下载声明',    // 声明模块标题
        'statement_text' => '本站文件与软件用于学习与研究，不得用于商业或非法用途。若内容涉及版权或隐私，请「<a href="mailto:cosmaut@hotmail.com" class="custom-link">联系撤下</a>」。',             // 声明内容文本
        'statement_border_color' => '#e5e7eb',   // 边框颜色
        'statement_border_color_hex' => '',
        'statement_bg_color' => '#ffffff',       // 背景颜色
        'statement_bg_color_hex' => '',
        'statement_title_color' => '#111827',    // 标题颜色
        'statement_title_color_hex' => '',
        'statement_text_color' => '#6b7280',     // 正文颜色
        'statement_text_color_hex' => '',
        'statement_custom_css' => '',             // 自定义CSS
        'statement_card_theme' => '',
    );
}

function cosmdl_get_file_info_defaults() {
    return array(
        'show_fileinfo' => 'yes',
        'file_info_title' => '文件信息',
        'file_info_card_theme' => 'blue',
        'file_info_card_border_radius' => 'medium',
        'file_info_card_shadow' => 'yes',
        'file_info_border_color' => '',
        'file_info_bg_color' => '',
        'file_info_title_color' => '',
        'file_info_text_color' => '',
        'file_info_custom_css' => '',
    );
}

/**
 * 获取广告模块默认配置
 * 
 * @return array 广告模块配置数组
 */
function cosmdl_get_ad_defaults() {
    return array(
        'show_ad_slot' => 'no',             // 是否显示广告位
        'ad_html' => '',                    // 广告HTML内容
    );
}

/**
 * 获取自定义链接模块默认配置
 * 
 * @return array 自定义链接模块配置数组
 */
function cosmdl_get_custom_links_defaults() {
    return array(
        'show_custom_links' => 'yes',       // 是否显示自定义链接模块
        'custom_links_title' => '', // 链接模块标题
        'custom_link_1_label' => '星海博客',        // 自定义链接1标签
        'custom_link_1_url' => 'https://cosmaut.com/',          // 自定义链接1地址
        'custom_link_2_label' => '星海博客',        // 自定义链接2标签
        'custom_link_2_url' => 'https://cosmaut.com/',          // 自定义链接2地址
        'custom_link_3_label' => '星海博客',        // 自定义链接3标签
        'custom_link_3_url' => 'https://cosmaut.com/',          // 自定义链接3地址
        'custom_link_4_label' => '星海博客',        // 自定义链接4标签
        'custom_link_4_url' => 'https://cosmaut.com/',          // 自定义链接4地址
        'custom_links_border_color' => '#e5e7eb',
        'custom_links_bg_color' => '#ffffff',
        'custom_links_title_color' => '#111827',
        'custom_links_text_color' => '#6b7280',
        'custom_links_border_color_hex' => '#e5e7eb',
        'custom_links_bg_color_hex' => '#ffffff',
        'custom_links_title_color_hex' => '#111827',
        'custom_links_text_color_hex' => '#6b7280',
        'custom_links_custom_css' => '',
        'custom_links_card_theme' => '',
    );
}

/**
 * 获取网盘卡片（下载按钮组）模块默认配置
 *
 * @return array 网盘卡片模块配置数组
 */
function cosmdl_get_pan_cards_defaults() {
    return array(
        'show_pan_cards' => 'yes',           // 是否显示网盘卡片
        'pan_cards_title' => '',    // 模块标题
        'pan_cards_html' => '',              // 模块内容
        'pan_cards_border_color' => '#e5e7eb', // 边框颜色
        'pan_cards_border_color_hex' => '',
        'pan_cards_bg_color' => '#ffffff',    // 背景颜色
        'pan_cards_bg_color_hex' => '',
        'pan_cards_title_color' => '#111827', // 标题颜色
        'pan_cards_title_color_hex' => '',
        'pan_cards_text_color' => '#6b7280',  // 正文颜色
        'pan_cards_text_color_hex' => '',
        'pan_cards_custom_css' => '',         // 自定义CSS
        'pan_cards_card_theme' => '',
    );
}

/**
 * 获取二维码模块默认配置
 * 
 * @return array 二维码模块配置数组
 */
function cosmdl_get_qr_defaults() {
    return array(
        // 中文注释：二维码解锁模式（static=静态扫码解锁，wechat=关注公众号解锁，group=扫码进群解锁）
        'qr_unlock_mode' => 'static',       // 二维码解锁模式
        'show_qr_block' => 'no',            // 是否显示二维码区块
        'qr_image_url' => '',               // 自定义二维码图片URL
        'wechat_appid' => '',               // 微信公众号AppID
        'wechat_appsecret' => '',           // 微信公众号AppSecret
        'qr_follow_text' => '关注公众号后自动解锁下载链接', // 关注引导文字
    );
}



/**
 * 获取下载说明模块默认配置
 * 
 * @return array 下载说明模块配置数组
 */
function cosmdl_get_download_tips_defaults() {
    return array(
        'show_download_tips' => 'yes',      // 是否显示下载说明模块
        'download_tips_title' => '下载说明', // 说明模块标题
        'download_tips_html' => '<ul class="help"><li>1. 压缩包若设置密码，请参看页面说明或联系站点管理员。</li><li>2. 建议使用可靠的下载工具，避免浏览器断流引起错误。</li><li>3. 如遇链接失效，请在文章页留言或私信反馈，我们 会尽快修复。</li><li>4. 请合理使用资源，遵循相关法律法规。</li><li>5. 本站默认解压密码： cosmaut</li></ul>',                          // 说明内容HTML
        'download_tips_border_color' => '#e5e7eb', // 下载说明模块边框颜色
        'download_tips_border_color_hex' => '',
        'download_tips_bg_color' => '#ffffff',     // 下载说明模块背景颜色
        'download_tips_bg_color_hex' => '',
        'download_tips_title_color' => '#111827',  // 下载说明模块标题颜色
        'download_tips_title_color_hex' => '',
        'download_tips_text_color' => '#6b7280',   // 下载说明模块正文颜色
        'download_tips_text_color_hex' => '',
        'download_tips_custom_css' => '',          // 下载说明模块自定义CSS
        'download_tips_card_theme' => '',
    );
}

/**
 * 获取站长声明模块默认配置
 * 
 * @return array 站长声明模块配置数组
 */
function cosmdl_get_owner_statement_defaults() {
    return array(
        'show_owner_statement' => 'yes',    // 是否显示站长声明模块
        'owner_statement_title' => '站长声明', // 声明模块标题
        'owner_statement_html' => '<p class="shengming">本站资源多来源于公开网络，仅供学习交流。请尊重原创与版权， 商业用途请至官方渠道获取授权。若本页内容侵犯您的权益，可点击「<a href="mailto:cosmaut@hotmail.com" class="custom-link">联系撤下</a>」与我们沟通处理。</p>', // 声明内容HTML
        'owner_statement_border_color' => '#e5e7eb', // 站长声明模块边框颜色
        'owner_statement_border_color_hex' => '',
        'owner_statement_bg_color' => '#ffffff',     // 站长声明模块背景颜色
        'owner_statement_bg_color_hex' => '',
        'owner_statement_title_color' => '#111827',  // 站长声明模块标题颜色
        'owner_statement_title_color_hex' => '',
        'owner_statement_text_color' => '#6b7280',   // 站长声明模块正文颜色
        'owner_statement_text_color_hex' => '',
        'owner_statement_custom_css' => '',          // 站长声明模块自定义CSS
        'owner_statement_card_theme' => '',
    );
}

/**
 * 获取文件树模块默认配置
 * 
 * @return array 文件树模块配置数组
 */
function cosmdl_get_tree_defaults() {
    return array(
        'enable_tree' => 'yes',             // 是否启用文件树
        'tree_visibility' => 'public',      // 文件树可见性（public/admin）
        'tree_open_links_in_new_window' => 'yes', // 文件树链接是否新窗口打开
    );
}

/**
 * 获取界面样式默认配置
 * 
 * @return array 界面样式配置数组
 */
function cosmdl_get_style_defaults() {
    return array(
        'metabox_collapsed' => 'yes',       // 元框是否默认折叠
        'card_border_radius' => 'medium',   // 卡片圆角大小
        'card_shadow' => 'yes',             // 卡片阴影效果
        'card_theme' => 'green',
        'text_color' => '#333333',          // 文本颜色（用于颜色选择器避免空值显示 #000000）
        'tip_color' => '#2271b1',           // 提示文字颜色
        'tip_bg_color' => '#f0f7ff',        // 提示背景颜色
        'warning_color' => '#d63638',       // 警告文字颜色
        'warning_bg_color' => '#fff4f4',    // 警告背景颜色
        'custom_css' => '',                 // 自定义CSS样式
    );
}

/**
 * 获取网盘管理默认配置
 * 
 * @return array 网盘管理配置数组
 */
function cosmdl_get_drive_management_defaults() {
    $drives = array(
        'baidu' => array(
            'enabled' => 'yes',
            'label' => '百度网盘',
            'order' => 1,
            'alias' => 'baidu',
            'is_custom' => 'no'
        ),
        '123' => array(
            'enabled' => 'yes',
            'label' => '123云盘',
            'order' => 2,
            'alias' => '123',
            'is_custom' => 'no'
        ),
        'ali' => array(
            'enabled' => 'yes',
            'label' => '阿里云盘',
            'order' => 3,
            'alias' => 'ali',
            'is_custom' => 'no'
        ),
        '189' => array(
            'enabled' => 'yes',
            'label' => '天翼云盘',
            'order' => 4,
            'alias' => '189',
            'is_custom' => 'no'
        ),
        'quark' => array(
            'enabled' => 'yes',
            'label' => '夸克网盘',
            'order' => 5,
            'alias' => 'quark',
            'is_custom' => 'no'
        ),
        'pikpak' => array(
            'enabled' => 'yes',
            'label' => 'PikPak',
            'order' => 6,
            'alias' => 'pikpak',
            'is_custom' => 'no'
        ),
        'lanzou' => array(
            'enabled' => 'no',
            'label' => '蓝奏云网盘',
            'order' => 7,
            'alias' => 'lanzou',
            'is_custom' => 'no'
        ),
        'xunlei' => array(
            'enabled' => 'no',
            'label' => '迅雷云盘',
            'order' => 8,
            'alias' => 'xunlei',
            'is_custom' => 'no'
        ),
        'weiyun' => array(
            'enabled' => 'no',
            'label' => '微云',
            'order' => 9,
            'alias' => 'weiyun',
            'is_custom' => 'no'
        ),
        'onedrive' => array(
            'enabled' => 'no',
            'label' => 'OneDrive',
            'order' => 10,
            'alias' => 'onedrive',
            'is_custom' => 'no'
        ),
        'googledrive' => array(
            'enabled' => 'no',
            'label' => 'GoogleDrive',
            'order' => 11,
            'alias' => 'googledrive',
            'is_custom' => 'no'
        ),
        'dropbox' => array(
            'enabled' => 'no',
            'label' => 'Dropbox',
            'order' => 12,
            'alias' => 'dropbox',
            'is_custom' => 'no'
        ),
        'mega' => array(
            'enabled' => 'no',
            'label' => 'MEGA',
            'order' => 13,
            'alias' => 'mega',
            'is_custom' => 'no'
        ),
        'mediafire' => array(
            'enabled' => 'no',
            'label' => 'MediaFire',
            'order' => 14,
            'alias' => 'mediafire',
            'is_custom' => 'no'
        ),
        'box' => array(
            'enabled' => 'no',
            'label' => 'Box',
            'order' => 15,
            'alias' => 'box',
            'is_custom' => 'no'
        ),
        'other' => array(
            'enabled' => 'no',
            'label' => '其他网盘',
            'order' => 16,
            'alias' => 'other',
            'is_custom' => 'no'
        )
    );
    
    return $drives;
}

/**
 * 获取网盘管理默认配置（别名函数）
 * 
 * 为了向后兼容，提供一个更简洁的函数名别名。
 * 
 * @return array 网盘管理配置数组
 */
function cosmdl_get_drive_defaults() {
    return cosmdl_get_drive_management_defaults();
}

/**
 * 获取完整的默认配置数组
 * 
 * 整合所有模块的默认值，按优先级排序：
 * 1. 基础功能配置
 * 2. 内容模块配置（声明、广告、链接等）
 * 3. 功能模块配置（二维码、密码、说明等）
 * 4. 界面样式配置
 * 5. 网盘管理配置
 * 
 * @return array 完整的默认配置数组
 */
function cosmdl_get_all_defaults() {
    // 按优先级和依赖关系合并所有默认值
    $defaults = array();
    
    // 1. 基础功能配置（最先加载，优先级最高）
    $defaults = array_merge($defaults, cosmdl_get_basic_defaults());
    
    // 2. 内容模块配置
    $defaults = array_merge($defaults, cosmdl_get_statement_defaults());
    $defaults = array_merge($defaults, cosmdl_get_file_info_defaults());
    $defaults = array_merge($defaults, cosmdl_get_ad_defaults());
    $defaults = array_merge($defaults, cosmdl_get_custom_links_defaults());
    $defaults = array_merge($defaults, cosmdl_get_pan_cards_defaults());
    
    // 3. 功能模块配置
    $defaults = array_merge($defaults, cosmdl_get_qr_defaults());
    $defaults = array_merge($defaults, cosmdl_get_download_tips_defaults());
    $defaults = array_merge($defaults, cosmdl_get_owner_statement_defaults());
    $defaults = array_merge($defaults, cosmdl_get_tree_defaults());
    
    // 4. 界面样式配置
    $defaults = array_merge($defaults, cosmdl_get_style_defaults());
    
    // 5. 网盘管理配置（最后加载，覆盖前面的设置）
    $defaults['drive_management'] = cosmdl_get_drive_management_defaults();
    
    return $defaults;
}

/**
 * 按模块获取默认配置（用于增量更新）
 * 
 * @param string $module 模块名称
 * @return array|false 对应模块的默认配置，失败返回 false
 */
function cosmdl_get_module_defaults($module) {
    $method_map = array(
        'basic' => 'cosmdl_get_basic_defaults',
        'statement' => 'cosmdl_get_statement_defaults',
        'fileinfo' => 'cosmdl_get_file_info_defaults',
        'ad' => 'cosmdl_get_ad_defaults',
        'custom_links' => 'cosmdl_get_custom_links_defaults',
        'pan_cards' => 'cosmdl_get_pan_cards_defaults',
        'qr' => 'cosmdl_get_qr_defaults',
        'download_tips' => 'cosmdl_get_download_tips_defaults',
        'owner_statement' => 'cosmdl_get_owner_statement_defaults',
        'tree' => 'cosmdl_get_tree_defaults',
        'style' => 'cosmdl_get_style_defaults',
        'drive_management' => 'cosmdl_get_drive_management_defaults',
    );
    
    if (isset($method_map[$module]) && function_exists($method_map[$module])) {
        return call_user_func($method_map[$module]);
    }
    
    return false;
}
