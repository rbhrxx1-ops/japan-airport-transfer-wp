# 2026-07-16 Meet & Link 全站品牌替换

## 任务

按 [`docs/task-2026-07-16-meet-and-link-brand.md`](../../docs/task-2026-07-16-meet-and-link-brand.md) 将 `meetandgreet.jp` 的公开品牌从 `Japan Airport Transfer` 替换为 **Meet & Link**，使用用户提供的明亮版与深色版 Logo。价格、公司资料、法务条款、招聘待遇和预约状态机保持不变。

## 变更

| 范围 | 内容 |
|---|---|
| 站点品牌 | WordPress 站点名称、首页品牌眉题、SEO、JSON-LD、邮件和隐私标签同步为 Meet & Link |
| 页头/页脚 | 分别使用明亮版与深色版 WebP Logo，保留现有导航结构 |
| 站点图标 | 发布 32、180、192、512 像素 PNG 图标并移除旧 `favicon.svg` |
| 资产构建 | 新增确定性构建脚本与资产清单，保持裁切、尺寸、哈希可复现 |
| 测试 | 新增 85 项品牌专项门禁和桌面/390px 生产 E2E；全站质量脚本按最终重定向 URL 验证 canonical |
| 视觉证据 | 归档生产首页 1440px 桌面和 390px 移动端全页截图 |

## 生产安全

生产写入前创建专用回滚目录 `/home/u907480505/rollback/meet-and-link-brand-20260715-160827`，完成 MariaDB 原生导出、部署前目标文件快照与页面/选项清单，并通过 UpdraftPlus 生成完整备份，备份 ID 为 `d0ba0ce84724`。

部署采用私有暂存目录、候选包 SHA-256 校验、PHP 语法检查、幂等种子、逐文件原子替换和无条件临时文件清理。发布后 16 个文件哈希与本地候选一致，`SEED_PERSISTED=NO`、`OLD_FAVICON_PRESENT=NO`、`TEMP_ARTIFACTS=NONE`。

## 验证

本地品牌 85/85、料金 24/24、阶段六业务 141/141、内容整改 64/64、匿名 24 页质量门禁及招聘 E2E 均通过。生产品牌 85/85、料金 24/24、匿名 24 页质量门禁和 `MEET_AND_LINK_BRAND_E2E=PASS` 均通过；桌面与 390px 移动端无横向溢出或品牌布局异常。

生产镜像运行两套本地草稿状态门禁时，`CONTENT_REMEDIATION` 有 2 项、`PHASE6_BUSINESS` 有 6 项环境不适用断言失败。失败已明确记录，未通过修改已授权公开的生产页面来迎合本地断言，也未误报通过。

正式证据见 [`docs/meet-and-link-brand-production-report-2026-07-16.md`](../../docs/meet-and-link-brand-production-report-2026-07-16.md)。
