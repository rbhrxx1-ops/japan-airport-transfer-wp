# Meet & Link 品牌资产处理记录

日期：2026-07-16
任务：Meet & Link 全站品牌替换追加任务

## 输入资产

用户授权提供两份 WebP 品牌资产：浅色背景版 `meet_and_link_logo_light.webp` 与深色背景版 `meet_and_link_logo_dark.webp`。输入文件保持不变，来源哈希与裁切参数记录在主题品牌目录的 `manifest.json`。

## 处理方式

使用确定性脚本 `tools/build-brand-assets.py` 去除四周多余留白，不生成或重绘 Logo 内容。脚本输出网页优化版明暗 Logo，并从深色版左侧品牌标志自动识别安全分隔区，派生 32、180、192 与 512 像素的正方形站点图标。

## 输出与视觉检查

| 文件 | 用途 | 视觉检查 |
|---|---|---|
| `meet-and-link-light.webp` | 白色页头、浅色区域 | 1746×447；标志、`Meet & Link` 与副标题完整，无截断；留白适合横向响应式显示 |
| `meet-and-link-dark.webp` | 深蓝页脚、深色区域 | 1746×446；浅色文字与深蓝背景对比清晰，标志与副标题完整 |
| `site-icon-32.png` | 浏览器小图标 | 使用深色底与左侧标志，适合小尺寸识别 |
| `site-icon-180.png` | Apple Touch Icon | 正方形输出 |
| `site-icon-192.png` | 移动端图标 | 正方形输出 |
| `site-icon-512.png` | WordPress 站点图标及高分辨率图标 | 正方形输出 |

两份网页 Logo 均仅做确定性裁切与压缩，没有改变品牌文字、颜色、构图或图形身份。下一阶段需在实际页头、页脚与移动端渲染中验证尺寸、清晰度、无横向溢出和导航共存。
