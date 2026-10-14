# yuuu-ui

The shared base stylesheet of the **yuuu** suite, shipped as a Symfony bundle: yuuuID's screens and operator panel, the yuuu CRM back-office, and every new app started from the skeleton look the same and stay accessible without copying CSS around.

- One file, `yuuu-base.css`: design tokens as CSS custom properties, a small set of BEM-light classes, automatic dark mode.
- **Neutral** by design. Each app brings its personality through four brand tokens.
- **WCAG AA** contrast, tested: 4.5:1 for text, 3:1 for control outlines, in light *and* dark mode.
- **No external resources**: no web fonts, no CDN, no remote `url()`, no `@import`. Works under CSP `style-src 'self'`, with no inline styles needed.
- A Twig function, `yuuu_ui_stylesheet()`, that links the file with a content fingerprint for cache busting, computed once when the container is compiled.

Requires **PHP 8.4** and **Symfony 8.1**.

---

## 1. Install

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

The bundle has no configuration. It needs **TwigBundle** for the Twig function. Without TwigBundle the container still compiles, but the function does not exist.

## 2. Publish the asset

The stylesheet lives inside the package, at `public/css/yuuu-base.css`. `assets:install` copies it to the app's public directory, where the web server can serve it:

```bash
php bin/console assets:install public
# -> public/bundles/yuuuui/css/yuuu-base.css
```

Symfony Flex runs this automatically after every `composer install` and `composer update`, through the `auto-scripts` of the app's `composer.json`.

> **Deploys that run `composer install --no-scripts` must publish it explicitly.** `--no-scripts` skips `auto-scripts`, so nothing copies the file and the site loads without styles. Add this to the deploy script, after `composer install`:
>
> ```bash
> php bin/console assets:install public --no-debug
> ```
>
> Use the default **hard copy**. Do not pass `--symlink` or `--relative`: a symlink points into `vendor/`, and a release-based deploy that swaps or prunes `vendor/` leaves it dangling.

Run it on **every deploy**, not only on the first one. The fingerprint in the URL is computed from the file inside `vendor/`. If a new version of the package arrives and the copy in `public/` is not refreshed, the URL changes but browsers download the old file under the new name.

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
</head>
<body>
    {% block body %}{% endblock %}
