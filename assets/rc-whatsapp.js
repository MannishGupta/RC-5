/* RC WhatsApp share helper — Version: 3.2.66
 * 100% ASCII source file. Emoji via Unicode escapes only.
 * Endpoint: https://api.whatsapp.com/send (official; avoids wa.me redirect corruption)
 */
(function (w) {
  'use strict';
  /** Build open URL. phoneDigits optional (country code, no +). */
  function rcWhatsAppUrl(text, phoneDigits) {
    var t = encodeURIComponent(String(text || ''));
    var base = 'https://api.whatsapp.com/send';
    if (phoneDigits) {
      var p = String(phoneDigits).replace(/\D/g, '');
      if (p) return base + '?phone=' + p + '&text=' + t;
    }
    return base + '?text=' + t;
  }
  function rcWhatsAppOpen(text, phoneDigits) {
    var url = rcWhatsAppUrl(text, phoneDigits);
    w.open(url, '_blank', 'noopener,noreferrer');
    return url;
  }
  /* Common glyphs (ASCII-safe definitions) */
  var E = {
    spark: '\u2728',
    cheers: '\uD83E\uDD42',
    cal: '\uD83D\uDCC5',
    pin: '\uD83D\uDCCD',
    phone: '\uD83D\uDCDE',
    mail: '\uD83D\uDCE7',
    link: '\uD83D\uDD17',
    bank: '\uD83C\uDFE6',
    user: '\uD83D\uDC64',
    star: '\u2B50',
    check: '\u2705',
    wave: '\uD83D\uDC4B',
    crystal: '\uD83D\uDD2E',
    rule: '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500',
    mid: '\u00B7'
  };
  w.rcWhatsAppUrl = rcWhatsAppUrl;
  w.rcWhatsAppOpen = rcWhatsAppOpen;
  w.RC_WA_E = E;
})(window);
