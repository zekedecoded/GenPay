# GenPay Theme — the Scan & Pay design language

One design system for every role shell. Source of truth: **`assets/css/theme.css`**.
Extracted from the student Scan & Pay pages; consumed by the student
(`student_dashboard.css`, `--sd-*` aliases), admin (`admin.css`, `--emerald-*`/`--gold*`
aliases), merchant (`merchant.css`, same `--emerald-*`/`--gold*` alias pattern), and
parent (`parent_shell.css`, `--emerald-*`/`--gp-*` alias pattern) stylesheets.
**Change a color once in theme.css and every shell follows.**

## Palette tokens (`:root`)

| Token | Value | Use for |
|---|---|---|
| `--gp-green-950` | `#0d2418` | Deepest forest — sidebar bottom, topbar, hero gradients |
| `--gp-green-900` | `#123524` | Forest — gradient partner, hover fills |
| `--gp-green-850` | `#17402c` | Elevated dark surfaces (avatar tiles, icon chips on dark) |
| `--gp-green-700` | `#1e5c3a` | Lightest brand green — accents, chart lines, gradient ends |
| `--gp-gold` | `#f0a500` | THE accent: active nav, key numbers, focus, primary CTAs |
| `--gp-gold-light` | `#f6b81f` | Gold gradient partner (`gold-light → gold`, 135deg) |
| `--gp-gold-soft` | `rgba(240,165,0,.14)` | Gold-tinted fills (active nav bg, icon chips) |
| `--gp-gold-line` | `rgba(240,165,0,.45)` | Gold hairline borders on chips/badges |
| `--gp-gold-deep` | `#8a6a10` | Gold as **text on light surfaces** (contrast-safe) |
| `--gp-cream` | `#eef4ef` | Page background; also soft fills (table headers) |
| `--gp-card` | `#ffffff` | Content cards |
| `--gp-line` | `#e2eae3` | Hairline borders |
| `--gp-ink` / `--gp-muted` | `#14251b` / `#6b7a70` | Body text / secondary text |
| `--gp-success` on `--gp-success-bg` | `#15803d` / `#dcf3e4` | Completed, approved, active |
| `--gp-warning` on `--gp-warning-bg` | `#b45309` / `#fdf1d8` | Pending, processing |
| `--gp-danger` on `--gp-danger-bg` | `#b42318` / `#fde2e2` | Failed, rejected, frozen, blocked |
| `--gp-info` on `--gp-info-bg` | `#2563eb` / `#e3edfd` | Neutral-informational |
| `--gp-green` / `--gp-red` | `#16a34a` / `#dc2626` | Money-in amounts / money-out & destructive |
| `--gp-radius` / `--gp-radius-sm` | `16px` / `14px` | Cards / stat tiles |
| `--gp-mono` | IBM Plex Mono | References, amounts, IDs (all numbers) |
| `--gp-grad-hero` | composite | Dark hero cards (gold + green glows over forest) |
| `--gp-grad-shell` | composite | Sidebar (950 → 900, vertical) |
| `--gp-grad-forest-btn` | composite | Primary commit buttons (700 → 900) |
| `--gp-shadow-hero` / `--gp-shadow-card` | composite | Dark card lift / subtle card edge |

## Reusable classes

**Cards** — `gp-card` white content card · `gp-card-head` (h3 + p, optional right-side
action) · `gp-hero` dark gradient hero with `gp-hero-label` / `gp-hero-value` /
`gp-hero-badge` (gold chip) · `gp-stat` white stat tile with
`gp-stat-icon is-success|is-warning|is-danger|is-info|is-gold`.

**Buttons** (pill) — `gp-btn` base + one modifier: `gp-btn--forest` (primary commit),
`gp-btn--gold` (key CTA / on dark), `gp-btn--outline` (cancel/neutral),
`gp-btn--ghost` (translucent, dark surfaces only), plus `gp-btn--block`.

**Badges** — `gp-badge` + `gp-badge--success|--warning|--danger|--info|--gold`;
`gp-count` for soft count chips ("12 Records").

