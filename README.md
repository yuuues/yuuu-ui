# yuuu-ui

The shared base stylesheet of the **yuuu** suite, shipped as a Symfony bundle: yuuuID's screens and operator panel, the yuuu CRM back-office, and every new app started from the skeleton look the same and stay accessible without copying CSS around.

- One file, `yuuu-base.css`: design tokens as CSS custom properties, a small set of BEM-light classes, automatic dark mode. The tokens alone, `tokens.css`, are generated from it for apps that bring their own rules.
- **Neutral** by design. Each app brings its personality through four brand tokens.
- **WCAG AA required, AAA targeted.** Tested in light *and* dark mode: every text pair reaches 4.5:1 (AA), control outlines 3:1. Every default text pair at rest also reaches **7:1 (AAA)**; see §7.
- **No external resources**: no web fonts, no CDN, no remote `url()`, no `@import`. Works under CSP `style-src 'self'`, with no inline styles needed.
- An optional script, `yuuu-ui.js` (under 3 KiB, no dependencies), that opens forms and confirmations in native `<dialog>` modals. **Progressive enhancement**: without it, links navigate and forms submit, and nothing breaks. Works under CSP `script-src 'self'`.
- An error page, `@YuuuUi/error.html.twig`, on the card layout, for the app's 403/404/500 pages and a "page expired" case.
- Two Twig functions, `yuuu_ui_stylesheet()` and `yuuu_ui_script()`, that link the files with a content fingerprint for cache busting, computed once when the container is compiled.
- An npm package, `@yuuues/yuuu-ui`, installed from a git tag (no registry), so JavaScript apps built with Vite can **import** the CSS, inlined into a single HTML file if they need to; see §12.

The bundle requires **PHP 8.4** and **Symfony 8.1**. JavaScript apps need neither (§12).

---

## 1. Install

This section and the next four are for Symfony apps. A JavaScript app (Vite, Preact...) goes straight to §12.

The package is not on Packagist yet. Until it is, add the repository to the app's `composer.json` (it is public, no credentials needed):

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/yuuues/yuuu-ui.git"
        }
    ]
}
```

Then:

```bash
composer require yuuues/yuuu-ui
```

Without Symfony Flex, register the bundle yourself in `config/bundles.php`:

```php
return [
    // ...
    Yuuues\YuuuUi\YuuuUiBundle::class => ['all' => true],
];
```

The bundle has no configuration. It needs **TwigBundle** for the Twig functions and the error page. Without TwigBundle the container still compiles, but the functions do not exist. TwigBundle registers the bundle's `templates/` directory by itself, under the `@YuuuUi` namespace: nothing to configure.

## 2. Publish the assets

The stylesheet and the script live inside the package, under `public/`. `assets:install` copies them to the app's public directory, where the web server can serve them:

```bash
php bin/console assets:install public
# -> public/bundles/yuuuui/css/yuuu-base.css
# -> public/bundles/yuuuui/js/yuuu-ui.js
```

Symfony Flex runs this automatically after every `composer install` and `composer update`, through the `auto-scripts` of the app's `composer.json`.

> **Deploys that run `composer install --no-scripts` must publish them explicitly.** `--no-scripts` skips `auto-scripts`, so nothing copies the files and the site loads without styles (and without dialogs). Add this to the deploy script, after `composer install`:
>
> ```bash
> php bin/console assets:install public --no-debug
> ```
>
> Use the default **hard copy**. Do not pass `--symlink` or `--relative`: a symlink points into `vendor/`, and a release-based deploy that swaps or prunes `vendor/` leaves it dangling.

Run it on **every deploy**, not only on the first one. The fingerprints in the URLs are computed from the files inside `vendor/`. If a new version of the package arrives and the copies in `public/` are not refreshed, the URLs change but browsers download the old files under the new names.

`public/bundles/` is a build artefact: add it to the app's `.gitignore`.

## 3. Use it in the base layout

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}My app{% endblock %}</title>
    <link rel="stylesheet" href="{{ yuuu_ui_stylesheet() }}">
    {# The app's own layer, always AFTER the base. #}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {# Optional: only for the dialogs of §5. `defer`: it runs once the page is parsed. #}
    <script src="{{ yuuu_ui_script() }}" defer></script>
</head>
<body>
    {% block body %}{% endblock %}
</body>
</html>
```

`yuuu_ui_stylesheet()` returns `/bundles/yuuuui/css/yuuu-base.css?v=<fingerprint>` and `yuuu_ui_script()` returns `/bundles/yuuuui/js/yuuu-ui.js?v=<fingerprint>`, where each fingerprint is the first 10 hex digits of the xxh3 hash of its own file. Each:

- **changes only when the content changes**, so browsers can cache the file for as long as you like (`Cache-Control: max-age=31536000, immutable` on `/bundles/yuuuui/` is safe as long as every page links it through the function, never by its bare path);
- **is computed once, when the container is compiled**, not per request. Rendering the function is a string concatenation. In debug mode both files are resources of the container, so editing one rebuilds the container and its fingerprint. In production the deploy's `cache:clear` does it;
- is **root-relative**. An app served from a sub-path (`https://example.com/app/`) is not supported yet.

## 4. The app's own layer

Everything app-specific goes in the app's own stylesheet, linked after the base. Two kinds of change:

**Override tokens.** The base reads everything from custom properties on `:root`. Because the app's file comes later, a plain `:root` rule wins over the base in **both** colour schemes:

```css
/* public/css/app.css (the CRM) */
:root {
    --yuuu-color-primary: #4055ff;
    --yuuu-color-primary-text: #ffffff;
}
```

To use different values in dark mode, add your own dark block after it:

```css
@media (prefers-color-scheme: dark) {
    :root {
        --yuuu-color-primary: #8c99ff;
        --yuuu-color-primary-text: #111111;
    }
}
```

**Add the app's own rules.** Use the tokens (`var(--yuuu-color-border)`, `var(--yuuu-space-4)`...) so they follow dark mode and stay consistent. Use your own prefix for your own classes, so they never collide with a future version of the base.

