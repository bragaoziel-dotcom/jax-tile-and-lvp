# Jax Tile & LVP

Lead-generation website for Jax Tile & LVP, a Braga Remodeling brand operated by MB FLOOR AND TILE INSTALLATIONS LLC.

## Production source

`main` is the authoritative production source. Every push to `main` is validated by the **Site Quality** GitHub Actions workflow before a deploy-only artifact is packaged.

Current tracking infrastructure:

- Google Tag Manager: `GTM-W6P5NQ2X`
- Google Analytics 4: `G-MQ4DLFWW0F`
- Google Ads tag: `AW-18023260184`
- Site brand context: `jax_tile_lvp`

Legacy/cross-business tracking IDs are blocked by CI.

## Included

- LVP-only sales landing page: Promo $3.99 / Plus $4.49 / Premium from $4.99 per sq ft installed (500 sq ft minimum), 60-second price quiz, add-on price table; English-only (under 500 sq ft = custom quote, no price shown)
- Real Braga Remodeling LVP project imagery stored locally in Jax assets
- Optimized WebP brand logo for visible page use
- Call and SMS CTAs using `(904) 520-1994`
- Lead form emailing `braga@bragaremodeling.com`
- Honeypot, time trap, US phone + 5-digit ZIP validation (320xx/322xx = service area), duplicate suppression and conversion-safe response (out-of-area/no-JS leads are emailed but not counted)
- Braga AI chat disabled (UI removed, `api/braga-ai.php` returns 410) until a Gemini key and an LVP-only prompt exist
- Service pages, service-area page, project gallery and privacy page
- `robots.txt`, `sitemap.xml`, canonical URLs and structured data
- `.htaccess`: 301 www → apex, 301 `/tile-installation-jacksonville/` → `/`, 301 `/portugues/` (and `/pt/`, `/es/`) → `/`, blocks `/docs`, `/scripts`, `/.github`, `README.md`

## Placeholders to fill (search the repo for `TODO(Oziel)`)

- `assets/site.js`: `BOOKING_URL` (Calendly/Zoho Bookings) and `INSTAGRAM_URL` (Instagram cards/links stay hidden until set)
- `assets/meta-pixel.js`: `META_PIXEL_ID` (nothing loads while empty)
- Before/after photos, insurance proof, warranty text, Google reviews, LVP specs and the real in-stock sq ft count (hidden until provided)

## Hostinger deployment

Deploy the generated GitHub Actions artifact `jax-tile-and-lvp-production` to the document root for `jaxtileandlvp.com`. The artifact contains the validated production files only and excludes repository tooling/documentation.

PHP `mail()` must be enabled or replaced with the site's authenticated mail provider.

Add the Gemini key outside public files. In WordPress/PHP hosting, keep it server-side; for example in `wp-config.php`:

```php
define('JAX_GEMINI_API_KEY', 'YOUR_KEY');
```

Never commit the real key to GitHub. Browsers only call `api/braga-ai.php`.

## Search/indexing

Google Search Console should use a dedicated Jax property (`sc-domain:jaxtileandlvp.com`) rather than a Braga Remodeling or cleaning-company property. After the domain is live and verified, submit `https://jaxtileandlvp.com/sitemap.xml` and inspect the primary service URLs.

## Conversion events

The site emits privacy-safe events for:

- `phone_click`
- `sms_click`
- `email_click`
- `cta_click`
- `lead_form_submit`
- `generate_lead`
- `lead_form_error`
- `calculator_start`, `quiz_step`, `quiz_complete`, `calculator_estimate` (sq ft bucket only)
- `booking_click`, `social_click`

If `META_PIXEL_ID` is set, `generate_lead` → `Lead`, phone/SMS → `Contact`, booking → `Schedule`.

`generate_lead` is emitted only after the backend returns `trackConversion: true`. UTM/GCLID attribution stays with the lead backend and is not included in analytics event payloads.

Google Ads conversion actions for Jax must remain separate from Braga Remodeling conversion labels.

## Quality controls

CI validates required pages/assets, metadata, canonical/H1 coverage, internal links, JSON-LD, robots/sitemap consistency, production tracking IDs, absence of legacy IDs, analytics PII safety, optimized logo usage and local project-image assets.

Do not add invented reviews, certifications, guarantees, licenses, thin doorway pages or copied competitor content.