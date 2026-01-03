# CosmautDL

专为 WordPress 打造的多网盘下载管理插件

[![License](https://img.shields.io/badge/License-GPL%20v3.0-blue.svg)](LICENSE)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-8892bf.svg)](https://www.php.net/)

## 简介

CosmautDL 是一款功能强大的 WordPress 下载管理插件，支持多网盘集成、智能下载卡片生成、扫码解锁等功能。本插件专为提升网站下载体验而设计，让下载更简单，让管理更高效。

**支持网盘（部分）**

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

## 快速入口

| 🏠 [插件主页](https://cosmaut.com/cosmautdl/) | 📖 [使用文档](https://cosmaut.com/cosmautdl/docs/) | ❓ [常见问题](https://cosmaut.com/cosmautdl/faq/) |
|---|---|---|
| 🐞 [BUG反馈](https://cosmaut.com/cosmautdl/feedback/) | 💬 [交流群](https://cosmaut.com/cosmautdl/group/) | ❤️ [赞助我们](https://cosmaut.com/cosmautdl/sponsor/) |

## 主要特性

| 特性 | 说明 |
|---|---|
| ☁️ 多网盘支持 | 支持多种主流网盘平台，统一管理入口，一处修改全局生效，告别重复编辑。 |
| 🎴 优雅卡片 | 提供结构清晰的下载卡片与独立下载页，视觉体验高度一致。 |
| 🔓 扫码解锁 | 支持二维码扫码验证，兼顾用户引导与防刷机制。 |
| 📱 响应式设计 | 完美适配移动设备，移动端也能舒适阅读。 |
| 🌐 国际化支持 | 支持多语言界面，满足不同语言环境需求。 |
| ⚡ 性能优化 | 高效的下载处理机制，不拖慢网站速度。 |

## 系统要求

- **WordPress**：5.0 或更高版本
- **PHP**：7.4 或更高版本
- **固定链接**：建议开启，避免下载页路由异常
- **服务器权限**：需具备安装插件与写入缓存的服务器权限

## 安装与启用

在 WordPress 后台搜索 "CosmautDL" 或上传 zip 包安装并激活。

## 快速上手

### 基础配置

进入 CosmautDL 设置，配置下载页路由前缀（推荐开启固定链接）。

### 发布资源

在文章编辑页底部找到 "CosmautDL 下载设置"，填入网盘链接与提取码。

### 前台展示

文章页会自动插入下载卡片，点击即可进入独立下载页。

## 创建下载链接

### 方法一：使用"添加附件"

1. 进入文章/页面编辑页面
2. 在页面底部找到 "添加附件" 面板，填写网盘链接/提取码等信息
3. 保存/发布后，前台会自动展示下载卡片

> **提示**：若未看到该面板，请先在右上角"显示选项"勾选。

### 方法二：短代码（可选）

在正文中插入短代码（示例）：

```
[cosmautdl url="https://example.com/file.zip" name="文件名称" type="zip" size="10MB"]
```

> **提示**：具体参数以插件版本实现为准。

## 进阶功能

### 扫码解锁配置

在设置中开启扫码解锁，支持静态二维码或对接公众号接口。

### 文件树页面

开启后访问 `/downloads/tree.html` 可展示全站资源索引。

### 自定义样式

支持通过 CSS 变量或自定义 CSS 覆盖默认下载卡片样式。

```css
:root {
  --cosmdl-primary: #ff0000;
}
```

### 下载统计

访问 CosmautDL > 统计数据，查看下载次数、热门文件等信息。

## 常见问题

### 下载按钮不显示怎么办？

1. 确认文章编辑页底部的"启用下载"开关已打开
2. 检查是否有缓存插件（如 WP Rocket），尝试清理缓存
3. 确认已填入至少一个有效的网盘链接

### 点击下载报 404 错误？

这是最常见的问题。请前往 **设置 > 固定链接**，无需修改任何内容，直接点击"保存更改"按钮即可刷新路由规则。

### 扫码解锁功能如何测试？

建议先开启"静态二维码"模式，上传一张图片作为二维码。前台访问下载页，模拟用户扫码后的输入口令操作。确认流程通畅后，再对接公众号接口。

### 可以修改下载页的样式吗？

可以。插件基于 CSS 变量构建，你可以在 **外观 > 自定义 > 额外 CSS** 中覆盖默认变量。

### 支持哪些文件格式？

支持所有常见文件格式，文件格式不影响插件功能。

### 如何禁用扫码解锁？

在下载卡片设置中关闭"启用扫码解锁"选项即可。

### 下载统计不准确怎么办？

检查统计代码是否正确嵌入，或者是否存在缓存插件导致的统计失效。建议尝试清理浏览器缓存后重试。

### 插件与主题冲突怎么办？

尝试在插件设置中调整 CSS 加载方式，或检查是否有 JS 报错。如果问题持续，请联系技术支持。

### 移动端显示异常？

插件已针对移动设备优化。如有问题，请检查主题是否做了特殊样式覆盖，或在反馈群中提交截图。

### 支持批量导入链接吗？

目前暂不支持，后续会支持 CSV 格式批量导入。

## 文档与支持

| 内容 | 链接 |
|------|------|
| 插件主页 | [插件主页](https://cosmaut.com/cosmautdl/) |
| 使用文档 | [使用文档](https://cosmaut.com/cosmautdl/docs/) |
| 常见问题 | [常见问题](https://cosmaut.com/cosmautdl/faq/) |
| BUG反馈 | [BUG反馈](https://cosmaut.com/cosmautdl/feedback/) |
| 交流群 | [交流群](https://cosmaut.com/cosmautdl/group/) |
| 赞助我们 | [赞助我们](https://cosmaut.com/cosmautdl/sponsor/) |

## 交流群

QQ 交流群：**991285132**

扫码加入，进群请备注"CosmautDL"

### 群组规则

- **友好交流**：保持礼貌，互相尊重。禁止发布广告、灰产等违规内容。
- **高效提问**：提问前请先查阅文档和 FAQ。提问时请附带截图和环境信息。
- **互助分享**：鼓励分享使用心得和配置技巧，帮助新人快速上手。

## 赞助支持

开源不易，用爱发电。如果 CosmautDL 帮助到了你，欢迎请作者喝杯咖啡。

### 赞赏资金用途

- **服务器开支**：维持演示站、文档站的正常运行
- **咖啡续命**：支持作者熬夜写代码，修复 Bug
- **新功能研发**：加速扫码支付、会员系统等高级功能的开发

## 版本历史

查看 [Changelog](readme.txt) 了解版本更新历史。

## 许可证

本插件遵循 [GNU General Public License v3.0](LICENSE) 开源协议。

---

&copy; 2024 星海博客. All rights reserved.
