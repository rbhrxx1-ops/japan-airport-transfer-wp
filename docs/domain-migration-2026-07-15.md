# 域名迁移审计与执行记录

**项目：** Japan Airport Transfer WordPress
**迁移目标：** `japanairporttransfer.com` → `meetandgreet.jp`
**核查时间：** 2026-07-15（GMT+9）
**执行角色：** ②全栈工程师AI（生产部署）

## 1. 只读核查结论

| 检查项 | 结果 | 结论 |
|---|---|---|
| `meetandgreet.jp` DNS | IPv4 `195.35.62.119`；IPv6 `2a02:4780:3:583:0:3617:db9:7` | 已解析至 Hostinger 环境 |
| `www.meetandgreet.jp` DNS | 与根域解析一致 | DNS 存在，但 TLS 尚未覆盖 `www` |
| 根域 HTTPS | `https://meetandgreet.jp/` 返回 HTTP/2 200 | 根域可正常访问 |
| WordPress REST 根信息 | `url` 与 `home` 均为 `https://meetandgreet.jp` | 生产 WordPress `siteurl/home` 已切换 |
| 首页 canonical | `https://meetandgreet.jp/` | 规范链接已使用新域名 |
| 根域证书 | Let's Encrypt；SAN 仅含 `meetandgreet.jp`；有效期 2026-07-15 至 2026-10-13 | 根域证书有效 |
| `www` HTTPS | 证书主机名校验失败 | 必须补发包含 `www.meetandgreet.jp` 的证书，或在证书生效后将 `www` 301 到根域 |
| 旧域名解析 | `japanairporttransfer.com` 当前无法解析 | 无法执行 SEO 所需的旧域名 301；需恢复旧域名 DNS 并保留迁移期重定向 |
| 生产首页标题 | `Japan Airport Transfer – 空港・駅 ミート＆センディングサービス` | 品牌标题保持不变，仅域名迁移 |

核查来源为公开 DNS 解析、TLS 握手、`https://meetandgreet.jp/` 响应和 `https://meetandgreet.jp/wp-json/` 的只读结果；未对生产后台、数据库、主题或插件执行写操作。

## 2. 代码影响分析

专属主题的 canonical 与 JSON-LD 使用 `home_url()`、`wp_get_canonical_url()` 和 `get_permalink()` 动态生成；预约 REST 同源校验使用 `home_url()` 的主机名；事务邮件正文没有硬编码旧域名。因此，生产 WordPress 地址已切换后，这些运行时输出会自动使用 `meetandgreet.jp`，不应为迁移额外引入硬编码。

仓库中需要直接更新的有效引用包括主题 `style.css` 的 `Theme URI`、`README.md`、`truth.md`、`plan.md` 与 QA/审计文档。历史 WXR 备份中的旧域名必须保持原样，保证备份真实性，不进行搜索替换。

本地集成测试继续使用 `http://127.0.0.1:8080`，不得改为生产域名，以防测试脚本向生产站创建订单或执行安全请求。主域名正确性应由独立的只读迁移验收测试覆盖。

## 3. 生产迁移门禁

| 门禁 | 当前状态 | 放行要求 |
|---|---|---|
| 完整生产备份 | 未完成 | Hostinger 完整文件与数据库备份可恢复 |
| PM-AI 验收 | 待新提交哈希 | PM-AI 对域名变更提交明确通过 |
| 上线授权 | 待确认 | 中村先生转达明确生产授权 |
| `www` TLS | 未通过 | 证书覆盖 `meetandgreet.jp` 与 `www.meetandgreet.jp` |
| 旧域名 301 | 未通过 | 恢复旧域名 DNS，并将所有路径以 301 映射到新域名对应路径 |
| 搜索引擎迁移 | 待生产放行 | 新域 sitemap/canonical 验证；旧域重定向验证；提交搜索引擎站点变更 |

在以上门禁解除前，只允许提交仓库、本地测试和只读生产核查，不执行生产主题安装、插件安装、数据库替换或内容发布。

## 4. 可重复验收

运行 `./tests/domain-migration-readonly.sh` 可重复检查新主域首页、canonical、WordPress `url/home`、根域证书、`www` 证书、旧域按原路径重定向及运行时代码旧域硬编码。该脚本只发送 GET/HEAD 请求和 TLS 握手，不登录后台、不提交预约、不修改生产数据。

2026-07-15 首次执行结果为 **5 项通过、2 项失败**。已通过新主域 HTTPS、canonical、WordPress URL、根域证书及运行时代码扫描；失败项严格对应 `www` 证书未覆盖和旧域名不可达。输出为 `DOMAIN_MIGRATION_GATE=FAIL (2)`，因此不得将域名迁移标记为完全完成。
