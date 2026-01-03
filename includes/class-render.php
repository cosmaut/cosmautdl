<?php
/**
 * 前端渲染：在文章页自动插入下载卡片，并提供短代码调用
 * 说明：当文章勾选“启用下载”或存在任一下载链接时，自动在正文末尾追加卡片；也可通过 [cosmdl_download_card] 手动插入。
 */

if (!defined('ABSPATH')) { exit; }

/**
 * 渲染主类：CosMDL_Render
 * 职责：
 * - 自动在文章页末尾注入下载卡片（the_content 过滤器）；
 * - 提供短代码 [cosmdl_download_card]；
 * 数据来源：
 * - 文章元数据：cosmdl_* 元键（name/size/date/author/official_site/softtype 等）；
 * - 插件设置：cosmdl_options（drive_management、卡片外观设置等）；
 * - 路由构建：cosmdl_route_url('download', $post_id) 生成独立下载页地址；
 * 输出：
 * - 仅包含静态 HTML + 轻量内联样式；具体视觉由 assets/card.css 控制。
 */
class CosMDL_Render {
    /** 构造：注册内容过滤器与短代码 */
    public function __construct(){
        add_filter('the_content', array($this, 'inject_card'), 20);
        add_shortcode('cosmdl_download_card', array($this, 'shortcode'));
    }

    /** 内容过滤器：在文章页追加下载卡片 */
    public function inject_card($content){
        if (!is_singular('post')) return $content;
        if (!in_the_loop() || !is_main_query()) return $content;

        global $post; $post_id = $post->ID;
        $enabled = ($this->get_meta($post_id, 'cosmdl_start') === 'yes');

        // 若未启用且无任何链接，直接返回
        $drive_management = function_exists('cosmdl_get_drive_management_settings') 
            ? cosmdl_get_drive_management_settings() 
            : array();
            
        if (!$enabled && !cosmdl_post_has_any_link($post_id, $drive_management)) return $content;

        $card = $this->build_card($post_id);
        if (!$card) return $content;

        return $content . $card;
    }

    /** 短代码：手动插入下载卡片 */
    public function shortcode($atts){
        $atts = shortcode_atts(array('id'=>0), $atts, 'cosmdl_download_card');
        $post_id = intval($atts['id']);
        if ($post_id <= 0) {
            global $post; $post_id = $post ? $post->ID : 0;
        }
        if ($post_id <= 0) return '';
        return $this->build_card($post_id);
    }



