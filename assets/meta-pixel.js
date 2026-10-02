/*
 * Meta (Facebook/Instagram) Pixel loader for Jax Tile & LVP.
 * TODO(Oziel): paste the NEW Jax Meta Pixel ID below (Meta Events Manager > Data sources).
 * Leave it empty and nothing loads. Never reuse a pixel from another business (CI blocks legacy IDs).
 * Events are fired from /assets/site.js (Lead, Contact, Schedule) with no personal data.
 */
(function (window, document) {
  var META_PIXEL_ID = ''; // TODO(Oziel): Meta Pixel ID, e.g. '123456789012345'
  if (!META_PIXEL_ID || window.fbq) return;
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
  window.fbq('init', META_PIXEL_ID);
  window.fbq('track', 'PageView');
})(window, document);
