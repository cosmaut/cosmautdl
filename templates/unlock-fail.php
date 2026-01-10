<?php
/**
 * 授权失败提示页：用于微信公众号关注解锁失败的自定义页面
 */
if (!defined('ABSPATH')) { exit; }

get_header();
$cosmdl_text = isset($GLOBALS['cosmdl_follow_text']) ? $GLOBALS['cosmdl_follow_text'] : esc_html__('请在微信内关注指定公众号后重试', 'cosmautdl');
$cosmdl_back_url = wp_get_referer();
if (!$cosmdl_back_url) {
  $cosmdl_back_url = home_url('/');
}
?>
<div class="container puock-text">
  <div class="p-block">
    <h1 class="t-lg"><?php echo esc_html__('未完成关注', 'cosmautdl'); ?></h1>
    <p><?php echo esc_html($cosmdl_text); ?></p>
    <p class="mt10"><a class="pk-btn" href="<?php echo esc_url($cosmdl_back_url); ?>"><?php echo esc_html__('返回上一页', 'cosmautdl'); ?></a></p>
  </div>
</div>
<?php get_footer(); ?>