**Tables** — `gp-table`: cream uppercase headers, hairline rows, hover tint. Always
wrap in `.table-responsive`. Use `gp-mono` on references/amounts.

**Misc** — `gp-empty` empty state (icon + caption).

## Merchant-specific patterns (POS / Scan & Pay generation)

`merchant/pos.php` generates the payment QR the student side scans — its panel
echoes the student scan screen's visual language, adapted for a **static** code
instead of a live camera feed (see `assets/css/pos.css`):

- **`.pos-qr-frame` + `.pos-qr-corner.tl/tr/bl/br`** — the gold corner-bracket
  reticle (student `.sp-frame`/`.sp-corner` pattern), sized down to a 220×220 QR
  instead of a full camera frame. No scanline (nothing is scanning); the corners
  are pure framing. Wraps `#posQrCanvas` (where `qrcodejs` renders) — the wrapper
  and corner `<span>`s are siblings/parent of that id, so JS that targets
  `#posQrCanvas` by id is untouched.
- **`.pos-qr-guide` + `.pos-qr-guide-step`** — a compact 3-step "Show this QR to
  the student" strip inside the QR box (numbered gold-circle steps, same visual
  as `.sp-step`/`.sp-steps` on the student guide panel, but condensed to fit POS
  density — no separate side panel, since the cart/product grid already fills
  the layout).
- **`.pos-qr-status.is-pending/.is-paid/.is-expired`** — maps 1:1 to
  `gp-warning`/`gp-success`/`gp-danger`, matching the same three states the
  student side shows for a payment token.

`merchant/qr_scanner.php` (visitor voucher scanner, a **different** feature from
Scan & Pay — validates `VISITOR_VOUCHER` hashes, not payment tokens) gets a
lighter touch: `.merchant-reader` — the html5-qrcode container — gets a
gold-tinted border (`--gp-gold-line`) instead of literal corner brackets, and
**keeps a light background** (`--gp-cream`), because html5-qrcode injects its
own camera-permission button / device dropdown with light-theme styling we
don't control; a dark container risked poor contrast on that third-party UI
before the video feed starts. `.merchant-voucher-card` (the result panel) uses
`--gp-grad-hero` like every other hero card.

Both merchant hero cards (`.encash-hero-card`, `.history-balance-card`,
`.merchant-metric-card`) and the dashboard's economy pool cards keep their
category data-viz colors (purple/blue/amber) — same rule as admin's economy
widget.

## Parent portal (fourth shell)

The parent portal (`parent/dashboard.php`, `controls.php`, `profile.php`,
`student.php`) was rebuilt from the ground up onto its own `.parent-*` class
namespace in `assets/css/parent_shell.css` — a full shell rebuild, not a color
swap, since it previously ran on the pre-redesign `.student-layout`/
`.student-sidebar` classes (defined in the now-orphaned `assets/css/student.css`,
which nothing else in the app loads anymore). `includes/partials/sidebar_parent.php`
and the new `includes/partials/topbar_parent.php` mirror the exact partial
pattern used by the other three sides — 250px dark sidebar, full-bleed dark
topbar, gold active-nav state, `gp-grad-hero` cards, mono numbers.

Two things worth knowing if you touch this again:
- **The shared `logout_modal.php`** has a hardcoded CSS selector list for its
  instant-active-tab-on-click feedback (`.sidebar-menu > a, .merchant-menu > a,
  .student-menu > a, .parent-menu > a`) — if a future role adds its own
  `*-menu` class, it must be added to that list too, or the click highlight
  silently does nothing on that role's sidebar.
- **The ledger's transaction-type colors** (`parent/student.php`'s
  `$typeLabels` array) are deliberately kept in sync with student
  `student_dashboard.css`'s `.sd-txn--*` colors (payment=amber,
  topup=green, transfer=blue, voucher=purple) — a parent should see the
  same type-per-color coding their child sees on their own dashboard.

