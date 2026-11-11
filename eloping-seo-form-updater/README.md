# Eloping SEO Form Bulk Updater (v1.1 — Hero + CTA)

## What it does (kya karta hai)

Every SEO landing page (the 151 pages that use the **SEO Landing** page template —
the ones listed in your `user-sitemap`) has two **"Form Shortcode"** fields in the
page editor:

1. **Hero tab → "Form Shortcode"** (hero banner form)
2. **CTA tab → "Form Shortcode"** (bottom "Let's Start Planning" band form)

Both currently hold this Contact Form 7 shortcode:

```
[contact-form-7 id="db74934" title="SEO Forms"]
```

This plugin replaces **both fields on every SEO landing page — in one click** —
with the **Studio Ninja iframe embed** that the **Contact Us** page uses, so the
enquiry form stays the same and consistent everywhere (hero aur bottom dono jagah,
har SEO page pe).

You had already done the hero manually on one page
(`lesbian-friendly-elopement-venues-australia`) — that page's hero field is
auto-detected and skipped (your pasted value stays as-is); its CTA field gets
the iframe like all the others.

## Scope — exactly and only this

| Changed | Not touched |
|---|---|
| `seo_hero_form` meta (Hero "Form Shortcode") on SEO Landing pages | Header / footer menus |
| `seo_cta_form` meta (CTA "Form Shortcode") on SEO Landing pages | Every other ACF field on those pages |
| | All other pages (Home, About, Contact Us, Pricing, destination pages, …) |
| | Any theme file, CSS, layout, or design |

No theme code is modified at all — the SEO Landing template already renders
whatever is in those fields (that's why your manual page works).

## Install & run (3 steps)

1. **Upload**: WP Admin → **Plugins → Add New → Upload Plugin** →
   choose `eloping-seo-form-updater.zip` → **Install Now → Activate**.

2. **Open the new menu item "SEO Form Update"** in the left sidebar
   (top level, forms icon). You'll see a status table:

   | Form field | Already iframe | Still CF7 | Empty | Will be updated |
   |---|---|---|---|---|
   | Hero banner (seo_hero_form) | 1 | 150 | 0 | **150** |
   | Bottom CTA band (seo_cta_form) | 0 | 151 | 0 | **151** |
   | 151 SEO landing pages in total | | | | **301 field(s)** |

   The exact iframe code that will be written is shown on the same screen —
   verify it matches the Contact Us page before you click.

3. Click **"Run Update Now"** and confirm. Done — every SEO landing page now
   shows the Contact-page iframe form in **both** the hero banner and the bottom
   CTA band.

## Safety features

- **One-click Revert**: before changing anything, the plugin saves a backup of
  every original value. The same screen shows a **"Revert to Originals"**
  button — one click restores all 301 fields exactly as they were.
- **Idempotent**: running it again changes nothing (cells that already have
  the iframe are skipped).
- **Precise targeting**: it only queries pages with
  `_wp_page_template = template-seo-landing.php` and only writes those two
  form-field meta keys. Nothing else in the database is touched.
- **Storage-convention aware**: your site stores these ACF fields as plain
  post meta (`seo_hero_form` / `seo_cta_form`, no `_acf_` prefix) — the plugin
  writes to those exact keys, the same way your manual edit did. (It also
  understands standard ACF storage `_acf_{name}` as a fallback.)
- **Runs only on your explicit click** (admin button + nonce + `manage_options`
  permission). Never runs on activation or in the background.

## Good to know (baad me kya hoga)

- After the update, each SEO page contains the **same Studio Ninja form twice**
  (hero + bottom CTA). Both are independent iframe forms from the same
  studioninja app — both display in full (the embed has a fixed height of
  693px) and both submit to the same destination as the Contact Us page.
- If you use a **page-cache plugin** (WP Rocket, W3 Total Cache, LiteSpeed,
  host-level cache…), **clear/regenerate the cache** so visitors see the new
  forms on all pages.
- Spot-check 2–3 pages on the front end (e.g. `/lgbtq-elopement-byron-bay/`,
  `/all-inclusive-elopement-packages-for-two/`) — hero **and** bottom CTA should
  now show the Studio Ninja form inside the same cards/styling as before.
  Design/layout unchanged.
- When you're happy, you can **deactivate and delete the plugin** — it leaves
  no trace other than the form field values it wrote. The rollback backup is
  stored in one WP option (`eloping_seo_form_updater_backup`) and stays until
  you use Revert; deleting it just removes the "undo" ability, nothing more.

## File

- `eloping-seo-form-updater.php` — the complete plugin (single file,
  commented).