    /** 构建卡片HTML（文章页简版，cosmdl 风格：信息 + CTA 跳转到独立下载页） */
    private function build_card($post_id){
        // 收集信息（仅使用 cosmdl_ 前缀）
        $meta = array(
            'name'   => $this->get_meta($post_id, 'cosmdl_name'),
            'size'   => $this->get_meta($post_id, 'cosmdl_size'),
            'date'   => $this->get_meta($post_id, 'cosmdl_date'),
            'author' => $this->get_meta($post_id, 'cosmdl_author'),
            'site'   => $this->get_meta($post_id, 'cosmdl_official_site'),
            'softtype' => $this->get_meta($post_id, 'cosmdl_softtype'),
        );

        // 加载 cosmdl 卡片样式，使文章页与独立下载页统一视觉
        wp_enqueue_style('cosmdl-style');

        // 统一外观：根据后台外观设置输出动态样式（主题色/圆角/阴影）
        $opts = get_option('cosmdl_options', array());
        // 中文注释：优先使用“下载页设置>文件信息”的外观设置，未设置时回退到通用外观设置
        $card_theme  = isset($opts['file_info_card_theme']) ? $opts['file_info_card_theme'] : (isset($opts['card_theme']) ? $opts['card_theme'] : 'green');
        $card_radius = isset($opts['file_info_card_border_radius']) ? $opts['file_info_card_border_radius'] : (isset($opts['card_border_radius']) ? $opts['card_border_radius'] : 'medium');
        $card_shadow = isset($opts['file_info_card_shadow']) ? $opts['file_info_card_shadow'] : (isset($opts['card_shadow']) ? $opts['card_shadow'] : 'yes');

		$theme_map = array(
			'green'  => array('base'=>'#22c55e','deep'=>'#16a34a','darker'=>'#15803d','rgb'=>'34,197,94'),
			'blue'   => array('base'=>'#3f83f8','deep'=>'#2563eb','darker'=>'#1d4ed8','rgb'=>'63,131,248'),
			'red'    => array('base'=>'#ef4444','deep'=>'#dc2626','darker'=>'#b91c1c','rgb'=>'239,68,68'),
			'purple' => array('base'=>'#a855f7','deep'=>'#7c3aed','darker'=>'#6d28d9','rgb'=>'124,58,237'),
			'orange' => array('base'=>'#f59e0b','deep'=>'#d97706','darker'=>'#b45309','rgb'=>'245,158,11'),
			'pink'   => array('base'=>'#ec4899','deep'=>'#db2777','darker'=>'#be185d','rgb'=>'236,72,153'),
			'gray'   => array('base'=>'#6b7280','deep'=>'#4b5563','darker'=>'#374151','rgb'=>'107,114,128'),
			'indigo' => array('base'=>'#6366f1','deep'=>'#4f46e5','darker'=>'#4338ca','rgb'=>'99,102,241'),
			'teal'   => array('base'=>'#14b8a6','deep'=>'#0d9488','darker'=>'#0f766e','rgb'=>'20,184,166'),
		);
        $t = isset($theme_map[$card_theme]) ? $theme_map[$card_theme] : $theme_map['green'];
        // 中文注释：统一圆角映射（none 0px / small 4px / medium 8px / large 16px）
        switch($card_radius){ case 'none': $radius_px='0px'; break; case 'small': $radius_px='4px'; break; case 'medium': $radius_px='8px'; break; case 'large': $radius_px='16px'; break; default: $radius_px='8px'; }
        $shadow_css = ($card_shadow === 'yes') ? '0 2px 16px rgba(0,0,0,0.06)' : 'none';

        // 多附件：收集可用附件及其名称（修复元键命名：附件2-6使用 cosmdl{idx}_ 前缀）
        $attachments = array();
        for ($i=1; $i<=6; $i++){
            $name_key = ($i===1) ? 'cosmdl_name' : ('cosmdl'.$i.'_name');
            $name = $this->get_meta($post_id, $name_key);
            // 检查是否有任一链接
            $has = false;
            $default_keys = array('downurl1','downurl4','downurl5','downurl6','downurl7','downurl2','downurl3');
            foreach($default_keys as $d){
                // 附件1：cosmdl_downurlX；附件2-6：cosmdl{idx}_downurlX
                $k = ($i===1) ? ('cosmdl_' . $d) : ('cosmdl'.$i.'_' . $d);
                if ($this->get_meta($post_id, $k)) { $has = true; break; }
            }
            if (!$has){
                $options = get_option('cosmdl_options', array());
                $drive_management = isset($options['drive_management']) && is_array($options['drive_management']) ? $options['drive_management'] : array();
                foreach($drive_management as $key => $drive){
                    if (isset($drive['enabled']) && $drive['enabled']==='yes') {
                        // 同时检查标准网盘和自定义网盘
                        if (isset($drive['is_custom']) && $drive['is_custom']==='yes'){
                            $k = ($i===1) ? ('cosmdl_downurl_custom_' . $key) : ('cosmdl'.$i.'_downurl_custom_' . $key);
                            if ($this->get_meta($post_id, $k)) { $has = true; break; }
                            $alias_slug = '';
                            if (isset($drive['alias']) && $drive['alias'] !== '') { $alias_slug = preg_replace('/[^a-z0-9\-]/','', strtolower($drive['alias'])); }
                            if ($alias_slug !== ''){
                                $k2 = ($i===1) ? ('cosmdl_downurl_custom_' . $alias_slug) : ('cosmdl'.$i.'_downurl_custom_' . $alias_slug);
                                if ($this->get_meta($post_id, $k2)) { $has = true; break; }
                            }
                        } else {
                            // 检查标准网盘：cosmdl_downurl_{ID} 或 cosmdl{idx}_downurl_{ID}
                            $k = ($i===1) ? ('cosmdl_downurl_' . $key) : ('cosmdl'.$i.'_downurl_' . $key);
                            if ($this->get_meta($post_id, $k)) { $has = true; break; }
                        }
                    }
                }
            }
            if ($has){
                $attachments[] = array('index'=>$i, 'name'=>$name);
            }
        }

        $show_size = (empty($attachments) || count($attachments) === 1);
        $size_text = '';
        if ($show_size) {
            $size_attach_index = (!empty($attachments) && count($attachments) === 1) ? intval($attachments[0]['index']) : 1;
            $size_prefix = ($size_attach_index === 1) ? 'cosmdl_' : ('cosmdl' . $size_attach_index . '_');
            $size_raw = $this->get_meta($post_id, $size_prefix . 'size');
            $size_unit_raw = $this->get_meta($post_id, $size_prefix . 'size_unit');
            if ($size_raw !== '') {
                $size_raw_trim = trim($size_raw);
                if (preg_match('/[a-zA-Z]/', $size_raw_trim)) {
                    $size_text = $size_raw_trim;
                } else {
                    $u = strtoupper(trim($size_unit_raw));
                    $size_text = ($u !== '') ? ($size_raw_trim . ' ' . $u) : $size_raw_trim;
                }
            }
        }

        // 文章页简版卡片（cosmdl 风格）：左侧元信息
        ob_start();
        $style_vars = '--cosmdl-theme-rgb: '.esc_html($t['rgb']).'; --cosmdl-card-radius: '.esc_html($radius_px).'; --cosmdl-card-shadow: '.esc_html($shadow_css).'; --cosmdl-primary: '.esc_html($t['base']).'; --cosmdl-primary-600: '.esc_html($t['deep']).'; --cosmdl-primary-700: '.esc_html($t['darker']).';';
        echo '<div class="cosmdl-card" aria-label="相关文件下载地址" style="'.esc_attr($style_vars).'">';
        echo '  <div class="cosmdl-card-header">';
        echo '    <span class="cosmdl-card-icon">⬇</span>';
        echo '    <span class="cosmdl-card-title">相关文件下载地址</span>';
        echo '  </div>';
        echo '  <div class="cosmdl-card-body">';
        echo '    <div class="cosmdl-meta" style="grid-column: 1 / -1;">';
        if($meta['site'])   echo '<p>官方网站：<a class="cosmdl-link" target="_blank" href="'.esc_url($meta['site']).'">访问</a></p>';
        if($meta['softtype']) echo '<p>软件性质：<span>'.esc_html($meta['softtype']).'</span></p>';
        // 日期展示逻辑：优先显示“更新日期”（若已填写），否则显示“发布日期”
        $upload_date = get_the_date('Y-m-d', $post_id);
        if (!empty($meta['date'])){
            echo '<p>更新日期：<span>'.esc_html($meta['date']).'</span></p>';
        } else {
            echo '<p>发布日期：<span>'.esc_html($upload_date).'</span></p>';
        }
        if ($size_text !== '') {
            echo '<p>文件大小：<span>' . esc_html($size_text) . '</span></p>';
        }
        // 作者信息始终显示（若存在）
        if($meta['author']) echo '<p>作者信息：<span>'.esc_html($meta['author']).'</span></p>';
        // 多附件链接列表
        echo '<p>下载链接：';
        if (!empty($attachments)){
            echo '<span class="cosmdl-links">';
            $first = true;
            foreach($attachments as $att){
                if (!$first) echo ' <span class="cosmdl-sep">|</span> ';
                $first = false;
                $href = add_query_arg(array('attach'=>$att['index']), cosmdl_route_url('download', $post_id));
                $text = $att['name'] ? $att['name'] : ('附件'.$att['index']);
                echo '<a class="cosmdl-link" href="'.esc_url($href).'" rel="nofollow noopener" target="_blank">'.esc_html($text).'</a>';
            }
            echo '</span>';
        } else {
            $href = cosmdl_route_url('download', $post_id);
            echo '<a class="cosmdl-link" href="'.esc_url($href).'" rel="nofollow noopener" target="_blank">'.($meta['name'] ? esc_html($meta['name']) : '立即下载').'</a>';
        }
        echo '</p>';
        echo '<style>.cosmdl-links{display:inline-flex;flex-wrap:wrap;gap:6px 8px}.cosmdl-sep{color:#999}</style>';
        echo '    </div>';
        
        // 广告位渲染 - 整合到卡片内容内部
        $show_ad_slot = isset($opts['show_ad_slot']) ? $opts['show_ad_slot'] : 'no';
        $ad_html = isset($opts['ad_html']) ? $opts['ad_html'] : '';
        
        if ($show_ad_slot === 'yes' && !empty($ad_html)) {
            echo '    <div class="cosmdl-ad-slot cosmdl-ad-full-width" style="grid-column: 1 / -1; margin-top: 0px; padding-top: 16px; border-top: 1px solid #e5e7eb;">';
            echo '      <div class="cosmdl-ad-container">';
            echo wp_kses_post($ad_html);
            echo '      </div>';
            echo '    </div>';
        }
        
        echo '  </div>';
        echo '</div>';
        return ob_get_clean();
    }

    /** 读取 cosmdl_* 元键 */
    private function get_meta($post_id, $key){
        return get_post_meta($post_id, $key, true);
    }
}
