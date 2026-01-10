<?php
/**
 * 文章编辑页元框：CosMDL_Meta_Box
 * 作用：在文章编辑页提供下载相关的字段输入（链接、提取码、解锁控制等），支持保存与读取。
 */

if (!defined('ABSPATH')) { exit; }

class CosMDL_Meta_Box {
    private $metabox_inline_js = '';
	private $metabox_inline_js_printed = false;
    /**
     * 构造函数
     * 作用：注册文章编辑页的元数据框、保存钩子，以及发布状态转换时的自动填充逻辑。
     * 安全：所有保存均搭配 nonce 校验与权限校验，避免未授权写入。
     */
    public function __construct(){
        // 中文注释：尊重“插件总开关”，若全局未启用则不注册任何元框相关钩子
        $opts = get_option('cosmdl_options', array());
        if (!is_array($opts) || !isset($opts['plugin_active']) || $opts['plugin_active'] !== 'yes') {
            return;
        }
        add_action('add_meta_boxes', array($this, 'register_metabox'), 10, 2);
        add_action('save_post', array($this, 'save_meta'));
        // 中文注释：增加发布状态转换钩子，确保在文章从草稿/待审转为“已发布”时，自动填充空的更新日期
        // 使用 3 个参数版本以获取新旧状态与 Post 对象
        add_action('transition_post_status', array($this, 'autofill_update_date_on_publish'), 10, 3);

        add_action('wp_ajax_cosmdl_enable_drive', array($this, 'ajax_enable_drive'));

		add_action('admin_enqueue_scripts', array($this, 'enqueue_metabox_assets'));
    }

	public function enqueue_metabox_assets($hook_suffix){
		if (!in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || !isset($screen->post_type) || !in_array($screen->post_type, array('post', 'page'), true)) {
			return;
		}

		$filter_name = 'postbox_classes_' . $screen->id . '_cosmdl-post-meta-boxes';
		if ( ! has_filter( $filter_name, array( $this, 'filter_postbox_classes' ) ) ) {
			add_filter( $filter_name, array( $this, 'filter_postbox_classes' ) );
		}

		wp_enqueue_style(
			'cosmdl-style',
			COSMDL_PLUGIN_URL . 'assets/cosmautdl.css',
			array(),
			function_exists('cosmdl_asset_version') ? cosmdl_asset_version('assets/cosmautdl.css') : COSMDL_VERSION
		);
		wp_enqueue_script('jquery');

		if (!has_action('admin_print_footer_scripts', array($this, 'print_metabox_inline_js'))) {
			add_action('admin_print_footer_scripts', array($this, 'print_metabox_inline_js'), 99);
		}
	}

	public function filter_postbox_classes( $classes ) {
		global $post;
		if ( ! $post || ! is_object( $post ) ) {
			return $classes;
		}

		$start_enabled = $this->get_meta( (int) $post->ID, 'cosmdl_start' ) === 'yes';
		$is_new_post   = ( $post->post_status === 'auto-draft' || empty( $post->ID ) );
		$should_close  = $is_new_post ? true : ! $start_enabled;

		if ( $should_close ) {
			if ( ! in_array( 'closed', $classes, true ) ) {
				$classes[] = 'closed';
			}
		} else {
			$classes = array_values( array_diff( $classes, array( 'closed' ) ) );
		}

		return $classes;
	}

	public function print_metabox_inline_js() {
		if ( $this->metabox_inline_js_printed ) {
			return;
		}

		$js = trim( (string) $this->metabox_inline_js );
		if ( $js === '' ) {
			return;
		}

		$this->metabox_inline_js_printed = true;

		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'cosmdl-metabox-inline-js' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<script id='cosmdl-metabox-inline-js'>\n" . $js . "\n</script>\n";
	}

    public function ajax_enable_drive(){
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足，无法启用网盘。请使用管理员账号操作。', 'cosmautdl')));
        }

        $post_data = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        $post_data = is_array($post_data) ? wp_unslash($post_data) : array();

        $nonce = isset($post_data['nonce']) ? sanitize_text_field((string) $post_data['nonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'cosmdl_enable_drive')) {
            wp_send_json_error(array('message' => __('安全校验失败，请刷新页面后重试。', 'cosmautdl')));
        }

        $drive_key = isset($post_data['drive_key']) ? sanitize_key((string) $post_data['drive_key']) : '';
        if ($drive_key === '') {
            wp_send_json_error(array('message' => __('缺少网盘标识。', 'cosmautdl')));
        }

        $known = array(
            'baidu'       => __('百度网盘', 'cosmautdl'),
            '123'         => __('123云盘', 'cosmautdl'),
            'ali'         => __('阿里云盘', 'cosmautdl'),
            '189'         => __('天翼云盘', 'cosmautdl'),
            'quark'       => __('夸克网盘', 'cosmautdl'),
            'pikpak'      => __('PikPak', 'cosmautdl'),
            'lanzou'      => __('蓝奏云网盘', 'cosmautdl'),
            'xunlei'      => __('迅雷云盘', 'cosmautdl'),
            'weiyun'      => __('微云', 'cosmautdl'),
            'onedrive'    => __('OneDrive', 'cosmautdl'),
            'googledrive' => __('GoogleDrive', 'cosmautdl'),
            'dropbox'     => __('Dropbox', 'cosmautdl'),
            'mega'        => __('MEGA', 'cosmautdl'),
            'mediafire'   => __('MediaFire', 'cosmautdl'),
            'box'         => __('Box', 'cosmautdl'),
            'other'       => __('其他网盘', 'cosmautdl'),
        );

        if (!isset($known[$drive_key])) {
            wp_send_json_error(array('message' => __('该网盘类型暂不支持自动启用。', 'cosmautdl')));
        }

        $options = get_option('cosmdl_options', array());
        if (!is_array($options)) { $options = array(); }
        $drives = isset($options['drive_management']) ? $options['drive_management'] : array();
        if (!is_array($drives)) { $drives = array(); }

        if (isset($drives[$drive_key]) && is_array($drives[$drive_key])) {
            $drives[$drive_key]['enabled'] = 'yes';
            if (!isset($drives[$drive_key]['label']) || $drives[$drive_key]['label'] === '') {
                $drives[$drive_key]['label'] = $known[$drive_key];
            }
        } else {
            $max_order = 0;
            foreach ($drives as $one) {
                if (is_array($one) && isset($one['order'])) {
                    $max_order = max($max_order, (int) $one['order']);
                }
            }
            $drives[$drive_key] = array(
                'enabled'   => 'yes',
                'label'     => $known[$drive_key],
                'order'     => $max_order + 1,
                'is_custom' => 'no',
            );
        }

        $options['drive_management'] = $drives;
        update_option('cosmdl_options', $options, false);

        wp_send_json_success(array(
            'drive_key' => $drive_key,
            'label'     => isset($drives[$drive_key]['label']) ? (string) $drives[$drive_key]['label'] : (string) $known[$drive_key],
        ));
    }

    /**
     * 注册元框
     * 在文章编辑页添加「添加附件」元框，承载插件的下载相关字段。
     * 权限：仅具备编辑文章权限的用户可见。
     */
    public function register_metabox( $post_type, $post ){
        if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
            return;
        }

