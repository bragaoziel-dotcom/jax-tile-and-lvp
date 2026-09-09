# Jax Tile & LVP

WordPress/Hostinger-ready lead generation landing page for `jaxtileandlvp.com`, based on the Braga Remodeling positioning and limited to Tile and Vinyl/LVP.

## Included

- Responsive landing page with the supplied Jax Tile & LVP logo
- Real Braga Remodeling LVP and tile project imagery
- Call CTAs using `(904) 520-1994`
- Lead form emailing `braga@bragaremodeling.com`
- Honeypot, input validation, duplicate suppression and conversion-safe response
- Braga AI backed by Gemini and restricted to Tile and Vinyl/LVP

## Hostinger / WordPress installation

Upload the folder contents to the document root for `jaxtileandlvp.com`, or adapt the markup into a WordPress page template. PHP `mail()` must be enabled or replaced with the site's authenticated mail provider.

Add the Gemini key outside public files. In WordPress, place this in `wp-config.php` above the stop-editing line:

```php
define('JAX_GEMINI_API_KEY', 'YOUR_KEY');
```

Never commit the real key to GitHub. The key remains server-side; browsers only call `api/braga-ai.php`.

Before production, download the two approved Braga project images into `assets/` and update the image URLs so the Jax site does not depend on the Lovable domain.
