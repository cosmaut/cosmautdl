<?php
/**
 * 下载统计页面：展示点击详情与用户信息（简版，注释微调）
 * 路由：/downloads/stats.html，仅管理员可见
 */
if (!defined('ABSPATH')) { exit; }

if (!current_user_can('manage_options')){ wp_die(esc_html__('需要管理员登录查看统计', 'cosmautdl')); }

get_header();
global $wpdb;
$cosmdl_stats_cache_key = 'cosmdl_stats_recent_rows_100';
$cosmdl_rows = wp_cache_get($cosmdl_stats_cache_key, 'cosmautdl');
if ($cosmdl_rows === false) {
	$cosmdl_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cosmdl_clicks ORDER BY id DESC LIMIT %d",
			100
		)
	);
	wp_cache_set($cosmdl_stats_cache_key, $cosmdl_rows, 'cosmautdl', 60);
}
?>
<div class="container puock-text">
  <h1 class="t-lg"><?php echo esc_html__('下载统计（最近100条）', 'cosmautdl'); ?></h1>

  <div class="p-block mt15">
    <table class="pk-table" style="width:100%">
      <thead>
        <tr>
          <th><?php echo esc_html__('时间', 'cosmautdl'); ?></th>
          <th><?php echo esc_html__('文章', 'cosmautdl'); ?></th>
          <th><?php echo esc_html__('类型', 'cosmautdl'); ?></th>
          <th><?php echo esc_html__('用户', 'cosmautdl'); ?></th>
          <th>IP</th>
          <th><?php echo esc_html__('来源', 'cosmautdl'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($cosmdl_rows): foreach($cosmdl_rows as $cosmdl_row):
          $cosmdl_post_id = intval($cosmdl_row->post_id);
          $cosmdl_title = get_the_title($cosmdl_post_id);
          $cosmdl_user_str = esc_html__('游客', 'cosmautdl');
          if (!empty($cosmdl_row->user_id)){
            $cosmdl_user = get_userdata(intval($cosmdl_row->user_id));
            if ($cosmdl_user){
              $cosmdl_user_str = $cosmdl_user->display_name . ' (#' . intval($cosmdl_row->user_id) . ')';
            } else {
              $cosmdl_user_str = esc_html__('用户#', 'cosmautdl') . intval($cosmdl_row->user_id);
            }
          }

          $cosmdl_dl_url = cosmdl_route_url('download', $cosmdl_post_id);
        ?>
        <tr>
          <td><?php 
    $cosmdl_created_at = $cosmdl_row->created_at;
    echo esc_html(get_date_from_gmt($cosmdl_created_at, 'Y-m-d H:i:s'));
?>
</td>
          <td><a href="<?php echo esc_url($cosmdl_dl_url); ?>" target="_blank"><?php echo esc_html($cosmdl_title ? $cosmdl_title : ('#'.intval($cosmdl_post_id))); ?></a></td>
          <td><?php echo esc_html($cosmdl_row->type); ?></td>
          <td><?php echo esc_html($cosmdl_user_str); ?></td>
          <td><?php echo esc_html($cosmdl_row->ip); ?></td>
          <td><?php echo esc_html($cosmdl_row->referer); ?></td>
        </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6"><?php echo esc_html__('暂无点击数据', 'cosmautdl'); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="puock-text mt10" style="text-align:center;color:var(--pk-c-9)"><?php echo esc_html__('如需更高级筛选和导出，可在后续版本扩展', 'cosmautdl'); ?></div>
</div>
<?php get_footer(); ?>
