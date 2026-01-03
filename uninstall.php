<?php
/**
 * CosmautDL 卸载清理脚本
 *
 * 中文说明：当用户在后台“删除插件”时，WordPress 会执行本文件。
 * 这里清理插件产生的选项与统计表（如有），避免遗留数据。
 */

// 防止直接访问
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 删除插件主要配置
delete_option('cosmdl_options');
delete_option('cosmdl_force_flush_once');
delete_option('cosmdl_sidebar_initialized');

// 中文说明：卸载时默认仅移除插件配置。
// 统计表是否删除属于“是否保留历史数据”的产品策略，避免误删用户数据，这里不做强制删除。

