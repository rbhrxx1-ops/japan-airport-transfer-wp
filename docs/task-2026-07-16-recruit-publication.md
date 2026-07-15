# 任务书：招聘页公开与页头导航接入 V1

**任务：** `[RECRUIT-PUBLISH-20260716]` 将既有招聘页由草稿发布，并加入全站页头导航

**任务书：** `docs/task-2026-07-16-recruit-publication.md`（基线 commit `bd4046d11107153cbf0c2c1317275a20492fe28f`）

**仓库：** `github.com/rbhrxx1-ops/japan-airport-transfer-wp`（`main`，基线 HEAD：`bd4046d11107153cbf0c2c1317275a20492fe28f`）

**生产地址：** `https://meetandgreet.jp/`

**招聘地址：** `https://meetandgreet.jp/recruit/`
**生产对象：** WordPress 页面 ID `94`、slug `recruit`

## 目标与授权

中村先生于 2026-07-16 明确授权：将既有招聘页改为公开发布状态，并加入页头导航。本任务不改招聘待遇、雇主资料、招聘文案、联系表单、页脚导航或其他业务页面。

## 允许修改范围

| 文件或对象 | 允许变更 |
|---|---|
| `bin/seed-site.php` | 将既有 `recruit` 页面默认状态由 `draft` 改为 `publish`，确保后续幂等执行不重新降级 |
| `wp-content/themes/jat-meet-theme/parts/header.html` | 新增唯一页头导航项「採用情報」并链接 `/recruit/` |
| `wp-content/themes/jat-meet-theme/templates/page-recruit.html` | 将招聘页唯一 CTA 从重定向地址 `/contact/` 改为规范地址 `/company/contact/`，避免公开后产生内部 301 与 canonical 门禁失败 |
| `tests/recruit_page_e2e.py` | 将草稿隔离断言更新为公开页和页头导航断言，并锁定招聘 CTA 使用规范联系地址 |
| 专项测试/质量门禁 | 增加页面状态、导航唯一性、HTTP 200、移动端菜单、规范内部链接与横向溢出检查 |
| 生产 WordPress | 页面 ID `94` 状态改为 `publish`；部署唯一变更的页头模板；清理缓存 |
| `truth.md`、`plan.md`、`.pm-ai/changelogs/`、`docs/` | 记录事实、计划、生产证据和回滚信息 |

## 禁止范围

除将招聘 CTA 的目标 URL 从 `/contact/` 规范化为 `/company/contact/` 外，不得修改招聘待遇、雇主资料、正文、CTA 文案或样式、联系表单、页脚、价格、公司、法务、预约插件或其他页面内容；不得更新插件、主题依赖、WordPress 核心或服务器配置；不得将凭据、私钥、数据库备份、Cookie、个人资料或真实订单写入 Git。

## 执行步骤

1. `git fetch origin main`，确认本地 HEAD 与 `origin/main` 均为基线提交，工作树无未解释变更。
2. 只读确认生产页面 ID `94` 为 `draft`、内容角色为 `recruit`，页头尚无 `/recruit/`。
3. 修改 `bin/seed-site.php`、`parts/header.html`、`templates/page-recruit.html` 和招聘专项测试；只做上述最小变更。
4. 在本地 WordPress 执行 PHP 语法、幂等种子、页面公开状态、页头唯一链接、招聘 E2E、业务验收和全站质量门禁；全部必须 exit `0`。
5. 生产写入前建立受保护的专用回滚目录、数据库导出、部署前页面/页头哈希清单，并触发 UpdraftPlus 完整本地备份；必须确认备份组成可恢复。
6. 对远端现有页头和招聘模板哈希执行唯一锚点校验；仅上传校验过的 `parts/header.html`、`templates/page-recruit.html` 和一次性受控发布脚本。
7. 执行一次性脚本，将页面 ID `94` 改为 `publish`，保持内容与元数据不变；清理 LiteSpeed 缓存；无条件删除一次性脚本。
8. 验收 `/recruit/` HTTP 200、页面 ID/slug/角色/正文哈希不变、页头导航唯一、桌面和 390×844 移动端无横向溢出、移动菜单可访问；公司、联系、价格及四个法务页哈希保持不变。
9. 复跑本地全部门禁，更新 `truth.md`、`plan.md`、变更日志和生产报告。
10. `git add` 仅纳入本轮源码、测试和文档；检查敏感信息与运行时文件排除后 commit 并 `git push origin main`。

## 检查点与停止条件

| 检查点 | 通过标准 | 失败动作 |
|---|---|---|
| 源码基线 | 本地与远端 HEAD 一致，无未知变更 | 停止并报告差异 |
| 本地门禁 | 所有语法、业务、招聘 E2E、全站质量测试通过 | 不建立生产写入 |
| 生产备份 | 数据库、主题、插件、上传、其他内容与回滚哈希均可确认 | 禁止部署 |
| 唯一锚点 | 远端页头哈希与部署前基线一致 | 禁止覆盖 |
| 写后验收 | 招聘页 200、导航唯一、内容哈希不变、保护页哈希不变 | 立即回滚并清缓存 |
| 代码闭环 | 提交已推送，工作树干净，远端 main 指向新提交 | 不声明完成 |

## 完成标准

生产 `/recruit/` 对匿名访客返回 HTTP `200`；WordPress 页面 ID `94` 状态为 `publish`；页头桌面和移动菜单均只出现一个「採用情報」链接；招聘正文、待遇、CTA 文案/样式和内容角色未被意外修改，CTA 直接指向规范联系地址；受保护页面哈希不变；一次性脚本公开路径为 `404`；完整备份和专用回滚点可用；自动化与视觉验收全部通过；提交已推送至 `origin/main`。

## 闭环

完成后回报完整 commit hash、生产备份标识、专用回滚路径、变更文件、测试结果、公开 URL 和已知风险；随后停止，等待中村先生后续指示。