### Brand tokens

| Token | Light default | Dark default | Used for |
|---|---|---|---|
| `--yuuu-color-primary` | `#1b1f24` | `#e8eaed` | Background of `.btn` / `.btn--primary` |
| `--yuuu-color-primary-text` | `#ffffff` | `#111111` | Text on the primary colour |
| `--yuuu-color-accent` | `#4e545e` | `#a0a7b1` | The stripe on top of `.card` |
| `--yuuu-color-accent-text` | `#ffffff` | `#111111` | Text on the accent colour (for the app's own use) |

**You answer for the contrast of your own pair.** The base tests its defaults; it cannot test yours. Text on the primary colour must reach **4.5:1** (AA, required). Pick the text colour with the WCAG relative luminance, as yuuuID does for each company's colours: white or near-black (`#111111`), whichever contrasts more. For the CRM, `#ffffff` on `#4055ff` gives **5.30:1**: it passes AA.

The brand colour is only ever used as a **background** with its own text token, and as an ornament. Links, focus rings, form outlines and status colours stay neutral on purpose: a brand colour that reads well on white disappears on the dark background, and the other way round.

`.btn:hover` and `.btn:active` dim the whole button, text included, with `filter: brightness(0.92)` and `brightness(0.85)`. That lowers the ratio of a pair with white text: `#4055ff` with white drops from 5.30:1 to 5.07:1 on hover and 4.82:1 while pressed. Leave some headroom above 4.5:1.

### Brand overrides and AAA

AA is the requirement; AAA (7:1) is an **option** the app can take for its own brand. A brand colour used as the background of normal-size text reaches AAA only if it gets 7:1 with its `-text` pair. Mid-tones often cannot, with pure black or with pure white: `#4055ff` gives 5.30:1 with white and 3.96:1 with black.

The way out is to keep the bright tone for what carries no text (the `.card` stripe through `--yuuu-color-accent`, the app's own ornaments) and to give `--yuuu-color-primary` a **darker shade of the same hue** for the surfaces that carry text. For `#4055ff` (HSL 233°, 100%, 63%), the closest shade with the same hue and saturation that reaches 7:1 with white is **`#1b34ff`** (lightness 55%): **7.04:1**. Mixed with black instead of lowering the HSL lightness, the closest is `#3546d3` (7.08:1).

```css
/* Optional: the CRM's brand at AAA. */
:root {
    --yuuu-color-primary: #1b34ff;      /* 7.04:1 with white */
    --yuuu-color-primary-text: #ffffff;
    --yuuu-color-accent: #4055ff;       /* the bright tone, no text on it */
}
```

Even at 7:1 at rest, the hover and pressed filters bring the pair below 7:1 (`#1b34ff`: 6.61:1 hovered, 6.20:1 pressed), still well above AA. A shade that holds 7:1 while pressed too is `#001cfe` (8.09:1 at rest). In dark mode the same applies with near-black text: `#8c99ff` with `#111111` gives 7.30:1 at rest.

### Other tokens

All tokens are prefixed with `--yuuu-` to avoid collisions with the app's own properties.

| Group | Tokens |
|---|---|
| Surfaces and text | `--yuuu-color-bg`, `--yuuu-color-surface`, `--yuuu-color-border`, `--yuuu-color-border-strong`, `--yuuu-color-text`, `--yuuu-color-text-muted` |
| Status (each with `-text`, `-bg`, `-border`) | `--yuuu-color-success-*`, `--yuuu-color-warning-*`, `--yuuu-color-danger-*`, `--yuuu-color-info-*`, `--yuuu-color-neutral-*` |
| Danger button | `--yuuu-color-danger-solid`, `--yuuu-color-danger-solid-text` |
| Type | `--yuuu-font` (system UI stack), `--yuuu-font-mono` |
| Spacing | `--yuuu-space-1` (0.25rem) to `--yuuu-space-7` (3rem): 0.25, 0.5, 0.75, 1, 1.5, 2, 3 rem |
| Shape | `--yuuu-radius-1` (6px), `--yuuu-radius-2` (12px), `--yuuu-shadow` |
| Dialogs | `--yuuu-backdrop`: what a `.modal` lays over the page, a translucent colour (no text is painted on it) |
| Layout | `--yuuu-content-width` (80rem): the max width of `.page` and of the content of `.nav` |

Status colours are **not** meant to be overridden per brand: an error has to look like an error in every app.

## 5. Class catalogue

Naming is BEM-light: a block (`.btn`), a modifier with two dashes (`.btn--small`), an element with two underscores (`.nav__brand`). Modifiers always go together with their block: `class="btn btn--danger"`.

### Base elements (no class)

The base also styles plain elements, so a page with no classes already looks right: `body` (page background, system font), `h1`-`h3`, `p`, lists, `a` (neutral, underlined), `code`, `hr`, `img` (never wider than its container), `dl` (a two-column grid of terms and values, one column on narrow screens), `fieldset`, and a visible neutral focus ring on every focusable element. `[hidden]` always hides, whatever class the element has.

Form controls are styled without classes too: `label`, `input` (text, email, password, search, number, date...), `select` and `textarea` get the full width, a 2.75rem minimum height and a 3:1 outline. Checkboxes and radios keep their native look. A field with `aria-invalid="true"`, or one the browser finds invalid after the user touched it (`:user-invalid`), gets a red outline.

`form` is a vertical stack with a small gap between label, field and error. That is the layout of every sign-in and edit form; use `form.inline` for the other case.

> Bare `<button>` elements are **not** styled: use `.btn`. That way a button the app styles itself never has to undo the base.

### Layout

#### `.nav`, `.nav__brand`, `.nav__links`, `.nav__end`

The top bar. It runs edge to edge; its content lines up with `.page`. Mark the current link with `aria-current="page"` (it gets a thick underline).

```html
<nav class="nav" aria-label="Main">
    <a class="nav__brand" href="/"><img src="/img/wordmark.svg" alt="yuuu CRM" height="32"></a>
    <ul class="nav__links">
        <li><a href="/customers" aria-current="page">Customers</a></li>
        <li><a href="/invoices">Invoices</a></li>
    </ul>
    <div class="nav__end">
        <span class="muted">ana@example.com</span>
        <form class="inline" method="post" action="/logout">
            <button class="btn btn--secondary btn--small">Sign out</button>
        </form>
    </div>
</nav>
```

`.nav__brand` takes a text wordmark or an `img`/`svg` (capped at 2rem tall). `.nav__end` is pushed to the right.

#### `.page`

The content column: centred, `--yuuu-content-width` wide at most, with page padding. Long unbroken strings (emails, identifiers) wrap instead of overflowing.

```html
<main class="page">...</main>
```

#### `.page-header`, `.page-actions`

The title of a page, with its actions on the right (they wrap below on narrow screens). A `p` inside the header becomes a subtitle.

```html
<header class="page-header">
    <div>
        <h1>Customers</h1>
        <p class="muted">142 active</p>
    </div>
    <div class="page-actions">
        <a class="btn btn--secondary" href="/customers/export">Export</a>
        <a class="btn btn--primary" href="/customers/new">New customer</a>
    </div>
</header>
```

#### `.layout-card`, `.card`, `.card__header`

The centred card of the authentication screens (sign-in, activation, verification, OAuth consent). `.layout-card` centres its child in the viewport; `.card` is 26rem wide at most, with the accent stripe on top. Buttons inside a form in a card take the full width.

```html
<body class="layout-card">
    <main class="card">
        <div class="card__header">
            <img src="/img/logo.svg" alt="" height="56">
            <h1>Sign in</h1>
            <p class="muted">Welcome back</p>
        </div>
        <form method="post">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required>
            <button class="btn btn--primary">Continue</button>
        </form>
    </main>
</body>
```

### Navigation aids

#### `.breadcrumb`

Where the page sits, above its `.page-header`. A `<nav>` with its own label and an ordered list; the separators are drawn by CSS (with an empty alternative text, so screen readers do not read "slash" where the browser supports it). The last item is the current page: mark it `aria-current="page"`. It may be a link to itself or plain text.

```html
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/customers">Customers</a></li>
        <li><span aria-current="page">ana@example.com</span></li>
    </ol>
</nav>
```

Links are muted (7:1 on the page and on cards), the current item is in the text colour and bold.

#### `.pagination`, `.pagination__link`, `.pagination__current`

Previous, where you are, next. Links only: no page numbers, no JavaScript. The indicator carries `aria-current="page"`. An end with nowhere to go is **not a link**: a `<span class="pagination__link">` with no `href`, so it cannot be focused or followed, and `aria-hidden="true"`, so screen readers do not announce a "Previous" that goes nowhere (the indicator already says "Page 1 of 5"). It keeps its place, so the indicator does not jump between pages.

```twig
<nav class="pagination" aria-label="Pagination">
    {% if page > 1 %}
        <a class="pagination__link" href="{{ path('customer_index', {page: page - 1}) }}" rel="prev">Previous</a>
    {% else %}
        <span class="pagination__link" aria-hidden="true">Previous</span>
    {% endif %}
    <span class="pagination__current" aria-current="page">Page {{ page }} of {{ pages }}</span>
    {% if page < pages %}
        <a class="pagination__link" href="{{ path('customer_index', {page: page + 1}) }}" rel="next">Next</a>
    {% else %}
        <span class="pagination__link" aria-hidden="true">Next</span>
    {% endif %}
</nav>
```

A link (`.pagination__link` with an `href`) has the 3:1 outline of a control and a 2.75rem height; the dead end has a decorative edge and muted text, still 7:1. With more than one pagination on a page, give each `nav` its own `aria-label`. Keep the filters of the list in the URLs (`?q=ana&status=active&page=3`).

### Tables

#### `.table`, `.table-wrap`

Data tables: muted headers, a line between rows, zebra rows. No sorting UI. Wrap the table in `.table-wrap` when it may be wider than the screen: it scrolls sideways instead of breaking the page.

```html
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Email</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
            <tr>
                <td>ana@example.com</td>
                <td><span class="badge badge--success">Active</span></td>
                <td>
                    <form class="inline" method="post" action="/users/1/block">
                        <button class="btn btn--danger btn--small">Block</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

#### `.filter-bar`, `.filter-bar__field`, `.filter-bar__field--search`, `.filter-bar__actions`

Search and filters above a table: a GET form laid out in a row that wraps. Each `.filter-bar__field` is a label above its control and takes at least 12rem; `.filter-bar__field--search` takes twice the room of the others. `.filter-bar__actions` holds the buttons, lined up with the bottom of the fields. On narrow screens (30rem and below) every field and the actions take the full width, and the buttons share it.

```html
<form class="filter-bar" method="get" action="/customers" role="search" aria-label="Customers">
    <div class="filter-bar__field filter-bar__field--search">
        <label for="filter-q">Search</label>
        <input id="filter-q" name="q" type="search" value="ana">
    </div>
    <div class="filter-bar__field">
        <label for="filter-status">Status</label>
        <select id="filter-status" name="status">
            <option value="">Any</option>
            <option value="active" selected>Active</option>
            <option value="blocked">Blocked</option>
        </select>
    </div>
    <div class="filter-bar__actions">
        <button class="btn btn--secondary">Filter</button>
        <a class="btn btn--secondary" href="/customers">Clear</a>
    </div>
</form>
<div class="table-wrap">
    <table class="table">...</table>
</div>
```

Keep the labels visible: a filter whose purpose is only in a placeholder is lost as soon as it has a value. GET, so the filtered list has a URL that can be shared and reloaded, and `.pagination` links can carry the filters.

### Badges

#### `.badge`, `.badge--success`, `.badge--warning`, `.badge--danger`, `.badge--info`, `.badge--neutral`

A short status label. A `.badge` without a modifier looks like `.badge--neutral`. Colour is never the only signal: the word inside says the status.

```html
<span class="badge badge--success">Active</span>
<span class="badge badge--warning">Pending</span>
<span class="badge badge--danger">Blocked</span>
<span class="badge badge--info">Invited</span>
<span class="badge badge--neutral">Archived</span>
```

### Forms

Labels, fields and the vertical stack need no classes (see *Base elements*).

#### `.field-error`

The error of one field, right below it. Link it to the field with `aria-describedby` and mark the field `aria-invalid="true"`:

```html
<form method="post">
    <label for="name">Name</label>
    <input id="name" name="name" aria-invalid="true" aria-describedby="name-error">
    <p class="field-error" id="name-error">This value should not be blank.</p>

    <label for="role">Role</label>
    <select id="role" name="role"><option>Viewer</option><option>Editor</option></select>

    <label for="notes">Notes</label>
    <textarea id="notes" name="notes"></textarea>

    <button class="btn btn--primary">Save</button>
</form>
```

#### `.btn`, `.btn--primary`, `.btn--secondary`, `.btn--danger`, `.btn--small`

Works on `<button>`, `<input type="submit">` and `<a>`.

- `.btn--primary`: the main action, in the brand colour. A bare `.btn` looks the same, but always write the modifier.
- `.btn--secondary`: a neutral outlined button, for every other action.
- `.btn--danger`: destructive actions, in the suite's red (never the brand's).
- `.btn--small`: compact, for inline actions in table rows and in the nav. Still 32px tall, above the WCAG 2.5.8 minimum target.
- Disabled: the `disabled` attribute on a button, `aria-disabled="true"` on a link. Dimmed to 60% opacity so it reads as unavailable; WCAG exempts inactive controls from contrast (§7).

```html
<button class="btn btn--primary">Save</button>
<button class="btn btn--secondary">Cancel</button>
<button class="btn btn--danger">Delete</button>
<a class="btn btn--secondary btn--small" href="/users/1">View</a>
<button class="btn btn--primary" disabled>Saving...</button>
```

#### `form.inline`

A form that sits in a line instead of stacking: actions in a table row, a sign-out button in the nav, a search box with its button. Its inputs take their natural width.

```html
<form class="inline" method="get" action="/customers">
    <label class="visually-hidden" for="q">Search</label>
    <input id="q" name="q" type="search">
    <button class="btn btn--secondary">Search</button>
</form>
```

### Messages

#### `.flash`, `.flash--success`, `.flash--warning`, `.flash--error`, `.flash--info`

A message about the last action. A `.flash` without a modifier is neutral.

```twig
{% for type, messages in app.flashes %}
    {% for message in messages %}
        <p class="flash flash--{{ type }}" role="status">{{ message }}</p>
    {% endfor %}
{% endfor %}
```

```html
<p class="flash flash--success" role="status">Customer saved.</p>
<p class="flash flash--warning" role="status">The invitation expires tomorrow.</p>
<p class="flash flash--error" role="alert">The customer could not be saved.</p>
<p class="flash flash--info" role="status">An import is running.</p>
```

With the loop above, the flash types the app uses must be `success`, `warning`, `error` or `info`.

#### `.muted`

Secondary text: dates, counters, hints. Still 7:1 (AAA) on the page and on cards.

```html
<p class="muted">Last sign-in 3 days ago</p>
```

#### `.empty-state`

What a list or table shows when it has nothing yet, with an optional way out.

```html
<div class="empty-state">
    <p>No customers yet.</p>
    <a class="btn btn--primary" href="/customers/new">New customer</a>
</div>
```

#### `.visually-hidden`

Hidden on screen, read by screen readers: the label of a search box whose purpose the layout already shows, the header of an actions column.

```html
<label class="visually-hidden" for="q">Search</label>
```

### Dialogs

Native `<dialog>` elements, opened as modals by `yuuu-ui.js` (load it as in §3). The browser does the hard parts: the rest of the page becomes inert, focus moves into the dialog and stays there, Escape closes it.

#### The contract: progressive enhancement

Every dialog **replaces something that already works without JavaScript**:

- a **form modal** replaces a link to a full page: `<a href="/companies/new" data-modal-open="new-company">`. Without the script, or in a browser without `HTMLDialogElement.prototype.showModal`, the link navigates to `/companies/new`, which renders the same form as a page;
- a **confirm dialog** stands between a destructive button and its form: `<button data-confirm="confirm-block">`. Without the script, the form submits directly, with no confirmation.

So the server must not depend on the dialog:

- The form inside a form modal posts to **the same action** as the full page. When validation fails, the server renders the full page with the errors (the dialog is not reopened); on success it redirects as usual.
- A destructive action is a **POST** (never a link), protected by a CSRF token, and **safe to receive without a confirmation**: idempotent (blocking a blocked user, or deleting a deleted record, is a no-op or a 404, never an error page or a second effect) and, where the harm would be serious, reversible (archive or soft-delete, an "undo" flash) or confirmed on a page of its own. The dialog is a convenience against misclicks, not a safeguard.

The attributes the script reacts to:

| Attribute | On | With the script | Without it |
|---|---|---|---|
| `data-modal-open="id"` | a link (or a button) | Opens `<dialog id="id">` with `showModal()` instead of following the link. Ctrl/Cmd/Shift/Alt-clicks and middle clicks keep their browser meaning (new tab...). | The link navigates. |
| `data-modal-close` | any element inside a dialog | Closes the dialog. | On a link, it navigates (a "Cancel" link back to the list); on a `type="button"`, nothing. |
| `data-confirm="id"` | a submit button of a form | Opens the confirm dialog `id` instead of submitting. | The form submits. |
| `data-modal-confirm` | a button inside the confirm dialog | Closes the dialog and submits the original form with `form.requestSubmit(button)`: the original button is the submitter, so its `name`/`value` and `formaction` are sent, and the form's `submit` event and validation run as on a direct click. | Never shown. |

Also, for every dialog opened by the script: a click on the **backdrop** closes it (only for `class="modal"`), and on close, by any means, **focus returns to the trigger**. If the dialog `id` does not exist, the trigger behaves as without the script. Browsers with `<dialog>` but without `requestSubmit()` (Safari before 16) get no confirm dialog: the form submits directly.

#### `.modal`, `.modal__header`, `.modal__body`, `.modal__actions`

A dialog on a card surface, 32rem wide at most, over a dimmed page (`--yuuu-backdrop`). The header holds the title and a close button, the body scrolls when the dialog is taller than the screen, the actions sit at the bottom, aligned right. On narrow screens (30rem and below) it becomes a **sheet** along the bottom edge, full width, with the buttons sharing the row. In dark mode its edge uses `--yuuu-color-border-strong`: the dark surface alone barely stands out from the dimmed page.

A `<form>` that is a direct child of the dialog takes the layout, so it can wrap the body and the actions:

```html
<a class="btn btn--primary" href="/companies/new" data-modal-open="new-company">New company</a>

<dialog class="modal" id="new-company" aria-labelledby="new-company-title">
    <form method="post" action="/companies/new">
        <div class="modal__header">
            <h2 id="new-company-title">New company</h2>
            <button type="button" class="btn btn--secondary btn--small" data-modal-close>Close</button>
        </div>
        <div class="modal__body">
            <label for="new-company-name">Name</label>
            <input id="new-company-name" name="name" required autofocus>
        </div>
        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
            <button class="btn btn--primary">Create</button>
        </div>
    </form>
</dialog>
```

- Use `<div>`s for the parts, not `<header>`/`<footer>`: inside a `<dialog>` those would be announced as the page's banner and content info.
- Name the dialog with `aria-labelledby` pointing at its title.
- Put `autofocus` on the first field; otherwise the browser focuses the first focusable element (here, the Close button).
- Buttons that only close are `type="button"`: a bare `<button>` inside the form would submit it.
- Render the form with the same Twig partial as the full page, with its own `id`s: the dialog is in the page even while closed, and two fields with the same `id` break their labels.

#### `.modal--confirm`

The confirmation before a destructive action: narrower (26rem), no lines between the parts. Give it `role="alertdialog"`, a title and a description, and put `autofocus` on **Cancel**, so a stray Enter does not confirm:

```html
<form class="inline" method="post" action="/users/1/block">
    <input type="hidden" name="_token" value="...">
    <button class="btn btn--danger btn--small" data-confirm="confirm-block">Block</button>
</form>

<dialog class="modal modal--confirm" id="confirm-block" role="alertdialog"
        aria-labelledby="confirm-block-title" aria-describedby="confirm-block-text">
    <div class="modal__header">
        <h2 id="confirm-block-title">Block this user?</h2>
    </div>
    <div class="modal__body">
        <p id="confirm-block-text">They are signed out everywhere and cannot sign in until unblocked.</p>
    </div>
    <div class="modal__actions">
        <button type="button" class="btn btn--secondary" data-modal-close autofocus>Cancel</button>
        <button type="button" class="btn btn--danger" data-modal-confirm>Block</button>
    </div>
</dialog>
```

One confirm dialog can serve every row of a table: the script remembers which button opened it and submits that button's form. Use one dialog per row only when the question names the row ("Block ana@example.com?"). Keep the confirm dialog **outside** the form it confirms.

## 6. Dark mode

Automatic, from the operating system's preference (`prefers-color-scheme: dark`). Every colour token has a dark value, and `color-scheme: light dark` makes the browser draw native widgets (checkboxes, scrollbars, date pickers) in the matching scheme. There is no manual toggle yet.

An app that overrides tokens decides whether its values apply to both schemes or only one (see §4).

## 7. Accessibility and contrast

**Policy: WCAG AA required, AAA targeted where reasonable.**

- **Text** must reach **4.5:1** (WCAG 1.4.3, AA): the suite fails below it, at rest and on hovered or pressed buttons.
- **AAA, 7:1** (WCAG 1.4.6) is the target for the default values wherever it does not spoil the neutral look. It is not a gate: the suite does not fail between 4.5:1 and 7:1. Today every default text pair **at rest** reaches it, in both schemes.
- **Non-text** (control outlines, the invalid border, the focus ring) must reach **3:1** (WCAG 1.4.11). WCAG has no AAA level for it.

`tests/Unit/Styles/StylesheetContrastTest.php` reads the real token values from the stylesheet and checks every pair the CSS paints together, in both schemes. It also fails when a token is painted as a text colour or a background without a pair in its lists, and when a `brightness()` filter is not checked. Current values (AAA = 7:1 or more):

| Text pair | Light | Dark | Required | AAA |
|---|---|---|---|---|
| `text` on `bg` | 15.31:1 | 15.68:1 | 4.5 | yes |
| `text` on `surface` | 16.56:1 | 14.46:1 | 4.5 | yes |
| `text-muted` on `bg` | 7.05:1 | 7.79:1 | 4.5 | yes |
| `text-muted` on `surface` | 7.63:1 | 7.18:1 | 4.5 | yes |
| `danger-text` on `surface` (`.field-error`) | 7.71:1 | 8.98:1 | 4.5 | yes |
| `danger-text` on `bg` | 7.13:1 | 9.73:1 | 4.5 | yes |
| `success-text` on `success-bg` | 7.07:1 | 10.14:1 | 4.5 | yes |
| `warning-text` on `warning-bg` | 7.21:1 | 10.45:1 | 4.5 | yes |
| `danger-text` on `danger-bg` | 7.10:1 | 8.98:1 | 4.5 | yes |
| `info-text` on `info-bg` | 7.05:1 | 9.88:1 | 4.5 | yes |
| `neutral-text` on `neutral-bg` | 8.41:1 | 9.90:1 | 4.5 | yes |
| `text` on `success-bg` (a link in a flash) | 15.70:1 | 13.61:1 | 4.5 | yes |
| `text` on `warning-bg` | 15.87:1 | 13.41:1 | 4.5 | yes |
| `text` on `danger-bg` | 15.23:1 | 14.46:1 | 4.5 | yes |
| `text` on `info-bg` | 15.41:1 | 14.50:1 | 4.5 | yes |
| `text` on `neutral-bg` | 15.03:1 | 12.60:1 | 4.5 | yes |
| `primary-text` on `primary` (defaults) | 16.56:1 | 15.67:1 | 4.5 | yes |
| `accent-text` on `accent` (defaults) | 7.63:1 | 7.78:1 | 4.5 | yes |
| `danger-solid-text` on `danger-solid` | 7.71:1 | 7.09:1 | 4.5 | yes |

| Hovered or pressed button (text and background dimmed) | Light | Dark | Required | AAA |
|---|---|---|---|---|
| `.btn--primary` hovered, `brightness(0.92)` | 14.22:1 | 13.19:1 | 4.5 | yes |
| `.btn--primary` pressed, `brightness(0.85)` | 12.36:1 | 11.39:1 | 4.5 | yes |
| `.btn--danger` hovered, `brightness(0.92)` | 7.21:1 | 6.07:1 | 4.5 | light only |
| `.btn--danger` pressed, `brightness(0.85)` | 6.70:1 | 5.35:1 | 4.5 | no |
| `.btn--secondary` pressed (text dimmed, on `surface`) | 17.44:1 | 10.28:1 | 4.5 | yes |
| `.btn--secondary` pressed (text dimmed, on `bg`) | 16.13:1 | 11.15:1 | 4.5 | yes |

The danger button stays below 7:1 while hovered or pressed on purpose: holding 7:1 under the filter would need a much darker red in light mode and a pale salmon in dark mode, and the state lasts only as long as the pointer or the press.

| Non-text pair | Light | Dark | Required |
|---|---|---|---|
| `border-strong` on `surface` (field outline, button ring) | 3.35:1 | 3.38:1 | 3 |
| `border-strong` on `bg` | 3.09:1 | 3.67:1 | 3 |
| `danger-text` on `surface` (invalid field border) | 7.71:1 | 8.98:1 | 3 |
| `danger-text` on `bg` | 7.13:1 | 9.73:1 | 3 |
| `danger-solid` on `surface` (danger button edge) | 7.71:1 | 6.55:1 | 3 |
| `danger-solid` on `bg` | 7.13:1 | 7.10:1 | 3 |
| `text` on `surface` (focus ring) | 16.56:1 | 14.46:1 | 3 |
| `text` on `bg` | 15.31:1 | 15.68:1 | 3 |

Not tested, on purpose:

- **Disabled controls** (`disabled`, `aria-disabled="true"`): WCAG 1.4.3 and 1.4.6 exempt inactive components. They are dimmed to 60% opacity so they read as unavailable, which takes them below 4.5:1. Do not put information that must be read only in a disabled control.
- **Placeholders**: the base does not style `::placeholder`, so the browser's default applies, which is usually below 7:1. Use a `<label>` for anything the user must read, never only a placeholder.
- **`.muted` inside a flash or a badge**: `.muted` is tested on the page and on cards. On the tinted backgrounds of flashes it ranges from 6.26:1 to 7.31:1 (AA, not always AAA).
- The `.card` accent stripe: an ornament, it carries no information.
- **The edge of a `.modal` against the dimmed page.** A dialog's outline is not a control and WCAG 1.4.11 does not ask for 3:1, but it must still be visible. For the record, with the default backdrop: in light mode the white surface against the dimmed page is 4.19:1; in dark mode the surface alone would be 1.17:1, so the dialog takes the `border-strong` edge there, 3.95:1 against the dimmed page.

**The components of 0.3 add no new colour pair.** The dialogs, the breadcrumb, the pagination and the filter bar paint pairs already in the tables above: `text` on `surface` inside a `.modal`; `text` and `text-muted` on `bg` or `surface` for the breadcrumb, the pagination indicator and its dead ends; `border-strong` on `bg` or `surface` for the ring of a `.pagination__link`; the focus ring on `surface` inside dialogs (on a focused `.modal` itself the ring is drawn inside its edge, so it never falls on the backdrop). The backdrop carries no text.

Also: a visible focus ring everywhere (`:focus-visible`), button borders in forced-colours (Windows high contrast) mode, no transitions under `prefers-reduced-motion`, 44px controls (32px for `.btn--small`; `.pagination__link` is 44px too). Dialogs are native `<dialog>` modals: focus is moved in and kept in by the browser, the page behind is inert, Escape closes, and the script returns focus to the trigger.

## 8. Content Security Policy

The stylesheet loads nothing: no fonts, no images, no `@import`. `tests/Unit/Styles/StylesheetHygieneTest.php` fails the build if a remote `url()`, an `@import` or an `@font-face` appears, or if `!important` shows up anywhere other than `[hidden]` and the reduced-motion reset.

The script loads nothing either, and needs nothing relaxed: it is a file from the app's own origin (no inline `<script>`), attaches its listeners with `addEventListener` (no `onclick=` attributes), never evaluates strings (no `'unsafe-eval'`), never parses HTML and never writes a `style`. `tests/Unit/Scripts/ScriptHygieneTest.php` fails the build if `eval`, `new Function`, `innerHTML` and friends, `.style`, an `on*` handler, a URL, a network call or a global appears, or if the file grows past 3 KiB.

An app using the base and the script needs:

```
style-src 'self'; script-src 'self'
```

No `'unsafe-inline'`, and no `style=` attributes in templates: the classes cover the layout. An app that injects per-request token values (as yuuuID does with each company's colours) needs a nonce on that `<style>` element.

With a **nonce-based** policy that uses `'strict-dynamic'`, browsers ignore `'self'` for scripts: give the script tag the nonce too (`<script src="{{ yuuu_ui_script() }}" nonce="..." defer>`).

## 9. Error pages

`@YuuuUi/error.html.twig` is a whole HTML document on the card layout (`.layout-card`, `.card`): a title, the status code, a message and a button back to the home page. It links the base stylesheet itself and loads no script.

It is a complete page on purpose, not a block of the app's `base.html.twig`: an error page must still render when what failed is the app's layout (a menu that queries the database, a missing user).

### Use it for the app's error pages

Symfony renders `templates/bundles/TwigBundle/Exception/error<code>.html.twig`, falling back to `error.html.twig`, **when debug is off** (in production; in development you get the exception page instead). One file is enough, because the bundle's page picks its texts from the status code:

```twig
{# templates/bundles/TwigBundle/Exception/error.html.twig #}
{% extends '@YuuuUi/error.html.twig' %}

{% block stylesheets %}<link rel="stylesheet" href="{{ asset('css/app.css') }}">{% endblock %}
{% block error_header %}<img src="{{ asset('img/logo.svg') }}" alt="yuuu CRM" height="56">{% endblock %}
```

Add `error403.html.twig`, `error404.html.twig` or `error500.html.twig` next to it, extending the same page, only for the codes that need their own text or action. To see them in development, import the error routes (`config/routes/framework.yaml`, `when@dev: _errors: { resource: '@FrameworkBundle/Resources/config/routing/errors.php', prefix: /_error }`) and open `/_error/404`.

### The texts: English defaults, replaced by blocks

The bundle ships no translations. It has **minimal English defaults**, so that an app that only extends the page gets a correct error page from day one, and every text sits in a block, so an app in another language replaces them with its own translations:

| Block | Default |
|---|---|
| `lang` | `en`: change it together with the texts |
| `title` | The `<title>`: the content of `error_title` |
| `error_header` | Empty: the logo slot, above the title |
| `error_title` | By code: 400 "Bad request", 403 "Access denied", 404 "Page not found", 429 "Too many requests", 503 "Service unavailable", any other 5xx "Something went wrong", any other 4xx the status text ("Gone"). Plain text: it is reused in `<title>` |
| `error_code` | `<p class="muted">Error 404</p>`; empty it to hide the code |
| `error_message` | One sentence per code, as above |
| `error_action` | `<p><a class="btn btn--primary" href="/">Go to the home page</a></p>` |
| `error_body` | The message and the action together |
| `stylesheets` | Empty: the app's own stylesheet, linked after the base |

```twig
{# templates/bundles/TwigBundle/Exception/error.html.twig, a Spanish app #}
{% extends '@YuuuUi/error.html.twig' %}

{% block lang %}es{% endblock %}
{% block error_title %}{{ ('error.' ~ status_code ~ '.title')|trans }}{% endblock %}
{% block error_code %}<p class="muted">{{ 'error.code'|trans({'%code%': status_code}) }}</p>{% endblock %}
{% block error_message %}{{ ('error.' ~ status_code ~ '.message')|trans }}{% endblock %}
{% block error_action %}<p><a class="btn btn--primary" href="{{ path('app_home') }}">{{ 'error.home'|trans }}</a></p>{% endblock %}
```

Rendered by hand (without `status_code`), the page defaults to 500. It **never shows the exception message**, which may carry internal details.

### "Page expired": a stale CSRF token

A form with a CSRF token that sits open too long (or across a deploy that rotated the secret) fails its token check. A Symfony **form** already handles this: it re-renders with a "The CSRF token is invalid" error. The case left is the bare POST button (block, delete, sign out) that has no form to re-render. Do not show it as a generic 403 page: tell the user what happened and how to retry.

```twig
{# templates/page_expired.html.twig #}
{% extends '@YuuuUi/error.html.twig' %}

{% block error_title %}This page has expired{% endblock %}
{% block error_code %}{% endblock %}
{% block error_message %}For your security, forms expire after a while. Go back, reload the page and try again.{% endblock %}
{% block error_action %}<p><a class="btn btn--primary" href="{{ back_url }}">Back</a></p>{% endblock %}
```

```php
if (!$this->isCsrfTokenValid('block-user', $request->getPayload()->getString('_token'))) {
    return $this->render('page_expired.html.twig', [
        'back_url' => $this->generateUrl('user_show', ['id' => $user->getId()]),
    ], new Response(status: Response::HTTP_FORBIDDEN));
}
```

Link back to a URL the app builds, not to the `Referer` header. The status is **403**: there is no standard "expired" code (419 is a Laravel convention), and the request was refused.

## 10. Versioning

[Semantic versioning](https://semver.org/), with git tags `vX.Y.Z`. Before 1.0, a **minor** version (0.1 → 0.2) may break: renaming or removing a class or token, or changing what a class looks like in a way a layout depends on. A **patch** (0.1.0 → 0.1.1) only fixes, without renaming anything. Require it with a caret:

```json
"yuuues/yuuu-ui": "^0.4"
```

JavaScript apps pin an exact git tag instead (§12). A release bumps the version in `package.json`, the `branch-alias` of `composer.json` and the install command of §12 together: `PackageJsonTest` fails while they disagree.

Every class and token documented here is public API, and so are the two Twig functions, the `data-` attributes of the script and the blocks of the error page. Everything else in the files is not.

## 11. Development

```bash
docker run --rm -v "$PWD":/app -w /app darkkyto/yuuu-app-ci-php:8.4-1 composer install
docker run --rm -v "$PWD":/app -w /app darkkyto/yuuu-app-ci-php:8.4-1 bin/verify
```

On Git Bash for Windows, prefix both with `MSYS_NO_PATHCONV=1`.

`bin/verify` is the gate of the bundle, the same one CI runs: php-cs-fixer, PHPStan level 8, and PHPUnit (unit and functional suites). A new class in the stylesheet needs its entry in §5, or the suite fails. A new colour pair needs its line in `StylesheetContrastTest`. A new `data-` attribute in the script needs its line in the table of *Dialogs*, and the script must stay under 3 KiB.

PHPUnit cannot click a dialog: the script's behaviour is checked by hand in a browser (open from a link, Escape, backdrop, Cancel, confirm submits the original form with its button, focus back on the trigger, and nothing intercepted without the script). `ScriptHygieneTest` guards what can be read from the source.

**`tokens.css` is generated, never edited.** Section 1 of `yuuu-base.css` is the one source of truth; after changing a token, regenerate the copy and commit both files:

```bash
docker run --rm -v "$PWD":/app -w /app darkkyto/yuuu-app-ci-php:8.4-1 php bin/build-tokens
```

`TokensStylesheetTest` fails while the committed `tokens.css` differs from what the script would write, and checks that it carries the same colours the contrast test checks, and nothing but tokens.

**The npm side has its own gate**, `bin/verify-package`, the one the `package` job of CI runs. It needs Node 22 and the npm registry, so it runs in a Node image:

```bash
docker run --rm -v "$PWD":/app -w /app node:22-alpine sh bin/verify-package
```

It checks the exact list of files `npm pack` ships, then copies `tests/npm` (a throwaway Vite + Preact app with a locked set of dependencies) to a temporary directory, installs the packed tarball into it with scripts off, and builds two single-file pages, one importing `tokens.css` and one `base.css`. It fails if the two subpaths do not resolve through `exports` (or a path outside them does), if either CSS file does not parse strictly (lightningcss), if a page is not one self-contained HTML file (a `src=`, an `href=`, a `<link>`, an `@import`, a `url()`, a `fetch()` or a dynamic `import()`), if the light or dark tokens are missing from it, or if the app's brand override does not come after the defaults. The working tree is never touched.

## 12. Using yuuu-ui from a JavaScript app (Vite/Preact)

The same CSS, imported instead of linked: Vite bundles it with the app, and a single-file build inlines it into the HTML. Nothing PHP is involved.

### Install

From the git tag, with no registry and no credentials (the repository is public):

```bash
npm i github:yuuues/yuuu-ui#v0.4.0
# or
pnpm add github:yuuues/yuuu-ui#v0.4.0
```

`package.json` gets `"@yuuues/yuuu-ui": "github:yuuues/yuuu-ui#v0.4.0"` and the lockfile pins the commit. The package ships the two CSS files (plus its README, LICENSE and `package.json`), and **nothing runs on install**: the CSS is committed, built, so it works with `ignore-scripts=true`. Both npm and pnpm need **`git`** on the machine to resolve the tag; slim images such as `node:22-alpine` lack it (`apk add git`).

The package is `private`: it is never published to a registry, the git tag is the release.

### Import

```js
// main.jsx
import '@yuuues/yuuu-ui/tokens.css'; // the tokens only
// or
import '@yuuues/yuuu-ui/base.css';   // the whole base: the tokens and the classes of §5

import './app.css';                  // the app's own layer, ALWAYS after
```

| Import | File | What it brings |
|---|---|---|
| `@yuuues/yuuu-ui/tokens.css` | `public/css/tokens.css` | Every `--yuuu-*` custom property of §4, with its dark value, and `color-scheme: light dark`. Styles no element. |
| `@yuuues/yuuu-ui/base.css` | `public/css/yuuu-base.css` | Exactly what Twig apps link: the tokens plus the base elements and the classes of §5. |

Import **one** of the two: `base.css` already contains `tokens.css`. Take `tokens.css` when the app writes its own components and only wants the palette, dark mode, type and spacing to match the suite; take `base.css` to use the classes (`.btn`, `.card`, `.table`...). Only these two subpaths are public: `exports` refuses `@yuuues/yuuu-ui/public/css/...`.

`yuuu-ui.js` is not part of the package: it wires `data-` attributes for server-rendered pages, and in a Preact app the component owns its dialogs (`ref.current.showModal()`). The `.modal` classes of `base.css` work on any `<dialog>`.

### Single-file builds

With [`vite-plugin-singlefile`](https://github.com/richardtallent/vite-plugin-singlefile), the imported CSS lands in an inline `<style>` of the one HTML file: no `<link>`, nothing fetched. yuuu-ui itself loads nothing (no fonts, no `url()`, no `@import`, §8), so the page works offline.

```js
// vite.config.js
import { defineConfig } from 'vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

export default defineConfig({
    plugins: [/* the app's own plugins (Preact...), */ viteSingleFile()],
    build: {
        // Vite's modulepreload polyfill contains a fetch(). A single file
        // has nothing to preload: leave it out, so a review of the page
        // finds no network call at all.
        modulePreload: { polyfill: false },
    },
});
```

`bin/verify-package` builds exactly this, from the packed tarball, on every CI run (§11). A single-file page served under a CSP needs hashes for its inline `<style>` and `<script>`: `'self'` does not cover inline code.

### The app's brand

Exactly as in §4, in the app's own CSS **imported after** yuuu-ui (Vite keeps the import order, so the later `:root` rule wins, in both colour schemes):

```css
/* src/app.css */
:root {
    --yuuu-color-primary: #4055ff;
    --yuuu-color-primary-text: #ffffff;
}

@media (prefers-color-scheme: dark) {
    :root {
        --yuuu-color-primary: #8c99ff;
        --yuuu-color-primary-text: #111111;
    }
}
```

The app answers for the contrast of its own pair (§4, *Brand tokens*). Status colours are not meant to be overridden: an error has to look like an error in every app.

### Updating

**Apps never edit yuuu-ui**: not the files in `node_modules`, not a copy pasted into the app, not a fork. A change to a token or a class is made here, tested by the gates of §11 and released as a new tag; the app then moves to it on purpose:

```bash
npm i github:yuuues/yuuu-ui#vX.Y.Z
```

and commits `package.json` and the lockfile. Before 1.0 a minor version may break (§10): read the release notes. What only one app needs belongs in that app's own CSS, with its own class prefix.