        $cap = ( $post_type === 'page' ) ? 'edit_pages' : 'edit_posts';
        if ( current_user_can( $cap ) ) {
            add_meta_box('cosmdl-post-meta-boxes', __('添加附件','cosmautdl'), array($this, 'render_metabox'), $post_type, 'normal', 'high');
        }
    }

    /**
     * 字段定义：返回结构数组
     * 说明：仅包含通用字段；网盘字段由 get_drive_management_settings() 动态生成。
     */
    private function fields(){
        return array(
            array('name'=>'cosmdl_start','title'=>__('启用下载','cosmautdl'),'type'=>'checkbox'),
            array('name'=>'cosmdl_official_site','title'=>__('官方网站','cosmautdl'),'type'=>'text'),
            array('name'=>'cosmdl_softtype','title'=>__('软件性质','cosmautdl'),'type'=>'select','options'=>array(__('免费','cosmautdl')=>__('免费','cosmautdl'),__('开源','cosmautdl')=>__('开源','cosmautdl'),__('试用','cosmautdl')=>__('试用','cosmautdl'),__('商业','cosmautdl')=>__('商业','cosmautdl'))),
            array('name'=>'cosmdl_name','title'=>__('资源名称','cosmautdl'),'type'=>'text'),
            array('name'=>'cosmdl_size','title'=>__('资源大小','cosmautdl'),'type'=>'text'),
            array('name'=>'cosmdl_date','title'=>__('更新日期','cosmautdl'),'type'=>'text'),
            array('name'=>'cosmdl_author','title'=>__('作者信息','cosmautdl'),'type'=>'text'),
        );
    }

    /**
     * 渲染元框
     * 输出：字段表单、交互脚本与样式（包含附件分组标签、开关、二维码自检与本地存储同步等）。
     * 无参数：依赖全局 $post 获取当前文章。
     */
    public function render_metabox(){
        global $post;
        $fields = $this->fields();
        // 检查是否启用下载来决定默认展开状态
        $start_enabled = $this->get_meta($post->ID, 'cosmdl_start') === 'yes';
        $is_new_post = ($post->post_status === 'auto-draft' || empty($post->ID));
        $should_collapse = $is_new_post ? true : !$start_enabled;

		$global_options = get_option('cosmdl_options', array());
		$show_qr_block = (is_array($global_options) && isset($global_options['show_qr_block'])) ? (string) $global_options['show_qr_block'] : 'no';
		$drive_management = $this->get_drive_management_settings();
		ob_start();
		?>
        // 本地存储：在未保存文章时，刷新页面也能保留已填入的附件信息
        (function(){
            var box = null;
            function init(){
                box = document.getElementById('cosmdl-metabox');
                if(!box){ return false; }
                if (box.getAttribute('data-cosmdl-init') === '1') { return true; }
                box.setAttribute('data-cosmdl-init', '1');

                var postIdEl = document.getElementById('post_ID');
                var postId = postIdEl ? parseInt(postIdEl.value, 10) : 0;
                var postStatusEl = document.getElementById('original_post_status');
                window.cosmdlIsAutoDraft = postStatusEl ? (postStatusEl.value === 'auto-draft') : false;

                // 将启用下载开关移动到元框标题左侧
                try{
                    var wrap = document.getElementById('cosmdl-start-toggle-wrap');
                    var row = document.getElementById('cosmdl-start-row');
                    var postbox = document.getElementById('cosmdl-post-meta-boxes');
                    var hndle = null;
                    if (postbox) {
                        hndle = postbox.querySelector('.hndle') || postbox.querySelector('.postbox-header') || postbox.querySelector('h2');
                    }
                    if (wrap && hndle) {
                        hndle.insertBefore(wrap, hndle.firstChild);
                        if (row && row.parentNode) {
                            row.parentNode.removeChild(row);
                        }
                    } else if (row) {
                        row.classList.remove('cosmdl-hidden');
                    }
                }catch(e){}

                // 键策略：auto-draft 使用统一草稿键；正式ID使用按ID键
                var key = (window.cosmdlIsAutoDraft ? 'cosmdl_meta_draft' : ('cosmdl_meta_' + postId));
                // 读取并应用存储值
                try{
                    var raw = localStorage.getItem(key);
                    // 若切换为已分配ID但尚无对应存储，尝试从草稿键迁移
                    if(!raw && !window.cosmdlIsAutoDraft && postId > 0){
                        var draftRaw = localStorage.getItem('cosmdl_meta_draft');
                        if(draftRaw){
                            raw = draftRaw;
                            localStorage.setItem(key, draftRaw);
                            localStorage.removeItem('cosmdl_meta_draft');
                        }
                    }
                    var data = raw ? JSON.parse(raw) : {};
                    var inputs = box.querySelectorAll('input[name], select[name], textarea[name]');
                    inputs.forEach(function(el){
                        var name = el.getAttribute('name');
                        if(!name){ return; }
                        if (el.type === 'checkbox') {
                            if (data.hasOwnProperty(name)) {
                                el.checked = (data[name] === 'yes');
                            }
                        } else {
                            if (data.hasOwnProperty(name)) {
                                el.value = data[name];
                            }
                        }
                    });
                    // 根据存储的启用下载状态决定展开/收起，并同步小三角开合状态
                    if (data.hasOwnProperty('cosmdl_start')) {
                        var isOn = (data['cosmdl_start'] === 'yes');
                        var cb = document.getElementById('cosmdl_start');
                        if (cb) cb.checked = isOn;
                        toggleCosMDLFields(isOn);
                        setPostboxOpen(isOn);
                    } else {
                        var cb2 = document.getElementById('cosmdl_start');
                        var isOn2 = cb2 ? !!cb2.checked : false;
                        toggleCosMDLFields(isOn2);
                        setPostboxOpen(isOn2);
                    }
                }catch(e){}
                // 监听变更与输入并写入存储（确保自动化 fill 也能触发保存）
                ['change','input'].forEach(function(ev){
                  box.addEventListener(ev, function(e){
                      var t = e.target;
                      if(!t || !t.name){ return; }
                      try{
                          var raw = localStorage.getItem(key);
                          var data = raw ? JSON.parse(raw) : {};
                          if (t.type === 'checkbox') {
                              data[t.name] = t.checked ? 'yes' : '';
                          } else {
                              data[t.name] = t.value || '';
                          }
                          localStorage.setItem(key, JSON.stringify(data));
                      }catch(err){}
                  }, true);
                });

                var checkbox = document.getElementById('cosmdl_start');
                if (checkbox && checkbox.getAttribute('data-cosmdl-bound') !== '1') {
                    checkbox.setAttribute('data-cosmdl-bound', '1');
                    checkbox.addEventListener('change', function(){
                        handleStartToggle(this);
                    });
                }

                return true;
            }

            function schedule(){
                if (init()) { return; }
                var tries = 0;
                var timer = setInterval(function(){
                    tries++;
                    if (init() || tries > 100) {
                        clearInterval(timer);
                    }
                }, 200);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', schedule);
            } else {
                schedule();
            }
        })();
        // 工具：根据开关状态隐藏/显示扫码控件
        function cosmdlSetHidden(el, hidden){
            if (!el) return;
            el.classList.toggle('cosmdl-hidden', !!hidden);
        }
        function cosmdlIsHidden(el){
            if (!el) return true;
            return el.classList.contains('cosmdl-hidden');
        }
        function cosmdlApplyQrMode(isOn){
            var els = document.querySelectorAll('.drive-row .inline label.inline-text, .drive-row .inline .cosmdl-switch');
            els.forEach(function(el){ cosmdlSetHidden(el, !isOn); });
        }
        function toggleCosMDLFields(forceShow){
            var fields = document.querySelector('.cosmdl-collapsible');
            if (!fields) return;
            if(forceShow === true){
                cosmdlSetHidden(fields, false);
            } else if(forceShow === false){
                cosmdlSetHidden(fields, true);
            } else {
                cosmdlSetHidden(fields, !cosmdlIsHidden(fields));
            }
        }
        // 控制元框本体开合（同步小三角与 closed 类，避免“开关开启但元框仍收起”的问题）
        function setPostboxOpen(isOpen){
            try{
                var postbox = document.getElementById('cosmdl-post-meta-boxes');
                if(!postbox) return;
                if(isOpen){ postbox.classList.remove('closed'); } else { postbox.classList.add('closed'); }
                // 同步保存开合状态（若可用）
                if (window.postboxes && typeof window.postboxes.save_state === 'function') {
                    try {
                        var saveType = (typeof window.typenow !== 'undefined' && window.typenow) ? window.typenow : 'post';
                        window.postboxes.save_state(saveType);
                    } catch(err){}
                }
            }catch(err){}
        }
        
        // 启用下载开关变化时自动展开/收起
        function handleStartToggle(checkbox){
            // 打开开关则展开并打开元框；关闭开关则收起并关闭元框
            toggleCosMDLFields(checkbox.checked);
            setPostboxOpen(checkbox.checked);
            // 同步存储状态，确保刷新后仍保持展开
            try{
                var postIdEl = document.getElementById('post_ID');
                var postId = postIdEl ? parseInt(postIdEl.value, 10) : 0;
                var key = (window.cosmdlIsAutoDraft ? 'cosmdl_meta_draft' : ('cosmdl_meta_' + postId));
                var raw = localStorage.getItem(key);
                var data = raw ? JSON.parse(raw) : {};
                data['cosmdl_start'] = checkbox.checked ? 'yes' : '';
                localStorage.setItem(key, JSON.stringify(data));
            }catch(e){}
        }

		try{
			window.cosmdlSetHidden = cosmdlSetHidden;
			window.cosmdlIsHidden = cosmdlIsHidden;
			window.cosmdlApplyQrMode = cosmdlApplyQrMode;
			window.toggleCosMDLFields = toggleCosMDLFields;
			window.setPostboxOpen = setPostboxOpen;
			window.handleStartToggle = handleStartToggle;
		}catch(e){}

        // 自检：后台全局二维码区块状态，若为关闭则隐藏任何可能出现的扫码控件
        (function(){
            if (typeof ajaxurl === 'undefined') return;
            var fd = new FormData();
            fd.append('action','cosmdl_get_qr_status');
            fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' })
              .then(function(r){ return r.json(); })
              .then(function(j){
                if(j && j.success && j.data){ cosmdlApplyQrMode(j.data.show_qr_block === 'yes'); }
              }).catch(function(){});
        })();

        // 监听设置页的跨标签广播，实现无需刷新即可同步
        window.addEventListener('storage', function(e){
            try{
              if(e && e.key === 'cosmdl_show_qr_block'){
                var val = (e.newValue || '').split('|')[0];
                cosmdlApplyQrMode(val === 'yes');
              }
            }catch(err){}
        });
        // 附件标签页交互（默认仅显示“附件1”，点击“+”逐步添加；已存在数据的附件自动生成标签）
        (function(){
            function tabsWrap(){ return document.querySelector('.cosmdl-tabs'); }
            function addBtn(){ return document.getElementById('cosmdl_tab_add'); }
            function hasTab(idx){ return !!document.querySelector('.cosmdl-tab[data-idx="'+idx+'"]'); }
            function createTab(idx){
                if (hasTab(idx)) return;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cosmdl-tab';
                btn.setAttribute('data-idx', String(idx));
                
                // 获取资源名称字段的值（如果有）
                var nameFieldName;
                if (idx === '1') {
                    nameFieldName = 'cosmdl_name';
                } else {
                    // 避免直接字符串连接，分步构建
                    nameFieldName = 'cosmdl';
                    nameFieldName += idx;
                    nameFieldName += '_name';
                }
                // 使用单独变量存储选择器
                var selector = 'input[name="';
                selector += nameFieldName;
                selector += '"]';
                var nameField = document.querySelector(selector);
                var defaultText = '附件 ';
                defaultText += idx;
                var tabText = defaultText;
                if (nameField && nameField.value.trim()) {
                    tabText = nameField.value.trim();
                }
                
                btn.textContent = tabText;
                // 创建底部覆盖条元素
                var bottomCover = document.createElement('div');
                bottomCover.className = 'tab-bottom-cover';
                btn.appendChild(bottomCover);
                // 创建关闭按钮
                var close = document.createElement('span');
                close.className = 'cosmdl-tab-close';
                close.setAttribute('data-idx', String(idx));
                close.textContent = '×';
                btn.appendChild(close);
                tabsWrap().insertBefore(btn, addBtn());
                
                // 为资源名称字段添加事件监听器，实现实时更新标签文本
                setupNameFieldListener(idx, nameFieldName);
            }
            
            function setupNameFieldListener(idx, fieldName){
                // 分步构建选择器字符串
                var nameSelector = "input[name='";
                nameSelector += fieldName;
                nameSelector += "']";
                var nameField = document.querySelector(nameSelector);
                if (!nameField) return;
                
                nameField.addEventListener('input', function(){
                    // 分步构建标签选择器
                    var tabSelector = ".cosmdl-tab[data-idx='";
                    tabSelector += idx;
                    tabSelector += "']";
                    var tab = document.querySelector(tabSelector);
                    if (tab) {
                        // 分步构建默认文本
                        var defaultTabText = '附件 ';
                        defaultTabText += idx;
                        var newText = defaultTabText;
                        if (this.value.trim()) {
                            newText = this.value.trim();
                        }
                        tab.textContent = newText;
                        // 确保关闭按钮重新添加
                        var closeBtn = tab.querySelector('.cosmdl-tab-close');
                        if (!closeBtn) {
                            closeBtn = document.createElement('span');
                            closeBtn.className = 'cosmdl-tab-close';
                            closeBtn.setAttribute('data-idx', String(idx));
                            closeBtn.textContent = '×';
                            tab.appendChild(closeBtn);
                        }
                    }
                });
            }
            function activate(idx){
                var tabs = document.querySelectorAll('.cosmdl-tab');
                var groups = document.querySelectorAll('.cosmdl-attach-group');
                
                tabs.forEach(function(t){ 
                    t.classList.toggle('active', t.getAttribute('data-idx')==idx); 
                    
                    // 确保激活标签有底部覆盖条
                    if (t.classList.contains('active')) {
                        // 检查是否已有覆盖条，没有则创建
                        var cover = t.querySelector('.tab-bottom-cover');
                        if (!cover) {
                            cover = document.createElement('div');
                            cover.className = 'tab-bottom-cover';
                            t.appendChild(cover);
                        }
                    }
                });
                
                groups.forEach(function(g){
                    g.classList.toggle('cosmdl-hidden', (g.getAttribute('data-idx')!=idx));
                });
            }
            function groupHasData(group){
                // 仅当分组内存在“实际填写的数据”时才认为该分组有数据：
                // - 文本/文本域：非空（忽略隐藏字段、nonce与 _wp_http_referer 等辅助字段）
                // - 复选框：被选中
                // - 下拉选择：与默认选项不同且非空（避免默认值导致误判）
                var inputs = group.querySelectorAll('input[name],select[name],textarea[name]');
                for (var i = 0; i < inputs.length; i++) {
                    var el = inputs[i];
                    var name = (el.getAttribute('name') || '');

                    // 跳过所有隐藏/辅助字段
                    if (el.type === 'hidden') continue; // 忽略隐藏字段（nonce、_wp_http_referer等）
                    if (name === '_wp_http_referer') continue; // 忽略 WP 引用字段
                    if (name.endsWith('_input_name')) continue; // 忽略 nonce 校验字段

                    if (el.tagName === 'SELECT') {
                        // 特例：资源大小单位（KB/MB/GB）不应单独被视为“有数据”。
                        // 只有当对应的“资源大小”文本框填写了数值时，单位选择才参与判定。
                        // 例如：cosmdl_size_unit 或 cosmdl2_size_unit 等。
                        if (/^cosmdl\d*_size_unit$/.test(name) || name === 'cosmdl_size_unit') {
                            var sizeFieldName = name.replace('_size_unit','');
                            // 注意：双引号需要转义以避免 PHP 输出脚本时的语法冲突
                            var sizeInput = group.querySelector('input[name="'+sizeFieldName+'"]');
                            if (!sizeInput || (sizeInput.value || '').trim() === '') {
                                // 未填写“资源大小”，忽略单位选择
                                continue;
                            }
                            // 若填写了“资源大小”，文本框本身会触发“有数据”，无需再依赖单位选择
                            // 但为保持函数的通用性，这里仍保留对选择值的判定（不影响最终结果）
                        }
                        // 若当前值为空字符串，视为“未填写”；
                        // 若当前值与第一个非禁用 option 的值相同，视为“未填写”；
                        // 仅当当前值非空且与默认不同，判定为“有数据”。
                        var firstOpt = el.querySelector('option:not([disabled])');
                        var defVal = firstOpt ? (firstOpt.value || '') : '';
                        var curVal = (el.value || '');
                        if (curVal !== '' && curVal !== defVal) return true;
                        continue;
                    }
                    if (el.type === 'checkbox') {
                        if (el.checked) return true;
                        continue;
                    }
                    // 文本/文本域
                    if ((el.value || '').trim() !== '') return true;
                }
                return false;
            }
            function ensureTabsForExisting(){
                var groups = document.querySelectorAll('.cosmdl-attach-group');
                for (var i=0;i<groups.length;i++){
                    var idx = groups[i].getAttribute('data-idx');
                    if (idx==='1') continue;
                    if (groupHasData(groups[i])){ createTab(idx); }
                }
            }
            function updateAddVisibility(){
                var btn = addBtn();
                if (!btn) return;
                var groups = document.querySelectorAll('.cosmdl-attach-group');
                var canAdd = false;
                for (var i=0;i<groups.length;i++){
                    var g = groups[i];
                    var idx = g.getAttribute('data-idx');
                    if (idx==='1') continue;
                    if (cosmdlIsHidden(g) && !hasTab(idx)){
                        canAdd = true;
                        break;
                    }
                }
                cosmdlSetHidden(btn, !canAdd);
            }
            document.addEventListener('DOMContentLoaded', function(){
                // 初始化：仅"附件1"有可见标签，其他根据数据生成标签但不默认展开
                ensureTabsForExisting();
                activate('1');
                updateAddVisibility();
                
                // 初始化附件1的标签文本和事件监听器
                initTabName('1', 'cosmdl_name');
                
                // 为所有已生成的标签页（附件2-6）初始化名称和监听器
                for (var i = 2; i <= 6; i++) {
                    var idx = String(i);
                    if (hasTab(idx)) {
                        var fieldName = 'cosmdl' + idx + '_name';
                        initTabName(idx, fieldName);
                    }
                }
            });
            
            function initTabName(idx, fieldName){
                // 更新标签文本
                // 分步构建选择器字符串
                var nameSelector = "input[name='";
                nameSelector += fieldName;
                nameSelector += "']";
                var nameField = document.querySelector(nameSelector);
                
                // 分步构建标签选择器
                var tabSelector = ".cosmdl-tab[data-idx='";
                tabSelector += idx;
                tabSelector += "']";
                var tab = document.querySelector(tabSelector);
                
                if (nameField && tab) {
                    // 分步构建默认文本
                    var defaultTabText = '附件 ';
                    defaultTabText += idx;
                    var tabText = defaultTabText;
                    if (nameField.value.trim()) {
                        tabText = nameField.value.trim();
                    }
                    tab.textContent = tabText;
                    
                    // 确保关闭按钮重新添加
                    var closeBtn = tab.querySelector('.cosmdl-tab-close');
                    if (!closeBtn) {
                        closeBtn = document.createElement('span');
                        closeBtn.className = 'cosmdl-tab-close';
                        closeBtn.setAttribute('data-idx', String(idx));
                        closeBtn.textContent = '×';
                        tab.appendChild(closeBtn);
                    }
                }
                
                // 设置监听器
                setupNameFieldListener(idx, fieldName);
            }
            document.addEventListener('click', function(e){
                var t = e.target;
                if (t.classList.contains('cosmdl-tab')){
                    activate(t.getAttribute('data-idx'));
                } else if (t.classList.contains('cosmdl-tab-add')){
                    // 新逻辑：优先填充最小的缺号（包含附件1），并保持标签按编号升序
                    try{
                        var groupsAll = document.querySelectorAll('.cosmdl-attach-group');
                        for (var ii=0; ii<groupsAll.length; ii++){
                                var gid = groupsAll[ii].getAttribute('data-idx');
                                if (!hasTab(gid)){
                                    createTab(gid);
                                cosmdlSetHidden(groupsAll[ii], false);
                                // 重新排序：按 1..6 的顺序将现有标签依次插入到 + 按钮之前
                                var wrap = tabsWrap(); var add = addBtn();
                                for (var k=1;k<=6;k++){
                                    // 注意：双引号需要转义以避免 PHP 输出脚本时的语法冲突
                                    var tk = wrap.querySelector('.cosmdl-tab[data-idx="'+k+'"]');
                                    if (tk){ wrap.insertBefore(tk, add); }
                                }
                                activate(gid);
                                // 新的可见性计算：只要存在未创建的标签即可继续显示“+”
                                var canAddNew = false;
                                for (var jj=0;jj<groupsAll.length;jj++){ if (!hasTab(groupsAll[jj].getAttribute('data-idx'))){ canAddNew = true; break; } }
                                if (add){ cosmdlSetHidden(add, !canAddNew); }
                                return; // 阻止后续旧逻辑执行
                            }
                        }
                    }catch(err){}
                    // 寻找第一个隐藏且尚未有标签的分组
                    var groups = document.querySelectorAll('.cosmdl-attach-group');
                    for (var i=0;i<groups.length;i++){
                        var g = groups[i];
                        var idx = g.getAttribute('data-idx');
                        if (idx==='1') continue;
                        if (cosmdlIsHidden(g) && !hasTab(idx)){
                            createTab(idx);
                            cosmdlSetHidden(g, false);
                            activate(idx);
                            updateAddVisibility();
                            break;
                        }
                    }
                } else if (t.classList.contains('cosmdl-tab-close')){
                    e.stopPropagation();
                    var idx = t.getAttribute('data-idx');
                    // 判断附件分组是否已有数据（用于在关闭当前标签后，自动激活其他有数据的附件）
                    var hasGroupData = function(g){
                        var filled = false;
                        var inputs = g.querySelectorAll('input[name],select[name],textarea[name]');
                        inputs.forEach(function(el){
                            if (filled) return;
                            if (el.type === 'checkbox'){
                                if (el.checked) filled = true;
                            } else if (el.tagName === 'SELECT'){
                                if (el.value && el.value !== '') filled = true;
                            } else {
                                if (el.value && el.value.trim() !== '') filled = true;
                            }
                        });
                        return filled;
                    };
                    if (idx==='1'){
                        var g1 = document.querySelector('.cosmdl-attach-group[data-idx="1"]');
                        if (g1){
                            var inputs1 = g1.querySelectorAll('input[name],select[name],textarea[name]');
                            inputs1.forEach(function(el){ 
                                if(el.type==='checkbox'){ el.checked=false; } 
                                else if (el.tagName==='SELECT'){ 
                                    var firstOpt = el.querySelector('option:not([disabled])');
                                    el.value = firstOpt ? (firstOpt.value||'') : ''; 
                                } else { el.value=''; } 
                                try{
                                    el.dispatchEvent(new Event('change', { bubbles: true }));
                                    el.dispatchEvent(new Event('input', { bubbles: true }));
                                }catch(err){}
                            });
                        }
                        // 移除标签按钮（附件1）并决定下一个激活项
                        var tab1 = document.querySelector('.cosmdl-tab[data-idx="1"]');
                        if (tab1 && tab1.parentNode){ tab1.parentNode.removeChild(tab1); }
                        var target = (function(cur){
                            var curNum = parseInt(cur,10);
                            for (var i = curNum - 1; i >= 1; i--) { if (hasTab(String(i))) return String(i); }
                            for (var j = curNum + 1; j <= 6; j++) { if (hasTab(String(j))) return String(j); }
                            return null;
                        })(idx);
                        if (target){
                            activate(target);
                            updateAddVisibility();
                        } else {
                            // 尝试寻找其他有数据的附件并激活（优先更小索引）
                            var selectWithData = null;
                            (function(){
                                var curNum = parseInt(idx,10);
                                for (var i = curNum - 1; i >= 1; i--){
                                    var gi = document.querySelector('.cosmdl-attach-group[data-idx="'+i+'"]');
                                    if (gi && hasGroupData(gi)){ selectWithData = String(i); break; }
                                }
                                if (!selectWithData){
                                    for (var j = curNum + 1; j <= 6; j++){
                                        var gj = document.querySelector('.cosmdl-attach-group[data-idx="'+j+'"]');
                                        if (gj && hasGroupData(gj)){ selectWithData = String(j); break; }
                                    }
                                }
                            })();
                            if (selectWithData){
                                if (!hasTab(selectWithData)) createTab(selectWithData);
                                var gsel = document.querySelector('.cosmdl-attach-group[data-idx="'+selectWithData+'"]');
                                if (gsel){ cosmdlSetHidden(gsel, false); }
                                activate(selectWithData);
                                updateAddVisibility();
                            } else {
                                var col = document.querySelector('.cosmdl-collapsible');
                                if (col) cosmdlSetHidden(col, true);
                                var tog = document.getElementById('cosmdl_start');
                                if (tog){
                                    tog.checked = false;
                                    try{ if (typeof handleStartToggle === 'function') handleStartToggle(tog); }catch(err){}
                                }
                            }
                        }
                        return;
                    }
                    var group = document.querySelector('.cosmdl-attach-group[data-idx="'+idx+'"]');
                    if (group){
                        var inputs = group.querySelectorAll('input[name],select[name],textarea[name]');
                        inputs.forEach(function(el){
                            if(el.type==='checkbox'){
                                el.checked=false;
                            } else if (el.tagName==='SELECT'){
                                var firstOpt = el.querySelector('option:not([disabled])');
                                el.value = firstOpt ? (firstOpt.value||'') : '';
                            } else {
                                el.value='';
                            }
                            try{
                                el.dispatchEvent(new Event('change', { bubbles: true }));
                                el.dispatchEvent(new Event('input', { bubbles: true }));
                            }catch(err){}
                        });
                        cosmdlSetHidden(group, true);
                        // 移除标签按钮
                        var tab = document.querySelector('.cosmdl-tab[data-idx="'+idx+'"]');
                        if (tab && tab.parentNode){ tab.parentNode.removeChild(tab); }
                        // 关闭后激活最近的其他标签；若无则收起并关闭启用
                        var target2 = (function(cur){
                            var curNum = parseInt(cur,10);
                            for (var i = curNum - 1; i >= 1; i--) { if (hasTab(String(i))) return String(i); }
                            for (var j = curNum + 1; j <= 6; j++) { if (hasTab(String(j))) return String(j); }
                            return null;
                        })(idx);
                        if (target2){
                            activate(target2);
                            updateAddVisibility();
                        } else {
                            // 尝试寻找其他有数据的附件并激活（优先更小索引）
                            var selectWithData2 = null;
                            (function(){
                                var curNum2 = parseInt(idx,10);
                                for (var i2 = curNum2 - 1; i2 >= 1; i2--){
                                    var gi2 = document.querySelector('.cosmdl-attach-group[data-idx="'+i2+'"]');
                                    if (gi2 && hasGroupData(gi2)){ selectWithData2 = String(i2); break; }
                                }
                                if (!selectWithData2){
                                    for (var j2 = curNum2 + 1; j2 <= 6; j2++){
                                        var gj2 = document.querySelector('.cosmdl-attach-group[data-idx="'+j2+'"]');
                                        if (gj2 && hasGroupData(gj2)){ selectWithData2 = String(j2); break; }
                                    }
                                }
                            })();
                            if (selectWithData2){
                                if (!hasTab(selectWithData2)) createTab(selectWithData2);
                                var gsel2 = document.querySelector('.cosmdl-attach-group[data-idx="'+selectWithData2+'"]');
                                if (gsel2){ cosmdlSetHidden(gsel2, false); }
                                activate(selectWithData2);
                                updateAddVisibility();
                            } else {
                                var col2 = document.querySelector('.cosmdl-collapsible');
                                if (col2) cosmdlSetHidden(col2, true);
                                var tog2 = document.getElementById('cosmdl_start');
                                if (tog2){
                                    tog2.checked = false;
                                    try{ if (typeof handleStartToggle === 'function') handleStartToggle(tog2); }catch(err){}
                                }
                            }
                        }
                    }
                }
            });
        })();

        (function(){
            var cosmdlSmartConfig = {
                nonce: '<?php echo esc_js(wp_create_nonce('cosmdl_enable_drive')); ?>',
                canManage: <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>,
                imagesBase: '<?php echo esc_js(COSMDL_PLUGIN_URL . 'images/'); ?>',
                showQr: <?php echo ($show_qr_block === 'yes') ? 'true' : 'false'; ?>,
                driveMap: <?php echo wp_json_encode(is_array($drive_management) ? $drive_management : array()); ?>
            };

            function cosmdlSmartCleanUrl(raw){
                if(!raw){ return ''; }
                var url = String(raw).trim();
                url = url.replace(/^`+|`+$/g, '');
                url = url.replace(/^['\"“”]+|['\"“”]+$/g, '');

                var cutChars = ['（','(', '【','[','《','<'];
                for (var i = 0; i < cutChars.length; i++){
                    var pos = url.indexOf(cutChars[i]);
                    if (pos > 0){
                        url = url.slice(0, pos);
                        break;
                    }
                }

                url = url.replace(/[\s\u3000]+/g, '');
                url = url.replace(/[),，。！!；;、】\]》>]+$/g, '');
                url = url.replace(/#+$/g, '');
                return url;
            }

            function cosmdlSmartExtractUrls(text){
                var input = String(text || '');
                var m = input.match(/https?:\/\/[^\s<>"'`]+/ig);
                if(!m){ return []; }
                var out = [];
                for (var i = 0; i < m.length; i++){
                    var u = cosmdlSmartCleanUrl(m[i]);
                    if(u && out.indexOf(u) === -1){
                        out.push(u);
                    }
                }
                return out;
            }

            function cosmdlSmartExtractCode(text, url){
                var t = String(text || '');
                var code = '';
                if (url){
                    try{
                        var u = new URL(url, window.location.origin);
                        var qp = u.searchParams.get('pwd');
                        if (qp && /^[0-9a-zA-Z]{3,12}$/.test(qp)){
                            code = qp;
                        }
                    }catch(e){}
                }
                if (code){
                    return code;
                }

                var m = t.match(/(提取码|访问码|密码|口令)\s*[:：]?\s*([0-9a-zA-Z]{3,12})/i);
                if (m && m[2]){
                    return String(m[2]).trim();
                }
                return '';
            }

            function cosmdlSmartDetectByUrl(url){
                var u = String(url || '').toLowerCase();
                if (/^https?:\/\/(pan|yun)\.baidu\.com\//.test(u)) return 'baidu';
                if (/^https?:\/\/www\.123865\.com\//.test(u) || /^https?:\/\/www\.123pan\.com\//.test(u)) return '123';
                if (/^https?:\/\/(www\.)?(alipan\.com|aliyundrive\.com)\//.test(u)) return 'ali';
                if (/^https?:\/\/cloud\.189\.cn\//.test(u)) return '189';
                if (/^https?:\/\/pan\.quark\.cn\//.test(u)) return 'quark';
                if (/^https?:\/\/mypikpak\.com\//.test(u) || /^https?:\/\/pikpak\.me\//.test(u)) return 'pikpak';
                if (/^https?:\/\/([a-z0-9-]+\.)?lanzou[a-z0-9]*\.com\//.test(u)) return 'lanzou';
                if (/^https?:\/\/pan\.xunlei\.com\//.test(u)) return 'xunlei';
                if (/^https?:\/\/share\.weiyun\.com\//.test(u) || /^https?:\/\/weiyun\.com\//.test(u)) return 'weiyun';
                if (/^https?:\/\/1drv\.ms\//.test(u) || /^https?:\/\/onedrive\.live\.com\//.test(u)) return 'onedrive';
                if (/^https?:\/\/drive\.google\.com\//.test(u)) return 'googledrive';
                if (/^https?:\/\/([a-z0-9-]+\.)?dropbox\.com\//.test(u)) return 'dropbox';
                if (/^https?:\/\/mega\.nz\//.test(u)) return 'mega';
                if (/^https?:\/\/([a-z0-9-]+\.)?mediafire\.com\//.test(u)) return 'mediafire';
                if (/^https?:\/\/([a-z0-9-]+\.)?box\.com\//.test(u)) return 'box';
                return '';
            }

            function cosmdlSmartPickBestUrl(urls){
                if (!urls || !urls.length){
                    return '';
                }
                var best = urls[0];
                var bestScore = -1;
                for (var i = 0; i < urls.length; i++){
                    var u = urls[i];
                    var score = 0;
                    var k = cosmdlSmartDetectByUrl(u);
                    if (k){ score += 5; }
                    if (/[?&]pwd=/.test(u)){ score += 2; }
                    if (/\/s\//.test(u) || /\/share\b/.test(u)){ score += 1; }
                    if (score > bestScore){
                        best = u;
                        bestScore = score;
                    }
                }
                return best;
            }

            function cosmdlSmartParse(text, driveRows){
                var urls = cosmdlSmartExtractUrls(text);
                var url = cosmdlSmartPickBestUrl(urls);
                var byUrl = url ? cosmdlSmartDetectByUrl(url) : '';
                var byLabel = '';

                if (driveRows && driveRows.length){
                    var t = String(text || '').toLowerCase();
                    for (var i = 0; i < driveRows.length; i++){
                        var row = driveRows[i];
                        var label = String(row.label || '').toLowerCase();
                        if (label && t.indexOf(label) !== -1){
                            byLabel = row.key;
                            break;
                        }
                    }
                }

                var driveKey = byUrl || byLabel || 'other';
                var code = cosmdlSmartExtractCode(text, url);

                return {
                    driveKey: driveKey,
                    url: url,
                    code: code
                };
            }

            function cosmdlSmartSetTip(tipEl, text, type){
                if (!tipEl){ return; }
                tipEl.classList.remove('is-ok');
                tipEl.classList.remove('is-bad');
                if (type === 'ok'){
                    tipEl.classList.add('is-ok');
                } else if (type === 'bad'){
                    tipEl.classList.add('is-bad');
                }
                tipEl.textContent = text;
            }

            function cosmdlSmartFill(groupEl, parsed, driveRows){
                if (!groupEl || !parsed){ return { ok:false, message:'识别失败：缺少参数' }; }
                if (!parsed.url){
                    return { ok:false, message:'未找到分享链接：请粘贴包含 http/https 的完整分享内容' };
                }

                function findInput(key, field){
                    return groupEl.querySelector('input[data-drive-key="' + key + '"][data-field="' + field + '"]');
                }

                var urlInput = findInput(parsed.driveKey, 'url');
                var pwdInput = findInput(parsed.driveKey, 'pwd');
                var usedKey = parsed.driveKey;

                if (!urlInput && parsed.driveKey && parsed.driveKey !== 'other'){
                    return { ok:false, needEnable:true, driveKey: parsed.driveKey, message:'检测到该分享属于未启用的网盘：' + parsed.driveKey };
                }

                if (!urlInput){
                    return { ok:false, message:'该附件分组里没有可回填的网盘地址输入框（可能在“网盘管理”里被禁用）' };
                }

                urlInput.value = parsed.url;
                try{ urlInput.dispatchEvent(new Event('input', { bubbles:true })); }catch(e){}
                try{ urlInput.dispatchEvent(new Event('change', { bubbles:true })); }catch(e){}

                if (pwdInput && parsed.code){
                    pwdInput.value = parsed.code;
                    try{ pwdInput.dispatchEvent(new Event('input', { bubbles:true })); }catch(e){}
                    try{ pwdInput.dispatchEvent(new Event('change', { bubbles:true })); }catch(e){}
                }

                var label = '';
                if (driveRows && driveRows.length){
                    for (var i = 0; i < driveRows.length; i++){
                        if (driveRows[i].key === usedKey){
                            label = driveRows[i].label;
                            break;
                        }
                    }
                }

                var msg = '已识别为“' + (label || usedKey) + '”，已回填链接';
                if (pwdInput){
                    msg += parsed.code ? '与提取码' : '（未找到提取码/访问码，可手动补充）';
                }

                if (usedKey === 'other' && parsed.driveKey !== 'other'){
                    msg += '（该网盘未启用，已回填到“其他网盘”）';
                }

                return { ok:true, message: msg };
            }

            function cosmdlDriveLabel(key){
                if (!key){ return ''; }
                if (cosmdlSmartConfig.driveMap && cosmdlSmartConfig.driveMap[key] && cosmdlSmartConfig.driveMap[key].label){
                    return String(cosmdlSmartConfig.driveMap[key].label);
                }
                var map = {
                    baidu: '百度网盘',
                    '123': '123云盘',
                    ali: '阿里云盘',
                    '189': '天翼云盘',
                    quark: '夸克网盘',
                    pikpak: 'PikPak',
                    lanzou: '蓝奏云网盘',
                    xunlei: '迅雷云盘',
                    weiyun: '微云',
                    onedrive: 'OneDrive',
                    googledrive: 'GoogleDrive',
                    dropbox: 'Dropbox',
                    mega: 'MEGA',
                    mediafire: 'MediaFire',
                    box: 'Box',
                    other: '其他网盘'
                };
                return map[key] || key;
            }

            function cosmdlKeyForIndex(base, idx){
                var i = parseInt(idx, 10) || 1;
                if (i <= 1){
                    return base;
                }
                return base.replace(/^cosmdl_/, 'cosmdl' + i + '_');
            }

            function cosmdlCreateDriveRow(groupEl, driveKey, label){
                var idx = groupEl.getAttribute('data-idx') || '1';
                var baseUrl = 'cosmdl_downurl_' + driveKey;
                var basePwd = 'cosmdl_cipher_' + driveKey;
                var baseUnlock = 'cosmdl_unlock_' + driveKey;

                var urlKey = cosmdlKeyForIndex(baseUrl, idx);
                var pwdKey = cosmdlKeyForIndex(basePwd, idx);
                var unlockKey = cosmdlKeyForIndex(baseUnlock, idx);

                var row = document.createElement('div');
                row.className = 'drive-row';
                row.setAttribute('data-drive-key', driveKey);

                var lab = document.createElement('label');
                lab.setAttribute('for', urlKey);
                lab.className = 'cosmdl-field-label';
                lab.textContent = label || driveKey;
                row.appendChild(lab);

                var urlWrap = document.createElement('div');
                urlWrap.className = 'cosmdl-drive-input-wrap';

                var img = document.createElement('img');
                img.src = cosmdlSmartConfig.imagesBase + driveKey + '.png';
                img.alt = label || driveKey;
                img.className = 'cosmdl-drive-icon';
                urlWrap.appendChild(img);

                var urlInput = document.createElement('input');
                urlInput.className = 'url-input';
                urlInput.type = 'text';
                urlInput.name = urlKey;
                urlInput.id = urlKey;
                urlInput.setAttribute('data-drive-key', driveKey);
                urlInput.setAttribute('data-field', 'url');
                urlWrap.appendChild(urlInput);
                row.appendChild(urlWrap);

                var inline = document.createElement('div');
                inline.className = 'inline';

                var pwdInput = document.createElement('input');
                pwdInput.className = 'short-input';
                pwdInput.type = 'text';
                pwdInput.name = pwdKey;
                pwdInput.id = pwdKey;
                pwdInput.placeholder = '提取码';
                pwdInput.setAttribute('data-drive-key', driveKey);
                pwdInput.setAttribute('data-field', 'pwd');
                inline.appendChild(pwdInput);

                if (cosmdlSmartConfig.showQr){
                    var unlockLabel = document.createElement('label');
                    unlockLabel.className = 'inline-text';
                    unlockLabel.setAttribute('for', unlockKey);
                    unlockLabel.textContent = '扫码解锁';
                    inline.appendChild(unlockLabel);

                    var sw = document.createElement('label');
                    sw.className = 'cosmdl-switch';
                    sw.setAttribute('for', unlockKey);

                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = unlockKey;
                    cb.id = unlockKey;
                    cb.value = 'yes';
                    var slider = document.createElement('span');
                    slider.className = 'cosmdl-slider';
                    sw.appendChild(cb);
                    sw.appendChild(slider);
                    inline.appendChild(sw);
                }

                row.appendChild(inline);

                groupEl.appendChild(row);
                return row;
            }

            function cosmdlEnableDriveAjax(driveKey){
                if (typeof ajaxurl === 'undefined'){
                    return Promise.reject(new Error('ajaxurl 缺失'));
                }
                var fd = new FormData();
                fd.append('action', 'cosmdl_enable_drive');
                fd.append('nonce', cosmdlSmartConfig.nonce);
                fd.append('drive_key', driveKey);
                return fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r){ return r.json(); });
            }

            function cosmdlModal(){
                return document.getElementById('cosmdl-enable-drive-modal');
            }

            function cosmdlOpenModal(){
                var m = cosmdlModal();
                if (!m){ return; }
                m.classList.add('is-visible');
                m.setAttribute('aria-hidden', 'false');
            }

            function cosmdlCloseModal(){
                var m = cosmdlModal();
                if (!m){ return; }
                m.classList.remove('is-visible');
                m.setAttribute('aria-hidden', 'true');
            }

            var cosmdlPendingEnable = null;

            function cosmdlSmartInit(){
                var root = document.getElementById('cosmdl-metabox');
                if (!root){ return; }

                document.addEventListener('keydown', function(e){
                    if (e && e.key === 'Escape'){
                        cosmdlCloseModal();
                    }
                });

                document.addEventListener('click', function(e){
                    var m = cosmdlModal();
                    if (!m || !m.classList.contains('is-visible')){ return; }
                    if (e.target && e.target.classList && e.target.classList.contains('cosmdl-delete-modal')){
                        cosmdlCloseModal();
                    }
                });

                (function(){
                    var cancel = document.getElementById('cosmdl-enable-drive-cancel');
                    var other = document.getElementById('cosmdl-enable-drive-other');
                    var confirm = document.getElementById('cosmdl-enable-drive-confirm');
                    if (cancel){
                        cancel.addEventListener('click', function(){
                            cosmdlCloseModal();
                            cosmdlPendingEnable = null;
                        });
                    }
                    if (other){
                        other.addEventListener('click', function(){
                            if (!cosmdlPendingEnable){
                                cosmdlCloseModal();
                                return;
                            }
                            var fillToOther = function(){
                                var otherParsed = {
                                    driveKey: 'other',
                                    url: cosmdlPendingEnable.parsed.url,
                                    code: cosmdlPendingEnable.parsed.code
                                };
                                var result = cosmdlSmartFill(cosmdlPendingEnable.groupEl, otherParsed, cosmdlPendingEnable.driveRows);
                                if (result.ok){
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, result.message, 'ok');
                                    cosmdlPendingEnable.textarea.setAttribute('data-autoselect', '1');
                                } else {
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, result.message, 'bad');
                                }
                                cosmdlCloseModal();
                                cosmdlPendingEnable = null;
                            };

                            var otherInput = cosmdlPendingEnable.groupEl.querySelector('input[data-drive-key="other"][data-field="url"]');
                            if (otherInput){
                                fillToOther();
                                return;
                            }

                            if (!cosmdlSmartConfig.canManage){
                                cosmdlSmartSetTip(cosmdlPendingEnable.tip, '“其他网盘”未启用，且当前账号无权限启用。请联系管理员在“CosmautDL 设置 → 网盘管理”启用。', 'bad');
                                cosmdlCloseModal();
                                cosmdlPendingEnable = null;
                                return;
                            }

                            other.disabled = true;
                            cosmdlEnableDriveAjax('other').then(function(j){
                                if (!j || !j.success){
                                    var msg = (j && j.data && j.data.message) ? j.data.message : '启用失败，请稍后重试';
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, msg, 'bad');
                                    return;
                                }
                                var label = (j.data && j.data.label) ? String(j.data.label) : cosmdlDriveLabel('other');
                                cosmdlCreateDriveRow(cosmdlPendingEnable.groupEl, 'other', label);
                                try{
                                    var list = cosmdlPendingEnable.driveRows;
                                    if (list && typeof list.length !== 'undefined'){
                                        var exists = false;
                                        for (var i = 0; i < list.length; i++){
                                            if (list[i] && list[i].key === 'other'){ exists = true; break; }
                                        }
                                        if (!exists){
                                            list.push({ key: 'other', label: label });
                                        }
                                    }
                                }catch(e){}
                                fillToOther();
                            }).catch(function(){
                                cosmdlSmartSetTip(cosmdlPendingEnable.tip, '启用请求失败，请检查网络或稍后重试。', 'bad');
                            }).finally(function(){
                                other.disabled = false;
                            });
                        });
                    }
                    if (confirm){
                        confirm.addEventListener('click', function(){
                            if (!cosmdlPendingEnable){
                                cosmdlCloseModal();
                                return;
                            }
                            if (!cosmdlSmartConfig.canManage){
                                cosmdlSmartSetTip(cosmdlPendingEnable.tip, '当前账号无权限启用网盘，请联系管理员在“CosmautDL 设置 → 网盘管理”启用。', 'bad');
                                cosmdlCloseModal();
                                cosmdlPendingEnable = null;
                                return;
                            }
                            confirm.disabled = true;
                            var dk = cosmdlPendingEnable.parsed.driveKey;
                            cosmdlEnableDriveAjax(dk).then(function(j){
                                if (!j || !j.success){
                                    var msg = (j && j.data && j.data.message) ? j.data.message : '启用失败，请稍后重试';
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, msg, 'bad');
                                    return;
                                }
                                var label = (j.data && j.data.label) ? String(j.data.label) : cosmdlDriveLabel(dk);
                                cosmdlCreateDriveRow(cosmdlPendingEnable.groupEl, dk, label);
                                try{
                                    if (cosmdlSmartConfig.driveMap){
                                        if (!cosmdlSmartConfig.driveMap[dk]){ cosmdlSmartConfig.driveMap[dk] = {}; }
                                        cosmdlSmartConfig.driveMap[dk].label = label;
                                    }
                                }catch(e){}
                                try{
                                    var list = cosmdlPendingEnable.driveRows;
                                    if (list && typeof list.length !== 'undefined'){
                                        var exists = false;
                                        for (var i = 0; i < list.length; i++){
                                            if (list[i] && list[i].key === dk){ exists = true; break; }
                                        }
                                        if (!exists){
                                            list.push({ key: dk, label: label });
                                        }
                                    }
                                }catch(e){}
                                var result = cosmdlSmartFill(cosmdlPendingEnable.groupEl, cosmdlPendingEnable.parsed, cosmdlPendingEnable.driveRows);
                                if (result.ok){
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, result.message, 'ok');
                                    cosmdlPendingEnable.textarea.setAttribute('data-autoselect', '1');
                                } else {
                                    cosmdlSmartSetTip(cosmdlPendingEnable.tip, result.message, 'bad');
                                }
                            }).catch(function(){
                                cosmdlSmartSetTip(cosmdlPendingEnable.tip, '启用请求失败，请检查网络或稍后重试。', 'bad');
                            }).finally(function(){
                                confirm.disabled = false;
                                cosmdlCloseModal();
                                cosmdlPendingEnable = null;
                            });
                        });
                    }
                })();

                root.querySelectorAll('.cosmdl-smart-row').forEach(function(row){
                    var textarea = row.querySelector('.cosmdl-smart-input');
                    var btn = row.querySelector('.cosmdl-smart-btn');
                    var clearBtn = row.querySelector('.cosmdl-smart-clear');
                    var tip = row.querySelector('.cosmdl-smart-tip');
                    var group = row.closest('.cosmdl-attach-group');
                    if (!textarea || !btn || !group){ return; }

                    var driveRows = [];
                    group.querySelectorAll('.drive-row[data-drive-key]').forEach(function(drow){
                        var key = drow.getAttribute('data-drive-key') || '';
                        var labelEl = drow.querySelector('label');
                        var label = labelEl ? labelEl.textContent.trim() : key;
                        if (key){
                            driveRows.push({ key: key, label: label });
                        }
                    });

                    function autoSelectIfNeeded(){
                        try{
                            if (textarea.value && textarea.value.length > 0){
                                textarea.select();
                            }
                        }catch(e){}
                    }

                    textarea.addEventListener('focus', function(){
                        if (textarea.getAttribute('data-autoselect') === '1'){
                            autoSelectIfNeeded();
                        }
                    });
                    textarea.addEventListener('click', function(){
                        if (textarea.getAttribute('data-autoselect') === '1'){
                            autoSelectIfNeeded();
                        }
                    });

                    btn.addEventListener('click', function(){
                        btn.disabled = true;
                        var raw = textarea.value || '';
                        var parsed = cosmdlSmartParse(raw, driveRows);
                        var result = cosmdlSmartFill(group, parsed, driveRows);
                        if (result.ok){
                            cosmdlSmartSetTip(tip, result.message, 'ok');
                            textarea.setAttribute('data-autoselect', '1');
                        } else if (result.needEnable) {
                            var dk = result.driveKey;
                            var msgEl = document.getElementById('cosmdl-enable-drive-modal-message');
                            var titleEl = document.getElementById('cosmdl-enable-drive-modal-title');
                            var otherBtn = document.getElementById('cosmdl-enable-drive-other');
                            var confirmBtn = document.getElementById('cosmdl-enable-drive-confirm');
                            var label = cosmdlDriveLabel(dk);
                            if (titleEl){ titleEl.textContent = '是否启用该网盘？'; }
                            if (msgEl){
                                msgEl.textContent = '识别到该分享属于“' + label + '”，但当前未在网盘管理启用。是否现在启用并自动回填到该网盘？';
                            }
                            if (otherBtn){
                                cosmdlSetHidden(otherBtn, false);
                            }
                            if (confirmBtn){
                                confirmBtn.disabled = false;
                            }
                            cosmdlPendingEnable = { groupEl: group, parsed: parsed, driveRows: driveRows, textarea: textarea, tip: tip };
                            cosmdlOpenModal();
                            cosmdlSmartSetTip(tip, '该网盘未启用，已弹出确认窗口。', 'bad');
                        } else {
                            cosmdlSmartSetTip(tip, result.message, 'bad');
                        }
                        btn.disabled = false;
                    });

                    if (clearBtn){
                        clearBtn.addEventListener('click', function(){
                            textarea.value = '';
                            textarea.setAttribute('data-autoselect', '0');
                            cosmdlSmartSetTip(tip, '已清空，可直接粘贴新的分享内容', '');
                            textarea.focus();
                        });
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', cosmdlSmartInit);
        })();
		<?php
		$cosmdl_inline_js = trim((string) ob_get_clean());
		$this->metabox_inline_js = $cosmdl_inline_js;

        // 中文注释：先输出启用下载开关（不显示“启用下载”文字），随后通过脚本移动到标题右侧
        $start_field = null;
        foreach($fields as $f){
            if($f['name'] === 'cosmdl_start'){
                $start_field = $f;
                break;
            }
        }
        if($start_field){
            $val = $this->get_meta($post->ID, $start_field['name']);
            echo '<div id="cosmdl-metabox">';
            // 隐藏行，仅承载待移动的开关控件
            echo '<div class="general-row cosmdl-hidden" id="cosmdl-start-row">';
            echo '<span id="cosmdl-start-toggle-wrap" class="cosmdl-header-toggle">';
            echo '<label class="cosmdl-switch" for="'.esc_attr($start_field['name']).'"><input type="checkbox" name="'.esc_attr($start_field['name']).'" id="'.esc_attr($start_field['name']).'" value="yes" '.checked('yes', htmlentities($val,1), false).' /><span class="cosmdl-slider"></span></label>';
            echo '</span>';
            echo '</div>';
            // 添加统一的nonce字段，替代每个字段单独的nonce验证
            wp_nonce_field('cosmdl_save_meta', 'cosmdl_meta_nonce');

            echo '<div id="cosmdl-enable-drive-modal" class="cosmdl-delete-modal" role="dialog" aria-labelledby="cosmdl-enable-drive-modal-title" aria-hidden="true">'
                . '<div class="cosmdl-delete-modal-content">'
                . '<div class="cosmdl-delete-modal-header">'
                . '<span class="dashicons dashicons-admin-generic"></span>'
                . '<h3 id="cosmdl-enable-drive-modal-title" class="cosmdl-delete-modal-title">'.esc_html__('是否启用该网盘？','cosmautdl').'</h3>'
                . '</div>'
                . '<div class="cosmdl-delete-modal-message" id="cosmdl-enable-drive-modal-message"></div>'
                . '<div class="cosmdl-delete-modal-buttons">'
                . '<button type="button" id="cosmdl-enable-drive-cancel" class="cosmdl-delete-modal-btn cancel">'.esc_html__('取消','cosmautdl').'</button>'
                . '<button type="button" id="cosmdl-enable-drive-other" class="cosmdl-delete-modal-btn cancel cosmdl-hidden">'.esc_html__('回填到其他网盘','cosmautdl').'</button>'
                . '<button type="button" id="cosmdl-enable-drive-confirm" class="cosmdl-delete-modal-btn confirm">'.esc_html__('启用并回填','cosmautdl').'</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }
        
        // 中文注释：可折叠区域包含附件分组（最多6个），每组有通用字段与网盘字段
        $collapsible_class = 'cosmdl-collapsible';
        if ( $should_collapse ) {
            $collapsible_class .= ' cosmdl-hidden';
        }
        echo '<div class="' . esc_attr( $collapsible_class ) . '">';
        echo '<div class="cosmdl-tabs">';
        // 默认仅输出“附件1”标签，其它附件标签由脚本在有数据或点击“+”时动态生成
        echo '<button type="button" class="cosmdl-tab active" data-idx="1">附件 1<span class="cosmdl-tab-close" data-idx="1">×</span></button>';
        echo '<button type="button" class="cosmdl-tab-add" id="cosmdl_tab_add">+</button>';
        echo '</div>';
        
        $general = array('cosmdl_official_site','cosmdl_softtype','cosmdl_name','cosmdl_size','cosmdl_date','cosmdl_author');

        for($idx=1;$idx<=6;$idx++){
            echo '<div class="cosmdl-attach-group'.($idx===1?'':' cosmdl-hidden').'" data-idx="'.esc_attr($idx).'">';

            echo '<div class="general-row cosmdl-smart-row">';
            echo '<label class="cosmdl-field-label">'.esc_html__('智能识别','cosmautdl').'</label>';
            echo '<div class="cosmdl-smart-wrap">';
            echo '<textarea class="cosmdl-smart-input" rows="2" placeholder="'.esc_attr__('粘贴网盘原始分享内容（包含链接/提取码/访问码），点击【识别】自动回填到下方对应网盘','cosmautdl').'" spellcheck="false"></textarea>';
            echo '<div class="cosmdl-smart-actions">';
            echo '<button type="button" class="button cosmdl-smart-btn">'.esc_html__('识别','cosmautdl').'</button>';
            echo '<button type="button" class="button cosmdl-smart-clear">'.esc_html__('清空','cosmautdl').'</button>';
            echo '</div>';
            echo '<div class="cosmdl-smart-tip" aria-live="polite"></div>';
            echo '</div>';
            echo '</div>';

            // 通用字段
            foreach($fields as $f){
                if(!in_array($f['name'],$general)) continue;
                $key = $this->key_for_index($f['name'], $idx);
                $val = $this->get_meta($post->ID, $key);
                echo '<div class="general-row">';
                echo '<label for="'.esc_attr($key).'" class="cosmdl-field-label">'.esc_html($f['title']).'</label>';
                if ($f['type']==='text'){
                    // 中文注释：为特定字段提供更友好的占位提示
                    $placeholder = '';
                    if ($f['name']==='cosmdl_size'){
                        $placeholder = '留空则不显示';
                    } elseif ($f['name']==='cosmdl_author'){
                        $placeholder = '留空则不显示';
                    } elseif ($f['name']==='cosmdl_date'){
                        $placeholder = '留空则显示文章的发布日期';
                    }
                    echo '<input class="general-input" type="text" name="'.esc_attr($key).'" id="'.esc_attr($key).'" value="'.esc_attr($val).'"'.($placeholder?' placeholder="'.esc_attr($placeholder).'"':'').' />';
                    // 为“资源大小”添加单位下拉（KB/MB/GB，默认MB），紧邻输入框
                    if ($f['name']==='cosmdl_size'){
                        $unit_key = $this->key_for_index('cosmdl_size_unit', $idx);
                        $unit_val = $this->get_meta($post->ID, $unit_key);
                        if (!$unit_val) { $unit_val = 'MB'; }
                        echo '<select class="general-input select-input cosmdl-size-unit" name="'.esc_attr($unit_key).'" id="'.esc_attr($unit_key).'" >'
                           . '<option value="KB" '.selected(strtoupper($unit_val),'KB',false).'>KB</option>'
                           . '<option value="MB" '.selected(strtoupper($unit_val),'MB',false).'>MB</option>'
                           . '<option value="GB" '.selected(strtoupper($unit_val),'GB',false).'>GB</option>'
                           . '</select>';
                    }
                    // 提示性文本：说明更新日期的自动填充与展示策略
                    if ($f['name']==='cosmdl_date'){
                        echo '<p class="description cosmdl-hint">'
                           . '</p>';
                    }
                } elseif ($f['type']==='textarea'){
                    echo '<textarea class="general-input" name="'.esc_attr($key).'" id="'.esc_attr($key).'" rows="3">'.esc_textarea($val).'</textarea>';
                } elseif ($f['type']==='checkbox'){
                    echo '<label class="cosmdl-switch" for="'.esc_attr($key).'"><input type="checkbox" name="'.esc_attr($key).'" id="'.esc_attr($key).'" value="yes" '.checked('yes', htmlentities($val,1), false).' /><span class="cosmdl-slider"></span></label>';
                } elseif ($f['type']==='select'){
                    $opts = isset($f['options']) && is_array($f['options']) ? $f['options'] : array();
                    echo '<select class="general-input select-input" name="'.esc_attr($key).'" id="'.esc_attr($key).'">';
                    foreach($opts as $k=>$label){ echo '<option value="'.esc_attr($k).'" '.selected($val,$k,false).'>'.esc_html($label).'</option>'; }
                    echo '</select>';
                }
                echo '</div>';
            }

            // 网盘字段
            foreach($drive_management as $dkey => $drive){
                if (!isset($drive['enabled']) || $drive['enabled'] !== 'yes') continue;
                $base = $this->get_field_names_for_drive($dkey);
                $url_key = $this->key_for_index($base['url'], $idx);
                $pwd_key = $base['pwd'] ? $this->key_for_index($base['pwd'], $idx) : '';
                $unlock_key = $this->key_for_index($base['unlock'], $idx);

                $url_val = $this->get_meta($post->ID, $url_key);
                $pwd_val = $pwd_key ? $this->get_meta($post->ID, $pwd_key) : '';
                $unlock_val = $this->get_meta($post->ID, $unlock_key);
                
                // 已移除：旧版 alias 兼容逻辑


                echo '<div class="drive-row" data-drive-key="'.esc_attr($dkey).'">';
                echo '<label for="'.esc_attr($url_key).'" class="cosmdl-field-label">'.esc_html($drive['label']).'</label>';
                // 将LOGO移到输入框内部
                $drive_icon_path = plugin_dir_url(dirname(__FILE__)) . 'images/' . $dkey . '.png';
                echo '<div class="cosmdl-drive-input-wrap">';
                echo '<img class="cosmdl-drive-icon" src="'.esc_url($drive_icon_path).'" alt="'.esc_attr($drive['label']).'" />';
                echo '<input class="url-input" type="text" name="'.esc_attr($url_key).'" id="'.esc_attr($url_key).'" value="'.esc_attr($url_val).'" data-drive-key="'.esc_attr($dkey).'" data-field="url" />';
                echo '</div>';
                echo '<div class="inline">';
                if ($pwd_key){
                    echo '<input class="short-input" type="text" name="'.esc_attr($pwd_key).'" id="'.esc_attr($pwd_key).'" value="'.esc_attr($pwd_val).'" placeholder="提取码" data-drive-key="'.esc_attr($dkey).'" data-field="pwd" />';
                }
                if ($show_qr_block === 'yes'){
                    echo '<label class="inline-text" for="'.esc_attr($unlock_key).'">扫码解锁</label>';
                    echo '<label class="cosmdl-switch" for="'.esc_attr($unlock_key).'">'.
                         '<input type="checkbox" name="'.esc_attr($unlock_key).'" id="'.esc_attr($unlock_key).'" value="yes" '.
                         checked('yes', htmlentities($unlock_val,1), false).' /><span class="cosmdl-slider"></span></label>';
                }
                echo '</div></div>';
            }

            // 统一从 drive_management 渲染（其中自定义网盘项以 is_custom => 'yes' 标记），避免重复

            echo '</div>'; // end attach-group
        }

        echo '</div>'; // 关闭可折叠区域
        echo '</div>'; // 关闭元框包裹容器
    }
    
    /**
     * 获取网盘管理设置
     * 数据来源：后台设置 cosmdl_options['drive_management']。
     * 兜底：若为空或缺失，返回内置默认集合，保证编辑页始终可用。
     */
    private function get_drive_management_settings() {
        // 中文注释：从设置中读取网盘管理；若不存在（首次安装），使用内置默认值兜底，确保文章编辑页能显示网盘地址与提取码输入框
        $options = get_option('cosmdl_options', array());
        $drives = isset($options['drive_management']) ? $options['drive_management'] : array();
        if (!is_array($drives) || empty($drives)) {
            // 首次安装或设置缺失时的默认网盘集合（与后台默认值一致）
            $drives = array(
                'baidu'       => array('enabled' => 'yes', 'label' => '百度网盘',   'order' => 1,  'is_custom' => 'no'),
                '123'         => array('enabled' => 'yes', 'label' => '123云盘',    'order' => 2,  'is_custom' => 'no'),
                'ali'         => array('enabled' => 'yes', 'label' => '阿里云盘',   'order' => 3,  'is_custom' => 'no'),
                '189'         => array('enabled' => 'yes', 'label' => '天翼云盘',   'order' => 4,  'is_custom' => 'no'),
                'quark'       => array('enabled' => 'yes', 'label' => '夸克网盘',   'order' => 5,  'is_custom' => 'no'),
                'pikpak'      => array('enabled' => 'yes', 'label' => 'PikPak',     'order' => 6,  'is_custom' => 'no'),
                'lanzou'      => array('enabled' => 'yes', 'label' => '蓝奏云网盘', 'order' => 7,  'is_custom' => 'no'),
                'xunlei'      => array('enabled' => 'yes', 'label' => '迅雷云盘',   'order' => 8,  'is_custom' => 'no'),
                'weiyun'      => array('enabled' => 'yes', 'label' => '微云',       'order' => 9,  'is_custom' => 'no'),
                'onedrive'    => array('enabled' => 'yes', 'label' => 'OneDrive',   'order' => 10, 'is_custom' => 'no'),
                'googledrive' => array('enabled' => 'yes', 'label' => 'GoogleDrive','order' => 11, 'is_custom' => 'no'),
                'dropbox'     => array('enabled' => 'yes', 'label' => 'Dropbox',    'order' => 12, 'is_custom' => 'no'),
                'mega'        => array('enabled' => 'yes', 'label' => 'MEGA',       'order' => 13, 'is_custom' => 'no'),
                'mediafire'   => array('enabled' => 'yes', 'label' => 'MediaFire',  'order' => 14, 'is_custom' => 'no'),
                'box'         => array('enabled' => 'yes', 'label' => 'Box',        'order' => 15, 'is_custom' => 'no'),
                'other'       => array('enabled' => 'yes', 'label' => '其他网盘',   'order' => 16, 'is_custom' => 'no'),
            );
        }
        return $drives;
    }
    
    /**
     * 获取指定网盘的字段名映射
     * 规则：
     * - 标准网盘（1-16）：cosmdl_downurl_{ID}, cosmdl_cipher_{ID}, cosmdl_unlock_{ID}
     * - 自定义网盘：cosmdl_downurl_custom_{ID}, cosmdl_cipher_custom_{ID}, cosmdl_unlock_custom_{ID}
     */
    private function get_field_names_for_drive($drive_key) {
        $options = get_option('cosmdl_options', array());
        $drives = isset($options['drive_management']) ? $options['drive_management'] : array();
        
        $is_custom = false;
        if (isset($drives[$drive_key]) && isset($drives[$drive_key]['is_custom']) && $drives[$drive_key]['is_custom'] === 'yes') {
            $is_custom = true;
        }

        if ($is_custom) {
            // 兼容：如果ID本身包含 'custom_' 前缀（如旧数据），提取纯ID
            $clean_key = preg_replace('/^custom_/', '', $drive_key);
            return array(
                'url'    => 'cosmdl_downurl_custom_' . $clean_key,
                'pwd'    => 'cosmdl_cipher_custom_' . $clean_key,
                'unlock' => 'cosmdl_unlock_custom_' . $clean_key
            );
        } else {
            return array(
                'url'    => 'cosmdl_downurl_' . $drive_key,
                'pwd'    => 'cosmdl_cipher_' . $drive_key,
                'unlock' => 'cosmdl_unlock_' . $drive_key
            );
        }
    }

    /**
     * 工具方法：根据附件索引生成元键
     * 规则：附件1原样返回；附件2-6将 cosmdl_ 前缀替换为 cosmdl{idx}_。
     */
    private function key_for_index($base_key, $idx){
        $idx = intval($idx);
        if ($idx <= 1) return $base_key;
        return preg_replace('/^cosmdl_/', 'cosmdl'.$idx.'_',$base_key);
    }

    /**
     * 保存逻辑
     * 流程：一次性验证大nonce与权限 -> 清理/类型化 -> 写入或删除（空值删除）。
     * 范围：附件1的通用字段与网盘字段；附件2-6的通用字段与所有启用网盘；自定义网盘（附件1）。
     * 额外：自动填充更新日期（发布状态）、计算并写入 cosmdl_size_bytes（KB/MB/GB 转换）。
     * 优化：使用单一nonce组代替多个独立nonce，减少输入变量数量。
     */
	public function save_meta($post_id){
		$post_data = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
		$post_data = is_array($post_data) ? wp_unslash($post_data) : array();
		// 验证权限
		$ptype = isset($post_data['post_type']) ? sanitize_key($post_data['post_type']) : get_post_type($post_id);
		if ('page' == $ptype && !current_user_can('edit_page', $post_id)) return;
		if ('post' == $ptype && !current_user_can('edit_post', $post_id)) return;
		
		// 使用统一的nonce验证
		$nonce = isset($post_data['cosmdl_meta_nonce']) ? sanitize_text_field($post_data['cosmdl_meta_nonce']) : '';
		if (!$nonce || !wp_verify_nonce($nonce, 'cosmdl_save_meta')) {
			return;
		}
		
		// 保存默认字段（附件1）
		$fields = $this->fields();
		foreach($fields as $f){
			$name = $f['name'];
			$raw = isset($post_data[$name]) ? $post_data[$name] : '';
			if ($f['type']==='checkbox'){
				$data = !empty($raw) ? 'yes' : '';
			} else {
				if (strpos($name,'downurl')!==false || $name==='cosmdl_official_site') {
					$data = esc_url_raw((string) $raw);
				} else {
					$data = sanitize_text_field((string) $raw);
				}
			}
			// 写入新键；若为空则删除
			$this->save_single_meta($post_id, $name, $data);
		}
		// 单独保存“资源大小单位”（附件1）
		if (isset($post_data['cosmdl_size_unit'])){
			$u = strtoupper(sanitize_text_field((string) $post_data['cosmdl_size_unit']));
			$allowed = array('KB','MB','GB');
			if (!in_array($u, $allowed, true)) { $u = 'MB'; }
			$this->save_single_meta($post_id, 'cosmdl_size_unit', $u);
		}

        // 修复：保存附件1的标准网盘字段（此前遗漏）
        // 注意：自定义网盘由 save_custom_drive_meta 单独处理，此处仅处理标准网盘
        $drive_management = $this->get_drive_management_settings();
        foreach($drive_management as $dkey => $drive){
            if (!isset($drive['enabled']) || $drive['enabled'] !== 'yes') continue;
            // 跳过自定义网盘
            if (isset($drive['is_custom']) && $drive['is_custom'] === 'yes') continue;

            $base = $this->get_field_names_for_drive($dkey);
            $url_key = $base['url'];
            $pwd_key = $base['pwd'];
            $unlock_key = $base['unlock'];

			if (isset($post_data[$url_key])){
				$this->save_single_meta($post_id, $url_key, esc_url_raw((string) $post_data[$url_key]));
				
				// 仅当该网盘存在于表单提交中时，才处理密码和解锁状态
				if ($pwd_key && isset($post_data[$pwd_key])){
					$this->save_single_meta($post_id, $pwd_key, sanitize_text_field((string) $post_data[$pwd_key]));
				}
				
				// 处理 Checkbox：未选中时 POST 中无该键，需显式设为空
				$unlock_val = isset($post_data[$unlock_key]) ? 'yes' : '';
				$this->save_single_meta($post_id, $unlock_key, $unlock_val);
			}
		}
        
        // 保存附件2-6的字段与网盘
        $general = array('cosmdl_official_site','cosmdl_softtype','cosmdl_name','cosmdl_size','cosmdl_size_unit','cosmdl_date','cosmdl_author');
        // $drive_management 已在上方获取

		for($idx=2;$idx<=6;$idx++){
			// 通用字段
			foreach($general as $base){
				$key = $this->key_for_index($base, $idx);
				$raw = isset($post_data[$key]) ? $post_data[$key] : '';
				if ($base==='cosmdl_official_site'){
					$data = esc_url_raw((string) $raw);
				} else {
					$data = sanitize_text_field((string) $raw);
					if ($base==='cosmdl_size_unit'){
						$data = strtoupper($data);
						$allowed = array('KB','MB','GB');
						if (!in_array($data, $allowed, true)) { $data = 'MB'; }
					}
				}
				$this->save_single_meta($post_id, $key, $data);
			}
			// 默认/自定义网盘
			foreach($drive_management as $dkey=>$drive){
                if (!isset($drive['enabled']) || $drive['enabled']!=='yes') continue;
                $base = $this->get_field_names_for_drive($dkey);
                $url_key = $this->key_for_index($base['url'], $idx);
                $pwd_key = $base['pwd'] ? $this->key_for_index($base['pwd'], $idx) : '';
                $unlock_key = $this->key_for_index($base['unlock'], $idx);
				if (isset($post_data[$url_key])){
					$url_data = esc_url_raw((string) $post_data[$url_key]);
					$this->save_single_meta($post_id, $url_key, $url_data);
				}
				if ($pwd_key && isset($post_data[$pwd_key])){
					$pwd_data = sanitize_text_field((string) $post_data[$pwd_key]);
					$this->save_single_meta($post_id, $pwd_key, $pwd_data);
				}
				$unlock_data = isset($post_data[$unlock_key]) ? 'yes' : '';
				$this->save_single_meta($post_id, $unlock_key, $unlock_data);
			}
			// 已移除：旧版针对 options['custom_drives'] 的保存循环，统一由 drive_management 的自定义项处理
		}

		// 保存自定义网盘字段（附件1） - 使用统一nonce验证，不再单独验证每个字段
		$this->save_custom_drive_meta($post_id, $post_data);

        // 中文注释：在保存流程中也尝试自动填充（当状态已是“已发布”时）
        // 注意：部分场景下 save_post 在状态最终变为 publish 之前触发，因此此处作为兜底，核心填充逻辑在 transition_post_status 中执行
        $status = get_post_status($post_id);
        if ($status === 'publish') {
            $current_update = get_post_meta($post_id, 'cosmdl_date', true);
            if (empty($current_update)){
                $pub_date = get_the_date('Y-m-d', $post_id);
                if (!empty($pub_date)){
                    update_post_meta($post_id, 'cosmdl_date', $pub_date);
                }
            }
        }

        // 统一计算并写入“资源大小（字节）”元键，支持附件1-6
        // 说明：在保存后读取最新的 size 与 unit，按 KB/MB/GB 转换为字节，便于文件树使用数据库层按字节排序与筛选
        $allowed_units = array('KB','MB','GB');
        for($idx=1;$idx<=6;$idx++){
            $size_key = $this->key_for_index('cosmdl_size', $idx);
            $unit_key = $this->key_for_index('cosmdl_size_unit', $idx);
            $bytes_key = $this->key_for_index('cosmdl_size_bytes', $idx);
            $size_val = trim(strval(get_post_meta($post_id, $size_key, true)));
            $unit_val = strtoupper(trim(strval(get_post_meta($post_id, $unit_key, true))));
            if ($size_val==='') {
                // 无大小值时删除字节数，避免脏数据
                delete_post_meta($post_id, $bytes_key);
                continue;
            }
            if (!in_array($unit_val, $allowed_units, true)) { $unit_val = 'MB'; }
            $bytes = $this->compute_bytes($size_val, $unit_val);
            if ($bytes>0){
                update_post_meta($post_id, $bytes_key, $bytes);
            } else {
                delete_post_meta($post_id, $bytes_key);
            }
        }
    }

    /**
     * 发布态自动填充更新日期
     * 当文章状态转换为 publish，且 cosmdl_date 为空时，写入文章的发布日期（Y-m-d）。
     * 限定：仅处理标准文章类型 post，避免影响页面或自定义类型。
     */
    public function autofill_update_date_on_publish($new_status, $old_status, $post){
        // 仅在状态变更为 publish 时执行
        if ($new_status !== 'publish') return;
        // 仅处理标准文章类型
        if (!is_object($post) || $post->post_type !== 'post') return;
        $post_id = $post->ID;
        // 避免覆盖已有值
        $current_update = get_post_meta($post_id, 'cosmdl_date', true);
        if (!empty($current_update)) return;
        // 使用文章的发布日期作为默认“更新日期”
        $pub_date = get_the_date('Y-m-d', $post_id);
        if (!empty($pub_date)){
            update_post_meta($post_id, 'cosmdl_date', $pub_date);
        }
    }
    
    /**
     * 保存自定义网盘的元数据
     * 场景：附件1的自定义网盘字段（URL/密码/扫码解锁）
     * 优化：不再逐项校验nonce，使用统一nonce验证
     */
	private function save_custom_drive_meta($post_id, $post_data) {
		// 获取所有已启用的网盘
		$drive_management = $this->get_drive_management_settings();
        
        foreach($drive_management as $key => $drive) {
            // 只处理自定义网盘
            if (!isset($drive['is_custom']) || $drive['is_custom'] !== 'yes') {
                continue;
            }
            
			// 获取字段名
			$field_names = $this->get_field_names_for_drive($key);
			
			// 处理URL字段
			if (isset($post_data[$field_names['url']])) {
				$url_data = esc_url_raw((string) $post_data[$field_names['url']]);
				$this->save_single_meta($post_id, $field_names['url'], $url_data);
			}
			
			// 处理密码字段
			if (isset($post_data[$field_names['pwd']])) {
				$pwd_data = sanitize_text_field((string) $post_data[$field_names['pwd']]);
				$this->save_single_meta($post_id, $field_names['pwd'], $pwd_data);
			}
			
			// 处理解锁字段
			$unlock_data = isset($post_data[$field_names['unlock']]) ? 'yes' : '';
			$this->save_single_meta($post_id, $field_names['unlock'], $unlock_data);
		}
	}
    
    /**
     * 保存单个元数据的辅助方法
     * 规则：若不存在则 add_post_meta；值变化则 update_post_meta；空值删除当前键。
     */
    private function save_single_meta($post_id, $name, $data) {
        if (get_post_meta($post_id, $name) === '') {
            add_post_meta($post_id, $name, $data, true);
        } elseif ($data != get_post_meta($post_id, $name, true)) {
            update_post_meta($post_id, $name, $data);
        } elseif ($data === '') {
            delete_post_meta($post_id, $name, get_post_meta($post_id, $name, true));
        }
    }

    /**
     * 计算字节数
     * 支持：KB/MB/GB 单位；非数值或空值返回 0。
     */
    private function compute_bytes($size, $unit){
        $s = trim(strval($size));
        if ($s==='') return 0;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)$/', $s, $m)){
            $val = floatval($m[1]);
        } else {
            return 0;
        }
        $u = strtoupper(trim(strval($unit)));
        switch($u){
            case 'KB': return intval(round($val * 1024));
            case 'MB': return intval(round($val * 1024 * 1024));
            case 'GB': return intval(round($val * 1024 * 1024 * 1024));
            default:   return intval(round($val * 1024 * 1024)); // 默认按 MB
        }
    }

    /** 工具：读取 cosmdl_* 元键 */
    private function get_meta($post_id, $name){
        return get_post_meta($post_id, $name, true);
    }

    /**
     * 添加统一的meta box nonce字段
     * 在meta box开始处调用此方法添加单一nonce，替代每个字段单独的nonce
     */
    private function render_meta_box_nonce() {
        wp_nonce_field('cosmdl_save_post_meta', 'cosmdl_meta_box_nonce');
    }
    
    /**
     * 工具：渲染表格行
     * 支持类型：text/textarea/checkbox/select
     * 优化：移除每个字段单独的nonce，使用统一的meta box nonce
     */
    private function render_field_row($f, $value){
        echo '<tr><th><label for="'.esc_attr($f['name']).'">'.esc_html($f['title']).'</label></th><td>';
        if ($f['type']==='text'){
            echo '<input class="cosmdl-metabox-admin-input" type="text" name="'.esc_attr($f['name']).'" id="'.esc_attr($f['name']).'" value="'.esc_attr($value).'" />';
        } elseif ($f['type']==='textarea'){
            echo '<textarea class="cosmdl-metabox-admin-input" name="'.esc_attr($f['name']).'" id="'.esc_attr($f['name']).'" rows="4">'.esc_textarea($value).'</textarea>';
        } elseif ($f['type']==='checkbox'){
            echo '<label class="switch" for="'.esc_attr($f['name']).'"><input type="checkbox" name="'.esc_attr($f['name']).'" id="'.esc_attr($f['name']).'" value="yes" '.checked('yes', htmlentities($value,1), false).' /><span class="slider"></span></label>';
        } elseif ($f['type']==='select'){
            $opts = isset($f['options']) && is_array($f['options']) ? $f['options'] : array();
            echo '<select name="'.esc_attr($f['name']).'" id="'.esc_attr($f['name']).'">';
            foreach($opts as $key=>$label){ echo '<option value="'.esc_attr($key).'" '.selected($value,$key,false).'>'.esc_html($label).'</option>'; }
            echo '</select>';
        }
        // 移除每个字段单独的nonce，使用统一的meta box nonce
        echo '</td></tr>';
    }
}
