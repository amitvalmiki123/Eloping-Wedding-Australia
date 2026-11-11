# Eloping SEO Form Bulk Updater (v1.2 — Hero + CTA, collision-free)

## What it does (kya karta hai)

Every SEO landing page (the 151 pages that use the **SEO Landing** page template —
the ones listed in your `user-sitemap`) has two **"Form Shortcode"** fields in the
page editor:

1. **Hero tab → "Form Shortcode"** (hero banner form)
2. **CTA tab → "Form Shortcode"** (bottom "Let's Start Planning" band form)

This plugin writes the **Studio Ninja iframe embed** — the exact same enquiry form
the **Contact Us** page uses — into **both fields on every SEO landing page in one
click**, so the form stays the same and consistent everywhere.

## v1.2 — why the form looked cut off, and the fix

The embed ships with an auto-height script (iFrameSizer) that tracks iframes **by
their `id` attribute**. v1.1 wrote the *identical* embed into both fields, so every
SEO page had two iframes with the same id (`sn-form-m1co4`). The script then
resized the wrong iframe and the form got clipped at the bottom
(message + submit hidden).

v1.2 fixes this:

- **Hero** field keeps the original embed — byte-identical to the Contact Us page
  (`id="sn-form-m1co4"`).
- **CTA** field gets the *same form* (same `src`, same fields, same submission)
  with only the internal id changed to `sn-form-m1co4-cta` (and its matching
  `data-iframe-id`). No collision, both iframes auto-size correctly.
- **Migration is automatic**: if the v1.1 version already ran (CTA fields hold
  the same-id embed), the next "Run Update Now" rewrites those CTA fields with
  the correct id. Hero fields already correct are skipped. Your manually pasted
  hero value is left as-is.
- A **different** Studio Ninja form (different `src`) on any field is respected
  and never overwritten.

## Scope — exactly and only this

| Changed | Not touched |
|---|---|
| `seo_hero_form` meta (Hero "Form Shortcode") on SEO Landing pages | Header / footer menus |
| `seo_cta_form` meta (CTA "Form Shortcode") on SEO Landing pages | Every other ACF field on those pages |
| | All other pages (Home, About, Contact Us, Pricing, destination pages, …) |
| | Any theme file, CSS, layout, or design |

No theme code is modified at all.

## Install & run

**First time**: WP Admin → **Plugins → Add New → Upload Plugin** →
`eloping-seo-form-updater.zip` → Install → Activate.
**Upgrading from v1.1**: upload the new zip over the old plugin (WP will replace
it) or delete the old plugin first and upload the new one — then activate.

Open the **"SEO Form Update"** menu item (sidebar, forms icon):

- After a v1.1 run you'll see: Hero **151 already correct**, CTA **151 will be
  updated** → click **"Run Update Now"** → confirm. Done — the cut-off is fixed.
- Fresh install: Hero **151** + CTA **151** = **301 fields** to update.
- The screen shows the exact code for each field before you run.

## Safety features

- **One-click Revert**: the backup from the original run (the CF7 shortcodes) is
  kept across upgrades — **"Revert to Originals"** restores all 301 fields
  exactly.
- **Idempotent**: re-running changes nothing.
- **Precise targeting**: only pages with
  `_wp_page_template = template-seo-landing.php`; only those two field keys.
- **Storage-convention aware**: your site stores these ACF fields as plain post
  meta (no `_acf_` prefix) — the plugin writes to those exact keys, and also
  understands standard ACF storage as a fallback.
- **Runs only on your explicit click** (admin button + nonce + `manage_options`).

## After running

- **Clear/regenerate page cache** if you use a caching plugin (WP Rocket, W3TC,
  LiteSpeed, host cache).
- Refresh 2–3 SEO pages — the hero **and** bottom CTA forms should now show in
  full (First Name … Multi-line message … Submit), same as the Contact Us page.
- When you're happy you can **deactivate/delete the plugin** — it leaves no
  trace other than the field values it wrote (backup option:
  `eloping_seo_form_updater_backup`).

## File

- `eloping-seo-form-updater.php` — the complete plugin (single file, commented).
