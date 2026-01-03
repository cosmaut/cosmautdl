# CosmautDL

专为 WordPress 打造的多网盘下载管理插件

[![License](https://img.shields.io/badge/License-GPL%20v3.0-blue.svg)](LICENSE)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-8892bf.svg)](https://www.php.net/)

## 简介

CosmautDL 是一款功能强大的 WordPress 下载管理插件，支持多网盘集成、智能下载卡片生成、扫码解锁等功能。本插件专为提升网站下载体验而设计，让下载更简单，让管理更高效。

## 快速入口

<p>
  <a href="https://cosmaut.com/cosmautdl/" title="插件主页" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M277.8 8.6c-12.3-11.4-31.3-11.4-43.5 0l-224 208c-9.6 9-12.8 22.9-8 35.1S18.8 272 32 272l16 0 0 176c0 35.3 28.7 64 64 64l288 0c35.3 0 64-28.7 64-64l0-176 16 0c13.2 0 25-8.1 29.8-20.3s1.6-26.2-8-35.1l-224-208zM240 320l32 0c26.5 0 48 21.5 48 48l0 96-128 0 0-96c0-26.5 21.5-48 48-48z"/></svg>
      插件主页
    </span>
  </a>
  <a href="https://cosmaut.com/cosmautdl/docs/" title="使用文档" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M256 141.3l0 309.3 .5-.2C311.1 427.7 369.7 416 428.8 416l19.2 0 0-320-19.2 0c-42.2 0-84.1 8.4-123.1 24.6-16.8 7-33.4 13.9-49.7 20.7zM230.9 61.5L256 72 281.1 61.5C327.9 42 378.1 32 428.8 32L464 32c26.5 0 48 21.5 48 48l0 352c0 26.5-21.5 48-48 48l-35.2 0c-50.7 0-100.9 10-147.7 29.5l-12.8 5.3c-7.9 3.3-16.7 3.3-24.6 0l-12.8-5.3C184.1 490 133.9 480 83.2 480L48 480c-26.5 0-48-21.5-48-48L0 80C0 53.5 21.5 32 48 32l35.2 0c50.7 0 100.9 10 147.7 29.5z"/></svg>
      使用文档
    </span>
  </a>
  <a href="https://cosmaut.com/cosmautdl/faq/" title="常见问题" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
      常见问题
    </span>
  </a>
  <a href="https://cosmaut.com/cosmautdl/feedback/" title="BUG反馈" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M192 96c0-53 43-96 96-96s96 43 96 96l0 3.6c0 15.7-12.7 28.4-28.4 28.4l-135.1 0c-15.7 0-28.4-12.7-28.4-28.4l0-3.6zm345.6 12.8c10.6 14.1 7.7 34.2-6.4 44.8l-97.8 73.3c5.3 8.9 9.3 18.7 11.8 29.1l98.8 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-96 0 0 32c0 2.6-.1 5.3-.2 7.9l83.4 62.5c14.1 10.6 17 30.7 6.4 44.8s-30.7 17-44.8 6.4l-63.1-47.3c-23.2 44.2-66.5 76.2-117.7 83.9L312 280c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 230.2c-51.2-7.7-94.5-39.7-117.7-83.9L83.2 473.6c-14.1 10.6-34.2 7.7-44.8-6.4s-7.7-34.2 6.4-44.8l83.4-62.5c-.1-2.6-.2-5.2-.2-7.9l0-32-96 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l98.8 0c2.5-10.4 6.5-20.2 11.8-29.1L44.8 153.6c-14.1-10.6-17-30.7-6.4-44.8s30.7-17 44.8-6.4L192 184c12.3-5.1 25.8-8 40-8l112 0c14.2 0 27.7 2.8 40 8l108.8-81.6c14.1-10.6 34.2-7.7 44.8 6.4z"/></svg>
      BUG反馈
    </span>
  </a>
  <a href="https://cosmaut.com/cosmautdl/group/" title="交流群" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M64 128a112 112 0 1 1 224 0 112 112 0 1 1 -224 0zM0 464c0-97.2 78.8-176 176-176s176 78.8 176 176l0 6c0 23.2-18.8 42-42 42L42 512c-23.2 0-42-18.8-42-42l0-6zM432 64a96 96 0 1 1 0 192 96 96 0 1 1 0-192zm0 240c79.5 0 144 64.5 144 144l0 22.4c0 23-18.6 41.6-41.6 41.6l-144.8 0c6.6-12.5 10.4-26.8 10.4-42l0-6c0-51.5-17.4-98.9-46.5-136.7 22.6-14.7 49.6-23.3 78.5-23.3z"/></svg>
      交流群
    </span>
  </a>
  <a href="https://cosmaut.com/cosmautdl/sponsor/" title="赞助我们" style="display: inline-block; padding: 10px 14px; margin: 0 10px 10px 0; border: 1px solid #d0d7de; border-radius: 999px; text-decoration: none; background: #f6f8fa; color: #24292f;">
    <span style="display: inline-flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 16px; height: 16px;"><path fill="currentColor" d="M241 87.1l15 20.7 15-20.7C296 52.5 336.2 32 378.9 32 452.4 32 512 91.6 512 165.1l0 2.6c0 112.2-139.9 242.5-212.9 298.2-12.4 9.4-27.6 14.1-43.1 14.1s-30.8-4.6-43.1-14.1C139.9 410.2 0 279.9 0 167.7l0-2.6C0 91.6 59.6 32 133.1 32 175.8 32 216 52.5 241 87.1z"/></svg>
      赞助我们
    </span>
  </a>
</p>

## 主要特性

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-top: 18px;">
  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #e3f2fd; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 28px; height: 28px; color: #1976d2;"><path fill="currentColor" d="M0 336c0 79.5 64.5 144 144 144l304 0c70.7 0 128-57.3 128-128 0-51.6-30.5-96.1-74.5-116.3 6.7-13.1 10.5-28 10.5-43.7 0-53-43-96-96-96-17.7 0-34.2 4.8-48.4 13.1-24.1-45.8-72.2-77.1-127.6-77.1-79.5 0-144 64.5-144 144 0 8 .7 15.9 1.9 23.5-56.9 19.2-97.9 73.1-97.9 136.5z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">多网盘支持</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">支持多种主流网盘平台，统一管理入口，一处修改全局生效，告别重复编辑。</p>
  </div>

  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #e8f5e9; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="width: 28px; height: 28px; color: #2e7d32;"><path fill="currentColor" d="M0 64C0 28.7 28.7 0 64 0L213.5 0c17 0 33.3 6.7 45.3 18.7L365.3 125.3c12 12 18.7 28.3 18.7 45.3L384 448c0 35.3-28.7 64-64 64L64 512c-35.3 0-64-28.7-64-64L0 64zm208-5.5l0 93.5c0 13.3 10.7 24 24 24L325.5 176 208 58.5zM120 256c-13.3 0-24 10.7-24 24s10.7 24 24 24l144 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-144 0zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24l144 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-144 0z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">优雅卡片</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">提供结构清晰的下载卡片与独立下载页，视觉体验高度一致。</p>
  </div>

  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #fff3e0; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 28px; height: 28px; color: #f57c00;"><path fill="currentColor" d="M64 160l64 0 0-64-64 0 0 64zM0 80C0 53.5 21.5 32 48 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48L0 80zM64 416l64 0 0-64-64 0 0 64zM0 336c0-26.5 21.5-48 48-48l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96zM320 96l0 64 64 0 0-64-64 0zM304 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96c0-26.5 21.5-48 48-48zM288 352a32 32 0 1 1 0-64 32 32 0 1 1 0 64zm0 64c17.7 0 32 14.3 32 32s-14.3 32-32 32-32-14.3-32-32 14.3-32 32-32zm96 32c0-17.7 14.3-32 32-32s32 14.3 32 32-14.3 32-32 32-32-14.3-32-32zm32-96a32 32 0 1 1 0-64 32 32 0 1 1 0 64zm-32 32a32 32 0 1 1 -64 0 32 32 0 1 1 64 0z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">扫码解锁</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">支持二维码扫码验证，兼顾用户引导与防刷机制。</p>
  </div>

  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #e0f2f1; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="width: 28px; height: 28px; color: #00796b;"><path fill="currentColor" d="M16 64C16 28.7 44.7 0 80 0L304 0c35.3 0 64 28.7 64 64l0 384c0 35.3-28.7 64-64 64L80 512c-35.3 0-64-28.7-64-64L16 64zM128 440c0 13.3 10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-80 0c-13.3 0-24 10.7-24 24zM304 64l-224 0 0 304 224 0 0-304z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">响应式设计</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">完美适配移动设备，移动端也能舒适阅读。</p>
  </div>

  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #ede7f6; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 28px; height: 28px; color: #673ab7;"><path fill="currentColor" d="M160 0c17.7 0 32 14.3 32 32l0 32 128 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-9.6 0-8.4 23.1c-16.4 45.2-41.1 86.5-72.2 122 14.2 8.8 29 16.6 44.4 23.5l50.4 22.4 62.2-140c5.1-11.6 16.6-19 29.2-19s24.1 7.4 29.2 19l128 288c7.2 16.2-.1 35.1-16.2 42.2s-35.1-.1-42.2-16.2l-20-45-157.5 0-20 45c-7.2 16.2-26.1 23.4-42.2 16.2s-23.4-26.1-16.2-42.2l39.8-89.5-50.4-22.4c-23-10.2-45-22.4-65.8-36.4-21.3 17.2-44.6 32.2-69.5 44.7L78.3 380.6c-15.8 7.9-35 1.5-42.9-14.3s-1.5-35 14.3-42.9l34.5-17.3c16.3-8.2 31.8-17.7 46.4-28.3-13.8-12.7-26.8-26.4-38.9-40.9L81.6 224.7c-11.3-13.6-9.5-33.8 4.1-45.1s33.8-9.5 45.1 4.1l10.2 12.2c11.5 13.9 24.1 26.8 37.4 38.7 27.5-30.4 49.2-66.1 63.5-105.4l.5-1.2-210.3 0C14.3 128 0 113.7 0 96S14.3 64 32 64l96 0 0-32c0-17.7 14.3-32 32-32zM416 270.8L365.7 384 466.3 384 416 270.8z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">国际化支持</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">支持多语言界面，满足不同语言环境需求。</p>
  </div>

  <div style="background-color: #ffffff; border: 1px solid #eaecef; border-radius: 16px; padding: 24px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);">
    <div style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background-color: #ffebee; border-radius: 14px; margin-bottom: 14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 28px; height: 28px; color: #d32f2f;"><path fill="currentColor" d="M0 256a256 256 0 1 1 512 0 256 256 0 1 1 -512 0zM288 96a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM256 416c35.3 0 64-28.7 64-64 0-16.2-6-31.1-16-42.3l69.5-138.9c5.9-11.9 1.1-26.3-10.7-32.2s-26.3-1.1-32.2 10.7L261.1 288.2c-1.7-.1-3.4-.2-5.1-.2-35.3 0-64 28.7-64 64s28.7 64 64 64zM176 144a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM96 288a32 32 0 1 0 0-64 32 32 0 1 0 0 64zm352-32a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z"/></svg>
    </div>
    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1f2328;">性能优化</h3>
    <p style="margin: 0; color: #57606a; line-height: 1.6;">高效的下载处理机制，不拖慢网站速度。</p>
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
