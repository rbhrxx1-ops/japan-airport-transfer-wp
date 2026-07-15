# Japan Airport Transfer WordPress

`meetandgreet.jp` 的专属 WordPress 主题与五步在线预约插件项目。主域名于 2026-07-15 从 `japanairporttransfer.com` 迁移；迁移审计见 `docs/domain-migration-2026-07-15.md`。

## 范围

- 专属主题：`jat-meet-theme`
- 专属预约插件：`jat-reservation`
- 前端语言：自然日语
- 首期服务：机场迎接、机场送行、车站迎接、车站送行
- 换乘支持与陪同服务：首期仅提供咨询入口

> 在线提交仅表示申请已受理（`受付済み`），不代表预约已确定。价格、资源、集合点和可用性由客服人工确认。

## 环境基线

生产主地址为 `https://meetandgreet.jp/`。生产站当前审计环境为 WordPress 7.0.1、PHP 8.3.30、MariaDB 11.8.8、LiteSpeed 与对象缓存。代码必须兼容该基线，并确保预约接口、确认页、nonce 与用户输入不被页面缓存。本地集成测试固定使用隔离地址，不得将测试基准直接改为生产域名。

## 目录

| 目录 | 用途 |
|---|---|
| `wp-content/themes/jat-meet-theme/` | 专属主题源代码 |
| `wp-content/plugins/jat-reservation/` | 专属预约插件源代码 |
| `docs/` | 审计、架构、测试与交付文档 |
| `.pm-ai/changelogs/` | 向 PM-AI 提交的变更记录 |
| `truth.md` | 项目唯一事实来源 |
| `plan.md` | 中村先生批准的执行计划 |

## 发布门禁

生产部署前必须同时满足：测试环境验证通过、完整文件与数据库备份可用、PM-AI 验收通过、中村先生转达生产上线授权。未确认的价格、公司资料、地点现场规则、法务文本、邮件配置和素材不得发布。

## 安全规则

凭据、真实订单、个人资料、上传文件、数据库导出与生产备份不得进入 Git。所有表单提交必须使用服务端白名单验证、权限检查、nonce、速率限制、幂等键、重复提交防护、预处理查询与输出转义。
