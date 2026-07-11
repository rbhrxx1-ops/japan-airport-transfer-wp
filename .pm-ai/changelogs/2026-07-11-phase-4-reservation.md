# 阶段四变更记录：五步预约前端与安全提交接口

**日期：** 2026-07-11（GMT+9）

**角色：** ②全栈工程师AI（生产部署）

**汇报对象：** PM-AI（通过中村先生转达）

## 完成内容

独立插件 `jat-reservation` 已在隔离环境完成阶段四开发。插件提供五步日语预约表单、六类服务分支、条件字段清理、欢迎牌预览、确认摘要、本地草稿恢复和“受付済み不等于预约确定”的明确边界。换乘与陪同首期仅作咨询，不保存航班、列车、欢迎牌或车辆等标准服务承诺字段。

REST 接口已实现同源校验、Nonce、蜜罐、时间陷阱、十分钟速率限制、UUID 幂等键、十五分钟请求指纹重复检测、服务器端白名单、条件必填、长度与格式校验。订单使用不可顺序推测的公开受付编号。WordPress 核心过期 Nonce 错误码 `rest_cookie_invalid_nonce` 已纳入前端自动刷新与单次重试流程，输入继续保存在本地草稿中。

## 主要文件

| 路径 | 说明 |
|---|---|
| `wp-content/plugins/jat-reservation/jat-reservation.php` | 插件入口、模块加载、激活迁移与资源加载 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-db.php` | 阶段四最小订单表、迁移、受付编号、幂等与重复检测 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-validator.php` | 字段白名单、服务条件、类型/长度/格式校验与隐藏字段清理 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-api.php` | REST 路由、同源、Nonce、反垃圾、限流、错误与提交响应 |
| `wp-content/plugins/jat-reservation/includes/class-jat-reservation-form.php` | 五步日语表单与可访问标记 |
| `wp-content/plugins/jat-reservation/assets/js/reservation.js` | 条件分支、草稿、摘要、错误聚焦、幂等提交与 Nonce 恢复 |
| `wp-content/plugins/jat-reservation/assets/css/reservation.css` | 响应式、进度、错误、摘要和成功状态样式 |
| `tests/phase4-integration.php` | 六分支、清洗、隐藏字段与幂等数据库集成测试 |
| `tests/phase4-api.py` | REST 同源、Nonce、蜜罐、时间陷阱、限流与重试测试 |

## 验证证据

| 检查 | 结果 |
|---|---|
| PHP lint | 通过 |
| JavaScript `node --check` | 通过 |
| WordPress 7.0.1 插件激活 | 通过 |
| 浏览器完整标准机场迎接五步提交 | 通过 |
| 服务器端集成测试 | 23/23 通过 |
| REST 安全与恢复测试 | 11/11 通过 |
| 统一门禁 | `PHASE4_GATE=PASS` |
| 凭据扫描 | 通过 |

## 未完成与门禁

阶段四不含订单后台、状态历史、邮件、角色权限、隐私导出/删除和保留任务；这些属于阶段五。公司资料、价格、取消规则、地点核验、法务正文、SMTP 与通知收件人仍未确认。生产完整文件与数据库备份、PM-AI 验收和中村先生转达的上线授权仍未取得，因此**未对生产站安装插件、切换主题、修改设置或写入订单数据**。
