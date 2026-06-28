# Aime Design System

## Overview

Aime Assistant 是字节内部一款多端 AI 助手产品，桌面 / Electron mini-window / Lark 群机器人共用同一套视觉。设计哲学一句话讲完：**信息是主角，UI 是背景；青绿是引子，冷灰是底**。整套视觉走 Minimal Technical（极简工技）路线——一屏 95% 是白 (#ffffff) + neutral cool gray (#f3f3f5) + 黑灰文字，只在"发送 / 选中 / 完成"五处穷举位置出现鲜青绿 (#3dbf3d)。

它刻意反 AI Slop：不用紫色 / 紫渐变 / 蓝色科技感 / emoji 装饰 / 霓虹光晕。代码里部分历史色阶变量沿用 Arco 体系的语义化插槽名（如 `--agent-color-purple-*`），但实际色值是青绿色——命名是历史包袱，色值才是真源。与 Claude / Poe / Notion AI 的蓝紫科技感形成差异化，Aime 更像面向研发同事的内部工作台：安静、信息密集、按住理智的克制感。**真源说明**：代码库内有两套 token，v1（`--agent-color-*`）历史遗留正在淘汰；v2（`--aime-color-*`，定义于 `packages/aime-theme/src/css-variables-v2.less`）是当前主流，assistant 模块 100% 用 v2，本文档所有色值以 v2 为准。

视觉签名集中在三处：(a) 三栏圆角浮岛——最左 76px BotList 透明列 + 中 260px Chat list（#f3f3f5 冷灰底 + 左圆角 12px）+ 右侧 Main（#ffffff 白底 + 右圆角 12px），合在一个 20px 外壳里浮在页面上；(b) neutral cool gray 冷蓝灰阶，从 #f5f6f7 到 #060e1f，**和 AntD / Arco 中性灰同向**（带轻微蓝调），差异化交给品牌青绿与排版克制；(c) 半透明 overlay 胜过实色——hover / active / line 全部走 rgba(...) 让底色透出来，UI 因此整体更柔。品牌绿使用极度克制：整页饱和色面积不超过 5%。绿色像红绿灯里的绿——指示行动，不抢戏。

## Colors

### Brand & Accent

- **brand-default** (#3dbf3d): 唯一品牌色青绿，仅出现五处——send 按钮、Switch on、Brand logo、文字链、success 状态 fg
- **brand-hover** (#62d662): 品牌按钮 hover（v2 fill-brand-hover 真值）
- **brand-active** (#33a033): 品牌按钮 pressed（v2 fill-brand-active 真值，与 text-brand 同 hex 不同语义）
- **brand-disable** (rgba(61,191,61,0.32)): 品牌按钮 disable 态，32% alpha 固定值
- **text-brand** (#33a033): 文字链专用（"立即升级"、文件名 `IDENTITY.md`），不作背景

### Surface（冷灰底 + 浮岛白）

- **bg-page** (#f3f3f5): 整页底色（neutral cool gray，带轻微蓝调），叠 14% gradient overlay 加深为画布感
- **bg-panel** (#ffffff): 浮岛主面板 / 卡片 / Main 区底色
- **bg-menu** (#f3f3f5): Chat list 列底色 / 中型容器底色，与 page 同值但语义不同
- **bg-overlay-1** (rgba(91,100,117,0.06)): 全局 hover 行 / 按钮 / icon，不用品牌色填充
- **bg-overlay-2** (rgba(91,100,117,0.10)): active / selected 行
- **bg-overlay-3** (rgba(91,100,117,0.14)): pressed / secondary 按钮容器底色 / page gradient 叠加层
- **bg-disabled** (#c7cdd9): disable 态控件底色（v2 gray-3）

### Neutral Cool Gray Scale（带蓝调，和 AntD 中性灰同向）

- **gray-1** (#f5f6f7): 最浅，近白底
- **gray-2** (#e8eaed): 轻 hover bg / 弱分隔
- **gray-3** (#c7cdd9): disable 容器底 / disable 文字
- **gray-4** (#a6adbd): 中淡装饰
- **gray-5** (#747b8a): 中性灰（对位 AntD 冷灰 #8c8c8c，方向相同，更偏蓝）
- **gray-6** (#5b6475): 次深辅助
- **gray-7** (#444c5c): 深辅助 / 次正文
- **gray-8** (#313747): 深正文备用
- **gray-9** (#232938): 主正文文字
- **gray-10** (#060e1f): Primary 按钮极深底 / highlight 强调文字（Aime 重写 Arco 蓝色 primary 为深底白字）

### Text

- **text-highlight** (#060e1f): 一级标题 / 强调标签 / Session pill 文字，整页最多用一处
- **text-default** (#232938): 主正文 / 默认文字
- **text-subtle** (#444c5c): 次正文 / 按钮文字 / Header tag 文字
- **text-muted** (#747b8a): 辅助 / placeholder / 时间戳 / 预览
- **text-disabled** (#c7cdd9): disable 控件文字
- **text-inverse** (#ffffff): 深底白字（Primary / Brand button 上的文字）

一个面板最多用 3 级 text 颜色（如 highlight + default + muted），超过 3 级 = 失败。

### Line & Border（偏冷 77,101,148 基底，故意区分暖 overlay）

- **border-subtle** (rgba(77,101,148,0.10)): 分隔线 / Header bottom / panel 间分隔
- **border-default** (rgba(77,101,148,0.20)): 默认 Input / 卡片 border
- **border-bold** (rgba(77,101,148,0.32)): focused / hover Input / chat-input 强边框 / dashed avatar

### Semantic

- **success-fg** (#3dbf3d): 成功 / 完成态文字色，与 brand-default 同值但语义不同
- **success-bg** (rgba(61,191,61,0.10)): 成功淡底
- **success-border** (rgba(61,191,61,0.32)): 成功边
- **danger-fg** (#eb545e): 删除 / 验证错误 / 危险确认
- **danger-bg** (rgba(235,84,94,0.10)): 错误淡底
- **danger-border** (rgba(235,84,94,0.32)): 错误边
- **info-fg** (#5a90fa): 处理中 / 信息提示
- **info-bg** (rgba(90,144,250,0.10)): 信息淡底
- **info-border** (rgba(90,144,250,0.32)): 信息边
- **warning-fg** (#f59f00): 项目未显式定义，agent 兜底用 orange

### Bubble

- **bubble-user** (rgba(155,204,155,0.15)): user 气泡品牌绿淡底
- **bubble-assistant** (rgba(91,100,117,0.06)): assistant 气泡走 overlay-1

## Typography

### Font Families

- **font-cjk**: PingFang SC, -apple-system, BlinkMacSystemFont, 'Helvetica Neue', 'Microsoft YaHei', sans-serif — 中文 / 中英混排正文
- **font-en**: SF Pro, -apple-system, BlinkMacSystemFont, sans-serif — 纯英文段落 / 数字 + 冒号（如时间 "14:56"）
- **font-digital**: JetBrains Mono, 'SF Mono', Menlo, Consolas, monospace — 代码 / 行号 / 文件名 / token 数字

中英文混排走 PingFang（自带英文字形），不要切 SF Pro。禁止用 Inter / Roboto / Arial 当中文字体。

### Hierarchy

- **h2**: PingFang SC, 20px, 500, line-height 1.5, letter-spacing 0.3px — 一级标题（极少出现）
- **h3**: PingFang SC, 16px, 500, line-height 1.5, letter-spacing 0.3px — 二级标题 / 强调段
- **body**: PingFang SC, 14px, 400, line-height 1.57, letter-spacing 0.3px — 默认正文 / chat 消息 / Session pill
- **body-medium**: PingFang SC, 14px, 500, line-height 1.57, letter-spacing 0.3px — 按钮标签 / 强调文字
- **body-sm**: PingFang SC, 13px, 400, line-height 1.54, letter-spacing 0.3px — 紧凑正文 / placeholder / Header tag
- **body-sm-medium**: PingFang SC, 13px, 500, line-height 1.54, letter-spacing 0.3px — macOS button / Header tag medium 态
- **caption**: PingFang SC, 12px, 400, line-height 1.5, letter-spacing 0.3px — 标签 / 时间戳 / 辅助
- **digital**: JetBrains Mono, 13px, 400, line-height 1.54 — 代码 / 文件名 / 行号
- **en**: SF Pro, 13px, 400, line-height 1.54 — 纯英文 / 时间数字
- **button**: PingFang SC, 14px, 400, line-height 1.57, letter-spacing 0.3px — 默认按钮文字

字号严格走 12 / 13 / 14 / 16 / 20 五档，禁止凭空发明 15px。字重只用 400 regular 与 500 medium 两档；Medium 仅用于标题 / 按钮 / 品牌名 / selected 态文字。中文 letter-spacing 0.3px 是 PingFang 固定字距，不要去掉。

## Spacing

Base unit: **4px**。裸 px 禁止出现，除了 `1px` border。

### Inline 轴（水平间距）

- **inline-xs** (4px): icon 与文字间距
- **inline-sm** (8px): 紧凑并排（avatar + name）
- **inline-md** (12px): 标准元素间距

### Block 轴（垂直间距）

- **block-sm** (8px): 段内
- **block-md** (16px): 段间 / Content padding
- **block-lg** (24px): 区块间
- **block-xl** (32px): 大区块间
- **block-xxl** (40px): 顶层区块间
- **block-xxxl** (48px): 页级区块间

### Layout 专用

- **panel-margin** (8px): 浮岛与页面之间的呼吸
- **content-pad** (16px): Main 区内边距
- **content-max** (760px): 对话流 max-width

### Grid & Container

- 三栏浮岛比例：左 BotList **76px 固定** + 中 Chat list **260px 固定**（collapsed 80px）+ 右 Main `flex: 1`
- Panel wrapper padding 不对称 `8px 8px 8px 0`——左 0 让 BotList 紧贴页面边
- Main 内容居中：16px padding，内部 max-width 760px，chat-input min-width 480px
- Header 高度 **56px**，padding 不对称 `14px 24px 14px 12px`
- Page 底色不是纯 #f3f3f5，而是 `linear-gradient(rgba(91,100,117,0.14), rgba(91,100,117,0.14))` 叠 #f3f3f5

## Border Radius

- **radius-xs** (2px): Dot / 极小数据可视化
- **radius-sm** (4px): Session pill / 小 chip / Header 内嵌方按钮
- **radius-default** (6px): 按钮 / Input / Tag / 标签（最常用）
- **radius-md** (8px): Search bar / 中型 dropdown / Tool 卡片
- **radius-lg** (12px): 主 panel 内嵌容器 / chat list 左圆角 / Main 右圆角 / Drawer
- **radius-outer** (20px): 浮岛外壳（视觉缓冲层）
- **radius-pill** (42px): Count badge / pill
- **radius-full** (9999px): 圆形 avatar

圆角层级硬约束：外层圆角必须 ≥ 内层（outer 20 ≥ lg 12 ≥ md 8 ≥ default 6 ≥ sm 4 ≥ xs 2）。违反层级（如 6px 卡片包 12px 按钮）= 视觉错乱，立即重做。

气泡圆角"指向 avatar 不圆"——user 气泡 `border-radius: 8px 0 8px 8px`（右上不圆指向右侧 avatar），assistant 气泡 `border-radius: 0 12px 12px 12px`（左上不圆指向左侧 avatar）。

## Elevation

深度哲学：色块为主，阴影为辅。Aime 层次主要靠颜色对比（cool gray page vs 白 panel vs 浅灰 chat-list）建立，阴影只在浮岛外壳和少数 hover 态出现。禁止彩色 shadow（紫光 / 绿 glow / 蓝光），一律黑色透明叠 rgba(0,0,0,*)。

- **shadow-island** (0 2px 3px rgba(0,0,0,0.05)): 浮岛外壳，必须用 `filter: drop-shadow` 而非 `box-shadow`（被 `overflow: hidden` 裁掉）
- **shadow-card** (0 2px 6px rgba(0,0,0,0.05)): 普通浮起卡片
- **shadow-card-hover** (0 4px 12px rgba(0,0,0,0.10), 0 1px 2px rgba(0,0,0,0.08)): Card hover 浮起
- **shadow-popover** (0 1px 2px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.10)): Tooltip / Popover
- **shadow-dropdown** (0 4px 12px rgba(0,0,0,0.10)): Select / Menu 弹层
- **shadow-modal** (0 15px 35px rgba(0,0,0,0.05), 0 5px 15px rgba(0,0,0,0.05)): Modal / Dialog
- **shadow-input-focus** (0 2px 4px rgba(0,0,0,0.06), 0 1px 1px rgba(0,0,0,0.06)): Input focused 附加层

三处签名细节：panel 外壳的 shadow-island drop-shadow（浮岛视觉锚点）；chat-list 三面 1px white border + 左圆角 12px（内嵌感）；side-nav-item active 态 white → #f2fff2 渐变 + white border + `0 1px 1px rgba(0,0,0,0.05)`——Aime 唯一允许的"绿调底"，几乎看不出绿。

## Components

### Layout Shells

- **panel**: 浮岛外壳, 20px 外圆角, `filter: drop-shadow(0 2px 3px rgba(0,0,0,0.05))`, 内部双栏拼接
- **panel-chat-list**: 左侧灰底 #f3f3f5, 左圆角 12px
- **panel-main**: 右侧白底 #ffffff, 右圆角 12px, 左侧 1px rgba(77,101,148,0.10) 分隔
- **bot-list**: 最左 76px 纵向列, 透明背景, padding 20px 8px, 顶部 32×32 圆角方搜索按钮 + 多个 side-nav-item + 底部 return 按钮

### Navigation

- **header**: 56px 高, padding `14px 24px 14px 12px` 不对称, `border-bottom: 1px rgba(77,101,148,0.10)`, 白底; 左侧 Session pill + 返回 icon, 右侧 macOS button / 任务 tag / 编辑 icon, 默认 contained 态带 bg-overlay-1 灰底
- **side-nav-item**: 60×* 助理条目, flex 列向居中, 4px gap, 24×24 黑底 SVG avatar, default 透明, hover bg-overlay-2, active white → #f2fff2 渐变 + white border + 微 drop-shadow
- **side-nav-item-add**: 圆形 dashed 1px rgba(77,101,148,0.32) border + 中心 star icon
- **chat-list-item**: 244 宽 ~60px 高, padding `8px 6px`, 38×38 灰圆 avatar + 双行 body; row 1 title body + time caption SF Pro #747b8a; row 2 preview caption + 16×16 pin opacity-0 hover-显示; active 态 white → #fafbfb 渐变 + 1px border-subtle + drop-shadow
- **menu-item**: 36px 高 footer 菜单条, hover bg-overlay-1, active 白底 + 微阴影（不上绿）

### Cards & Panels

- **upgrade-banner**: Header 中部更新提示 pill, Aime 唯一允许的横向青绿黄渐变, 叠 white 底 + 浅黄 background

### Inputs

- **input-default**: 32px 高, padding `0 12px`, 1px rgba(77,101,148,0.20) border, 6px radius, 白底, 14px PingFang, #747b8a placeholder
- **input-hover**: border 升级到 rgba(77,101,148,0.32)
- **input-focused**: border rgba(77,101,148,0.32) + shadow-input-focus
- **input-error**: border rgba(235,84,94,0.32) + bg rgba(235,84,94,0.10) 淡红底
- **chat-input**: 760px max-width, 480px min-width, 居中, 1px rgba(77,101,148,0.32) 强边框（不是 default border）; 右下 28×28 send button 用 #3dbf3d——品牌色五处穷举之一

### Buttons

所有按钮共享 32px 高度, 6px radius, 14px PingFang button 字体。每个 Header / 表单 / Drawer 最多 1 个 primary 或 brand。

- **button-primary**: bg #060e1f 极深底, 白字（不是蓝——Aime 重写 Arco primary, 蓝色 = 用错了）
- **button-secondary**: bg rgba(91,100,117,0.14) 14% 灰底, #444c5c 文字（90% 场景的默认选择）
- **button-outline**: 白底, 1px rgba(77,101,148,0.20) border, #444c5c 文字
- **button-text**: 透明 bg, #232938 或 #33a033 文字
- **button-brand**: bg #3dbf3d 青绿, 白字, hover #62d662, active #33a033, disable rgba(61,191,61,0.32); 仅用于 send / upgrade
- **icon-button**: 28×28, 6px radius, 16-20px icon, 默认 #444c5c, hover #232938, active #060e1f; Header 右侧默认带 bg-overlay-1 灰底

### Tags & Badges

- **tag-default**: bg rgba(91,100,117,0.06), #444c5c 文字, 13px body-sm, 6px radius, padding `4px 12px`
- **tag-brand**: 品牌色文字链态
- **tag-count**: pill 计数态
- **tag-header**: Header 内嵌 tag 带 icon
- **count-badge**: 群人数 pill, 20×auto, 42px radius, 12px caption, bg rgba(91,100,117,0.14)

### Avatars

- **avatar-image**: 圆形 ``, 4 档尺寸 16/24/32/38px
- **avatar-initial**: 黑底 + initial 字母, demo 用 `getAssistantColor(id)` hash
- **avatar-dashed-add**: 圆形 1px dashed rgba(77,101,148,0.32) + star icon
- **avatar-logo-mask**: 黑底圆 + SVG mask 渲染品牌 logo

### Drawer

- **drawer-right**: 独立任务 / 定时任务 / 助理配置抽屉, 12px radius, shadow-modal, 白底, 关闭按钮 28×28 icon-button
- **drawer-bottom**: mobile 用, 同上样式

### Misc

- **chat-bubble-user**: 右对齐, bg rgba(155,204,155,0.15) 品牌绿淡底, `border-radius: 8px 0 8px 8px`, max-width 560px, 时间用 Roboto（历史遗留例外）
- **chat-bubble-assistant**: 左对齐, bg rgba(91,100,117,0.06) 灰底, `border-radius: 0 12px 12px 12px`, 支持 Tool 卡片嵌套（6px padding + 白底 + 1px rgba(77,101,148,0.20) border + 8px radius, header 文件名用 JetBrains Mono 13px + #33a033 青绿）
- **chat-bubble-system**: 居中, 12px caption, #747b8a 文字, 无 avatar 无 bg

### Motion

- **motion-fast** (0.1s cubic-bezier(0,0,1,1)): header / hover
- **motion-default** (0.15s ease): 颜色 / 背景过渡
- **motion-panel** (0.2s ease): 面板展开折叠
- **motion-empty** (0.3s ease): opacity 渐隐

新动画遵循 ≤ 0.3s + ease + 无 bounce 原则，禁止 spring / bounce。

## Guidelines

### Do

- 每页默认带 8px 外边距 + 20px 浮岛外壳 + drop-shadow island。新页面不要写"全屏铺满"
- 所有 hover 走 bg-overlay-1 (rgba(91,100,117,0.06))，active 走 bg-overlay-2 (0.10)，pressed / container 底走 bg-overlay-3 (0.14)。品牌色按钮例外——走 brand-hover / brand-active
- 中文段落用 PingFang SC（自带英文字形），代码 / 文件名 / 数字用 JetBrains Mono
- 字号严格走 12 / 13 / 14 / 16 / 20 五档
- 圆角层级 outer 20 ≥ lg 12 ≥ md 8 ≥ default 6 ≥ sm 4 ≥ xs 2，外层永远 ≥ 内层
- 任何可交互元素必须实现 default / hover / active / disabled 四态，列表项再加 selected 态
- 任何数据驱动列表必须有 skeleton + empty-state + error-fallback 三件套
- 所有图标默认色 #444c5c，hover #232938，active #060e1f
- 面板 / 区域之间只用 1px border-subtle 分割，不加阴影。阴影只给浮岛外壳与少数 hover
- 一次只关注一个组件，迭代而非一次性全部产出。新组件先 sketch 出像素级规格再写 HTML
- 用 Arco / ToD 现成组件 + `aime-theme.less` 自动重置，不自造

### Don't

- 不用紫色 / 紫渐变。代码里 `--agent-color-purple-*` 实际是绿色，插槽名是误导。看到 #8b5cf6 / #6366f1 一律禁用
- 不用 emoji 作功能图标。全屏 emoji ≤ 2 个，仅出现在已建立感知的位置（如 upgrade banner 🎉）
- 不大面积铺品牌色 #3dbf3d。单点 > 80×80px = 失败。绿色穷举只在五处：send button / Switch on / brand logo / 文字链 / success
- 不用纯色中性灰（#666 / #999 / #ccc）。Aime gray 走 v2 冷蓝灰阶，gray-5 是 #747b8a（带蓝调，比 AntD #8c8c8c 更显冷），所有中性灰从 token 取，不凭空写 hex
- 不混用字体。同一段中文别切 PingFang 和 SF Pro
- 不在 panel 外壳上写 `box-shadow`——必须 `filter: drop-shadow`
- 不超过 3 级 text 颜色。一个面板只用 highlight + default + muted 三档
- 不写赛博霓虹 / 深蓝 #0D1117 GitHub-dark 美学
- 不混用 overlay 与 line 的基底——overlay 是暖 (91,100,117)，line 是冷 (77,101,148)
- 不写 `outline: 1px dashed` 当 focus ring——focused 走 border-color + shadow-input-focus
- 不让 chat list 出现绿底 active——它的 active 是白底 + 微阴影，不上绿

## Responsive Behavior

Aime 通过 `MultiPlatformCompatible` 组件容器 + `body[aime-multi-platform-compatible]` 全局属性区分三端，不用 media query 切换布局——同一份 markup，运行时按平台 hide 不需要的元素。Hide 优于另写，禁止为 mobile / mini 各写一份组件。

- **desktop** (`useDevice().isDesktop`): 三栏圆角浮岛（76 + 260 + flex）+ 完整 Header + content padding `0 20px 16px` + max-w 760 居中。默认形态
- **desktop-mini** (`useDevice().isDesktopMiniWindow`): 仅 Main 区, BotList 与 Chat list 全部 `` 隐藏。Panel 外壳仍保留 20px radius + drop-shadow。宽度通常 360-400px
- **mobile** (`useDevice().isMobile`): 单列, Chat list 全屏, 选中后 Drawer 滑出全屏对话, content padding `0 12px 12px`, Header 折叠右侧 tag 群

Breakpoint 阈值未单独抽 token——项目用 `useDevice()` hook 内部判断，禁止手写 `window.innerWidth < 768`。Touch targets：所有 icon-button ≥ 28×28，button ≥ 32 高，chat-list-item 整条 60px 高（远超 44 触屏标准）。

## Known Gaps

- 仅 **assistant 框架**在 scope。`main` / `chat` / `task-list` / `history-tasks` / `showcase` / `workflow` / `setting` / `skill-mgmt` / `space-commands` / `space-setting` / `create-space` / `knowledge-base` / `mcp-debug` / `meego` / `assistant-config` 等 27 个页面未验证。生成这些前先拿 Figma 节点补 templates
- **Figma screenshot 未拿到**（rate limit），无 `design.png` 像素对照。需要时由用户手动从 Figma 节点 `8sN3eIAiVcQKDy1A6XI2dM / 146:11827` 导出 PNG 放入 `examples/assistant/design.png`
- **Dark mode** 仅 reference 层完整映射。System 层暗色映射只覆盖 bg-page / bg-panel / text-*，其余 border / overlay / brand / status 在暗色下精确表现未提取
- **未覆盖组件**: toast / message / popconfirm / progress / skeleton / loading-bubble / checkbox / radio / switch / select / cascader / date-picker / tabs / breadcrumb / pagination / steps / table / tree / list / chart-wrapper / artifact-preview / markdown-renderer。走 Arco / ToD 默认 + aime-theme 自动重置
- **字面量 `rgba(0,0,0,*)`** 未完全 token 化——部分 hover / shadow 用纯黑透明叠。`#f5f5f5` / `#e0e0e0` 在 `assistant/index.module.less` 内有少量遗留字面量
- **动画时序**（消息进入 / 滚动到底 / loading-bubble / streaming typing / drawer backdrop fade）未规格化
- **Breakpoint 阈值**未抽 token——项目用 `useDevice()` hook 内部判断
- **助理头像 12 色板**未提取——`getAssistantColor(id)` hash 函数输出色板未规格化
- **资产**: 所有功能 icon 来自 `@arco-design/iconbox-react-ve-o-design` / `@arco-design/iconbox-react-rdsvc` 包，未单独导出。skill / knowledge / file mention 图标 + upgrade banner 🎉 替代图未提取
- **测试 / 验证**: 未做 Playwright 截图对比、跨浏览器渲染验证、a11y 自动审计
