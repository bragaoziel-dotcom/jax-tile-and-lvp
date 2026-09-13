# Jax Tile & LVP — Tracking Reference

Last updated: 2026-09-13.

## Shared Braga measurement infrastructure

Jax Tile & LVP intentionally reuses the confirmed Braga Remodeling measurement infrastructure so both brands can be analyzed together while remaining distinguishable by hostname and `site_brand`.

- Google Ads account: Braga Remodeling — customer `598-312-6095`
- Google Ads base tag: `AW-18023260184`
- GA4 measurement ID: `G-MQ4DLFWW0F`
- Google Tag Manager: `GTM-W6P5NQ2X`
- Jax hostname: `jaxtileandlvp.com`
- Jax event marker: `site_brand=jax_tile_lvp`

`assets/google-tracking.js` establishes Consent Mode defaults, configures the Braga Ads/GA4 IDs, loads the Google tag library, then loads the dedicated Braga GTM container.

## Jax events

`assets/site.js` sends non-identifying event metadata to GA4 and the GTM dataLayer:

- `generate_lead` — only after the backend accepts a legitimate lead (`trackConversion === true`)
- `lead_form_submit` — form submit attempt
- `lead_form_error` — failed submission
- `phone_click`
- `sms_click`
- `email_click`
- `cta_click`
- `ai_chat_opened`

Every event includes `site_brand=jax_tile_lvp`, `page_path`, and language. Service name and CTA location may be included when relevant.

## Attribution

UTM parameters and Google click IDs (`gclid`, `gbraid`, `wbraid`) are retained in session storage and passed as hidden form fields to the lead backend/email for attribution. They are intentionally NOT sent as GA4/GTM event parameters.

## No-PII rule

Never send these to GA4, GTM or Google Ads event payloads:

- names
- phone numbers
- email addresses
- ZIP codes or street addresses
- project-detail/free-text fields
- chat messages
- `gclid`, `gbraid`, or `wbraid`

## Google Ads conversions

Do not reuse Braga Remodeling conversion labels for Jax leads. Jax-specific Google Ads conversion actions should be created in customer `598-312-6095`, for example:

- `JAX - Form Lead`
- `JAX - Phone Click`
- `JAX - SMS Click`

Only after their real conversion labels are confirmed should direct Google Ads conversion events be added to the Jax site. Until then, base Ads measurement and GA4 events remain active without mixing Jax leads into Braga-specific paid conversion labels.

## Forbidden legacy / cross-business IDs

These must not be installed in Jax runtime code:

- `GTM-MF6BLDTV`
- `AW-18110072514`
- `G-STY53PJMCS`
- Meta Pixel `1432051588726720`

The historical `GTM-MF6BLDTV` container was documented in the Braga Remodeling repository as contaminated/cross-business and is not the Braga production container.
