# 招聘页公开与页头导航生产发布报告

**日期：** 2026-07-16（GMT+9）

**执行角色：** ②全栈工程师AI（生产部署）

**生产地址：** [https://meetandgreet.jp/recruit/](https://meetandgreet.jp/recruit/)
**任务书：** `docs/task-2026-07-16-recruit-publication.md`

## 1. 发布结论

招聘页面 ID `94` 已从 `draft` 切换为 `publish`，公开路径 `/recruit/` 返回 HTTP 200。全站页头导航已加入唯一的「採用情報」入口，桌面端直接显示，移动端在菜单展开后显示。招聘正文、募集条件和既有待遇内容没有新增或改写。

招聘页公开后，全站质量门禁发现该页 CTA 原先指向会产生重定向的 `/contact/`。该问题被严格限制在本任务范围内修正：招聘模板中的唯一 CTA 改为既有规范地址 `/company/contact/`，文案与样式不变，其他页面未做顺手重构。

| 验收项 | 结果 |
|---|---|
| 招聘页公开状态 | `publish`，HTTP 200 |
| 页头导航 | 唯一「採用情報」链接，目标 `/recruit/` |
| 移动端菜单 | 展开后可见唯一招聘链接 |
| 招聘 CTA | 直接指向 `/company/contact/` |
| 页面横向溢出 | 桌面端及 390px 移动端均未发现 |
| 临时部署文件 | 已清理，候选文件数量为 0 |
| 受保护页面 | 公司、联系、料金及四个法务页面内容哈希未变 |
| 全站质量 | 生产匿名抓取 23 个公开页面，通过 |

## 2. 变更范围

本轮仅修改招聘页公开状态、页头导航、招聘 CTA 规范地址及相应测试门禁。

| 文件 | 变更 |
|---|---|
| `bin/seed-site.php` | 将既有招聘页默认状态设为 `publish`，保持幂等发布，不将其错误纳入公司/联系/法务六页保护计数 |
| `wp-content/themes/jat-meet-theme/parts/header.html` | 在既有页头导航加入唯一「採用情報」链接 |
| `wp-content/themes/jat-meet-theme/templates/page-recruit.html` | 将招聘 CTA 从 `/contact/` 改为规范地址 `/company/contact/` |
| `tests/recruit_page_e2e.py` | 验证公开状态、桌面/移动导航唯一性、募集条件表及规范 CTA |
| `tests/phase6-site-quality.py` | 仅对本地隔离环境允许规范联系页仍为草稿；生产继续严格要求其可公开访问 |

## 3. 备份与回滚

生产写入前已建立专用回滚目录，并通过 UpdraftPlus 1.26.5 生成完整本地备份。回滚目录包含数据库导出、部署前页头模板、页面状态与内容哈希清单；规范 CTA 补丁部署前又将原生产招聘模板单独归档到同一回滚点。

| 项目 | 证据 |
|---|---|
| 专用回滚目录 | `/home/u907480505/rollback/recruit-publication-20260715-151453` |
| UpdraftPlus 备份 ID | `65cf12b41472` |
| 数据库组件 | `backup_2026-07-16-0016_Japan_Airport_Transfer_65cf12b41472-db.gz` |
| 主题组件 | `backup_2026-07-16-0016_Japan_Airport_Transfer_65cf12b41472-themes.zip` |
| 插件组件 | `backup_2026-07-16-0016_Japan_Airport_Transfer_65cf12b41472-plugins.zip` |
| 上传组件 | `backup_2026-07-16-0016_Japan_Airport_Transfer_65cf12b41472-uploads.zip` |
| 其他组件 | `mu-plugins`、`others` 与完整备份日志均已生成 |
| 备份门禁 | `BACKUP_GATE=PASS` |

若需回滚，可从专用目录恢复数据库、页头模板和招聘模板，或通过 UpdraftPlus 后台使用备份 ID `65cf12b41472` 执行完整恢复。

## 4. 自动化验收

本地种子连续执行两次，招聘页仍保持唯一且为 `publish`，页头招聘导航只出现一次。本地全量回归在开启 `pipefail` 后执行，避免管道掩盖失败。

| 测试 | 结果 |
|---|---|
| 阶段四 PHP 集成 | 23/23 通过 |
| 阶段四 REST API | 11/11 通过 |
| 阶段五 PHP 集成 | 31/31 通过 |
| 阶段六业务边界 | 141/141 通过 |
| 内容整改门禁 | 64/64 通过 |
| 公开料金门禁 | 24/24 通过 |
| 固定页头桌面/移动 E2E | 通过 |
| 招聘页桌面/移动 E2E | `RECRUIT_PAGE_E2E=PASS` |
| 本地匿名全站 | 抓取 23 个公开页面，通过 |
| 生产匿名全站 | 抓取 23 个公开页面，通过 |

生产浏览器自动化首次匿名连接受到边缘安全层 403 限制，该环境失败未被误报为页面失败。随后通过已通过安全层的持久浏览器会话完成桌面及 390×844 移动视觉验收，并以独立匿名 HTTP/全站质量门禁验证公开可访问性。

## 5. 生产保护证明

部署后重新计算页面内容哈希。招聘页状态按预期切换为 `publish`；公司、联系、料金及四个法务页面内容哈希与部署前清单一致，结果为 `PROTECTED_PAGE_HASHES=UNCHANGED`。

| 页面 ID | 角色 | 部署后状态 |
|---:|---|---|
| 94 | 招聘页 | `publish`，预期变化 |
| 29、30 | 公司/联系 | `publish`，内容哈希未变 |
| 25 | 料金 | `publish`，内容哈希未变 |
| 3、34、35、36 | 法务页面 | `publish`，内容哈希未变 |

## 6. 视觉证据

| 证据 | 文件 |
|---|---|
| 桌面首屏与页头导航 | `docs/screenshots/recruit-desktop-top-2026-07-16.webp` |
| 桌面中下部、CTA 与页脚 | `docs/screenshots/recruit-desktop-bottom-2026-07-16.webp` |
| 390px 移动全页 | `docs/screenshots/recruit-mobile-2026-07-16.png` |
| 390px 移动菜单展开 | `docs/screenshots/recruit-mobile-menu-2026-07-16.png` |
| 移动端结构化验收数据 | `docs/production-recruit-mobile-validation-2026-07-16.json` |

移动端结构化证据确认：视口宽度 390px、正文宽度 375px、无横向溢出、募集条件表存在、菜单展开后招聘链接可见、CTA 目标为 `/company/contact/`。

## 7. 未包含事项

本任务没有改写招聘待遇、雇主资料、职位承诺或其他招聘正文，没有修改页脚导航、预约 CTA、价格、公司、联系或法务内容。站点品牌名称与 Logo 替换属于中村先生随后批准的独立追加任务，将使用单独任务书、备份与提交闭环。
