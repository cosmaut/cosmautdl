# CosmautDL

专为 WordPress 打造的多网盘下载管理插件

[![License](https://img.shields.io/badge/License-GPL%20v3.0-blue.svg)](LICENSE)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-8892bf.svg)](https://www.php.net/)

## 简介

CosmautDL 是一款功能强大的 WordPress 下载管理插件，支持多网盘集成、智能下载卡片生成、扫码解锁等功能。本插件专为提升网站下载体验而设计，让下载更简单，让管理更高效。

## 主要特性

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #e3f2fd; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="width: 28px; height: 28px; color: #1976d2;"><path d="M214.6 221.3c-3.9-3.9-10.2-3.9-14.1 0l-72 72c-3.9 3.9-3.9 10.2 0 14.1l72 72c3.9 3.9 10.2 3.9 14.1 0l72-72c3.9-3.9 3.9-10.2 0-14.1l-72-72zM192 448c-88.4 0-160-71.6-160-160V96C32 43 75 0 128 0H256c53 0 96 43 96 96v192c0 88.4-71.6 160-160 160zm16-160c0 70.7-57.3 128-128 128s-128-57.3-128-128V96C16 51.8 51.8 16 96 16H256c44.2 0 80 35.8 80 80v192z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">多网盘支持</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">支持多种主流网盘平台，统一管理入口，一处修改全局生效，告别重复编辑。</p>
</div>

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #e8f5e9; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 28px; height: 28px; color: #388e3c;"><path d="M64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64h384c35.3 0 64-28.7 64-64V256H352c-35.3 0-64-28.7-64-64V32H64zM384 256h128v160c0 17.7-14.3 32-32 32H64c-17.7 0-32-14.3-32-32V96c0-17.7 14.3-32 32-32h224v192c0 17.7 14.3 32 32 32zM272 96h64v64h64V96h64v128H272V96z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">优雅卡片</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">提供结构清晰的下载卡片与独立下载页，视觉体验高度一致。</p>
</div>

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #fff3e0; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 28px; height: 28px; color: #f57c00;"><path d="M32 32C14.3 32 0 46.3 0 64s14.3 32 32 32H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H32zM80 128c-26.5 0-48 21.5-48 48v64c0 26.5 21.5 48 48 48h80c26.5 0 48-21.5 48-48V176c0-26.5-21.5-48-48-48H80zm-8 208c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H88c-8.8 0-16-7.2-16-16V336zm0 96c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H88c-8.8 0-16-7.2-16-16V432zM248 256c-26.5 0-48 21.5-48 48v64c0 26.5 21.5 48 48 48h80c26.5 0 48-21.5 48-48V304c0-26.5-21.5-48-48-48H248zm-8 208c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H256c-8.8 0-16-7.2-16-16V432zm0-96c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H256c-8.8 0-16-7.2-16-16V336z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">扫码解锁</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">支持二维码扫码验证，兼顾用户引导与防刷机制。</p>
</div>

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #e0f2f1; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" style="width: 28px; height: 28px; color: #00796b;"><path d="M272 0H48C21.5 0 0 21.5 0 48V320c0 26.5 21.5 48 48 48h80l0 32c0 17.7 14.3 32 32 32s32-14.3 32-32l0-32 80 0c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zm0 320H48V48h224v272z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">响应式设计</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">完美适配移动设备，移动端也能舒适阅读。</p>
</div>

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #ede7f6; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" style="width: 28px; height: 28px; color: #673ab7;"><path d="M384 32H112C50.2 32 0 82.2 0 144L0 448c0 35.3 28.7 64 64 64l368 0c35.3 0 64-28.7 64-64V144c0-61.8-50.2-112-112-112zM338 346.3c0 12.4-10.1 22.5-22.5 22.5h-39.1c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM168 346.3c0 12.4-10.1 22.5-22.5 22.5H106.4c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM338 282.3c0 12.4-10.1 22.5-22.5 22.5h-39.1c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM168 282.3c0 12.4-10.1 22.5-22.5 22.5H106.4c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM338 218.3c0 12.4-10.1 22.5-22.5 22.5h-39.1c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM168 218.3c0 12.4-10.1 22.5-22.5 22.5H106.4c-12.4 0-22.5-10.1-22.5-22.5l0-7.5c0-12.4 10.1-22.5 22.5-22.5h39.1c12.4 0 22.5 10.1 22.5 22.5l0 7.5zM448 320c0-8.8-7.2-16-16-16l-192 0c-8.8 0-16 7.2-16 16s7.2 16 16 16l192 0c8.8 0 16-7.2 16-16z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">国际化支持</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">支持多语言界面，满足不同语言环境需求。</p>
</div>

<div style="background-color: #f8f9fa; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;">
  <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #ffebee; border-radius: 10px; margin-bottom: 15px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 28px; height: 28px; color: #d32f2f;"><path d="M288 32c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 32 48 0c26.5 0 48 21.5 48 48s-21.5 48-48 48l-80 0c-8.8 0-16 7.2-16 16s7.2 16 16 16l80 0c44.2 0 80 35.8 80 80l0 64c0 8.8 7.2 16 16 16s16-7.2 16-16l0-64c0-70.7-57.3-128-128-128l-80 0c-26.5 0-48-21.5-48-48s21.5-48 48-48l48 0L256 32zm-96 288l0 128c0 53 43 96 96 96s96-43 96-96V320c0-8.8 7.2-16 16-16s16 7.2 16 16v128c0 88.4-71.6 160-160 160s-160-71.6-160-160V320c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
  </div>
  <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50;">性能优化</h3>
  <p style="margin: 0; color: #6c757d; line-height: 1.5;">高效的下载处理机制，不拖慢网站速度。</p>
</div>

</div>

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
| 插件主页 | [CosmautDL - 星海博客](https://cosmaut.com/cosmautdl/) |
| 使用文档 | [CosmautDL 使用文档](https://cosmaut.com/cosmautdl/docs/) |
| 常见问题 | [CosmautDL FAQ](https://cosmaut.com/cosmautdl/faq/) |
| BUG 反馈 | [CosmautDL BUG 反馈](https://cosmaut.com/cosmautdl/feedback/) |
| 交流群 | [CosmautDL QQ交流群](https://cosmaut.com/cosmautdl/group/) |
| 赞助我们 | [支持 CosmautDL](https://cosmaut.com/cosmautdl/sponsor/) |

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
