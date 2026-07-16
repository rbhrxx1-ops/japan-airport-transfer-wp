# Meet & Link AIOSEO 生产基线

**日期：** 2026-07-16（GMT+9）

## 1. 只读结论

生产站已启用 **All in One SEO Pro 4.9.10**，同时启用 AIOSEO E-E-A-T 1.2.13、Image SEO 1.2.5 与 Broken Link Checker SEO 1.3.0。AIOSEO 已接管前端 title、description、canonical、Open Graph 和 JSON-LD；主题在检测到 `AIOSEO_VERSION` 后停止输出自有 description、canonical 与 Schema，因此当前没有主题与插件重复输出。

| 项目 | 基线 |
|---|---|
| 固定页面 | 29 个已发布，1 个草稿 |
| FAQ | 24 个已发布的内部 FAQ 条目；CPT 不公开，由 FAQ 页面统一展示 |
| 普通文章 | 1 个已发布，另有 2 个 auto-draft |
| 事例 | 当前没有已发布内容，页面 `cases` 为草稿 |
| AIOSEO 逐页记录 | 已为全部页面创建记录，但绝大多数自定义 title/description/keyphrase 为空 |
| TruSEO 分数 | 29 个已发布页面均为 0；首页虽有短标题与短描述，分数仍为 0 |
| 首页现状 | title 为“ホーム - Meet & Link”，description 仅“空港・駅でのお出迎えを、確実でスムーズに。” |

## 2. 全局设置基线

| 设置 | 当前状态 | 风险 |
|---|---|---|
| 站点标题模板 | `#site_title #separator_sa #tagline` | 容易生成过长或泛化标题 |
| 全局描述 | `#tagline` | 无法覆盖各页搜索意图 |
| Organization | 名称与描述依赖站点标题/Tagline，Logo 为空 | 品牌实体不完整 |
| 作者归档 | 开启并可索引 | 单一作者站容易形成重复/薄内容 |
| 日期归档 | 开启并可索引 | 当前仅 1 篇文章，价值低且易重复 |
| 搜索结果 | noindex | 符合预期 |
| 全局 Sitemap | 开启，当前自动包含全部公开 Post Type 与 Taxonomy | 会纳入低价值分类和空内容类型 |
| 新闻 Sitemap | 开启 | 当前站点不符合新闻站点用途，属于多余配置 |
| 视频 Sitemap | 开启且自动包含多个内容类型/分类法 | 当前无系统化视频内容，属于多余配置 |
| Open Graph | 开启，默认图为空 | 无图页面分享卡片不稳定 |
| X/Twitter | 开启，summary_large_image，默认图为空 | 无图页面分享卡片不稳定 |
| Google 验证 | 已存在真实验证码 | 必须保留不变 |
| robots.txt | 仅禁止 `/wp-admin/`，允许 admin-ajax | 基础规则正常 |

## 3. Sitemap 基线

`/sitemap.xml` 与 `/sitemap_index.xml` 均由 AIOSEO 生成，当前至少包含 `post-sitemap.xml`、`page-sitemap.xml` 与 `category-sitemap.xml`。页面 Sitemap 已包含首页。后续需将范围缩至有搜索价值的已发布内容，并关闭无实际内容支持的新闻/视频 Sitemap。

## 4. 前端标签抽样

首页、服务、羽田、价格、FAQ、在线申请与隐私页面均只有一个 title、description、canonical、Open Graph 组和一个 AIOSEO Schema 脚本。当前描述大多由正文自动截断，部分句子在语义中途结束，不适合作为最终搜索摘要。

| 页面 | 当前 title | 当前 description | robots |
|---|---|---|---|
| 首页 | ホーム - Meet & Link | 极短通用标语 | **noindex, nofollow** |
| 服务 | サービス - Meet & Link | 正文自动截断 | index |
| 羽田空港 | 羽田空港 - Meet & Link | 正文自动截断 | **noindex, nofollow** |
| 料金 | 料金 - Meet & Link | 正文自动截断 | index |
| FAQ | よくあるご質問 - Meet & Link | 简短说明 | index |
| 在线申请 | オンライン申込 - Meet & Link | 正文自动截断 | index |
| 隐私政策 | プライバシーポリシー - Meet & Link | 仍含旧品牌 `Japan Airport Transfer` 的正文开头 | index |

## 5. 关键异常

生产首页当前实际输出 `noindex, nofollow`，这是严重 SEO 阻断；羽田等地点页面也输出 noindex。WordPress 全局可见性、AIOSEO 页面记录与主题 `jat_publish_ready` 保护逻辑需要在备份后做确定性修复，但不得绕过未核准地点事实边界。

只读 SSH 在完成插件、表结构、选项和页面记录导出后出现三次远端主动断开/超时；未产生写入。后续备份阶段优先使用已登录后台的 UpdraftPlus 和 AIOSEO 导出能力，同时在连接恢复后补做数据库与服务器回滚目录，不允许在完整回滚点建立前修改生产。
