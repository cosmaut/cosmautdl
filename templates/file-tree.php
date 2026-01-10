<?php
/**
 * 文件树模板：显示全站已配置分享文件的文章列表，支持基础筛选与排序
 * 路由：downloads/tree.html（固定链接）或 /?cosmdl_tree=1（普通链接）
 */
if (!defined('ABSPATH')) { exit; }

// 确保引入下载页模板函数库
require_once COSMDL_PLUGIN_DIR . 'includes/download-page.php';

// 可见性与启用开关（模板层保护）
$cosmdl_opts = get_option('cosmdl_options', []);
$cosmdl_enable_tree = isset($cosmdl_opts['enable_tree']) ? $cosmdl_opts['enable_tree'] : 'yes';
$cosmdl_tree_visibility = isset($cosmdl_opts['tree_visibility']) ? $cosmdl_opts['tree_visibility'] : 'public';

if ($cosmdl_enable_tree !== 'yes'){
  status_header(404);
  nocache_headers();
  echo '<h1>404</h1><p>' . esc_html__('文件树未启用', 'cosmautdl') . '</p>';
  exit;
}

if ($cosmdl_tree_visibility === 'admin' && !current_user_can('manage_options')){
  status_header(404);
  nocache_headers();
  echo '<h1>404</h1><p>' . esc_html__('此页面仅管理员可见', 'cosmautdl') . '</p>';
  exit;
}

// 明确设置 200 状态并取消 404 标记
status_header(200);
if (isset($GLOBALS['wp_query'])){ $GLOBALS['wp_query']->is_404 = false; }

// 加载页头
$cosmdl_debug = (string) get_query_var('debug');
if ($cosmdl_debug === '1'){
  try { get_header(); } catch (Throwable $e) {
    echo '<div class="cosmdl-tree-header-error">' . esc_html__('加载主题页头时出错：', 'cosmautdl')
       . esc_html($e->getMessage()) . '</div>';
  }
} else {
  get_header();
}
?>

