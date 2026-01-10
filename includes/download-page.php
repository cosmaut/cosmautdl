<?php
/**
 * 模板函数库
 * 负责生成下载页面的核心 HTML 内容
 * 
 * 包含：
 * 1. 基础数据获取辅助函数
 * 2. 网盘与附件逻辑判断辅助函数
 * 3. 页面模块渲染函数 (声明、卡片、网盘列表、提示等)
 * 4. 主入口函数 cosmdl_get_download_content
 */

if (!defined('ABSPATH')) { exit; }

// ==========================================
// 第一部分：基础辅助函数
// ==========================================

if (!function_exists('cosmdl_get_meta')) {
    /**
     * 读取文章元数据（使用 cosmdl_ 前缀）
     * 
     * @param int    $post_id 文章 ID
     * @param string $key     元数据键名
     * @return mixed 元数据值
     */
    function cosmdl_get_meta($post_id, $key){
        return get_post_meta($post_id, $key, true);
    }
}

if (!function_exists('cosmdl_get_options')) {
    /**
     * 获取插件全局设置（使用 cosmdl_options）
     * 
     * @return array 设置数组
     */
    function cosmdl_get_options(){
        $opts = get_option('cosmdl_options', array());
        return is_array($opts) ? $opts : array();
    }
}

if (!function_exists('cosmdl_get_drive_management_settings')) {
    /**
     * 获取网盘管理配置列表
     * 包含所有支持的网盘及其启用状态、标签、排序等信息
     * 
     * @return array 已启用的网盘列表，按 order 排序
     */
    function cosmdl_get_drive_management_settings() {
        $options = get_option('cosmdl_options', array());
        $drive_management = isset($options['drive_management']) && is_array($options['drive_management']) ? $options['drive_management'] : array();

        // 首次安装或从未保存设置时，drive_management 可能为空，需使用默认值兜底
        if (!is_array($drive_management) || empty($drive_management)) {
            // 引入默认配置值文件并获取网盘管理默认值
            require_once plugin_dir_path(__FILE__) . 'default-value.php';
            $drive_management = cosmdl_get_drive_management_defaults();
        }

        // 过滤出已启用的网盘
        $enabled_drives = array();
        foreach($drive_management as $key => $drive) {
            if (isset($drive['enabled']) && $drive['enabled'] === 'yes') {
                $enabled_drives[$key] = $drive;
            }
        }

        // 按顺序排序网盘
        uasort($enabled_drives, function($a, $b) {
            $orderA = isset($a['order']) ? intval($a['order']) : 999;
            $orderB = isset($b['order']) ? intval($b['order']) : 999;
            return $orderA - $orderB;
        });

        return $enabled_drives;
    }
}

if (!function_exists('cosmdl_key_for_index')) {
    /**
     * 根据附件索引获取对应的元数据键名
     * 
     * @param string $key   基础键名
     * @param int    $index 附件索引 (1-6)
     * @return string 处理后的键名
     */
    function cosmdl_key_for_index($key, $index){
        $index = intval($index);
        if ($index <= 1) { return $key; }
        return preg_replace('/^cosmdl_/', 'cosmdl' . $index . '_', $key);
    }
}

if (!function_exists('cosmdl_get_field_names_for_drive')) {
    /**
     * 获取特定网盘在特定附件索引下的字段名映射
     * 
     * @param string $drive_key    网盘标识 (如 baidu)
     * @param int    $attach_index 附件索引
     * @param bool   $is_custom    是否为自定义网盘
     * @return array 包含 url, pwd, unlock 键名的数组
     */
    function cosmdl_get_field_names_for_drive($drive_key, $attach_index = 1, $is_custom = false) {
        $attach_index = intval($attach_index);
        $prefix = ($attach_index === 1) ? 'cosmdl_' : ('cosmdl' . $attach_index . '_');

        if ($is_custom) {
            // 自定义网盘：cosmdl_downurl_custom_{ID}
            // 去除 custom_ 前缀以匹配 meta box 保存格式
            $clean_key = preg_replace('/^custom_/', '', $drive_key);
            return array(
                'url'    => $prefix . 'downurl_custom_' . $clean_key,
                'pwd'    => $prefix . 'cipher_custom_' . $clean_key,
                'unlock' => $prefix . 'unlock_custom_' . $clean_key
            );
        } else {
            // 标准网盘：cosmdl_downurl_{ID}
            return array(
                'url'    => $prefix . 'downurl_' . $drive_key,
                'pwd'    => $prefix . 'cipher_' . $drive_key,
                'unlock' => $prefix . 'unlock_' . $drive_key
            );
        }
    }
}

if (!function_exists('cosmdl_generate_scene_key')) {
    /**
     * 生成当前下载会话使用的 scene 标识
     * 
     * @param int $post_id  文章ID
     * @param int $attach   附件索引
     * @return string 唯一场景标识
     */
    function cosmdl_generate_scene_key($post_id, $attach) {
        $post_id = intval($post_id);
        $attach = intval($attach);
        // 中文注释：使用文章ID、附件索引与随机串组合，尽量避免被轻易猜测
        return 'cosmdl_' . $post_id . '_' . $attach . '_' . wp_generate_password(10, false, false);
    }
}

if (!function_exists('cosmdl_drive_logo_html')) {
    /**
     * 生成网盘 LOGO 的 HTML
     * 
     * @param string $alias 网盘别名
     * @param string $key   网盘键名
     * @return string img 标签 HTML 或空字符串
     */
    function cosmdl_drive_logo_html($alias, $key){
        $candidates = array($alias, $key);
        foreach($candidates as $name){
            $slug = strtolower($name);
            // 轻量 sanitize：仅保留字母、数字与短横线
            $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
            if (!$slug) { continue; }
            $path = trailingslashit(COSMDL_PLUGIN_DIR) . 'images/' . $slug . '.png';
            if (file_exists($path)){
                    $url = trailingslashit(COSMDL_PLUGIN_URL) . 'images/' . $slug . '.png';
                    // 20251206: 调整图标大小为 40x40
                    return '<img class="cosmdl-pan-logo" src="' . esc_url($url) . '" alt="" aria-hidden="true" width="40" height="40" />';
                }
        }
        return '';
    }
}

// ==========================================
// 第二部分：扩展辅助函数 (工具类)
// ==========================================

if (!function_exists('cosmdl_size_to_bytes')) {
    /**
     * 将带单位的大小字符串转换为字节数
     * 
     * @param string $s 大小字符串 (如 "10MB")
     * @return int 字节数
     */
    function cosmdl_size_to_bytes($s){
        if (!$s) return 0; $s = trim($s);
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(([Kk][Bb]?)|([Mm][Bb]?)|([Gg][Bb]?)|([KkMmGg]))?$/', $s, $m)) return 0;
        $num = floatval($m[1]);
        $unit = isset($m[2]) ? strtoupper($m[2]) : '';
        if ($unit==='KB') $unit='K'; elseif($unit==='MB') $unit='M'; elseif($unit==='GB') $unit='G';
        $mul = 1;
        if ($unit==='K') $mul=1024;
        elseif($unit==='M') $mul=1048576;
        elseif($unit==='G') $mul=1073741824;
        return intval($num*$mul);
    }
}

if (!function_exists('cosmdl_calc_bytes')) {
    /**
     * 计算文件字节大小，优先参考单位元数据
     */
    function cosmdl_calc_bytes($size, $unit_meta){
        $unit = strtoupper(trim($unit_meta));
        if ($size!=='' && in_array($unit, array('KB','MB','GB'))){
            $num = floatval($size);
            $mul = ($unit==='KB')?1024:(($unit==='MB')?1048576:1073741824);
            return intval($num*$mul);
        }
        return cosmdl_size_to_bytes($size);
    }
}

