# BuddyBridge 项目指南

项目位于 `D:\xampp\bishe`。面向在意大利生活、学习或工作的中国人，以及在中国生活、学习或工作的意大利人，界面主要使用意大利语。

## 技术与运行

PHP 8.2、Symfony 6.4、Doctrine ORM / Migrations、Twig、Bootstrap、自定义 CSS 和 JavaScript。资源由 AssetMapper / importmap 加载，不需要 npm。开发数据库为 XAMPP 的 MariaDB 10.4.32。

完整安装步骤见意大利语 `README.md`。本机启动数据库后，在项目目录运行 `symfony server:start --no-tls --port=8000`，访问 `http://127.0.0.1:8000/`。

新环境从 `.env.example` 创建 `.env`，填写自己的数据库连接和 APP_SECRET，再安装 Composer 依赖、创建数据库并执行迁移。不要覆盖现有本地配置。仓库不包含真实账户、信件、数据库备份或依赖目录。

## 功能和入口

| 功能 | 页面路径 | 主要代码 |
| --- | --- | --- |
| 首页、分类和最近求助 | `/` | HomeController、templates/home/ |
| 登录、注册 | `/login`、`/register` | SecurityController、RegistrationController、templates/security/、templates/registration/ |
| 公开求助、搜索、回答 | `/bridge/task` | BridgeTaskController、TaskResponseController、ResponseCommentController、templates/bridge_task/ |
| 个人资料 | `/profilo/{id}` | UserProfileController、templates/profile/ |
| 笔友申请、信件 | `/amici-di-penna` | PenpalController、templates/penpal/ |
| 保存回答、共享笔记和补充 | 回答下的保存按钮及信件页的笔记入口 | SharedNoteController、templates/shared_note/、templates/penpal/_notebook.html.twig |
| 文化护照与参与记录 | `/passaporto-culturale` | CulturalPassportController、BridgeJourneyService、templates/passport/ |
| 通知 | `/notifiche` | CommunityNotificationController、CommunityNotificationService |
| 分类和用户管理 | `/category`、`/admin/users` | CategoryController、Admin/UserAdminController |

首页布局在 `templates/home/sections/`；全站配色和导航样式在 `assets/styles/`；导航结构在 `templates/base.html.twig`。前端资源入口是 `assets/app.js`。

## 数据与规则

- User 保存账户和个人资料。Category、BridgeTask、TaskResponse、ResponseComment 对应分类、求助、回答和评论；点赞另有关系表。
- 求助使用 open / resolved 状态。作者选择最佳回答后标记为已解决；最佳回答删除后恢复未解决。
- BuddyConnection 保存申请双方及 pending / accepted / rejected / removed 状态；PenpalMessage 保存关系中的信件。
- 解除笔友关系保留历史，但停止信件和笔记访问；重新申请并接受后恢复。当前有解除关系功能，没有独立的拉黑名单。
- SharedNote 保存关系内的共享笔记、个人记录及回答副本。SharedNoteComment 保存双方追加的内容。
- 同一回答在同一关系中只保存一次。原回答被删除后，笔记中保存的摘录仍保留。作者可编辑自己的笔记，关系双方可以补充。
- 信件和共享笔记要求关系为 accepted，访问者必须是关系参与者。表单写入检查权限、输入和 CSRF；分类写操作及用户管理需要管理员身份。
- 推荐查询在 UserRepository，按语言、国籍和共同兴趣评分；性别和年龄不参与评分。文化护照和关系等级由 BridgeJourneyService 汇总现有活动，不是友情质量的测量。
- 最新共享笔记结构变更在 `migrations/Version20260908120000.php`。迁移文件是结构变更记录，不代表任意新环境都已经执行过迁移。

## 检查与维护

`php vendor/bin/phpunit`：当前 29 项测试、150 条断言通过。共享笔记 HTTP 流程使用独立 SQLite 内存数据库。Twig 及 YAML 语法检查通过。这些检查不等于真实用户研究或全站人工验收。

修改时优先查看相关控制器、实体、模板和样式，跳过 `vendor/`、`var/`、`.phpunit.cache/`、`public/assets/` 和 `assets/vendor/`。不要公开 `.env`、`.env.local`、密码或私人数据。