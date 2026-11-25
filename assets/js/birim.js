// assets/js/birim.js
// Ağırlık birimi (kg / gr) dönüşümleri. Tarayıcıda window.Birim, Node'da module.exports.
(function (root) {
  'use strict';

  var AGIRLIK = { kg: 1000, gr: 1 }; // gram cinsinden çarpan

  function agirlikMi(birim) {
    return Object.prototype.hasOwnProperty.call(AGIRLIK, birim);
  }

  // Miktarı "kaynak" birimden "hedef" birime çevirir. Ağırlık dışı birimlerde değer aynen döner.
  function miktarCevir(miktar, kaynak, hedef) {
    miktar = parseFloat(miktar) || 0;
    if (kaynak === hedef || !agirlikMi(kaynak) || !agirlikMi(hedef)) return miktar;
    return (miktar * AGIRLIK[kaynak]) / AGIRLIK[hedef];
  }

  // Ürünün kendi birimindeki fiyatını satış birimine göre birim fiyata çevirir.
  // Örn: 500 TL/kg ürün gr ile satılıyorsa 0.5 TL/gr.
  function birimFiyat(urunFiyati, urunBirimi, satisBirimi) {
    urunFiyati = parseFloat(urunFiyati) || 0;
    if (urunBirimi === satisBirimi || !agirlikMi(urunBirimi) || !agirlikMi(satisBirimi)) return urunFiyati;
    return (urunFiyati * AGIRLIK[satisBirimi]) / AGIRLIK[urunBirimi];
  }

  // Birime uygun yuvarlama (kg: 3 hane, gr/adet: tam sayı)
  function yuvarla(miktar, birim) {
    var basamak = birim === 'kg' ? 1000 : 1;
    return Math.round((parseFloat(miktar) || 0) * basamak) / basamak;
  }

  // innerHTML / template literal içinde güvenli metin
  function escHtml(deger) {
    return String(deger === null || deger === undefined ? '' : deger)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/`/g, '&#96;');
  }

  var Birim = { agirlikMi: agirlikMi, miktarCevir: miktarCevir, birimFiyat: birimFiyat, yuvarla: yuvarla, escHtml: escHtml };

  if (typeof module !== 'undefined' && module.exports) module.exports = Birim;
  root.Birim = Birim;
  root.escHtml = escHtml;
})(typeof window !== 'undefined' ? window : globalThis);
