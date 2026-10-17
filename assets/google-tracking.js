(function (window, document) {
  if (window.__jaxGoogleTrackingInitialized) return;
  window.__jaxGoogleTrackingInitialized = true;

  // Reuse the confirmed Braga Remodeling measurement infrastructure.
  // Jax-specific paid conversion labels remain separate and are NOT hard-coded here.
  var GOOGLE_ADS_ID = "AW-18023260184";
  var GA4_ID = "G-MQ4DLFWW0F";
  var GTM_ID = "GTM-W6P5NQ2X";

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () {
    window.dataLayer.push(arguments);
  };

  // Keep Consent Mode defaults aligned with Braga Remodeling production.
  window.gtag("consent", "default", {
    ad_storage: "granted",
    ad_user_data: "granted",
    ad_personalization: "granted",
    analytics_storage: "granted"
  });

  window.gtag("consent", "default", {
    ad_storage: "denied",
    ad_user_data: "denied",
    ad_personalization: "denied",
    analytics_storage: "denied",
    wait_for_update: 500,
    region: [
      "AT","BE","BG","HR","CY","CZ","DK","EE","FI","FR","DE","GR","HU","IE","IT","LV","LT","LU","MT","NL","PL","PT","RO","SK","SI","ES","SE","IS","LI","NO","GB","CH","CA"
    ]
  });

  window.gtag("js", new Date());
  window.gtag("config", GOOGLE_ADS_ID);
  window.gtag("config", GA4_ID);

  // Google Ads "Website Calls" (call-tracking number swap). Installed ONCE, here only
  // (not in GTM, not inline). If Google returns a forwarding number (ad visitors only),
  // every visible (904) 520-1994 and every tel: link is swapped; otherwise nothing changes.
  var WEBSITE_CALL_CONVERSION = GOOGLE_ADS_ID + "/8pnMCMrWyoUdEJjAlJJD";
  var BUSINESS_NUMBER = "(904) 520-1994";
  var NUMBER_RE = /(\+?1[\s.-]?)?\(?904\)?[\s.-]?520[\s.-]?1994/g;
  var swapTo = null;
  function applySwap() {
    if (!swapTo || !document.body) return;
    var digits = String(swapTo.mobile || swapTo.formatted || "").replace(/\D/g, "");
    if (digits.length === 10) digits = "1" + digits;
    if (digits.length < 11) return;
    var formatted = swapTo.formatted || ("(" + digits.substr(1, 3) + ") " + digits.substr(4, 3) + "-" + digits.substr(7));
    document.querySelectorAll('a[href^="tel:"]').forEach(function (a) {
      if (/9045201994/.test(a.getAttribute("href").replace(/\D/g, ""))) a.setAttribute("href", "tel:+" + digits);
      var lbl = a.getAttribute("aria-label");
      if (lbl && NUMBER_RE.test(lbl)) a.setAttribute("aria-label", lbl.replace(NUMBER_RE, formatted));
      NUMBER_RE.lastIndex = 0;
    });
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        var t = n.parentNode && n.parentNode.nodeName;
        return (t === "SCRIPT" || t === "STYLE" || t === "NOSCRIPT") ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      }
    });
    var nodes = [], n;
    while ((n = walker.nextNode())) { if (n.nodeValue.indexOf("520") !== -1) nodes.push(n); }
    nodes.forEach(function (t) { t.nodeValue = t.nodeValue.replace(NUMBER_RE, formatted); });
  }
  window.gtag("config", WEBSITE_CALL_CONVERSION, {
    phone_conversion_number: BUSINESS_NUMBER,
    phone_conversion_callback: function (formatted, mobile) {
      swapTo = { formatted: formatted, mobile: mobile };
      window.__jaxWebsiteCallNumber = swapTo;
      if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", applySwap);
      else applySwap();
      try { window.dispatchEvent(new Event("jax:website-call-number")); } catch (e) {}
    }
  });


  // Google Ads conversions (Jax Tile & LVP)
  var LEAD_CONVERSION = "AW-18023260184/sry7CMu4sp8dEJjAlJJD";
  var CALL_CONVERSION = "AW-18023260184/3zIuCLzrqp8dEJjAlJJD";
  // Called by site.js ONLY after /api/lead.php confirms a countable lead.
  window.jaxTrackLeadConversion = function () {
    window.gtag("event", "conversion", { send_to: LEAD_CONVERSION, value: 1.0, currency: "USD" });
  };
  // Second, separate conversion: "JTL - Pre-qualified lead (1,000+ sq ft, in area)".
  // Decided server-side by /api/lead.php (in area, not tile, 1,000+ sq ft). Fires at most
  // once per Lead ID (transaction_id = Lead ID; also de-duplicated in the page).
  var QUALIFIED_LEAD_CONVERSION = "AW-18023260184/0V9jCPvRg6AdEJjAlJJD";
  var qualifiedSent = {};
  window.jaxTrackQualifiedLead = function (leadId) {
    if (!QUALIFIED_LEAD_CONVERSION || !leadId || qualifiedSent[leadId]) return;
    qualifiedSent[leadId] = true;
    window.gtag("event", "conversion", { send_to: QUALIFIED_LEAD_CONVERSION, value: 1.0, currency: "USD", transaction_id: leadId || "" });
  };
  document.addEventListener("click", function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href^="tel:"]') : null;
    if (a) window.gtag("event", "conversion", { send_to: CALL_CONVERSION });
  }, true);

  var googleTag = document.createElement("script");
  googleTag.async = true;
  googleTag.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GOOGLE_ADS_ID);
  document.head.appendChild(googleTag);

  (function (w, d, s, l, i) {
    w[l] = w[l] || [];
    var f = d.getElementsByTagName(s)[0];
    var j = d.createElement(s);
    var dl = l !== "dataLayer" ? "&l=" + l : "";
    j.async = true;
    j.src = "https://www.googletagmanager.com/gtm.js?id=" + i + dl;
    f.parentNode.insertBefore(j, f);
  })(window, document, "script", "dataLayer", GTM_ID);
})(window, document);
