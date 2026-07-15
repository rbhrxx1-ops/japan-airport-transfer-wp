# meetandgreet.jp 内容整改生产部署与验收报告

**日期：** 2026-07-15（GMT+9）
**任务：** MG-CONTENT-20260715 竞品调查报告内容整改
**执行角色：** ②全栈工程师AI（生产部署）
**生产地址：** https://meetandgreet.jp

## 生产写入前安全门禁

SSH 连接严格核验 RSA、ECDSA 与 ED25519 三类主机指纹，均与 `docs/ssh-access-audit-2026-07-15.md` 基线一致。连接使用专用 ED25519 私钥、`BatchMode=yes`、`StrictHostKeyChecking=yes`，并禁用密码、键盘交互、Agent 和端口转发。

## UpdraftPlus 完整备份

2026-07-15 22:41:25（GMT+9）通过生产已启用的 UpdraftPlus 1.26.5 原生备份 API 创建新的完整本地备份。插件返回 `true`，最终状态为：`バックアップは成功し、完了しました (7月 15 22:41:25)`。备份目录文件数由 10 增至 17，新增 7 个文件：

| 文件 | 字节数 | 组成 |
|---|---:|---|
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-db.gz` | 59,715 | 数据库 |
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-mu-plugins.zip` | 8,397 | MU 插件 |
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-others.zip` | 3,335,606 | 其他 wp-content 文件 |
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-plugins.zip` | 37,312,003 | 插件 |
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-themes.zip` | 30,326,098 | 主题 |
| `backup_2026-07-15-2241_Japan_Airport_Transfer_8816e6e23409-uploads.zip` | 4,124,358 | 上传文件 |
| `log.8816e6e23409.txt` | 49,307 | 备份日志 |

备份 nonce 为 `8816e6e23409`，可在 WordPress 后台 UpdraftPlus 现有备份恢复入口中识别。备份调用禁用云端上传，文件保存在生产 `wp-content/updraft`，未读取或复制备份正文。

## 部署记录

### 专用回滚点

生产写入前建立受保护回滚目录：`/home/u907480505/rollback/content-remediation-20260715-224235`，目录权限为 `0700`。`wp db export` 在该共享主机环境中无错误文本返回退出码 255，因此未将其误记为成功；改用同机 MariaDB 原生 `mariadb-dump` 执行 `--single-transaction --quick --skip-lock-tables --no-tablespaces` 导出。数据库凭据仅由远端 `wp config get` 注入进程环境，没有输出到终端、仓库或报告。

数据库回滚文件为 `database.sql`，权限 `0600`，大小 284,533 字节，SHA-256 为 `fb5979a25c44c253eab4111aa1c65b3cf88ec702f73afd2541fdd1790d457833`。同目录 `manifest.txt` 已保存生产目标页面的部署前状态、修改时间和正文 SHA-256；回滚目录总大小为 290,262 字节。

生产部署前不存在 `bin/seed-site.php`，因此本轮文件部署属于新增文件，不会覆盖既有生产脚本。部署前目标页面全部为 `publish`，首页 ID 为 10。需要保持不变的已发布页面正文哈希为：公司 `44e6a08c…6f72`、联系 `0932576a…2ebb`、隐私 `56a2698a…c5dc`、利用条款 `615d82f9…0b3f`、取消政策 `c309a1c0…92f`、特商法 `3ba9a138…2fe0`。整改目标页的完整部署前哈希已保存在生产受保护清单中。

最小差异部署仅上传与本地测试哈希一致的 `bin/seed-site.php`，通过 WP-CLI 在 WordPress 上下文中幂等执行一次；目标页面内容更新成功，公司、联系及四个法务页面部署前后正文哈希保持不变。缓存通过 WordPress 与 LiteSpeed 原生入口清理。

部署完成后追加公网安全复核：`https://meetandgreet.jp/bin/seed-site.php` 曾因缺少 WordPress 引导上下文返回 HTTP 500。该文件仅用于本次一次性内容迁移，且生产部署前不存在，因此在再次核对三类 SSH 主机指纹及本地/远端 SHA-256 均为 `1978cff126c772d240ef3b090efaebe5940eea29620a53b1ddac18e29d9abff1` 后，将远端脚本和空 `bin` 目录安全移除。清理后该 URL 返回 HTTP 404；仓库继续保留版本化脚本和测试，生产数据库内容不受影响。

## 自动化与生产验收

### 桌面端首页视觉检查

2026-07-15 22:49（GMT+9）在 Chromium 桌面视口打开 [生产首页](https://meetandgreet.jp/)，页面正常渲染，标题为「Japan Airport Transfer – 空港・駅 ミート＆センディングサービス」。主视觉、日文导航、主/次 CTA、图片、价值说明和页脚均显示完整，未观察到重叠、空白区块或横向溢出。首页主视觉公开文案继续保持「空港・駅でのお出迎えを、確実でスムーズに。」并显示“到着情報を確認 / サインボード / 車両と連携 / 受付後に確認”四项流程摘要；“オンラインで申し込む”“サービス内容を見る”“標準の流れを見る”等入口可见。桌面端检查在已通过边缘挑战的持久浏览器会话中完成；移动端有效证据另以仓库内截图和结构化 JSON 保存。

浏览器当前存在 WordPress 管理员登录态，因此独立的匿名 HTTP、SEO、结构化数据和响应式门禁另行执行，不以管理员工具栏截图替代公开访问验收。

匿名 HTTP 全站门禁已爬取 22 个公开页面并通过；首页返回 HTTP 200，`/cases/` 与 `/recruit/` 均保持 HTTP 404。生产只读 WordPress 内容门禁通过 104 项检查。

首次使用无状态 headless Chromium 生成 390×844 移动截图时，Hostinger 边缘安全层返回“Checking your browser before accessing meetandgreet.jp”挑战页；该截图不能用于判断站点布局，已明确标记为无效证据，不据此判定移动端失败。

随后通过已通过边缘挑战的持久 Chromium 会话，以 DevTools Protocol 模拟 390×844、触控启用的移动设备，对首页、服务、料金和法人页面重新验收。四页均满足：实际 `clientWidth=390`、`scrollWidth=390`、移动媒体查询命中、H1 正常、整改关键词可见、无挑战页、无横向溢出。首页响应式汉堡菜单可见，主标题、说明文字、主次 CTA、四项流程摘要和底部固定“お問い合わせ / オンラインで申し込む”CTA 均在移动首屏正常渲染，无重叠或截断。有效截图保存在 `docs/screenshots/{home,service,price,corporate}-mobile-2026-07-15.png`，结构化证据保存在 `docs/production-mobile-validation-2026-07-15.json`。

移动端生产验收结论：**通过**。人工复核有效截图后确认：料金页的“個別にご案内”“各項目を分けた見積り”“合計金額と条件を書面で確認”及“お見積りの主な構成”表格在 390px 下层级清晰、无横向截断；法人页标题、说明、业务对象和“複数便・複数名”卡片在 390px 下正常换行，底部固定 CTA 不遮挡主要标题与说明。浏览器控制台仅存在本次人工执行的视口检查输出，未发现站点脚本错误或警告。

移动端与控制台生产验收结论：**通过**。

## 回滚说明

若部署或验收失败，优先使用部署前专用数据库导出和内容种子文件快照；完整回滚可使用上述 UpdraftPlus 备份集恢复数据库、插件、主题、上传与其他内容。
