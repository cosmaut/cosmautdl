# CosmautDL

专为 WordPress 打造的多网盘下载管理插件：把零散网盘链接统一整理成「下载卡片」，并提供独立下载页、可选扫码解锁、文件树索引与下载点击统计，让资源分享更规范、更好用。

[![License](https://img.shields.io/badge/License-GPL%20v3.0-blue.svg)](LICENSE)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-8892bf.svg)](https://www.php.net/)

## 快速入口

| 🏠 [插件主页](https://cosmaut.com/cosmautdl/) | 📖 [使用文档](https://cosmaut.com/cosmautdl/docs/) | ❓ [常见问题](https://cosmaut.com/cosmautdl/faq/) |
|:---:|:---:|:---:|
| 🐞 [BUG反馈](https://cosmaut.com/cosmautdl/feedback/) | 💬 [交流群](https://cosmaut.com/cosmautdl/group/) | ❤️ [赞助我们](https://cosmaut.com/cosmautdl/sponsor/) |
| ⭐ [GitHub](https://github.com/cosmaut/cosmautdl) | 🧩 [WordPress 插件目录（搜索）](https://cn.wordpress.org/plugins/search/cosmautdl) | ⬇️ [插件下载页](https://cosmaut.com/cosmautdl-plugin.html) |

## 适用场景

- 资源分享站 / 软件下载站：统一网盘入口、减少用户跳转成本
- 教程博客 / 课程站：每篇文章一个下载卡片 + 独立下载页，更清晰
- 团队资料库：用文件树页汇总全站资源，便于检索

## 核心能力（一句话版）

- 多网盘下载管理：在后台集中配置，在文章里统一展示
- 下载卡片 UI：在文章/页面中自动生成一致的下载卡片
- 独立下载页：每个资源对应独立下载页面，支持固定链接与普通链接
- 扫码解锁（可选）：可用于防刷、引导关注与验证流程
- 文件树索引：提供全站资源索引页，便于用户浏览查找
- 下载点击统计：记录点击并提供统计入口（需权限）

## 支持网盘（部分）

<p>
  <img src="images/baidu.png" width="26" alt="百度网盘" />
  <img src="images/ali.png" width="26" alt="阿里云盘" />
  <img src="images/quark.png" width="26" alt="夸克网盘" />
  <img src="images/xunlei.png" width="26" alt="迅雷" />
  <img src="images/onedrive.png" width="26" alt="OneDrive" />
  <img src="images/googledrive.png" width="26" alt="Google Drive" />
  <img src="images/dropbox.png" width="26" alt="Dropbox" />
  <img src="images/mega.png" width="26" alt="MEGA" />
</p>

## 系统要求

- **WordPress**：5.0 或更高版本
- **PHP**：7.4 或更高版本
- **固定链接**：建议开启（避免下载页/跳转页路由异常）
- **服务器权限**：需具备安装插件与写入缓存的权限

## 安装与启用（3 步）

1. 后台 → 插件 → 安装插件 → 上传插件（zip）→ 启用
2. 后台 → 设置 → CosmautDL：完成网盘类型、跳转路由前缀、样式等基础配置
3. 编辑文章/页面：在编辑页底部的 CosmautDL 下载面板中填写网盘链接与提取码，发布即可

## 页面路由（便于站长理解与排错）

- 独立下载页：`/downloads/{post_id}.html`
- 文件树页：`/downloads/tree.html`
- 统计页：`/downloads/stats.html`（前台路由会做权限检查，并跳转到后台统计页）
- 跳转路由：`/{prefix}/{post_id}/{type}.html`（其中 `prefix` 可在后台配置，默认用于跳转中转）

如果你的站点未开启固定链接，以上地址会自动退化为带参数的形式（例如 `?cosmdl_download=1&post_id=123`）。

## 常见问题（快速自查）

- 访问下载页提示 404：后台 → 设置 → 固定链接，直接点一次「保存更改」刷新路由规则
- 下载按钮不显示：确认文章里已填写至少一个网盘链接；如有缓存插件请清理缓存
- 统计页打不开：需要登录且具备后台权限（用于防止敏感统计数据被公开）

## 隐私与外部服务说明

- 插件核心功能（下载卡片 / 独立下载页 / 文件树 / 统计）不依赖任何外部服务
- 仅当你在后台启用并配置「微信扫码解锁」模式时，才会与微信相关接口交互（用于 OAuth 与订阅状态验证）

## 关键词（方便搜索引擎与 AI 更准确理解）

WordPress 下载管理插件、多网盘下载、网盘链接管理、下载卡片、独立下载页、下载跳转中转、扫码解锁、下载统计、文件树索引、资源分享站、软件下载站、教程下载

## 许可证

本插件遵循 [GNU General Public License v3.0](LICENSE) 开源协议。

---

&copy; 2024 星海博客. All rights reserved .
