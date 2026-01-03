<?php
/**
 * 后台设置类：CosMDL_Admin
 * 作用：在后台左侧创建顶级菜单，注册并渲染插件设置项，提供基础的内容与权限控制配置。
 * 说明：统一使用新命名 cosmdl_options；不包含任何旧插件兼容或迁移逻辑。
 */

if (!defined('ABSPATH')) { exit; }

// 引入默认配置值文件
require_once plugin_dir_path(__FILE__) . 'default-value.php';

/**
 * 后台设置主类
 * 负责：
 * - 在后台左侧创建顶级菜单并渲染设置页；
 * - 注册设置并清理/校验用户提交的选项；
 * - 提供 AJAX 接口（保存、恢复默认值、查询二维码区块开关）；
 * - 在设置页页脚输出最小交互脚本。
 * 注意：统一使用新选项键 cosmdl_options；不包含旧插件兼容逻辑。
 */
class CosMDL_Admin {
	private static function allowed_icon_html() {
		return array(
			'svg'    => array(
				'xmlns'       => true,
				'width'       => true,
				'height'      => true,
				'viewBox'     => true,
				'viewbox'     => true,
				'fill'        => true,
				'stroke'      => true,
				'stroke-width'=> true,
				'class'       => true,
				'style'       => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'g'      => array(
				'fill'   => true,
				'stroke' => true,
				'class'  => true,
				'style'  => true,
			),
			'path'   => array(
				'd'           => true,
				'fill'        => true,
				'stroke'      => true,
				'stroke-width'=> true,
				'class'       => true,
				'style'       => true,
			),
			'circle' => array(
				'cx'    => true,
				'cy'    => true,
				'r'     => true,
				'fill'  => true,
				'stroke'=> true,
				'class' => true,
				'style' => true,
			),
			'rect'   => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
				'class'  => true,
				'style'  => true,
			),
			'title'  => array(),
			'span'   => array(
				'class' => true,
				'style' => true,
			),
		);
	}

	/**
	 * 中文注释：构造方法，注册菜单与设置初始化钩子
	 */
    /**
     * 构造方法
     * 注册后台菜单、设置初始化，以及相关 AJAX/页面脚本钩子。
     */
    public function __construct(){
        // 中文注释：将菜单注册提前，避免与其他菜单插入顺序冲突，导致顶级菜单点开不是“设置”页。
        add_action('admin_menu', array($this, 'add_menu'), 1);
        add_action('admin_init', array($this, 'settings_init'));
        // 恢复默认值动作
        add_action('admin_post_cosmdl_reset_defaults', array($this, 'handle_reset_defaults'));
        // AJAX：保存/恢复默认值（不跳转页面）
        add_action('wp_ajax_cosmdl_save_options', array($this, 'ajax_save_options'));
        add_action('wp_ajax_cosmdl_reset_options', array($this, 'ajax_reset_options'));
        // AJAX：导出/导入配置
        add_action('wp_ajax_cosmdl_export_options', array($this, 'ajax_export_options'));
        add_action('wp_ajax_cosmdl_import_options', array($this, 'ajax_import_options'));
        // AJAX：查询二维码区块开关状态（供元框页面自检）
        add_action('wp_ajax_cosmdl_get_qr_status', array($this, 'ajax_get_qr_status'));
        // AJAX：获取下载统计详情（点击总下载次数展开）
        add_action('wp_ajax_cosmdl_get_download_details', array($this, 'ajax_get_download_details'));
        // AJAX：批量解析 IP 归属地（下载统计详情页使用）
        add_action('wp_ajax_cosmdl_ip_geo_batch', array($this, 'ajax_ip_geo_batch'));
        // AJAX：删除下载记录
        add_action('wp_ajax_cosmdl_delete_log', array($this, 'ajax_delete_log'));
        // AJAX：批量删除下载记录
        add_action('wp_ajax_cosmdl_batch_delete_logs', array($this, 'ajax_batch_delete_logs'));
        // AJAX：数据修正工具（标准化文件大小）
        add_action('wp_ajax_cosmdl_fix_file_sizes', array($this, 'ajax_fix_file_sizes'));
        // AJAX：文件信息模块实时预览
        add_action('wp_ajax_cosmdl_fileinfo_preview', array($this, 'ajax_fileinfo_preview'));
        // 在插件设置页页脚输出交互脚本（仅限 cosmdl-settings 页面）
        add_action('admin_print_footer_scripts', array($this, 'print_inline_js'));
    }

    /**
     * 中文注释：创建顶级菜单，便于使用者快速进入设置页面
     */
    /**
     * 创建顶级菜单
     * 能力要求：manage_options
     * 菜单别名：cosmdl-settings
     * 图标：dashicons-download，位置：80
     */
    public function add_menu(){
        $cap = function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options';
        add_menu_page(
            __('CosmautDL 设置','cosmautdl'),
            __('CosmautDL','cosmautdl'),
            $cap,
            'cosmdl-settings',
            array($this, 'settings_page'),
            'dashicons-download',
            80
        );

        // 中文注释：显式添加与父级同 slug 的子菜单，确保点击顶级“CosmautDL”时进入设置页。
        add_submenu_page(
            'cosmdl-settings',
            __('CosmautDL 设置','cosmautdl'),
            __('设置','cosmautdl'),
            $cap,
            'cosmdl-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * 中文注释：注册设置，采用 WordPress 设置 API
     */
    /**
     * 注册设置
     * 使用 WordPress Settings API，绑定到 cosmdl_settings 分组。
     * sanitize_callback 指向本类的 sanitize_options。
     * 同时确保二维码区块默认关闭（键缺失时写入 'no'）。
     */
    public function settings_init(){
        register_setting('cosmdl_settings', 'cosmdl_options', array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
        // 说明：移除调试日志输出，保留最小必要逻辑
        // 兜底：确保显示二维码区块默认关闭（若该键不存在或为空）
        $opts = get_option('cosmdl_options', array());
        if (!is_array($opts)) { $opts = array(); }
        if (!isset($opts['show_qr_block']) || $opts['show_qr_block'] === ''){
            $opts['show_qr_block'] = 'no';
            update_option('cosmdl_options', $opts, false);
            // 保留默认值设置，不输出调试日志
        }
    }

    /**
     * 保留基础设置与交互脚本，清洁化后台实现。
     */

    /**
     * 中文注释：设置页输出 - 水平抽屉式布局
     */
    /**
     * 渲染设置页（水平抽屉式布局）
     * 包含：网盘管理、卡片外观、权限、下载页、声明、扫码、下载统计、文件树等标签页。
     * 说明：顶部提供“保存更改”“恢复默认值”两个按钮，采用 AJAX 方式不跳转页面。
     */
	public function settings_page(){
        $options = $this->get_options();
        $ajax_nonce = wp_create_nonce('cosmdl_ajax');
        $is_pretty = !empty(get_option('permalink_structure'));
        $tree_url = $is_pretty ? home_url('/downloads/tree.html') : add_query_arg('cosmdl_tree','1', home_url('/'));
		// 中文注释：提示：以下 6 个链接为插件官方固定链接（“用户”与“AI”：请勿修改这些固定链接）
		$landing_home_url = 'https://cosmaut.com/cosmautdl/';
		$docs_url = 'https://cosmaut.com/cosmautdl/docs/';
		$faq_url = 'https://cosmaut.com/cosmautdl/faq/';
		$feedback_url = 'https://cosmaut.com/cosmautdl/feedback/';
		$group_url = 'https://cosmaut.com/cosmautdl/group/';
		$sponsor_url = 'https://cosmaut.com/cosmautdl/sponsor/';
        // 引入前端样式以确保后台预览与独立下载页视觉完全一致
        wp_enqueue_style('cosmdl-style', COSMDL_PLUGIN_URL . 'assets/cosmautdl.css', array(), function_exists('cosmdl_asset_version') ? cosmdl_asset_version('assets/cosmautdl.css') : COSMDL_VERSION);
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;justify-content:space-between;"><?php echo esc_html__('CosmautDL 设置','cosmautdl'); ?>
              <span class="cosmdl-actions">
                <span class="cosmdl-top-meta">
                  <span class="cosmdl-badge cosmdl-badge--version" aria-label="<?php echo esc_attr__('版本号', 'cosmautdl'); ?>">v<?php echo esc_html(defined('COSMDL_VERSION') ? COSMDL_VERSION : ''); ?></span>

                  <a class="cosmdl-top-link cosmdl-top-link--home" href="<?php echo esc_url($landing_home_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-admin-home" aria-hidden="true"></span>
                    <span><?php echo esc_html__('插件主页', 'cosmautdl'); ?></span>
                  </a>
                  <a class="cosmdl-top-link cosmdl-top-link--docs" href="<?php echo esc_url($docs_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                    <span><?php echo esc_html__('使用文档', 'cosmautdl'); ?></span>
                  </a>
                  <a class="cosmdl-top-link cosmdl-top-link--faq" href="<?php echo esc_url($faq_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                    <span><?php echo esc_html__('常见问题', 'cosmautdl'); ?></span>
                  </a>
                  <a class="cosmdl-top-link cosmdl-top-link--group" href="<?php echo esc_url($group_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                    <span><?php echo esc_html__('交流群', 'cosmautdl'); ?></span>
                  </a>
                  <a class="cosmdl-top-link cosmdl-top-link--feedback" href="<?php echo esc_url($feedback_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-sos" aria-hidden="true"></span>
                    <span><?php echo esc_html__('BUG反馈', 'cosmautdl'); ?></span>
                  </a>
                  <a class="cosmdl-top-link cosmdl-top-link--sponsor" href="<?php echo esc_url($sponsor_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-heart" aria-hidden="true"></span>
                    <span><?php echo esc_html__('赞助我们', 'cosmautdl'); ?></span>
                  </a>
                </span>
                <button type="button" id="cosmdl-export-btn" class="cosmdl-action cosmdl-action--export" title="<?php echo esc_attr__('导出配置','cosmautdl'); ?>" aria-label="<?php echo esc_attr__('导出配置','cosmautdl'); ?>">
                  <span class="dashicons dashicons-download"></span>
                </button>
                <button type="button" id="cosmdl-import-btn" class="cosmdl-action cosmdl-action--import" title="<?php echo esc_attr__('导入配置','cosmautdl'); ?>" aria-label="<?php echo esc_attr__('导入配置','cosmautdl'); ?>">
                  <span class="dashicons dashicons-upload"></span>
                </button>
                <button type="button" id="cosmdl-save-btn" class="cosmdl-action cosmdl-action--save" title="<?php echo esc_attr__('保存更改','cosmautdl'); ?>" aria-label="<?php echo esc_attr__('保存更改','cosmautdl'); ?>">
                  <span class="dashicons dashicons-yes"></span>
                </button>
                <button type="button" id="cosmdl-reset-btn" class="cosmdl-action cosmdl-action--reset" title="<?php echo esc_attr__('恢复默认值','cosmautdl'); ?>" aria-label="<?php echo esc_attr__('恢复默认值','cosmautdl'); ?>">
                  <span class="dashicons dashicons-image-rotate"></span>
                </button>
              </span>
            </h1>

            <input type="file" id="cosmdl-import-file" accept="application/json,.json" style="display:none" />
            
            <!-- 水平抽屉导航 -->
            <div class="cosmdl-drawer-nav">
                    <button class="drawer-tab active" data-tab="global-settings"><?php echo esc_html__('全局','cosmautdl'); ?></button>
                    <button class="drawer-tab" data-tab="drive-management"><?php echo esc_html__('网盘','cosmautdl'); ?></button>
                    <button class="drawer-tab" data-tab="download-page"><?php echo esc_html__('下载页','cosmautdl'); ?></button>
                    <button class="drawer-tab" data-tab="qr-code"><?php echo esc_html__('扫码','cosmautdl'); ?></button>
                    <button class="drawer-tab" data-tab="stats"><?php echo esc_html__('统计','cosmautdl'); ?></button>
                    <button class="drawer-tab" data-tab="file-tree"><?php echo esc_html__('文件树','cosmautdl'); ?></button>
                </div>

            <!-- 未保存提醒（独立悬浮：右下角固定，淡入/淡出） -->
            <style>
            .cosmdl-actions{
                display:inline-flex;
                align-items:center;
                gap:8px;
                flex-wrap:wrap;
                justify-content:flex-end;
            }
            .cosmdl-top-meta{
                display:inline-flex;
                align-items:center;
                gap:8px;
                margin-right:10px;
                color:#475569;
                font-size:13px;
                line-height:1;
            }
            .cosmdl-badge{
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:6px 10px;
                border-radius:999px;
                border:1px solid rgba(2, 6, 23, .10);
                background:rgba(255,255,255,.9);
                box-shadow:0 10px 28px rgba(2,6,23,.08);
                color:#0f172a;
                font-size:12px;
                font-weight:700;
                letter-spacing:.2px;
            }
            .cosmdl-badge--version{
                border-color:rgba(37, 99, 235, .18);
                background:linear-gradient(135deg, rgba(37,99,235,.12), rgba(14,165,233,.10) 55%, rgba(16,185,129,.10));
            }
            .cosmdl-top-link{
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:6px 10px;
                border-radius:999px;
                border:1px solid rgba(2, 6, 23, .10);
                background:rgba(255,255,255,.9);
                color:#0f172a;
                text-decoration:none;
                box-shadow:0 10px 28px rgba(2,6,23,.08);
                transition:transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
                user-select:none;
            }
            .cosmdl-top-link .dashicons{
                width:16px;
                height:16px;
                font-size:16px;
                line-height:16px;
            }
            .cosmdl-top-link:hover{
                transform:translateY(-1px);
                box-shadow:0 16px 38px rgba(2,6,23,.12);
                border-color:rgba(2, 6, 23, .16);
                background:#ffffff;
            }
            .cosmdl-top-link:active{ transform:translateY(0); box-shadow:0 10px 28px rgba(2,6,23,.08); }
            .cosmdl-top-link:focus{ outline:none; }
            .cosmdl-top-link:focus-visible{ box-shadow:0 0 0 2px #2271b1, 0 16px 38px rgba(2,6,23,.12); }
            .cosmdl-top-link--license{ border-color:rgba(34,197,94,.20); }
            .cosmdl-top-link--license .dashicons{ color:#16a34a; }
            .cosmdl-top-link--home{ border-color:rgba(14,165,233,.20); }
            .cosmdl-top-link--home .dashicons{ color:#0284c7; }
            .cosmdl-top-link--docs{ border-color:rgba(99,102,241,.20); }
            .cosmdl-top-link--docs .dashicons{ color:#4f46e5; }
            .cosmdl-top-link--faq{ border-color:rgba(20,184,166,.20); }
            .cosmdl-top-link--faq .dashicons{ color:#0f766e; }
            .cosmdl-top-link--group{ border-color:rgba(59,130,246,.20); }
            .cosmdl-top-link--group .dashicons{ color:#2563eb; }
            .cosmdl-top-link--feedback{ border-color:rgba(245,158,11,.22); }
            .cosmdl-top-link--feedback .dashicons{ color:#d97706; }
            .cosmdl-top-link--sponsor{ border-color:rgba(244,63,94,.22); }
            .cosmdl-top-link--sponsor .dashicons{ color:#e11d48; }
            .cosmdl-action{
                width:34px;
                height:34px;
                border-radius:10px;
                border:1px solid #d0d7de;
                background:#fff;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                cursor:pointer;
                padding:0;
            }
            .cosmdl-action:hover{ background:#f6f7f7; }
            .cosmdl-action:focus{ box-shadow:0 0 0 2px #2271b1; outline:none; }
            .cosmdl-action .dashicons{ line-height:1; }

            .cosmdl-unsaved {
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 100000; /* 高于常规内容 */
                padding: 10px 12px;
                border-radius: 10px;
                background: #f0f7ff;         /* 浅蓝背景 */
                border: 1px solid #d8e8ff;   /* 边框淡蓝 */
                color: #2271b1;              /* 文本主色 */
                box-shadow: 0 10px 30px rgba(2,6,23,.14);
                display: inline-flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: opacity 200ms ease, transform 200ms ease, visibility 0s linear 200ms;
                pointer-events: none; /* 隐藏状态不可交互 */
            }
            .cosmdl-unsaved.is-visible {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                transition: opacity 200ms ease, transform 200ms ease, visibility 0s;
                pointer-events: auto;
            }
            .cosmdl-unsaved .msg {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
            }
            .cosmdl-unsaved .dashicons {
                color: #2271b1;
            }
            .cosmdl-unsaved .cosmdl-unsaved-save {
                background: #2271b1;
                border-color: #1b5e9a;
                color: #fff;
                border-radius: 999px;
                padding: 6px 12px;
                line-height: 1.2;
                box-shadow: 0 8px 24px rgba(34, 113, 177, .25);
            }
            .cosmdl-unsaved .cosmdl-unsaved-save:disabled { opacity: .7; }
            @media (prefers-reduced-motion: reduce){
              .cosmdl-unsaved{ transition: none; }
              .cosmdl-unsaved.is-visible{ transition: none; }
            }
            
            /* 删除操作右下角通知（复用unsaved样式） */
            .cosmdl-delete-notice {
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 100000;
                padding: 10px 12px;
                border-radius: 10px;
                background: #f0f7ff;
                border: 1px solid #d8e8ff;
                color: #2271b1;
                box-shadow: 0 10px 30px rgba(2,6,23,.14);
                display: inline-flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: opacity 200ms ease, transform 200ms ease, visibility 0s linear 200ms;
                pointer-events: none;
            }
            .cosmdl-delete-notice.is-visible {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                transition: opacity 200ms ease, transform 200ms ease, visibility 0s;
                pointer-events: auto;
            }
            .cosmdl-delete-notice .msg {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
            }
            .cosmdl-delete-notice .dashicons {
                color: #2271b1;
            }
            .cosmdl-delete-notice .cosmdl-delete-ok {
                background: #2271b1;
                border-color: #1b5e9a;
                color: #fff;
                border-radius: 999px;
                padding: 6px 12px;
                line-height: 1.2;
                box-shadow: 0 8px 24px rgba(34, 113, 177, .25);
            }
            .cosmdl-delete-notice .cosmdl-delete-ok:disabled { opacity: .7; }
            @media (prefers-reduced-motion: reduce){
              .cosmdl-delete-notice{ transition: none; }
              .cosmdl-delete-notice.is-visible{ transition: none; }
            }
            
            /* 批量删除确认弹窗 */
            .cosmdl-delete-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100001;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transition: opacity 200ms ease, visibility 0s linear 200ms;
            }
            .cosmdl-delete-modal.is-visible {
                opacity: 1;
                visibility: visible;
                transition: opacity 200ms ease, visibility 0s;
            }
            .cosmdl-delete-modal-content {
                background: #fff;
                border-radius: 10px;
                padding: 24px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                transform: translateY(20px);
                transition: transform 200ms ease;
            }
            .cosmdl-delete-modal.is-visible .cosmdl-delete-modal-content {
                transform: translateY(0);
            }
            .cosmdl-delete-modal-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 16px;
            }
            .cosmdl-delete-modal-header .dashicons {
                color: #d63638;
                font-size: 24px;
            }
            .cosmdl-delete-modal-title {
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
                margin: 0;
            }
            .cosmdl-delete-modal-message {
                color: #646970;
                margin-bottom: 24px;
                line-height: 1.5;
            }
            .cosmdl-delete-modal-buttons {
                display: flex;
                gap: 12px;
                justify-content: flex-end;
            }
            .cosmdl-delete-modal-btn {
                padding: 8px 16px;
                border-radius: 6px;
                border: none;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 200ms ease;
            }
            .cosmdl-delete-modal-btn.cancel {
                background: #f6f7f7;
                color: #646970;
                border: 1px solid #c3c4c7;
            }
            .cosmdl-delete-modal-btn.cancel:hover {
                background: #e9e9e9;
            }
            .cosmdl-delete-modal-btn.confirm {
                background: #d63638;
                color: #fff;
                border: 1px solid #b32d2e;
            }
            .cosmdl-delete-modal-btn.confirm:hover {
                background: #b32d2e;
            }
            .cosmdl-delete-modal-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            </style>
            <div id="cosmdl-unsaved-notice" class="cosmdl-unsaved" aria-live="polite" role="status">
                <div class="msg"><span class="dashicons dashicons-warning"></span> <span>您的配置发生改变，记得保存喔~</span></div>
                <button type="button" id="cosmdl-unsaved-save" class="button cosmdl-unsaved-save">保存配置</button>
            </div>
            
            <!-- 单条删除右下角通知 -->
            <div id="cosmdl-delete-notice" class="cosmdl-delete-notice" aria-live="polite" role="status">
                <div class="msg"><span class="dashicons dashicons-trash"></span> <span>下载记录已删除</span></div>
                <button type="button" id="cosmdl-delete-ok" class="button cosmdl-delete-ok">知道了</button>
            </div>
            
            <!-- 批量删除确认弹窗 -->
            <div id="cosmdl-delete-modal" class="cosmdl-delete-modal" role="dialog" aria-labelledby="cosmdl-delete-modal-title" aria-hidden="true">
                <div class="cosmdl-delete-modal-content">
                    <div class="cosmdl-delete-modal-header">
                        <span class="dashicons dashicons-warning"></span>
                        <h3 id="cosmdl-delete-modal-title" class="cosmdl-delete-modal-title">确认删除</h3>
                    </div>
                    <div class="cosmdl-delete-modal-message" id="cosmdl-delete-modal-message">
                        确定要删除选中的下载记录吗？此操作不可撤销。
                    </div>
                    <div class="cosmdl-delete-modal-buttons">
                        <button type="button" id="cosmdl-delete-cancel" class="cosmdl-delete-modal-btn cancel">取消</button>
                        <button type="button" id="cosmdl-delete-confirm" class="cosmdl-delete-modal-btn confirm">确认删除</button>
                    </div>
                </div>
            </div>
            
            <!-- 恢复默认值确认弹窗 -->
            <div id="cosmdl-reset-modal" class="cosmdl-delete-modal" role="dialog" aria-labelledby="cosmdl-reset-modal-title" aria-hidden="true">
                <div class="cosmdl-delete-modal-content">
                    <div class="cosmdl-delete-modal-header">
                        <span class="dashicons dashicons-warning"></span>
                        <h3 id="cosmdl-reset-modal-title" class="cosmdl-delete-modal-title">确认恢复默认值</h3>
                    </div>
                    <div class="cosmdl-delete-modal-message" id="cosmdl-reset-modal-message">
                        确认恢复默认值？此操作将覆盖当前所有设置。
                    </div>
                    <div class="cosmdl-delete-modal-buttons">
                        <button type="button" id="cosmdl-reset-cancel" class="cosmdl-delete-modal-btn cancel">取消</button>
                        <button type="button" id="cosmdl-reset-confirm" class="cosmdl-delete-modal-btn confirm">确认恢复</button>
                    </div>
                </div>
            </div>

            <form id="cosmdl-settings-form" action="options.php" method="post">
                <?php settings_fields('cosmdl_settings'); ?>
                <input type="hidden" id="cosmdl-ajax-nonce" name="cosmdl_ajax_nonce" value="<?php echo esc_attr($ajax_nonce); ?>" />
                
                <!-- 全局设置 -->
                <div class="drawer-content" id="global-settings-content">
                    <h2><?php echo esc_html__('全局设置','cosmautdl'); ?></h2>
                    <p class="description"><?php echo esc_html__('配置插件的全局参数和行为设置','cosmautdl'); ?></p>
					<?php
					global $wp_version;
					$min_wp = '5.0';
					$min_php = '7.4';
					$current_wp = is_string($wp_version) ? $wp_version : get_bloginfo('version');
					$current_php = PHP_VERSION;
					$wp_ok = version_compare($current_wp, $min_wp, '>=');
					$php_ok = version_compare($current_php, $min_php, '>=');
					$permalink_structure = (string) get_option('permalink_structure');
					$permalink_ok = ($permalink_structure !== '');
					$uploads = wp_upload_dir(null, false);
					$uploads_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
					$uploads_ok = ($uploads_dir !== '' && wp_is_writable($uploads_dir));
					$logs_dir = ($uploads_dir !== '') ? trailingslashit($uploads_dir) . 'cosmautdl-logs' : '';
					$logs_ok = true;
					if ($logs_dir !== '') {
						$logs_ok = file_exists($logs_dir) ? wp_is_writable($logs_dir) : $uploads_ok;
					}
					$wp_content_ok = wp_is_writable(WP_CONTENT_DIR);
					$perm_ok = ($uploads_ok && $logs_ok && $wp_content_ok);

					$fa_ok = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path fill="currentColor" d="M256 512a256 256 0 1 1 0-512 256 256 0 1 1 0 512zM374 145.7c-10.7-7.8-25.7-5.4-33.5 5.3L221.1 315.2 169 263.1c-9.4-9.4-24.6-9.4-33.9 0s-9.4 24.6 0 33.9l72 72c5 5 11.8 7.5 18.8 7s13.4-4.1 17.5-9.8L379.3 179.2c7.8-10.7 5.4-25.7-5.3-33.5z"/></svg>';
					$fa_bad = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path fill="currentColor" d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zM167 167c9.4-9.4 24.6-9.4 33.9 0l55 55 55-55c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-55 55 55 55c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-55-55-55 55c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l55-55-55-55c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>';
					?>
					<div class="cosmdl-env-checks">
						<div class="cosmdl-env-title"><?php echo esc_html__('系统要求', 'cosmautdl'); ?></div>
						<div class="cosmdl-env-grid" role="list">
							<div class="cosmdl-env-item <?php echo $wp_ok ? 'is-ok' : 'is-bad'; ?>" role="listitem">
								<span class="cosmdl-env-icon" style="color:<?php echo $wp_ok ? '#0ea5e9' : '#ef4444'; ?>"><?php echo wp_kses($wp_ok ? $fa_ok : $fa_bad, self::allowed_icon_html()); ?></span>
								<div class="cosmdl-env-body">
									<div class="cosmdl-env-line"><strong><?php echo esc_html__('WordPress：', 'cosmautdl'); ?></strong><?php /* translators: %s: 最低 WordPress 版本号 */ echo esc_html(sprintf(__('需 %s 或更高版本', 'cosmautdl'), $min_wp)); ?></div>
									<div class="cosmdl-env-sub"><?php /* translators: %s: 当前 WordPress 版本号 */ echo esc_html(sprintf(__('当前版本：%s', 'cosmautdl'), $current_wp)); ?></div>
									<?php if (!$wp_ok): ?>
										<div class="cosmdl-env-hint"><?php echo esc_html__('当前 WordPress 版本过低，可能导致功能异常。请到“仪表盘 → 更新”升级 WordPress。', 'cosmautdl'); ?> <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="cosmdl-env-link"><?php echo esc_html__('去升级', 'cosmautdl'); ?></a></div>
									<?php endif; ?>
								</div>
							</div>

							<div class="cosmdl-env-item <?php echo $php_ok ? 'is-ok' : 'is-bad'; ?>" role="listitem">
								<span class="cosmdl-env-icon" style="color:<?php echo $php_ok ? '#0ea5e9' : '#ef4444'; ?>"><?php echo wp_kses($php_ok ? $fa_ok : $fa_bad, self::allowed_icon_html()); ?></span>
								<div class="cosmdl-env-body">
									<div class="cosmdl-env-line"><strong><?php echo esc_html__('PHP：', 'cosmautdl'); ?></strong><?php /* translators: %s: 最低 PHP 版本号 */ echo esc_html(sprintf(__('需 %s 或更高版本', 'cosmautdl'), $min_php)); ?></div>
									<div class="cosmdl-env-sub"><?php /* translators: %s: 当前 PHP 版本号 */ echo esc_html(sprintf(__('当前版本：%s', 'cosmautdl'), $current_php)); ?></div>
									<?php if (!$php_ok): ?>
										<div class="cosmdl-env-hint"><?php echo esc_html__('当前 PHP 版本过低。请联系主机/服务器提供商将 PHP 升级到 7.4 或更高版本。', 'cosmautdl'); ?></div>
									<?php endif; ?>
								</div>
							</div>

							<div class="cosmdl-env-item <?php echo $permalink_ok ? 'is-ok' : 'is-bad'; ?>" role="listitem">
								<span class="cosmdl-env-icon" style="color:<?php echo $permalink_ok ? '#0ea5e9' : '#ef4444'; ?>"><?php echo wp_kses($permalink_ok ? $fa_ok : $fa_bad, self::allowed_icon_html()); ?></span>
								<div class="cosmdl-env-body">
									<div class="cosmdl-env-line"><strong><?php echo esc_html__('固定链接：', 'cosmautdl'); ?></strong><?php echo esc_html__('建议开启，避免下载页路由异常', 'cosmautdl'); ?></div>
									<div class="cosmdl-env-sub"><?php echo $permalink_ok ? esc_html__('当前状态：已启用固定链接', 'cosmautdl') : esc_html__('当前状态：未启用固定链接', 'cosmautdl'); ?></div>
									<?php if (!$permalink_ok): ?>
										<div class="cosmdl-env-hint"><?php echo esc_html__('请到“设置 → 固定链接”选择“文章名”并保存一次。保存后，本插件的下载页链接更稳定且更美观。', 'cosmautdl'); ?> <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="cosmdl-env-link"><?php echo esc_html__('去设置', 'cosmautdl'); ?></a></div>
									<?php endif; ?>
								</div>
							</div>

							<div class="cosmdl-env-item <?php echo $perm_ok ? 'is-ok' : 'is-bad'; ?>" role="listitem">
								<span class="cosmdl-env-icon" style="color:<?php echo $perm_ok ? '#0ea5e9' : '#ef4444'; ?>"><?php echo wp_kses($perm_ok ? $fa_ok : $fa_bad, self::allowed_icon_html()); ?></span>
								<div class="cosmdl-env-body">
									<div class="cosmdl-env-line"><strong><?php echo esc_html__('权限：', 'cosmautdl'); ?></strong><?php echo esc_html__('需要具备安装插件与写入缓存/日志的服务器权限', 'cosmautdl'); ?></div>
									<div class="cosmdl-env-sub"><?php echo $perm_ok ? esc_html__('当前状态：目录可写', 'cosmautdl') : esc_html__('当前状态：目录不可写或权限不足', 'cosmautdl'); ?></div>
									<?php if (!$perm_ok): ?>
										<div class="cosmdl-env-hint"><?php echo esc_html__('请确保 wp-content/ 与 wp-content/uploads/ 目录具备写入权限（用于更新、缓存与日志）。必要时请联系主机提供商调整文件权限或属主。', 'cosmautdl'); ?> <a href="<?php echo esc_url(admin_url('site-health.php')); ?>" class="cosmdl-env-link"><?php echo esc_html__('查看站点健康', 'cosmautdl'); ?></a></div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
					<style>
					.cosmdl-env-checks{margin:14px 0 18px 0;}
					.cosmdl-env-title{font-size:14px;font-weight:700;color:#0f172a;margin:0 0 10px 0;}
					.cosmdl-env-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-width:980px;}
					@media (max-width: 782px){.cosmdl-env-grid{grid-template-columns:1fr;}}
					.cosmdl-env-item{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:12px;border:1px solid rgba(2,6,23,.10);background:#fff;box-shadow:0 10px 28px rgba(2,6,23,.06);}
					.cosmdl-env-item.is-ok{border-color:rgba(14,165,233,.18);background:linear-gradient(135deg, rgba(14,165,233,.10), rgba(255,255,255,1));}
					.cosmdl-env-item.is-bad{border-color:rgba(239,68,68,.22);background:linear-gradient(135deg, rgba(239,68,68,.10), rgba(255,255,255,1));}
					.cosmdl-env-icon{width:20px;height:20px;flex:0 0 20px;display:inline-flex;align-items:center;justify-content:center;margin-top:1px;}
					.cosmdl-env-icon svg{width:20px;height:20px;display:block;}
					.cosmdl-env-line{font-size:13px;line-height:1.4;color:#0f172a;}
					.cosmdl-env-sub{margin-top:4px;font-size:12px;line-height:1.4;color:#475569;}
					.cosmdl-env-hint{margin-top:8px;font-size:12px;line-height:1.5;color:#7f1d1d;}
					.cosmdl-env-item.is-ok .cosmdl-env-hint{color:#14532d;}
					.cosmdl-env-link{margin-left:6px;text-decoration:none;font-weight:700;}
					.cosmdl-env-item.is-bad .cosmdl-env-link{color:#b91c1c;}
					.cosmdl-env-item.is-ok .cosmdl-env-link{color:#0f766e;}
					</style>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('插件开关','cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_checkbox(array('key' => 'plugin_active', 'label' => esc_html__('启用插件功能','cosmautdl'))); ?>
                                <p class="description"><?php echo esc_html__('关闭后插件所有功能禁用，仅保留顶级菜单','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">路由前缀</th>
                            <td>
                                <?php $this->field_text(array('key'=>'route_prefix')); ?>
                                <div class="route-prefix-wrapper" style="margin-top:10px;padding:10px;border:1px solid #e5e7eb;border-radius:6px;background:#f8fafc;">
                                    <?php if($is_pretty): ?>
                                        <p style="margin:0;color:#166534;">当前已启用固定链接，示例：<code><?php echo esc_html( home_url( (isset($options['route_prefix'])?$options['route_prefix']:'get') . '/123/baidu.html') ); ?></code></p>
                                    <?php else: ?>
                                        <p style="margin:0;color:#b91c1c;">当前未启用固定链接，使用降级模式：<code><?php echo esc_html( home_url('/?cosmdl_redirect=1&post_id=123&type=baidu') ); ?></code>。建议到“设置 → 固定链接”启用“文章名”结构，以获得更美观的地址。</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('错误处理方式','cosmautdl'); ?></th>
                            <td>
                                <select name="cosmdl_options[error_handling]">
                                    <option value="message" <?php selected(isset($options['error_handling']) ? $options['error_handling'] : '', 'message'); ?>><?php echo esc_html__('显示错误信息','cosmautdl'); ?></option>
                                    <option value="hide" <?php selected(isset($options['error_handling']) ? $options['error_handling'] : '', 'hide'); ?>><?php echo esc_html__('隐藏错误信息','cosmautdl'); ?></option>
                                    <option value="redirect" <?php selected(isset($options['error_handling']) ? $options['error_handling'] : '', 'redirect'); ?>><?php echo esc_html__('重定向到首页','cosmautdl'); ?></option>
                                </select>
                                <p class="description"><?php echo esc_html__('当下载链接无效或无法访问时的处理方式','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('日志记录','cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_checkbox(array('key' => 'enable_logging', 'label' => esc_html__('启用操作日志记录','cosmautdl'))); ?>
                                <p class="description"><?php echo esc_html__('日志存储在wp-content/uploads/cosmautdl-logs/','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('调试模式','cosmautdl'); ?></th>
                            <td><?php $this->field_checkbox(array('key' => 'debug_mode', 'label' => esc_html__('启用调试信息输出','cosmautdl'))); ?>
                                <p class="description"><?php echo esc_html__('仅在开发和测试环境中启用，生产环境请关闭。','cosmautdl'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 网盘管理 -->
                <div class="drawer-content" id="drive-management-content" style="display:none;">
                    <h2><?php echo esc_html__('网盘管理','cosmautdl'); ?></h2>
                    <p class="description"><?php echo esc_html__('管理文章编辑页和下载页面显示的网盘类型、顺序及名称','cosmautdl'); ?></p>
                    
                    <div id="drives-container">
                        <table class="form-table">
                            <thead>
                                <tr>
                                    <!-- 调整列宽：第一列为拖拽句柄，最后一列为删除按钮，居中列尽量占满 -->
                                    <th style="width:40px"></th>
                                    <th style="width:0"></th>
                                    <th style="width:auto"></th>
                                    <th style="width:0"></th>
                                </tr>
                            </thead>
                            <tbody id="drives-sortable">
                                <?php $this->render_drive_management_fields(); ?>
                            </tbody>
                        </table>
                        <!-- 按钮移动到容器内部，跟随容器宽度 -->
                        <div id="drives-toggle-wrap">
                            <button type="button" id="drives-toggle" aria-label="展开/收起未开启网盘">
                                <span class="text">展开更多网盘</span>
                                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                <span class="screen-reader-text">展开更多网盘</span>
                            </button>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">自定义网盘</th>
                            <td>
                                <input type="text" id="new-drive-name" style="min-width:150px" placeholder="请输入网盘名称" />
                                <input type="text" id="new-drive-alias" style="min-width:150px; margin-left:8px" placeholder="请输入网盘别名" />
                                <button type="button" id="add-drive-btn" class="button button-primary" style="margin-left:8px">添加网盘</button>
                                <p class="description" style="margin-top:6px">别名用于跳转路径，如 baidu、lanzou。</p>
                            </td>
                        </tr>
                    </table>
                    
                    <style>
                    /* 路由前缀部分外包裹宽度自适应 */
                    .route-prefix-wrapper {
                        display: block;
                        max-width: 100%;
                        box-sizing: border-box;
                    }
                    
                    /* 缩短"添加自定义网盘"表单的标签与输入框间距 */
                    /* 仅作用于 #drive-management-content 直接子级的 form-table（不会影响上方 #drives-container 表格） */
                    #drive-management-content > table.form-table th[scope="row"] {
                        width: 96px !important;      /* 默认 WP 管理后台为较宽的 th，这里缩窄以减少左侧空白 */
                        padding-right: 8px;          /* 适度保留右侧间距，避免贴得过近 */
                        text-align: left;            /* 与整体风格一致，左对齐标签 */
                    }
                    #drive-management-content > table.form-table td {
                        padding-left: 0 !important;  /* 去掉额外左内边距，让输入更靠近标签 */
                    }
                    #drives-sortable {
                        list-style-type: none;
                        margin: 0;
                        padding: 0;
                    }
                    /* 旧版可拖拽列表样式（用于 #drives-sortable 列表场景） */
                    #drives-sortable .drive-item {
                        padding: 4px;               /* 列表项内边距 */
                        margin-bottom: 4px;         /* 列表项下边距，便于分隔 */
                        background: #f9f9f9;
                        border: 1px solid #e0e0e0;
                        border-radius: 4px;
                        display: flex;              /* 仅用于旧版 UL/LI 列表模式 */
                        align-items: center;
                        cursor: move;               /* 旧版列表项整行可拖拽 */
                    }
                    /* 表格模式的行样式（tr.drive-item）——恢复表格语义，避免整行按 Flex 收缩导致右侧出现大量空白 */
                    tr.drive-item {
                        display: table-row !important;  /* 强制为表格行，覆盖 .drive-item 的 display:flex */
                        margin: 0 !important;            /* 移除下边距，避免出现“上窄下宽”的视觉不一致 */
                        padding: 0 !important;           /* 由 td 自身的 padding 控制行的垂直间距 */
                        cursor: default !important;      /* 仅在“三道杠”上显示拖拽指示，整行不再显示可拖拽光标 */
                        /* 为行位移动画做准备 */
                        transition: transform 150ms ease; 
                        will-change: transform;
                    }
                    .drive-item:hover {
                        background: #f0f0f0;
                    }
                    .drive-item.dragging {
                        /* 拖拽中轻量反馈：不改变行尺寸，减少透明度干扰 */
                        opacity: 0.95;
                        outline: 2px solid rgba(0, 115, 170, 0.25);
                        background: #f7faff; /* 轻微高亮 */
                        /* 不对 tr 使用 transform/box-shadow，避免表格重排与行高异常 */
                    }
                    /* 跟随鼠标的“拖拽影像”：独立的卡片样式（真实内容承载） */
                    .drag-ghost {
                        position: fixed;
                        z-index: 9999;
                        pointer-events: none;
                        opacity: 1;                /* 背景不透明，提升可读性 */
                        background: #fff;
                        box-sizing: border-box;    /* 便于用 width 覆盖包含 padding/border 的总宽 */
                        padding: 6px 8px;          /* 卡片内边距，视觉更友好 */
                        border: 1px solid #e5e7eb; /* 细边框 */
                        border-radius: 6px;        /* 与行卡片圆角保持一致 */
                        transform: translateZ(0);
                        box-shadow: 0 10px 24px rgba(0,0,0,0.08);
                        /* 使用块级容器承载克隆的第三列内容，使其能够充满卡片宽度 */
                        display: block;
                        /* 保持与真实卡片一致的换行策略，避免未来内容增减导致视觉不一致 */
                        white-space: normal;
                        /* 保证内部内容可溢出时仍然可见（避免被裁切） */
                        overflow: visible;
                    }
                    /* 拖拽过程中，统一将鼠标光标改为“上下移动”指示 */
                    body.cosmdl-dragging-cursor, .cosmdl-dragging-cursor {
                        cursor: ns-resize !important;
                        user-select: none !important; /* 拖拽期间避免误选中文本/输入框 */
                    }
                    /* 影像中的输入框与右侧控制区保持与表格一致的收缩与对齐 */
                    .drag-ghost input[type="text"][name*="[label]"],
                    .drag-ghost input[type="text"][name*="[alias]"] {
                        width: 130px !important;
                        max-width: 130px !important;
                        flex: 0 0 130px !important;
                    }
                    .drag-ghost .right-controls {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        margin-left: auto;
                        padding-left: 12px;
                        padding-right: 6px;
                    }
                    /* 第三列内容占位符：在拖拽期间保持原行的高度与布局不变 */
                    .drive-content-placeholder {
                        display: block;
                        width: 100%;
                        box-sizing: border-box;
                        visibility: hidden; /* 隐藏占位但保留尺寸 */
                    }
                    /* 占位行（在原位置撑开空间，其他行围绕其动画移动） */
                    .drive-placeholder td {
                        border-top: 1px dashed #e5e7eb !important;
                        border-bottom: 1px dashed #e5e7eb !important;
                        background: #f9fafb !important;
                    }
                    .drive-handle {
                        cursor: grab;
                        padding: 5px;
                        margin-right: 10px;
                        color: #666;
                    }
                    .drive-handle:active {
                        cursor: grabbing;
                    }
                    .remove-drive {
                        color: #d63638;
                        cursor: pointer;
                        font-size: 18px;
                        transition: transform 0.2s ease, color 0.2s ease;
                        vertical-align: middle;
                    }
                    .remove-drive:hover {
                        color: #a32a2a;
                        transform: scale(1.2);
                    }
                    #drives-container {
                        display: inline-block;  /* 关键：容器宽度由内容决定（自适应表格宽度） */
                        min-width: 600px;       /* 最小宽度保护 */
                        max-width: 100%;        /* 不超过父级宽度 */
                        vertical-align: top;    /* 顶部对齐 */
                    }
                    /* 调整表头文字与内容对齐 */
                    #drives-container table {
                        width: 100%;            /* 表格占满容器宽度（容器是 inline-block，所以就是表格实际宽度） */
                        table-layout: auto;
                        /* 为表格行之间增加垂直间隙（不影响左右对齐） */
                        border-collapse: separate;
                        border-spacing: 0 4px; /* 水平方向 0，垂直方向 4px 轻微留白 */
                    }
                    #drives-container th {
                        padding: 6px 0;
                    }
                    #drives-container th:first-child,
                    #drives-container th:last-child {
                        text-align: left;
                    }
                    /* 隐藏原第二列表头（显示开关），将开关移动到“别名”右侧 */
                    #drives-container th:nth-child(2) {
                        display: none;
                    }
                    /* 第三个表头（名称/别名组合）左对齐，便于水平排列阅读 */
                    #drives-container th:nth-child(3) {
                        text-align: left;
                    }
                    #drives-container td {
                        padding: 6px 0; /* 垂直方向稍微增大间距 */
                        /* 覆盖 WP 核心 .form-table td 的 9px 下外边距，避免行间额外空隙 */
                        margin-bottom: 0 !important;
                        /* 为每一行形成一体化的“淡边框”视觉：同一行所有单元格顶部/底部有边框，左右边框仅作用于首尾单元格 */
                        border-top: 1px solid #e5e7eb;
                        border-bottom: 1px solid #e5e7eb;
                        background: #fff; /* 边框配合白底，更清晰的分隔效果 */
                    }
                    #drives-container.collapsed tr.drive-item[data-enabled="no"] { display: none !important; }
                    #drives-container.collapsed tr.drive-item[data-enabled="no"][data-force-visible="1"] { display: table-row !important; }
                    
                    /* 优化后的展开按钮容器 */
                    #drives-toggle-wrap { 
                        display:flex; 
                        justify-content:center; 
                        width: 100%; 
                        clear: both;
                        margin-top: 12px;
                        margin-bottom: 12px;
                    }
                    /* 优化后的展开按钮外观 */
                    #drives-toggle { 
                        display: inline-flex; 
                        align-items: center; 
                        justify-content:center; 
                        padding: 0 20px;
                        height: 36px;
                        border-radius: 18px;
                        background: #fff; 
                        border: 1px solid #d1d5db; 
                        color: #64748b; 
                        font-size: 13px;
                        font-weight: 500;
                        gap: 6px;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                        will-change: transform;
                        transition: all 0.2s ease;
                        outline: none;
                        cursor: pointer;
                    }
                    #drives-toggle:hover { 
                        background:#f8fafc; 
                        border-color:#2271b1; 
                        color:#2271b1; 
                        box-shadow: 0 4px 12px rgba(34, 113, 177, 0.1);
                        transform: translateY(-1px);
                    }
                    #drives-toggle:active { 
                        background:#f1f5f9; 
                        transform: translateY(0);
                    }
                    #drives-toggle .dashicons { 
                        font-size: 18px; 
                        width: 18px; 
                        height: 18px; 
                        line-height: 18px; 
                    }
                    #drives-toggle .screen-reader-text { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }
                    /* 额外加一条更高特异性的覆盖，确保在 .form-table 环境下也生效 */
                    #drives-container .form-table td {
                        /* 保持为表格单元格，避免被其他样式改成 block 导致 margin 生效 */
                        display: table-cell !important;
                        margin-bottom: 0 !important;
                        padding-top: 6px !important; /* 垂直方向稍微增大间距 */
                        padding-bottom: 6px !important; /* 垂直方向稍微增大间距 */
                    }
                    /* 隐藏原第二列单元格（显示开关）—需使用 !important，避免被“统一为 table-cell”规则覆盖 */
                    #drives-container .drive-item td:nth-child(2) {
                        display: none !important;
                        width: 0 !important;
                        padding: 0 !important;
                    }
                    #drives-container .drive-item td:nth-child(3) {
                        text-align: left;
                        vertical-align: middle;
                    }
                    #drives-container .drive-item td:first-child {
                        text-align: left;
                        vertical-align: middle;
                        padding-left: 6px !important; /* 容器左缘 → 三道杠：6px */
                        border-left: 1px solid #e5e7eb;
                        border-top-left-radius: 6px;
                        border-bottom-left-radius: 6px;
                    }
                    /* 删除按钮改为放在第三列内容末尾，使其紧随滑动开关 */
                    #drives-container .drive-item .remove-drive {
                        margin-left: 8px;
                        color: #d63638;
                        cursor: pointer;
                        font-size: 18px;
                        transition: transform 0.2s ease, color 0.2s ease;
                        vertical-align: middle;
                    }
                    #drives-container .drive-item .remove-drive:hover {
                        color: #a32a2a;
                        transform: scale(1.2);
                    }
                    /* 右侧控制容器：靠右对齐，设置与别名输入框的间距，以及与右边缘的对称留白 */
                    #drives-container .drive-item .right-controls {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        margin-left: auto; /* 推到右侧 */
                        padding-left: 12px; /* 与“别名”到“名称输入框”的间距一致（约 12px） */
                        padding-right: 6px !important; /* 容器右缘 → 删除按钮：6px（与左侧对称） */
                    }

                    /* 行末尾单元格的右边框与圆角，保证整行看起来像一个“卡片” */
                    #drives-container .drive-item td:last-child {
                        border-right: 1px solid #e5e7eb;
                        border-top-right-radius: 6px;
                        border-bottom-right-radius: 6px;
                    }

                    /* 固定“名称”和“别名”输入框的宽度（缩短一半并保持一致） */
                    #drives-container .drive-item td:nth-child(3) input[type="text"][name*="[label]"] {
                        width: 130px !important;
                        max-width: 130px !important;
                        flex: 0 0 130px !important;
                    }
                    #drives-container .drive-item td:nth-child(3) input[type="text"][name*="[alias]"] {
                        width: 130px !important;
                        max-width: 130px !important;
                        flex: 0 0 130px !important;
                    }
                    /* 容器 label 不再强制 flex:1，按内容自然宽度排列；不影响右侧 .cosmdl-switch */
                    #drives-container .drive-item td:nth-child(3) label:not(.cosmdl-switch) {
                        flex: 0 0 auto !important;
                        min-width: unset !important;
                    }
                    /* 别名所在的包裹 span 也取消 flex:1，避免整体被拉伸 */
                    #drives-container .drive-item td:nth-child(3) span:not(.cosmdl-switch) {
                        flex: 0 0 auto !important;
                    }
                    /* 窄屏兼容：在小屏时允许输入框撑满一行，避免拥挤 */
                    @media (max-width: 782px) {
                        #drives-container .drive-item td:nth-child(3) label:not(.cosmdl-switch) {
                            flex: 1 1 100% !important;
                        }
                        #drives-container .drive-item td:nth-child(3) input[type="text"][name*="[label]"],
                        #drives-container .drive-item td:nth-child(3) input[type="text"][name*="[alias]"] {
                            width: 100% !important;
                            max-width: 100% !important;
                            flex: 1 1 100% !important;
                        }
                    }
                    </style>
                </div>



                <!-- 下载页设置（模块化/可拖拽/互斥折叠） -->
                <div class="drawer-content" id="download-page-content" style="display:none;">
                    <h2><?php echo esc_html__('下载页设置','cosmautdl'); ?></h2>
                    <p class="description">按照独立下载页的呈现顺序，将以下模块做成可拖拽排序的折叠面板。每次仅展开一个模块。</p>

                    <style>
                        /* 模块容器与卡片样式 */
                        #download-modules-sortable { 
                            list-style: none; 
                            margin: 0; 
                            padding: 0; 
                            display: block; /* 固定容器布局，避免因折叠导致宽度收缩 */
                            width: 100%; /* 占据可用宽度 */
                            max-width: min(773px, calc(100% - 40px)); /* 以“文件信息”模块预览的最大宽度为基准，且不超过视口 */
                            min-width: 300px; /* 设置最小宽度保证基本布局 */
                            box-sizing: border-box;
                        }
                        .cosmdl-module { 
                            border: 1px solid #e5e7eb; 
                            border-radius: 8px; 
                            background: #fff; 
                            margin-bottom: 8px; 
                            width: 100%; /* 所有模块宽度一致，都使用容器的100%宽度 */
                            box-sizing: border-box; /* 包含边框和内边距在宽度内 */
                            word-wrap: break-word; /* 长单词或URL自动换行 */
                            overflow-wrap: break-word; /* 现代浏览器自动换行 */
                        }
                        .cosmdl-module.dragging { outline: 2px solid rgba(34,113,177,.18); background: #f7fafc; }
                        .cosmdl-module__header { display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer; }
                        .cosmdl-module__title { font-weight: 600; color: #111827; flex: 1 1 auto; }
                        .cosmdl-module__toggle-icon { 
                            display: inline-flex; 
                            align-items: center; 
                            justify-content: center;
                            width: 16px; 
                            height: 16px;
                            transition: transform 0.2s ease;
                        }
                        .cosmdl-module__toggle-icon svg {
                            width: 16px; 
                            height: 16px; 
                            fill: #6b7280;
                        }
                        .cosmdl-module.is-open .cosmdl-module__toggle-icon {
                            transform: rotate(180deg);
                        }
                        .cosmdl-module__tools { display: inline-flex; align-items: center; gap: 10px; }
                        .cosmdl-module__handle { cursor: grab; color: #64748b; }
                        .cosmdl-module__handle:active { cursor: grabbing; }
                        .cosmdl-module__body { 
                            display: none; 
                            padding: 20px; 
                            border-top: 1px solid #e5e7eb; 
                            box-sizing: border-box; /* 确保padding包含在宽度内 */
                            width: 100%; /* 明确设置宽度，防止撑开 */
                        }
                        .cosmdl-module.is-open .cosmdl-module__body { display: block; }
                        .cosmdl-module__kv { display:flex; align-items:center; gap:8px; padding: 0; }
                        .cosmdl-module__kv label { min-width: 96px; text-align: left; padding: 20px 10px 20px 0; }
                        .cosmdl-module__kv .cosmdl-switch { margin-left: 0; }
                        .cosmdl-color-pair input[type="text"] { width: 100px; margin-left: 10px; box-sizing: border-box; }
                        
                        /* 文本域样式优化，防止撑开模块 */
                        .cosmdl-module__kv textarea {
                            width: 100%;
                            max-width: 300px;
                            min-height: 60px;
                            resize: vertical;
                            box-sizing: border-box;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                        }
                        
                        /* 颜色选择器组样式优化 */
                        .cosmdl-color-pair {
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            flex-wrap: wrap;
                        }
                        .cosmdl-color-pair input[type="color"] {
                            width: 60px;
                            height: 30px;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            background: linear-gradient(#f9fafb, #e5e7eb);
                            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset,
                                0 1px 2px rgba(15, 23, 42, 0.15);
                            cursor: pointer;
                        }
                        
                        /* 确保输入框和文本域不会撑开模块宽度 */
                        .cosmdl-module__kv input[type="text"],
                        .cosmdl-module__kv textarea,
                        .cosmdl-module__kv select {
                            flex: 1;
                            max-width: 360px;
                            min-width: 0;
                            box-sizing: border-box;
                        }

                        .cosmdl-module__kv .cosmdl-color-input {
                            flex: none; /* 中文注释：颜色选择按钮不参与伸缩，保持固定尺寸 */
                            padding: 0; /* 中文注释：移除内边距，避免实际可视区域变小 */
                        }

                        .cosmdl-module__kv .cosmdl-color-hex-input {
                            flex: none; /* 中文注释：颜色值输入框固定宽度，防止被压缩 */
                            width: 120px;
                            max-width: 120px;
                            margin-left: 10px;
                            box-sizing: border-box;
                        }

                        .cosmdl-module__kv .cosmdl-textarea {
                            max-width: 480px;
                            min-height: 96px;
                        }

                        .cosmdl-module__kv .cosmdl-card-theme-select {
                            flex: none;
                            width: 160px;
                            max-width: 160px;
                            min-width: 120px;
                        }

                        /* 实时预览容器：与前端保持同宽 */
                        .cosmdl-fileinfo-preview,
                        .cosmdl-statement-preview,
                        .cosmdl-custom-links-preview,
                        .cosmdl-pan-cards-preview,
                        .cosmdl-download-tips-preview,
                        .cosmdl-owner-statement-preview { display: block; width: 100%; max-width: 773px; margin: 0; }
                        .cosmdl-fileinfo-preview .cosmdl-card p { font-size: inherit; }
                        .cosmdl-fileinfo-preview .cosmdl-card,
                        .cosmdl-statement-preview .cosmdl-section,
                        .cosmdl-custom-links-preview .cosmdl-section,
                        .cosmdl-pan-cards-preview .cosmdl-section,
                        .cosmdl-download-tips-preview .cosmdl-section,
                        .cosmdl-owner-statement-preview .cosmdl-section { width: 100%; }
                    </style>

                    <ul id="download-modules-sortable" aria-label="下载页模块排序">
                        <!-- 模块：下载声明 -->
                        <li class="cosmdl-module" data-module="statement">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">下载声明</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_statement')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">
                                <!-- 下载声明模块实时预览 -->
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-statement-preview cosmdl-preview-card" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-section" id="cosmdl-statement-preview" style="
                                            margin: 0;
                                        ">
                                            <h3 class="cosmdl-section-title">下载声明</h3>
                                            <p>本文件仅供学习交流使用，请在下载后24小时内删除，不得用于商业用途。如有侵权请<a href="mailto:cosmaut@hotmail.com" class="custom-link" style="pointer-events: none;">联系撤下</a>。</p>
                                        </div>
                                    </div>
                                    <script>
                                      function cosmdlUpdateStatementPreview(e){
                                        var container = document.getElementById('cosmdl-statement-preview');
                                        if(!container) return;

                                        container.removeAttribute('style');

                                        var customCss = document.querySelector('textarea[name="cosmdl_options[statement_custom_css]"]');
                                        if(customCss) {
                                          var oldStyle = document.getElementById('cosmdl-statement-custom-css');
                                          if(oldStyle) oldStyle.remove();

                                          if(customCss.value.trim()) {
                                            var styleElement = document.createElement('style');
                                            styleElement.id = 'cosmdl-statement-custom-css';
                                            styleElement.textContent = customCss.value;
                                            document.head.appendChild(styleElement);
                                          }
                                        }

                                        var borderColorInput = document.querySelector('input[type="color"][name="cosmdl_options[statement_border_color]"]');
                                        var borderColorHexInput = document.querySelector('input[type="text"][name="cosmdl_options[statement_border_color_hex]"]');
                                        var bgColorInput = document.querySelector('input[type="color"][name="cosmdl_options[statement_bg_color]"]');
                                        var bgColorHexInput = document.querySelector('input[type="text"][name="cosmdl_options[statement_bg_color_hex]"]');
                                        var titleColorInput = document.querySelector('input[type="color"][name="cosmdl_options[statement_title_color]"]');
                                        var titleColorHexInput = document.querySelector('input[type="text"][name="cosmdl_options[statement_title_color_hex]"]');
                                        var textColorInput = document.querySelector('input[type="color"][name="cosmdl_options[statement_text_color]"]');
                                        var textColorHexInput = document.querySelector('input[type="text"][name="cosmdl_options[statement_text_color_hex]"]');

                                        var currentBorderColor = borderColorHexInput && borderColorHexInput.value ? borderColorHexInput.value : (borderColorInput ? borderColorInput.value : '');
                                        var currentBgColor = bgColorHexInput && bgColorHexInput.value ? bgColorHexInput.value : (bgColorInput ? bgColorInput.value : '');
                                        var currentTitleColor = titleColorHexInput && titleColorHexInput.value ? titleColorHexInput.value : (titleColorInput ? titleColorInput.value : '');
                                        var currentTextColor = textColorHexInput && textColorHexInput.value ? textColorHexInput.value : (textColorInput ? textColorInput.value : '');

                                        var themeSelect = document.getElementById('statement-card-theme');
                                        var theme = themeSelect ? themeSelect.value : 'blue';

                                        var isThemeChange = e && e.target && e.target.name === 'cosmdl_options[statement_card_theme]';

                                        var themeColors = {
                                          blue: {
                                            border: '#acd0f9',
                                            bg: '#e8f2fd',
                                            title: '#4285f4',
                                            text: '#4285f4'
                                          },
                                          green: {
                                            border: '#a8dbc1',
                                            bg: '#e7f5ee',
                                            title: '#34a853',
                                            text: '#34a853'
                                          },
                                          purple: {
                                            border: '#e1bbfc',
                                            bg: '#f7edfe',
                                            title: '#a256e3',
                                            text: '#a256e3'
                                          },
                                          orange: {
                                            border: '#f9d69f',
                                            bg: '#fdf3e4',
                                            title: '#fbbc05',
                                            text: '#fbbc05'
                                          },
                                          red: {
                                            border: '#ffb7b2',
                                            bg: '#fff5f4',
                                            title: '#ea4335',
                                            text: '#ea4335'
                                          },
                                          gray: {
                                            border: '#d1d5db',
                                            bg: '#f5f6f8',
                                            title: '#64748b',
                                            text: '#64748b'
                                          }
                                        };

                                        var defaultColors = {
                                          border: '#e5e7eb',
                                          bg: '#ffffff',
                                          title: '#111827',
                                          text: '#6b7280'
                                        };

                                        if (isThemeChange && !theme) {
                                          if (borderColorInput) borderColorInput.value = defaultColors.border;
                                          if (borderColorHexInput) borderColorHexInput.value = defaultColors.border;
                                          if (bgColorInput) bgColorInput.value = defaultColors.bg;
                                          if (bgColorHexInput) bgColorHexInput.value = defaultColors.bg;
                                          if (titleColorInput) titleColorInput.value = defaultColors.title;
                                          if (titleColorHexInput) titleColorHexInput.value = defaultColors.title;
                                          if (textColorInput) textColorInput.value = defaultColors.text;
                                          if (textColorHexInput) textColorHexInput.value = defaultColors.text;

                                          if (customCss) customCss.value = '';
                                          var oldStyleReset = document.getElementById('cosmdl-statement-custom-css');
                                          if (oldStyleReset) oldStyleReset.remove();

                                          container.style.padding = '16px';
                                          container.style.borderRadius = '8px';
                                          container.style.border = '1px solid ' + defaultColors.border;
                                          container.style.backgroundColor = defaultColors.bg;

                                          var titleEl = container.querySelector('.cosmdl-section-title');
                                          if (titleEl) {
                                            titleEl.style.color = defaultColors.title;
                                          }
                                          var textEl = container.querySelector('p');
                                          if (textEl) {
                                            textEl.style.color = defaultColors.text;
                                          }

                                          currentBorderColor = defaultColors.border;
                                          currentBgColor = defaultColors.bg;
                                          currentTitleColor = defaultColors.title;
                                          currentTextColor = defaultColors.text;

                                          return;
                                        }

                                        if(theme && themeColors[theme]) {
                                          var t = themeColors[theme];
                                          if(isThemeChange) {
                                            currentBorderColor = t.border;
                                            if(borderColorInput) borderColorInput.value = t.border;
                                            if(borderColorHexInput) borderColorHexInput.value = t.border;

                                            currentBgColor = t.bg;
                                            if(bgColorInput) bgColorInput.value = t.bg;
                                            if(bgColorHexInput) bgColorHexInput.value = t.bg;

                                            currentTitleColor = t.title;
                                            if(titleColorInput) titleColorInput.value = t.title;
                                            if(titleColorHexInput) titleColorHexInput.value = t.title;

                                            currentTextColor = t.text;
                                            if(textColorInput) textColorInput.value = t.text;
                                            if(textColorHexInput) textColorHexInput.value = t.text;
                                          }
                                        }

                                        var statementTitle = document.querySelector('input[name="cosmdl_options[statement_title]"]');
                                        var statementText = document.querySelector('textarea[name="cosmdl_options[statement_text]"]');

                                        var rawTitle = statementTitle ? statementTitle.value : '';
                                        var rawText = statementText ? statementText.value : '';
                                        var trimmedTitle = rawTitle.trim();
                                        var trimmedText = rawText.trim();

                                        if (trimmedTitle === '' && trimmedText === '') {
                                          container.style.display = 'none';
                                          return;
                                        } else {
                                          container.style.display = '';
                                        }

                                        container.style.padding = '16px';
                                        container.style.borderRadius = '8px';

                                        if(currentBorderColor) {
                                          container.style.border = '1px solid ' + currentBorderColor;
                                        } else {
                                          container.style.border = '1px solid #e2e8f0';
                                        }
                                        if(currentBgColor) container.style.backgroundColor = currentBgColor;

                                        var title = container.querySelector('.cosmdl-section-title');
                                        if (title) {
                                          if (trimmedTitle === '') {
                                            title.style.display = 'none';
                                          } else {
                                            title.style.display = '';
                                            title.textContent = rawTitle;
                                            title.style.color = currentTitleColor || defaultColors.title;
                                          }
                                        }

                                        var text = container.querySelector('p');
                                        if (text) {
                                          if (trimmedText === '') {
                                            text.style.display = 'none';
                                            text.innerHTML = '';
                                          } else {
                                            text.style.display = '';
                                            text.innerHTML = rawText;
                                            text.style.color = currentTextColor || defaultColors.text;
                                          }

                                          if (trimmedTitle === '' && text.style.display !== 'none') {
                                            container.style.display = 'flex';
                                            container.style.flexDirection = 'row';
                                            container.style.alignItems = 'center';
                                            container.style.justifyContent = 'flex-start';
                                            text.style.marginTop = '0';
                                            text.style.marginBottom = '0';
                                          } else {
                                            container.style.display = '';
                                            container.style.flexDirection = '';
                                            container.style.alignItems = '';
                                            container.style.justifyContent = '';
                                            text.style.marginTop = '';
                                            text.style.marginBottom = '';
                                          }
                                        }
                                      }
                                      
                                      // 确保DOM加载完成后执行
                                      if (document.readyState === 'loading') {
                                        document.addEventListener('DOMContentLoaded', function() {
                                          // 监听所有与statement相关的输入变化
                                          var statementInputs = document.querySelectorAll(
                                            'input[name="cosmdl_options[statement_border_color]"]'
                                          + ', input[name="cosmdl_options[statement_border_color_hex]"]'
                                          + ', input[name="cosmdl_options[statement_bg_color]"]'
                                          + ', input[name="cosmdl_options[statement_bg_color_hex]"]'
                                          + ', input[name="cosmdl_options[statement_title_color]"]'
                                          + ', input[name="cosmdl_options[statement_title_color_hex]"]'
                                          + ', input[name="cosmdl_options[statement_text_color]"]'
                                          + ', input[name="cosmdl_options[statement_text_color_hex]"]'
                                          + ', textarea[name="cosmdl_options[statement_custom_css]"]'
                                          + ', input[name="cosmdl_options[statement_title]"]'
                                          + ', textarea[name="cosmdl_options[statement_text]"]'
                                          + ', select[name="cosmdl_options[statement_card_theme]"]'
                                          );
                                          statementInputs.forEach(function(input) {
                                            input.addEventListener('input', cosmdlUpdateStatementPreview);
                                            input.addEventListener('change', cosmdlUpdateStatementPreview);
                                          });
                                          
                                          // 初始化预览
                                          cosmdlUpdateStatementPreview();
                                        });
                                      } else {
                                        // DOM已经加载完成
                                        // 监听所有与statement相关的输入变化
                                        var statementInputs = document.querySelectorAll(
                                          'input[name="cosmdl_options[statement_border_color]"]'
                                        + ', input[name="cosmdl_options[statement_border_color_hex]"]'
                                        + ', input[name="cosmdl_options[statement_bg_color]"]'
                                        + ', input[name="cosmdl_options[statement_bg_color_hex]"]'
                                        + ', input[name="cosmdl_options[statement_title_color]"]'
                                        + ', input[name="cosmdl_options[statement_title_color_hex]"]'
                                        + ', input[name="cosmdl_options[statement_text_color]"]'
                                        + ', input[name="cosmdl_options[statement_text_color_hex]"]'
                                        + ', textarea[name="cosmdl_options[statement_custom_css]"]'
                                        + ', input[name="cosmdl_options[statement_title]"]'
                                        + ', textarea[name="cosmdl_options[statement_text]"]'
                                        + ', select[name="cosmdl_options[statement_card_theme]"]'
                                        );
                                        statementInputs.forEach(function(input) {
                                          input.addEventListener('input', cosmdlUpdateStatementPreview);
                                          input.addEventListener('change', cosmdlUpdateStatementPreview);
                                        });
                                        
                                        // 初始化预览
                                        cosmdlUpdateStatementPreview();
                                      }
                                    </script>
                                </div>
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'statement_title')); ?></div>
                                <div class="cosmdl-module__kv"><label>声明内容</label><?php $this->field_textarea(array('key'=>'statement_text')); ?></div>
                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[statement_card_theme]" id="statement-card-theme">
                                        <option value="" <?php selected(($options['statement_card_theme'] ?? 'blue'), ''); ?>>默认</option>
                                        <option value="blue" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="gray" <?php selected(($options['statement_card_theme'] ?? 'blue'), 'gray'); ?>>灰色主题</option>
                                    </select>
                                </div>
                                <div class="cosmdl-module__kv"><label>边框颜色</label><?php $this->field_color(array('key'=>'statement_border_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>背景颜色</label><?php $this->field_color(array('key'=>'statement_bg_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>标题颜色</label><?php $this->field_color(array('key'=>'statement_title_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>正文颜色</label><?php $this->field_color(array('key'=>'statement_text_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>自定义CSS</label><?php $this->field_textarea(array('key'=>'statement_custom_css', 'rows'=>6)); ?></div>
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="statement" />
                            </div>
                        </li>

                        <!-- 模块：文件信息 -->
                        <li class="cosmdl-module" data-module="fileinfo">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">文件信息卡</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_fileinfo')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">

                                <!-- 文件信息模块实时预览 -->
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-fileinfo-preview" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-card file-info-card" id="cosmdl-fileinfo-card" style="
                                            --cosmdl-theme-rgb: 63,131,248;
                                            --cosmdl-card-radius: 8px;
                                            --cosmdl-card-shadow: 0 2px 16px rgba(0,0,0,0.06);
                                            margin: 0;
                                        ">
                                            <?php 
                                            $file_title = isset($options['file_info_title']) ? trim((string)$options['file_info_title']) : '';
                                            ?>
                                            <div class="cosmdl-card-header"<?php echo $file_title === '' ? ' style="display:none;"' : ''; ?>>
                                                <span class="cosmdl-card-icon">⬇</span>
                                                <span class="cosmdl-card-title"><?php echo $file_title !== '' ? esc_html($file_title) : ''; ?></span>
                                            </div>
                                            <div class="cosmdl-card-body no-aside" style="color: #111827;">
                                                <div class="cosmdl-meta">
                                                    <p><?php echo esc_html__('文件名称：', 'cosmautdl'); ?><span>示例文件.zip</span></p>
                                                    <p><?php echo esc_html__('软件性质：', 'cosmautdl'); ?><span>压缩包</span></p>
                                                    <p><?php echo esc_html__('更新日期：', 'cosmautdl'); ?><span>2024-01-15</span></p>
                                                    <p><?php echo esc_html__('文件大小：', 'cosmautdl'); ?><span>125.6 MB</span></p>
													<?php $sample_url = home_url('/sample-post/'); ?>
													<p><?php echo esc_html__('原文出处：', 'cosmautdl'); ?><a class="cosmdl-link" href="<?php echo esc_url($sample_url); ?>"><?php echo esc_html($sample_url); ?></a></p>
                                                </div>
                                                
                                                <!-- 预览广告位 -->
                                                <div class="cosmdl-ad-slot-preview" style="display: none;">
                                                    <div class="cosmdl-ad-container">
                                                        <div style="background: #f3f4f6; border: 2px dashed #d1d5db; padding: 20px; text-align: center; color: #6b7280; font-size: 14px; border-radius: 6px;">
                                                            广告位预览区域<br>
                                                            <small>（实际显示时为设置的广告内容）</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                    (function(){
                                      // 中文注释：文件信息模块实时预览（类似旧版本的即时预览）
                                      function cosmdlUpdateFileInfoPreview(){
                                        var card = document.getElementById('cosmdl-fileinfo-card');
                                        if(!card) return;
                                        
                                        // 获取模块标题并处理头部显示逻辑
                                        var titleInput = document.querySelector('input[name="cosmdl_options[file_info_title]"]');
                                        var title = titleInput ? titleInput.value.trim() : '';
                                        var headerElement = card.querySelector('.cosmdl-card-header');
                                        
                                        if (title) {
                                            // 标题不为空时，显示头部并更新标题
                                            if (headerElement) {
                                                headerElement.style.display = 'flex';
                                            }
                                            var titleElement = card.querySelector('.cosmdl-card-title');
                                            if (titleElement) {
                                                titleElement.textContent = title;
                                            }
                                        } else {
                                            // 标题为空时，隐藏整个头部
                                            if (headerElement) {
                                                headerElement.style.display = 'none';
                                            }
                                        }
                                        
                                        // 获取卡片主题色设置
                                        var themeSelect = document.querySelector('select[name="cosmdl_options[file_info_card_theme]"]');
                                        var theme = themeSelect ? themeSelect.value : 'green';
                                        
                                        // 主题色映射（同时提供 RGB 用于渐变背景）
                                        var themeMap = {
                                          'green':  { base:'#22c55e', rgb:'34,197,94' },
                                          'blue':   { base:'#3b82f6', rgb:'63,131,248' },
                                          'red':    { base:'#ef4444', rgb:'239,68,68' },
                                          'purple': { base:'#8b5cf6', rgb:'124,58,237' },
                                          'orange': { base:'#f59e0b', rgb:'245,158,11' },
                                          'pink':   { base:'#ec4899', rgb:'236,72,153' }
                                        };
                                        var t = themeMap[theme] || themeMap['green'];
                                        
                                        // 获取圆角设置
                                        var radiusSelect = document.querySelector('select[name="cosmdl_options[file_info_card_border_radius]"]');
                                        var radius = radiusSelect ? radiusSelect.value : 'medium';
                                        
                                        // 圆角映射统一
                                        var radiusValues = { 'none':'0px','small':'4px','medium':'8px','large':'16px' };
                                        var radiusPx = radiusValues[radius] || radiusValues['medium'];
                                        
                                        // 获取阴影设置
                                        var shadowCheckbox = document.querySelector('input[name="cosmdl_options[file_info_card_shadow]"]');
                                        var shadow = shadowCheckbox && shadowCheckbox.checked ? 'yes' : 'no';
                                        var shadowCss = shadow === 'yes' ? '0 2px 16px rgba(0,0,0,0.06)' : 'none';
                                        
                                        // 通过 CSS 变量更新预览样式（与前台一致）
                                        card.style.setProperty('--cosmdl-card-radius', radiusPx);
                                        card.style.setProperty('--cosmdl-card-shadow', shadowCss);
                                        card.style.setProperty('--cosmdl-theme-rgb', t.rgb);
                                        card.style.setProperty('--cosmdl-primary', t.base);
                                        card.style.setProperty('--cosmdl-primary-600', t.deep);
                                        card.style.setProperty('--cosmdl-primary-700', t.darker);
                                        
                                        // 控制广告位预览显示
                                        var adCheckbox = document.querySelector('input[type="checkbox"][name="cosmdl_options[show_ad_slot]"]');
                                        var adPreview = card.querySelector('.cosmdl-ad-slot-preview');
                                        if (adCheckbox && adPreview) {
                                            if (adCheckbox.checked) {
                                                adPreview.style.display = 'block';
                                            } else {
                                                adPreview.style.display = 'none';
                                            }
                                        }
                                      }
                                      
                                      window.cosmdlUpdateFileInfoPreview = cosmdlUpdateFileInfoPreview;
                                      document.addEventListener('DOMContentLoaded', function(){
                                        var mod = document.querySelector('[data-module="fileinfo"]');
                                        if(!mod) return;
                                        
                                        // 初始化预览
                                        cosmdlUpdateFileInfoPreview();
                                        
                                        // 监听设置变化，实现即时预览
                                        mod.addEventListener('input', cosmdlUpdateFileInfoPreview, true);
                                        mod.addEventListener('change', cosmdlUpdateFileInfoPreview, true);
                                      });
                                    })();
                                    </script>
                                </div>
                                
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'file_info_title')); ?></div>

                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[file_info_card_theme]" class="cosmdl-card-theme-select">
                                        <option value="blue" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="pink" <?php selected(($options['file_info_card_theme'] ?? 'blue'), 'pink'); ?>>粉色主题</option>
                                    </select>
                                    <p class="description">设置后覆盖文章页下载卡片、独立下载页文件信息卡片。</p>
                                </div>
                                <div class="cosmdl-module__kv"><label>卡片圆角</label>
                                    <select name="cosmdl_options[file_info_card_border_radius]">
                                        <option value="none" <?php selected(($options['file_info_card_border_radius'] ?? 'medium'), 'none'); ?>>无圆角</option>
                                        <option value="small" <?php selected(($options['file_info_card_border_radius'] ?? 'medium'), 'small'); ?>>小圆角</option>
                                        <option value="medium" <?php selected(($options['file_info_card_border_radius'] ?? 'medium'), 'medium'); ?>>中等圆角</option>
                                        <option value="large" <?php selected(($options['file_info_card_border_radius'] ?? 'large'), 'large'); ?>>大圆角</option>
                                    </select>
                                    <p class="description">提示：若未设置，则继承全局圆角；设置后仅覆盖本模块及文章页卡片。</p>
                                </div>
                                <div class="cosmdl-module__kv"><label>卡片阴影</label>
                                    <label class="cosmdl-checkbox">
                                        <input type="checkbox" name="cosmdl_options[file_info_card_shadow]" value="yes" <?php checked(($options['file_info_card_shadow'] ?? 'yes'), 'yes'); ?> />
                                        <span>启用卡片阴影</span>
                                    </label>
                                    <p class="description">提示：未选中时等价于继承关闭阴影；选中时覆盖为开启阴影。</p>
                                </div>
                                
                                <!-- 广告位设置 -->
                                <div class="cosmdl-module__kv"><label>显示广告位</label>
                                    <label class="cosmdl-checkbox">
                                        <input type="hidden" name="cosmdl_options[show_ad_slot]" value="no" />
                                        <input type="checkbox" name="cosmdl_options[show_ad_slot]" value="yes" <?php checked(($options['show_ad_slot'] ?? 'no'), 'yes'); ?> />
                                        <span>在文件信息卡片中显示广告</span>
                                    </label>
                                </div>
                                <div class="cosmdl-module__kv"><label>广告位 HTML</label>
                                    <textarea name="cosmdl_options[ad_html]" rows="4" placeholder="请输入广告HTML代码..."><?php echo esc_textarea($options['ad_html'] ?? ''); ?></textarea>
                                </div>
                                
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="fileinfo" />
                            </div>
                        </li>

                        <!-- 模块：自定义链接 -->
                        <li class="cosmdl-module" data-module="custom_links">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">自定义链接</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_custom_links')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">
                                <!-- 自定义链接模块实时预览 -->
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-custom-links-preview cosmdl-preview-card" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-section cosmdl-custom-links" id="cosmdl-custom-links-preview" style="
                                            --cosmdl-link-count: 2;
                                            margin: 0;
                                        ">
                                            <div class="cosmdl-custom-links-title-wrapper">
                                                <h3 class="cosmdl-section-title cosmdl-custom-links-title">自定义链接</h3>
                                            </div>
                                            <div class="cosmdl-custom-links-content-wrapper">
                                                <div class="cosmdl-custom-link-item">
                                                    <a href="#" class="cosmdl-link" style="pointer-events: none;">官方演示</a>
                                                </div>
                                                <div class="cosmdl-custom-link-item">
                                                    <a href="#" class="cosmdl-link" style="pointer-events: none;">购买链接</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                      // 自定义链接模块实时预览
                                      function cosmdlUpdateCustomLinksPreview(event){
                                        var container = document.getElementById('cosmdl-custom-links-preview');
                                        if(!container) return;
                                        
                                        // 重置所有样式
                                        container.removeAttribute('style');
                                        
                                        // 处理自定义CSS
                                        var customCss = document.querySelector('textarea[name="cosmdl_options[custom_links_custom_css]"]');
                                        if(customCss) {
                                          // 移除旧的style元素
                                          var oldStyle = document.getElementById('cosmdl-custom-links-custom-css');
                                          if(oldStyle) oldStyle.remove();
                                          
                                          // 如果有自定义CSS，创建新的style元素
                                          if(customCss.value.trim()) {
                                            var styleElement = document.createElement('style');
                                            styleElement.id = 'cosmdl-custom-links-custom-css';
                                            styleElement.textContent = customCss.value;
                                            document.head.appendChild(styleElement);
                                          }
                                        }

                                        // 获取颜色输入元素
                                        var borderColorInput = document.querySelector('input[name="cosmdl_options[custom_links_border_color]"]');
                                        var borderColorHexInput = document.querySelector('input[name="cosmdl_options[custom_links_border_color_hex]"]');
                                        var bgColorInput = document.querySelector('input[name="cosmdl_options[custom_links_bg_color]"]');
                                        var bgColorHexInput = document.querySelector('input[name="cosmdl_options[custom_links_bg_color_hex]"]');
                                        var titleColorInput = document.querySelector('input[name="cosmdl_options[custom_links_title_color]"]');
                                        var titleColorHexInput = document.querySelector('input[name="cosmdl_options[custom_links_title_color_hex]"]');
                                        var textColorInput = document.querySelector('input[name="cosmdl_options[custom_links_text_color]"]');
                                        var textColorHexInput = document.querySelector('input[name="cosmdl_options[custom_links_text_color_hex]"]');

                                        // 当前颜色值（优先使用hex输入）
                                        var borderColor = borderColorHexInput || borderColorInput;
                                        var bgColor = bgColorHexInput || bgColorInput;
                                        var titleColor = titleColorHexInput || titleColorInput;
                                        var textColor = textColorHexInput || textColorInput;
                                        
                                        // 获取卡片主题色设置
                                        var themeSelect = document.getElementById('custom-links-card-theme');
                                        var theme = themeSelect ? themeSelect.value : 'blue';
                                        
                                        // 预定义主题颜色
                                        var themeColors = {
                                          blue: {
                                            border: '#acd0f9',
                                            bg: '#e8f2fd',
                                            text: '#4285f4'
                                          },
                                          green: {
                                            border: '#a8dbc1',
                                            bg: '#e7f5ee',
                                            text: '#34a853'
                                          },
                                          purple: {
                                            border: '#e1bbfc',
                                            bg: '#f7edfe',
                                            text: '#a256e3'
                                          },
                                          orange: {
                                            border: '#f9d69f',
                                            bg: '#fdf3e4',
                                            text: '#fbbc05'
                                          },
                                          red: {
                                            border: '#ffb7b2',
                                            bg: '#fff5f4',
                                            text: '#ea4335'
                                          },
                                          gray: {
                                            border: '#d1d5db',
                                            bg: '#f5f6f8',
                                            text: '#64748b'
                                          }
                                        };

                                        var defaultColors = {
                                          border: '#e5e7eb',
                                          bg: '#ffffff',
                                          title: '#111827',
                                          text: '#3b82f6'
                                        };

                                        var isThemeChange = event && event.target && event.target.id === 'custom-links-card-theme';

                                        if (isThemeChange && !theme) {
                                          if (borderColorInput) borderColorInput.value = defaultColors.border;
                                          if (borderColorHexInput) borderColorHexInput.value = defaultColors.border;
                                          if (bgColorInput) bgColorInput.value = defaultColors.bg;
                                          if (bgColorHexInput) bgColorHexInput.value = defaultColors.bg;
                                          if (titleColorInput) titleColorInput.value = defaultColors.title;
                                          if (titleColorHexInput) titleColorHexInput.value = defaultColors.title;
                                          if (textColorInput) textColorInput.value = defaultColors.text;
                                          if (textColorHexInput) textColorHexInput.value = defaultColors.text;

                                          if (customCss) customCss.value = '';
                                          var oldStyleReset = document.getElementById('cosmdl-custom-links-custom-css');
                                          if (oldStyleReset) oldStyleReset.remove();

                                          container.style.padding = '16px';
                                          container.style.borderRadius = '8px';
                                          container.style.border = '1px solid ' + defaultColors.border;
                                          container.style.backgroundColor = defaultColors.bg;

                                          var titleEl = container.querySelector('.cosmdl-custom-links-title');
                                          if (titleEl) {
                                            titleEl.style.color = defaultColors.title;
                                          }

                                          var linksReset = container.querySelectorAll('.cosmdl-link');
                                          linksReset.forEach(function(link) {
                                            link.style.color = defaultColors.text;
                                          });

                                          borderColor = { value: defaultColors.border };
                                          bgColor = { value: defaultColors.bg };
                                          titleColor = { value: defaultColors.title };
                                          textColor = { value: defaultColors.text };

                                          return;
                                        }

                                        if(isThemeChange && theme && themeColors[theme]) {
                                          var preset = themeColors[theme];
                                          if(borderColorInput) borderColorInput.value = preset.border;
                                          if(borderColorHexInput) borderColorHexInput.value = preset.border;
                                          if(bgColorInput) bgColorInput.value = preset.bg;
                                          if(bgColorHexInput) bgColorHexInput.value = preset.bg;
                                          if(titleColorInput) titleColorInput.value = preset.text;
                                          if(titleColorHexInput) titleColorHexInput.value = preset.text;
                                          if(textColorInput) textColorInput.value = preset.text;
                                          if(textColorHexInput) textColorHexInput.value = preset.text;

                                          borderColor = borderColorHexInput || borderColorInput;
                                          bgColor = bgColorHexInput || bgColorInput;
                                          titleColor = titleColorHexInput || titleColorInput;
                                          textColor = textColorHexInput || textColorInput;
                                        }

                                        // 应用主题颜色作为兜底（当输入为空时）
                                        if(theme && themeColors[theme]) {
                                          if(!borderColor || !borderColor.value) {
                                            borderColor = { value: themeColors[theme].border };
                                          }
                                          if(!bgColor || !bgColor.value) {
                                            bgColor = { value: themeColors[theme].bg };
                                          }
                                          if(!titleColor || !titleColor.value) {
                                            titleColor = { value: themeColors[theme].text };
                                          }
                                          if(!textColor || !textColor.value) {
                                            textColor = { value: themeColors[theme].text };
                                          }
                                        }
                                        
                                        // 获取内容设置
                                        var linksTitle = document.querySelector('input[name="cosmdl_options[custom_links_title]"]');
                                        
                                        // 收集有效链接
                                        var validLinks = [];
                                        for(var i=1; i<=4; i++) {
                                          var labelInput = document.querySelector('input[name="cosmdl_options[custom_link_'+i+'_label]"]');
                                          var urlInput = document.querySelector('input[name="cosmdl_options[custom_link_'+i+'_url]"]');
                                          if(labelInput && urlInput && labelInput.value.trim() && urlInput.value.trim()) {
                                            validLinks.push({
                                              label: labelInput.value.trim(),
                                              url: urlInput.value.trim()
                                            });
                                          }
                                        }
                                        
                                        // 先根据标题与链接数量判断模块是否需要隐藏
                                        var rawTitle = linksTitle ? linksTitle.value : '';
                                        var trimmedTitle = rawTitle.trim();

                                        if (trimmedTitle === '' && validLinks.length === 0) {
                                          container.style.display = 'none';
                                          return;
                                        } else {
                                          container.style.display = '';
                                        }

                                        // 更新标题内容

                                        var title = container.querySelector('.cosmdl-custom-links-title');
                                        if (title) {
                                          if (trimmedTitle === '') {
                                            title.style.display = 'none';
                                            title.textContent = '';
                                          } else {
                                            title.style.display = '';
                                            title.textContent = rawTitle;
                                            if (titleColor) title.style.color = titleColor.value;
                                            title.style.textAlign = '';
                                            title.style.width = '';
                                          }
                                        }

                                        var titleWrapper = container.querySelector('.cosmdl-custom-links-title-wrapper');
                                        if(titleWrapper) {
                                            if (trimmedTitle === '') {
                                              titleWrapper.style.display = 'none';
                                            } else {
                                              titleWrapper.style.display = '';
                                            }
                                            titleWrapper.style.justifyContent = '';
                                            titleWrapper.style.alignItems = '';
                                        }
                                        
                                        // 更新链接内容
                                        var contentWrapper = container.querySelector('.cosmdl-custom-links-content-wrapper');
                                        if(contentWrapper && validLinks.length > 0) {
                                          // 清空现有链接
                                          contentWrapper.innerHTML = '';
                                          
                                          // 添加有效链接
                                          validLinks.forEach(function(link) {
                                            var item = document.createElement('div');
                                            item.className = 'cosmdl-custom-link-item';
                                            var anchor = document.createElement('a');
                                            anchor.className = 'cosmdl-link';
                                            anchor.href = '#';
                                            anchor.textContent = link.label;
                                            anchor.style.pointerEvents = 'none'; // 禁用点击
                                            item.appendChild(anchor);
                                            contentWrapper.appendChild(item);
                                          });
                                          
                                          // 更新链接数量CSS变量
                                          container.style.setProperty('--cosmdl-link-count', validLinks.length);
                                        } else if(contentWrapper) {
                                          contentWrapper.innerHTML = '<div style="color: #6b7280; font-style: italic;">请添加至少一个有效的链接</div>';
                                        }
                                        
                                        // 应用默认内边距和边框样式（与前端保持一致）
                                        container.style.padding = '16px';
                                        container.style.borderRadius = '8px';
                                        container.style.border = '1px solid';
                                        
                                        // 应用样式（这些样式会覆盖自定义CSS中的对应样式）
                                        if(borderColor) {
                                          container.style.borderColor = borderColor.value;
                                        } else {
                                          container.style.borderColor = '#e2e8f0'; // 默认边框颜色
                                        }
                                        if(bgColor) container.style.backgroundColor = bgColor.value;
                                        
                                        // 应用正文颜色到所有链接
                                        var links = container.querySelectorAll('.cosmdl-link');
                                        links.forEach(function(link) {
                                          if(textColor) {
                                            link.style.color = textColor.value;
                                          } else {
                                            link.style.color = '#3b82f6'; // 默认链接颜色
                                          }
                                        });
                                      }
                                      
                                      // 确保DOM加载完成后执行
                                      if (document.readyState === 'loading') {
                                        document.addEventListener('DOMContentLoaded', function() {
                                          // 监听所有与custom_links相关的输入变化
                                          var customLinksInputs = document.querySelectorAll(
                                            'input[name="cosmdl_options[custom_links_border_color]"]'
                                          + ', input[name="cosmdl_options[custom_links_border_color_hex]"]'
                                          + ', input[name="cosmdl_options[custom_links_bg_color]"]'
                                          + ', input[name="cosmdl_options[custom_links_bg_color_hex]"]'
                                          + ', input[name="cosmdl_options[custom_links_title_color]"]'
                                          + ', input[name="cosmdl_options[custom_links_title_color_hex]"]'
                                          + ', input[name="cosmdl_options[custom_links_text_color]"]'
                                          + ', input[name="cosmdl_options[custom_links_text_color_hex]"]'
                                          + ', textarea[name="cosmdl_options[custom_links_custom_css]"]'
                                          + ', input[name="cosmdl_options[custom_links_title]"]'
                                          + ', input[name="cosmdl_options[custom_link_1_label]"]'
                                          + ', input[name="cosmdl_options[custom_link_1_url]"]'
                                          + ', input[name="cosmdl_options[custom_link_2_label]"]'
                                          + ', input[name="cosmdl_options[custom_link_2_url]"]'
                                          + ', input[name="cosmdl_options[custom_link_3_label]"]'
                                          + ', input[name="cosmdl_options[custom_link_3_url]"]'
                                          + ', input[name="cosmdl_options[custom_link_4_label]"]'
                                          + ', input[name="cosmdl_options[custom_link_4_url]"]'
                                          + ', select#custom-links-card-theme'
                                          );
                                          customLinksInputs.forEach(function(input) {
                                            input.addEventListener('input', cosmdlUpdateCustomLinksPreview);
                                            input.addEventListener('change', cosmdlUpdateCustomLinksPreview);
                                          });
                                          
                                          // 初始化预览
                                          cosmdlUpdateCustomLinksPreview();
                                        });
                                      } else {
                                        // DOM已经加载完成
                                        // 监听所有与custom_links相关的输入变化
                                        var customLinksInputs = document.querySelectorAll(
                                          'input[name="cosmdl_options[custom_links_border_color]"]'
                                        + ', input[name="cosmdl_options[custom_links_border_color_hex]"]'
                                        + ', input[name="cosmdl_options[custom_links_bg_color]"]'
                                        + ', input[name="cosmdl_options[custom_links_bg_color_hex]"]'
                                        + ', input[name="cosmdl_options[custom_links_title_color]"]'
                                        + ', input[name="cosmdl_options[custom_links_title_color_hex]"]'
                                        + ', input[name="cosmdl_options[custom_links_text_color]"]'
                                        + ', input[name="cosmdl_options[custom_links_text_color_hex]"]'
                                        + ', textarea[name="cosmdl_options[custom_links_custom_css]"]'
                                        + ', input[name="cosmdl_options[custom_links_title]"]'
                                        + ', input[name="cosmdl_options[custom_link_1_label]"]'
                                        + ', input[name="cosmdl_options[custom_link_1_url]"]'
                                        + ', input[name="cosmdl_options[custom_link_2_label]"]'
                                        + ', input[name="cosmdl_options[custom_link_2_url]"]'
                                        + ', input[name="cosmdl_options[custom_link_3_label]"]'
                                        + ', input[name="cosmdl_options[custom_link_3_url]"]'
                                        + ', input[name="cosmdl_options[custom_link_4_label]"]'
                                        + ', input[name="cosmdl_options[custom_link_4_url]"]'
                                        + ', select#custom-links-card-theme'
                                        );
                                        customLinksInputs.forEach(function(input) {
                                          input.addEventListener('input', cosmdlUpdateCustomLinksPreview);
                                          input.addEventListener('change', cosmdlUpdateCustomLinksPreview);
                                        });
                                        
                                        // 初始化预览
                                        cosmdlUpdateCustomLinksPreview();
                                      }
                                    </script>
                                </div>
                                
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'custom_links_title')); ?></div>
                                <?php for($i=1;$i<=4;$i++): ?>
                                <div class="cosmdl-module__kv">
                                    <label>链接<?php echo esc_html($i); ?>标题</label>
                                    <?php $this->field_text(array('key'=>'custom_link_'.$i.'_label')); ?>
                                </div>
                                <div class="cosmdl-module__kv">
                                    <label>链接<?php echo esc_html($i); ?>地址</label>
                                    <?php $this->field_text(array('key'=>'custom_link_'.$i.'_url')); ?>
                                </div>
                                <?php endfor; ?>
                                
                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[custom_links_card_theme]" id="custom-links-card-theme" class="cosmdl-card-theme-select">
                                        <option value="" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), ''); ?>>默认</option>
                                        <option value="blue" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="gray" <?php selected(($options['custom_links_card_theme'] ?? 'blue'), 'gray'); ?>>灰色主题</option>
                                    </select>
                                </div>
                                <div class="cosmdl-module__kv"><label>边框颜色</label><?php $this->field_color(array('key'=>'custom_links_border_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>背景颜色</label><?php $this->field_color(array('key'=>'custom_links_bg_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>标题颜色</label><?php $this->field_color(array('key'=>'custom_links_title_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>正文颜色</label><?php $this->field_color(array('key'=>'custom_links_text_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>自定义CSS</label><?php $this->field_textarea(array('key'=>'custom_links_custom_css', 'rows'=>6)); ?></div>
                                
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="custom_links" />
                            </div>
                        </li>

                        <!-- 模块：网盘卡片 -->
                        <li class="cosmdl-module" data-module="pan_cards">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">下载按钮组</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_pan_cards')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-pan-cards-preview cosmdl-preview-card" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-section cosmdl-pan-cards-section" id="cosmdl-pan-cards-preview" style="margin:0; padding:16px; border-radius:8px; border:1px solid #e5e7eb; background-color:#ffffff; color:#6b7280;">
                                            <h3 class="cosmdl-section-title">下载按钮组</h3>
                                            <div class="cosmdl-pan-cards-html-content" style="margin-bottom:12px;"></div>
											<div class="cosmdl-pan-group" id="cosmdl-pan-group-preview" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px;">
																<?php $cosmdl_allowed_drive_logo = array( 'img' => array( 'class' => true, 'src' => true, 'alt' => true, 'aria-hidden' => true, 'width' => true, 'height' => true ) ); ?>
																<a href="javascript:void(0);" class="cosmdl-pan-btn" style="pointer-events:none;">
																    <?php if (function_exists('cosmdl_drive_logo_html')) { echo wp_kses(cosmdl_drive_logo_html('baidu', 'baidu'), $cosmdl_allowed_drive_logo); } ?>
																    <span class="cosmdl-pan-info">
																        <span class="cosmdl-pan-text">百度网盘</span>
																        <span class="cosmdl-pan-pwd">提取码: abcd</span>
																    </span>
																</a>
																<a href="javascript:void(0);" class="cosmdl-pan-btn" style="pointer-events:none;">
																    <?php if (function_exists('cosmdl_drive_logo_html')) { echo wp_kses(cosmdl_drive_logo_html('123', '123'), $cosmdl_allowed_drive_logo); } ?>
																    <span class="cosmdl-pan-info">
																        <span class="cosmdl-pan-text">123云盘</span>
																        <span class="cosmdl-pan-pwd">提取码: 4567</span>
																    </span>
																</a>
																<a href="javascript:void(0);" class="cosmdl-pan-btn" style="pointer-events:none;">
																    <?php if (function_exists('cosmdl_drive_logo_html')) { echo wp_kses(cosmdl_drive_logo_html('ali', 'ali'), $cosmdl_allowed_drive_logo); } ?>
																    <span class="cosmdl-pan-info">
																        <span class="cosmdl-pan-text">阿里云盘</span>
																        <span class="cosmdl-pan-pwd">提取码: 1234</span>
																    </span>
																</a>
															</div>
														</div>
													</div>
                                    <script>
                                      (function(){
                                        function cosmdlUpdatePanCardsPreview(event){
                                          var container = document.getElementById('cosmdl-pan-cards-preview');
                                          if(!container) return;

                                          var borderColorInput = document.querySelector('input[name="cosmdl_options[pan_cards_border_color]"]');
                                          var borderColorHexInput = document.querySelector('input[name="cosmdl_options[pan_cards_border_color_hex]"]');
                                          var bgColorInput = document.querySelector('input[name="cosmdl_options[pan_cards_bg_color]"]');
                                          var bgColorHexInput = document.querySelector('input[name="cosmdl_options[pan_cards_bg_color_hex]"]');
                                          var titleColorInput = document.querySelector('input[name="cosmdl_options[pan_cards_title_color]"]');
                                          var titleColorHexInput = document.querySelector('input[name="cosmdl_options[pan_cards_title_color_hex]"]');
                                          var textColorInput = document.querySelector('input[name="cosmdl_options[pan_cards_text_color]"]');
                                          var textColorHexInput = document.querySelector('input[name="cosmdl_options[pan_cards_text_color_hex]"]');

                                          var borderColor = borderColorHexInput || borderColorInput;
                                          var bgColor = bgColorHexInput || bgColorInput;
                                          var titleColor = titleColorHexInput || titleColorInput;
                                          var textColor = textColorHexInput || textColorInput;
                                          
                                          // 获取卡片主题色设置
                                          var themeSelect = document.getElementById('pan-cards-card-theme');
                                          var theme = themeSelect ? themeSelect.value : 'blue';
                                          
                                          // 预定义主题颜色
                                          var themeColors = {
                                            blue: {
                                              border: '#acd0f9',
                                              bg: '#e8f2fd',
                                              text: '#4285f4'
                                            },
                                            green: {
                                              border: '#a8dbc1',
                                              bg: '#e7f5ee',
                                              text: '#34a853'
                                            },
                                            purple: {
                                              border: '#e1bbfc',
                                              bg: '#f7edfe',
                                              text: '#a256e3'
                                            },
                                            orange: {
                                              border: '#f9d69f',
                                              bg: '#fdf3e4',
                                              text: '#fbbc05'
                                            },
                                            red: {
                                              border: '#ffb7b2',
                                              bg: '#fff5f4',
                                              text: '#ea4335'
                                            },
                                            gray: {
                                              border: '#d1d5db',
                                              bg: '#f5f6f8',
                                              text: '#64748b'
                                            }
                                          };

                                          var defaultColors = {
                                            border: '#e5e7eb',
                                            bg: '#ffffff',
                                            title: '#111827',
                                            text: '#6b7280'
                                          };

                                          var isThemeChange = event && event.target && event.target.id === 'pan-cards-card-theme';

                                          if (isThemeChange && !theme) {
                                            if (borderColorInput) borderColorInput.value = defaultColors.border;
                                            if (borderColorHexInput) borderColorHexInput.value = defaultColors.border;
                                            if (bgColorInput) bgColorInput.value = defaultColors.bg;
                                            if (bgColorHexInput) bgColorHexInput.value = defaultColors.bg;
                                            if (titleColorInput) titleColorInput.value = defaultColors.title;
                                            if (titleColorHexInput) titleColorHexInput.value = defaultColors.title;
                                            if (textColorInput) textColorInput.value = defaultColors.text;
                                            if (textColorHexInput) textColorHexInput.value = defaultColors.text;

                                            if (customCss) {
                                              customCss.value = '';
                                              var oldStyleReset = document.getElementById('cosmdl-pan-cards-custom-css');
                                              if (oldStyleReset) oldStyleReset.remove();
                                            }

                                            container.style.padding = '16px';
                                            container.style.borderRadius = '8px';
                                            container.style.border = '1px solid ' + defaultColors.border;
                                            container.style.backgroundColor = defaultColors.bg;
                                            container.style.color = defaultColors.text;

                                            var titleEl = container.querySelector('.cosmdl-section-title');
                                            if (titleEl) {
                                              titleEl.style.color = defaultColors.title;
                                            }

                                            borderColor = { value: defaultColors.border };
                                            bgColor = { value: defaultColors.bg };
                                            titleColor = { value: defaultColors.title };
                                            textColor = { value: defaultColors.text };

                                            return;
                                          }

                                          if(isThemeChange && theme && themeColors[theme]) {
                                            var preset = themeColors[theme];
                                            if(borderColorInput) borderColorInput.value = preset.border;
                                            if(borderColorHexInput) borderColorHexInput.value = preset.border;
                                            if(bgColorInput) bgColorInput.value = preset.bg;
                                            if(bgColorHexInput) bgColorHexInput.value = preset.bg;
                                            if(titleColorInput) titleColorInput.value = preset.text;
                                            if(titleColorHexInput) titleColorHexInput.value = preset.text;
                                            if(textColorInput) textColorInput.value = preset.text;
                                            if(textColorHexInput) textColorHexInput.value = preset.text;

                                            borderColor = borderColorHexInput || borderColorInput;
                                            bgColor = bgColorHexInput || bgColorInput;
                                            titleColor = titleColorHexInput || titleColorInput;
                                            textColor = textColorHexInput || textColorInput;
                                          }

                                          // 应用主题颜色作为兜底
                                          if(theme && themeColors[theme]) {
                                            if(!borderColor || !borderColor.value) {
                                              borderColor = { value: themeColors[theme].border };
                                            }
                                            if(!bgColor || !bgColor.value) {
                                              bgColor = { value: themeColors[theme].bg };
                                            }
                                            if(!titleColor || !titleColor.value) {
                                              titleColor = { value: themeColors[theme].text };
                                            }
                                            if(!textColor || !textColor.value) {
                                              textColor = { value: themeColors[theme].text };
                                            }
                                          }
                                          var titleInput = document.querySelector('input[name="cosmdl_options[pan_cards_title]"]');
                                          var htmlInput = document.querySelector('textarea[name="cosmdl_options[pan_cards_html]"]');
                                          var customCss = document.querySelector('textarea[name="cosmdl_options[pan_cards_custom_css]"]');

                                          var rawTitle = titleInput ? titleInput.value : '';
                                          var rawHtml = htmlInput ? htmlInput.value : '';
                                          var trimmedTitle = rawTitle.trim();
                                          var trimmedHtml = rawHtml.trim();

                                          container.style.display = '';

                                          container.style.padding = '16px';
                                          container.style.borderRadius = '8px';
                                          container.style.border = '1px solid ' + (borderColor && borderColor.value ? borderColor.value : '#e5e7eb');
                                          container.style.backgroundColor = bgColor && bgColor.value ? bgColor.value : '#ffffff';
                                          container.style.color = textColor && textColor.value ? textColor.value : '#6b7280';

                                          var title = container.querySelector('.cosmdl-section-title');
                                          if(title){
                                            if (trimmedTitle === '') {
                                              title.style.display = 'none';
                                              title.textContent = '';
                                            } else {
                                              title.style.display = '';
                                              title.textContent = rawTitle;
                                              if(titleColor && titleColor.value){
                                                title.style.color = titleColor.value;
                                              }
                                            }
                                          }

													var htmlContent = container.querySelector('.cosmdl-pan-cards-html-content');
													if (htmlContent && htmlInput) {
														if (trimmedHtml === '') {
															htmlContent.innerHTML = '';
															htmlContent.style.display = 'none';
															htmlContent.style.marginBottom = '0';
														} else {
															htmlContent.style.display = '';
															htmlContent.style.marginBottom = '12px';
															htmlContent.innerHTML = rawHtml;
														}
													}

                                          if(customCss){
                                            var oldStyle = document.getElementById('cosmdl-pan-cards-custom-css');
                                            if(oldStyle) oldStyle.remove();
                                            if(customCss.value.trim()){
                                              var styleElement = document.createElement('style');
                                              styleElement.id = 'cosmdl-pan-cards-custom-css';
                                              styleElement.textContent = customCss.value;
                                              document.head.appendChild(styleElement);
                                            }
                                          }
                                        }

                                        function cosmdlBindPanCardsPreview(){
                                          var inputs = document.querySelectorAll(
                                            'input[name="cosmdl_options[pan_cards_border_color]"]'
                                            + ', input[name="cosmdl_options[pan_cards_border_color_hex]"]'
                                            + ', input[name="cosmdl_options[pan_cards_bg_color]"]'
                                            + ', input[name="cosmdl_options[pan_cards_bg_color_hex]"]'
                                            + ', input[name="cosmdl_options[pan_cards_title_color]"]'
                                            + ', input[name="cosmdl_options[pan_cards_title_color_hex]"]'
                                            + ', input[name="cosmdl_options[pan_cards_text_color]"]'
                                            + ', input[name="cosmdl_options[pan_cards_text_color_hex]"]'
                                            + ', input[name="cosmdl_options[pan_cards_title]"]'
                                            + ', textarea[name="cosmdl_options[pan_cards_html]"]'
                                            + ', textarea[name="cosmdl_options[pan_cards_custom_css]"]'
                                            + ', select#pan-cards-card-theme'
                                          );
                                          inputs.forEach(function(el){
                                            el.addEventListener('input', cosmdlUpdatePanCardsPreview);
                                            el.addEventListener('change', cosmdlUpdatePanCardsPreview);
                                          });
                                          cosmdlUpdatePanCardsPreview();
                                        }

                                        if(document.readyState === 'loading'){
                                          document.addEventListener('DOMContentLoaded', cosmdlBindPanCardsPreview);
                                        } else {
                                          cosmdlBindPanCardsPreview();
                                        }
                                      })();
                                    </script>
                                </div>
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'pan_cards_title')); ?></div>
                                <div class="cosmdl-module__kv"><label>模块内容</label><?php $this->field_textarea(array('key'=>'pan_cards_html')); ?></div>
                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[pan_cards_card_theme]" id="pan-cards-card-theme" class="cosmdl-card-theme-select">
                                        <option value="" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), ''); ?>>默认</option>
                                        <option value="blue" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="gray" <?php selected(($options['pan_cards_card_theme'] ?? 'blue'), 'gray'); ?>>灰色主题</option>
                                    </select>
                                </div>
                                <div class="cosmdl-module__kv"><label>边框颜色</label><?php $this->field_color(array('key'=>'pan_cards_border_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>背景颜色</label><?php $this->field_color(array('key'=>'pan_cards_bg_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>标题颜色</label><?php $this->field_color(array('key'=>'pan_cards_title_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>正文颜色</label><?php $this->field_color(array('key'=>'pan_cards_text_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>自定义CSS</label><?php $this->field_textarea(array('key'=>'pan_cards_custom_css', 'rows'=>6)); ?></div>
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="pan_cards" />
                            </div>
                        </li>

                        <!-- 模块：下载说明 -->
                        <li class="cosmdl-module" data-module="download_tips">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">下载说明</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_download_tips')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-download-tips-preview cosmdl-preview-card" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-section cosmdl-download-tips-section" id="cosmdl-download-tips-preview" style="margin:0; padding:16px; border-radius:8px; border:1px solid #e5e7eb; background-color:#ffffff; color:#6b7280;">
                                            <h3 class="cosmdl-section-title">下载说明</h3>
                                            <div class="cosmdl-download-tips-content">
                                                <ul class="help">
                                                    <li>1. 压缩包若设置密码，请参看页面说明或联系站点管理员。</li>
                                                    <li>2. 建议使用可靠的下载工具，避免浏览器断流引起错误。</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                      (function(){
                                        function cosmdlUpdateDownloadTipsPreview(event){
                                          var container = document.querySelector('.cosmdl-download-tips-section') || document.getElementById('cosmdl-download-tips-preview');
                                          if(!container) return;

                                          var themeSelect = document.getElementById('download-tips-card-theme');
                                          var theme = themeSelect ? themeSelect.value : 'blue';

                                          var themeColors = {
                                            blue: {
                                              border: '#acd0f9',
                                              bg: '#e8f2fd',
                                              text: '#4285f4'
                                            },
                                            green: {
                                              border: '#a8dbc1',
                                              bg: '#e7f5ee',
                                              text: '#34a853'
                                            },
                                            purple: {
                                              border: '#e1bbfc',
                                              bg: '#f7edfe',
                                              text: '#a256e3'
                                            },
                                            orange: {
                                              border: '#f9d69f',
                                              bg: '#fdf3e4',
                                              text: '#fbbc05'
                                            },
                                            red: {
                                              border: '#ffb7b2',
                                              bg: '#fff5f4',
                                              text: '#ea4335'
                                            },
                                            gray: {
                                              border: '#d1d5db',
                                              bg: '#f5f6f8',
                                              text: '#64748b'
                                            }
                                          };

                                          var borderColorInput = document.querySelector('input[name="cosmdl_options[download_tips_border_color]"]');
                                          var borderColorHexInput = document.querySelector('input[name="cosmdl_options[download_tips_border_color_hex]"]');
                                          var bgColorInput = document.querySelector('input[name="cosmdl_options[download_tips_bg_color]"]');
                                          var bgColorHexInput = document.querySelector('input[name="cosmdl_options[download_tips_bg_color_hex]"]');
                                          var titleColorInput = document.querySelector('input[name="cosmdl_options[download_tips_title_color]"]');
                                          var titleColorHexInput = document.querySelector('input[name="cosmdl_options[download_tips_title_color_hex]"]');
                                          var textColorInput = document.querySelector('input[name="cosmdl_options[download_tips_text_color]"]');
                                          var textColorHexInput = document.querySelector('input[name="cosmdl_options[download_tips_text_color_hex]"]');

                                          var borderColor = borderColorHexInput || borderColorInput;
                                          var bgColor = bgColorHexInput || bgColorInput;
                                          var titleColor = titleColorHexInput || titleColorInput;
                                          var textColor = textColorHexInput || textColorInput;

                                          var titleInput = document.querySelector('input[name="cosmdl_options[download_tips_title]"]');
                                          var htmlInput = document.querySelector('textarea[name="cosmdl_options[download_tips_html]"]');
                                          var customCss = document.querySelector('textarea[name="cosmdl_options[download_tips_custom_css]"]');

                                          var rawTitle = titleInput ? titleInput.value : '';
                                          var rawHtml = htmlInput ? htmlInput.value : '';
                                          var trimmedTitle = rawTitle.trim();
                                          var trimmedHtml = rawHtml.trim();

                                          var defaultColors = {
                                            border: '#e5e7eb',
                                            bg: '#ffffff',
                                            title: '#111827',
                                            text: '#6b7280'
                                          };

                                          var isThemeChange = event && event.target && event.target.id === 'download-tips-card-theme';

                                          if (isThemeChange && !theme) {
                                            if (borderColorInput) borderColorInput.value = defaultColors.border;
                                            if (borderColorHexInput) borderColorHexInput.value = defaultColors.border;
                                            if (bgColorInput) bgColorInput.value = defaultColors.bg;
                                            if (bgColorHexInput) bgColorHexInput.value = defaultColors.bg;
                                            if (titleColorInput) titleColorInput.value = defaultColors.title;
                                            if (titleColorHexInput) titleColorHexInput.value = defaultColors.title;
                                            if (textColorInput) textColorInput.value = defaultColors.text;
                                            if (textColorHexInput) textColorHexInput.value = defaultColors.text;

                                            if (customCss) {
                                              customCss.value = '';
                                              var oldStyleReset = document.getElementById('cosmdl-download-tips-custom-css');
                                              if (oldStyleReset) oldStyleReset.remove();
                                            }

                                            container.style.padding = '16px';
                                            container.style.borderRadius = '8px';
                                            container.style.border = '1px solid ' + defaultColors.border;
                                            container.style.backgroundColor = defaultColors.bg;
                                            container.style.color = defaultColors.text;

                                            var titleEl = container.querySelector('.cosmdl-section-title');
                                            if (titleEl) {
                                              titleEl.style.color = defaultColors.title;
                                            }

                                            var contentReset = container.querySelector('.cosmdl-download-tips-content');
                                            if (contentReset) {
                                              contentReset.style.color = defaultColors.text;
                                              contentReset.style.setProperty('color', defaultColors.text, 'important');
                                              var textElementsReset = contentReset.querySelectorAll('p, span, li, a, div');
                                              textElementsReset.forEach(function(el) {
                                                el.style.color = defaultColors.text;
                                                el.style.setProperty('color', defaultColors.text, 'important');
                                              });
                                            }

                                            borderColor = { value: defaultColors.border };
                                            bgColor = { value: defaultColors.bg };
                                            titleColor = { value: defaultColors.title };
                                            textColor = { value: defaultColors.text };

                                            return;
                                          }

                                          if(isThemeChange && theme && themeColors[theme]) {
                                            var preset = themeColors[theme];
                                            if(borderColorInput) borderColorInput.value = preset.border;
                                            if(borderColorHexInput) borderColorHexInput.value = preset.border;
                                            if(bgColorInput) bgColorInput.value = preset.bg;
                                            if(bgColorHexInput) bgColorHexInput.value = preset.bg;
                                            if(titleColorInput) titleColorInput.value = preset.text;
                                            if(titleColorHexInput) titleColorHexInput.value = preset.text;
                                            if(textColorInput) textColorInput.value = preset.text;
                                            if(textColorHexInput) textColorHexInput.value = preset.text;

                                            borderColor = borderColorHexInput || borderColorInput;
                                            bgColor = bgColorHexInput || bgColorInput;
                                            titleColor = titleColorHexInput || titleColorInput;
                                            textColor = textColorHexInput || textColorInput;
                                          }

                                          if (trimmedTitle === '' && trimmedHtml === '') {
                                            container.style.display = 'none';
                                            return;
                                          } else {
                                            container.style.display = '';
                                          }

                                          container.style.padding = '16px';
                                          container.style.borderRadius = '8px';

                                          if(theme && themeColors[theme]) {
                                            if(!borderColor || !borderColor.value) {
                                              borderColor = { value: themeColors[theme].border };
                                            }
                                            if(!bgColor || !bgColor.value) {
                                              bgColor = { value: themeColors[theme].bg };
                                            }
                                            if(!titleColor || !titleColor.value) {
                                              titleColor = { value: themeColors[theme].text };
                                            }
                                            if(!textColor || !textColor.value) {
                                              textColor = { value: themeColors[theme].text };
                                            }
                                          }

                                          container.style.border = '1px solid ' + (borderColor && borderColor.value ? borderColor.value : '#e5e7eb');
                                          container.style.backgroundColor = bgColor && bgColor.value ? bgColor.value : '#ffffff';
                                          container.style.color = textColor && textColor.value ? textColor.value : '#6b7280';

                                          var title = container.querySelector('.cosmdl-section-title');
                                          if (title) {
                                            if (trimmedTitle === '') {
                                              title.style.display = 'none';
                                              title.textContent = '';
                                            } else {
                                              title.style.display = '';
                                              title.textContent = rawTitle;
                                              if (titleColor && titleColor.value) {
                                                title.style.color = titleColor.value;
                                              }
                                            }
                                          }

                                          var content = container.querySelector('.cosmdl-download-tips-content');
                                          if (content) {
                                            if (trimmedHtml === '') {
                                              content.style.display = 'none';
                                              content.innerHTML = '';
                                            } else {
                                              content.style.display = '';
                                              content.innerHTML = rawHtml;
                                            }
                                          }
                                          
                                          // 修复：确保正文颜色正确应用到内容区域及其所有子元素
                                          if(content){
                                            // 直接设置内容区域颜色
                                            content.style.color = textColor && textColor.value ? textColor.value : '#6b7280';
                                            content.style.setProperty('color', textColor && textColor.value ? textColor.value : '#6b7280', 'important');
                                            
                                            // 强制设置所有文本子元素的颜色
                                            var textElements = content.querySelectorAll('p, span, li, a, div');
                                            textElements.forEach(function(el) {
                                              el.style.color = textColor && textColor.value ? textColor.value : '#6b7280';
                                              el.style.setProperty('color', textColor && textColor.value ? textColor.value : '#6b7280', 'important');
                                            });
                                          }

                                          if(customCss){
                                            var oldStyle = document.getElementById('cosmdl-download-tips-custom-css');
                                            if(oldStyle) oldStyle.remove();
                                            if(customCss.value.trim()){
                                              var styleElement = document.createElement('style');
                                              styleElement.id = 'cosmdl-download-tips-custom-css';
                                              styleElement.textContent = customCss.value;
                                              document.head.appendChild(styleElement);
                                            }
                                          }
                                        }

                                        function cosmdlBindDownloadTipsPreview(){
                                          var inputs = document.querySelectorAll(
                                            'input[name="cosmdl_options[download_tips_border_color]"]'
                                            + ', input[name="cosmdl_options[download_tips_border_color_hex]"]'
                                            + ', input[name="cosmdl_options[download_tips_bg_color]"]'
                                            + ', input[name="cosmdl_options[download_tips_bg_color_hex]"]'
                                            + ', input[name="cosmdl_options[download_tips_title_color]"]'
                                            + ', input[name="cosmdl_options[download_tips_title_color_hex]"]'
                                            + ', input[name="cosmdl_options[download_tips_text_color]"]'
                                            + ', input[name="cosmdl_options[download_tips_text_color_hex]"]'
                                            + ', input[name="cosmdl_options[download_tips_title]"]'
                                            + ', textarea[name="cosmdl_options[download_tips_html]"]'
                                            + ', textarea[name="cosmdl_options[download_tips_custom_css]"]'
                                            + ', select#download-tips-card-theme'
                                          );
                                          inputs.forEach(function(el){
                                            el.addEventListener('input', cosmdlUpdateDownloadTipsPreview);
                                            el.addEventListener('change', cosmdlUpdateDownloadTipsPreview);
                                          });
                                          cosmdlUpdateDownloadTipsPreview();
                                        }

                                        if(document.readyState === 'loading'){
                                          document.addEventListener('DOMContentLoaded', cosmdlBindDownloadTipsPreview);
                                        } else {
                                          cosmdlBindDownloadTipsPreview();
                                        }
                                      })();
                                    </script>
                                </div>
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'download_tips_title')); ?></div>
                                <div class="cosmdl-module__kv"><label>模块内容</label><?php $this->field_textarea(array('key'=>'download_tips_html')); ?></div>
                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[download_tips_card_theme]" id="download-tips-card-theme" class="cosmdl-card-theme-select">
                                        <option value="" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), ''); ?>>默认</option>
                                        <option value="blue" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="gray" <?php selected(($options['download_tips_card_theme'] ?? 'blue'), 'gray'); ?>>灰色主题</option>
                                    </select>
                                </div>
                                <div class="cosmdl-module__kv"><label>边框颜色</label><?php $this->field_color(array('key'=>'download_tips_border_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>背景颜色</label><?php $this->field_color(array('key'=>'download_tips_bg_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>标题颜色</label><?php $this->field_color(array('key'=>'download_tips_title_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>正文颜色</label><?php $this->field_color(array('key'=>'download_tips_text_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>自定义CSS</label><?php $this->field_textarea(array('key'=>'download_tips_custom_css', 'rows'=>6)); ?></div>
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="download_tips" />
                            </div>
                        </li>

                        <!-- 模块：站长声明 -->
                        <li class="cosmdl-module" data-module="owner_statement">
                            <div class="cosmdl-module__header" role="button" aria-expanded="false">
                                <span class="dashicons dashicons-menu cosmdl-module__handle" title="按住拖动排序"></span>
                                <span class="cosmdl-module__title">站长声明</span>
								<span class="cosmdl-module__toggle-icon"><?php echo wp_kses(cosmautdl_Icons::get('chevron-down'), self::allowed_icon_html()); ?></span>
                                <div class="cosmdl-module__tools">
                                    <?php $this->field_checkbox(array('key'=>'show_owner_statement')); ?>
                                </div>
                            </div>
                            <div class="cosmdl-module__body">
                                <div class="cosmdl-module__kv">
                                    <label>实时预览</label>
                                    <div class="cosmdl-owner-statement-preview cosmdl-preview-card" style="font-size:16px; line-height:1.6;">
                                        <div class="cosmdl-section cosmdl-owner-statement-section" id="cosmdl-owner-statement-preview" style="margin:0; padding:16px; border-radius:8px; border:1px solid #e5e7eb; background-color:#ffffff; color:#6b7280;">
                                            <h3 class="cosmdl-section-title">站长声明</h3>
                                            <div class="cosmdl-owner-statement-content">
                                                <p>本站资源多来源于公开网络，仅供学习交流。请尊重原创与版权，商业用途请至官方渠道获取授权。</p>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                      (function(){
                                        function cosmdlUpdateOwnerStatementPreview(event){
                                          var container = document.querySelector('.cosmdl-owner-statement-section') || document.getElementById('cosmdl-owner-statement-preview');
                                          if(!container) return;

                                          var themeSelect = document.getElementById('owner-statement-card-theme');
                                          var theme = themeSelect ? themeSelect.value : 'blue';
                                          
                                          // 预定义主题颜色
                                          var themeColors = {
                                            blue: {
                                              border: '#acd0f9',
                                              bg: '#e8f2fd',
                                              text: '#4285f4'
                                            },
                                            green: {
                                              border: '#a8dbc1',
                                              bg: '#e7f5ee',
                                              text: '#34a853'
                                            },
                                            purple: {
                                              border: '#e1bbfc',
                                              bg: '#f7edfe',
                                              text: '#a256e3'
                                            },
                                            orange: {
                                              border: '#f9d69f',
                                              bg: '#fdf3e4',
                                              text: '#fbbc05'
                                            },
                                            red: {
                                              border: '#ffb7b2',
                                              bg: '#fff5f4',
                                              text: '#ea4335'
                                            },
                                            gray: {
                                              border: '#d1d5db',
                                              bg: '#f5f6f8',
                                              text: '#64748b'
                                            }
                                          };

                                          var borderColorInput = document.querySelector('input[name="cosmdl_options[owner_statement_border_color]"]');
                                          var borderColorHexInput = document.querySelector('input[name="cosmdl_options[owner_statement_border_color_hex]"]');
                                          var bgColorInput = document.querySelector('input[name="cosmdl_options[owner_statement_bg_color]"]');
                                          var bgColorHexInput = document.querySelector('input[name="cosmdl_options[owner_statement_bg_color_hex]"]');
                                          var titleColorInput = document.querySelector('input[name="cosmdl_options[owner_statement_title_color]"]');
                                          var titleColorHexInput = document.querySelector('input[name="cosmdl_options[owner_statement_title_color_hex]"]');
                                          var textColorInput = document.querySelector('input[name="cosmdl_options[owner_statement_text_color]"]');
                                          var textColorHexInput = document.querySelector('input[name="cosmdl_options[owner_statement_text_color_hex]"]');

                                          var borderColor = borderColorHexInput || borderColorInput;
                                          var bgColor = bgColorHexInput || bgColorInput;
                                          var titleColor = titleColorHexInput || titleColorInput;
                                          var textColor = textColorHexInput || textColorInput;

                                          var titleInput = document.querySelector('input[name="cosmdl_options[owner_statement_title]"]');
                                          var htmlInput = document.querySelector('textarea[name="cosmdl_options[owner_statement_html]"]');
                                          var customCss = document.querySelector('textarea[name="cosmdl_options[owner_statement_custom_css]"]');

                                          var rawTitle = titleInput ? titleInput.value : '';
                                          var rawHtml = htmlInput ? htmlInput.value : '';
                                          var trimmedTitle = rawTitle.trim();
                                          var trimmedHtml = rawHtml.trim();

                                          container.style.padding = '16px';
                                          container.style.borderRadius = '8px';
                                          var defaultColors = {
                                            border: '#e5e7eb',
                                            bg: '#ffffff',
                                            title: '#111827',
                                            text: '#6b7280'
                                          };

                                          var isThemeChange = event && event.target && event.target.id === 'owner-statement-card-theme';

                                          if (isThemeChange && !theme) {
                                            if (borderColorInput) borderColorInput.value = defaultColors.border;
                                            if (borderColorHexInput) borderColorHexInput.value = defaultColors.border;
                                            if (bgColorInput) bgColorInput.value = defaultColors.bg;
                                            if (bgColorHexInput) bgColorHexInput.value = defaultColors.bg;
                                            if (titleColorInput) titleColorInput.value = defaultColors.title;
                                            if (titleColorHexInput) titleColorHexInput.value = defaultColors.title;
                                            if (textColorInput) textColorInput.value = defaultColors.text;
                                            if (textColorHexInput) textColorHexInput.value = defaultColors.text;

                                            if (customCss) {
                                              customCss.value = '';
                                              var oldStyleReset = document.getElementById('cosmdl-owner-statement-custom-css');
                                              if (oldStyleReset) oldStyleReset.remove();
                                            }

                                            container.style.border = '1px solid ' + defaultColors.border;
                                            container.style.backgroundColor = defaultColors.bg;
                                            container.style.color = defaultColors.text;

                                            var titleEl = container.querySelector('.cosmdl-section-title');
                                            if (titleEl) {
                                              titleEl.style.color = defaultColors.title;
                                            }

                                            var contentReset = container.querySelector('.cosmdl-owner-statement-content');
                                            if (contentReset) {
                                              contentReset.style.color = defaultColors.text;
                                              contentReset.style.setProperty('color', defaultColors.text, 'important');
                                              var textElementsReset = contentReset.querySelectorAll('p, span, li, a, div');
                                              textElementsReset.forEach(function(el) {
                                                el.style.color = defaultColors.text;
                                                el.style.setProperty('color', defaultColors.text, 'important');
                                              });
                                            }

                                            borderColor = { value: defaultColors.border };
                                            bgColor = { value: defaultColors.bg };
                                            titleColor = { value: defaultColors.title };
                                            textColor = { value: defaultColors.text };

                                            return;
                                          }

                                          if(isThemeChange && theme && themeColors[theme]) {
                                            var preset = themeColors[theme];
                                            if(borderColorInput) borderColorInput.value = preset.border;
                                            if(borderColorHexInput) borderColorHexInput.value = preset.border;
                                            if(bgColorInput) bgColorInput.value = preset.bg;
                                            if(bgColorHexInput) bgColorHexInput.value = preset.bg;
                                            if(titleColorInput) titleColorInput.value = preset.text;
                                            if(titleColorHexInput) titleColorHexInput.value = preset.text;
                                            if(textColorInput) textColorInput.value = preset.text;
                                            if(textColorHexInput) textColorHexInput.value = preset.text;

                                            borderColor = borderColorHexInput || borderColorInput;
                                            bgColor = bgColorHexInput || bgColorInput;
                                            titleColor = titleColorHexInput || titleColorInput;
                                            textColor = textColorHexInput || textColorInput;
                                          }

                                          if(theme && themeColors[theme]) {
                                            if(!borderColor || !borderColor.value) {
                                              borderColor = { value: themeColors[theme].border };
                                            }
                                            if(!bgColor || !bgColor.value) {
                                              bgColor = { value: themeColors[theme].bg };
                                            }
                                            if(!titleColor || !titleColor.value) {
                                              titleColor = { value: themeColors[theme].text };
                                            }
                                            if(!textColor || !textColor.value) {
                                              textColor = { value: themeColors[theme].text };
                                            }
                                          }

                                          if (trimmedTitle === '' && trimmedHtml === '') {
                                            container.style.display = 'none';
                                            return;
                                          } else {
                                            container.style.display = '';
                                          }

                                          container.style.border = '1px solid ' + (borderColor && borderColor.value ? borderColor.value : '#e5e7eb');
                                          container.style.backgroundColor = bgColor && bgColor.value ? bgColor.value : '#ffffff';
                                          container.style.color = textColor && textColor.value ? textColor.value : '#6b7280';

                                          var title = container.querySelector('.cosmdl-section-title');
                                          if (title) {
                                            if (trimmedTitle === '') {
                                              title.style.display = 'none';
                                              title.textContent = '';
                                            } else {
                                              title.style.display = '';
                                              title.textContent = rawTitle;
                                              if (titleColor && titleColor.value) {
                                                title.style.color = titleColor.value;
                                              }
                                            }
                                          }

                                          var content = container.querySelector('.cosmdl-owner-statement-content');
                                          if (content) {
                                            if (trimmedHtml === '') {
                                              content.style.display = 'none';
                                              content.innerHTML = '';
                                            } else {
                                              content.style.display = '';
                                              content.innerHTML = rawHtml;
                                            }
                                          }

                                          // 修复：确保正文颜色正确应用到内容区域及其所有子元素
                                          if(content){
                                            // 直接设置内容区域颜色
                                            content.style.color = textColor && textColor.value ? textColor.value : '#6b7280';
                                            content.style.setProperty('color', textColor && textColor.value ? textColor.value : '#6b7280', 'important');

                                            // 强制设置所有文本子元素的颜色
                                            var textElements = content.querySelectorAll('p, span, li, a, div');
                                            textElements.forEach(function(el) {
                                              el.style.color = textColor && textColor.value ? textColor.value : '#6b7280';
                                              el.style.setProperty('color', textColor && textColor.value ? textColor.value : '#6b7280', 'important');
                                            });
                                          }

                                          if(customCss){
                                            var oldStyle = document.getElementById('cosmdl-owner-statement-custom-css');
                                            if(oldStyle) oldStyle.remove();
                                            if(customCss.value.trim()){
                                              var styleElement = document.createElement('style');
                                              styleElement.id = 'cosmdl-owner-statement-custom-css';
                                              styleElement.textContent = customCss.value;
                                              document.head.appendChild(styleElement);
                                            }
                                          }
                                        }

                                        function cosmdlBindOwnerStatementPreview(){
                                          var inputs = document.querySelectorAll(
                                            'input[name="cosmdl_options[owner_statement_border_color]"]'
                                            + ', input[name="cosmdl_options[owner_statement_border_color_hex]"]'
                                            + ', input[name="cosmdl_options[owner_statement_bg_color]"]'
                                            + ', input[name="cosmdl_options[owner_statement_bg_color_hex]"]'
                                            + ', input[name="cosmdl_options[owner_statement_title_color]"]'
                                            + ', input[name="cosmdl_options[owner_statement_title_color_hex]"]'
                                            + ', input[name="cosmdl_options[owner_statement_text_color]"]'
                                            + ', input[name="cosmdl_options[owner_statement_text_color_hex]"]'
                                            + ', input[name="cosmdl_options[owner_statement_title]"]'
                                            + ', textarea[name="cosmdl_options[owner_statement_html]"]'
                                            + ', textarea[name="cosmdl_options[owner_statement_custom_css]"]'
                                            + ', select#owner-statement-card-theme'
                                          );
                                          inputs.forEach(function(el){
                                            el.addEventListener('input', cosmdlUpdateOwnerStatementPreview);
                                            el.addEventListener('change', cosmdlUpdateOwnerStatementPreview);
                                          });
                                          cosmdlUpdateOwnerStatementPreview();
                                        }

                                        if(document.readyState === 'loading'){
                                          document.addEventListener('DOMContentLoaded', cosmdlBindOwnerStatementPreview);
                                        } else {
                                          cosmdlBindOwnerStatementPreview();
                                        }
                                      })();
                                    </script>
                                </div>
                                <div class="cosmdl-module__kv"><label>模块标题</label><?php $this->field_text(array('key'=>'owner_statement_title')); ?></div>
                                <div class="cosmdl-module__kv"><label>模块内容</label><?php $this->field_textarea(array('key'=>'owner_statement_html')); ?></div>
                                <div class="cosmdl-module__kv"><label>卡片主题色</label>
                                    <select name="cosmdl_options[owner_statement_card_theme]" id="owner-statement-card-theme" class="cosmdl-card-theme-select">
                                        <option value="" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), ''); ?>>默认</option>
                                        <option value="blue" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'blue'); ?>>蓝色主题</option>
                                        <option value="green" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'green'); ?>>绿色主题</option>
                                        <option value="purple" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'purple'); ?>>紫色主题</option>
                                        <option value="orange" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'orange'); ?>>橙色主题</option>
                                        <option value="red" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'red'); ?>>红色主题</option>
                                        <option value="gray" <?php selected(($options['owner_statement_card_theme'] ?? 'blue'), 'gray'); ?>>灰色主题</option>
                                    </select>
                                </div>
                                <div class="cosmdl-module__kv"><label>边框颜色</label><?php $this->field_color(array('key'=>'owner_statement_border_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>背景颜色</label><?php $this->field_color(array('key'=>'owner_statement_bg_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>标题颜色</label><?php $this->field_color(array('key'=>'owner_statement_title_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>正文颜色</label><?php $this->field_color(array('key'=>'owner_statement_text_color')); ?></div>
                                <div class="cosmdl-module__kv"><label>自定义CSS</label><?php $this->field_textarea(array('key'=>'owner_statement_custom_css', 'rows'=>6)); ?></div>
                                <input type="hidden" name="cosmdl_options[download_modules_order][]" value="owner_statement" />
                            </div>
                        </li>


                    </ul>
                </div>





                <!-- 扫码设置 -->
                <div class="drawer-content" id="qr-code-content" style="display:none;">
                    <h2><?php echo esc_html__('扫码设置','cosmautdl'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">显示二维码区块</th>
                            <td><?php $this->field_checkbox(array('key'=>'show_qr_block')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('扫码解锁模式','cosmautdl'); ?></th>
                            <td>
                                <?php
                                // 中文注释：扫码解锁模式选择，决定扫码后如何验证与解锁
                                $this->field_select(array(
                                    'key' => 'qr_unlock_mode',
                                    'options' => array(
                                        'static' => '静态扫码解锁（不校验关注/进群，仅记录解锁）',
                                        'wechat' => '微信公众号关注解锁（通过微信接口校验是否已关注）',
                                        'group'  => '微信扫码进群解锁（适合配合微信群/社群二维码）',
                                    ),
                                ));
                                ?>
                                <p class="description">
                                    <?php echo esc_html__('选择不同模式会影响后台如何校验用户是否完成扫码操作：', 'cosmautdl'); ?>
                                </p>
                                <ul style="margin:4px 0 0 1.5em;list-style:disc;">
                                    <li><?php echo esc_html__('静态扫码解锁：用户在微信中打开扫码页面即视为完成解锁，适合简单引流或测试环境。', 'cosmautdl'); ?></li>
                                    <li><?php echo esc_html__('微信公众号关注解锁：仅当用户已关注指定公众号时才会解锁，依赖微信接口校验。', 'cosmautdl'); ?></li>
                                    <li><?php echo esc_html__('微信扫码进群解锁：适合配合微信群/社群二维码使用，插件只负责“扫码→解锁”，无法校验是否真正完成进群动作。', 'cosmautdl'); ?></li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('二维码图片地址','cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_text(array('key'=>'qr_image_url')); ?>
                                <p class="description"><?php echo esc_html__('静态模式：输入二维码图片的完整URL地址','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('微信公众号AppID','cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_text(array('key'=>'wechat_appid')); ?>
                                <p class="description"><?php echo esc_html__('微信公众号的AppID，用于生成关注二维码','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('微信公众号AppSecret','cosmautdl'); ?></th>
                            <td>
                                <input type="password" name="cosmdl_options[wechat_appsecret]" value="<?php echo esc_attr($options['wechat_appsecret']); ?>" style="min-width:320px" />
                                <p class="description"><?php echo esc_html__('微信公众号的AppSecret，用于验证用户关注状态','cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('关注提示文案','cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_text(array('key'=>'qr_follow_text')); ?>
                                <p class="description"><?php echo esc_html__('用户扫码后显示的关注提示文案','cosmautdl'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3><?php echo esc_html__('使用说明（推荐生产环境配置）','cosmautdl'); ?></h3>
                    <p class="description">
                        <?php echo esc_html__('要在真实环境中稳定使用扫码解锁功能，建议按照以下步骤完成配置：', 'cosmautdl'); ?>
                    </p>
                    <ol style="margin:8px 0 0 1.5em;">
                        <li><?php echo esc_html__('在微信公众平台/开放平台中，将本站域名（含 HTTPS）添加到“网页授权域名”或相关白名单中；', 'cosmautdl'); ?></li>
                        <li><?php echo esc_html__('在本页正确填写公众号的 AppID 和 AppSecret，并保存设置；', 'cosmautdl'); ?></li>
                        <li><?php echo esc_html__('在文章编辑页的“下载设置”中，勾选需要扫码解锁的网盘项（“扫码解锁”开关）；', 'cosmautdl'); ?></li>
                        <li><?php echo esc_html__('确保站点已部署到有公网 IP 与 HTTPS 证书的正式域名上（本地开发环境仅用于功能联调与样式确认，无法完成真实的关注校验）；', 'cosmautdl'); ?></li>
                        <li><?php echo esc_html__('访问前台下载页，确认网盘按钮处于锁定状态，并能正常显示扫码二维码及解锁提示。', 'cosmautdl'); ?></li>
                    </ol>
                    <p class="description" style="margin-top:8px;">
                        <?php echo esc_html__('提示：在“微信公众号关注解锁”模式下，插件会通过微信官方接口校验是否已关注；在“静态扫码解锁”和“微信扫码进群解锁”模式下，插件只负责记录扫码行为，不校验用户是否真正完成关注或进群操作。', 'cosmautdl'); ?>
                    </p>
                </div>

                <!-- 下载统计（管理员可见） -->
                <div class="drawer-content" id="stats-content" style="display:none;">
                    <h2><?php echo esc_html__('下载统计','cosmautdl'); ?></h2>
                    <p class="description"><?php echo esc_html__('列出所有已配置下载附件的文章与页面，显示文件名、发布日期、文件大小、已上传网盘与总下载次数；支持按文件名、发布日期、文件大小、总下载次数排序，并可选择每页显示数量（50/100/200）。','cosmautdl'); ?></p>
                    <table class="form-table" style="margin-top:10px;">
                        <tr>
                            <th scope="row"><?php echo esc_html__('IP 归属地显示', 'cosmautdl'); ?></th>
                            <td>
                                <?php $this->field_checkbox(array('key' => 'stats_ip_geo', 'label' => esc_html__('在“各网盘下载次数”的详情表格中显示 IP 归属地', 'cosmautdl'))); ?>
                                <p class="description"><?php echo esc_html__('启用后，插件会在后台通过第三方 IP 解析服务查询归属地，并做缓存；若你的站点有隐私合规要求，可关闭此功能。', 'cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('归属地查询服务', 'cosmautdl'); ?></th>
                            <td>
                                <?php
                                $this->field_select(array(
                                    'key' => 'stats_ip_geo_provider',
                                    'options' => array(
                                        'ipapi'  => 'ipapi.co（推荐，HTTPS）',
                                        'ip-api' => 'ip-api.com（HTTP，可能有频率限制）',
                                        'ipinfo' => 'ipinfo.io（HTTPS，可能有频率限制）',
                                    ),
                                ));
                                ?>
                                <p class="description"><?php echo esc_html__('若某个服务在你的服务器环境不可用，可切换到其他服务。', 'cosmautdl'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('归属地缓存时间（小时）', 'cosmautdl'); ?></th>
                            <td>
                                <input type="number" min="1" max="720" step="1" name="cosmdl_options[stats_ip_geo_cache_hours]" value="<?php echo esc_attr(isset($options['stats_ip_geo_cache_hours']) ? intval($options['stats_ip_geo_cache_hours']) : 168); ?>" style="width:120px;" />
                                <p class="description"><?php echo esc_html__('默认 168 小时（7 天）。缓存越长越省请求，但归属地变化会更新更慢。', 'cosmautdl'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div style="margin:10px 0">
                        <?php
                        // 自动刷新数据缓存，不再需要手动按钮
                        ?>
                    </div>
                    <?php
                    if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')){
                        echo '<div class="notice notice-warning"><p>'.esc_html__('需要管理员权限才能查看下载统计。','cosmautdl').'</p></div>';
                    } else {
                        global $wpdb;
                        $clicks_table = $wpdb->prefix . 'cosmdl_clicks';
                        // 默认开启刷新详情缓存，确保每次加载都是最新的
                        $flush_per_drive = true; 

                        // 自动清理列表缓存（移除手动刷新判断逻辑）
                        delete_transient('cosmdl_admin_stats_rows_v3');
                        // 同时清理旧版本缓存以防万一
                        delete_transient('cosmdl_admin_stats_rows_v2');

					$sort_raw  = filter_input(INPUT_GET, 'cosmdl_stats_sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
					$order_raw = filter_input(INPUT_GET, 'cosmdl_stats_order', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
					$ppp_raw   = filter_input(INPUT_GET, 'cosmdl_stats_ppp', FILTER_SANITIZE_NUMBER_INT);
					$page_raw  = filter_input(INPUT_GET, 'cosmdl_stats_page', FILTER_SANITIZE_NUMBER_INT);
					$nonce_raw = filter_input(INPUT_GET, 'cosmdl_stats_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

					$has_stats_args = ($sort_raw !== null || $order_raw !== null || $ppp_raw !== null || $page_raw !== null);
					$stats_nonce_ok = true;
					if ($has_stats_args) {
						$stats_nonce_ok = false;
						$stats_nonce = is_string($nonce_raw) ? sanitize_text_field($nonce_raw) : '';
						if ($stats_nonce !== '' && wp_verify_nonce($stats_nonce, 'cosmdl_stats')) {
							$stats_nonce_ok = true;
						}
					}

					$sort = (is_string($sort_raw) && $stats_nonce_ok) ? sanitize_key($sort_raw) : 'count';
					if (!in_array($sort, array('name', 'date', 'size', 'count'), true)) { $sort = 'count'; }

					$order = (is_string($order_raw) && $stats_nonce_ok) ? strtoupper(sanitize_text_field($order_raw)) : 'DESC';
					if (!in_array($order, array('ASC','DESC'), true)) { $order = 'DESC'; }

					$allowed_ppp = array(50,100,200);
					$user_default_ppp = intval(get_user_meta(get_current_user_id(), 'cosmdl_stats_ppp', true));
					if (!$user_default_ppp || !in_array($user_default_ppp, $allowed_ppp, true)) { $user_default_ppp = 50; }
					$ppp = ($ppp_raw !== null && $stats_nonce_ok) ? absint($ppp_raw) : $user_default_ppp;
					if (!in_array($ppp, $allowed_ppp, true)) { $ppp = 50; }
					if ($ppp_raw !== null && $stats_nonce_ok) {
						update_user_meta(get_current_user_id(), 'cosmdl_stats_ppp', $ppp);
					}

					$page = ($page_raw !== null && $stats_nonce_ok) ? max(1, absint($page_raw)) : 1;

                        // 读取网盘管理配置（用于识别自定义网盘及标签）
                        $opts = $this->get_options();
                        $drive_management = isset($opts['drive_management']) && is_array($opts['drive_management']) ? $opts['drive_management'] : array();

                        /**
                         * 辅助函数：根据网盘 key 与附件索引生成对应的下载链接字段名
                         * 说明：
                         * - 附件索引 1 使用前缀 cosmdl_；附件索引 2-6 使用前缀 cosmdl{N}_
                         * - 标准网盘（1-16）：cosmdl_downurl_{ID}
                         * - 自定义网盘：cosmdl_downurl_custom_{ID}
                         */
                        function cosmdl_admin_field_url_for_drive($drive_key, $attach_index, $is_custom = false){
                            $attach_index = intval($attach_index);
                            $prefix = ($attach_index===1) ? 'cosmdl_' : ('cosmdl'.$attach_index.'_');
                            
                            if ($is_custom) {
                                // 去除 custom_ 前缀以匹配 meta box 保存格式
                                $clean_key = preg_replace('/^custom_/', '', $drive_key);
                                return $prefix . 'downurl_custom_' . $clean_key;
                            } else {
                                return $prefix . 'downurl_' . $drive_key;
                            }
                        }

                        /**
                         * 辅助函数：判断某篇文章/页面是否存在任意下载链接
                         * 范围：附件 1-6；包含默认网盘与“网盘管理”中启用的自定义网盘。
                         * 用途：用于筛选统计列表的候选文章，避免无下载链接的内容进入统计。
                         */
                        function cosmdl_admin_post_has_any_link($pid, $drive_management){
                            $pid = intval($pid);
                            for($i=1;$i<=6;$i++){
                                foreach($drive_management as $dk => $dv){
                                    $is_custom = (isset($dv['is_custom']) && $dv['is_custom']==='yes');
                                    $key = cosmdl_admin_field_url_for_drive($dk, $i, $is_custom);
                                    $val = get_post_meta($pid, $key, true);
                                    if (!empty($val)) return true;
                                }
                            }
                            return false;
                        }

                        /**
                         * 工具函数：解析文件大小到字节数
                         * 支持：
                         * - 新版“size + unit(KB/MB/GB)”字段（优先使用 unit 决定倍数）
                         * - 旧版混合格式（如 123M、1.5G、100MB 等），自动识别单位
                         * 返回：整数（字节数），无效输入返回 0
                         */
                        function cosmdl_admin_size_to_bytes($size_str, $unit_meta = ''){
                            $unit_meta = strtoupper(trim($unit_meta));
                            if ($unit_meta){
                                $num = floatval(preg_replace('/[^0-9.]/','',$size_str));
                                if ($num <= 0) return 0;
                                switch($unit_meta){
                                    case 'KB': return intval($num * 1024);
                                    case 'MB': return intval($num * 1048576);
                                    case 'GB': return intval($num * 1073741824);
                                    default: return intval($num);
                                }
                            }
                            // 兼容旧格式：如 "123M"、"1.5G"、"100MB" 等
                            $s = trim($size_str); if ($s==='') return 0;
                            if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KkMmGg]?[Bb]?)/', $s, $m)) return 0;
                            $num = floatval($m[1]); $u = strtoupper($m[2]);
                            if ($u==='K' || $u==='KB') return intval($num*1024);
                            if ($u==='M' || $u==='MB') return intval($num*1048576);
                            if ($u==='G' || $u==='GB') return intval($num*1073741824);
                            return intval($num);
                        }

                        /**
                         * 工具函数：格式化字节值为带单位的可读字符串
                         * 逻辑：
                         * - 若存在标准化单位（KB/MB/GB），优先展示“size + unit”；当 size 已含单位时保留原文
                         * - 否则根据字节值智能选择 B/KB/MB/GB 并保留两位小数
                         */
                        function cosmdl_admin_format_size($size_str, $size_unit, $bytes){
                            $u = strtoupper(trim($size_unit));
                            // 优先使用标准化的 size + unit（避免重复单位）
                            if ($size_str !== '' && in_array($u, array('KB','MB','GB'), true)){
                                // 若 size 是纯数字则追加单位，否则保留原样（兼容 "100MB" 这类旧数据）
                                if (preg_match('/^\s*[0-9]+(?:\.[0-9]+)?\s*$/', $size_str)){
                                    return trim($size_str) . ' ' . $u;
                                }
                                return trim($size_str);
                            }
                            // 回退：根据字节值智能选择单位
                            $b = intval($bytes);
                            if ($b <= 0){ return '—'; }
                            if ($b >= 1073741824){ return round($b/1073741824, 2) . ' GB'; }
                            if ($b >= 1048576){   return round($b/1048576,   2) . ' MB'; }
                            if ($b >= 1024){      return round($b/1024,      2) . ' KB'; }
                            return $b . ' B';
                        }

                        // 轻量级缓存：统计列表基础数据（不包含排序与分页），TTL 60s
                        // 说明：仅缓存行数据构建结果；排序与分页在内存中完成，确保刷新后立即生效
                        $rows = get_transient('cosmdl_admin_stats_rows_v3');
                        if ($rows === false || !is_array($rows)){
                            // 查询全部已发布文章/页面，再在 PHP 层筛选（确保覆盖自定义网盘与附件 2-6）
                            // 统计范围：post + page，避免部分资源配置在页面时被遗漏
                            $q = new WP_Query(array(
                                'post_type' => array('post','page'),
                                'post_status' => 'publish',
                                'posts_per_page' => -1,
                                'fields' => 'ids',
                            ));

                            $rows = array();
                            if ($q->have_posts()){
                                // 批量获取所有文章的下载统计数据，按 post_id, attach_id, type 分组
                                // 修复：N+1 查询问题，并确保总数仅包含有效网盘
                                $post_ids = array_map('intval', $q->posts);
                                $download_stats = array();
                                if (!empty($post_ids)) {
							$stats_cache_key = 'cosmdl_admin_download_stats_' . md5(implode(',', $post_ids));
							$download_stats = wp_cache_get($stats_cache_key, 'cosmautdl');
							if ($download_stats === false) {
								$args = array_merge($post_ids, array(''));
								$download_stats = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										'SELECT post_id, attach_id, type, COUNT(*) as count FROM ' . $wpdb->prefix . 'cosmdl_clicks WHERE post_id IN (' . implode(',', array_fill(0, count($post_ids), '%d')) . ') AND type != %s GROUP BY post_id, attach_id, type', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
										...$args
									),
									ARRAY_A
								);
								wp_cache_set($stats_cache_key, $download_stats, 'cosmautdl', 60);
							}
						}
                                
                                // 构建三维映射表: [post_id][attach_id][type] = count
                                $stats_map = array();
                                foreach ($download_stats as $item) {
                                    $pid = $item['post_id'];
                                    $aid = isset($item['attach_id']) ? intval($item['attach_id']) : 1;
                                    $type = strtolower($item['type']);
                                    if (!isset($stats_map[$pid])) { $stats_map[$pid] = array(); }
                                    if (!isset($stats_map[$pid][$aid])) { $stats_map[$pid][$aid] = array(); }
                                    // 累加（防止同一 type 不同大小写出现多条）
                                    if (!isset($stats_map[$pid][$aid][$type])) { $stats_map[$pid][$aid][$type] = 0; }
                                    $stats_map[$pid][$aid][$type] += intval($item['count']);
                                }
                                
                                // 循环处理每篇文章
                                foreach($q->posts as $pid){
                                    if (!cosmdl_admin_post_has_any_link($pid, $drive_management)) continue;
                                    $post_title = get_the_title($pid);
                                    
                                    // 将每个"存在下载链接"的附件（1-6）单独作为一行
                                    for($attach=1; $attach<=6; $attach++){
                                        // 获取当前附件的所有网盘统计数据
                                        $current_stats = isset($stats_map[$pid][$attach]) ? $stats_map[$pid][$attach] : array();

                                        // 检查该附件是否存在任意下载链接（默认网盘 + 自定义网盘）
                                        $has_any = false;
                                        foreach($drive_management as $dk => $dv){
                                            $is_custom = (isset($dv['is_custom']) && $dv['is_custom']==='yes');
                                            $key = cosmdl_admin_field_url_for_drive($dk, $attach, $is_custom);
                                            $val = get_post_meta($pid, $key, true);
                                            if (!empty($val)) { $has_any = true; break; }
                                        }

                                        if (!$has_any) { continue; }

                                        // 读取该附件的展示信息（名称/大小/单位/更新/作者）
                                        $name_key   = ($attach===1) ? 'cosmdl_name'       : ('cosmdl'.$attach.'_name');
                                        $size_key   = ($attach===1) ? 'cosmdl_size'       : ('cosmdl'.$attach.'_size');
                                        $unit_key   = ($attach===1) ? 'cosmdl_size_unit'  : ('cosmdl'.$attach.'_size_unit');
                                        $date_key   = ($attach===1) ? 'cosmdl_date'       : ('cosmdl'.$attach.'_date');
                                        $author_key = ($attach===1) ? 'cosmdl_author'     : ('cosmdl'.$attach.'_author');

                                        $name  = get_post_meta($pid, $name_key, true);
                                        $size  = get_post_meta($pid, $size_key, true);
                                        $unit  = get_post_meta($pid, $unit_key, true);
                                        $date  = get_post_meta($pid, $date_key, true);
                                        if (!$date){ $date = get_the_date('Y-m-d', $pid); }

                                        // 收集该附件已上传的网盘（显示名称，与文件树页面逻辑一致）
                                        $uploaded = array();
                                        // 计算各网盘的实际下载数（兼容 Key 和 Alias）
                                        $calculated_counts = array();
                                        
                                        foreach($drive_management as $dk => $dv){
                                            $is_custom = (isset($dv['is_custom']) && $dv['is_custom'] === 'yes');
                                            $url_key = cosmdl_admin_field_url_for_drive($dk, $attach, $is_custom);
                                            $val = get_post_meta($pid, $url_key, true);
                                            if (!empty($val)){
                                                // 确定显示名称：优先使用设置的label，确保正确显示网盘名称
                                                $display_name = isset($dv['label']) && $dv['label'] !== '' ? $dv['label'] : $dk;
                                                
                                                // 确定统计ID：优先 alias (兼容旧版统计)，否则 key
                                                $effective_id = isset($dv['alias']) && $dv['alias'] !== '' ? $dv['alias'] : $dk;
                                                $effective_id = preg_replace('/[^a-z0-9\-]/','', strtolower($effective_id));

                                                $uploaded[$effective_id] = $display_name; 
                                                
                                                // 计算下载数：Key + Alias
                                                $dk_clean = strtolower(trim($dk));
                                                $alias_clean = isset($dv['alias']) ? strtolower(trim($dv['alias'])) : '';
                                                
                                                $count = 0;
                                                // 加上 Key 的统计
                                                if (isset($current_stats[$dk_clean])) {
                                                    $count += intval($current_stats[$dk_clean]);
                                                }
                                                // 加上 Alias 的统计（如果 Alias 存在且与 Key 不同）
                                                if ($alias_clean !== '' && $alias_clean !== $dk_clean && isset($current_stats[$alias_clean])) {
                                                    $count += intval($current_stats[$alias_clean]);
                                                }
                                                
                                                $calculated_counts[$effective_id] = $count;
                                            }
                                        }

                                        // 计算总下载数：仅累加当前显示的网盘
                                        $post_count = 0;
                                        foreach($calculated_counts as $eff_id => $cnt){
                                            $post_count += $cnt;
                                        }

                                        // 构造一行（附件为独立资源项）
                                        $rows[] = array(
                                            'pid' => $pid,
                                            'attach' => $attach,
                                            'title' => $post_title,
                                            'name' => $name ? $name : $post_title,
                                            'size' => $size,
                                            'size_unit' => strtoupper(trim($unit)),
                                            'size_bytes' => cosmdl_admin_size_to_bytes($size, $unit),
                                            'date' => $date,
                                            'count'=> $post_count, // 修正后的总数
                                            'uploaded' => $uploaded,
                                            'drive_counts' => $current_stats, // 原始统计数据（供详情展示）
                                            'calculated_counts' => $calculated_counts, // 修正后的各网盘统计
                                            'sort_name' => strtolower($name ? $name : $post_title),
                                            // 优先使用文章发布时间排序；若存在附件级“更新”日期则尝试解析
                                            'sort_date' => $date ? strtotime($date . ' 00:00:00') : strtotime(get_the_date('Y-m-d H:i:s', $pid)),
                                        );
                                    }
                                }
                            }
                            wp_reset_postdata();
                            // 写入缓存
                            set_transient('cosmdl_admin_stats_rows_v3', $rows, 60);
                        }

                        // 若刷新标记开启，则清理每篇文章的“各网盘下载次数”缓存（轻量清理）
                        if ($flush_per_drive && !empty($rows)){
                            foreach($rows as $r){
                                if (isset($r['pid'])){
                                    delete_transient('cosmdl_admin_stats_per_' . intval($r['pid']));
                                }
                            }
                        }

                        // 排序：支持 name/date/size/count，并遵循 ASC/DESC 切换
                        if (!empty($rows)){
                            if ($sort === 'name'){
                                usort($rows, function($a,$b){ return $a['sort_name'] <=> $b['sort_name']; });
                            } elseif ($sort === 'date'){
                                usort($rows, function($a,$b){ return $a['sort_date'] <=> $b['sort_date']; });
                            } elseif ($sort === 'size'){
                                usort($rows, function($a,$b){ return $a['size_bytes'] <=> $b['size_bytes']; });
                            } else { // count
                                usort($rows, function($a,$b){ return $a['count'] <=> $b['count']; });
                            }
                            if ($order === 'DESC'){ $rows = array_reverse($rows); }
                        }

                        // 计算表头链接（点击切换升降序，并保持在“下载统计”抽屉）
                        $base_url = admin_url('admin.php?page=cosmdl-settings');
                        /**
                         * 辅助函数：生成表头排序链接
                         * 行为：在当前排序的基础上切换 ASC/DESC；保留每页数量与页码；强制回到 stats 抽屉。
                         */
						function cosmdl_admin_sort_link($base, $key, $return_html = false){
							$current_sort_raw = filter_input(INPUT_GET, 'cosmdl_stats_sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
							$current_sort = is_string($current_sort_raw) ? sanitize_key($current_sort_raw) : 'count';
							if (!in_array($current_sort, array('name', 'date', 'size', 'count'), true)) { $current_sort = 'count'; }

							$current_order_raw = filter_input(INPUT_GET, 'cosmdl_stats_order', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
							$current_order = is_string($current_order_raw) ? strtoupper(sanitize_text_field($current_order_raw)) : 'DESC';
							$next_order = ($current_sort === $key && $current_order === 'ASC') ? 'DESC' : 'ASC';
							$stats_nonce = wp_create_nonce('cosmdl_stats');
							$url = add_query_arg(array('cosmdl_stats_sort'=>$key,'cosmdl_stats_order'=>$next_order,'cosmdl_stats_nonce'=>$stats_nonce), $base);
							$url = add_query_arg(array('cosmdl_active_tab'=>'stats'), $url);
							// 保持当前每页数量与页码
							$ppp_raw = filter_input(INPUT_GET, 'cosmdl_stats_ppp', FILTER_SANITIZE_NUMBER_INT);
							$page_raw = filter_input(INPUT_GET, 'cosmdl_stats_page', FILTER_SANITIZE_NUMBER_INT);
							if ($ppp_raw !== null){ $url = add_query_arg(array('cosmdl_stats_ppp'=>absint($ppp_raw)), $url); }
							if ($page_raw !== null){ $url = add_query_arg(array('cosmdl_stats_page'=>absint($page_raw)), $url); }
							
							// 如果需要返回HTML链接（包含排序箭头）
							if ($return_html) {
								$arrow = '';
								if ($current_sort === $key) {
                                    $arrow = ($current_order === 'ASC') ? ' ↑' : ' ↓';
                                }
                                return '<a href="' . esc_url($url) . '" class="cosmdl-sort-link" title="' . esc_attr__('点击排序', 'cosmautdl') . '">' . $arrow . '</a>';
                            }
                            
                            return $url;
                        }
                    ?>
                    <div class="p-block mt10">
                        <table class="form-table cosmdl-stats-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="cosmdl-col-auto cosmdl-sortable">
                                        <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(cosmdl_admin_sort_link($base_url,'name')); ?>'">
                                            <?php echo esc_html__('文件名','cosmautdl'); ?><?php echo wp_kses(cosmdl_admin_sort_link($base_url,'name', true), array('a' => array('href' => true, 'class' => true, 'title' => true))); ?>
                                        </button>
                                    </th>
                                    <th class="cosmdl-col-auto cosmdl-sortable">
                                        <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(cosmdl_admin_sort_link($base_url,'date')); ?>'">
                                            <?php echo esc_html__('发布日期','cosmautdl'); ?><?php echo wp_kses(cosmdl_admin_sort_link($base_url,'date', true), array('a' => array('href' => true, 'class' => true, 'title' => true))); ?>
                                        </button>
                                    </th>
                                    <th class="cosmdl-col-auto cosmdl-sortable">
                                        <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(cosmdl_admin_sort_link($base_url,'size')); ?>'">
                                            <?php echo esc_html__('文件大小','cosmautdl'); ?><?php echo wp_kses(cosmdl_admin_sort_link($base_url,'size', true), array('a' => array('href' => true, 'class' => true, 'title' => true))); ?>
                                        </button>
                                    </th>
                                    <th class="cosmdl-col-drives"><?php echo esc_html__('已上传网盘','cosmautdl'); ?></th>
                                    <th class="cosmdl-col-auto cosmdl-sortable">
                                        <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(cosmdl_admin_sort_link($base_url,'count')); ?>'">
                                            <?php echo esc_html__('总下载','cosmautdl'); ?><?php echo wp_kses(cosmdl_admin_sort_link($base_url,'count', true), array('a' => array('href' => true, 'class' => true, 'title' => true))); ?>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // 分页切片
                                $total = count($rows);
                                $pages = max(1, ceil($total / $ppp));
                                if ($page > $pages) { $page = $pages; }
                                $offset = ($page - 1) * $ppp;
                                $paged_rows = array_slice($rows, $offset, $ppp);
                                ?>
                                <?php if (!empty($paged_rows)) : foreach($paged_rows as $r) : ?>
                                    <tr class="stats-row" data-pid="<?php echo intval($r['pid']); ?>">
                                        <?php
                                        // 多附件：为下载链接附加 attach 参数（与前端文件树一致）
                                        $dl = cosmdl_route_url('download', intval($r['pid']));
                                        if (isset($r['attach']) && intval($r['attach'])>1){
                                            $dl = add_query_arg('attach', intval($r['attach']), $dl);
                                        }
                                        ?>
                                        <td class="cosmdl-col-auto"><a href="<?php echo esc_url($dl); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['name']); ?></a></td>
                                        <td class="cosmdl-col-auto"><?php echo esc_html($r['date']); ?></td>
                                        <td class="cosmdl-col-auto"><?php echo esc_html(cosmdl_admin_format_size(isset($r['size'])?$r['size']:'', isset($r['size_unit'])?$r['size_unit']:'', isset($r['size_bytes'])?$r['size_bytes']:0)); ?></td>
                                        <td class="cosmdl-col-drives">
                                            <?php 
                                            // 直接使用预计算的统计数据
                                            $type_to_count = isset($r['drive_counts']) ? $r['drive_counts'] : array();
                                            $calculated_counts = isset($r['calculated_counts']) ? $r['calculated_counts'] : array();
                                            
                                            if (!empty($r['uploaded'])){ 
                                                foreach($r['uploaded'] as $k=>$name){
                                                    // 优先使用预计算的修正统计（包含 Key + Alias）
                                                    if (isset($calculated_counts[$k])) {
                                                        $drive_count = $calculated_counts[$k];
                                                    } else {
                                                        $drive_count = isset($type_to_count[strtolower($k)]) ? $type_to_count[strtolower($k)] : 0;
                                                    }
                                                    
                                                    // 查找原始 drive config 以获取正确的 meta key
                                                    $original_drive_key = '';
                                                    $is_custom_drive = false;
                                                    
                                                    // 通过 effective_id 反查 drive config
                                                    // $k 是 effective_id (alias or key)
                                                    foreach($drive_management as $dm_k => $dm_v){
                                                        $eff = isset($dm_v['alias']) && $dm_v['alias']!=='' ? $dm_v['alias'] : $dm_k;
                                                        $eff = preg_replace('/[^a-z0-9\-]/','', strtolower($eff));
                                                        if ($eff === strtolower($k)){
                                                            $original_drive_key = $dm_k;
                                                            $is_custom_drive = (isset($dm_v['is_custom']) && $dm_v['is_custom']==='yes');
                                                            break;
                                                        }
                                                    }
                                                    
                                                    $direct_download_url = '';
                                                    if ($original_drive_key){
                                                        $meta_key = cosmdl_admin_field_url_for_drive($original_drive_key, isset($r['attach'])?$r['attach']:1, $is_custom_drive);
                                                        $direct_download_url = get_post_meta($r['pid'], $meta_key, true);
                                                    }
                                                    
                                                    if (!empty($direct_download_url)) {
                                                        $drive_url = esc_url_raw($direct_download_url);
                                                    } else {
                                                        // 找不到直接链接时使用备用方案
                                                        $drive_url = cosmdl_route_url('download', $r['pid']);
                                                        if (isset($r['attach']) && $r['attach'] > 1) {
                                                            $drive_url = add_query_arg('attach', $r['attach'], $drive_url);
                                                        }
                                                        // 使用 effective_id ($k) 作为参数
                                                        $drive_url = add_query_arg('drive', $k, $drive_url);
                                                    }
                                                    
                                                    echo '<div class="drive-badge-container" style="display: inline-block; margin-right: 6px; margin-bottom: 0px; text-align: center;">';
                                                    echo '<a href="'.esc_url($drive_url).'" target="_blank" rel="noopener" class="pk-badge alias-badge drive-name-badge" title="'.esc_attr($k).'">'.esc_html($name) . '</a>';
                                                    echo '</div>'; 
                                                } 
                                            } else { 
                                                echo '—'; 
                                            } 
                                            ?>
                                        </td>
                                        <td class="cosmdl-col-auto">
                                            <button type="button" class="button button-small cosmdl-count" data-pid="<?php echo intval($r['pid']); ?>" data-attach="<?php echo intval($r['attach']); ?>" aria-expanded="false" title="<?php echo esc_attr__('点击查看各网盘下载次数','cosmautdl'); ?>"><?php echo intval($r['count']); ?></button>
                                        </td>
                                    </tr>
                                    <tr id="per-drive-<?php echo intval($r['pid']); ?>-<?php echo intval($r['attach']); ?>" class="per-drive-row" style="display:none">
                                        <td colspan="5">
                                            <?php
                                            $uploaded = isset($r['uploaded']) ? $r['uploaded'] : array();
                                            $calculated_counts = isset($r['calculated_counts']) ? $r['calculated_counts'] : array();

                                            if (!empty($uploaded)){
                                                echo '<div class="per-drive-box">';
                                                echo '<strong>' . esc_html__('各网盘下载次数：','cosmautdl') . '</strong> ';

                                                foreach($uploaded as $effective_id => $drive_label){
                                                    $effective_id = strtolower((string) $effective_id);
                                                    $cnt = isset($calculated_counts[$effective_id]) ? intval($calculated_counts[$effective_id]) : 0;

                                                    $original_drive_key = '';
                                                    $is_custom_drive = false;
                                                    foreach($drive_management as $dm_k => $dm_v){
                                                        $eff = isset($dm_v['alias']) && $dm_v['alias']!=='' ? $dm_v['alias'] : $dm_k;
                                                        $eff = preg_replace('/[^a-z0-9\-]/','', strtolower($eff));
                                                        if ($eff === $effective_id){
                                                            $original_drive_key = $dm_k;
                                                            $is_custom_drive = (isset($dm_v['is_custom']) && $dm_v['is_custom']==='yes');
                                                            break;
                                                        }
                                                    }

                                                    $download_url = '';
                                                    if ($original_drive_key){
                                                        $meta_key = cosmdl_admin_field_url_for_drive($original_drive_key, isset($r['attach']) ? $r['attach'] : 1, $is_custom_drive);
                                                        $download_url = get_post_meta($r['pid'], $meta_key, true);
                                                    }

                                                    $style = 'margin-right:6px; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; color: white;';
                                                    if ($download_url) {
                                                        printf(
                                                            '<a href="%1$s" target="_blank" rel="noopener" class="pk-badge per-drive-badge" style="%2$s" title="%3$s">%4$s: %5$d</a>',
                                                            esc_url($download_url),
                                                            esc_attr($style),
                                                            esc_attr__('点击跳转下载', 'cosmautdl'),
                                                            esc_html($drive_label),
                                                            intval($cnt)
                                                        );
                                                    } else {
                                                        printf(
                                                            '<span class="pk-badge per-drive-badge" style="%1$s" title="%2$s">%3$s: %4$d</span>',
                                                            esc_attr($style),
                                                            esc_attr__('点击跳转下载', 'cosmautdl'),
                                                            esc_html($drive_label),
                                                            intval($cnt)
                                                        );
                                                    }
                                                }
                                                echo '</div>';
                                            } else {
                                                echo '<em>' . esc_html__('暂无详细统计','cosmautdl') . '</em>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5"><?php echo esc_html__('暂无数据：尚未配置任何下载链接','cosmautdl'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                            <div>
                                <span>共 <?php echo intval($total); ?> 条；每页显示</span>
                                <!-- 为避免嵌套表单破坏主设置表单，这里移除内联 GET 表单，改用 JS 修改 URL 参数后刷新 -->
                                <select id="cosmdl-stats-ppp" name="cosmdl_stats_ppp" onchange="(function(sel){try{var u=new URL(location.href);u.searchParams.set('cosmdl_active_tab','stats');u.searchParams.set('cosmdl_stats_ppp', sel.value);location.href=u.toString();}catch(e){location.reload();}})(this)" style="min-width:90px">
                                    <option value="50" <?php selected($ppp,50); ?>>50</option>
                                    <option value="100" <?php selected($ppp,100); ?>>100</option>
                                    <option value="200" <?php selected($ppp,200); ?>>200</option>
                                </select>
                            </div>
                            <div>
                                <?php
                                // 简易分页按钮
                                $base = $base_url;
                                $base = add_query_arg(array('cosmdl_active_tab'=>'stats','cosmdl_stats_sort'=>$sort,'cosmdl_stats_order'=>$order,'cosmdl_stats_ppp'=>$ppp), $base);
                                $prev_page = max(1, $page-1);
                                $next_page = min($pages, $page+1);
                                $prev_url = add_query_arg(array('cosmdl_stats_page'=>$prev_page), $base);
                                $next_url = add_query_arg(array('cosmdl_stats_page'=>$next_page), $base);
                                echo '<span>' . esc_html__('第','cosmautdl') . ' '.intval($page).' / '.intval($pages).' ' . esc_html__('页','cosmautdl') . '</span> ';
                                echo '<a class="button" href="'.esc_url($prev_url).'" style="margin-left:6px">' . esc_html__('上一页','cosmautdl') . '</a>';
                                echo '<a class="button" href="'.esc_url($next_url).'" style="margin-left:6px">' . esc_html__('下一页','cosmautdl') . '</a>';
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php } // end permission check ?>
                </div>

                <!-- 文件树设置 -->
                <div class="drawer-content" id="file-tree-content" style="display:none;">
                    <h2><?php echo esc_html__('文件树','cosmautdl'); ?></h2>
                    <p class="description"><?php echo esc_html__('控制前端「文件树」页面是否启用以及可见性。文件树用于面向用户展示全站资源目录，支持筛选与排序。','cosmautdl'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('启用文件树','cosmautdl'); ?></th>
                            <td><?php $this->field_checkbox(array('key'=>'enable_tree')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('新窗口打开链接','cosmautdl'); ?></th>
                            <td><?php $this->field_checkbox(array('key'=>'tree_open_links_in_new_window','label'=>esc_html__('文件树页面所有链接在新窗口打开','cosmautdl'))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('可见性','cosmautdl'); ?></th>
                            <td><?php $this->field_select(array('key'=>'tree_visibility','options'=>array('public'=>'公开','admin'=>'仅管理员'))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('访问路径','cosmautdl'); ?></th>
                            <td>
                              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <code id="cosmdl-tree-url-code"><?php echo esc_html($tree_url); ?></code>
                                <a class="button" href="<?php echo esc_url($tree_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__('直达链接','cosmautdl'); ?></a>
                                <button type="button" class="button" id="cosmdl-copy-tree-url" data-url="<?php echo esc_attr($tree_url); ?>"><?php echo esc_html__('点击复制地址','cosmautdl'); ?></button>
                              </div>
                            </td>
                        </tr>
                    </table>
                    
                </div>

                <!-- 顶部图标按钮负责保存/恢复，这里不再显示底部提交按钮 -->
            </form>

            <style>
            .cosmdl-drawer-nav {
                display: flex;
                margin-bottom: 20px;
                border-bottom: 1px solid #ccd0d4;
                background: #f1f1f1;
                padding: 0;
                border-radius: 4px 4px 0 0;
            }
            .drawer-tab {
                background: none;
                border: none;
                padding: 12px 20px;
                cursor: pointer;
                border-bottom: 3px solid transparent;
                transition: all 0.3s ease;
                font-size: 14px;
                color: #555;
            }
            .drawer-tab:hover {
                background: #e8e8e8;
                color: #333;
            }
            .drawer-tab.active {
                background: #fff;
                color: #0073aa;
                border-bottom-color: #0073aa;
                font-weight: 600;
            }
            .drawer-content {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-top: none;
                border-radius: 0 0 4px 4px;
                min-height: 400px;
            }
            .drawer-content h2 {
                margin-top: 0;
                color: #23282d;
                border-bottom: 1px solid #e1e1e1;
                padding-bottom: 10px;
            }

            /* 下载统计视觉表格增强 */
            .cosmdl-stats-table { border-collapse: separate; border-spacing: 0; width: 100%; }
            .cosmdl-stats-table thead th, .cosmdl-stats-table tbody td { vertical-align: middle; } /* 添加垂直居中对齐 */
            .cosmdl-stats-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; text-align: center; }
            /* 表头排序样式 */
            .cosmdl-sortable { cursor: pointer; }
            .cosmdl-header-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                color: #23282d;
                padding: 4px 8px;
                border-radius: 3px;
                transition: background-color 0.2s;
                width: 100%;
            }
            .cosmdl-header-link:hover {
                background-color: #f0f0f1;
                color: #0073aa;
            }
            .cosmdl-sort-link {
                margin-left: 4px;
                color: #0073aa;
                text-decoration: none;
            }
            
            /* 排序状态本地存储脚本 */
            <script>
            (function() {
                // 存储键名
                const STORAGE_KEY = 'cosmdl_stats_sort_prefs';
                
                // 初始化：检查并应用保存的排序偏好
                function initSortPreferences() {
                    try {
                        const savedPrefs = localStorage.getItem(STORAGE_KEY);
                        
                        if (savedPrefs) {
                            const prefs = JSON.parse(savedPrefs);
                            const currentURL = new URL(window.location.href);
                            
                            // 检查当前URL是否已经包含排序参数
                            const hasSortParams = currentURL.searchParams.has('cosmdl_stats_sort') && 
                                                currentURL.searchParams.has('cosmdl_stats_order');
                            
                            // 如果没有显式的排序参数，则应用保存的偏好
                            if (!hasSortParams) {
                                currentURL.searchParams.set('cosmdl_stats_sort', prefs.sort);
                                currentURL.searchParams.set('cosmdl_stats_order', prefs.order);
                                window.location.href = currentURL.toString();
                            }
                        }
                    } catch (e) {
                        // 只在开发模式下输出调试信息
                        if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                            console.error('Error loading sort preferences:', e);
                        }
                    }
                }
                
                // 保存排序偏好
                function saveSortPreferences(sort, order) {
                    try {
                        const prefs = { sort, order };
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
                    } catch (e) {
                        // 只在开发模式下输出调试信息
                        if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                            console.error('Error saving sort preferences:', e);
                        }
                    }
                }
                
                // 监听排序链接点击
                function setupSortListeners() {
                    const sortLinks = document.querySelectorAll('.cosmdl-header-link, .cosmdl-sort-link');
                    
                    sortLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            // 获取链接中的排序参数
                            const href = this.getAttribute('href');
                            const url = new URL(href, window.location.origin);
                            const sort = url.searchParams.get('cosmdl_stats_sort');
                            const order = url.searchParams.get('cosmdl_stats_order');
                            
                            if (sort && order) {
                                saveSortPreferences(sort, order);
                            }
                        });
                    });
                }
                
                // 页面加载时执行初始化
                document.addEventListener('DOMContentLoaded', function() {
                    // 只有在下载统计页面才执行
                    if (document.querySelector('.cosmdl-stats-table')) {
                        initSortPreferences();
                        setupSortListeners();
                    }
                });
            })();
            </script>
.cosmdl-stats-table tbody tr:nth-child(odd) { background: #fafafa; line-height: 1.2; height: auto; } /* 统一行高，优化高度 */
.cosmdl-stats-table tbody tr.stats-row:hover { background: #f0f7ff; }
/* 自适应列宽样式 */
            .cosmdl-col-auto { width: 1%; white-space: nowrap; text-align: center; line-height: 1.2; }
            .cosmdl-col-drives { width: 100%; min-width: 180px; word-break: break-word; text-align: center; line-height: 1.2; display: table-cell; vertical-align: middle !important; padding: 0 !important; } /* 强制垂直居中，去除内边距 */
.cosmdl-col-drives .pk-badge { display: inline-flex; align-items: center; margin: 0; } /* 去除底部间距 */
/* 响应式调整 */
@media (max-width: 768px) {
    .cosmdl-col-drives { min-width: 120px; }
    .cosmdl-stats-table { font-size: 14px; }
}
            .alias-badge { background:#3730a3; color:white; padding:2px 8px; border-radius:999px; font-size:12px; font-weight: 500; }
            .per-drive-badge { background:#92400e; color:white; padding:2px 8px; border-radius:999px; font-size:12px; font-weight: 500; }
            
            /* 网盘LOGO悬停效果 */
            .per-drive-badge .cosmdl-pan-logo {
              transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
              border-radius: 4px;
              vertical-align: middle;
            }

            .per-drive-badge:hover .cosmdl-pan-logo {
              transform: scale(1.3); /* 放大效果 */
              filter: brightness(1.2); /* 亮度增加 */
            }
            
            /* 增强链接悬停反馈 */
            a.per-drive-badge:hover {
                opacity: 0.9;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                transform: translateY(-1px);
                transition: all 0.2s ease;
            }
            
            .per-drive-box { padding:10px; border:1px dashed #e5e7eb; border-radius:6px; background:#fff; }
            
            /* 下载详情表格：网盘 LOGO 悬停反馈 */
            .cosmdl-log-drive .cosmdl-pan-logo {
              transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
              border-radius: 6px;
            }
            .cosmdl-log-drive:hover .cosmdl-pan-logo {
              transform: scale(1.12);
              filter: brightness(1.08);
              box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
            }
            
            /* 下载详情表格：删除按钮悬停反馈 */
            .cosmdl-delete-log { transition: transform 0.2s ease, filter 0.2s ease; }
            .cosmdl-delete-log:hover { transform: scale(1.08); filter: brightness(1.03); }
            .cosmdl-delete-log:hover .cosmdl-delete-log-icon, .cosmdl-delete-log:hover .dashicons-trash { transform: scale(1.15); filter: brightness(1.05); transition: transform 0.2s ease, filter 0.2s ease; }
            .cosmdl-details-box table td { padding: 0 10px !important; }
            
            /* 网盘标签和下载次数的样式 */
            .drive-badge-container { text-align: center; margin:0; padding:0; display:inline-flex; align-items: center; vertical-align: middle; line-height: 1; height: 22px; } /* 固定高度确保对齐 */
            .drive-name-badge { background:#e7f5ee; color:#34a853; border:1px solid #a8dbc1; border-radius:3px; display: inline-flex; align-items: center; justify-content: center; padding:2px 4px; margin:0 1px 0 0; vertical-align: middle; line-height: 1; height: 15px; } /* 优化内边距和高度，确保完全对齐 */
            
            /* 各网盘特定颜色样式 */
            .drive-name-badge[data-drive="baidu"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="123"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="ali"] { background:#fdf4ff; color:#9333ea; border-color:#d8b4fe; }
            .drive-name-badge[data-drive="189"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="quark"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="pikpak"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="lanzou"] { background:#f0fdf4; color:#16a34a; border-color:#86efac; }
            .drive-name-badge[data-drive="xunlei"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="weiyun"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="onedrive"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="googledrive"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="dropbox"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="mega"] { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }
            .drive-name-badge[data-drive="mediafire"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="box"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-name-badge[data-drive="other"] { background:#f0f9ff; color:#0284c7; border-color:#7dd3fc; }
            .drive-count-badge { background:#dbeafe; color:#1e40af; padding:1px 6px; font-size:11px; min-width:20px; line-height:1.4; }

            /* 顶部图标按钮样式（对齐截图色系） */
            .cosmdl-actions{display:inline-flex;gap:10px;align-items:center}
            .cosmdl-top-meta{display:inline-flex;align-items:center;gap:10px;margin-right:6px}
            .cosmdl-version{font-size:12px;line-height:1.8;color:#64748b;background:#fff;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px}
            .cosmdl-top-link{font-size:12px;line-height:1.8;color:#2271b1;text-decoration:none;border-radius:6px;padding:2px 6px}
            .cosmdl-top-link:hover{background:rgba(34,113,177,.08)}
            .cosmdl-action{width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;color:#334155;cursor:pointer;transition:all .15s ease}
            .cosmdl-action:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(2,6,23,.08)}
            .cosmdl-action--export{background:#dbeafe;color:#1d4ed8}
            .cosmdl-action--export:hover{background:#bfdbfe}
            .cosmdl-action--import{background:#ffedd5;color:#c2410c}
            .cosmdl-action--import:hover{background:#fed7aa}
            .cosmdl-action--save{background:#d1fae5;color:#16a34a}
            .cosmdl-action--save:hover{background:#a7f3d0}
            .cosmdl-action--reset{background:#ede9fe;color:#7c3aed}
            .cosmdl-action--reset:hover{background:#ddd6fe}

            /* 统一后台勾选框为 cosmdl-switch 样式 */
.cosmdl-switch{position:relative;display:inline-block;width:40px!important;height:22px;vertical-align:middle;min-width:40px;flex-shrink:0;flex:0 0 40px;line-height:0}
            .cosmdl-switch input{opacity:0;width:0;height:0}
            .cosmdl-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d9d9d9;transition:.2s;border-radius:22px}
            .cosmdl-slider:before{position:absolute;content:"";height:18px;width:18px;left:2px;top:50%;background:white;transition:.2s;border-radius:50%;transform:translateY(-50%)}
            .cosmdl-switch input:checked + .cosmdl-slider{background:#22c55e}
            .cosmdl-switch input:checked + .cosmdl-slider:before{transform:translate(18px,-50%)}
            
            /* 下载详情表格样式 - 使所有单元格内容居中显示 */
            .widefat.striped td {
                text-align: center;
                vertical-align: middle;
            }
            </style>

            <script>
            (function(){
                // 颜色选择器同步功能
                document.addEventListener('DOMContentLoaded', function() {
                    // 同步颜色选择器和文本输入框
                    var colorInputs = document.querySelectorAll('input[type="color"]');
                    colorInputs.forEach(function(colorInput) {
                        var textInput = colorInput.nextElementSibling;
                        if (textInput && textInput.type === 'text') {
                            // 颜色选择器变化时更新文本框
                            colorInput.addEventListener('input', function() {
                                textInput.value = this.value;
                            });
                            // 文本框变化时更新颜色选择器
                            textInput.addEventListener('input', function() {
                                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                                    colorInput.value = this.value;
                                }
                            });
                        }
                    });
                });
                
                // 抽屉切换功能（记忆当前激活标签）
                var tabs = document.querySelectorAll('.drawer-tab');
                var contents = document.querySelectorAll('.drawer-content');
                function activateTab(tabKey){
                    tabs.forEach(function(t){
                        var k = t.getAttribute('data-tab');
                        if(k === tabKey){ t.classList.add('active'); } else { t.classList.remove('active'); }
                    });
                    contents.forEach(function(c){ c.style.display = 'none'; });
                    var target = document.getElementById(tabKey + '-content');
                    if(target){ target.style.display = 'block'; }
                }
                
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        var targetTab = this.getAttribute('data-tab');
                        
                        // 激活当前选项卡并记忆
                        activateTab(targetTab);
                        try {
                          localStorage.setItem('cosmdl_active_tab', targetTab);
                          // 同步到 URL，刷新后保持当前抽屉
                          var u = new URL(location.href);
                          u.searchParams.set('cosmdl_active_tab', targetTab);
                          history.replaceState(null, '', u.toString());
                        } catch(e){}
                    });
                });
                
                // 初始化显示状态：优先读取 URL 参数，其次读取 localStorage，默认显示“全局”设置
                (function(){
                  var initial = 'global-settings';
                  try {
                    var u = new URL(location.href);
                    var qp = u.searchParams.get('cosmdl_active_tab');
                    if(qp){ initial = qp; }
                    else {
                      var saved = localStorage.getItem('cosmdl_active_tab');
                      if(saved){ initial = saved; }
                    }
                  } catch(e){}
                  contents.forEach(function(c) { c.style.display = 'none'; });
                  activateTab(initial);
                })();
                
                // 折叠/展开未开启网盘
                document.addEventListener('DOMContentLoaded', function(){
                  var container = document.getElementById('drives-container');
                  if (!container) return;
                  container.classList.add('collapsed');
                  var btn = document.getElementById('drives-toggle');
                  if (!btn) return;
                  // 移除 JS 宽度同步，改用 CSS 控制
                  function setExpanded(expanded){
                    // 设置按钮过渡效果，使用ease-in-out让动画更加平滑
                    btn.style.transition = 'transform 350ms ease-in-out';
                    
                    // 记录按钮当前位置
                    var beforeTop = 0;
                    try { beforeTop = btn.getBoundingClientRect().top; } catch(e){}
                    
                    var icon = btn.querySelector('.dashicons');
                    var txt = btn.querySelector('.text');

                    if (expanded){
                      // 更改按钮图标与文字
                      if(icon) icon.className = 'dashicons dashicons-arrow-up-alt2';
                      if(txt) txt.textContent = '收起未开启网盘';
                      
                      // 先移除折叠类，使容器准备好展开
                      container.classList.remove('collapsed');
                      
                      // 显示被关闭的网盘行，使用平滑过渡
                      var rows = container.querySelectorAll('tr.drive-item[data-enabled="no"]');
                      rows.forEach(function(r){
                        if (getComputedStyle(r).display === 'none'){
                          r.style.display = 'table-row';
                          r.style.opacity = '0';
                          r.style.transition = 'opacity 350ms ease-in-out';
                          requestAnimationFrame(function(){ r.style.opacity = '1'; });
                        }
                      });
                    } else {
                      // 更改按钮图标与文字
                      if(icon) icon.className = 'dashicons dashicons-arrow-down-alt2';
                      if(txt) txt.textContent = '展开更多网盘';
                      
                      // 先应用淡出效果，再添加折叠类
                      var rows = container.querySelectorAll('tr.drive-item[data-enabled="no"]');
                      rows.forEach(function(r){
                        r.dataset.forceVisible = '0';
                        if (getComputedStyle(r).display !== 'none'){
                          r.style.transition = 'opacity 350ms ease-in-out';
                          r.style.opacity = '0';
                        }
                      });
                      
                      // 等待淡出动画完成后添加折叠类
                      setTimeout(function(){
                        container.classList.add('collapsed');
                        rows.forEach(function(r){
                          r.style.display = 'none';
                          r.style.transition = '';
                          r.style.opacity = '';
                        });
                      }, 350);
                    }
                    
                    // 在DOM更新后计算位置差并执行位移动画
                    requestAnimationFrame(function(){
                      requestAnimationFrame(function(){
                        try {
                          var afterTop = btn.getBoundingClientRect().top;
                          var delta = beforeTop - afterTop; // >0 表示按钮向下移动
                          
                          if (Math.abs(delta) > 1){
                            // 应用位移动画，让按钮跟随内容平滑移动
                            btn.style.transform = 'translateY(' + (-delta) + 'px)';
                            
                            // 动画完成后重置变换
                            setTimeout(function(){
                              btn.style.transform = '';
                              btn.style.transition = '';
                            }, 350);
                          } else {
                            btn.style.transition = '';
                          }
                        } catch(e){}
                      });
                    });
                    try { localStorage.setItem('cosmdl_drives_expanded', expanded ? '1' : '0'); } catch(e){}
                  }
                  btn.addEventListener('click', function(){
                    var expanded = !container.classList.contains('collapsed');
                    setExpanded(!expanded);
                  });
                  // 初始化时根据本地存储状态或默认展开状态设置按钮文本
                  try {
                    // 优先检查本地存储中的状态
                    var savedState = localStorage.getItem('cosmdl_drives_expanded');
                    
                    if (savedState === null) {
                      // 首次使用，默认设置为展开状态，显示"收起未开启网盘"
                      setExpanded(true);
                    } else {
                      // 使用保存的状态
                      setExpanded(savedState === '1');
                    }
                  } catch(e){}
                });

                // 开关联动：在折叠模式下，开启立即显示，关闭自动收纳
                document.addEventListener('DOMContentLoaded', function(){
                  var container = document.getElementById('drives-container');
                  if (!container) return;
                  container.addEventListener('change', function(e){
                    var t = e.target;
                    if (!t || t.tagName !== 'INPUT') return;
                    var name = t.getAttribute('name') || '';
                    if (!/cosmdl_options\[drive_management\]\[[^\]]+\]\[enabled\]/.test(name)) return;
                  var row = t.closest('tr.drive-item');
                  if (!row) return;
                  var enabled = t.checked;
                  row.dataset.enabled = enabled ? 'yes' : 'no';
                  if (container.classList.contains('collapsed')){
                    if (enabled){
                      row.removeAttribute('data-force-visible');
                      if (getComputedStyle(row).display === 'none'){
                        row.style.display = 'table-row';
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 350ms ease';
                        requestAnimationFrame(function(){ row.style.opacity = '1'; });
                        setTimeout(function(){ row.style.transition = ''; row.style.opacity = ''; }, 380);
                      }
                    } else {
                      row.setAttribute('data-force-visible', '1');
                      if (getComputedStyle(row).display === 'none'){
                        row.style.display = 'table-row';
                      }
                      // 关闭时不做淡入淡出，取消任何过渡及透明度
                      row.style.transition = '';
                      row.style.opacity = '';
                    }
                  }
                  });
                });
                // 网盘拖拽排序功能（仅允许通过“三道杠”把手触发拖拽）
                document.addEventListener('DOMContentLoaded', function() {
                    const sortableList = document.getElementById('drives-sortable');
                    let draggedItem = null;
                    
                    // 添加拖拽事件监听
                    let dragGhost = null;
                    let placeholder = null; // 行级占位兼容保留（当前未使用）
                    let cellPlaceholder = null; // 第三列内容占位，确保行高稳定
                    let draggedContent = null;  // 实际被临时移动的第三列真实内容容器
                    let originalContentCell = null; // 记录第三列单元格，便于拖拽结束归位
                    let dragStartY = 0;
                    let lastAfterElement = null;
                    let pointerDragging = false; // 使用指针事件自定义拖拽，规避原生 DnD 的禁止符号与虚线框
                    // 为避免“单击把手”立即出现拖拽影像，增加移动阈值与候选状态
                    const START_THRESHOLD = 6; // 仅当移动超过该像素距离才正式开始拖拽
                    let dragCandidate = null;   // { row, contentCell, contentRect, startY }

                    // 指针事件实现的自定义拖拽
                    function startPointerDrag(handle, clientY) {
                        const row = handle.closest('.drive-item');
                        if (!row) return;
                        // 不立即创建拖拽影像，仅记录候选信息；待移动超过阈值再真正开始拖拽
                        const rect = row.getBoundingClientRect();
                        const contentCell = row.querySelector('td:nth-child(3)');
                        const contentRect = contentCell ? contentCell.getBoundingClientRect() : rect;
                        dragStartY = clientY - contentRect.top;
                        dragCandidate = { row: row, contentCell: contentCell, contentRect: contentRect, startY: clientY };
                        draggedItem = row;
                    }

                    function movePointerDrag(clientY) {
                        // 若尚未正式进入拖拽，但存在候选且移动超过阈值，则在此刻创建影像并进入拖拽
                        if (!pointerDragging && dragCandidate && draggedItem) {
                            const delta = Math.abs(clientY - dragCandidate.startY);
                            if (delta >= START_THRESHOLD) {
                                const row = dragCandidate.row;
                                const contentCell = dragCandidate.contentCell;
                                const contentRect = dragCandidate.contentRect;
                                dragGhost = document.createElement('div');
                                dragGhost.classList.add('drag-ghost');
                                var ghostPaddingX = 8 * 2;
                                var ghostBorderX = 1 * 2;
                                dragGhost.style.width = (contentRect.width + ghostPaddingX + ghostBorderX) + 'px';
                                dragGhost.style.left = contentRect.left + 'px';
                                dragGhost.style.top = contentRect.top + 'px';
                                var innerRow = contentCell ? contentCell.querySelector('div') : null;
                                originalContentCell = contentCell;
                                if (innerRow) {
                                    cellPlaceholder = document.createElement('div');
                                    cellPlaceholder.className = 'drive-content-placeholder';
                                    try { var irRect = innerRow.getBoundingClientRect(); cellPlaceholder.style.height = irRect.height + 'px'; }
                                    catch(_){ cellPlaceholder.style.minHeight = '32px'; }
                                    contentCell.replaceChild(cellPlaceholder, innerRow);
                                    draggedContent = innerRow;
                                    dragGhost.appendChild(draggedContent);
                                    try { draggedContent.style.boxSizing = 'border-box'; draggedContent.style.width = '100%'; } catch(_){}
                                } else {
                                    var clone = contentCell ? contentCell.cloneNode(true) : row.cloneNode(true);
                                    dragGhost.appendChild(clone);
                                }
                                document.body.appendChild(dragGhost);
                                row.classList.add('dragging');
                                pointerDragging = true;
                                try { document.body.classList.add('cosmdl-dragging-cursor'); } catch(_){}
                                dragCandidate = null; // 正式开始后清空候选
                            }
                        }
                        if (!pointerDragging || !draggedItem) return;
                        if (dragGhost) {
                            const top = (clientY - dragStartY);
                            dragGhost.style.top = top + 'px';
                        }
                        const afterElement = getDragAfterElement(sortableList, clientY);
                        if (afterElement === lastAfterElement) return;
                        const beforeMap = new Map();
                        const rows = [...sortableList.querySelectorAll('.drive-item')].filter(r => r !== draggedItem);
                        rows.forEach(row => { beforeMap.set(row, row.getBoundingClientRect().top); });
                        if (!afterElement) { sortableList.appendChild(draggedItem); }
                        else { sortableList.insertBefore(draggedItem, afterElement); }
                        lastAfterElement = afterElement;
                        requestAnimationFrame(function(){
                            rows.forEach(row => {
                                const beforeTop = beforeMap.get(row);
                                const afterTop = row.getBoundingClientRect().top;
                                const deltaY = beforeTop - afterTop;
                                if (!isFinite(deltaY) || Math.abs(deltaY) < 1) return;
                                row.style.transform = `translateY(${deltaY}px)`;
                                row.style.transition = 'transform 160ms ease-out';
                                requestAnimationFrame(function(){ row.style.transform = ''; });
                                row.addEventListener('transitionend', function handler(){
                                    row.style.transition = '';
                                    row.removeEventListener('transitionend', handler);
                                });
                            });
                        });
                    }

                    function endPointerDrag() {
                        // 若未进入拖拽，仅清空候选并返回，不做任何视觉变动（解决单击把手出现影像）
                        if (!pointerDragging) { dragCandidate = null; return; }
                        if (draggedItem) { draggedItem.classList.remove('dragging'); }
                        try { document.body.classList.remove('cosmdl-dragging-cursor'); } catch(_){}
                        try {
                            if (draggedContent && originalContentCell) {
                                var currentCell = draggedItem ? draggedItem.querySelector('td:nth-child(3)') : originalContentCell;
                                if (cellPlaceholder && currentCell && cellPlaceholder.parentNode === currentCell) {
                                    currentCell.replaceChild(draggedContent, cellPlaceholder);
                                } else if (currentCell) {
                                    currentCell.appendChild(draggedContent);
                                }
                            }
                        } catch(_){}
                    if (cellPlaceholder && cellPlaceholder.parentNode) { cellPlaceholder.parentNode.removeChild(cellPlaceholder); }
                    cellPlaceholder = null; draggedContent = null; originalContentCell = null;
                        if (dragGhost && dragGhost.parentNode) { dragGhost.parentNode.removeChild(dragGhost); }
                        dragGhost = null; lastAfterElement = null; draggedItem = null; pointerDragging = false;
                        dragCandidate = null;
                        updateOrderValues();
                        // 改变排序属于配置变更，提示保存
                        try { markDirty(); } catch(_){}
                    }

                    // 把手绑定与新增行重绑定（支持按下把手即可拖）
                    function bindHandlePointer(){
                        var handles = document.querySelectorAll('.drive-handle');
                        handles.forEach(function(h){
                            h.addEventListener('mousedown', function(ev){ ev.preventDefault(); startPointerDrag(h, ev.clientY); });
                            h.addEventListener('touchstart', function(ev){ if(ev.touches && ev.touches.length){ ev.preventDefault(); startPointerDrag(h, ev.touches[0].clientY); } }, { passive:false });
                        });
                    }
                    bindHandlePointer();
                    const mo = new MutationObserver(function(muts){ for(const m of muts){ if(m.addedNodes && m.addedNodes.length){ bindHandlePointer(); break; } } });
                    mo.observe(sortableList, {childList:true});

                    // 全局指针移动/结束
                    // 指针移动：当存在候选或已进入拖拽时都需要跟踪移动；仅在真正拖拽中时阻止默认行为
                    document.addEventListener('mousemove', function(ev){
                        if (pointerDragging || dragCandidate) {
                            if (pointerDragging) ev.preventDefault();
                            movePointerDrag(ev.clientY);
                        }
                    });
                    document.addEventListener('touchmove', function(ev){
                        if ((pointerDragging || dragCandidate) && ev.touches && ev.touches.length) {
                            ev.preventDefault();
                            movePointerDrag(ev.touches[0].clientY);
                        }
                    }, { passive:false });
                    document.addEventListener('mouseup', function(){ endPointerDrag(); });
                    document.addEventListener('touchend', function(){ endPointerDrag(); });

                    // 捕获阶段阻止原生 DnD 的启动，直接走自定义拖拽
                    document.addEventListener('dragstart', function(e){
                        if (e.target && e.target.classList && e.target.classList.contains('drive-handle')) {
                            e.preventDefault();
                            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                            startPointerDrag(e.target, e.clientY);
                        }
                    }, true);

                    // 全局允许拖拽经过任何区域时保持“可放置”状态，避免出现“禁止符号”
                    document.addEventListener('dragover', function(e){
                        if(!draggedItem) return;
                        e.preventDefault();
                        try { if(e.dataTransfer) e.dataTransfer.dropEffect = 'move'; } catch(_){}
                    });
                    document.addEventListener('drop', function(e){
                        if(!draggedItem) return;
                        e.preventDefault();
                    });
                    
                    // 使用 document 级事件，确保跨浏览器都能捕获 dragstart
                    document.addEventListener('dragstart', function(e) {
                        // 仅当从把手元素开始拖拽时，才启动排序
                        if (!(e.target && e.target.classList && e.target.classList.contains('drive-handle'))) {
                            e.preventDefault();
                            return;
                        }
                        const row = e.target.closest('.drive-item');
                        if (!row) return;
                        draggedItem = row;
                        try {
                            e.dataTransfer.effectAllowed = 'move';
                            // 某些浏览器需要设置数据才会触发拖拽流程
                            e.dataTransfer.setData('text/plain', 'drag');
                        } catch(_){}
                        // 使用透明拖拽图像，避免浏览器默认影像与自定义影像叠加导致抖动
                        try {
                            // 使用自定义的“上下移动”指示图作为原生拖拽光标的锚点，替换浏览器默认虚线框
                            const dpr = window.devicePixelRatio || 1;
                            const size = 20; // 视觉尺寸（CSS 像素）
                            const canvas = document.createElement('canvas');
                            canvas.width = size * dpr;
                            canvas.height = size * dpr;
                            const ctx = canvas.getContext('2d');
                            ctx.scale(dpr, dpr);
                            ctx.clearRect(0, 0, size, size);
                            // 画一条竖线与上下箭头
                            ctx.strokeStyle = '#64748b';
                            ctx.lineWidth = 2;
                            ctx.beginPath();
                            ctx.moveTo(size/2, 4);
                            ctx.lineTo(size/2, size-4);
                            ctx.stroke();
                            // 上箭头
                            ctx.fillStyle = '#64748b';
                            ctx.beginPath();
                            ctx.moveTo(size/2, 4);
                            ctx.lineTo(size/2 - 4, 8);
                            ctx.lineTo(size/2 + 4, 8);
                            ctx.closePath();
                            ctx.fill();
                            // 下箭头
                            ctx.beginPath();
                            ctx.moveTo(size/2, size-4);
                            ctx.lineTo(size/2 - 4, size-8);
                            ctx.lineTo(size/2 + 4, size-8);
                            ctx.closePath();
                            ctx.fill();
                            e.dataTransfer.setDragImage(canvas, size/2, size/2);
                        } catch(_){}
                        // 拖拽中将全局光标设为“上下移动”
                        try { document.body.classList.add('cosmdl-dragging-cursor'); } catch(_){}
                        // 创建固定定位的影像卡片（以“第三列内容容器”为蓝本），避免 <tr> 克隆导致开关/删除按钮错位
                        const rect = row.getBoundingClientRect();
                        const contentCell = row.querySelector('td:nth-child(3)');
                        const contentRect = contentCell ? contentCell.getBoundingClientRect() : rect;
                        dragStartY = e.clientY - contentRect.top;
                        // 影像卡片容器
                        dragGhost = document.createElement('div');
                        dragGhost.classList.add('drag-ghost');
                        // 影像采用 border-box，因此需要在 width 上补偿左右 padding 与边框，避免内层内容宽度变窄而换行
                        var ghostPaddingX = 8 * 2; // 与 .drag-ghost 的水平 padding 保持一致
                        var ghostBorderX = 1 * 2;  // 左右边框
                        dragGhost.style.width = (contentRect.width + ghostPaddingX + ghostBorderX) + 'px';
                        dragGhost.style.left = contentRect.left + 'px';
                        dragGhost.style.top = contentRect.top + 'px';
                        // 真实内容拖动：将第三列的实际内容临时移动到影像卡片中，并在原位置放置等高占位符
                        var innerRow = contentCell ? contentCell.querySelector('div') : null;
                        originalContentCell = contentCell;
                        if(innerRow){
                            // 创建占位符，保留原位置的尺寸与布局
                            cellPlaceholder = document.createElement('div');
                            cellPlaceholder.className = 'drive-content-placeholder';
                            try {
                                var irRect = innerRow.getBoundingClientRect();
                                cellPlaceholder.style.height = irRect.height + 'px';
                            } catch(_){ cellPlaceholder.style.minHeight = '32px'; }
                            contentCell.replaceChild(cellPlaceholder, innerRow);
                            draggedContent = innerRow;
                            // 将真实内容放入影像卡片
                            dragGhost.appendChild(draggedContent);
                            // 保持真实布局属性，不强制 nowrap，让未来内容（如 LOGO）自然扩展
                            try {
                                draggedContent.style.boxSizing = 'border-box';
                                draggedContent.style.width = '100%';
                            } catch(_){}
                        } else {
                            // 兜底：若未找到第三列内容，仍然克隆整行以保证基本拖拽体验
                            var clone = contentCell ? contentCell.cloneNode(true) : row.cloneNode(true);
                            dragGhost.appendChild(clone);
                        }
                        document.body.appendChild(dragGhost);
                        // 保留原行可见，避免因 visibility:hidden 与表格布局的特殊性导致行高异常
                        // 仅添加 dragging 标记用于轻量视觉反馈
                        row.classList.add('dragging');
                        // 只在开发模式下输出调试信息
                        if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                            console.debug('[cosmdl] dragstart from handle');
                        }
                    });
                    
                    // 使用 document 级事件，确保拖拽结束时总能清理影像与占位
                    document.addEventListener('dragend', function(e) {
                        if (!draggedItem) return;
                        // 清理状态
                        draggedItem.classList.remove('dragging');
                        draggedItem = null;
                        try { document.body.classList.remove('cosmdl-dragging-cursor'); } catch(_){}
                        // 归位真实内容：将第三列内容回插占位位置
                        try {
                            if (draggedContent && originalContentCell) {
                                var currentCell = draggedItem ? draggedItem.querySelector('td:nth-child(3)') : originalContentCell;
                                if (cellPlaceholder && currentCell && cellPlaceholder.parentNode === currentCell) {
                                    currentCell.replaceChild(draggedContent, cellPlaceholder);
                                } else if (currentCell) {
                                    // 若占位不在当前行（极端情况），直接附加到当前单元格末尾
                                    currentCell.appendChild(draggedContent);
                                }
                            }
                        } catch(_){}
                        // 移除占位与拖拽影像
                        if (cellPlaceholder && cellPlaceholder.parentNode) { cellPlaceholder.parentNode.removeChild(cellPlaceholder); }
                        cellPlaceholder = null;
                        draggedContent = null;
                        originalContentCell = null;
                        if (placeholder && placeholder.parentNode) { placeholder.parentNode.removeChild(placeholder); }
                        placeholder = null;
                        if (dragGhost && dragGhost.parentNode) {
                            dragGhost.parentNode.removeChild(dragGhost);
                        }
                        dragGhost = null;
                        lastAfterElement = null;
                        // 更新排序值
                        updateOrderValues();
                        // 只在开发模式下输出调试信息
                        if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                            console.debug('[cosmdl] dragend cleanup');
                        }
                    });
                    
                    sortableList.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        if (!draggedItem) return;
                        // 跟随鼠标移动拖拽影像（固定定位，避免页面滚动抖动）
                        if (dragGhost) {
                            const top = (e.clientY - dragStartY);
                            dragGhost.style.top = top + 'px';
                        }
                        // 仅当插入点发生变化时，才执行一次 FLIP 动画，避免频繁重排抖动
                        const afterElement = getDragAfterElement(sortableList, e.clientY);
                        if (afterElement === lastAfterElement) return;
                        // 记录移动前的位置
                        const beforeMap = new Map();
                        const rows = [...sortableList.querySelectorAll('.drive-item')].filter(r => r !== draggedItem);
                        rows.forEach(row => { beforeMap.set(row, row.getBoundingClientRect().top); });
                        // 直接移动被拖拽的原行（保持其在 DOM 中），仅隐藏其可见性
                        if (!afterElement) { sortableList.appendChild(draggedItem); }
                        else { sortableList.insertBefore(draggedItem, afterElement); }
                        lastAfterElement = afterElement;
                        // 执行动画：让被动移动的行平滑过渡到新位置
                        requestAnimationFrame(function(){
                            rows.forEach(row => {
                                const beforeTop = beforeMap.get(row);
                                const afterTop = row.getBoundingClientRect().top;
                                const deltaY = beforeTop - afterTop;
                                if (!isFinite(deltaY) || Math.abs(deltaY) < 1) return;
                                row.style.transform = `translateY(${deltaY}px)`;
                                row.style.transition = 'transform 160ms ease-out';
                                requestAnimationFrame(function(){ row.style.transform = ''; });
                                row.addEventListener('transitionend', function handler(){
                                    row.style.transition = '';
                                    row.removeEventListener('transitionend', handler);
                                });
                            });
                        });
                        // 只在开发模式下输出调试信息
                        if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                            console.debug('[cosmdl] dragover, afterElement changed');
                        }
                    });
                    
                    // 辅助函数：获取拖拽元素应该放置的位置
                    function getDragAfterElement(container, y) {
                        // 忽略占位行，避免插入点计算混乱
                        const draggableElements = [...container.querySelectorAll('.drive-item:not(.dragging):not(.drive-placeholder)')];
                        
                        return draggableElements.reduce((closest, child) => {
                            const box = child.getBoundingClientRect();
                            const offset = y - box.top - box.height / 2;
                            
                            if (offset < 0 && offset > closest.offset) {
                                return { offset: offset, element: child };
                            } else {
                                return closest;
                            }
                        }, { offset: Number.NEGATIVE_INFINITY }).element;
                    }
                    
                    // 更新排序值
                    function updateOrderValues() {
                        const items = document.querySelectorAll('.drive-item');
                        items.forEach((item, index) => {
                            const orderInput = item.querySelector('input.drive-order');
                            if (orderInput) {
                                orderInput.value = index + 1;
                            }
                        });
                    }
                    
                    // 检查名称和别名冲突的函数
                    function checkDriveConflicts(nameToCheck, aliasToCheck = '') {
                        const existingDrives = document.querySelectorAll('.drive-item');
                        let nameConflict = false;
                        let aliasConflict = false;
                        
                        existingDrives.forEach(item => {
                            const nameInput = item.querySelector('input[name*="[label]"]');
                            const aliasInput = item.querySelector('input[name*="[alias]"]');
                            
                            if (nameInput && nameInput.value.trim() === nameToCheck.trim()) {
                                nameConflict = true;
                            }
                            
                            if (aliasToCheck && aliasInput && aliasInput.value.trim() === aliasToCheck.trim()) {
                                aliasConflict = true;
                            }
                        });
                        
                        return {
                            nameConflict: nameConflict,
                            aliasConflict: aliasConflict
                        };
                    }
                    
                    // 生成拼音别名的辅助函数（简化版，主要用于前端预览）
                    function generatePinyinAlias(text) {
                        // 移除常见后缀
                        text = text.replace(/(网盘|云盘|云|盘)$/u, '');
                        // 简单的拼音映射（完整版在后端）
                        const simplePinyin = {
                            '测': 'ce', '试': 'shi', '是': 'shi', '事': 'shi',
                            '的': 'de', '和': 'he', '与': 'yu', '在': 'zai',
                            '一': 'yi', '二': 'er', '三': 'san', '四': 'si',
                            '五': 'wu', '六': 'liu', '七': 'qi', '八': 'ba',
                            '九': 'jiu', '十': 'shi'
                        };
                        let result = '';
                        for (let i = 0; i < text.length; i++) {
                            const char = text[i];
                            if (simplePinyin[char]) {
                                result += simplePinyin[char];
                            } else if (/[a-zA-Z0-9]/.test(char)) {
                                result += char;
                            } else if (/[\u4e00-\u9fa5]/.test(char)) {
                                // 对于未收录的汉字，使用首字母或原字符
                                result += char;
                            }
                        }
                        // 只保留字母数字
                        result = result.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                        return result || 'custom';
                    }
                    
                    // 获取唯一别名的函数
                    function getUniqueAlias(baseAlias) {
                        const existingDrives = document.querySelectorAll('.drive-item');
                        const existingAliases = new Set();
                        
                        existingDrives.forEach(item => {
                            const aliasInput = item.querySelector('input[name*="[alias]"]');
                            if (aliasInput && aliasInput.value.trim()) {
                                existingAliases.add(aliasInput.value.trim());
                            }
                        });
                        
                        // 如果基础别名不存在冲突，直接返回
                        if (!existingAliases.has(baseAlias)) {
                            return baseAlias;
                        }
                        
                        // 检测冲突并添加数字后缀
                        let i = 2;
                        let newAlias = baseAlias + i;
                        while (existingAliases.has(newAlias)) {
                            i++;
                            newAlias = baseAlias + i;
                        }
                        
                        return newAlias;
                    }
                    
                    // 添加自定义网盘按钮
                    document.getElementById('add-drive-btn').addEventListener('click', function() {
                        const driveName = document.getElementById('new-drive-name').value.trim();
                        let driveAlias = document.getElementById('new-drive-alias').value.trim();
                        
                        if (driveName) {
                            // 如果用户没有提供别名，自动生成并确保唯一性
                            if (!driveAlias) {
                                const baseAlias = generatePinyinAlias(driveName);
                                driveAlias = getUniqueAlias(baseAlias);
                            }
                            
                            // 检查冲突
                            const conflicts = checkDriveConflicts(driveName, driveAlias);
                            
                            if (conflicts.nameConflict) {
                                // 显示名称冲突提示
                                showToast('名称冲突："' + driveName + '" 已存在，请使用其他名称！', {error: true});
                                return;
                            }
                            
                            // 再次检查别名冲突（以防在生成过程中有其他用户添加）
                            if (conflicts.aliasConflict) {
                                // 如果自动生成的别名仍有冲突，重新生成
                                const baseAlias = generatePinyinAlias(driveName);
                                driveAlias = getUniqueAlias(baseAlias);
                            }
                            
                            const timestamp = Date.now();
                            const driveKey = 'custom_' + timestamp;
                            
                            const newDrive = document.createElement('tr');
                            newDrive.className = 'drive-item';
                            newDrive.innerHTML = `
                            <td>
                                <span class="dashicons dashicons-menu drive-handle" draggable="false" title="按住拖动排序"></span>
                            </td>
                            <td></td>
                            <td>
                                <div style="display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:0;row-gap:0;min-width:240px">
                                    <label style="display:flex;align-items:center;gap:6px"><span>名称</span><input type="text" name="cosmdl_options[drive_management][${driveKey}][label]" value="${driveName}" /></label>
                                    <span style="display:flex;align-items:center;gap:0;margin-top:0">
                                        <label style="display:flex;align-items:center;gap:6px"><span>别名</span><input type="text" name="cosmdl_options[drive_management][${driveKey}][alias]" value="${driveAlias}" placeholder="用于跳转路径，如 jianguo、aliyun-pan" /></label>
                                        <label class="cosmdl-switch" style="margin-left:12px">
                                            <input type="checkbox" name="cosmdl_options[drive_management][${driveKey}][enabled]" value="yes" checked="checked" />
                                            <span class="cosmdl-slider"></span>
                                        </label>
                                        <span class="remove-drive dashicons dashicons-trash" data-key="${driveKey}"></span>
                                        <input type="hidden" name="cosmdl_options[drive_management][${driveKey}][is_custom]" value="yes" />
                                    </span>
                                </div>
                                <input type="hidden" class="drive-order" name="cosmdl_options[drive_management][${driveKey}][order]" value="${document.querySelectorAll('.drive-item').length + 1}" />
                            </td>
                            `;
                            
                            // 确保作为额外添加，不替换现有网盘
                            sortableList.appendChild(newDrive);
                            // 清空输入框
                            document.getElementById('new-drive-name').value = '';
                            document.getElementById('new-drive-alias').value = '';
                            
                            // 添加删除事件
                            newDrive.querySelector('.remove-drive').addEventListener('click', function() {
                                newDrive.remove();
                                updateOrderValues();
                            });
                            
                            // 为新添加的别名输入框添加冲突检测
                            const aliasInput = newDrive.querySelector('input[name*="[alias]"]');
                            if (aliasInput) {
                                aliasInput.addEventListener('blur', function() {
                                    const aliasValue = this.value.trim();
                                    if (aliasValue) {
                                        const existingDrives = document.querySelectorAll('.drive-item');
                                        let aliasConflict = false;
                                        
                                        existingDrives.forEach(item => {
                                            const otherAliasInput = item.querySelector('input[name*="[alias]"]');
                                            if (otherAliasInput && otherAliasInput !== this && 
                                                otherAliasInput.value.trim() === aliasValue.trim()) {
                                                aliasConflict = true;
                                            }
                                        });
                                        
                                        if (aliasConflict) {
                                            showToast('别名冲突："' + aliasValue + '" 已存在，请使用其他别名！', {error: true});
                                            this.value = '';
                                            this.focus();
                                        }
                                    }
                                });
                            }
                        }
                    });
                    
                    // 添加删除按钮事件
                    document.querySelectorAll('.remove-drive').forEach(button => {
                        button.addEventListener('click', function() {
                            const driveKey = this.getAttribute('data-key');
                            if (!driveKey.startsWith('custom_') && !confirm('确定要隐藏此默认网盘吗？您可以在需要时重新启用它。')) {
                                return;
                            }
                            
                            const driveItem = this.closest('.drive-item');
                            if (driveItem) {
                                driveItem.remove();
                                updateOrderValues();
                            }
                        });
                    });
                });

                // 下载页模块：折叠互斥 + 拖拽排序
                document.addEventListener('DOMContentLoaded', function(){
                  var list = document.getElementById('download-modules-sortable');
                  if (!list) return;

                  // 展开/收起切换：使用事件委托
                  list.addEventListener('click', function(e) {
                      var target = e.target;
                      if (target.nodeType === 3) target = target.parentNode;

                      // 查找被点击的 header
                      var header = target.closest('.cosmdl-module__header');
                      if (!header) return;
                      
                      var item = header.closest('.cosmdl-module');
                      if (!item) return;

                      // 只在开发模式下输出调试信息
                      if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                          console.log('Delegated Click. Target:', target.tagName, target.className);
                      }

                      // 避免在点击把手时触发切换
                      if (target.classList.contains('cosmdl-module__handle')) return;
                      
                      // 避免点击开关/工具区域时触发展开/收起
                      if (target.closest('.cosmdl-module__tools') || target.closest('.cosmdl-switch') || target.tagName === 'INPUT' || target.tagName === 'LABEL') {
                          // 只在开发模式下输出调试信息
                          if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                              console.log('Click ignored due to tools/switch check.');
                          }
                          return;
                      }

                      // 只在开发模式下输出调试信息
                      if (typeof cosmdlDebug !== 'undefined' && cosmdlDebug) {
                          console.log('Toggling expand/collapse.');
                      }
                      // 切换当前模块的展开/收起状态
                      if (item.classList.contains('is-open')) {
                        // 当前模块是展开状态，执行收起
                        item.classList.remove('is-open');
                        header.setAttribute('aria-expanded','false');
                      } else {
                        // 当前模块是收起状态，执行展开
                        item.classList.add('is-open');
                        header.setAttribute('aria-expanded','true');
                      }
                  });

                  /* 旧代码移除
                  list.querySelectorAll('.cosmdl-module').forEach(function(item){
                    // ...
                  });
                  */

                  // 默认全部收起
                  list.querySelectorAll('.cosmdl-module').forEach(function(m){ m.classList.remove('is-open'); m.querySelector('.cosmdl-module__header')?.setAttribute('aria-expanded','false'); });

                  // 拖拽排序：仅通过把手触发，采用指针事件，避免原生DnD的虚线框
                  var dragged = null, ghost = null, startY = 0, candidate = null, pointerDragging = false, lastAfter = null;

                  function startModuleDrag(handle, clientY){
                    var item = handle.closest('.cosmdl-module');
                    if (!item) return;
                    var rect = item.getBoundingClientRect();
                    startY = clientY - rect.top;
                    candidate = { item: item, rect: rect, startY: clientY };
                    dragged = item;
                  }

                  function moveModuleDrag(clientY){
                    if (!pointerDragging && candidate && dragged){
                      var delta = Math.abs(clientY - candidate.startY);
                      if (delta >= 6){
                        ghost = document.createElement('div');
                        ghost.className = 'drag-ghost';
                        ghost.style.width = candidate.rect.width + 'px';
                        ghost.style.left = candidate.rect.left + 'px';
                        ghost.style.top = candidate.rect.top + 'px';
                        ghost.textContent = dragged.querySelector('.cosmdl-module__title')?.textContent || '';
                        document.body.appendChild(ghost);
                        dragged.classList.add('dragging');
                        pointerDragging = true;
                        candidate = null;
                        try{ document.body.classList.add('cosmdl-dragging-cursor'); }catch(_){}
                      }
                    }
                    if (!pointerDragging || !dragged) return;
                    if (ghost){ ghost.style.top = (clientY - startY) + 'px'; }

                    var after = (function(container, y){
                      var els = [].slice.call(container.querySelectorAll('.cosmdl-module:not(.dragging)'));
                      var swaps = els.filter(function(el){ return y < el.getBoundingClientRect().top + el.offsetHeight/2; });
                      return swaps.length ? swaps[0] : null;
                    })(list, clientY);

                    if (after === lastAfter) return;
                    var beforeMap = new Map();
                    [].slice.call(list.querySelectorAll('.cosmdl-module')).filter(function(el){ return el !== dragged; }).forEach(function(el){ beforeMap.set(el, el.getBoundingClientRect().top); });
                    if (!after) { list.appendChild(dragged); } else { list.insertBefore(dragged, after); }
                    lastAfter = after;
                    requestAnimationFrame(function(){
                      beforeMap.forEach(function(beforeTop, el){
                        var afterTop = el.getBoundingClientRect().top;
                        var dy = beforeTop - afterTop;
                        if (!isFinite(dy) || Math.abs(dy) < 1) return;
                        el.style.transform = 'translateY(' + dy + 'px)';
                        el.style.transition = 'transform 150ms ease-out';
                        requestAnimationFrame(function(){ el.style.transform = ''; });
                        el.addEventListener('transitionend', function h(){ el.style.transition=''; el.removeEventListener('transitionend', h); });
                      });
                    });
                  }

                  function endModuleDrag(){
                    if (!pointerDragging){ candidate = null; return; }
                    if (dragged){ dragged.classList.remove('dragging'); }
                    try{ document.body.classList.remove('cosmdl-dragging-cursor'); }catch(_){}
                    if (ghost && ghost.parentNode){ ghost.parentNode.removeChild(ghost); }
                    ghost = null; dragged = null; candidate = null; pointerDragging = false; lastAfter = null;

                    // 标记为未保存：触发隐藏字段 change 事件
                    var hidden = list.querySelector('input[name="cosmdl_options[download_modules_order][]"]');
                    if (hidden){ try{ hidden.dispatchEvent(new Event('change', { bubbles: true })); }catch(_){} }
                  }

                  list.addEventListener('pointerdown', function(e){
                    if (!(e.target && e.target.classList && e.target.classList.contains('cosmdl-module__handle'))) return;
                    e.preventDefault();
                    startModuleDrag(e.target, e.clientY);
                  });
                  list.addEventListener('pointermove', function(e){ moveModuleDrag(e.clientY); });
                  list.addEventListener('pointerup', endModuleDrag);
                  list.addEventListener('pointercancel', endModuleDrag);
                });

                // 顶部图标按钮：保存与恢复（AJAX，不跳转）
                var form = document.getElementById('cosmdl-settings-form');
                var ajaxNonce = document.getElementById('cosmdl-ajax-nonce') ? document.getElementById('cosmdl-ajax-nonce').value : '';

                function showToast(msg, opts){
                  var tip = document.createElement('div');
                  var isLoading = opts && opts.loading;
                  var isError = opts && opts.error;
                  tip.style.position = 'fixed';
                  tip.style.top = '50%';
                  tip.style.left = '50%';
                  tip.style.transform = 'translate(-50%, -50%)';
                  // 根据提示类型设置不同样式
                  if (isError) {
                    // 错误提示样式（红色警告风格）
                    tip.style.background = '#fff2f0';
                    tip.style.color = '#d93025';
                    tip.style.border = '1px solid #ffccc7';
                  } else {
                    // 普通提示样式（浅蓝风格）
                    tip.style.background = '#f0f7ff';
                    tip.style.color = '#2271b1';
                    tip.style.border = '1px solid #d8e8ff';
                  }
                  tip.style.padding = '12px 18px';
                  tip.style.borderRadius = '10px';
                  tip.style.boxShadow = '0 10px 30px rgba(2, 6, 23, 0.14)';
                  tip.style.zIndex = '100000';
                  tip.style.fontWeight = '600';
                  tip.style.letterSpacing = '.2px';
                  // 自适应内容
                  tip.style.minWidth = '';
                  tip.style.maxWidth = 'min(90vw, 720px)';
                  tip.style.textAlign = 'center';
                  tip.style.display = 'inline-flex';
                  tip.style.alignItems = 'center';
                  tip.style.justifyContent = 'center';
                  tip.style.gap = '10px';
                  tip.style.whiteSpace = 'normal';
                  tip.style.wordBreak = 'break-word';
                  // 添加图标
                  var icon = document.createElement('span');
                  icon.className = 'dashicons';
                  icon.style.fontSize = '18px';
                  if (isError) {
                    icon.className += ' dashicons-warning';
                    icon.style.color = '#d93025';
                  } else if (isLoading) {
                    icon.className += ' dashicons-update';
                    icon.style.color = '#2271b1';
                  } else {
                    icon.className += ' dashicons-info';
                    icon.style.color = '#2271b1';
                  }
                  tip.appendChild(icon);
                  // 添加文本
                  var text = document.createElement('span');
                  text.textContent = msg || '';
                  tip.appendChild(text);
                  // 加载动画
                  if(isLoading){
                    icon.style.animation = 'cosmdl-spin .8s linear infinite';
                    var style = document.createElement('style');
                    style.textContent = '@keyframes cosmdl-spin{to{transform:rotate(360deg)}}';
                    document.head.appendChild(style);
                  }
                  document.body.appendChild(tip);
                  
                  // 设置自动消失
                  setTimeout(function() {
                    tip.style.transition = 'opacity 0.3s ease-out';
                    tip.style.opacity = '0';
                    setTimeout(function() {
                      tip.remove();
                    }, 300);
                  }, isError ? 3000 : 2000); // 错误提示显示时间更长
                  if(!isLoading){ setTimeout(function(){ tip.remove(); }, 1800); }
                  return tip;
                }

                function setToastSuccess(tip, msg){
                  if(!tip) return;
                  tip.innerHTML = '';
                  // 与右下角未保存提醒外观一致（浅蓝风格）
                  tip.style.background = '#f0f7ff';
                  tip.style.border = '1px solid #d8e8ff';
                  tip.style.color = '#2271b1';
                  // 绿色对号图标，增强成功的视觉提示
                  var icon = document.createElement('span');
                  icon.textContent = '✔';
                  icon.setAttribute('aria-hidden','true');
                  icon.style.color = '#16a34a'; /* green-600 */
                  icon.style.fontWeight = '800';
                  icon.style.fontSize = '18px';
                  icon.style.lineHeight = '1';
                  tip.appendChild(icon);
                  var text = document.createElement('span');
                  text.textContent = msg || '操作成功';
                  tip.appendChild(text);
                  setTimeout(function(){ tip.remove(); }, 1800);
                }

                function ajaxPost(action, onSuccess, onFail, onFinally){
                  if (!ajaxNonce){
                    if (typeof onFail === 'function') { onFail('缺少安全校验参数，请刷新页面重试'); }
                    if (typeof onFinally === 'function') { onFinally(); }
                    return;
                  }
                  var fd = new FormData(form);
                  fd.append('action', action);
                  fd.append('cosmdl_ajax_nonce', ajaxNonce);

                  fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){
                      return r.text().then(function(text){
                        return { ok: r.ok, status: r.status, text: text };
                      });
                    })
                    .then(function(res){
                      var j = null;
                      try { j = JSON.parse(res.text || ''); } catch(e) { j = null; }
                      if (!j) {
                        throw new Error('invalid_json');
                      }
                      if (j && j.success) {
                        if (typeof onSuccess === 'function') { onSuccess(j); }
                        return;
                      }
                      var msg = (j && j.data) ? j.data : '操作失败';
                      if (typeof onFail === 'function') { onFail(msg); }
                    })
                    .catch(function(){
                      if (typeof onFail === 'function') { onFail('网络异常，请重试'); }
                    })
                    .finally(function(){
                      if (typeof onFinally === 'function') { onFinally(); }
                    });
                }

                var saveBtn = document.getElementById('cosmdl-save-btn');
                var resetBtnTop = document.getElementById('cosmdl-reset-btn');
                var exportBtn = document.getElementById('cosmdl-export-btn');
                var importBtn = document.getElementById('cosmdl-import-btn');
                var importFile = document.getElementById('cosmdl-import-file');
                // 未保存提醒：检测到表单更改后显示提示条；保存或重置后隐藏
                var unsavedBar = document.getElementById('cosmdl-unsaved-notice');
                var unsavedSave = document.getElementById('cosmdl-unsaved-save');
                var isDirty = false;
                function showUnsaved(){ if(unsavedBar){ unsavedBar.classList.add('is-visible'); } }
                function hideUnsaved(){ if(unsavedBar){ unsavedBar.classList.remove('is-visible'); } }
                function markDirty(){ isDirty = true; showUnsaved(); }
                // 监听表单所有字段的变化（输入与选择）
                if(form){
                  var fields = form.querySelectorAll('input, select, textarea');
                  fields.forEach(function(el){
                    var evt = (el.tagName === 'TEXTAREA' || (el.type && el.type.toLowerCase() === 'text')) ? 'input' : 'change';
                    el.addEventListener(evt, function(){ markDirty(); });
                  });
                }
                if(unsavedSave){
                  unsavedSave.addEventListener('click', function(){ if(saveBtn){ saveBtn.click(); } });
                }
                function setBusy(btn, busy){ if(!btn) return; btn.disabled = !!busy; btn.style.opacity = busy ? '.7' : '1'; btn.setAttribute('aria-busy', busy ? 'true':'false'); }

                if(exportBtn){
                  exportBtn.addEventListener('click', function(){
                    if(!ajaxNonce){ alert('缺少安全校验参数，请刷新页面重试'); return; }
                    var url = ajaxurl + '?action=cosmdl_export_options&cosmdl_ajax_nonce=' + encodeURIComponent(ajaxNonce);
                    window.location.href = url;
                  });
                }

                if(importBtn && importFile){
                  importBtn.addEventListener('click', function(){
                    importFile.value = '';
                    importFile.click();
                  });

                  importFile.addEventListener('change', function(){
                    var file = importFile.files && importFile.files[0];
                    if(!file) return;
                    if(file.size > (2 * 1024 * 1024)){
                      showToast('配置文件过大（建议小于 2MB）', {error:true});
                      return;
                    }
                    var reader = new FileReader();
                    reader.onload = function(){
                      var text = String(reader.result || '');
                      try{ JSON.parse(text); }catch(e){ showToast('配置文件不是有效的 JSON', {error:true}); return; }
                      setBusy(exportBtn, true);
                      setBusy(importBtn, true);
                      setBusy(saveBtn, true);
                      setBusy(resetBtnTop, true);
                      if(unsavedSave){ unsavedSave.disabled = true; }
                      var t = showToast('正在导入配置…', {loading:true});
                      var fd = new FormData();
                      fd.append('action', 'cosmdl_import_options');
                      fd.append('cosmdl_ajax_nonce', ajaxNonce);
                      fd.append('payload', text);
                      fetch(ajaxurl, { method:'POST', body:fd, credentials:'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(j){
                          if(j && j.success){
                            setToastSuccess(t, (j.data && j.data.message) ? j.data.message : '已导入配置');
                            isDirty = false;
                            hideUnsaved();
                            setTimeout(function(){
                              try {
                                var u = new URL(location.href);
                                var active = 'global-settings';
                                var saved = localStorage.getItem('cosmdl_active_tab');
                                if(saved){ active = saved; }
                                u.searchParams.set('cosmdl_active_tab', active);
                                location.href = u.toString();
                              } catch(err) {
                                location.reload();
                              }
                            }, 900);
                            return;
                          }
                          showToast((j && j.data) ? j.data : '导入失败', {error:true});
                          setBusy(exportBtn, false);
                          setBusy(importBtn, false);
                          setBusy(saveBtn, false);
                          setBusy(resetBtnTop, false);
                          if(unsavedSave){ unsavedSave.disabled = false; }
                        })
                        .catch(function(){
                          showToast('网络异常，请重试', {error:true});
                          setBusy(exportBtn, false);
                          setBusy(importBtn, false);
                          setBusy(saveBtn, false);
                          setBusy(resetBtnTop, false);
                          if(unsavedSave){ unsavedSave.disabled = false; }
                        });
                    };
                    reader.onerror = function(){ showToast('读取文件失败', {error:true}); };
                    reader.readAsText(file);
                  });
                }
                // 当切换“显示二维码区块”时，自动保存，避免未点击保存造成编辑页显示不一致
                var qrToggleAuto = document.querySelector('input[name="cosmdl_options[show_qr_block]"]');
                if(qrToggleAuto){
                  qrToggleAuto.addEventListener('change', function(){
                    // 仅进行跨标签页状态同步，不触发保存请求，避免不必要的服务端负载
                    try { localStorage.setItem('cosmdl_show_qr_block', (qrToggleAuto.checked ? 'yes' : 'no') + '|' + Date.now()); } catch(e){}
                    // 用户要求：取消“尚未保存”的提醒提示
                  });
                }
                if(saveBtn){
                  saveBtn.addEventListener('click', function(){
                    setBusy(saveBtn, true); setBusy(resetBtnTop, true);
                    if(unsavedSave){ unsavedSave.disabled = true; }
                    var t = showToast('正在保存更改…', {loading:true});
                    ajaxPost(
                      'cosmdl_save_options',
                      function(){
                        setToastSuccess(t, '已保存更改');
                        isDirty = false;
                        hideUnsaved();
                      },
                      function(msg){
                        try { if (t) { t.remove(); } } catch(e) {}
                        showToast(msg || '保存失败', {error:true});
                      },
                      function(){
                        setBusy(saveBtn, false);
                        setBusy(resetBtnTop, false);
                        if(unsavedSave){ unsavedSave.disabled = false; }
                      }
                    );
                  });
                }
                if(resetBtnTop){
                      resetBtnTop.addEventListener('click', function(){
                        // 显示恢复默认值弹窗
                        var modal = document.getElementById('cosmdl-reset-modal');
                        var confirmBtn = document.getElementById('cosmdl-reset-confirm');
                        var cancelBtn = document.getElementById('cosmdl-reset-cancel');
                        
                        // 显示弹窗
                        modal.classList.add('is-visible');
                        modal.setAttribute('aria-hidden', 'false');
                        
                        // 确认按钮点击事件
                        var handleConfirm = function(){
                          // 隐藏弹窗
                          modal.classList.remove('is-visible');
                          modal.setAttribute('aria-hidden', 'true');
                          
                          // 执行恢复默认值操作
                          setBusy(saveBtn, true); setBusy(resetBtnTop, true);
                          var t = showToast('正在恢复默认…', {loading:true});
                          var resetOk = false;
                          ajaxPost(
                            'cosmdl_reset_options',
                            function(){
                              resetOk = true;
                            var qrToggle = document.querySelector('input[name="cosmdl_options[show_qr_block]"]');
                            if(qrToggle){ qrToggle.checked = false; }
                            try { localStorage.setItem('cosmdl_show_qr_block', 'no|' + Date.now()); } catch(e){}
                            setToastSuccess(t, '已恢复默认值');
                            isDirty = false; hideUnsaved(); if(unsavedSave){ unsavedSave.disabled = false; }
                            // 刷新页面以显示默认网盘配置，同时保持当前抽屉不跳回“网盘管理”
                            setTimeout(function() {
                              try {
                                var u = new URL(location.href);
                                var active = 'stats';
                                var saved = localStorage.getItem('cosmdl_active_tab');
                                if(saved){ active = saved; }
                                u.searchParams.set('cosmdl_active_tab', active);
                                location.href = u.toString();
                              } catch(err) {
                                location.reload();
                              }
                            }, 600);
                            },
                            function(msg){
                              try { if (t) { t.remove(); } } catch(e) {}
                              showToast(msg || '恢复失败', {error:true});
                            },
                            function(){
                              if (!resetOk) {
                                setBusy(saveBtn, false);
                                setBusy(resetBtnTop, false);
                                if(unsavedSave){ unsavedSave.disabled = false; }
                              }
                            }
                          );
                        };
                        
                        // 取消按钮点击事件
                        var handleCancel = function(){
                          // 隐藏弹窗
                          modal.classList.remove('is-visible');
                          modal.setAttribute('aria-hidden', 'true');
                        };
                        
                        // 点击遮罩层关闭弹窗
                        var handleOverlayClick = function(e){
                          if(e.target === modal){
                            handleCancel();
                          }
                        };
                        
                        // 按ESC键关闭弹窗
                        var handleEsc = function(e){
                          if(e.key === 'Escape'){
                            handleCancel();
                          }
                        };
                        
                        // 清理事件监听器的函数
                        var cleanup = function(){
                          confirmBtn.removeEventListener('click', handleConfirm);
                          cancelBtn.removeEventListener('click', handleCancel);
                          modal.removeEventListener('click', handleOverlayClick);
                          document.removeEventListener('keydown', handleEsc);
                        };
                        
                        // 添加事件监听
                        confirmBtn.addEventListener('click', handleConfirm);
                        cancelBtn.addEventListener('click', handleCancel);
                        modal.addEventListener('click', handleOverlayClick);
                        document.addEventListener('keydown', handleEsc);
                      });
                    }
            })();
            </script>
        </div>
        <?php
    }

    /**
     * 中文注释：清理与校验设置项
     */
    /**
     * 清理与校验设置项
     * @param array $opts 来自表单提交的 cosmdl_options（可能不完整，仅包含当前标签页字段）
     * @return array 经过校验/过滤后的完整设置（基于现有值增量更新）
     * 设计要点：
     * - 增量更新：避免跨标签保存造成未提交字段被清空；
     * - 复选框统一规范：未选中时提交隐藏输入的 'no'；
     * - 文本/HTML 内容做基本清理（sanitize_text_field / wp_kses_post / esc_url_raw）；
     * - 自定义 CSS 等富文本采用 WP 允许的安全标签集合；
     * - 路由前缀、文件树路径等进行格式修正。
     */
	public function sanitize_options($opts, $base = null){
        // 为避免跨标签保存时清空未提交的设置，这里基于已存在的选项进行增量更新
        // 先读取已保存的旧值作为基础（这些值已在首次保存或默认值阶段完成过清理）
        // 注意：get_options 为实例方法，不能通过 self:: 静态调用，避免在部分 PHP 版本下触发非静态方法静态调用的致命错误
		$existing = is_array($base) ? $base : $this->get_options();
        $clean = is_array($existing) ? $existing : array();

        // 中文注释：复选框类选项仅在提交中存在时才更新，否则保留旧值
        $checkboxes = array('plugin_active','show_statement','show_ad_slot','show_custom_links','show_qr_block','show_download_tips','show_owner_statement','metabox_collapsed','card_shadow','enable_tree','tree_open_links_in_new_window','show_fileinfo','show_pan_cards','file_info_card_shadow','enable_logging','debug_mode','stats_ip_geo');
        foreach($checkboxes as $k){
            // 仅当传入值严格为 'yes' 时记为开启；其余（包括 'no'、空、缺失）均不修改或视为关闭
            if (array_key_exists($k, $opts)) {
                $clean[$k] = ($opts[$k] === 'yes') ? 'yes' : 'no';
            } else {
                // 若本次未提交该字段，则保留旧值（避免切换到其他选项卡保存时被重置为 'no'）
                if (!isset($clean[$k])) { $clean[$k] = 'no'; }
            }
        }
        // 移除调试日志：不记录复选项处理状态
		$texts = array('qr_image_url','download_tips_title','owner_statement_title','wechat_appid','wechat_appsecret','qr_follow_text','text_color','tip_color','tip_bg_color','warning_color','warning_bg_color','statement_title','file_info_title','file_info_border_color','file_info_bg_color','file_info_title_color','file_info_text_color','pan_cards_title','custom_links_title','statement_border_color','statement_bg_color','statement_title_color','statement_text_color','pan_cards_border_color','pan_cards_bg_color','pan_cards_title_color','pan_cards_text_color','download_tips_border_color','download_tips_bg_color','download_tips_title_color','download_tips_text_color','owner_statement_border_color','owner_statement_bg_color','owner_statement_title_color','owner_statement_text_color');
        foreach($texts as $k){
            if (isset($opts[$k])) {
                $clean[$k] = sanitize_text_field($opts[$k]);
            } else if (!isset($clean[$k])) {
                // 未提交且旧值不存在时，落入默认空串
                $clean[$k] = '';
            }
        }

		if (array_key_exists('qr_image_url', $opts)) {
			$raw_qr_url = (string) $opts['qr_image_url'];
			$san_qr_url = esc_url_raw($raw_qr_url);
			if ($san_qr_url !== '' || trim($raw_qr_url) === '') {
				$clean['qr_image_url'] = $san_qr_url;
			} else {
				$clean['qr_image_url'] = isset($existing['qr_image_url']) ? $existing['qr_image_url'] : (isset($clean['qr_image_url']) ? $clean['qr_image_url'] : '');
			}
		}
        // 富文本/HTML 内容（下载说明与站长声明）
        $htmls = array('ad_html','download_tips_html','owner_statement_html','pan_cards_html','statement_text');
        foreach($htmls as $k){
            if (isset($opts[$k])){
                $clean[$k] = wp_kses_post($opts[$k]);
            } else if (!isset($clean[$k])) {
                $clean[$k] = '';
            }
        }

        $eh = isset($opts['error_handling']) ? sanitize_text_field($opts['error_handling']) : (isset($clean['error_handling']) ? $clean['error_handling'] : 'message');
        if (!in_array($eh, array('message', 'hide', 'redirect'), true)) {
            $eh = 'message';
        }
        $clean['error_handling'] = $eh;

        // 中文注释：下载统计 - IP 归属地解析服务
        $geo_provider = isset($opts['stats_ip_geo_provider']) ? sanitize_text_field($opts['stats_ip_geo_provider']) : (isset($clean['stats_ip_geo_provider']) ? $clean['stats_ip_geo_provider'] : 'ipapi');
        if (!in_array($geo_provider, array('ipapi', 'ip-api', 'ipinfo'), true)) {
            $geo_provider = 'ipapi';
        }
        $clean['stats_ip_geo_provider'] = $geo_provider;

        // 中文注释：下载统计 - IP 归属地缓存小时数
        if (array_key_exists('stats_ip_geo_cache_hours', $opts)) {
            $h = intval($opts['stats_ip_geo_cache_hours']);
            if ($h < 1) { $h = 1; }
            if ($h > 720) { $h = 720; }
            $clean['stats_ip_geo_cache_hours'] = $h;
        } else if (!isset($clean['stats_ip_geo_cache_hours'])) {
            $clean['stats_ip_geo_cache_hours'] = 168;
        }

        // 下载页模块排序
        $allowed_modules = array('statement','fileinfo','custom_links','pan_cards','download_tips','owner_statement');
        if (isset($opts['download_modules_order']) && is_array($opts['download_modules_order'])){
            $order = array();
            foreach($opts['download_modules_order'] as $mk){
                $mk = sanitize_text_field($mk);
                if (in_array($mk, $allowed_modules, true)) { $order[] = $mk; }
            }
            // 去重并保留顺序
            $order = array_values(array_unique($order));
            // 补齐缺失模块
            foreach($allowed_modules as $m){ if(!in_array($m, $order, true)) $order[] = $m; }
            $clean['download_modules_order'] = $order;
        } else if (!isset($clean['download_modules_order'])) {
            $clean['download_modules_order'] = $allowed_modules;
        }
        // 自定义CSS
		$clean['custom_css'] = isset($opts['custom_css']) ? wp_strip_all_tags($opts['custom_css']) : (isset($clean['custom_css']) ? $clean['custom_css'] : '');
		$clean['statement_custom_css'] = isset($opts['statement_custom_css']) ? wp_strip_all_tags($opts['statement_custom_css']) : (isset($clean['statement_custom_css']) ? $clean['statement_custom_css'] : '');
		$clean['file_info_custom_css'] = isset($opts['file_info_custom_css']) ? wp_strip_all_tags($opts['file_info_custom_css']) : (isset($clean['file_info_custom_css']) ? $clean['file_info_custom_css'] : '');
		$clean['pan_cards_custom_css'] = isset($opts['pan_cards_custom_css']) ? wp_strip_all_tags($opts['pan_cards_custom_css']) : (isset($clean['pan_cards_custom_css']) ? $clean['pan_cards_custom_css'] : '');
		$clean['download_tips_custom_css'] = isset($opts['download_tips_custom_css']) ? wp_strip_all_tags($opts['download_tips_custom_css']) : (isset($clean['download_tips_custom_css']) ? $clean['download_tips_custom_css'] : '');
		$clean['owner_statement_custom_css'] = isset($opts['owner_statement_custom_css']) ? wp_strip_all_tags($opts['owner_statement_custom_css']) : (isset($clean['owner_statement_custom_css']) ? $clean['owner_statement_custom_css'] : '');

		$color_keys = array(
			'text_color', 'tip_color', 'tip_bg_color', 'warning_color', 'warning_bg_color',
			'statement_border_color', 'statement_bg_color', 'statement_title_color', 'statement_text_color',
			'custom_links_border_color', 'custom_links_bg_color', 'custom_links_title_color', 'custom_links_text_color',
			'pan_cards_border_color', 'pan_cards_bg_color', 'pan_cards_title_color', 'pan_cards_text_color',
			'download_tips_border_color', 'download_tips_bg_color', 'download_tips_title_color', 'download_tips_text_color',
			'owner_statement_border_color', 'owner_statement_bg_color', 'owner_statement_title_color', 'owner_statement_text_color',
			'file_info_border_color', 'file_info_bg_color', 'file_info_title_color', 'file_info_text_color',
		);
		foreach ($color_keys as $k) {
			if (array_key_exists($k, $opts)) {
				$v = trim((string) $opts[$k]);
				if ($v === '') {
					$clean[$k] = '';
				} else {
					$c = sanitize_hex_color($v);
					if ($c !== null) {
						$clean[$k] = $c;
					} else {
						$clean[$k] = isset($existing[$k]) ? $existing[$k] : (isset($clean[$k]) ? $clean[$k] : '');
					}
				}
			} else if (!isset($clean[$k])) {
				$clean[$k] = '';
			}
		}

		$hex_color_keys = array(
			'statement_border_color_hex', 'statement_bg_color_hex', 'statement_title_color_hex', 'statement_text_color_hex',
			'custom_links_border_color_hex', 'custom_links_bg_color_hex', 'custom_links_title_color_hex', 'custom_links_text_color_hex',
			'pan_cards_border_color_hex', 'pan_cards_bg_color_hex', 'pan_cards_title_color_hex', 'pan_cards_text_color_hex',
			'download_tips_border_color_hex', 'download_tips_bg_color_hex', 'download_tips_title_color_hex', 'download_tips_text_color_hex',
			'owner_statement_border_color_hex', 'owner_statement_bg_color_hex', 'owner_statement_title_color_hex', 'owner_statement_text_color_hex',
		);
		foreach ($hex_color_keys as $k) {
			if (array_key_exists($k, $opts)) {
				$v = trim((string) $opts[$k]);
				if ($v === '') {
					$clean[$k] = '';
				} else {
					$c = sanitize_hex_color($v);
					$clean[$k] = ($c !== null) ? $c : '';
				}
			} else if (!isset($clean[$k])) {
				$clean[$k] = '';
			}
		}

		$color_pairs = array(
			'statement_border_color'        => 'statement_border_color_hex',
			'statement_bg_color'            => 'statement_bg_color_hex',
			'statement_title_color'         => 'statement_title_color_hex',
			'statement_text_color'          => 'statement_text_color_hex',
			'custom_links_border_color'     => 'custom_links_border_color_hex',
			'custom_links_bg_color'         => 'custom_links_bg_color_hex',
			'custom_links_title_color'      => 'custom_links_title_color_hex',
			'custom_links_text_color'       => 'custom_links_text_color_hex',
			'pan_cards_border_color'        => 'pan_cards_border_color_hex',
			'pan_cards_bg_color'            => 'pan_cards_bg_color_hex',
			'pan_cards_title_color'         => 'pan_cards_title_color_hex',
			'pan_cards_text_color'          => 'pan_cards_text_color_hex',
			'download_tips_border_color'    => 'download_tips_border_color_hex',
			'download_tips_bg_color'        => 'download_tips_bg_color_hex',
			'download_tips_title_color'     => 'download_tips_title_color_hex',
			'download_tips_text_color'      => 'download_tips_text_color_hex',
			'owner_statement_border_color'  => 'owner_statement_border_color_hex',
			'owner_statement_bg_color'      => 'owner_statement_bg_color_hex',
			'owner_statement_title_color'   => 'owner_statement_title_color_hex',
			'owner_statement_text_color'    => 'owner_statement_text_color_hex',
		);
		foreach ($color_pairs as $base_key => $hex_key) {
			if (array_key_exists($hex_key, $opts)) {
				$v = trim((string) $opts[$hex_key]);
				if ($v !== '') {
					$c = sanitize_hex_color($v);
					if ($c !== null) {
						$clean[$base_key] = $c;
						$clean[$hex_key] = $c;
					}
				}
			}
		}
        // 中文注释：链接标题与地址
        for($i=1;$i<=4;$i++){
            $lk = 'custom_link_'.$i.'_label';
            $lu = 'custom_link_'.$i.'_url';
            $clean[$lk] = isset($opts[$lk]) ? sanitize_text_field($opts[$lk]) : (isset($clean[$lk]) ? $clean[$lk] : '');
            $clean[$lu] = isset($opts[$lu]) ? esc_url_raw($opts[$lu]) : (isset($clean[$lu]) ? $clean[$lu] : '');
        }
        
        // 自定义链接外观设置清理
        // 自定义链接自定义CSS
        $clean['custom_links_custom_css'] = isset($opts['custom_links_custom_css']) ? wp_strip_all_tags($opts['custom_links_custom_css']) : (isset($clean['custom_links_custom_css']) ? $clean['custom_links_custom_css'] : '');
		// 解锁与外观
		$allowed_unlock_modes = array('static', 'wechat', 'group');
		$unlock_mode = isset($opts['qr_unlock_mode']) ? sanitize_text_field($opts['qr_unlock_mode']) : (isset($clean['qr_unlock_mode']) ? $clean['qr_unlock_mode'] : 'static');
		if (!in_array($unlock_mode, $allowed_unlock_modes, true)) {
			$unlock_mode = 'static';
		}
		$clean['qr_unlock_mode'] = $unlock_mode;

		$allowed_themes = array('green', 'blue', 'red', 'purple', 'orange', 'pink', 'gray', 'indigo', 'teal');
		$allowed_radius = array('none', 'small', 'medium', 'large');

		$card_theme = isset($opts['card_theme']) ? sanitize_text_field($opts['card_theme']) : '';
		if ($card_theme === '' && isset($opts['card_theme_color'])) {
			$card_theme = sanitize_text_field($opts['card_theme_color']);
		}
		if ($card_theme === '') {
			$card_theme = isset($clean['card_theme']) ? $clean['card_theme'] : 'green';
		}
		if (!in_array($card_theme, $allowed_themes, true)) {
			$card_theme = isset($existing['card_theme']) ? $existing['card_theme'] : 'green';
			if (!in_array($card_theme, $allowed_themes, true)) {
				$card_theme = 'green';
			}
		}
		$clean['card_theme'] = $card_theme;

		$card_radius = isset($opts['card_border_radius']) ? sanitize_text_field($opts['card_border_radius']) : '';
		if ($card_radius === '' && isset($opts['card_radius'])) {
			$card_radius = sanitize_text_field($opts['card_radius']);
		}
		if ($card_radius === '') {
			$card_radius = isset($clean['card_border_radius']) ? $clean['card_border_radius'] : 'medium';
		}
		if (!in_array($card_radius, $allowed_radius, true)) {
			$card_radius = isset($existing['card_border_radius']) ? $existing['card_border_radius'] : 'medium';
			if (!in_array($card_radius, $allowed_radius, true)) {
				$card_radius = 'medium';
			}
		}
		$clean['card_border_radius'] = $card_radius;
		$clean['card_theme_color'] = $card_theme;
		$clean['card_radius'] = $card_radius;
        // 文件信息局部外观设置（未提交时保留旧值或使用默认值）
		$file_info_theme = isset($opts['file_info_card_theme']) ? sanitize_text_field($opts['file_info_card_theme']) : (isset($clean['file_info_card_theme']) ? $clean['file_info_card_theme'] : 'blue');
		if (!in_array($file_info_theme, $allowed_themes, true)) {
			$file_info_theme = isset($existing['file_info_card_theme']) ? $existing['file_info_card_theme'] : 'blue';
			if (!in_array($file_info_theme, $allowed_themes, true)) {
				$file_info_theme = 'blue';
			}
		}
		$clean['file_info_card_theme'] = $file_info_theme;

		$file_info_radius = isset($opts['file_info_card_border_radius']) ? sanitize_text_field($opts['file_info_card_border_radius']) : (isset($clean['file_info_card_border_radius']) ? $clean['file_info_card_border_radius'] : 'medium');
		if (!in_array($file_info_radius, $allowed_radius, true)) {
			$file_info_radius = isset($existing['file_info_card_border_radius']) ? $existing['file_info_card_border_radius'] : 'medium';
			if (!in_array($file_info_radius, $allowed_radius, true)) {
				$file_info_radius = 'medium';
			}
		}
		$clean['file_info_card_border_radius'] = $file_info_radius;
        // 下载声明、站长声明及其他模块卡片主题色
		$module_theme_keys = array('statement_card_theme', 'owner_statement_card_theme', 'custom_links_card_theme', 'pan_cards_card_theme', 'download_tips_card_theme');
		foreach ($module_theme_keys as $k) {
			$val = isset($opts[$k]) ? sanitize_text_field($opts[$k]) : (isset($clean[$k]) ? $clean[$k] : '');
			if ($val !== '' && !in_array($val, $allowed_themes, true)) {
				$val = '';
			}
			$clean[$k] = $val;
		}
        // 路由前缀（仅用于跳转路由，如 /get），只允许小写字母、数字与短横线
        $rp = isset($opts['route_prefix']) ? strtolower(trim($opts['route_prefix'])) : (isset($clean['route_prefix']) ? $clean['route_prefix'] : 'get');
        $rp = preg_replace('/[^a-z0-9\-]/', '', $rp);
        if ($rp === '') { $rp = 'get'; }
        $clean['route_prefix'] = $rp;
        // 文件树相关选项
        $tv = isset($opts['tree_visibility']) ? sanitize_text_field($opts['tree_visibility']) : (isset($clean['tree_visibility']) ? $clean['tree_visibility'] : 'public');
        if (!in_array($tv, array('public','admin'), true)) { $tv = 'public'; }
        $clean['tree_visibility'] = $tv;
        
        // 网盘管理配置清理（加入 alias 支持）
        $clean['drive_management'] = array();
        $default_drives = cosmdl_get_drive_defaults();
        
        // 确保始终包含所有默认网盘，这是修复恢复默认值问题的关键
        foreach($default_drives as $key => $default_drive) {
            // 先设置默认值
            $clean['drive_management'][$key] = $default_drive;
            
            // 如果有提交的设置，更新它们
            if (isset($opts['drive_management'][$key])) {
                $submitted = $opts['drive_management'][$key];
                $clean['drive_management'][$key]['enabled'] = isset($submitted['enabled']) && $submitted['enabled'] === 'yes' ? 'yes' : 'no';
                // 对于默认网盘，我们不应该允许修改标签，以避免混淆
                // 但为了保持兼容性，仍然保留sanitize_text_field处理
                $clean['drive_management'][$key]['label'] = $default_drive['label']; // 固定使用默认标签
                $clean['drive_management'][$key]['order'] = isset($submitted['order']) ? intval($submitted['order']) : $default_drive['order'];
                // 处理别名：对于默认网盘，我们应该保持其原始别名，不允许修改
                $clean['drive_management'][$key]['alias'] = $default_drive['alias']; // 固定使用默认别名
            }
        }
        
        // 处理自定义网盘
        if (isset($opts['drive_management'])) {
            foreach($opts['drive_management'] as $key => $drive) {
                if (strpos($key, 'custom_') === 0 && isset($drive['is_custom']) && $drive['is_custom'] === 'yes') {
                    $clean['drive_management'][$key] = array(
                        'enabled' => isset($drive['enabled']) && $drive['enabled'] === 'yes' ? 'yes' : 'no',
                        'label' => isset($drive['label']) ? sanitize_text_field($drive['label']) : '',
                        'order' => isset($drive['order']) ? intval($drive['order']) : 999,
                        'is_custom' => 'yes',
                        'alias' => self::sanitize_alias(isset($drive['label']) ? $drive['label'] : '', isset($drive['alias']) ? $drive['alias'] : '')
                    );
                }
            }
        }

        // 兼容性清理：当默认网盘与自定义网盘别名冲突时，优先保留自定义项，移除冲突的默认项，避免出现两个同名网盘
        // 构建别名 -> 键 的映射，并记录是否为自定义
        $alias_index = array();
        foreach($clean['drive_management'] as $k => $d){
            $alias = isset($d['alias']) ? strtolower($d['alias']) : '';
            if ($alias === '') {
                $alias = strtolower(self::sanitize_alias(isset($d['label']) ? $d['label'] : '', $k));
            }
            $is_custom = isset($d['is_custom']) && $d['is_custom'] === 'yes';
            if (!isset($alias_index[$alias])){
                $alias_index[$alias] = array('key' => $k, 'is_custom' => $is_custom);
            } else {
                // 冲突：已有记录优先保留自定义，但确保"其他网盘"(other)不会被移除
                $existing = $alias_index[$alias];
                if ($existing['is_custom'] && !$is_custom){
                    // 当前为默认项，且冲突别名已有自定义 -> 移除当前默认项
                    // 但保留"其他网盘"(other)
                    if ($k !== 'other') {
                        unset($clean['drive_management'][$k]);
                    }
                } elseif (!$existing['is_custom'] && $is_custom){
                    // 当前为自定义项，已有默认项 -> 移除默认项，当前自定义覆盖
                    // 但保留"其他网盘"(other)
                    if ($existing['key'] !== 'other') {
                        unset($clean['drive_management'][$existing['key']]);
                        $alias_index[$alias] = array('key' => $k, 'is_custom' => true);
                    }
                } else {
                    // 同类冲突（两者均默认或均自定义），保留先出现的，移除后者
                    // 但保留"其他网盘"(other)
                    if ($k !== 'other') {
                        unset($clean['drive_management'][$k]);
                    }
                }
            }
        }

        // 别名去重：保证唯一性，如有冲突则追加 -2、-3 后缀
        $seen = array();
        foreach($clean['drive_management'] as $k => $d){
            $alias = isset($d['alias']) ? $d['alias'] : '';
            if ($alias === '') {
                // 再兜底：若为空，优先使用默认别名或 key
                $fallback = isset($default_drives[$k]['alias']) ? $default_drives[$k]['alias'] : $k;
                $alias = self::sanitize_alias(isset($d['label']) ? $d['label'] : '', $fallback);
                $clean['drive_management'][$k]['alias'] = $alias;
            }
            $base = $alias;
            $i = 2;
            while(isset($seen[$alias])){
                $alias = $base . '-' . $i;
                $i++;
            }
            $seen[$alias] = true;
            $clean['drive_management'][$k]['alias'] = $alias;
        }
        
        // 移除调试日志：不记录最终清理后的状态
        return $clean;
    }

    /**
     * AJAX：数据修正工具（标准化文件大小）
     * 作用：遍历所有文章的网盘资源，将文本大小转换为字节值并存储
     */
	public function ajax_fix_file_sizes(){
        // 权限校验
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'cosmautdl')));
        }
        check_ajax_referer('cosmdl_ajax', 'nonce');

        // 引入模板函数以使用 cosmdl_size_to_bytes
        if (!function_exists('cosmdl_size_to_bytes')) {
            require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php';
        }

        // 获取所有文章（考虑到超时风险，设置较大的时间限制）
		$args = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
            'posts_per_page' => -1, // 获取所有文章
            'fields' => 'ids', // 仅获取ID以节省内存
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        );

        $query = new WP_Query($args);
        $posts = $query->posts;
        $count_processed = 0;
        $count_updated = 0;

        foreach ($posts as $pid) {
            $has_update = false;
            // 遍历附件 1-6
            for ($i = 1; $i <= 6; $i++) {
                $prefix = ($i === 1) ? 'cosmdl_' : ('cosmdl' . $i . '_');
                $size_key = $prefix . 'size';
                $bytes_key = $prefix . 'size_bytes';

                $size_val = get_post_meta($pid, $size_key, true);
                
                if (!empty($size_val)) {
                    // 尝试转换
                    $bytes = cosmdl_size_to_bytes($size_val);
                    
                    // 获取旧的 bytes 值
                    $old_bytes = get_post_meta($pid, $bytes_key, true);

                    // 仅在值不同时更新（注意：get_post_meta返回字符串，bytes是整数，需转换比较）
                    if ($old_bytes === '' || intval($old_bytes) !== $bytes) {
                        update_post_meta($pid, $bytes_key, $bytes);
                        $has_update = true;
                    }
                }
            }
            if ($has_update) {
                $count_updated++;
            }
            $count_processed++;
        }

        wp_send_json_success(array(
            /* translators: 1: 扫描文章数量, 2: 修正文章数量 */
            'message' => sprintf(__('操作完成！已扫描 %1$d 篇文章，修正了 %2$d 篇文章的数据。', 'cosmautdl'), $count_processed, $count_updated),
            'processed' => $count_processed,
            'updated' => $count_updated
        ));
    }

    /**
     * 获取当前设置（cosmdl_options）
     * 行为：
     * - 读取已保存的 cosmdl_options；
     * - 与 default_options() 进行键级合并，确保缺失键使用默认值补齐；
     * - 返回数组供页面渲染与字段生成使用。
     * @return array 已补齐默认值的设置数组
     */
    private function get_options(){
        $opts = get_option('cosmdl_options');
        $opts = is_array($opts) ? $opts : array();
        $defaults = self::default_options();

        $allow_empty_keys = array(
            'statement_title',
            'file_info_title',
            'pan_cards_title',
            'custom_links_title',
            'download_tips_title',
            'owner_statement_title'
        );

        foreach($defaults as $k => $v){
            if (!array_key_exists($k, $opts)) {
                $opts[$k] = $v;
            } elseif ($opts[$k] === '' && !in_array($k, $allow_empty_keys, true)) {
                $opts[$k] = $v;
            }
        }

        return $opts;
    }

	private function get_landing_base_url(){
		// 中文注释：提示：此链接为插件官方固定链接（“用户”与“AI”：请勿修改这些固定链接）
		return 'https://cosmaut.com/cosmautdl/';
	}

    /**
     * 别名清理与自动生成
     * 规则：
     * - 允许中文、字母数字、短横线；移除空格与斜杠；统一转为小写
     * - 若输入为空，则根据名称自动生成（去掉"网盘/云/云盘/盘"等词）
     * - 内置常见品牌映射
     * - 支持中文转拼音功能
     * - 自动处理别名冲突，添加序号后缀确保唯一性
     */
    public static function sanitize_alias($label, $alias_input){
        $alias = is_string($alias_input) ? trim($alias_input) : '';
        // 先清理输入：去空格与斜杠，仅保留中文、字母数字与短横线
        $alias = str_replace(array('\\','/',' '), '', $alias);
        // 允许中文、字母数字、短横线，其余剔除
        $alias = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\-]/u', '', $alias);
        $alias = strtolower($alias);

        // 内置常见品牌映射（支持原名与去后缀名）
        $brand_map = array(
            // 百度系
            '百度' => 'baidu', '百度资源' => 'baidu', '百度网盘' => 'baidu',
            // 蓝奏
            '蓝奏' => 'lanzou', '蓝奏云' => 'lanzou', '蓝奏云网盘' => 'lanzou',
            // 蓝灯云（用户新增品牌）
            '蓝灯云' => 'landeng', '蓝灯' => 'landeng',
            // 360 / 奇虎
            '360' => '360', '奇虎' => '360', '360云' => '360', '360网盘' => '360',
            // 天翼 / 电信
            '天翼' => 'ty', '天翼云' => 'ty', '电信云' => 'ty',
            // 城通
            '城通网盘' => 'ct', '城通' => 'ct',
            // 坚果云 / 阿里 / 夸克
            '坚果云' => 'jianguo', '坚果' => 'jianguo',
            '阿里云盘' => 'aliyun', '阿里云' => 'aliyun', '阿里' => 'aliyun',
            '夸克' => 'kuake',
            // 其它常见
            'Google Drive' => 'gdrive', 'OneDrive' => 'onedrive',
            '官方' => 'official', '官网' => 'official',
            '本地' => 'local', '普通' => 'normal', '其他' => 'other'
        );

        if ($alias === ''){
            $orig = is_string($label) ? trim($label) : '';
            $stripped = preg_replace('/(网盘|云盘|云|盘)$/u', '', $orig);
            $stripped = trim($stripped);
            // 先按原名称匹配品牌映射，其次匹配去后缀的名称
            if (isset($brand_map[$orig])){
                $alias = $brand_map[$orig];
            } elseif (isset($brand_map[$stripped])){
                $alias = $brand_map[$stripped];
            } else {
                // 尝试转拼音（有限字典替换），最后移除非 ASCII
                $alias = self::pinyin_slug($stripped !== '' ? $stripped : $orig);
            }
        }
        // 兜底：若仍为空则回退为 'other'
        if ($alias === ''){ $alias = 'other'; }
        return $alias;
    }

    /**
     * 将中文名称转换为拼音 slug
     * 说明：支持常见品牌词映射和汉字转拼音功能
     */
    private static function pinyin_slug($name){
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') return '';
        
        // 先做常见词替换（按长度优先，避免部分词覆盖）
        $dict = array(
            '百度网盘' => 'baidu', '百度' => 'baidu',
            '蓝奏云网盘' => 'lanzou', '蓝奏云' => 'lanzou', '蓝奏' => 'lanzou',
            // 蓝灯云（用户新增品牌）
            '蓝灯云' => 'landeng', '蓝灯' => 'landeng',
            '360网盘' => '360', '360云' => '360', '奇虎' => '360', '360' => '360',
            '天翼云' => 'ty', '天翼' => 'ty', '电信云' => 'ty',
            '城通网盘' => 'ct', '城通' => 'ct',
            '坚果云' => 'jianguo', '坚果' => 'jianguo',
            '阿里云盘' => 'aliyun', '阿里云' => 'aliyun', '阿里' => 'aliyun',
            '夸克' => 'kuake', '微云' => 'weiyun', '腾讯' => 'tencent', '迅雷' => 'xunlei',
            '和彩云' => 'hecai', '和彩' => 'hecai',
            '谷歌云盘' => 'google', '谷歌' => 'google', 'Google Drive' => 'gdrive', 'OneDrive' => 'onedrive',
            '官方' => 'official', '官网' => 'official', '本地' => 'local', '普通' => 'normal', '其他' => 'other'
        );
        
        // 按词典顺序替换
        foreach($dict as $cn => $py){
            if (mb_strpos($name, $cn) !== false){
                $name = str_replace($cn, $py, $name);
            }
        }
        
        // 去除尾部常见后缀
        $name = preg_replace('/(网盘|云盘|云|盘)$/u', '', $name);
        
        // 实现中文转拼音功能
        $name = self::convert_chinese_to_pinyin($name);
        
        // 仅保留字母数字与短横线，转小写
        $slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $name);
        $slug = strtolower($slug);
        
        // 增强：如果处理后slug为空，但原始名称包含中文，使用汉字首字母作为fallback
        if ($slug === '' && preg_match('/[\x{4e00}-\x{9fa5}]/u', $name)) {
            $slug = 'custom-' . substr(md5($name), 0, 6);
        }
        
        // 兜底：返回清理后的 slug
        return $slug;
    }
    
    /**
     * 将中文字符转换为拼音
     * 支持常用汉字转拼音，保留数字和字母
     * 采用多级转换策略：
     * 1. 优先使用拼音映射表（精确匹配）
     * 2. 对于未收录的汉字，尝试使用WordPress内置函数或Unicode编码方式处理
     * 3. 最后使用字符编码作为兜底方案
     */
    private static function convert_chinese_to_pinyin($text) {
        // 常见汉字拼音映射表（扩展可添加更多汉字）
        $pinyin_map = array(
            // 基本汉字
            '一' => 'yi', '二' => 'er', '三' => 'san', '四' => 'si', '五' => 'wu',
            '六' => 'liu', '七' => 'qi', '八' => 'ba', '九' => 'jiu', '十' => 'shi',
            
            // 常用字
            '的' => 'de', '是' => 'shi', '在' => 'zai', '不' => 'bu', '了' => 'le',
            '有' => 'you', '和' => 'he', '人' => 'ren', '我' => 'wo', '他' => 'ta',
            '她' => 'ta', '它' => 'ta', '这' => 'zhe', '那' => 'na', '你' => 'ni',
            '您' => 'nin', '们' => 'men', '来' => 'lai', '去' => 'qu', '说' => 'shuo', '要' => 'yao',
            
            // 测试相关
            '测' => 'ce', '试' => 'shi',
            
            // 新添加常用字
            '新' => 'xin', '旧' => 'jiu', '大' => 'da', '小' => 'xiao', '多' => 'duo',
            '少' => 'shao', '上' => 'shang', '下' => 'xia', '前' => 'qian', '后' => 'hou',
            '左' => 'zuo', '右' => 'you', '高' => 'gao', '低' => 'di', '好' => 'hao',
            '坏' => 'huai', '东' => 'dong', '南' => 'nan', '西' => 'xi', '北' => 'bei',
            '中' => 'zhong', '天' => 'tian', '地' => 'di', '日' => 'ri', '月' => 'yue',
            '星' => 'xing', '云' => 'yun', '水' => 'shui', '火' => 'huo', '山' => 'shan',
            '石' => 'shi', '金' => 'jin', '木' => 'mu', '草' => 'cao', '花' => 'hua',
            '鸟' => 'niao', '兽' => 'shou', '鱼' => 'yu', '虫' => 'chong', '人' => 'ren',
            '手' => 'shou', '足' => 'zu', '口' => 'kou', '目' => 'mu', '耳' => 'er',
            '心' => 'xin', '头' => 'tou', '身' => 'shen', '个' => 'ge', '只' => 'zhi',
            '支' => 'zhi', '本' => 'ben', '张' => 'zhang', '把' => 'ba', '条' => 'tiao',
            '根' => 'gen', '枝' => 'zhi', '颗' => 'ke', '粒' => 'li', '件' => 'jian',
            '台' => 'tai', '个' => 'ge', '位' => 'wei', '名' => 'ming', '家' => 'jia',
            '户' => 'hu', '间' => 'jian', '栋' => 'dong', '座' => 'zuo', '辆' => 'liang',
            '匹' => 'pi', '头' => 'tou', '只' => 'zhi', '块' => 'kuai', '片' => 'pian',
            '张' => 'zhang', '面' => 'mian', '扇' => 'shan', '门' => 'men', '窗' => 'chuang',
            '桌' => 'zhuo', '椅' => 'yi', '床' => 'chuang', '灯' => 'deng', '柜' => 'gui',
            '箱' => 'xiang', '包' => 'bao', '袋' => 'dai', '盒' => 'he', '瓶' => 'ping',
            '杯' => 'bei', '盘' => 'pan', '碗' => 'wan', '筷' => 'kuai', '刀' => 'dao',
            '叉' => 'cha', '勺' => 'shao', '针' => 'zhen', '线' => 'xian', '布' => 'bu',
            '衣' => 'yi', '裤' => 'ku', '鞋' => 'xie', '帽' => 'mao', '袜' => 'wa',
            '巾' => 'jin', '带' => 'dai', '绳' => 'sheng', '索' => 'suo', '链' => 'lian',
            '环' => 'huan', '扣' => 'kou', '钩' => 'gou', '锁' => 'suo', '钥匙' => 'yaoshi',
            '纸' => 'zhi', '笔' => 'bi', '墨' => 'mo', '砚' => 'yan', '书' => 'shu',
            '报' => 'bao', '画' => 'hua', '信' => 'xin', '包' => 'bao', '钱' => 'qian',
            '币' => 'bi', '票' => 'piao', '卡' => 'ka', '证' => 'zheng', '件' => 'jian',
            '章' => 'zhang', '印' => 'yin', '照' => 'zhao', '片' => 'pian', '相' => 'xiang',
            '机' => 'ji', '车' => 'che', '船' => 'chuan', '飞' => 'fei', '机' => 'ji',
            '路' => 'lu', '桥' => 'qiao', '城' => 'cheng', '市' => 'shi', '镇' => 'zhen',
            '村' => 'cun', '区' => 'qu', '县' => 'xian', '省' => 'sheng', '国' => 'guo',
            '洲' => 'zhou', '洋' => 'yang', '海' => 'hai', '河' => 'he', '湖' => 'hu',
            '江' => 'jiang', '溪' => 'xi', '泉' => 'quan', '井' => 'jing', '池' => 'chi',
            '潭' => 'tan', '洞' => 'dong', '港' => 'gang', '湾' => 'wan', '滩' => 'tan',
            '岸' => 'an', '边' => 'bian', '角' => 'jiao', '峰' => 'feng', '岭' => 'ling',
            '坡' => 'po', '谷' => 'gu', '沟' => 'gou', '坑' => 'keng', '洞' => 'dong',
            '房' => 'fang', '屋' => 'wu', '楼' => 'lou', '阁' => 'ge', '亭' => 'ting',
            '台' => 'tai', '塔' => 'ta', '桥' => 'qiao', '门' => 'men', '窗' => 'chuang',
            '墙' => 'qiang', '柱' => 'zhu', '梁' => 'liang', '栋' => 'dong', '瓦' => 'wa',
            '砖' => 'zhuan', '石' => 'shi', '土' => 'tu', '沙' => 'sha', '泥' => 'ni',
            '灰' => 'hui', '尘' => 'chen', '烟' => 'yan', '雾' => 'wu', '云' => 'yun',
            '雨' => 'yu', '雪' => 'xue', '霜' => 'shuang', '露' => 'lu', '冰' => 'bing',
            '风' => 'feng', '雷' => 'lei', '电' => 'dian', '光' => 'guang', '热' => 're',
            '冷' => 'leng', '温' => 'wen', '暖' => 'nuan', '凉' => 'liang', '寒' => 'han',
            '炎' => 'yan', '火' => 'huo', '水' => 'shui', '油' => 'you', '气' => 'qi',
            '汽' => 'qi', '煤' => 'mei', '炭' => 'tan', '木' => 'mu', '材' => 'cai',
            '森' => 'sen', '林' => 'lin', '草' => 'cao', '花' => 'hua', '树' => 'shu',
            '叶' => 'ye', '根' => 'gen', '枝' => 'zhi', '果' => 'guo', '实' => 'shi',
            '种' => 'zhong', '苗' => 'miao', '芽' => 'ya', '藤' => 'teng', '竹' => 'zhu',
            '米' => 'mi', '面' => 'mian', '粮' => 'liang', '食' => 'shi', '菜' => 'cai',
            '果' => 'guo', '肉' => 'rou', '鱼' => 'yu', '蛋' => 'dan', '奶' => 'nai',
            '油' => 'you', '盐' => 'yan', '酱' => 'jiang', '醋' => 'cu', '茶' => 'cha',
            '酒' => 'jiu', '水' => 'shui', '汤' => 'tang', '饭' => 'fan', '菜' => 'cai',
            '餐' => 'can', '饮' => 'yin', '食' => 'shi', '味' => 'wei', '道' => 'dao',
            '香' => 'xiang', '臭' => 'chou', '甜' => 'tian', '酸' => 'suan', '苦' => 'ku',
            '辣' => 'la', '咸' => 'xian', '淡' => 'dan', '涩' => 'se', '滑' => 'hua',
            '腻' => 'ni', '脆' => 'cui', '软' => 'ruan', '硬' => 'ying', '松' => 'song',
            '紧' => 'jin', '干' => 'gan', '湿' => 'shi', '稀' => 'xi', '稠' => 'chou',
            '浓' => 'nong', '淡' => 'dan', '清' => 'qing', '浊' => 'zhuo', '亮' => 'liang',
            '暗' => 'an', '明' => 'ming', '黑' => 'hei', '白' => 'bai', '红' => 'hong',
            '黄' => 'huang', '蓝' => 'lan', '绿' => 'lü', '青' => 'qing', '紫' => 'zi',
            '橙' => 'cheng', '粉' => 'fen', '灰' => 'hui', '金' => 'jin', '银' => 'yin',
            '铜' => 'tong', '铁' => 'tie', '钢' => 'gang', '铝' => 'lü', '锡' => 'xi',
            '铅' => 'qian', '锌' => 'xin', '钛' => 'tai', '汞' => 'gong', '铂' => 'bo',
            '钯' => 'ba', '铑' => 'lao', '金' => 'jin', '银' => 'yin', '铜' => 'tong',
            '铁' => 'tie', '钢' => 'gang', '铝' => 'lü', '锡' => 'xi', '铅' => 'qian',
            '锌' => 'xin', '钛' => 'tai', '汞' => 'gong', '铂' => 'bo', '钯' => 'ba',
            '铑' => 'lao', '号' => 'hao'
            
            
        );
        
        // 初始化结果字符串
        $result = '';
        
        // 遍历文本中的每个字符
        $length = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // 如果是中文字符，尝试转换为拼音
            if (isset($pinyin_map[$char])) {
                $result .= $pinyin_map[$char];
            } 
            // 检查是否为未收录的汉字
            else if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
                // 对于未收录的汉字，使用多级处理策略
                // 1. 尝试使用WordPress内置的sanitize_title_with_dashes函数提取拼音特征
                $temp_pinyin = self::get_pinyin_for_unknown_char($char);
                $result .= $temp_pinyin;
            }
            // 如果是字母或数字，直接保留
            else if (preg_match('/[a-zA-Z0-9]/', $char)) {
                $result .= $char;
            }
            // 如果是短横线，直接保留
            else if ($char === '-') {
                $result .= $char;
            }
            // 其他字符忽略
        }
        
        return $result;
    }
    
    /**
     * 获取未知汉字的拼音或替代表示
     * 采用多种策略处理未收录的汉字
     */
    private static function get_pinyin_for_unknown_char($char) {
        // 策略1: 尝试使用WordPress内置函数的特征
        $temp = sanitize_title_with_dashes($char, '', 'utf8');
        if (!empty($temp) && !preg_match('/[0-9a-f]{2,}/', $temp)) {
            return $temp;
        }
        
        // 策略2: 使用Unicode编码生成唯一标识
        $code = dechex(mb_ord($char, 'UTF-8'));
        // 截取部分编码作为标识符，避免过长
        return 'char' . substr($code, -4);
    }

    /**
     * 获取标准网盘列表（16个）
     * 返回：array('id' => array('label' => '...', 'default_order' => N), ...)
     */
    public static function get_standard_drives(){
        return array(
            'baidu'       => array('label' => '百度网盘',   'default_order' => 1),
            '123'         => array('label' => '123云盘',    'default_order' => 2),
            'ali'         => array('label' => '阿里云盘',   'default_order' => 3),
            '189'         => array('label' => '天翼云盘',   'default_order' => 4),
            'quark'       => array('label' => '夸克网盘',   'default_order' => 5),
            'pikpak'      => array('label' => 'PikPak',     'default_order' => 6),
            'lanzou'      => array('label' => '蓝奏云网盘', 'default_order' => 7),
            'xunlei'      => array('label' => '迅雷云盘',   'default_order' => 8),
            'weiyun'      => array('label' => '微云',       'default_order' => 9),
            'onedrive'    => array('label' => 'OneDrive',   'default_order' => 10),
            'googledrive' => array('label' => 'GoogleDrive','default_order' => 11),
            'dropbox'     => array('label' => 'Dropbox',    'default_order' => 12),
            'mega'        => array('label' => 'MEGA',       'default_order' => 13),
            'mediafire'   => array('label' => 'MediaFire',  'default_order' => 14),
            'box'         => array('label' => 'Box',        'default_order' => 15),
            'other'       => array('label' => '其他网盘',   'default_order' => 16),
        );
    }

    /**
     * 中文注释：默认选项配置
     * 逻辑：使用模块化的默认配置函数获取所有默认值，然后合并为一个完整的配置数组。
     * 说明：从 default-value.php 文件中按模块获取默认值，便于维护和扩展。
     */
    public static function default_options(){
        if (function_exists('cosmdl_get_all_defaults')) {
            return cosmdl_get_all_defaults();
        }

        return array();
    }

    /**
     * 恢复默认设置（处理表单提交版本）
     * 能力要求：manage_options；使用 admin_post 钩子。
     * 安全：使用 wp_nonce_field('cosmdl_reset_defaults') 验证；成功后重置为硬编码出厂默认。
     * 跳转：完成后重定向回设置页并携带 reset=1 参数。
     * @return void
     */
    public function handle_reset_defaults(){
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) wp_die('权限不足');
        check_admin_referer('cosmdl_reset_defaults');
        // 重置新版设置为默认值
        $defaults = self::default_options();
        update_option('cosmdl_options', $defaults, false);

        // 说明：不涉及旧插件设置
        wp_safe_redirect( admin_url('admin.php?page=cosmdl-settings&reset=1') );
        exit;
    }

    /**
     * AJAX：保存设置（不跳转，返回 JSON）
     * 权限：manage_options；安全：nonce = cosmdl_ajax。
     * 请求：POST cosmdl_options[]（可能仅包含当前标签页字段）。
     * 行为：调用 sanitize_options 进行增量清理合并，update_option('cosmdl_options', $clean)。
     * 响应：{ success: true, data: { message: 'saved' } }
     * @return void
     */
	public function ajax_save_options(){
		if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) { wp_send_json_error('权限不足'); }
		$nonce = filter_input(INPUT_POST, 'cosmdl_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$nonce = ($nonce !== null && $nonce !== false) ? sanitize_text_field((string) $nonce) : '';
		if (!wp_verify_nonce($nonce, 'cosmdl_ajax')) { wp_send_json_error('非法请求'); }
		$incoming = filter_input(INPUT_POST, 'cosmdl_options', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$incoming = is_array($incoming) ? wp_unslash($incoming) : array();
		$clean = $this->sanitize_options($incoming);
		// 保存当前设置
		update_option('cosmdl_options', $clean, false);
        // 不再同步站点默认：恢复默认值统一回到硬编码出厂默认
        // 不输出调试日志
        wp_send_json_success(array('message'=>'saved'));
    }

    /**
     * AJAX：恢复默认值（不跳转，返回 JSON）
     * 权限：manage_options；安全：nonce = cosmdl_ajax。
     * 行为：使用 self::default_options() 覆盖 cosmdl_options。
     * 响应：{ success: true, data: { message: 'reset' } }
     * @return void
     */
	public function ajax_reset_options(){
		if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) { wp_send_json_error('权限不足'); }
		$nonce = filter_input(INPUT_POST, 'cosmdl_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$nonce = ($nonce !== null && $nonce !== false) ? sanitize_text_field((string) $nonce) : '';
		if (!wp_verify_nonce($nonce, 'cosmdl_ajax')) { wp_send_json_error('非法请求'); }
		$defaults = self::default_options();
		update_option('cosmdl_options', $defaults, false);
		wp_send_json_success(array('message'=>'reset'));
	}

	public function ajax_export_options(){
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_die(esc_html__('权限不足', 'cosmautdl'));
        }

		$nonce = filter_input(INPUT_GET, 'cosmdl_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$nonce = ($nonce !== null && $nonce !== false) ? sanitize_text_field((string) $nonce) : '';
		if (!wp_verify_nonce($nonce, 'cosmdl_ajax')) {
			wp_die(esc_html__('非法请求', 'cosmautdl'));
		}

        $opts = $this->get_options();
        $opts = is_array($opts) ? $opts : array();

        $payload = array(
            'plugin'      => 'cosmautdl',
            'version'     => defined('COSMDL_VERSION') ? COSMDL_VERSION : '',
            'exported_at' => current_time('mysql'),
            'options'     => $opts,
        );

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            wp_die(esc_html__('导出失败', 'cosmautdl'));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=cosmautdl-options-' . gmdate('Ymd-His') . '.json');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 中文注释：输出为 application/json 的下载文件内容，不属于 HTML 上下文，无需转义
        echo $json;
        exit;
    }

	public function ajax_import_options(){
		if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) { wp_send_json_error('权限不足'); }

		$nonce = filter_input(INPUT_POST, 'cosmdl_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$nonce = ($nonce !== null && $nonce !== false) ? sanitize_text_field((string) $nonce) : '';
		if (!wp_verify_nonce($nonce, 'cosmdl_ajax')) { wp_send_json_error('非法请求'); }

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 中文注释：payload 为 JSON 字符串，后续 json_decode 进行严格格式校验
		$payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
		$payload = ($payload !== null && $payload !== false) ? wp_unslash($payload) : '';
		$payload = is_string($payload) ? $payload : '';
		if ($payload === '') {
			wp_send_json_error('缺少配置内容');
		}

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            wp_send_json_error('配置文件不是有效的 JSON');
        }

        if (isset($decoded['plugin']) && is_string($decoded['plugin']) && $decoded['plugin'] !== 'cosmautdl') {
            wp_send_json_error('配置文件不属于 CosmautDL');
        }

        $incoming = $decoded;
        if (isset($decoded['options']) && is_array($decoded['options'])) {
            $incoming = $decoded['options'];
        }

        if (!is_array($incoming)) {
            wp_send_json_error('配置格式不正确');
        }

        $defaults = self::default_options();
        $defaults = is_array($defaults) ? $defaults : array();
        $merged = array_merge($defaults, $incoming);
        $clean = $this->sanitize_options($merged, $defaults);
        update_option('cosmdl_options', $clean, false);

        wp_send_json_success(array('message' => '已导入配置'));
    }

    /**
     * AJAX：查询二维码区块开关
     * 权限：edit_posts；无需 nonce（只读查询）。
     * 返回：{ success: true, data: { show_qr_block: 'yes'|'no' } }
     * 用途：元框页面决定是否展示“扫码下载”区块。
     * @return void
     */
    public function ajax_get_qr_status(){
        if (!current_user_can('edit_posts')) { wp_send_json_error('权限不足'); }
        $opts = get_option('cosmdl_options');
        $opts = is_array($opts) ? $opts : array();
        $v = (isset($opts['show_qr_block']) && $opts['show_qr_block'] === 'yes') ? 'yes' : 'no';
        wp_send_json_success(array('show_qr_block' => $v));
    }

    // 下载统计页面函数已移除

    /**
     * 字段渲染：文本输入
     * @param array $args 形如 ['key' => 'option_key']
     * 从当前选项中取值并渲染 input[type=text]，附带最小宽度以兼容窄屏。
     */
    public function field_text($args){ $k = $args['key']; $o = $this->get_options(); $v = isset($o[$k]) ? $o[$k] : ''; echo '<input type="text" style="min-width:320px" name="cosmdl_options['.esc_attr($k).']" value="'.esc_attr($v).'" />'; }
    /**
     * 字段渲染：文本域
     * @param array $args 形如 ['key' => 'option_key']
     * 渲染 textarea，使用 esc_textarea 保护用户输入。
     */
    public function field_textarea($args){ $k=$args['key']; $o=$this->get_options(); $v=isset($o[$k])?$o[$k]:''; $rows=isset($args['rows'])?intval($args['rows']):4; echo '<textarea name="cosmdl_options['.esc_attr($k).']" rows="'.esc_attr($rows).'" class="cosmdl-textarea">'.esc_textarea($v).'</textarea>'; }
    public function field_color($args){ $k=$args['key']; $o=$this->get_options(); $v=isset($o[$k])?$o[$k]:''; echo '<input type="color" class="cosmdl-color-input" name="cosmdl_options['.esc_attr($k).']" value="'.esc_attr($v).'" /> <input type="text" class="cosmdl-color-hex-input" name="cosmdl_options['.esc_attr($k).'_hex]" value="'.esc_attr($v).'" placeholder="#333333" />'; }
    /**
     * 字段渲染：复选框
     * 解决未选中时浏览器不提交值的问题：先输出隐藏输入值 'no'，复选框选中时提交 'yes' 覆盖。
     * @param array $args 形如 ['key' => 'option_key']
     */
    public function field_checkbox($args){
        // 中文注释：为了解决“未选中时不提交值，导致保存后又恢复为开启”的问题，这里增加一个隐藏输入，固定提交 'no'
        // 当复选框被选中时，浏览器会提交同名的 'yes' 输入，覆盖隐藏输入，从而得到正确的值
        $k = $args['key'];
        $o = $this->get_options();
        $v = isset($o[$k]) ? $o[$k] : 'no';
        echo '<input type="hidden" name="cosmdl_options['.esc_attr($k).']" value="no" />';
        echo '<label class="cosmdl-switch"><input type="checkbox" name="cosmdl_options['.esc_attr($k).']" value="yes" '.checked('yes',$v,false).' /><span class="cosmdl-slider"></span></label>';
    }
    /**
     * 字段渲染：下拉选择
     * @param array $args 包含 ['key' => 'option_key', 'options' => ['val' => 'Label', ...]]
     */
    public function field_select($args){ $k=$args['key']; $o=$this->get_options(); $v=isset($o[$k])?$o[$k]:''; $opts=isset($args['options'])?$args['options']:array(); echo '<select name="cosmdl_options['.esc_attr($k).']" class="cosmdl-select">'; foreach($opts as $val=>$label){ echo '<option value="'.esc_attr($val).'" '.selected($v,$val,false).'>'.esc_html($label).'</option>'; } echo '</select>'; }


    
    /**
     * 渲染“网盘管理”设置分组
     * 职责：
     * - 合并硬编码默认网盘条目（如 baidu/lanzou/360/jianguo/pikpak/normal）；
     * - 基于 alias 去重，避免同名网盘重复；
     * - 输出每一行的启用开关、名称、别名、顺序及删除按钮；
     * - 顺序通过拖拽或按钮调整时写入隐藏字段 .drive-order。
     */
    public function render_drive_management_fields(){
        $options = $this->get_options();
        $drive_management = isset($options['drive_management']) && is_array($options['drive_management']) ? $options['drive_management'] : array();
        
        // 添加默认网盘（如果不存在），并基于别名兼容去重：
        // 若现有设置中已存在相同别名（例如旧版本中用户将"其他网盘"改名为 PikPak），则跳过追加该默认项，避免出现两个同名网盘。
        $default_drives = cosmdl_get_drive_defaults();

        // 收集现有别名集合
        $existing_aliases = array();
        foreach($drive_management as $ekey => $edrive){
            $ealias = isset($edrive['alias']) ? $edrive['alias'] : self::sanitize_alias(isset($edrive['label']) ? $edrive['label'] : '', '');
            if ($ealias !== ''){ $existing_aliases[strtolower($ealias)] = true; }
        }

        foreach($default_drives as $key => $default_drive) {
            $default_alias = isset($default_drive['alias']) ? strtolower($default_drive['alias']) : strtolower($key);
            if (!isset($drive_management[$key]) && !isset($existing_aliases[$default_alias])) {
                $drive_management[$key] = $default_drive;
            }
        }

        // 再按别名去重（优先保留用户已有项，跳过后续重复别名的项）
        $deduped = array();
        $seen_alias = array();
        foreach($drive_management as $k=>$d){
            $alias = isset($d['alias']) ? $d['alias'] : self::sanitize_alias(isset($d['label']) ? $d['label'] : '', '');
            $al = strtolower($alias !== '' ? $alias : $k);
            if (isset($seen_alias[$al])){ continue; }
            $seen_alias[$al] = true;
            $deduped[$k] = $d;
        }
        $drive_management = $deduped;
        
        // 按顺序排序网盘
        uasort($drive_management, function($a, $b) {
            $orderA = isset($a['order']) ? intval($a['order']) : 999;
            $orderB = isset($b['order']) ? intval($b['order']) : 999;
            return $orderA - $orderB;
        });
        
		// 渲染每个网盘
		foreach($drive_management as $key => $drive) {
			$label_raw = isset($drive['label']) ? (string) $drive['label'] : '';
			$alias_raw = isset($drive['alias']) ? (string) $drive['alias'] : '';
			$order = isset($drive['order']) ? intval($drive['order']) : 999;
			$is_custom = isset($drive['is_custom']) && $drive['is_custom'] === 'yes';

            // 计算 LOGO 路径：优先使用别名（清理后）匹配 images/{alias}.png，其次尝试 key，同名存在即使用
			$alias_slug = strtolower(self::sanitize_alias($label_raw, $alias_raw));
            $logo_url = '';
            $candidates = array($alias_slug, strtolower($key));
            foreach($candidates as $cand){
                $file = COSMDL_PLUGIN_DIR . 'images/' . $cand . '.png';
                if (file_exists($file)) { $logo_url = COSMDL_PLUGIN_URL . 'images/' . $cand . '.png'; break; }
            }
            // 默认占位：若存在 default.png 则使用
            if ($logo_url === ''){
                $default = COSMDL_PLUGIN_DIR . 'images/default.png';
                if (file_exists($default)) { $logo_url = COSMDL_PLUGIN_URL . 'images/default.png'; }
            }
            
			$enabled_flag = isset($drive['enabled']) && $drive['enabled'] === 'yes' ? 'yes' : 'no';
			echo '<tr class="drive-item" data-enabled="' . esc_attr($enabled_flag) . '" data-key="' . esc_attr($key) . '">';
			echo '<td><span class="dashicons dashicons-menu drive-handle" draggable="false" title="按住拖动排序"></span></td>';
            // 原第二列开关移除（迁移到别名输入框右侧）；保留空列以兼容表格结构，但通过 CSS 隐藏
            echo '<td></td>';
            echo '<td>';
            // 水平布局：将“名称”与“别名”并排展示，减少垂直空间占用；
            // 注意：gap 同时影响行与列，当换行时会产生行间额外垂直间距，可能造成“下边距更宽”的视觉效果。
            // 为避免该视觉偏差，将行间距收窄为 4px，并显式指定 row-gap。
		echo '<div style="display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:0;row-gap:0;min-width:240px">';
		// LOGO：位于“三道杠”和“名称”之间，尺寸固定为 48x48，未匹配到 PNG 时显示占位
		if ($logo_url !== ''){
			$alt_text = $label_raw !== '' ? $label_raw : $alias_slug;
			echo '<img class="cosmdl-drive-logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr($alt_text) . '" style="width:48px;height:48px;object-fit:contain;margin-right:8px;" />';
		} else {
			$ph = strtoupper(substr($alias_slug, 0, 1));
			echo '<span class="cosmdl-drive-logo" style="width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#f2f4f7;color:#666;font-size:13px;margin-right:8px;">' . esc_html($ph) . '</span>';
		}
		echo '<label style="display:flex;align-items:center;gap:6px"><span>名称</span><input type="text" name="cosmdl_options[drive_management][' . esc_attr($key) . '][label]" value="' . esc_attr($label_raw) . '" /></label>';
		// 别名区域：与“名称输入框”之间的距离设为 12px（为别名与别名输入框 6px 间距的两倍）
		echo '<span class="alias-group" style="display:flex;align-items:center;gap:6px;margin-left:12px">';
		echo '<label style="display:flex;align-items:center;gap:6px"><span>别名</span><input type="text" name="cosmdl_options[drive_management][' . esc_attr($key) . '][alias]" value="' . esc_attr($alias_raw) . '" placeholder="用于跳转路径，如 baidu、lanzou、360 或 jianguo" /></label>';
		echo '</span>';
		// 右侧控制区域：开关 + 删除按钮，整体靠右对齐，使删除按钮随容器右缘顺延，并通过 CSS 统一间距
		echo '<span class="right-controls">';
		echo '<label class="cosmdl-switch">';
		echo '<input type="checkbox" name="cosmdl_options[drive_management][' . esc_attr($key) . '][enabled]" value="yes" ' . checked('yes', $enabled_flag, false) . ' />';
		echo '<span class="cosmdl-slider"></span>';
		echo '</label>';
		echo '<span class="remove-drive dashicons dashicons-trash" data-key="' . esc_attr($key) . '"></span>';
		if ($is_custom) {
			echo '<input type="hidden" name="cosmdl_options[drive_management][' . esc_attr($key) . '][is_custom]" value="yes" />';
		}
				echo '</span>';
				echo '</div>';
				echo '<input type="hidden" class="drive-order" name="cosmdl_options[drive_management][' . esc_attr($key) . '][order]" value="' . esc_attr($order) . '" />';
				echo '</td>';
				echo '</tr>';
			}
    }

    // 已移除 ensure_default_from_current：不再使用站点级默认覆盖机制

    /**
     * 辅助方法：从 UA 解析系统与浏览器
     */
    private function get_client_info($ua) {
        $ua = isset($ua) ? $ua : '';
        $os = '未知系统';
        $browser = '未知浏览器';
        $version = '';
        // 默认图标
        $os_icon = 'linux'; 
        $browser_icon = 'chrome';

        // 系统解析
        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
            $ver_map = array(
                '10.0' => 'Windows 10/11',
                '6.3' => 'Windows 8.1',
                '6.2' => 'Windows 8',
                '6.1' => 'Windows 7',
                '5.1' => 'Windows XP'
            );
            $os = isset($ver_map[$m[1]]) ? $ver_map[$m[1]] : 'Windows ' . $m[1];
            $os_icon = 'windows';
        } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
            $os_icon = 'android';
        } elseif (preg_match('/(iPhone|iPad).*?OS ([\d_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[2]);
            $os_icon = 'apple';
        } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
            $os = 'Mac OS X ' . str_replace('_', '.', $m[1]);
            $os_icon = 'apple';
        } elseif (strpos($ua, 'Ubuntu') !== false) {
            $os = 'Ubuntu';
            $os_icon = 'ubuntu';
        } elseif (strpos($ua, 'Linux') !== false) {
            $os = 'Linux';
            $os_icon = 'linux';
        }

        // 浏览器解析
        if (strpos($ua, 'MicroMessenger') !== false) {
            $browser = 'WeChat';
            $browser_icon = 'weixin';
            if (preg_match('/MicroMessenger\/([\d.]+)/i', $ua, $m)) { $version = $m[1]; }
        } elseif (preg_match('/Edg\/([\d.]+)/i', $ua, $m)) {
            $browser = 'Edge';
            $browser_icon = 'edge';
            $version = $m[1];
        } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) {
            $browser = 'Chrome';
            $browser_icon = 'chrome';
            $version = $m[1];
        } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) {
            $browser = 'Firefox';
            $browser_icon = 'firefox';
            $version = $m[1];
        } elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) {
            $browser = 'Safari';
            $browser_icon = 'safari';
            if (preg_match('/Version\/([\d.]+)/i', $ua, $v)) { $version = $v[1]; }
        } elseif (preg_match('/MSIE ([\d.]+)/i', $ua, $m)) {
            $browser = 'IE';
            $browser_icon = 'internet-explorer';
            $version = $m[1];
        } elseif (strpos($ua, 'OPR') !== false || strpos($ua, 'Opera') !== false) {
             $browser = 'Opera';
             $browser_icon = 'opera';
        }

        $browser_full = $browser . ($version ? " ($version)" : '');
        
        return array(
            'os' => $os, 
            'browser' => $browser_full,
            'os_icon' => $os_icon,
            'browser_icon' => $browser_icon
        );
    }

    /**
     * AJAX Handler: 获取下载详情
     */
	public function ajax_get_download_details() {
		if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
			wp_send_json_error('权限不足');
		}

		check_ajax_referer('cosmdl_ajax', 'nonce');

        global $wpdb;
		$pid = filter_input(INPUT_POST, 'pid', FILTER_SANITIZE_NUMBER_INT);
		$pid = ($pid !== null && $pid !== false) ? absint($pid) : 0;
		$attach = filter_input(INPUT_POST, 'attach', FILTER_SANITIZE_NUMBER_INT);
		$attach = ($attach !== null && $attach !== false) ? absint($attach) : 1;
		$attach = max(1, min(6, $attach));
        
        if (!$pid) {
            wp_send_json_error('参数错误');
        }

		$details_cache_key = 'cosmdl_admin_details_' . $pid . '_' . $attach;
		$rows = wp_cache_get($details_cache_key, 'cosmautdl');
		if ($rows === false) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cosmdl_clicks WHERE post_id=%d AND attach_id=%d ORDER BY created_at DESC LIMIT 100",
					$pid,
					$attach
				)
			);
			wp_cache_set($details_cache_key, $rows, 'cosmautdl', 60);
		}

        if (empty($rows)) {
            wp_send_json_success('<p style="margin:10px 0;color:#666">暂无下载记录</p>');
        }

        ob_start();
        echo '<div style="margin:10px 0;border:1px solid #ddd;background:#fff;border-radius:4px;overflow:hidden;">';
        // 批量操作栏
        echo '<div style="padding:8px 12px;border-bottom:1px solid #ddd;background:#f9f9f9;display:flex;align-items:center;justify-content:flex-end;">';
        echo '<button type="button" class="button button-small cosmdl-batch-delete-logs" disabled style="color:#b32d2e;border-color:#b32d2e;">' . esc_html__('批量删除选中', 'cosmautdl') . '</button>';
        echo '</div>';

        echo '<div style="max-height:550px;overflow-y:auto;">';
        echo '<table class="widefat striped" style="width:100%;border:0;box-shadow:none;">';
        echo '<thead><tr>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;width:30px;padding-left:10px;"><input type="checkbox" class="cosmdl-select-all-logs" /></th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">网盘</th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">时间</th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">IP地址</th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">系统版本</th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">浏览器</th>
            <th style="position:sticky;top:0;background:#f0f0f1;z-index:1;text-align:center;">删除</th>
        </tr></thead>';
        echo '<tbody>';
        
        // 图片映射表
        $img_map = array(
            'baidu' => 'baidu.png',
            'ali' => 'ali.png', 'aliyun' => 'ali.png',
            'quark' => 'quark.png',
            'pikpak' => 'pikpak.png',
            'lanzou' => 'lanzou.png',
            'xunlei' => 'xunlei.png',
            'weiyun' => 'weiyun.png',
            'onedrive' => 'onedrive.png',
            'google' => 'googledrive.png', 'googledrive' => 'googledrive.png', 'gdrive' => 'googledrive.png',
            'dropbox' => 'dropbox.png',
            'mega' => 'mega.png',
            'mediafire' => 'mediafire.png',
            'box' => 'box.png',
            'other' => 'other.png'
        );

		$icon_loc = wp_kses(cosmautdl_Icons::get('location-dot'), self::allowed_icon_html());
		$icon_clock = wp_kses(cosmautdl_Icons::get('clock'), self::allowed_icon_html());
		$icon_trash = cosmautdl_Icons::get('trash');
		$icon_trash = is_string($icon_trash) ? wp_kses($icon_trash, self::allowed_icon_html()) : '';
		if ($icon_trash === '') {
			$icon_trash = '<span class="dashicons dashicons-trash cosmdl-delete-log-icon" style="color:#dc3545;"></span>';
		} else {
			$icon_trash = str_replace('<svg ', '<svg class="cosmdl-delete-log-icon" style="width:16px;height:16px;color:#dc3545;" ', $icon_trash);
		}

			foreach ($rows as $r) {
				$info = $this->get_client_info($r->ua);
            
            // 获取网盘图片
            $drive_key = strtolower($r->type);
            $img_file = 'other.png';
            foreach ($img_map as $k => $v) {
                if (strpos($drive_key, $k) !== false) {
                    $img_file = $v;
                    break;
                }
            }
            $img_url = COSMDL_PLUGIN_URL . 'images/' . $img_file;
            
            // 获取网盘链接
            $att_idx = intval($r->attach_id);
            $prefix = ($att_idx === 1) ? 'cosmdl_' : ('cosmdl' . $att_idx . '_');
            // 尝试使用记录中的 type 作为 key
            $meta_key = $prefix . 'downurl_' . $r->type;
            $drive_url = get_post_meta($pid, $meta_key, true);

			$icon_os = wp_kses(cosmautdl_Icons::get($info['os_icon']), self::allowed_icon_html());
			$icon_browser = wp_kses(cosmautdl_Icons::get($info['browser_icon']), self::allowed_icon_html());

			echo '<tr id="cosmdl-log-' . intval($r->id) . '">';
            
            // 复选框列
            echo '<td style="text-align:center;"><input type="checkbox" class="cosmdl-log-checkbox" value="' . esc_attr($r->id) . '" /></td>';

            // 网盘列 - 使用图片
            echo '<td><div style="display:flex;align-items:center;justify-content:center;">';
            if ($drive_url) {
                echo '<a href="' . esc_url($drive_url) . '" target="_blank" class="cosmdl-log-drive" style="display:block;width:30px;height:30px;border:none;">';
                echo '<img class="cosmdl-pan-logo" src="' . esc_url($img_url) . '" alt="' . esc_attr($r->type) . '" style="width:100%;height:100%;object-fit:contain;">';
                echo '</a>';
            } else {
                echo '<div class="cosmdl-log-drive" style="width:40px;height:40px;">';
                echo '<img class="cosmdl-pan-logo" src="' . esc_url($img_url) . '" alt="' . esc_attr($r->type) . '" style="width:100%;height:100%;object-fit:contain;opacity:0.7;">';
                echo '</div>';
            }
            echo '</div></td>';

            $created_at = $r->created_at;

            if (!empty($created_at) && strtotime($created_at) !== false) {
                // 中文注释：数据库中的 created_at 使用 current_time('mysql', 1) 以 GMT 存储
                // 这里统一通过 WordPress 核心函数 get_date_from_gmt 转换为站点时区下的本地时间
                // 好处：
                // - 自动读取「设置 → 常规」中的时区配置（timezone_string / gmt_offset）
                // - 与前台 templates/stats.php 中的时间显示逻辑保持一致
                $formatted_time = get_date_from_gmt($created_at, 'Y-m-d H:i:s');
            } else {
                // 中文注释：若时间字段为空或格式异常，则直接显示原始值，避免抛出异常
                $formatted_time = $created_at;
            }
            
			echo '<td><div style="display:flex;align-items:center;justify-content:center;">' . wp_kses($icon_clock, self::allowed_icon_html()) . esc_html($formatted_time) . '</div></td>';
            
            // IP + Location
			echo '<td>';
			echo '<div style="display:flex;align-items:center;justify-content:center;">';
			echo wp_kses($icon_loc, self::allowed_icon_html());
            echo '<span style="margin-right:6px">' . esc_html($r->ip) . '</span>';
            echo '<span class="cosmdl-ip-loc" data-ip="' . esc_attr($r->ip) . '" style="color:#888;font-size:0.9em"></span>';
            echo '</div>';
            echo '</td>';

			echo '<td><div style="display:flex;align-items:center;justify-content:center;">' . wp_kses($icon_os, self::allowed_icon_html()) . esc_html($info['os']) . '</div></td>';
			echo '<td><div style="display:flex;align-items:center;justify-content:center;">' . wp_kses($icon_browser, self::allowed_icon_html()) . esc_html($info['browser']) . '</div></td>';
            
            // 删除列
            echo '<td><div style="display:flex;align-items:center;justify-content:center;">';
            echo '<button type="button" class="button-link cosmdl-delete-log" data-id="' . esc_attr($r->id) . '" title="删除此记录" style="display:flex;align-items:center;justify-content:center;cursor:pointer;">';
            echo wp_kses($icon_trash, self::allowed_icon_html());
            echo '</button>';
            echo '</div></td>';
            
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (count($rows) >= 100) {
            echo '<p style="padding:5px 10px;color:#999;font-size:12px;margin:0;text-align:center;">仅显示最近 100 条记录</p>';
        }
        echo '</div>'; // close scroll container
        echo '</div>'; // close main container
        $html = ob_get_clean();

        wp_send_json_success($html);
    }

    /**
     * AJAX Handler：批量解析 IP 归属地
     *
     * 说明：
     * - 仅后台管理员可调用；
     * - 仅用于下载统计详情中展示“IP 地址”的辅助信息；
     * - 使用 transient 做缓存，减少对外部服务请求。
     */
	public function ajax_ip_geo_batch() {
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_send_json_error('权限不足');
        }

        check_ajax_referer('cosmdl_ajax', 'nonce');

        $opts = $this->get_options();
        $enabled = isset($opts['stats_ip_geo']) ? $opts['stats_ip_geo'] : 'no';
        if ($enabled !== 'yes') {
            wp_send_json_success(array());
        }

		$ips = filter_input(INPUT_POST, 'ips', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$ips = is_array($ips) ? wp_unslash($ips) : array();
		if (empty($ips)) {
			wp_send_json_success(array());
		}

        // 中文注释：限制单次请求的 IP 数量，避免被滥用或导致后台卡顿
        $ips = array_slice($ips, 0, 50);

        $provider = isset($opts['stats_ip_geo_provider']) ? $opts['stats_ip_geo_provider'] : 'ipapi';
        if (!in_array($provider, array('ipapi', 'ip-api', 'ipinfo'), true)) {
            $provider = 'ipapi';
        }
        $cache_hours = isset($opts['stats_ip_geo_cache_hours']) ? intval($opts['stats_ip_geo_cache_hours']) : 168;
        if ($cache_hours < 1) { $cache_hours = 1; }
        if ($cache_hours > 720) { $cache_hours = 720; }

        $result = array();
        foreach ($ips as $raw_ip) {
            $ip = trim(sanitize_text_field($raw_ip));
            if ($ip === '') {
                continue;
            }

            $result[$ip] = $this->get_ip_geo_cached($ip, $provider, $cache_hours);
        }

        wp_send_json_success($result);
    }

    /**
     * 中文注释：获取 IP 归属地（带缓存）
     */
    private function get_ip_geo_cached($ip, $provider, $cache_hours) {
        // 中文注释：127.0.0.1 / ::1 直接显示“本地”
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return __('本地', 'cosmautdl');
        }

        // 中文注释：基础合法性校验
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        // 中文注释：私有网段 / 链路本地地址，避免外部查询
        if ($this->is_private_ip($ip)) {
            return __('内网', 'cosmautdl');
        }

        $cache_key = 'cosmdl_ip_geo_' . md5($provider . '|' . $ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return is_string($cached) ? $cached : '';
        }

        $location = $this->resolve_ip_geo_via_provider($ip, $provider);
        $location = is_string($location) ? trim($location) : '';

        // 中文注释：空结果也缓存一小段时间，避免频繁请求失败重试
        $ttl = $location !== '' ? ($cache_hours * HOUR_IN_SECONDS) : (6 * HOUR_IN_SECONDS);
        set_transient($cache_key, $location, $ttl);

        return $location;
    }

    /**
     * 中文注释：判断是否为私有 IP / 本地链路 IP
     */
    private function is_private_ip($ip) {
        // IPv4 私有网段：10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (strpos($ip, '10.') === 0) { return true; }
            if (strpos($ip, '192.168.') === 0) { return true; }
            if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) { return true; }
            return false;
        }

        // IPv6：fc00::/7（ULA），fe80::/10（link-local）
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip_lc = strtolower($ip);
            if (strpos($ip_lc, 'fc') === 0 || strpos($ip_lc, 'fd') === 0) { return true; }
            if (strpos($ip_lc, 'fe8') === 0 || strpos($ip_lc, 'fe9') === 0 || strpos($ip_lc, 'fea') === 0 || strpos($ip_lc, 'feb') === 0) { return true; }
        }

        return false;
    }

    /**
     * 中文注释：通过指定服务商解析 IP 归属地
     */
    private function resolve_ip_geo_via_provider($ip, $provider) {
        $timeout = 4;
        $args = array(
            'timeout' => $timeout,
            'redirection' => 2,
            'headers' => array(
                'User-Agent' => 'CosmautDL/' . (defined('COSMDL_VERSION') ? COSMDL_VERSION : '1.0') . ' (+https://cosmaut.com/)',
                'Accept' => 'application/json,text/plain,*/*',
            ),
        );

        $providers = array('ipapi', 'ip-api', 'ipinfo');
        $order = array_values(array_unique(array_merge(array($provider), $providers)));
        $order = apply_filters('cosmdl_ip_geo_providers', $order, $ip);
        if (!is_array($order) || empty($order)) {
            $order = array($provider);
        }

        foreach ($order as $p) {
            $p = is_string($p) ? $p : '';
            if ($p === '') { continue; }

            $text = '';
            $raw_data = null;

            if ($p === 'ip-api') {
                $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?lang=zh-CN&fields=status,message,country,regionName,city,isp,query';
                $resp = wp_remote_get($url, $args);
                if (!is_wp_error($resp)) {
                    $body = wp_remote_retrieve_body($resp);
                    $raw_data = json_decode($body, true);
                    if (is_array($raw_data) && isset($raw_data['status']) && $raw_data['status'] === 'success') {
                        $parts = array();
                        if (!empty($raw_data['country'])) { $parts[] = $raw_data['country']; }
                        if (!empty($raw_data['regionName'])) { $parts[] = $raw_data['regionName']; }
                        if (!empty($raw_data['city'])) { $parts[] = $raw_data['city']; }
                        $text = implode(' ', array_filter($parts));
                        if (!empty($raw_data['isp'])) { $text = trim($text . ' ' . $raw_data['isp']); }
                    }
                }
            } elseif ($p === 'ipinfo') {
                $url = 'https://ipinfo.io/' . rawurlencode($ip) . '/json';
                $resp = wp_remote_get($url, $args);
                if (!is_wp_error($resp)) {
                    $body = wp_remote_retrieve_body($resp);
                    $raw_data = json_decode($body, true);
                    if (is_array($raw_data) && empty($raw_data['error'])) {
                        $parts = array();
                        if (!empty($raw_data['country'])) { $parts[] = $raw_data['country']; }
                        if (!empty($raw_data['region'])) { $parts[] = $raw_data['region']; }
                        if (!empty($raw_data['city'])) { $parts[] = $raw_data['city']; }
                        $text = implode(' ', array_filter($parts));
                        if (!empty($raw_data['org'])) { $text = trim($text . ' ' . $raw_data['org']); }
                    }
                }
            } else {
                // ipapi.co
                $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
                $resp = wp_remote_get($url, $args);
                if (!is_wp_error($resp)) {
                    $body = wp_remote_retrieve_body($resp);
                    $raw_data = json_decode($body, true);
                    if (is_array($raw_data) && empty($raw_data['error'])) {
                        $parts = array();
                        if (!empty($raw_data['country_name'])) { $parts[] = $raw_data['country_name']; }
                        if (!empty($raw_data['region'])) { $parts[] = $raw_data['region']; }
                        if (!empty($raw_data['city'])) { $parts[] = $raw_data['city']; }
                        $text = implode(' ', array_filter($parts));
                        if (!empty($raw_data['org'])) { $text = trim($text . ' ' . $raw_data['org']); }
                    }
                }
            }

            $text = apply_filters('cosmdl_ip_geo_location_text', $text, $ip, $raw_data, $p);
            $text = is_string($text) ? trim($text) : '';
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * AJAX Handler: 删除下载记录
     */
	public function ajax_delete_log() {
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        check_ajax_referer('cosmdl_ajax', 'nonce');

        global $wpdb;
		$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
		$id = ($id !== null && $id !== false) ? absint($id) : 0;
        if (!$id) {
            wp_send_json_error('参数错误');
        }

		$table = $wpdb->prefix . 'cosmdl_clicks';
		$result = $wpdb->delete($table, array('id' => $id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($result !== false) {
            wp_send_json_success('删除成功');
        } else {
            wp_send_json_error('删除失败');
        }
    }

    /**
     * AJAX Handler: 批量删除下载记录
     */
	public function ajax_batch_delete_logs() {
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        check_ajax_referer('cosmdl_ajax', 'nonce');

        global $wpdb;
		$ids_raw = filter_input(INPUT_POST, 'ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$ids_raw = is_array($ids_raw) ? wp_unslash($ids_raw) : array();
		$ids = array_values(array_filter(array_map('absint', $ids_raw)));
        
        if (empty($ids)) {
            wp_send_json_error('未选择任何记录');
        }

		$table = $wpdb->prefix . 'cosmdl_clicks';
		$deleted = 0;
		foreach ($ids as $id) {
			$del = $wpdb->delete($table, array('id' => $id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ($del === false) {
				continue;
			}
			$deleted += intval($del);
		}

		if ($deleted > 0) {
			wp_send_json_success('批量删除成功');
		}
		wp_send_json_error('批量删除失败');
	}

    /**
     * AJAX处理：文件信息模块实时预览
     * 
     * 通过AJAX调用真实的cosmdl_render_file_info_card函数来生成预览HTML
     * 以确保预览效果与实际下载页面完全一致
     * 
     * @return void
     */
	public function ajax_fileinfo_preview() {
        // 权限检查
        if (!current_user_can(function_exists('cosmdl_admin_cap') ? cosmdl_admin_cap() : 'manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 验证nonce
        check_ajax_referer('cosmdl_ajax', 'nonce');

        // 获取参数（与设置项键名保持一致）
		$title = filter_input(INPUT_POST, 'file_info_title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$title = ($title !== null && $title !== false) ? sanitize_text_field((string) $title) : '';
        // 主题与圆角、阴影
		$card_theme = filter_input(INPUT_POST, 'card_theme', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$card_theme = ($card_theme !== null && $card_theme !== false) ? sanitize_text_field((string) $card_theme) : '';
		$card_border_radius = filter_input(INPUT_POST, 'card_border_radius', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$card_border_radius = ($card_border_radius !== null && $card_border_radius !== false) ? sanitize_text_field((string) $card_border_radius) : '';
		$card_shadow = filter_input(INPUT_POST, 'card_shadow', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$card_shadow = ($card_shadow !== null && $card_shadow !== false) ? sanitize_text_field((string) $card_shadow) : 'yes';
		$allowed_themes = array('green', 'blue', 'red', 'purple', 'orange', 'pink', 'gray', 'indigo', 'teal');
		$allowed_radius = array('none', 'small', 'medium', 'large');
		if ($card_theme !== '' && !in_array($card_theme, $allowed_themes, true)) {
			$card_theme = '';
		}
		if ($card_border_radius !== '' && !in_array($card_border_radius, $allowed_radius, true)) {
			$card_border_radius = '';
		}
        // 广告位设置
		$show_ad_slot = filter_input(INPUT_POST, 'show_ad_slot', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$show_ad_slot = ($show_ad_slot !== null && $show_ad_slot !== false) ? sanitize_text_field((string) $show_ad_slot) : '';
		$ad_html = filter_input(INPUT_POST, 'ad_html', FILTER_UNSAFE_RAW);
		$ad_html = ($ad_html !== null && $ad_html !== false) ? wp_kses_post(wp_unslash($ad_html)) : '';
        // 预览目标文章与附件索引
		$sample_post_id = filter_input(INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT);
		$sample_post_id = ($sample_post_id !== null && $sample_post_id !== false) ? absint($sample_post_id) : 1;
		$sample_attach  = filter_input(INPUT_POST, 'attach', FILTER_SANITIZE_NUMBER_INT);
		$sample_attach  = ($sample_attach !== null && $sample_attach !== false) ? absint($sample_attach) : 2;
		$sample_attach  = max(1, min(6, $sample_attach));

        // 获取插件选项并临时修改设置用于预览
        $options = get_option('cosmdl_options', array());
        
		// 构建临时选项用于预览
		$temp_options = array_merge($options, array(
			'file_info_title' => $title,
			'card_theme' => $card_theme ? $card_theme : ($options['card_theme'] ?? 'green'),
			'card_border_radius' => $card_border_radius ? $card_border_radius : ($options['card_border_radius'] ?? 'medium'),
			// 阴影
			'card_shadow' => $card_shadow,
            // 广告位设置
            'show_ad_slot' => $show_ad_slot ?: ($options['show_ad_slot'] ?? 'no'),
            'ad_html' => $ad_html ?: ($options['ad_html'] ?? ''),
            // 显示开关
            'show_fileinfo' => 'yes',
        ));

        // 确保download-page.php文件被加载
        if (!function_exists('cosmdl_render_file_info_card')) {
            $download_page_path = plugin_dir_path(__FILE__) . 'download-page.php';
            if (file_exists($download_page_path)) {
                require_once $download_page_path;
            } else {
                wp_send_json_error('下载页面文件未找到');
            }
        }

        // 检查文章是否存在，如果不存在则使用虚拟数据
        $post = get_post($sample_post_id);
        if (!$post) {
            // 如果文章不存在，我们需要为预览创建一些示例数据
            // 通过临时设置post meta来提供示例数据
            add_action('get_post_metadata', function($value, $object_id, $meta_key, $single) use ($sample_post_id) {
                if ($object_id == $sample_post_id) {
                    switch ($meta_key) {
                        case 'cosmdl_name':
                            return '示例文件名.zip';
                        case 'cosmdl_size':
                            return '15.6';
                        case 'cosmdl_size_unit':
                            return 'MB';
                        case 'cosmdl_date':
                            return '2024-12-20';
                        case 'cosmdl_author':
                            return '示例作者';
                        case 'cosmdl_softtype':
                            return '免费软件';
                        default:
                            return $value;
                    }
                }
                return $value;
            }, 10, 4);
            
            // 模拟get_the_date函数
            add_filter('the_date', function($date, $format, $before, $after, $post) use ($sample_post_id) {
                if ($post && $post->ID == $sample_post_id) {
                    return '2024-12-20';
                }
                return $date;
            }, 10, 5);
            
            // 模拟get_permalink函数
            add_filter('post_link', function($permalink, $post) use ($sample_post_id) {
                if ($post && $post->ID == $sample_post_id) {
                    return home_url('/sample-post/');
                }
                return $permalink;
            }, 10, 2);
        }

        // 调用真实的渲染函数，使用示例/指定数据
        if (function_exists('cosmdl_render_file_info_card')) {
            ob_start();
            cosmdl_render_file_info_card($sample_post_id, $sample_attach, $temp_options);
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error('文件信息渲染函数未找到');
        }
    }

    /**
     * 在设置页页脚输出最小交互脚本
     * 仅在 cosmdl-settings 页面输出，内容包括：
     * - 下载统计表中“总下载次数”按钮的展开/收起；
     * - 复制“文件树访问路径”到剪贴板（自动兼容 Clipboard API 与 execCommand）。
     * @return void
     */
    public function print_inline_js(){
        // 判断当前是否我们的设置页
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$screen_id = ($screen && isset($screen->id)) ? (string) $screen->id : '';
		if ($screen_id !== 'toplevel_page_cosmdl-settings') {
			return;
		}
        // 中文注释：读取开关，避免在用户关闭功能后仍然发起外部请求
        $opts = $this->get_options();
        $ip_geo_enabled = (isset($opts['stats_ip_geo']) && $opts['stats_ip_geo'] === 'yes');
        // 输出最小必要的交互脚本
        echo '<script>'
            . "var cosmdlIpGeoEnabled = " . ($ip_geo_enabled ? 'true' : 'false') . ";"
            // IP 地址解析逻辑
            . "window.cosmdl_resolve_ips = function(container){"
            . "  if(!container) return;"
            . "  var nonceEl = document.getElementById('cosmdl-ajax-nonce');"
            . "  var nonce = nonceEl ? nonceEl.value : '';"
            . "  if(!window.cosmdlIpGeoCache){ window.cosmdlIpGeoCache = {}; }"
            . "  var els = container.querySelectorAll('.cosmdl-ip-loc');"
            . "  var ipToEls = {};"
            . "  var ips = [];"
            . "  for(var i=0;i<els.length;i++){"
            . "    var el = els[i];"
            . "    if(!el) continue;"
            . "    if(el.textContent && el.textContent.trim() !== '') continue;"
            . "    var ip = el.getAttribute('data-ip');"
            . "    if(!ip) continue;"
            . "    if(ip === '127.0.0.1' || ip === '::1') { el.textContent = ' 本地'; continue; }"
            . "    if(!cosmdlIpGeoEnabled) continue;"
            . "    if(window.cosmdlIpGeoCache[ip]) { el.textContent = ' ' + window.cosmdlIpGeoCache[ip]; continue; }"
            . "    if(!ipToEls[ip]){ ipToEls[ip] = []; ips.push(ip); }"
            . "    ipToEls[ip].push(el);"
            . "  }"
            . "  if(!ips.length) return;"
            . "  ips = ips.slice(0, 50);"
            . "  var fd = new FormData();"
            . "  fd.append('action', 'cosmdl_ip_geo_batch');"
            . "  fd.append('nonce', nonce);"
            . "  for(var j=0;j<ips.length;j++){ fd.append('ips[]', ips[j]); }"
            . "  fetch(ajaxurl, {method:'POST', body: fd})"
            . "    .then(function(r){ return r.json(); })"
            . "    .then(function(r){"
            . "      if(!r || !r.success || !r.data) return;"
            . "      var data = r.data;"
            . "      for(var ip in data){"
            . "        if(!Object.prototype.hasOwnProperty.call(data, ip)) continue;"
            . "        var loc = data[ip];"
            . "        if(typeof loc !== 'string') loc = '';"
            . "        loc = loc.trim();"
            . "        if(loc){ window.cosmdlIpGeoCache[ip] = loc; }"
            . "        var list = ipToEls[ip] || [];"
            . "        for(var k=0;k<list.length;k++){"
            . "          if(!list[k]) continue;"
            . "          if(loc){ list[k].textContent = ' ' + loc; }"
            . "        }"
            . "      }"
            . "    })"
            . "    .catch(function(){});"
            . "};"
            . "if(!window.cosmdl_admin_init){ window.cosmdl_admin_init = true; document.addEventListener('click', function(e){"
            . "  var btn = e.target.closest('.cosmdl-count');"
            . "  if(btn){"
            . "    var pid = btn.getAttribute('data-pid');"
            . "    var attach = btn.getAttribute('data-attach') || 1;"
            . "    var rowId = 'per-drive-' + pid + '-' + attach;"
            . "    var row = document.getElementById(rowId);"
            . "    if(!row) return;"
            . "    var expanded = btn.getAttribute('aria-expanded') === 'true';"
            . "    if(!expanded){"
            . "      btn.setAttribute('aria-expanded', 'true');"
            . "      row.style.display = 'table-row';"
            . "      var container = row.querySelector('.cosmdl-details-box');"
            . "      if(!container){"
            . "        var td = row.querySelector('td');"
            . "        container = document.createElement('div');"
            . "        container.className = 'cosmdl-details-box';"
            . "        container.style.marginTop = '10px';"
            . "        container.innerHTML = '<p style=\"color:#666\">正在加载详细记录...</p>';"
            . "        td.appendChild(container);"
							. "        var fd = new FormData();"
							. "        fd.append('action', 'cosmdl_get_download_details');"
							. "        fd.append('nonce', document.getElementById('cosmdl-ajax-nonce').value);"
							. "        fd.append('pid', pid);"
							. "        fd.append('attach', attach);"
							. "        fetch(ajaxurl, {method:'POST', body:fd})"
            . "          .then(function(r){ return r.json(); })"
            . "          .then(function(r){"
            . "             if(r.success){ container.innerHTML = r.data; if(window.cosmdl_resolve_ips) window.cosmdl_resolve_ips(container); }"
            . "             else { container.innerHTML = '<p style=\"color:red\">' + (r.data||'Error') + '</p>'; }"
            . "          })"
            . "          .catch(function(){ container.innerHTML = '<p style=\"color:red\">网络错误</p>'; });"
            . "      }"
            . "    } else {"
            . "      btn.setAttribute('aria-expanded', 'false');"
            . "      row.style.display = 'none';"
            . "    }"
            . "    return;"
            . "  }"
            . "  var delBtn = e.target.closest('.cosmdl-delete-log');"
            . "  if(delBtn){"
            . "    var id = delBtn.getAttribute('data-id');"
            . "    delBtn.disabled = true;"
            . "    delBtn.textContent = '" . esc_js(__('删除中...', 'cosmautdl')) . "';"
            . "    var fd = new FormData();"
            . "    fd.append('action', 'cosmdl_delete_log');"
            . "    fd.append('id', id);"
            . "    fd.append('nonce', document.getElementById('cosmdl-ajax-nonce').value);"
            . "    "
            . "    fetch(ajaxurl, {method:'POST', body:fd})"
            . "      .then(function(r){ return r.json(); })"
            . "      .then(function(r){"
            . "        if(r.success){"
            . "          var row = document.getElementById('cosmdl-log-' + id);"
            . "          if(row) row.parentNode.removeChild(row);"
            . "          showDeleteNotice();"
            . "        } else {"
            . "          alert(r.data || '" . esc_js(__('删除失败', 'cosmautdl')) . "');"
            . "          delBtn.disabled = false;"
            . "          delBtn.textContent = '" . esc_js(__('删除', 'cosmautdl')) . "';"
            . "        }"
            . "      })"
            . "      .catch(function(){ "
            . "        alert('" . esc_js(__('网络错误', 'cosmautdl')) . "');"
            . "        delBtn.disabled = false;"
            . "        delBtn.textContent = '" . esc_js(__('删除', 'cosmautdl')) . "';"
            . "      });"
            . "    return;"
            . "  }"
            . "  var fixBtn = document.getElementById('cosmdl-btn-fix-sizes');"
            . "  if(fixBtn && e.target === fixBtn){"
            . "    if(!confirm('" . esc_js(__('确定要执行数据修正吗？建议先备份数据库。', 'cosmautdl')) . "')) return;"
            . "    var spinner = fixBtn.nextElementSibling;"
            . "    var resultBox = document.getElementById('cosmdl-fix-size-result');"
            . "    fixBtn.disabled = true;"
            . "    spinner.classList.add('is-active');"
            . "    resultBox.style.display = 'none';"
            . "    resultBox.innerHTML = '';"
            . "    "
            . "    var fd = new FormData();"
            . "    fd.append('action', 'cosmdl_fix_file_sizes');"
            . "    fd.append('nonce', document.getElementById('cosmdl-ajax-nonce').value);"
            . "    "
            . "    fetch(ajaxurl, {method:'POST', body:fd})"
            . "      .then(function(r){ return r.json(); })"
            . "      .then(function(r){"
            . "        spinner.classList.remove('is-active');"
            . "        fixBtn.disabled = false;"
            . "        resultBox.style.display = 'block';"
            . "        if(r.success){"
            . "          resultBox.style.background = '#f0fdf4';"
            . "          resultBox.style.border = '1px solid #bbf7d0';"
            . "          resultBox.style.color = '#15803d';"
            . "          resultBox.innerHTML = '<p style=\"margin:0\">' + r.data.message + '</p>';"
            . "        } else {"
            . "          resultBox.style.background = '#fef2f2';"
            . "          resultBox.style.border = '1px solid #fecaca';"
            . "          resultBox.style.color = '#b91c1c';"
            . "          resultBox.innerHTML = '<p style=\"margin:0\">' + (r.data.message || '未知错误') + '</p>';"
            . "        }"
            . "      })"
            . "      .catch(function(){"
            . "        spinner.classList.remove('is-active');"
            . "        fixBtn.disabled = false;"
            . "        resultBox.style.display = 'block';"
            . "        resultBox.style.background = '#fef2f2';"
            . "        resultBox.style.border = '1px solid #fecaca';"
            . "        resultBox.style.color = '#b91c1c';"
            . "        resultBox.innerHTML = '<p style=\"margin:0\">网络请求失败，请稍后重试。</p>';"
            . "      });"
            . "    return;"
            . "  }"
            . "  var copyBtn = e.target.closest('#cosmdl-copy-tree-url');"
            . "  if(copyBtn){"
            . "    var url = copyBtn.getAttribute('data-url') || '';"
            . "    var done = function(ok){"
            . "      copyBtn.textContent = ok ? '已复制' : '复制失败';"
            . "      setTimeout(function(){ copyBtn.textContent = '点击复制地址'; }, 1500);"
            . "    };"
            . "    if(navigator.clipboard && navigator.clipboard.writeText){"
            . "      navigator.clipboard.writeText(url).then(function(){done(true);}).catch(function(){done(false);});"
            . "    } else {"
            . "      var ta = document.createElement('textarea');"
            . "      ta.value = url;"
            . "      document.body.appendChild(ta);"
            . "      ta.select();"
            . "      try { var ok = document.execCommand('copy'); done(ok); } catch(err){ done(false); }"
            . "      document.body.removeChild(ta);"
            . "    }"
            . "    return;"
            . "  }"
            . "  /* 中文注释：防止点击开关或工具区域触发模块的展开/收起 */"
            . "  var inTools = e.target.closest('.cosmdl-module__tools') || e.target.closest('.cosmdl-switch') || (e.target.tagName === 'INPUT') || (e.target.tagName === 'LABEL');"
            . "  if(inTools){ return; }"
            . "  var moduleToggle = e.target.closest('.cosmdl-module__header');"
            . "  if(moduleToggle){"
            . "    e.preventDefault(); e.stopPropagation();"
            . "    var module = moduleToggle.closest('.cosmdl-module');"
            . "    var content = module.querySelector('.cosmdl-module__body');"
            . "    var iconContainer = moduleToggle.querySelector('.cosmdl-module__toggle-icon');"
            . "    if(content && iconContainer){"
            . "      var isExpanded = module.classList.contains('is-open');"
            . "      /* 先收起所有其他模块 */"
            . "      var allModules = document.querySelectorAll('.cosmdl-module');"
            . "      allModules.forEach(function(otherModule){"
            . "        if(otherModule !== module){"
            . "            var otherContent = otherModule.querySelector('.cosmdl-module__body');"
            . "          var otherIconContainer = otherModule.querySelector('.cosmdl-module__toggle-icon');"
            . "          var otherHeader = otherModule.querySelector('.cosmdl-module__header');"
            . "          if(otherContent && otherIconContainer && otherHeader){"
            . "            otherContent.style.display = 'none';"
            . "            otherModule.classList.remove('is-open');"
            . "            otherHeader.setAttribute('aria-expanded', 'false');"
            /* 使用现有的is-open类CSS样式 */
            . "          }"
            . "        }"
            . "      });"
            . "      /* 然后切换当前模块状态 */"
            . "      if(isExpanded){"
            . "        content.style.display = 'none';"
            . "        module.classList.remove('is-open');"
            . "        moduleToggle.setAttribute('aria-expanded', 'false');"
            /* 使用现有的is-open类CSS样式 */
            . "      } else {"
            . "        content.style.display = 'block';"
            . "        module.classList.add('is-open');"
            . "        moduleToggle.setAttribute('aria-expanded', 'true');"
            /* 使用现有的is-open类CSS样式 */
            . "      }"
            . "    }"
            . "    return;"
            . "  }"
            . "}, true); }"
            . "document.addEventListener('DOMContentLoaded', function(){"
            . "  var container = document.getElementById('drives-container');"
            . "  if(!container) return;"
            . "  var gap = 6;"
            . "  container.style.setProperty('--cosmdl-side-gap', gap + 'px');"
            . "  container.querySelectorAll('.right-controls').forEach(function(rc){ rc.style.paddingRight = gap + 'px'; });"
            . "});"
            /* 批量删除逻辑 */
            . "document.addEventListener('change', function(e){"
            . "  if(e.target.classList.contains('cosmdl-select-all-logs')){"
            . "    var table = e.target.closest('table');"
            . "    var checkboxes = table.querySelectorAll('.cosmdl-log-checkbox');"
            . "    checkboxes.forEach(function(cb){ cb.checked = e.target.checked; });"
            . "    cosmdl_update_batch_btn(table);"
            . "  }"
            . "  if(e.target.classList.contains('cosmdl-log-checkbox')){"
            . "    var table = e.target.closest('table');"
            . "    var all = table.querySelectorAll('.cosmdl-log-checkbox');"
            . "    var checked = table.querySelectorAll('.cosmdl-log-checkbox:checked');"
            . "    var selectAll = table.querySelector('.cosmdl-select-all-logs');"
            . "    if(selectAll){ selectAll.checked = (all.length > 0 && all.length === checked.length); }"
            . "    cosmdl_update_batch_btn(table);"
            . "  }"
            . "});"
            . "function cosmdl_update_batch_btn(table){"
            . "  var wrapper = table.closest('div').parentElement;"
            . "  var btn = wrapper.querySelector('.cosmdl-batch-delete-logs');"
            . "  if(!btn) return;"
            . "  var checked = table.querySelectorAll('.cosmdl-log-checkbox:checked');"
            . "  btn.disabled = checked.length === 0;"
            . "  btn.textContent = checked.length > 0 ? '" . esc_js(__('批量删除选中', 'cosmautdl')) . " (' + checked.length + ')' : '" . esc_js(__('批量删除选中', 'cosmautdl')) . "';"
            . "}"
            . "document.addEventListener('click', function(e){"
            . "  if(e.target.classList.contains('cosmdl-batch-delete-logs')){"
            . "    var btn = e.target;"
            . "    if(btn.disabled) return;"
            . "    var wrapper = btn.closest('div').parentElement;"
            . "    var table = wrapper.querySelector('table');"
            . "    var checked = table.querySelectorAll('.cosmdl-log-checkbox:checked');"
            . "    if(checked.length === 0) return;"
            . "    var ids = [];"
            . "    checked.forEach(function(cb){ ids.push(cb.value); });"
            . "    var message = '" . esc_js(__('确定要删除选中的', 'cosmautdl')) . "' + ids.length + '" . esc_js(__('条下载记录吗？此操作不可撤销。', 'cosmautdl')) . "';"
            . "    showDeleteModal(message, function(){"
            . "      btn.disabled = true;"
            . "      btn.textContent = '" . esc_js(__('删除中...', 'cosmautdl')) . "';"
            . "      var fd = new FormData();"
            . "      fd.append('action', 'cosmdl_batch_delete_logs');"
            . "      ids.forEach(function(id){ fd.append('ids[]', id); });"
            . "      fd.append('nonce', document.getElementById('cosmdl-ajax-nonce').value);"
            . "      fetch(ajaxurl, {method:'POST', body:fd})"
            . "        .then(function(r){ return r.json(); })"
            . "        .then(function(r){"
            . "          if(r.success){"
            . "            checked.forEach(function(cb){ var row = cb.closest('tr'); if(row) row.remove(); });"
            . "            btn.disabled = true;"
            . "            btn.textContent = '" . esc_js(__('批量删除选中', 'cosmautdl')) . "';"
            . "            var selectAll = table.querySelector('.cosmdl-select-all-logs');"
            . "            if(selectAll) selectAll.checked = false;"
            . "            showDeleteNotice();"
            . "          } else {"
            . "            alert(r.data || '" . esc_js(__('删除失败', 'cosmautdl')) . "');"
            . "            btn.disabled = false;"
            . "            btn.textContent = '" . esc_js(__('批量删除选中', 'cosmautdl')) . " (' + checked.length + ')';"
            . "          }"
            . "        })"
            . "        .catch(function(){"
            . "          alert('" . esc_js(__('网络错误', 'cosmautdl')) . "');"
            . "          btn.disabled = false;"
            . "          btn.textContent = '" . esc_js(__('批量删除选中', 'cosmautdl')) . " (' + checked.length + ')';"
            . "        });"
            . "    });"
            . "  }"
            . "});"
            /* 删除操作相关函数 */
            . "function showDeleteNotice(){"
            . "  var notice = document.getElementById('cosmdl-delete-notice');"
            . "  if(notice){ notice.classList.add('is-visible'); }"
            . "  setTimeout(function(){ hideDeleteNotice(); }, 3000);"
            . "}"
            . "function hideDeleteNotice(){"
            . "  var notice = document.getElementById('cosmdl-delete-notice');"
            . "  if(notice){ notice.classList.remove('is-visible'); }"
            . "}"
            . "function showDeleteModal(message, onConfirm){"
            . "  var modal = document.getElementById('cosmdl-delete-modal');"
            . "  var msgEl = document.getElementById('cosmdl-delete-modal-message');"
            . "  var confirmBtn = document.getElementById('cosmdl-delete-confirm');"
            . "  var cancelBtn = document.getElementById('cosmdl-delete-cancel');"
            . "  if(msgEl) msgEl.textContent = message || '" . esc_js(__('确定要删除选中的下载记录吗？此操作不可撤销。', 'cosmautdl')) . "';"
            . "  if(modal){ modal.classList.add('is-visible'); modal.setAttribute('aria-hidden', 'false'); }"
            . "  var handleConfirm = function(){"
            . "    hideDeleteModal();"
            . "    if(onConfirm) onConfirm();"
            . "    cleanup();"
            . "  };"
            . "  var handleCancel = function(){"
            . "    hideDeleteModal();"
            . "    cleanup();"
            . "  };"
            . "  var cleanup = function(){"
            . "    if(confirmBtn) confirmBtn.removeEventListener('click', handleConfirm);"
            . "    if(cancelBtn) cancelBtn.removeEventListener('click', handleCancel);"
            . "    if(modal) modal.removeEventListener('click', handleOverlayClick);"
            . "    document.removeEventListener('keydown', handleEsc);"
            . "  };"
            . "  var handleOverlayClick = function(e){"
            . "    if(e.target === modal){ handleCancel(); }"
            . "  };"
            . "  var handleEsc = function(e){"
            . "    if(e.key === 'Escape'){ handleCancel(); }"
            . "  };"
            . "  if(confirmBtn) confirmBtn.addEventListener('click', handleConfirm);"
            . "  if(cancelBtn) cancelBtn.addEventListener('click', handleCancel);"
            . "  if(modal) modal.addEventListener('click', handleOverlayClick);"
            . "  document.addEventListener('keydown', handleEsc);"
            . "}"
            . "function hideDeleteModal(){"
            . "  var modal = document.getElementById('cosmdl-delete-modal');"
            . "  if(modal){ modal.classList.remove('is-visible'); modal.setAttribute('aria-hidden', 'true'); }"
            . "}"
            /* 绑定删除通知关闭按钮 */
            . "document.addEventListener('DOMContentLoaded', function(){"
            . "  var deleteOk = document.getElementById('cosmdl-delete-ok');"
            . "  if(deleteOk){"
            . "    deleteOk.addEventListener('click', function(){ hideDeleteNotice(); });"
            . "  }"
            . "});"
            . '</script>';
    }
}

// 已移除站点默认填充钩子：出厂默认由硬编码提供

// 隐藏后台页脚左下与右下文本（仅在本插件页面生效）
add_action('current_screen', function($screen) {
	$screen_id = (is_object($screen) && isset($screen->id)) ? (string) $screen->id : '';
	if ($screen_id === '') {
		return;
	}
	if (strpos($screen_id, 'cosmdl') === false) {
		return;
	}
	add_filter('admin_footer_text', '__return_empty_string', 999);
	add_filter('update_footer', '__return_empty_string', 999);
});