<div class="container puock-text cosmdl-tree-fullwidth"><!-- 中文注释：文件树外层容器 -->
  <?php
  // 当前页码
  $cosmdl_paged = max(1, absint(get_query_var('paged')));
  $cosmdl_page_suffix = '';
  if ($cosmdl_paged > 1) {
    /* translators: %d: 当前页码 */
    $cosmdl_page_suffix = ' - ' . sprintf(esc_html__('第 %d 页', 'cosmautdl'), $cosmdl_paged);
  }
  $cosmdl_page_title = esc_html__('文件树（所有分享文件）', 'cosmautdl') . $cosmdl_page_suffix;
  
  // 检查是否在新窗口打开链接
  $cosmdl_tree_open_links_in_new_window = isset($cosmdl_opts['tree_open_links_in_new_window']) ? $cosmdl_opts['tree_open_links_in_new_window'] : 'yes';
  ?>
  <h1 class="t-lg"><?php echo esc_html($cosmdl_page_title); ?></h1>
  
  <?php
  $cosmdl_default_sort  = 'date'; 
  $cosmdl_default_order = 'DESC';

  // 读取筛选与排序参数
  $cosmdl_sort   = sanitize_text_field((string) get_query_var('sort'));
  if ($cosmdl_sort === '') { $cosmdl_sort = $cosmdl_default_sort; }
  $cosmdl_order  = strtoupper(sanitize_text_field((string) get_query_var('order')));
  if ($cosmdl_order === '') { $cosmdl_order = $cosmdl_default_order; }
  if (!in_array($cosmdl_order, array('ASC','DESC'), true)) { $cosmdl_order = 'DESC'; }

  // 分页设置
  $cosmdl_per = absint(get_query_var('per'));
  if (!$cosmdl_per){
    $cosmdl_wp_ppp = intval(get_option('posts_per_page', 10));
    if (in_array($cosmdl_wp_ppp, array(50,100,200), true)) { $cosmdl_per = $cosmdl_wp_ppp; }
  }
  if (!in_array($cosmdl_per, array(50,100,200), true)) { $cosmdl_per = 50; }

  $cosmdl_cat_id = absint(get_query_var('cat'));
  $cosmdl_tag    = sanitize_text_field((string) get_query_var('tag'));
  $cosmdl_author = absint(get_query_var('author'));
  $cosmdl_qkw    = sanitize_text_field((string) get_query_var('q'));
  $cosmdl_unit_f = strtoupper(sanitize_text_field((string) get_query_var('unit')));
  if (!in_array($cosmdl_unit_f, array('','KB','MB','GB'), true)) { $cosmdl_unit_f = ''; }
  $cosmdl_type_f = sanitize_text_field((string) get_query_var('type'));
  $cosmdl_size_min = sanitize_text_field((string) get_query_var('size_min'));
  $cosmdl_size_max = sanitize_text_field((string) get_query_var('size_max'));

  $cosmdl_drive_management = function_exists('cosmdl_get_drive_management_settings')
    ? cosmdl_get_drive_management_settings()
    : ( isset($cosmdl_opts['drive_management']) && is_array($cosmdl_opts['drive_management']) ? $cosmdl_opts['drive_management'] : array() );

  $cosmdl_min_bytes = $cosmdl_size_min ? cosmdl_size_to_bytes($cosmdl_size_min) : 0;
  $cosmdl_max_bytes = $cosmdl_size_max ? cosmdl_size_to_bytes($cosmdl_size_max) : 0;

  $cosmdl_has_attach_filters = ($cosmdl_unit_f !== '' || $cosmdl_type_f !== '' || $cosmdl_min_bytes > 0 || $cosmdl_max_bytes > 0);
  $cosmdl_needs_manual = ($cosmdl_has_attach_filters || in_array($cosmdl_sort, array('name','updated','size'), true));

  // 构建查询参数
  $cosmdl_args = array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $cosmdl_needs_manual ? -1 : $cosmdl_per,
    'paged' => $cosmdl_needs_manual ? 1 : $cosmdl_paged,
    'no_found_rows' => $cosmdl_needs_manual ? true : false, // 需要分页，所以需要计算总行数
    'fields' => 'ids', // 仅获取ID，后续再获取meta
    'update_post_meta_cache' => false, // 我们会手动获取需要的meta
    'update_post_term_cache' => false,
  );

  // 排序处理
  // 注意：排序主要基于主附件（附件1）的元数据，或者文章本身的属性
  switch($cosmdl_sort){
    case 'title':   
        $cosmdl_args['orderby'] = 'title'; 
        break;
    case 'name':    
        $cosmdl_args['orderby'] = 'date';
        break;
    case 'updated': 
        $cosmdl_args['orderby'] = 'date';
        break;
    case 'author':  
        $cosmdl_args['orderby'] = 'author'; 
        break;
    case 'size':
        // 使用数据修正工具生成的 cosmdl_size_bytes 进行数值排序
        $cosmdl_args['orderby'] = 'date';
        break;
    default:        
        $cosmdl_args['orderby'] = 'date';
  }
  $cosmdl_args['order'] = $cosmdl_order;

  // 其他筛选条件
  if ($cosmdl_cat_id) { $cosmdl_args['cat'] = $cosmdl_cat_id; }
  if ($cosmdl_tag) { $cosmdl_args['tag'] = $cosmdl_tag; }
  if ($cosmdl_author) { $cosmdl_args['author'] = $cosmdl_author; }
  if ($cosmdl_qkw !== '') { $cosmdl_args['s'] = $cosmdl_qkw; }

  // 执行查询
  $cosmdl_q_page = new WP_Query($cosmdl_args);

  $cosmdl_page_post_ids = array();
  $cosmdl_total_pages = 0;
  if ($cosmdl_needs_manual) {
      $cosmdl_all_post_ids = is_array($cosmdl_q_page->posts) ? $cosmdl_q_page->posts : array();

      if (!empty($cosmdl_all_post_ids)) {
          update_meta_cache('post', $cosmdl_all_post_ids);
      }

      $cosmdl_sort_map = array();
      $cosmdl_filtered_post_ids = array();

      foreach ($cosmdl_all_post_ids as $cosmdl_pid) {
          $cosmdl_has_visible = false;
          for ($cosmdl_attach = 1; $cosmdl_attach <= 6; $cosmdl_attach++) {
              if (!cosmdl_attach_has_any_link($cosmdl_pid, $cosmdl_attach, $cosmdl_drive_management)) {
                  continue;
              }

              if ($cosmdl_type_f !== '' && !cosmdl_attach_has_type($cosmdl_pid, $cosmdl_type_f, $cosmdl_attach, $cosmdl_drive_management)) {
                  continue;
              }

              $cosmdl_prefix = ($cosmdl_attach === 1) ? 'cosmdl_' : ('cosmdl' . $cosmdl_attach . '_');
              $cosmdl_size_unit = (string) get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size_unit', true);
              $cosmdl_size_bytes = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size_bytes', true);
              $cosmdl_size = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size', true);

              if (!$cosmdl_size_bytes && $cosmdl_size) {
                  $cosmdl_size_bytes = cosmdl_calc_bytes($cosmdl_size, $cosmdl_size_unit);
              }

              if ($cosmdl_unit_f !== '') {
                  $cosmdl_unit_current = strtoupper(trim($cosmdl_size_unit));
                  if ($cosmdl_unit_current !== $cosmdl_unit_f) {
                      continue;
                  }
              }

              $cosmdl_size_bytes_int = intval($cosmdl_size_bytes);
              if ($cosmdl_min_bytes > 0 && $cosmdl_size_bytes_int < intval($cosmdl_min_bytes)) {
                  continue;
              }
              if ($cosmdl_max_bytes > 0 && $cosmdl_size_bytes_int > intval($cosmdl_max_bytes)) {
                  continue;
              }

              $cosmdl_has_visible = true;
              break;
          }

          if (!$cosmdl_has_visible) {
              continue;
          }

          $cosmdl_filtered_post_ids[] = $cosmdl_pid;

          if (!isset($cosmdl_sort_map[$cosmdl_pid])) {
              if ($cosmdl_sort === 'name') {
                  $cosmdl_sort_map[$cosmdl_pid] = strtolower((string) get_post_meta($cosmdl_pid, 'cosmdl_name', true));
              } elseif ($cosmdl_sort === 'updated') {
                  $cosmdl_d = (string) get_post_meta($cosmdl_pid, 'cosmdl_date', true);
                  $cosmdl_sort_map[$cosmdl_pid] = $cosmdl_d !== '' ? strtotime($cosmdl_d) : strtotime((string) get_post_field('post_modified', $cosmdl_pid));
              } elseif ($cosmdl_sort === 'size') {
                  $cosmdl_s = get_post_meta($cosmdl_pid, 'cosmdl_size_bytes', true);
                  if (!$cosmdl_s) {
                      $cosmdl_size_1 = get_post_meta($cosmdl_pid, 'cosmdl_size', true);
                      $cosmdl_unit_1 = (string) get_post_meta($cosmdl_pid, 'cosmdl_size_unit', true);
                      $cosmdl_s = $cosmdl_size_1 ? cosmdl_calc_bytes($cosmdl_size_1, $cosmdl_unit_1) : 0;
                  }
                  $cosmdl_sort_map[$cosmdl_pid] = intval($cosmdl_s);
              } elseif ($cosmdl_sort === 'title') {
                  $cosmdl_sort_map[$cosmdl_pid] = strtolower((string) get_the_title($cosmdl_pid));
              } elseif ($cosmdl_sort === 'author') {
                  $cosmdl_author_id = intval(get_post_field('post_author', $cosmdl_pid));
                  $cosmdl_sort_map[$cosmdl_pid] = strtolower((string) get_the_author_meta('display_name', $cosmdl_author_id));
              } else {
                  $cosmdl_sort_map[$cosmdl_pid] = strtotime((string) get_post_field('post_date', $cosmdl_pid));
              }
          }
      }

      usort($cosmdl_filtered_post_ids, function($a, $b) use ($cosmdl_sort_map, $cosmdl_sort, $cosmdl_order) {
          $va = $cosmdl_sort_map[$a] ?? null;
          $vb = $cosmdl_sort_map[$b] ?? null;

          if ($va === $vb) {
              return ($cosmdl_order === 'ASC') ? ($a <=> $b) : ($b <=> $a);
          }

          $cmp = 0;
          if (in_array($cosmdl_sort, array('name', 'title', 'author'), true)) {
              $cmp = strcmp((string) $va, (string) $vb);
          } else {
              $cmp = intval($va) <=> intval($vb);
          }

          return ($cosmdl_order === 'ASC') ? $cmp : -$cmp;
      });

      $cosmdl_total_posts = count($cosmdl_filtered_post_ids);
      $cosmdl_total_pages = (int) ceil($cosmdl_total_posts / max(1, $cosmdl_per));
      if ($cosmdl_total_pages < 1) {
          $cosmdl_total_pages = 1;
      }

      $cosmdl_offset = max(0, ($cosmdl_paged - 1) * $cosmdl_per);
      $cosmdl_page_post_ids = array_slice($cosmdl_filtered_post_ids, $cosmdl_offset, $cosmdl_per);
  } else {
      $cosmdl_page_post_ids = is_array($cosmdl_q_page->posts) ? $cosmdl_q_page->posts : array();
      $cosmdl_total_pages = intval($cosmdl_q_page->max_num_pages);
  }
  ?>

  <div class="p-block mt10">
    <form method="get" class="cosmdl-tree-filter-form">
      <input type="hidden" name="cosmdl_tree" value="1" />
      <div>
        <label><?php esc_html_e('分类', 'cosmautdl'); ?></label>
        <?php wp_dropdown_categories(array(
            'show_option_all' => esc_html__('全部分类', 'cosmautdl'),
            'hide_empty'      => 0,
            'name'            => 'cat',
            'selected'        => $cosmdl_cat_id,
        )); ?>
      </div>
      <div>
        <label><?php esc_html_e('标签', 'cosmautdl'); ?></label>
        <input type="text" name="tag" value="<?php echo esc_attr($cosmdl_tag); ?>" placeholder="<?php echo esc_attr__('标签 slug', 'cosmautdl'); ?>" />
      </div>
      <div>
        <label><?php esc_html_e('作者', 'cosmautdl'); ?></label>
        <select name="author">
          <option value="0"><?php esc_html_e('全部作者', 'cosmautdl'); ?></option>
          <?php
          $cosmdl_users = get_users(array(
              'fields' => array('ID','display_name'),
              'number' => 50,
              'orderby'=> 'ID',
              'order'  => 'DESC',
          ));
          foreach($cosmdl_users as $cosmdl_user){
              echo '<option value="' . intval($cosmdl_user->ID) . '" ' . selected($cosmdl_author, $cosmdl_user->ID, false) . '>' . esc_html($cosmdl_user->display_name) . '</option>';
          }
          ?>
        </select>
      </div>
      <div>
        <label><?php esc_html_e('关键字', 'cosmautdl'); ?></label>
        <input type="text" name="q" value="<?php echo esc_attr($cosmdl_qkw); ?>" placeholder="<?php echo esc_attr__('标题或资源名', 'cosmautdl'); ?>" />
      </div>
      <div>
        <label><?php esc_html_e('大小范围', 'cosmautdl'); ?></label>
        <input type="text" name="size_min" value="<?php echo esc_attr($cosmdl_size_min); ?>" placeholder="<?php echo esc_attr__('如 50M', 'cosmautdl'); ?>" class="cosmdl-tree-size-input" />
        -
        <input type="text" name="size_max" value="<?php echo esc_attr($cosmdl_size_max); ?>" placeholder="<?php echo esc_attr__('如 2G', 'cosmautdl'); ?>" class="cosmdl-tree-size-input" />
      </div>
      <div>
        <label><?php esc_html_e('单位', 'cosmautdl'); ?></label>
        <select name="unit">
          <option value="" <?php selected($cosmdl_unit_f, ''); ?>><?php esc_html_e('全部', 'cosmautdl'); ?></option>
          <option value="KB" <?php selected($cosmdl_unit_f, 'KB'); ?>>KB</option>
          <option value="MB" <?php selected($cosmdl_unit_f, 'MB'); ?>>MB</option>
          <option value="GB" <?php selected($cosmdl_unit_f, 'GB'); ?>>GB</option>
        </select>
      </div>
      <div>
        <label><?php esc_html_e('类型', 'cosmautdl'); ?></label>
        <select name="type">
          <option value=""><?php esc_html_e('全部', 'cosmautdl'); ?></option>
          <?php
          if (is_array($cosmdl_drive_management)) {
              foreach($cosmdl_drive_management as $cosmdl_drive_key => $cosmdl_drive) {
                  $cosmdl_val = isset($cosmdl_drive['alias']) && $cosmdl_drive['alias'] !== '' ? $cosmdl_drive['alias'] : $cosmdl_drive_key;
                  echo '<option value="' . esc_attr($cosmdl_val) . '" ' . selected($cosmdl_type_f, $cosmdl_val, false) . '>' . esc_html($cosmdl_drive['label']) . '</option>';
              }
          }
          ?>
        </select>
      </div>
      <div>
        <label><?php esc_html_e('排序', 'cosmautdl'); ?></label>
        <select name="sort">
          <option value="date" <?php selected($cosmdl_sort,'date'); ?>><?php esc_html_e('按时间', 'cosmautdl'); ?></option>
          <option value="title" <?php selected($cosmdl_sort,'title'); ?>><?php esc_html_e('按标题', 'cosmautdl'); ?></option>
          <option value="name" <?php selected($cosmdl_sort,'name'); ?>><?php esc_html_e('按资源名', 'cosmautdl'); ?></option>
          <option value="updated" <?php selected($cosmdl_sort,'updated'); ?>><?php esc_html_e('按更新', 'cosmautdl'); ?></option>
          <option value="author" <?php selected($cosmdl_sort,'author'); ?>><?php esc_html_e('按作者', 'cosmautdl'); ?></option>
          <option value="size" <?php selected($cosmdl_sort,'size'); ?>><?php esc_html_e('按大小', 'cosmautdl'); ?></option>
        </select>
        <select name="order">
          <option value="DESC" <?php selected($cosmdl_order,'DESC'); ?>><?php esc_html_e('倒序', 'cosmautdl'); ?></option>
          <option value="ASC" <?php selected($cosmdl_order,'ASC'); ?>><?php esc_html_e('正序', 'cosmautdl'); ?></option>
        </select>
      </div>
      <div>
        <label><?php esc_html_e('每页显示', 'cosmautdl'); ?></label>
        <select name="per">
          <option value="50" <?php selected($cosmdl_per,50); ?>>50</option>
          <option value="100" <?php selected($cosmdl_per,100); ?>>100</option>
          <option value="200" <?php selected($cosmdl_per,200); ?>>200</option>
        </select>
      </div>
      <div>
        <button class="pk-btn" type="submit"><?php esc_html_e('筛选', 'cosmautdl'); ?></button>
      </div>
    </form>
  </div>

  <div class="p-block mt15 cosmdl-tree-table-wrap">
    <table class="pk-table cosmdl-tree-table">
      <thead>
        <tr>
          <th class="cosmdl-col-title"><?php esc_html_e('文章标题', 'cosmautdl'); ?></th>
          <th class="cosmdl-col-name"><?php esc_html_e('资源名称', 'cosmautdl'); ?></th>
          <th class="cosmdl-col-size"><?php esc_html_e('大小', 'cosmautdl'); ?></th>
          <th class="cosmdl-col-updated"><?php esc_html_e('更新时间', 'cosmautdl'); ?></th>
          <th class="cosmdl-col-author"><?php esc_html_e('作者', 'cosmautdl'); ?></th>
          <th class="cosmdl-col-links"><?php esc_html_e('下载项', 'cosmautdl'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $cosmdl_allowed_drive_logo = array(
            'img' => array(
                'class'       => true,
                'src'         => true,
                'alt'         => true,
                'aria-hidden' => true,
                'width'       => true,
                'height'      => true,
            ),
        );

        if (!empty($cosmdl_page_post_ids)) {
            foreach ($cosmdl_page_post_ids as $cosmdl_pid) {
                $cosmdl_title = get_the_title($cosmdl_pid);
                $cosmdl_post_author_id = get_post_field('post_author', $cosmdl_pid);
                $cosmdl_default_author_display = get_the_author_meta('display_name', $cosmdl_post_author_id);

                // 遍历文章的所有附件 (1-6)
                for ($cosmdl_attach = 1; $cosmdl_attach <= 6; $cosmdl_attach++) {
                    // 1. 检查该附件是否存在任何下载链接
                    if (!cosmdl_attach_has_any_link($cosmdl_pid, $cosmdl_attach, $cosmdl_drive_management)) continue;
                    
                    // 2. 类型筛选 (如果指定了 type)
                    if ($cosmdl_type_f !== '' && !cosmdl_attach_has_type($cosmdl_pid, $cosmdl_type_f, $cosmdl_attach, $cosmdl_drive_management)) continue;

                    // 获取元数据 key
                    $cosmdl_prefix = ($cosmdl_attach === 1) ? 'cosmdl_' : ('cosmdl' . $cosmdl_attach . '_');
                    
                    // 获取元数据
                    $cosmdl_name = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'name', true);
                    $cosmdl_size = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size', true);
                    $cosmdl_size_unit = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size_unit', true);
                    $cosmdl_size_bytes = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'size_bytes', true);
                    $cosmdl_date = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'date', true);
                    $cosmdl_author_display = get_post_meta($cosmdl_pid, $cosmdl_prefix . 'author', true);
                    
                    // 兜底逻辑
                    if (!$cosmdl_size_bytes && $cosmdl_size) { 
                        $cosmdl_size_bytes = cosmdl_calc_bytes($cosmdl_size, $cosmdl_size_unit); 
                    }
                    if (!$cosmdl_author_display) { 
                        $cosmdl_author_display = $cosmdl_default_author_display; 
                    }

                    // 3. 附件级别的筛选 (确保只显示符合条件的附件)
                    
                    // 单位筛选
                    if ($cosmdl_unit_f !== '') {
                        $cosmdl_unit_current = strtoupper(trim($cosmdl_size_unit));
                        if ($cosmdl_unit_current !== $cosmdl_unit_f) continue;
                    }
                    
                    // 大小范围筛选
                    if ($cosmdl_min_bytes > 0 && intval($cosmdl_size_bytes) < intval($cosmdl_min_bytes)) continue;
                    if ($cosmdl_max_bytes > 0 && intval($cosmdl_size_bytes) > intval($cosmdl_max_bytes)) continue;

                    // 生成下载链接
                    $cosmdl_dl = add_query_arg('attach', $cosmdl_attach, cosmdl_route_url('download', $cosmdl_pid));
                    ?>
                    <tr>
                        <td class="cosmdl-col-title">
                            <a href="<?php echo esc_url($cosmdl_dl); ?>"<?php echo ($cosmdl_tree_open_links_in_new_window === 'yes') ? ' target="_blank"' : ''; ?>>
                                <?php echo esc_html($cosmdl_title); ?>
                            </a>
                        </td>
                        <td class="cosmdl-col-name">
                            <?php if ($cosmdl_name) { ?>
                                <a href="<?php echo esc_url($cosmdl_dl); ?>"<?php echo ($cosmdl_tree_open_links_in_new_window === 'yes') ? ' target="_blank"' : ''; ?>>
                                    <?php echo esc_html($cosmdl_name); ?>
                                </a>
                            <?php } else { echo '-'; } ?>
                        </td>
                        <td class="cosmdl-col-size">
                            <?php 
                            if ($cosmdl_size_bytes) {
                                echo esc_html(size_format($cosmdl_size_bytes, 2));
                            } elseif ($cosmdl_size) {
                                // 兼容旧数据：有 size 但无 bytes
                                echo esc_html($cosmdl_size . ($cosmdl_size_unit ? ' ' . $cosmdl_size_unit : ''));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="cosmdl-col-updated">
                            <?php echo esc_html($cosmdl_date ? $cosmdl_date : get_the_date('Y-m-d', $cosmdl_pid)); ?>
                        </td>
                        <td class="cosmdl-col-author">
                            <?php echo esc_html($cosmdl_author_display); ?>
                        </td>
                        <td class="cosmdl-col-links">
                            <?php
                            // 显示该附件可用的下载网盘图标
                            foreach($cosmdl_drive_management as $cosmdl_drive_key => $cosmdl_drive){
                                $cosmdl_is_custom = (isset($cosmdl_drive['is_custom']) && $cosmdl_drive['is_custom'] === 'yes');
                                $cosmdl_fields = cosmdl_get_field_names_for_drive($cosmdl_drive_key, $cosmdl_attach, $cosmdl_is_custom);
                                $cosmdl_url_val = get_post_meta($cosmdl_pid, $cosmdl_fields['url'], true);
                                
                                if ($cosmdl_url_val) {
                                    $cosmdl_alias = (isset($cosmdl_drive['alias']) && $cosmdl_drive['alias'] !== '') ? $cosmdl_drive['alias'] : $cosmdl_drive_key;
                                    // 20251206: 网盘 LOGO 可点击跳转
                                    echo '<a href="' . esc_url($cosmdl_url_val) . '" target="_blank" rel="noopener noreferrer" class="cosmdl-tree-drive-link">';
                                    echo wp_kses(cosmdl_drive_logo_html($cosmdl_alias, $cosmdl_drive_key), $cosmdl_allowed_drive_logo);
                                    echo '</a>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            }
        } else {
            echo '<tr><td colspan="6" class="cosmdl-tree-empty">' . esc_html__('暂无数据', 'cosmautdl') . '</td></tr>';
        }
        wp_reset_postdata();
        ?>
      </tbody>
    </table>
  </div>
  
  <!-- 分页 -->
  <div class="cosmdl-pagination mt15">
    <?php
    $cosmdl_page_links = paginate_links(array(
      'base' => add_query_arg('paged', '%#%'),
      'format' => '',
      'prev_text' => '&laquo;',
      'next_text' => '&raquo;',
      'total' => $cosmdl_total_pages,
      'current' => $cosmdl_paged
    ));

    if (is_string($cosmdl_page_links) && $cosmdl_page_links !== '') {
        echo wp_kses_post($cosmdl_page_links);
    }
    ?>
  </div>
</div>

<?php get_footer(); ?>