## Bootstrap bridge — `body.gp-theme`

Add `class="gp-theme"` to `<body>` to theme Bootstrap controls without touching markup:
cream page bg, inputs/selects with gold focus ring, pill `.btn-primary/.btn-success`
(forest), `.btn-secondary/.btn-outline-secondary` (neutral), `.btn-danger`,
16px-radius modals, and `.badge.bg-*` → soft status tints. All admin and interactive
merchant pages have it (merchant's print_menu.php is excluded, like admin's print
pages); student pages use their own sd-* components and don't need it.

## Conventions

- **Status mapping:** paid/completed/approved/active/released → success ·
  pending/processing → warning · failed/rejected/frozen/blocked → danger ·
  informational → info. Never invent new status colors.
- **Gold is an accent, not a surface.** Large fills stay forest/white/cream; gold marks
  the active thing, the key number, the primary action. Gold text on white must use
  `--gp-gold-deep`.
- **Numbers are mono** (`--gp-mono` / `gp-mono` / tabular-nums) — amounts, refs, IDs.
- **Cache-busting:** every stylesheet link carries `?v=N`; bump it whenever the file
  changes (current: theme v2, gjc-clear v11, admin v17, student_dashboard v12,
  student_topup v3, merchant v32, pos v4, parent_shell v2, login v11, apply v5,
  stalls v6).
- **`gjc-clear.css` is now a pure alias layer**, not an independent palette: its
  `--gjc-green-*`/`--gjc-gold-*`/`--gjc-danger/-success/-warning/-info/-alert`/
  `--gjc-ink/-muted/-line/-page/-panel/-soft/-sidebar` tokens all resolve to the
  matching `--gp-*` token (it `@import`s theme.css itself, so this holds even on
  pages that link only `gjc-clear.css` directly). The `--gjc-*` names are kept
  because dozens of older rules across the app still reference them by name —
  only the *values* changed. Left as independent (no `--gp-*` equivalent exists):
  `--gjc-gold-100`, `--gjc-soft-2`, `--gjc-danger-border`, `--gjc-success-border`,
  `--gjc-slate` (the last is a deliberate distinct hue, not brand-related — see
  the economy-widget rule above). New code should prefer `--gp-*` directly;
  `--gjc-*` exists for backward compatibility, not as a second system to target.
- **Deliberately NOT themed:** `print_voucher.php`/`print_voucher.css` and
  `merchant/print_menu.php` (print layouts), `admin/doc.php` (standalone docs), the
  economy widget's category data-viz colors (vault/students/merchants/vouchers pools,
  on both admin and merchant), and email templates (stay Arial).
- **Shared partials** (`includes/partials/logout_modal.php`, `datatables_assets.php`)
  are used by all three sides — style them via theme.css tokens only, never
  per-role files.
- **Third-party UI you don't fully control** (html5-qrcode's injected camera
  controls, DataTables' generated markup): style the container around it, not
  its internals, and keep enough default contrast that the library's own light-
  or dark-themed elements stay legible either way.
- **Every `*-main` content wrapper needs `min-width: 0`.** The `*-layout` shells
  use `display: flex` with a `position: fixed` sidebar (out of flow), so `*-main`
  is the sole flex item — flex items default to `min-width: auto`, which refuses
  to shrink below the intrinsic width of their content. Without `min-width: 0`,
  a wide DataTable or grid inside pushes `*-main` past its `calc(100% - 250px)`
  width instead of scrolling internally in its own `.table-responsive`, and the
  whole page gets a horizontal scrollbar. `.sd-main` (the original pattern) had
  this from the start; `admin-main`/`merchant-main`/`parent-main` were missing
  it until it was added across the board.

## Adding a new page

1. Link `theme.css` (directly, or via a role stylesheet that `@import`s it).
2. Add `gp-theme` to `<body>` if the page uses Bootstrap controls.
3. Build with `gp-*` classes; reach for tokens (`var(--gp-*)`) in any custom CSS.
4. Bump `?v=` on anything you edit.
