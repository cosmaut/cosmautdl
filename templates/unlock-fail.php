<?php
/**
 * 授权失败提示页：用于微信公众号关注解锁失败的提示页面
 */
if (!defined('ABSPATH')) { exit; }

get_header();
$cosmdl_text = isset($GLOBALS['cosmdl_follow_text']) ? $GLOBALS['cosmdl_follow_text'] : esc_html__('请在微信内关注指定公众号后重试', 'cosmautdl');
?>
<div class="container puock-text">
  <div class="p-block">
    <h1 class="t-lg"><?php echo esc_html__('未完成关注', 'cosmautdl'); ?></h1>
    <p style="font-size:16px;line-height:1.8;"><?php echo esc_html($cosmdl_text); ?></p>
    <p class="mt10"><a class="pk-btn" href="javascript:history.back();"><?php echo esc_html__('返回上一页', 'cosmautdl'); ?></a></p>
  </div>
</div>
<?php get_footer(); ?>