if (!function_exists('cosmdl_post_has_any_link')) {
    /**
     * 判断文章是否包含任何下载链接 (遍历附件 1-6)
     */
    function cosmdl_post_has_any_link($pid, $drive_management = array()){
        if (!is_array($drive_management)) return false;
        for($i=1;$i<=6;$i++){
            foreach($drive_management as $dk => $dv){
                $is_custom = (isset($dv['is_custom']) && $dv['is_custom'] === 'yes');
                $fields = cosmdl_get_field_names_for_drive($dk, $i, $is_custom);
                $v = get_post_meta($pid, $fields['url'], true);
                if ($v !== '' && $v !== null) return true;
            }
        }
        return false;
    }
}

if (!function_exists('cosmdl_post_has_type')) {
    /**
     * 判断文章是否包含特定类型的下载链接
     */
    function cosmdl_post_has_type($pid, $type, $drive_management = array()){
        if (!is_array($drive_management)) return false;
        
        $target_key = null;
        $is_custom = false;

        // 尝试直接匹配或通过别名匹配
        if (isset($drive_management[$type])) {
            $target_key = $type;
            $is_custom = (isset($drive_management[$type]['is_custom']) && $drive_management[$type]['is_custom'] === 'yes');
        } else {
            foreach($drive_management as $dk => $dv){
                $alias = (isset($dv['alias']) && $dv['alias'] !== '') ? preg_replace('/[^a-z0-9\-]/','', strtolower($dv['alias'])) : '';
                if ($type === $alias) {
                    $target_key = $dk;
                    $is_custom = (isset($dv['is_custom']) && $dv['is_custom'] === 'yes');
                    break;
                }
            }
        }

        if ($target_key === null) return false;

        for($i=1; $i<=6; $i++){
            $fields = cosmdl_get_field_names_for_drive($target_key, $i, $is_custom);
            $v = get_post_meta($pid, $fields['url'], true);
            if ($v !== '' && $v !== null) return true;
        }
        return false;
    }
}

if (!function_exists('cosmdl_attach_has_any_link')) {
    /**
     * 判断特定附件索引是否有任何下载链接
     */
    function cosmdl_attach_has_any_link($pid, $i, $drive_management = array()){
        if (!is_array($drive_management)) return false;
        foreach($drive_management as $dk => $dv){
            $is_custom = (isset($dv['is_custom']) && $dv['is_custom'] === 'yes');
            $fields = cosmdl_get_field_names_for_drive($dk, $i, $is_custom);
            $v = get_post_meta($pid, $fields['url'], true);
            if ($v !== '' && $v !== null) return true;
        }
        return false;
    }
}

if (!function_exists('cosmdl_attach_has_type')) {
    /**
     * 判断特定附件索引是否包含特定类型的下载链接
     */
    function cosmdl_attach_has_type($pid, $type, $i, $drive_management = array()){
        if (!is_array($drive_management)) return false;
        
        $target_key = null;
        $is_custom = false;
        
        if (isset($drive_management[$type])) {
            $target_key = $type;
            $is_custom = (isset($drive_management[$type]['is_custom']) && $drive_management[$type]['is_custom'] === 'yes');
        } else {
            foreach($drive_management as $dk => $dv){
                $alias = (isset($dv['alias']) && $dv['alias'] !== '') ? preg_replace('/[^a-z0-9\-]/','', strtolower($dv['alias'])) : '';
                if ($type === $alias) {
                    $target_key = $dk;
                    $is_custom = (isset($dv['is_custom']) && $dv['is_custom'] === 'yes');
                    break;
                }
            }
        }
        
        if ($target_key === null) return false;
        
        $fields = cosmdl_get_field_names_for_drive($target_key, $i, $is_custom);
        $v = get_post_meta($pid, $fields['url'], true);
        return ($v !== '' && $v !== null);
    }
}

// ==========================================
// 第三部分：页面模块渲染函数
// ==========================================

