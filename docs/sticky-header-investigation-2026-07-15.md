# 固定页头核查记录

**日期：** 2026-07-15（GMT+9）

**目标站点：** `https://meetandgreet.jp/`

## 生产只读复现

首页初始视口中，顶部通知栏和主导航正常显示。将页面向下滚动约 98px 后，主导航完全离开视口，没有保持在顶部；因此中村先生反馈可以稳定复现。

仓库当前 `style.css` 已对 `.jat-header` 设置 `position: sticky; top: 0; z-index: 1000;`。生产 DOM 检查确认 `.jat-header` 位于仅约 118.95px 高的 `header.wp-block-template-part` 内；滚动至 `scrollY=962` 时，该模板部件整体顶部为 `-962px`，内部 sticky 元素顶部为 `-922.05px`。因此 sticky 的约束范围就是短父容器，主导航会随整个模板部件离开视口。

在浏览器会话中临时（不写入生产）将顶层 `header.wp-block-template-part` 设为 `position: sticky; top: 0; z-index: 1000`，并将内部 `.jat-header` 恢复为 `position: relative` 后，滚动至同一位置时模板部件顶部保持 `0`、底部约 `118.95px`，内部主导航顶部约 `39.95px`。候选方案能够同时固定通知栏与主导航，且不需要 JavaScript，不会产生 fixed 布局的占位跳动。

## 验收重点

修复必须同时验证桌面端和移动端，保持移动端覆盖菜单可用，并兼容 WordPress 登录后的管理工具栏偏移。固定页头不得遮挡锚点目标或造成首次渲染内容跳动。

## 本地修复验证

- 本地隔离站加载主题版本 `0.1.1`，样式资源为 `style.css?ver=0.1.1`。
- 桌面视口 `892×768`：初始页面同时显示通知栏和主导航；向下滚动后，两部分继续固定在视口顶部，主体内容从页头下方正常滚动。
- 实际修复由 `.wp-site-blocks > header.wp-block-template-part` 承担 `position: sticky`，不再把 sticky 放在内部 `.jat-header` 上，因此覆盖完整区块模板页头并避免父容器边界导致提前失效。

桌面端量化结果：滚动到 `scrollY=850` 时，顶层模板页头计算样式为 `position: sticky; top: 0`，边界保持为 `top=0`、`bottom=120.64px`；内部 `.jat-header` 为 `position: relative`，`html` 的 `scroll-padding-top` 为 `128px`。浏览器会话不允许通过 `window.resizeTo()` 改变视口，后续移动端验证改用独立窄视口的无头浏览器执行。
