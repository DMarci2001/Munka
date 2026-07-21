# Admin sidebar: scrollable + sticky, auto-scroll to the active menu item

## Context

The admin panel's ("bejelentkező felület") left sidebar (`#mainmenucolumn` in
`library/AdminPage.php`) currently has no height constraint at all — it just
grows with its content and scrolls together with the whole page. There's also
no logic to auto-expand a collapsed submenu when the active page happens to
live inside it, or to bring the active/selected menu item into view.

This has become more noticeable now that the menu has grown (e.g. the new
"Eszköznyilvántartás" section with 4 submenu items added earlier). On a long
menu, the highlighted/active item can end up scrolled out of view, and if it's
inside a submenu that was never manually expanded, it's not even visible at
all (submenus only open when the user manually clicks — there's no
open-if-active logic today).

Goal: make the sidebar independently scrollable and **sticky** (stays on
screen while the main content scrolls — confirmed with the user), and make
sure the currently active menu item is always auto-expanded (if inside a
collapsed submenu) and scrolled into view within the sidebar on page load.

## Current behavior (found during exploration)

- `library/AdminPage.php`
  - `_menuColumn()` (~line 187) renders the menu from `$this->adminMenu`
    (populated by `getAdminMenu()`). Active item gets class
    `mainmenuitem_aktiv` / `mainmenuitem_sub_aktiv`. Submenus (`pageid == '#'`
    parents) render into `<div id='submenu{$menu["id"]}">`, hidden via inline
    `display:none` **unless** `$_SESSION["opensubmenu"][$menu["id"]]` is set
    (set only when the user manually clicks to expand — see
    `toggleSubMenu()` below). There is currently no check for "does this
    submenu contain the active page" — so directly landing on a submenu page
    (e.g. via deep link) does not auto-expand it.
  - `showPage()` (~line 128) renders:
    `<div id='mainmenucolumn' style='width:{$_SESSION["mainmenuwidth"]};overflow: hidden;transition: all 0.3s ease 0s;'>`
    — the `overflow: hidden` shorthand hides both axes; this is what needs to
    change (need `overflow-x: hidden` only, so a new `overflow-y: auto` CSS
    rule can take effect without conflict).
- `public/admin/css/index.css`
  - `.menuoszlop` (line ~157): `box-sizing:border-box; min-height:500px; width:0px; background-color:#eee;` — no height cap, no position.
  - No existing rule for `#mainmenucolumn` at all.
- `public/admin/js/ajax.js`
  - `initHamburgerIcon()` (~line 5000) toggles `#mainmenucolumn` width
    180px/0px, called from the `$(document).ready(...)` block (line ~33).
  - `toggleSubMenu(id)` (~line 2652) slideToggles `#submenu{id}` and persists
    open/closed state via ajax (`opensubmenu`/`open` POST) to
    `library/AdminAjaxService.php` (~line 560), which stores it in
    `$_SESSION["opensubmenu"][$id]`.

## Implementation

### 1. `library/AdminPage.php` — auto-expand the submenu containing the active page

In `_menuColumn()`, where `$subMenuHtml` is built for a menu item with a
non-empty `submenu` array: before deciding `display:none`, check whether any
child in `$menu["submenu"]` has `pageid == $_GET["page"]`. If so, treat the
submenu as open regardless of the `$_SESSION["opensubmenu"]` flag:

```php
$hasActiveChild = false;
foreach ($menu["submenu"] as $submenuItem) {
    if ($_GET["page"] == $submenuItem["pageid"]) { $hasActiveChild = true; break; }
}
$isOpen = isset($_SESSION["opensubmenu"][$menu["id"]]) || $hasActiveChild;
$subMenuHtml .= "<div id='submenu{$menu["id"]}' style='margin:5px 0px;" . ($isOpen ? "" : "display:none;") . "'>";
```

(Keep the rest of the loop building `$subMenuHtml` unchanged.)

### 2. `library/AdminPage.php` — free up `overflow-y` for the sidebar

Change the `#mainmenucolumn` inline style in `showPage()` from
`overflow: hidden` to `overflow-x: hidden` (drop the y-axis so the new CSS
rule below can apply):

```php
echo "<div id='mainmenucolumn' style='width:{$_SESSION["mainmenuwidth"]};overflow-x: hidden;transition: all 0.3s ease 0s;'>";
```

### 3. `public/admin/css/index.css` — sticky, bounded-height, scrollable sidebar

Add a new rule (near the existing `.menuoszlop` block, ~line 157):

```css
#mainmenucolumn {
	position: sticky;
	top: 0;
	max-height: 100vh;
	overflow-y: auto;
}
```

This makes the sidebar pin to the viewport top as the user scrolls the main
content (sticky, within its containing `<td class="menuoszlop">`, which
spans the full row height), while capping its own height to the viewport and
scrolling its own content independently once the menu is taller than the
screen. `overflow-x: hidden` from the inline style (step 2) still clips
content horizontally during the existing width-collapse animation.

### 4. `public/admin/js/ajax.js` — scroll the active item into view

Add a small function near `toggleSubMenu` (or right after it) and call it
once on document ready, alongside the existing `initHamburgerIcon();` call
(~line 33):

```js
function scrollActiveMenuItemIntoView() {
    var el = document.querySelector(
        "#mainmenucolumn .mainmenuitem_aktiv, #mainmenucolumn .mainmenuitem_sub_aktiv"
    );
    if (el) {
        el.scrollIntoView({ block: "nearest" });
    }
}
```

```js
// inside the existing $(document).ready(...) block, alongside initHamburgerIcon();
scrollActiveMenuItemIntoView();
```

Because `#mainmenucolumn` is now the nearest scrollable ancestor (step 3),
`scrollIntoView({block:"nearest"})` will adjust only the sidebar's internal
`scrollTop` — it will not move the main page scroll position.

## Not in scope / left as-is

- The hamburger width-collapse toggle (`initHamburgerIcon`) — untouched, still
  works the same, just now only affects `overflow-x`.
- Deeper-than-one-level submenus — `_menuColumn()` doesn't render those today
  either; not introducing that capability here, just matching the existing
  one-level rendering when checking for an active child.
- `public/css/index.css` (the separate patient-facing stylesheet with
  near-duplicate `.mainmenuitem`/`.menuoszlop` rules) — not touched; the admin
  panel loads `public/admin/css/index.css`, not this file.

## Verification

1. Load the admin panel (`index.php?page=...`) for a page whose menu entry is
   a top-level item — confirm the sidebar still renders and highlights it as
   before.
2. Navigate directly (paste URL) to one of the new "Eszköznyilvántartás"
   submenu pages (e.g. `index.php?page=eszkozlista`) **without** ever having
   manually expanded that submenu — confirm the submenu is now open
   automatically and the item is highlighted and scrolled into view.
3. Resize the browser window short enough that the full menu doesn't fit —
   confirm the sidebar shows its own scrollbar (not the whole page's) and the
   active item is visible without needing to scroll the main page.
4. Scroll down a long content page — confirm the sidebar stays pinned
   (sticky) at the top of the viewport.
5. Click the hamburger icon — confirm the collapse/expand width animation
   still works as before (no visual glitch from the `overflow-x` change).
6. Click `toggleSubMenu` manually on an unrelated submenu — confirm
   open/close + session persistence still works unaffected.
