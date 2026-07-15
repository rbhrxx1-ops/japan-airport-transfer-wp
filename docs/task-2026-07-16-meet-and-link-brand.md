# 任务：M10 Meet & Link 全站品牌替换

**日期：** 2026-07-16（GMT+9）
**执行角色：** ②全栈工程师AI（生产部署）
**授权人：** 中村先生
**任务性质：** 招聘页发布闭环后的独立追加任务
**Git 基线：** `main` / `5c06f8d45f2d26dd4b0844eebc24a7ff580426c0`
**生产地址：** `https://meetandgreet.jp/`

## 目标

将网站公开品牌统一为 **Meet & Link（ミート＆リンク）**，副标题统一为 **EXECUTIVE & EVENT TRANSFER**，日文说明语统一为 **会議・来賓の送迎と出迎え**。使用用户提供的明暗两版 Logo，经确定性裁切和响应式适配后分别用于浅色与深色背景；同步站点标题、页头、页脚、首页品牌展示、SEO、结构化数据、预约通知、隐私工具标签和站点图标。

## 用户提供资源

| 资源 | 用途 | 原始规格 | SHA-256 |
|---|---|---|---|
| `meet_and_link_logo_light.webp` | 浅色背景、页头 | 2048×1365 WebP | `903be1f1469e9dfc90348b75961938d45da69d545ea530aaa6535d9297d9b489` |
| `meet_and_link_logo_dark.webp` | 深色背景、页脚/首页深色区 | 2048×1365 WebP | `607e5acefed77af0c549bb9822f93f3a16d9d1d17d1bf839d31b994d4911eab7` |

原图保持不变。衍生资产采用确定性裁切、缩放和压缩，不重新生成图形或改动 Logo 造型。

## 生产基线

| 项目 | 当前值 |
|---|---|
| WordPress 站点标题 | `Japan Airport Transfer` |
| WordPress 说明语 | `空港・駅 ミート＆センディングサービス` |
| 自定义 Logo | 未设置 |
| 站点图标 | 未设置（主题提供旧 `favicon.svg`） |
| 主题 | `jat-meet-theme 0.2.5` |
| 预约插件 | `jat-reservation 0.2.4` |
| Git / 生产关系 | 生产不是 Git 工作树，必须按候选文件、哈希校验和原子替换部署 |

## 允许修改范围

| 文件或资源 | 变更 |
|---|---|
| `bin/seed-site.php` | 幂等设置新站点标题、说明语；替换公司页中的旧品牌介绍，不改变法人登记信息 |
| `wp-content/themes/jat-meet-theme/parts/header.html` | 使用浅色横版 Logo，提供可访问品牌名和响应式布局 |
| `wp-content/themes/jat-meet-theme/parts/footer.html` | 使用深色横版 Logo；更新版权品牌名与日文说明语 |
| `wp-content/themes/jat-meet-theme/templates/front-page.html` | 更新首页举牌默认品牌文字及必要的公开品牌文案 |
| `wp-content/themes/jat-meet-theme/style.css` | 新 Logo 的桌面/移动尺寸、深浅背景适配、防溢出；更新主题作者品牌 |
| `wp-content/themes/jat-meet-theme/inc/seo.php` | 首页 meta description 更新为新品牌和新说明语；结构化数据继续读取站点标题 |
| `wp-content/themes/jat-meet-theme/assets/images/meet-and-link-logo-light.webp` | 浅色背景裁切版 Logo |
| `wp-content/themes/jat-meet-theme/assets/images/meet-and-link-logo-dark.webp` | 深色背景裁切版 Logo |
| `wp-content/themes/jat-meet-theme/assets/images/favicon.svg` 及必要衍生图标 | 替换旧品牌站点图标并保留小尺寸可识别性 |
| `wp-content/plugins/jat-reservation/jat-reservation.php` | 插件描述和作者品牌文字 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-mailer.php` | 预约邮件标题、开头品牌名 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-privacy.php` | 隐私导出/删除器与数据分组品牌名 |
| `tests/` | 新增或更新品牌一致性、Logo、SEO、邮件、桌面/移动和旧品牌禁用门禁 |
| `truth.md`、`plan.md`、`.pm-ai/changelogs/`、`docs/` | 事实源、计划、变更日志与生产报告 |

## 明确不修改

不更改域名、预约流程、价格、招聘内容、服务内容、数据库结构、订单数据、客户资料、法务条款实质、联系地址、法人登记名称或第三方品牌。若法务页面出现实际法人名称，不以展示品牌替换。历史报告、既有提交说明和备份证据保留原文，不做追溯性改写。

## 执行步骤

1. 复核 Git、生产站点标题、说明语、品牌文件哈希和受保护页面哈希。
2. 从用户原图生成裁切后的浅色/深色 WebP；生成小尺寸站点图标；验证尺寸、文件大小和视觉边界。
3. 外科式修改页头、页脚、首页、SEO、种子脚本和预约插件品牌文字。
4. 新增品牌专项测试；同步本地 WordPress 运行副本；执行两次幂等种子、PHP 语法、全部 PHP/Python/浏览器回归和旧品牌残留扫描。
5. 在生产写入前建立专用回滚目录、数据库导出、受影响文件原件、页面哈希清单，并触发 UpdraftPlus 完整本地备份。
6. 上传候选文件，校验本地/远端 SHA-256 与 PHP 语法；原子替换；执行种子；刷新 LiteSpeed 缓存；无条件删除一次性文件。
7. 验证站点标题、说明语、页头/页脚/首页 Logo、favicon、SEO、JSON-LD、预约邮件品牌、桌面和 390×844 移动端无溢出；确认价格、招聘、公司、联系及法务页未发生非预期变化。
8. 更新 `truth.md`、`plan.md`、变更日志和生产报告；执行敏感信息与差异门禁；独立 commit 并快进 push `main`。
9. 回报提交哈希、生产 URL、备份标识、自动化结果和桌面/移动证据。

## 检查点

| 检查点 | 通过标准 |
|---|---|
| CP1 资产 | 明暗 Logo 裁切正确、无拉伸；原图哈希不变；站点图标小尺寸可识别 |
| CP2 本地 | 站点标题/说明语正确；两次种子幂等；所有回归通过；生产源码中不再出现旧公开品牌 |
| CP3 备份 | 数据库、全部受影响文件、页面哈希和 UpdraftPlus 完整备份组成齐全 |
| CP4 部署 | 候选哈希一致；PHP 语法通过；原子替换成功；临时文件删除 |
| CP5 生产 | 首页、招聘、价格等公开页 200；明暗 Logo 正确；SEO/JSON-LD/邮件品牌正确；无横向溢出 |
| CP6 保护 | 非品牌业务内容和受保护页面正文哈希无非预期变化；旧 URL、订单和数据库结构未变 |
| CP7 Git | 品牌任务独立提交；`main` 与 `origin/main` 一致；工作树干净 |

## 回滚标准

若任一生产门禁失败，立即停止后续写入，恢复专用回滚目录中的文件原件和数据库导出，刷新 LiteSpeed 缓存并复验原页面哈希；未恢复到基线前不得标记完成或推送任务提交。

## 完成标准

生产站点所有公开品牌接触点均显示 **Meet & Link**，副标题为 **EXECUTIVE & EVENT TRANSFER**，日文说明语为 **会議・来賓の送迎と出迎え**；明暗 Logo 分别用于正确背景，桌面和移动端无溢出；SEO、结构化数据、预约通知与隐私标签一致；旧公开品牌字符串不再出现在生产源码或公开 HTML 中；全量回归、生产保护验证、独立提交和推送全部完成。
