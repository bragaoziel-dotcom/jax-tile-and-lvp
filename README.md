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

- Responsive local-service website focused on Tile and Vinyl/LVP
- Real Braga Remodeling LVP and tile project imagery stored locally in Jax assets
- Optimized WebP brand logo for visible page use
- Call and SMS CTAs using `(904) 520-1994`
- Lead form emailing `braga@bragaremodeling.com`
- Honeypot, input validation, duplicate suppression and conversion-safe response
- Braga AI backed by Gemini and restricted to Tile and Vinyl/LVP
- Service pages, service-area page, project gallery, Portuguese page and privacy page
- `robots.txt`, `sitemap.xml`, canonical URLs, hreflang and structured data

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

`generate_lead` is emitted only after the backend returns `trackConversion: true`. UTM/GCLID attribution stays with the lead backend and is not included in analytics event payloads.

Google Ads conversion actions for Jax must remain separate from Braga Remodeling conversion labels.

## Quality controls

CI validates required pages/assets, metadata, canonical/H1 coverage, internal links, JSON-LD, robots/sitemap consistency, production tracking IDs, absence of legacy IDs, analytics PII safety, optimized logo usage and local project-image assets.

Do not add invented reviews, certifications, guarantees, licenses, thin doorway pages or copied competitor content.