</body>
</html>
```

`yuuu_ui_stylesheet()` returns `/bundles/yuuuui/css/yuuu-base.css?v=<fingerprint>`, where the fingerprint is the first 10 hex digits of the xxh3 hash of the stylesheet. It:

- **changes only when the content changes**, so browsers can cache the file for as long as you like (`Cache-Control: max-age=31536000, immutable` on `/bundles/yuuuui/` is safe as long as every page links it through the function, never by its bare path);
- **is computed once, when the container is compiled**, not per request. Rendering the function is a string concatenation. In debug mode the stylesheet is a resource of the container, so editing it rebuilds the container and the fingerprint. In production the deploy's `cache:clear` does it;
- is **root-relative**. An app served from a sub-path (`https://example.com/app/`) is not supported in v0.1.0.

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
| `--yuuu-color-accent` | `#5b636e` | `#a0a7b1` | The stripe on top of `.card` |
| `--yuuu-color-accent-text` | `#ffffff` | `#111111` | Text on the accent colour (for the app's own use) |

**You answer for the contrast of your own pair.** The base tests its defaults; it cannot test yours. Text on the primary colour must reach 4.5:1. Pick the text colour with the WCAG relative luminance, as yuuuID does for each company's colours: white or near-black (`#111111`), whichever contrasts more. For the CRM, `#ffffff` on `#4055ff` gives **5.30:1**.

The brand colour is only ever used as a **background** with its own text token, and as an ornament. Links, focus rings, form outlines and status colours stay neutral on purpose: a brand colour that reads well on white disappears on the dark background, and the other way round.

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
- Disabled: the `disabled` attribute on a button, `aria-disabled="true"` on a link.

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

Secondary text: dates, counters, hints. Still 4.5:1.

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

## 6. Dark mode

Automatic, from the operating system's preference (`prefers-color-scheme: dark`). Every colour token has a dark value, and `color-scheme: light dark` makes the browser draw native widgets (checkboxes, scrollbars, date pickers) in the matching scheme. There is no manual toggle in v0.1.0.

An app that overrides tokens decides whether its values apply to both schemes or only one (see §4).

## 7. Accessibility and contrast

`tests/Unit/Styles/StylesheetContrastTest.php` reads the real token values from the stylesheet and checks every pair the CSS paints together, in both schemes. Current values:

| Pair | Light | Dark | Minimum |
|---|---|---|---|
| `text` on `bg` | 15.31:1 | 15.68:1 | 4.5 |
| `text` on `surface` | 16.56:1 | 14.46:1 | 4.5 |
| `text-muted` on `bg` | 5.62:1 | 7.79:1 | 4.5 |
| `text-muted` on `surface` | 6.08:1 | 7.18:1 | 4.5 |
| `danger-text` on `surface` (`.field-error`) | 6.57:1 | 8.98:1 | 4.5 |
| `danger-text` on `bg` | 6.08:1 | 9.73:1 | 4.5 |
| `success-text` on `success-bg` | 5.40:1 | 10.14:1 | 4.5 |
| `warning-text` on `warning-bg` | 7.21:1 | 10.45:1 | 4.5 |
| `danger-text` on `danger-bg` | 6.05:1 | 8.98:1 | 4.5 |
| `info-text` on `info-bg` | 5.57:1 | 9.88:1 | 4.5 |
| `neutral-text` on `neutral-bg` | 8.41:1 | 9.90:1 | 4.5 |
| `primary-text` on `primary` (defaults) | 16.56:1 | 15.67:1 | 4.5 |
| `accent-text` on `accent` (defaults) | 6.08:1 | 7.78:1 | 4.5 |
| `danger-solid-text` on `danger-solid` | 6.57:1 | 6.78:1 | 4.5 |
| `border-strong` on `surface` (field outline, button ring) | 3.35:1 | 3.38:1 | 3 |
| `border-strong` on `bg` | 3.09:1 | 3.67:1 | 3 |
| `danger-solid` on `surface` (danger button edge) | 6.57:1 | 6.25:1 | 3 |
| `danger-solid` on `bg` | 6.08:1 | 6.78:1 | 3 |

Also: a visible focus ring everywhere (`:focus-visible`), button borders in forced-colours (Windows high contrast) mode, no transitions under `prefers-reduced-motion`, 44px controls (32px for `.btn--small`).

## 8. Content Security Policy

The stylesheet loads nothing: no fonts, no images, no `@import`. `tests/Unit/Styles/StylesheetHygieneTest.php` fails the build if a remote `url()`, an `@import` or an `@font-face` appears, or if `!important` shows up anywhere other than `[hidden]` and the reduced-motion reset. An app using only the base needs:

```
style-src 'self'
```

No `'unsafe-inline'`, and no `style=` attributes in templates: the classes cover the layout. An app that injects per-request token values (as yuuuID does with each company's colours) needs a nonce on that `<style>` element.

## 9. Versioning

[Semantic versioning](https://semver.org/), with git tags `vX.Y.Z`. Before 1.0, a **minor** version (0.1 → 0.2) may break: renaming or removing a class or token, or changing what a class looks like in a way a layout depends on. A **patch** (0.1.0 → 0.1.1) only fixes, without renaming anything. Require it with a caret:

```json
"yuuues/yuuu-ui": "^0.1"
```

Every class and token documented here is public API. Everything else in the file is not.

## 10. Development

```bash
docker run --rm -v "$PWD":/app -w /app darkkyto/yuuu-app-ci-php:8.4-1 composer install
docker run --rm -v "$PWD":/app -w /app darkkyto/yuuu-app-ci-php:8.4-1 bin/verify
```

On Git Bash for Windows, prefix both with `MSYS_NO_PATHCONV=1`.

`bin/verify` is the single gate, the same one CI runs: php-cs-fixer, PHPStan level 8, and PHPUnit (unit and functional suites). A new class in the stylesheet needs its entry in §5, or the suite fails. A new colour pair needs its line in `StylesheetContrastTest`.