if (!function_exists('cosmdl_render_download_statement')) {
    // 中文注释：引入默认配置，并为下载声明模块提供默认值兜底
    require_once plugin_dir_path(__FILE__) . 'default-value.php';
    $cosmdl_statement_defaults = cosmdl_get_statement_defaults();

    /**
     * 渲染下载声明模块
     * 
     * @param array $opts 插件设置
     */
    function cosmdl_render_download_statement($opts) {
        global $cosmdl_statement_defaults;

        // 中文注释：处理模块开关，未设置时回退到默认值
        $show_statement = isset($opts['show_statement'])
            ? $opts['show_statement']
            : (isset($cosmdl_statement_defaults['show_statement']) ? $cosmdl_statement_defaults['show_statement'] : 'yes');
        if ($show_statement !== 'yes') {
            return;
        }

        // 中文注释：仅当选项键不存在时才回退到默认标题，
        // 若用户在设置页中将标题清空，则保持为空以便前端不显示标题
        if (array_key_exists('statement_title', $opts)) {
            $statement_title = $opts['statement_title'];
        } else {
            $statement_title = isset($cosmdl_statement_defaults['statement_title'])
                ? $cosmdl_statement_defaults['statement_title']
                : __('下载声明', 'cosmautdl');
        }

        // 中文注释：声明内容同样区分“未设置”和“主动留空”的情况
        if (array_key_exists('statement_text', $opts)) {
            $statement_text = $opts['statement_text'];
        } else {
            $statement_text = isset($cosmdl_statement_defaults['statement_text'])
                ? $cosmdl_statement_defaults['statement_text']
                : '';
        }

        $trimmed_title = trim((string) $statement_title);
        $trimmed_text  = trim((string) $statement_text);

        // 中文注释：当模块标题与声明内容都为空时不显示整个模块
        if ($trimmed_title === '' && $trimmed_text === '') {
            return;
        }

        // 获取声明样式设置
        $default_border_color = '#e5e7eb';
        $default_bg_color = '#ffffff';
        $default_title_color = '#111827';
        $default_text_color = '#6b7280';

        $border_color = isset($opts['statement_border_color']) && $opts['statement_border_color'] !== '' ? $opts['statement_border_color'] : $default_border_color;
        $bg_color = isset($opts['statement_bg_color']) && $opts['statement_bg_color'] !== '' ? $opts['statement_bg_color'] : $default_bg_color;
        $title_color = isset($opts['statement_title_color']) && $opts['statement_title_color'] !== '' ? $opts['statement_title_color'] : $default_title_color;
        $text_color = isset($opts['statement_text_color']) && $opts['statement_text_color'] !== '' ? $opts['statement_text_color'] : $default_text_color;
        $custom_css = isset($opts['statement_custom_css']) ? $opts['statement_custom_css'] : '';
        $card_theme = isset($opts['statement_card_theme']) ? $opts['statement_card_theme'] : '';
        
        $theme_colors = array(
            'blue' => array('border' => '#acd0f9', 'bg' => '#e8f2fd', 'text' => '#4285f4'),
            'green' => array('border' => '#a8dbc1', 'bg' => '#e7f5ee', 'text' => '#34a853'),
            'purple' => array('border' => '#e1bbfc', 'bg' => '#f7edfe', 'text' => '#a256e3'),
            'orange' => array('border' => '#f9d69f', 'bg' => '#fdf3e4', 'text' => '#fbbc05'),
            'red' => array('border' => '#ffb7b2', 'bg' => '#fff5f4', 'text' => '#ea4335'),
            'gray' => array('border' => '#d1d5db', 'bg' => '#f5f6f8', 'text' => '#64748b')
        );
        
        if (!empty($card_theme) && isset($theme_colors[$card_theme])) {
            if ($border_color === $default_border_color) {
                $border_color = $theme_colors[$card_theme]['border'];
            }
            if ($bg_color === $default_bg_color) {
                $bg_color = $theme_colors[$card_theme]['bg'];
            }
            if ($title_color === $default_title_color) {
                $title_color = $theme_colors[$card_theme]['text'];
            }
            if ($text_color === $default_text_color) {
                $text_color = $theme_colors[$card_theme]['text'];
            }
        }

        $card_theme_is_default = ($card_theme === '' || $card_theme === 'default');
		$using_default_colors = (
			$border_color === $default_border_color &&
			$bg_color === $default_bg_color &&
			$title_color === $default_title_color &&
			$text_color === $default_text_color
		);

		$should_output_inline_style = !($card_theme_is_default && $using_default_colors);
		$inline_css = '';
		if ($should_output_inline_style) {
			$border_color_s = sanitize_hex_color($border_color);
			$bg_color_s = sanitize_hex_color($bg_color);
			$title_color_s = sanitize_hex_color($title_color);
			$text_color_s = sanitize_hex_color($text_color);
			if (!$border_color_s) { $border_color_s = $default_border_color; }
			if (!$bg_color_s) { $bg_color_s = $default_bg_color; }
			if (!$title_color_s) { $title_color_s = $default_title_color; }
			if (!$text_color_s) { $text_color_s = $default_text_color; }
			$inline_css .= '#cosmdl-statement-preview{--cosmdl-border:' . $border_color_s . ';--cosmdl-bg:' . $bg_color_s . ';--cosmdl-title:' . $title_color_s . ';--cosmdl-text:' . $text_color_s . ';}';
		}
		if (!empty($custom_css)) {
			$css_raw = wp_strip_all_tags((string) $custom_css);
			if ($css_raw !== '') {
				$inline_css .= ($inline_css !== '' ? "\n" : '') . $css_raw;
			}
		}
		if ($inline_css !== '') {
			wp_add_inline_style('cosmdl-style', $inline_css);
		}

		?>
		<div id="cosmdl-statement-preview" class="cosmdl-section cosmdl-statement-section">
			<?php if ($trimmed_title !== ''): ?>
			<h3 class="cosmdl-section-title cosmdl-statement-title"><?php echo esc_html($statement_title); ?></h3>
			<?php endif; ?>
			<?php if ($trimmed_text !== ''): ?>
			<p class="cosmdl-statement-text"><?php echo wp_kses_post($statement_text); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

if (!function_exists('cosmdl_render_file_info_card')) {
    /**
     * 渲染文件信息卡片
     * 
     * @param int   $post_id 文章ID
     * @param int   $attach  附件索引
     * @param array $opts    插件设置
     */
    function cosmdl_render_file_info_card($post_id, $attach, $opts) {
        // 获取相关元数据
        $cosmdl_name     = cosmdl_get_meta($post_id, cosmdl_key_for_index('cosmdl_name', $attach));
        $cosmdl_size     = cosmdl_get_meta($post_id, cosmdl_key_for_index('cosmdl_size', $attach));
        $cosmdl_size_unit = cosmdl_get_meta($post_id, 'cosmdl_size_unit');
        $cosmdl_date     = cosmdl_get_meta($post_id, cosmdl_key_for_index('cosmdl_date', $attach));
        $cosmdl_author   = cosmdl_get_meta($post_id, cosmdl_key_for_index('cosmdl_author', $attach));
        $cosmdl_softtype = cosmdl_get_meta($post_id, cosmdl_key_for_index('cosmdl_softtype', $attach));

        // 中文注释：统一卡片外观设置（优先使用“下载页设置>文件信息”中的三项）
        $selected_theme  = isset($opts['file_info_card_theme']) ? $opts['file_info_card_theme'] : (isset($opts['card_theme']) ? $opts['card_theme'] : 'green');
        $selected_radius = isset($opts['file_info_card_border_radius']) ? $opts['file_info_card_border_radius'] : (isset($opts['card_border_radius']) ? $opts['card_border_radius'] : 'medium');
        $show_shadow     = isset($opts['file_info_card_shadow']) ? $opts['file_info_card_shadow'] : (isset($opts['card_shadow']) ? $opts['card_shadow'] : 'yes');

        // 中文注释：主题色映射（使用 RGB 以便生成渐变背景），兼容后台可选项
        $theme_map = array(
            'green'  => array('rgb' => '34,197,94'),
            'blue'   => array('rgb' => '63,131,248'),
            'red'    => array('rgb' => '239,68,68'),
            'purple' => array('rgb' => '124,58,237'),
            'orange' => array('rgb' => '245,158,11'),
            'pink'   => array('rgb' => '236,72,153'),
            'indigo' => array('rgb' => '99,102,241'),
            'teal'   => array('rgb' => '20,184,166'),
            'gray'   => array('rgb' => '107,114,128')
        );
        $theme_rgb = isset($theme_map[$selected_theme]) ? $theme_map[$selected_theme]['rgb'] : $theme_map['green']['rgb'];

        // 中文注释：圆角映射统一（none 0px / small 4px / medium 8px / large 16px）
        switch($selected_radius){
            case 'none':   $radius_px = '0px';  break;
            case 'small':  $radius_px = '4px';  break;
            case 'medium': $radius_px = '8px';  break;
            case 'large':  $radius_px = '16px'; break;
            default:       $radius_px = '8px';  break;
        }
        // 中文注释：阴影开关
        $shadow_css = ($show_shadow === 'yes') ? '0 2px 16px rgba(0,0,0,0.06)' : 'none';

        // 中文注释：通过 CSS 变量传递外观参数，减少重复维护
        if (array_key_exists('file_info_title', $opts)) {
            $file_title = $opts['file_info_title'];
        } else {
            $file_title = __('文件信息','cosmautdl');
        }
        
        // 中文注释：获取文件信息开关状态
        $show_fileinfo = isset($opts['show_fileinfo']) ? $opts['show_fileinfo'] : 'yes'; 

		// 中文注释：是否存在用户填写的“模块标题”（用于标题为空内容也为空时的显示判断）
		$has_file_title_setting = array_key_exists('file_info_title', $opts);
		$file_title_trimmed = trim((string) $file_title);
		$has_any_file_meta = (!empty($cosmdl_name) || !empty($cosmdl_size) || !empty($cosmdl_date) || !empty($cosmdl_author) || !empty($cosmdl_softtype));
        
        // 中文注释：显示逻辑统一为“模块标题或模块内容有值就显示”；
        // 文件信息内容为文章/附件元数据，标题仅在用户明确设置时才作为显示依据。
        if (
			$show_fileinfo === 'yes'
			&& (
				$has_any_file_meta
				|| ($has_file_title_setting && $file_title_trimmed !== '')
			)
		): 
        ?>
        <?php
		$theme_rgb_s = preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', (string) $theme_rgb) ? (string) $theme_rgb : $theme_map['green']['rgb'];
		$radius_px_s = in_array($radius_px, array('0px', '4px', '8px', '16px'), true) ? $radius_px : '8px';
		$shadow_css_s = ($shadow_css === 'none' || $shadow_css === '0 2px 16px rgba(0,0,0,0.06)') ? $shadow_css : '0 2px 16px rgba(0,0,0,0.06)';
		wp_add_inline_style(
			'cosmdl-style',
			'#cosmdl-download-wrap .file-info-card{--cosmdl-card-radius:' . $radius_px_s . ';--cosmdl-card-shadow:' . $shadow_css_s . ';--cosmdl-theme-rgb:' . $theme_rgb_s . ';}'
		);
		?>
		<div class="cosmdl-card file-info-card" aria-label="<?php echo esc_attr__('文件信息', 'cosmautdl'); ?>">
            <?php if (trim((string) $file_title) !== ''): ?>
            <div class="cosmdl-card-header">
                <span class="cosmdl-card-icon">⬇</span>
                <span class="cosmdl-card-title"><?php echo esc_html($file_title); ?></span>
            </div>
            <?php endif; ?>
            <div class="cosmdl-card-body no-aside">
                <div class="cosmdl-meta">
                        <p><?php echo esc_html__('文件名称：', 'cosmautdl'); ?><span><?php echo esc_html($cosmdl_name); ?></span></p>
                        <?php if (!empty($cosmdl_softtype)): ?>
                            <p><?php echo esc_html__('软件性质：', 'cosmautdl'); ?><span><?php echo esc_html($cosmdl_softtype); ?></span></p>
                        <?php endif; ?>
                        <?php 
                        $upload_date = get_the_date('Y-m-d', $post_id); 
                        $update_date = $cosmdl_date; 
                        if (!empty($update_date)){ 
                            echo '<p>' . esc_html__('更新日期：', 'cosmautdl') . '<span>'.esc_html($update_date).'</span></p>'; 
                        } else { 
                            echo '<p>' . esc_html__('发布日期：', 'cosmautdl') . '<span>'.esc_html($upload_date).'</span></p>'; 
                        } 
                        ?>

                        <?php if (!empty($cosmdl_size)) : ?>
                            <?php 
                            $__unit = strtoupper(trim($cosmdl_size_unit)); 
                            $__unit = in_array($__unit, array('KB','MB','GB')) ? ' '.$__unit : ''; 
                            ?>
                            <p><?php echo esc_html__('文件大小：', 'cosmautdl'); ?><span><?php echo esc_html($cosmdl_size . $__unit); ?></span></p>
                        <?php endif; ?>

                        <?php 
                        // 中文注释：将“原文出处”移动到“文件大小”之后，与实时预览卡片顺序一致
                        $origin = get_permalink($post_id); 
                        $origin_text = rawurldecode($origin); 
                        ?>
                        <p><?php echo esc_html__('原文出处：', 'cosmautdl'); ?><a class="cosmdl-link" href="<?php echo esc_url($origin); ?>"><?php echo esc_html($origin_text); ?></a></p>

                        <?php if (!empty($cosmdl_author)): ?>
                            <p><?php echo esc_html__('作者信息：', 'cosmautdl'); ?><span><?php echo esc_html($cosmdl_author); ?></span></p>
                        <?php endif; ?>
                </div>
                
                <?php 
                // 广告位渲染
                $show_ad_slot = isset($opts['show_ad_slot']) ? $opts['show_ad_slot'] : 'no';
                $ad_html = isset($opts['ad_html']) ? $opts['ad_html'] : '';
                
                if ($show_ad_slot === 'yes' && !empty($ad_html)): ?>
                    <div class="cosmdl-ad-slot">
                        <div class="cosmdl-ad-container">
                            <?php echo wp_kses_post($ad_html); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }
}

if (!function_exists('cosmdl_render_custom_links')) {
    /**
     * 渲染自定义链接区域 (如：官方演示、购买链接等)
     * 
     * @param array $opts 插件设置
     */
    function cosmdl_render_custom_links($opts) {
        $show_custom = isset($opts['show_custom_links']) ? $opts['show_custom_links'] : 'yes'; 
        if ($show_custom !== 'yes') return;

        // 1. 收集有效链接
        $valid_links = array();
        for ($i=1; $i<=4; $i++) { 
            $label = isset($opts['custom_link_'.$i.'_label']) ? $opts['custom_link_'.$i.'_label'] : ''; 
            $url = isset($opts['custom_link_'.$i.'_url']) ? $opts['custom_link_'.$i.'_url'] : ''; 
            if ($label && $url) { 
                $valid_links[] = array('label' => $label, 'url' => $url);
            } 
        }

		$count = count($valid_links);

		// 中文注释：仅当选项键不存在时才回退到默认标题；
		// 若用户在设置页中将标题清空，则保持为空以便前端不显示标题。
		$has_custom_title_setting = array_key_exists('custom_links_title', $opts);
		if ($has_custom_title_setting) {
			$custom_title = $opts['custom_links_title'];
		} else {
			$custom_title = __('自定义链接','cosmautdl');
		}
		$custom_title_trimmed = trim((string) $custom_title);

		// 中文注释：显示逻辑统一为“标题或内容有值就显示”；
		// 其中“标题有值”仅指用户在设置页明确填写了标题（避免无链接时显示默认空模块）。
		if ($count === 0 && (!$has_custom_title_setting || $custom_title_trimmed === '')) {
			return;
		}

        $custom_css = '';
        $has_custom_style = false;

        $border_color = isset($opts['custom_links_border_color_hex']) && $opts['custom_links_border_color_hex'] !== ''
            ? $opts['custom_links_border_color_hex']
            : (isset($opts['custom_links_border_color']) ? $opts['custom_links_border_color'] : '');
        $bg_color = isset($opts['custom_links_bg_color_hex']) && $opts['custom_links_bg_color_hex'] !== ''
            ? $opts['custom_links_bg_color_hex']
            : (isset($opts['custom_links_bg_color']) ? $opts['custom_links_bg_color'] : '');
        $title_color = isset($opts['custom_links_title_color_hex']) && $opts['custom_links_title_color_hex'] !== ''
            ? $opts['custom_links_title_color_hex']
            : (isset($opts['custom_links_title_color']) ? $opts['custom_links_title_color'] : '');
        $text_color = isset($opts['custom_links_text_color_hex']) && $opts['custom_links_text_color_hex'] !== ''
            ? $opts['custom_links_text_color_hex']
            : (isset($opts['custom_links_text_color']) ? $opts['custom_links_text_color'] : '');

        $card_theme = isset($opts['custom_links_card_theme']) ? $opts['custom_links_card_theme'] : '';
        $theme_colors = array(
            'blue' => array('border' => '#acd0f9', 'bg' => '#e8f2fd', 'text' => '#4285f4'),
            'green' => array('border' => '#a8dbc1', 'bg' => '#e7f5ee', 'text' => '#34a853'),
            'purple' => array('border' => '#e1bbfc', 'bg' => '#f7edfe', 'text' => '#a256e3'),
            'orange' => array('border' => '#f9d69f', 'bg' => '#fdf3e4', 'text' => '#fbbc05'),
            'red' => array('border' => '#ffb7b2', 'bg' => '#fff5f4', 'text' => '#ea4335'),
            'gray' => array('border' => '#d1d5db', 'bg' => '#f5f6f8', 'text' => '#64748b'),
        );

        if ($card_theme !== '' && isset($theme_colors[$card_theme])) {
            if ($border_color === '') {
                $border_color = $theme_colors[$card_theme]['border'];
            }
            if ($bg_color === '') {
                $bg_color = $theme_colors[$card_theme]['bg'];
            }
            if ($title_color === '') {
                $title_color = $theme_colors[$card_theme]['text'];
            }
            if ($text_color === '') {
                $text_color = $theme_colors[$card_theme]['text'];
            }
        }

		$user_css = isset($opts['custom_links_custom_css']) ? $opts['custom_links_custom_css'] : '';
		$has_custom_style = ($border_color !== '' || $bg_color !== '' || $title_color !== '' || $text_color !== '' || $user_css !== '');
		$style_vars = '--cosmdl-link-count:' . intval($count) . ';';
		if ($border_color !== '') {
			$style_vars .= '--cosmdl-border:' . $border_color . ';';
		}
		if ($bg_color !== '') {
			$style_vars .= '--cosmdl-bg:' . $bg_color . ';';
		}
		if ($title_color !== '') {
			$style_vars .= '--cosmdl-title:' . $title_color . ';';
		}
		if ($text_color !== '') {
			$style_vars .= '--cosmdl-text:' . $text_color . ';';
		}

		$inline_css = '#cosmdl-download-wrap .cosmdl-custom-links{--cosmdl-link-count:' . intval($count) . ';';
		if ($border_color !== '') {
			$border_color_s = sanitize_hex_color($border_color);
			if ($border_color_s) {
				$inline_css .= '--cosmdl-border:' . $border_color_s . ';';
			}
		}
		if ($bg_color !== '') {
			$bg_color_s = sanitize_hex_color($bg_color);
			if ($bg_color_s) {
				$inline_css .= '--cosmdl-bg:' . $bg_color_s . ';';
			}
		}
		if ($title_color !== '') {
			$title_color_s = sanitize_hex_color($title_color);
			if ($title_color_s) {
				$inline_css .= '--cosmdl-title:' . $title_color_s . ';';
			}
		}
		if ($text_color !== '') {
			$text_color_s = sanitize_hex_color($text_color);
			if ($text_color_s) {
				$inline_css .= '--cosmdl-text:' . $text_color_s . ';';
			}
		}
		$inline_css .= '}';
		$css_raw = '';
		if ($user_css !== '') {
			$css_raw = wp_strip_all_tags((string) $user_css);
		}
		if ($css_raw !== '') {
			$inline_css .= "\n" . $css_raw;
		}
		wp_add_inline_style('cosmdl-style', $inline_css);
		echo '<div class="cosmdl-section cosmdl-custom-links cosmdl-mt-10">';
        if ($custom_title_trimmed !== '') {
            echo '<div class="cosmdl-custom-links-title-wrapper">';
            echo '<h3 class="cosmdl-section-title cosmdl-custom-links-title">' . esc_html($custom_title) . '</h3>';
            echo '</div>';
        }
        echo '<div class="cosmdl-custom-links-content-wrapper">';
        foreach ($valid_links as $link) {
            echo '<div class="cosmdl-custom-link-item">';
            echo '<a class="cosmdl-link" href="' . esc_url($link['url']) . '" target="_blank" rel="noopener">' . esc_html($link['label']) . '</a>'; 
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
}

if (!function_exists('cosmdl_render_drive_cards')) {
    /**
     * 渲染网盘下载卡片列表
     * 
     * @param int   $post_id 文章ID
     * @param int   $attach  附件索引
     * @param array $opts    插件设置
     */
    function cosmdl_render_drive_cards($post_id, $attach, $opts) {
        $show = isset($opts['show_pan_cards']) ? $opts['show_pan_cards'] : 'yes';
        if ($show !== 'yes') return;

        // 中文注释：读取扫码解锁相关设置
        $show_qr_block = isset($opts['show_qr_block']) ? $opts['show_qr_block'] : 'no';
        $qr_mode       = isset($opts['qr_unlock_mode']) ? $opts['qr_unlock_mode'] : 'static';
        $qr_follow_txt = isset($opts['qr_follow_text']) ? $opts['qr_follow_text'] : __('关注公众号后自动解锁下载链接', 'cosmautdl');

        // 中文注释：记录当前附件是否存在需要扫码解锁的网盘按钮
        $has_locked_drive = false;
        $scene = '';

        $border_color = isset($opts['pan_cards_border_color']) ? $opts['pan_cards_border_color'] : '#e5e7eb';
        $bg_color     = isset($opts['pan_cards_bg_color'])     ? $opts['pan_cards_bg_color']     : '#ffffff';
        $title_color  = isset($opts['pan_cards_title_color'])  ? $opts['pan_cards_title_color']  : '#111827';
        $text_color   = isset($opts['pan_cards_text_color'])   ? $opts['pan_cards_text_color']   : '#6b7280';
        $custom_css   = isset($opts['pan_cards_custom_css'])   ? $opts['pan_cards_custom_css']   : '';

        $pan_card_theme = isset($opts['pan_cards_card_theme']) ? $opts['pan_cards_card_theme'] : '';
        if ($pan_card_theme !== '') {
            $pan_theme_colors = array(
                'blue' => array('border' => '#acd0f9', 'bg' => '#e8f2fd', 'text' => '#4285f4'),
                'green' => array('border' => '#a8dbc1', 'bg' => '#e7f5ee', 'text' => '#34a853'),
                'purple' => array('border' => '#e1bbfc', 'bg' => '#f7edfe', 'text' => '#a256e3'),
                'orange' => array('border' => '#f9d69f', 'bg' => '#fdf3e4', 'text' => '#fbbc05'),
                'red' => array('border' => '#ffb7b2', 'bg' => '#fff5f4', 'text' => '#ea4335'),
                'gray' => array('border' => '#d1d5db', 'bg' => '#f5f6f8', 'text' => '#64748b'),
            );

            if (isset($pan_theme_colors[$pan_card_theme])) {
                $preset = $pan_theme_colors[$pan_card_theme];

                if ($border_color === '#e5e7eb') {
                    $border_color = $preset['border'];
                }
                if ($bg_color === '#ffffff') {
                    $bg_color = $preset['bg'];
                }
                if ($title_color === '#111827') {
                    $title_color = $preset['text'];
                }
                if ($text_color === '#6b7280') {
                    $text_color = $preset['text'];
                }
            }
        }

        $default_border_color = '#e5e7eb';
        $default_bg_color = '#ffffff';
        $default_title_color = '#111827';
        $default_text_color = '#6b7280';

		$card_theme_is_default = ($pan_card_theme === '' || $pan_card_theme === 'default');
		$using_default_colors = (
			$border_color === $default_border_color &&
			$bg_color === $default_bg_color &&
			$title_color === $default_title_color &&
			$text_color === $default_text_color
		);

		$should_output_inline_style = !($card_theme_is_default && $using_default_colors);
		$inline_css = '';
		if ($should_output_inline_style) {
			$border_color_s = sanitize_hex_color($border_color);
			$bg_color_s = sanitize_hex_color($bg_color);
			$title_color_s = sanitize_hex_color($title_color);
			$text_color_s = sanitize_hex_color($text_color);
			if (!$border_color_s) { $border_color_s = $default_border_color; }
			if (!$bg_color_s) { $bg_color_s = $default_bg_color; }
			if (!$title_color_s) { $title_color_s = $default_title_color; }
			if (!$text_color_s) { $text_color_s = $default_text_color; }

			$inline_css .= '#cosmdl-download-wrap .cosmdl-pan-cards-section{--cosmdl-border:' . $border_color_s . ';--cosmdl-bg:' . $bg_color_s . ';--cosmdl-title:' . $title_color_s . ';--cosmdl-text:' . $text_color_s . ';}';
		}

		if (!empty($custom_css)) {
			$css_raw = wp_strip_all_tags((string) $custom_css);
			if ($css_raw !== '') {
				$inline_css .= ($inline_css !== '' ? "\n" : '') . $css_raw;
			}
		}

		if ($inline_css !== '') {
			wp_add_inline_style('cosmdl-style', $inline_css);
		}

        $pan_cards_html = isset($opts['pan_cards_html']) ? $opts['pan_cards_html'] : '';

        if (array_key_exists('pan_cards_title', $opts)) {
            $pan_title = $opts['pan_cards_title'];
        } else {
            $pan_title = __('网盘卡片','cosmautdl');
        }

        $pan_title_trimmed = trim((string) $pan_title);
        $pan_html_trimmed  = trim((string) $pan_cards_html);

        $drive_management = cosmdl_get_drive_management_settings();
        $has_any_link = cosmdl_attach_has_any_link($post_id, $attach, $drive_management);

        if ($pan_title_trimmed === '' && $pan_html_trimmed === '' && !$has_any_link) {
            return;
        }
		?>
		<div class="cosmdl-section cosmdl-pan-cards-section">
            <?php if (!empty($pan_title)): ?>
            <h3 class="cosmdl-section-title"><?php echo esc_html($pan_title); ?></h3>
            <?php endif; ?>
            <?php if (!empty($opts['pan_cards_html'])): ?>
                <div class="cosmdl-mt-10"><?php echo wp_kses_post($opts['pan_cards_html']); ?></div>
            <?php endif; ?>
			<div class="cosmdl-pan-group" id="cosmdl-pan-group">
				<?php $cosmdl_allowed_drive_logo = array( 'img' => array( 'class' => true, 'src' => true, 'alt' => true, 'aria-hidden' => true, 'width' => true, 'height' => true ) ); ?>
				<?php
				foreach($drive_management as $key => $drive) {
                    $is_custom = (isset($drive['is_custom']) && $drive['is_custom'] === 'yes');
                    $field_names = cosmdl_get_field_names_for_drive($key, $attach, $is_custom);
                    $url_value = cosmdl_get_meta($post_id, $field_names['url']);
                    
                    // 获取网盘别名
                    $alias_slug = '';
                    if (isset($drive['alias']) && $drive['alias'] !== '') { 
                        $alias_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($drive['alias'])); 
                    }
                    $effective_id = $alias_slug !== '' ? $alias_slug : $key;

                    if (empty($url_value)) continue;

                    $pwd_value = cosmdl_get_meta($post_id, $field_names['pwd']);

                    // 中文注释：判断当前网盘是否开启“扫码解锁”
                    $unlock_meta = cosmdl_get_meta($post_id, $field_names['unlock']);
                    $need_unlock = ($show_qr_block === 'yes' && $unlock_meta === 'yes');

                    // 构建基础跳转 URL（不含解锁参数）
                    $redirect_prefix = cosmdl_get_redirect_prefix();
                    $pretty = get_option('permalink_structure');
                    $is_pretty = !empty($pretty);
                    $btn_url = $is_pretty 
                        ? home_url($redirect_prefix . '/' . $post_id . '/' . $effective_id . '.html?attach=' . $attach)
                        : home_url('/?cosmdl_redirect=1&post_id=' . $post_id . '&type=' . $effective_id . '&attach=' . $attach);

                    // 中文注释：若需要扫码解锁，则为本附件生成统一的 scene 并将其附加到跳转 URL
                    $btn_href = $btn_url;
                    if ($need_unlock) {
                        if ($scene === '') {
                            $scene = cosmdl_generate_scene_key($post_id, $attach);
                        }
                        $has_locked_drive = true;
                        $btn_href = $btn_url . (strpos($btn_url, '?') !== false ? '&' : '?') . 'scene=' . rawurlencode($scene);
                    }

					?>
					<a class="cosmdl-pan-btn<?php echo $need_unlock ? ' cosmdl-pan-btn-locked' : ''; ?>"
					   href="<?php echo $need_unlock ? 'javascript:void(0);' : esc_url($btn_href); ?>"
                       <?php if ($need_unlock): ?>
                       data-scene="<?php echo esc_attr($scene); ?>"
                       data-target-url="<?php echo esc_url($btn_href); ?>"
                       data-mode="<?php echo esc_attr($qr_mode); ?>"
                       data-target="_blank"
                       data-rel="noopener nofollow"
                       <?php else: ?>
                       target="_blank" rel="noopener nofollow"
                       <?php endif; ?>
					>
						<?php echo wp_kses(cosmdl_drive_logo_html($alias_slug, $key), $cosmdl_allowed_drive_logo); ?>
						<span class="cosmdl-pan-info">
							<span class="cosmdl-pan-text"><?php echo esc_html($drive['label']); ?></span>
							<?php if ($pwd_value): ?>
								<span class="cosmdl-pan-pwd" title="<?php echo esc_attr__('点击复制密码', 'cosmautdl'); ?>" data-cosmdl-copy="<?php echo esc_attr($pwd_value); ?>"><?php echo esc_html__('提取码: ', 'cosmautdl') . esc_html($pwd_value); ?></span>
							<?php endif; ?>
						</span>
					</a>
                    <?php
                }
                ?>
            </div>
            <?php if ($has_locked_drive && $show_qr_block === 'yes' && $scene !== ''): ?>
                <?php
                $unlock_url = add_query_arg(
                    array(
                        'cosmdl_unlock' => 1,
                        'scene'         => $scene,
                    ),
                    home_url('/')
                );
                $qr_src = add_query_arg(
                    array(
                        'cosmdl_qr' => 1,
                        'text'      => rawurlencode($unlock_url),
                    ),
                    home_url('/')
                );

                if ($qr_mode === 'wechat') {
                    $mode_label = __('请使用微信扫码关注公众号，系统将自动校验关注状态并解锁本页下载按钮。', 'cosmautdl');
                } elseif ($qr_mode === 'group') {
                    $mode_label = __('请使用微信扫码并加入站长配置的微信群/社群，扫码完成后本页下载按钮将解锁（本插件仅记录扫码行为，无法校验是否真正完成进群）。', 'cosmautdl');
                } else {
                    $mode_label = __('请使用微信扫码完成验证，扫码成功后本页下载按钮将解锁。', 'cosmautdl');
                }
                ?>
                <div class="cosmdl-qr-modal" id="cosmdl-qr-modal" aria-hidden="true">
                    <div class="cosmdl-qr-modal-backdrop"></div>
                    <div class="cosmdl-qr-modal-dialog" role="dialog" aria-modal="true">
                        <button type="button" class="cosmdl-qr-modal-close" aria-label="<?php echo esc_attr__('关闭', 'cosmautdl'); ?>">
                            ×
                        </button>
                        <div class="cosmdl-qr-unlock" data-scene="<?php echo esc_attr($scene); ?>" data-mode="<?php echo esc_attr($qr_mode); ?>">
                            <div class="cosmdl-qr-heading">
                                <strong><?php echo esc_html__('扫码解锁下载按钮', 'cosmautdl'); ?></strong>
                            </div>
                            <div class="cosmdl-qr-body">
                                <img class="cosmdl-qr-image" src="<?php echo esc_url($qr_src); ?>" alt="<?php echo esc_attr__('使用微信扫码解锁下载按钮', 'cosmautdl'); ?>" width="180" height="180" />
                                <div class="cosmdl-qr-text">
                                    <p><?php echo esc_html($mode_label); ?></p>
                                    <?php if (!empty($qr_follow_txt)): ?>
                                        <p><?php echo esc_html($qr_follow_txt); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="cosmdl-qr-status" data-status="waiting"><?php echo esc_html__('等待扫码...', 'cosmautdl'); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('cosmdl_render_download_tips')) {
    // 引入默认配置值文件并获取下载说明模块默认值
    require_once plugin_dir_path(__FILE__) . 'default-value.php';
    $cosmdl_download_tips_defaults = cosmdl_get_download_tips_defaults();
    
	function cosmdl_render_download_tips($opts) {
		global $cosmdl_download_tips_defaults;
        
        if (array_key_exists('download_tips_title', $opts)) {
            $tips_title = $opts['download_tips_title'];
        } else {
			$tips_title = $cosmdl_download_tips_defaults['download_tips_title'];
        }

        if (array_key_exists('download_tips_html', $opts)) {
            $tips_html = $opts['download_tips_html'];
        } else {
			$tips_html = $cosmdl_download_tips_defaults['download_tips_html'];
        }
        
		// 中文注释：当模块标题与内容都为空（含仅空格）时，不显示模块
		$tips_title_trimmed = trim((string) $tips_title);
		$tips_html_trimmed  = trim((string) $tips_html);
		if ($tips_title_trimmed === '' && $tips_html_trimmed === '') {
			return;
		}

		$border_color = isset($opts['download_tips_border_color']) ? $opts['download_tips_border_color'] : (isset($cosmdl_download_tips_defaults['download_tips_border_color']) ? $cosmdl_download_tips_defaults['download_tips_border_color'] : '#e5e7eb');
		$bg_color     = isset($opts['download_tips_bg_color']) ? $opts['download_tips_bg_color'] : (isset($cosmdl_download_tips_defaults['download_tips_bg_color']) ? $cosmdl_download_tips_defaults['download_tips_bg_color'] : '#ffffff');
		$title_color  = isset($opts['download_tips_title_color']) ? $opts['download_tips_title_color'] : (isset($cosmdl_download_tips_defaults['download_tips_title_color']) ? $cosmdl_download_tips_defaults['download_tips_title_color'] : '#111827');
		$text_color   = isset($opts['download_tips_text_color']) ? $opts['download_tips_text_color'] : (isset($cosmdl_download_tips_defaults['download_tips_text_color']) ? $cosmdl_download_tips_defaults['download_tips_text_color'] : '#6b7280');
        $custom_css   = isset($opts['download_tips_custom_css']) ? $opts['download_tips_custom_css'] : '';

        $download_tips_card_theme = isset($opts['download_tips_card_theme']) ? $opts['download_tips_card_theme'] : '';
        if ($download_tips_card_theme !== '') {
            $download_tips_theme_colors = array(
                'blue' => array('border' => '#acd0f9', 'bg' => '#e8f2fd', 'text' => '#4285f4'),
                'green' => array('border' => '#a8dbc1', 'bg' => '#e7f5ee', 'text' => '#34a853'),
                'purple' => array('border' => '#e1bbfc', 'bg' => '#f7edfe', 'text' => '#a256e3'),
                'orange' => array('border' => '#f9d69f', 'bg' => '#fdf3e4', 'text' => '#fbbc05'),
                'red' => array('border' => '#ffb7b2', 'bg' => '#fff5f4', 'text' => '#ea4335'),
                'gray' => array('border' => '#d1d5db', 'bg' => '#f5f6f8', 'text' => '#64748b'),
            );

            if (isset($download_tips_theme_colors[$download_tips_card_theme])) {
                $preset = $download_tips_theme_colors[$download_tips_card_theme];

				$default_border = isset($cosmdl_download_tips_defaults['download_tips_border_color']) ? $cosmdl_download_tips_defaults['download_tips_border_color'] : '#e5e7eb';
				$default_bg = isset($cosmdl_download_tips_defaults['download_tips_bg_color']) ? $cosmdl_download_tips_defaults['download_tips_bg_color'] : '#ffffff';
				$default_title = isset($cosmdl_download_tips_defaults['download_tips_title_color']) ? $cosmdl_download_tips_defaults['download_tips_title_color'] : '#111827';
				$default_text = isset($cosmdl_download_tips_defaults['download_tips_text_color']) ? $cosmdl_download_tips_defaults['download_tips_text_color'] : '#6b7280';

                if ($border_color === $default_border) {
                    $border_color = $preset['border'];
                }
                if ($bg_color === $default_bg) {
                    $bg_color = $preset['bg'];
                }
                if ($title_color === $default_title) {
                    $title_color = $preset['text'];
                }
                if ($text_color === $default_text) {
                    $text_color = $preset['text'];
                }
            }
        }

		$default_border_color = isset($cosmdl_download_tips_defaults['download_tips_border_color']) ? $cosmdl_download_tips_defaults['download_tips_border_color'] : '#e5e7eb';
		$default_bg_color = isset($cosmdl_download_tips_defaults['download_tips_bg_color']) ? $cosmdl_download_tips_defaults['download_tips_bg_color'] : '#ffffff';
		$default_title_color = isset($cosmdl_download_tips_defaults['download_tips_title_color']) ? $cosmdl_download_tips_defaults['download_tips_title_color'] : '#111827';
		$default_text_color = isset($cosmdl_download_tips_defaults['download_tips_text_color']) ? $cosmdl_download_tips_defaults['download_tips_text_color'] : '#6b7280';

        $card_theme_is_default = ($download_tips_card_theme === '' || $download_tips_card_theme === 'default');
        $using_default_colors = (
            $border_color === $default_border_color &&
            $bg_color === $default_bg_color &&
            $title_color === $default_title_color &&
            $text_color === $default_text_color
        );

		$should_output_inline_style = !($card_theme_is_default && $using_default_colors);
		$inline_css = '';
		if ($should_output_inline_style) {
			$border_color_s = sanitize_hex_color($border_color);
			$bg_color_s = sanitize_hex_color($bg_color);
			$title_color_s = sanitize_hex_color($title_color);
			$text_color_s = sanitize_hex_color($text_color);
			if (!$border_color_s) { $border_color_s = $default_border_color; }
			if (!$bg_color_s) { $bg_color_s = $default_bg_color; }
			if (!$title_color_s) { $title_color_s = $default_title_color; }
			if (!$text_color_s) { $text_color_s = $default_text_color; }

			$inline_css .= '#cosmdl-download-wrap .cosmdl-download-tips-section{--cosmdl-border:' . $border_color_s . ';--cosmdl-bg:' . $bg_color_s . ';--cosmdl-title:' . $title_color_s . ';--cosmdl-text:' . $text_color_s . ';}';
		}

		if (!empty($custom_css)) {
			$css_raw = wp_strip_all_tags((string) $custom_css);
			if ($css_raw !== '') {
				$inline_css .= ($inline_css !== '' ? "\n" : '') . $css_raw;
			}
		}

		if ($inline_css !== '') {
			wp_add_inline_style('cosmdl-style', $inline_css);
		}
		?>
		<div class="cosmdl-section cosmdl-download-tips-section">
            <?php if (!empty($tips_title)): ?>
            <h3 class="cosmdl-section-title"><?php echo esc_html($tips_title); ?></h3>
            <?php endif; ?>
            <?php echo wp_kses_post($tips_html); ?>
        </div>
        <?php
    }
}

if (!function_exists('cosmdl_render_owner_statement')) {
    // 引入默认配置值文件并获取站长声明模块默认值
    require_once plugin_dir_path(__FILE__) . 'default-value.php';
	$cosmdl_owner_statement_defaults = cosmdl_get_owner_statement_defaults();

    /**
     * 渲染站长声明
     *
     * @param array $opts 插件设置
     */
	function cosmdl_render_owner_statement($opts) {
		global $cosmdl_owner_statement_defaults;

        $show_owner = isset($opts['show_owner_statement']) ? $opts['show_owner_statement'] : 'yes';
        if ($show_owner !== 'yes') {
            return;
        }

        if (array_key_exists('owner_statement_title', $opts)) {
            $owner_title = $opts['owner_statement_title'];
        } else {
			$owner_title = isset($cosmdl_owner_statement_defaults['owner_statement_title']) ? $cosmdl_owner_statement_defaults['owner_statement_title'] : '站长声明';
        }

        if (array_key_exists('owner_statement_html', $opts)) {
            $owner_html = $opts['owner_statement_html'];
        } else {
			$owner_html = isset($cosmdl_owner_statement_defaults['owner_statement_html']) ? $cosmdl_owner_statement_defaults['owner_statement_html'] : '';
        }
        
		// 中文注释：当模块标题与内容都为空（含仅空格）时，不显示模块
		$owner_title_trimmed = trim((string) $owner_title);
		$owner_html_trimmed  = trim((string) $owner_html);
		if ($owner_title_trimmed === '' && $owner_html_trimmed === '') {
			return;
		}

		$border_color = isset($opts['owner_statement_border_color'])
	            ? $opts['owner_statement_border_color']
			: (isset($cosmdl_owner_statement_defaults['owner_statement_border_color']) ? $cosmdl_owner_statement_defaults['owner_statement_border_color'] : '#e5e7eb');
		$bg_color = isset($opts['owner_statement_bg_color'])
	            ? $opts['owner_statement_bg_color']
			: (isset($cosmdl_owner_statement_defaults['owner_statement_bg_color']) ? $cosmdl_owner_statement_defaults['owner_statement_bg_color'] : '#ffffff');
		$title_color = isset($opts['owner_statement_title_color'])
	            ? $opts['owner_statement_title_color']
			: (isset($cosmdl_owner_statement_defaults['owner_statement_title_color']) ? $cosmdl_owner_statement_defaults['owner_statement_title_color'] : '#111827');
		$text_color = isset($opts['owner_statement_text_color'])
	            ? $opts['owner_statement_text_color']
			: (isset($cosmdl_owner_statement_defaults['owner_statement_text_color']) ? $cosmdl_owner_statement_defaults['owner_statement_text_color'] : '#6b7280');
        $custom_css = isset($opts['owner_statement_custom_css']) ? $opts['owner_statement_custom_css'] : '';

		$card_theme = isset($opts['owner_statement_card_theme']) ? $opts['owner_statement_card_theme'] : '';
		if ($card_theme !== '') {
			$owner_statement_theme_colors = array(
				'blue' => array('border' => '#acd0f9', 'bg' => '#e8f2fd', 'text' => '#4285f4'),
				'green' => array('border' => '#a8dbc1', 'bg' => '#e7f5ee', 'text' => '#34a853'),
				'purple' => array('border' => '#e1bbfc', 'bg' => '#f7edfe', 'text' => '#a256e3'),
				'orange' => array('border' => '#f9d69f', 'bg' => '#fdf3e4', 'text' => '#fbbc05'),
				'red' => array('border' => '#ffb7b2', 'bg' => '#fff5f4', 'text' => '#ea4335'),
				'gray' => array('border' => '#d1d5db', 'bg' => '#f5f6f8', 'text' => '#64748b'),
			);

			if (isset($owner_statement_theme_colors[$card_theme])) {
				$preset = $owner_statement_theme_colors[$card_theme];

				$default_border = isset($cosmdl_owner_statement_defaults['owner_statement_border_color']) ? $cosmdl_owner_statement_defaults['owner_statement_border_color'] : '#e5e7eb';
				$default_bg = isset($cosmdl_owner_statement_defaults['owner_statement_bg_color']) ? $cosmdl_owner_statement_defaults['owner_statement_bg_color'] : '#ffffff';
				$default_title = isset($cosmdl_owner_statement_defaults['owner_statement_title_color']) ? $cosmdl_owner_statement_defaults['owner_statement_title_color'] : '#111827';
				$default_text = isset($cosmdl_owner_statement_defaults['owner_statement_text_color']) ? $cosmdl_owner_statement_defaults['owner_statement_text_color'] : '#6b7280';

				if ($border_color === $default_border) {
					$border_color = $preset['border'];
				}
				if ($bg_color === $default_bg) {
					$bg_color = $preset['bg'];
				}
				if ($title_color === $default_title) {
					$title_color = $preset['text'];
				}
				if ($text_color === $default_text) {
					$text_color = $preset['text'];
				}
			}
		}

		$default_border_color = isset($cosmdl_owner_statement_defaults['owner_statement_border_color']) ? $cosmdl_owner_statement_defaults['owner_statement_border_color'] : '#e5e7eb';
		$default_bg_color = isset($cosmdl_owner_statement_defaults['owner_statement_bg_color']) ? $cosmdl_owner_statement_defaults['owner_statement_bg_color'] : '#ffffff';
		$default_title_color = isset($cosmdl_owner_statement_defaults['owner_statement_title_color']) ? $cosmdl_owner_statement_defaults['owner_statement_title_color'] : '#111827';
		$default_text_color = isset($cosmdl_owner_statement_defaults['owner_statement_text_color']) ? $cosmdl_owner_statement_defaults['owner_statement_text_color'] : '#6b7280';

		$card_theme_is_default = ($card_theme === '' || $card_theme === 'default');
		$using_default_colors = (
			$border_color === $default_border_color &&
			$bg_color === $default_bg_color &&
			$title_color === $default_title_color &&
			$text_color === $default_text_color
		);

		$should_output_inline_style = !($card_theme_is_default && $using_default_colors);
		$inline_css = '';
		if ($should_output_inline_style) {
			$border_color_s = sanitize_hex_color($border_color);
			$bg_color_s = sanitize_hex_color($bg_color);
			$title_color_s = sanitize_hex_color($title_color);
			$text_color_s = sanitize_hex_color($text_color);
			if (!$border_color_s) { $border_color_s = $default_border_color; }
			if (!$bg_color_s) { $bg_color_s = $default_bg_color; }
			if (!$title_color_s) { $title_color_s = $default_title_color; }
			if (!$text_color_s) { $text_color_s = $default_text_color; }

			$inline_css .= '#cosmdl-download-wrap .cosmdl-owner-statement-section{--cosmdl-border:' . $border_color_s . ';--cosmdl-bg:' . $bg_color_s . ';--cosmdl-title:' . $title_color_s . ';--cosmdl-text:' . $text_color_s . ';}';
		}

		if (!empty($custom_css)) {
			$css_raw = wp_strip_all_tags((string) $custom_css);
			if ($css_raw !== '') {
				$inline_css .= ($inline_css !== '' ? "\n" : '') . $css_raw;
			}
		}

		if ($inline_css !== '') {
			wp_add_inline_style('cosmdl-style', $inline_css);
		}
		?>
		<div class="cosmdl-section cosmdl-owner-statement-section">
            <?php if (!empty($owner_title)): ?>
            <h3 class="cosmdl-section-title"><?php echo esc_html($owner_title); ?></h3>
            <?php endif; ?>
            <?php echo wp_kses_post($owner_html); ?>
        </div>
        <?php
    }
}



if (!function_exists('cosmdl_render_copy_script')) {
	function cosmdl_render_copy_script() {
		return;
	}
}

// ==========================================
// 第四部分：主入口函数
// ==========================================

/**
 * 获取下载页面完整 HTML 内容
 * 
 * @param int $post_id 文章ID
 * @return string HTML内容
 */
function cosmdl_get_download_content($post_id) {
    $id = intval($post_id);
    if ($id <= 0) { return '<p class="error">无效的文章ID</p>'; }

    // 文章存在性校验
    $post = get_post($id);
    if (!$post) { return '<p class="error">文章不存在</p>'; }

    // 获取当前附件索引 (默认为 1)
    $attach = absint(get_query_var('attach'));
    if ($attach < 1) { $attach = 1; }
    if ($attach > 6) { $attach = 6; }

    // 获取全局设置
    $opts = cosmdl_get_options();
    
    // 开启输出缓冲
    ob_start();
    ?>
    <div id="cosmdl-download-wrap" class="cosmdl-download-wrap">
        <?php 
        // 根据后台"模块排序"依次渲染模块
        $order = isset($opts['download_modules_order']) && is_array($opts['download_modules_order'])
            ? $opts['download_modules_order']
            : array('statement','fileinfo','custom_links','pan_cards','download_tips','owner_statement');
        
        // 中文注释：确保当文件信息开关开启时，fileinfo模块会被包含在渲染列表中
        $show_fileinfo = isset($opts['show_fileinfo']) ? $opts['show_fileinfo'] : 'yes';
        if ($show_fileinfo === 'yes' && !in_array('fileinfo', $order)) {
            // 将fileinfo模块添加到排序数组的第二个位置（默认位置）
            array_splice($order, 1, 0, 'fileinfo');
        }
        
        // 中文注释：确保当下载说明开关开启时，download_tips模块会被包含在渲染列表中
        $show_download_tips = isset($opts['show_download_tips']) ? $opts['show_download_tips'] : 'yes';
        if ($show_download_tips === 'yes' && !in_array('download_tips', $order)) {
            // 将download_tips模块添加到排序数组的第5个位置（默认位置）
            array_splice($order, 4, 0, 'download_tips');
        }
        
        foreach($order as $mod){
            switch($mod){
                case 'statement':
                    cosmdl_render_download_statement($opts);
                    break;
                case 'fileinfo':
                    cosmdl_render_file_info_card($id, $attach, $opts);
                    break;
                case 'custom_links':
                    cosmdl_render_custom_links($opts);
                    break;
                case 'pan_cards':
                    cosmdl_render_drive_cards($id, $attach, $opts);
                    break;
                case 'download_tips':
                    $show_download_tips = isset($opts['show_download_tips']) ? $opts['show_download_tips'] : 'yes';
                    if ($show_download_tips === 'yes') {
                        cosmdl_render_download_tips($opts);
                    }
                    break;
                case 'owner_statement':
                    cosmdl_render_owner_statement($opts);
                    break;
            }
        }
        ?>
    </div>
    <?php
    $content = ob_get_clean();
    
    // 移除标签之间的空白字符，防止 wpautop 自动生成空 p 标签影响布局
    $content = preg_replace('/>\s+</', '><', $content);
    
    return $content;
}
