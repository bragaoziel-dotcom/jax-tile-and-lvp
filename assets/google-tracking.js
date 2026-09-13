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
