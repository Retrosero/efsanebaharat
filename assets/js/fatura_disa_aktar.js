// assets/js/fatura_disa_aktar.js
// Fatura detay sayfasındaki "Dışa Aktar" menüsü: PDF, Excel ve görüntü (PNG) indirme.
// Kütüphaneler yalnızca ilk kullanımda yüklenir.
(function (root) {
  'use strict';

  var KUTUPHANELER = {
    html2canvas: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    jspdf: 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    xlsx: 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js'
  };
  var yuklenenler = {};

  function scriptYukle(ad) {
    if (!yuklenenler[ad]) {
      yuklenenler[ad] = new Promise(function (coz, reddet) {
        var s = document.createElement('script');
        s.src = KUTUPHANELER[ad];
        s.async = true;
        s.onload = function () { coz(); };
        s.onerror = function () {
          delete yuklenenler[ad];
          reddet(new Error(ad + ' kütüphanesi yüklenemedi. İnternet bağlantınızı kontrol edin.'));
        };
        document.head.appendChild(s);
      });
    }
    return yuklenenler[ad];
  }

  // Dosya adında sorun çıkaracak karakterleri temizler
  // Dosya adı: <Cari Adı>_<gg.aa.yyyy>_<Fatura No>.<uzantı>
  // Türkçe harfler korunur; Windows/macOS'ta geçersiz karakterler ve yol ayraçları temizlenir.
  function dosyaAdiParcasi(metin) {
    return String(metin || '')
      .replace(/[<>:"\/\\|?*\u0000-\u001f]+/g, ' ')
      .replace(/\.{2,}/g, '.')
      .trim()
      .replace(/\s+/g, '_')
      .replace(/^[._]+|[._]+$/g, '');
  }

  function dosyaAdi(veri, uzanti) {
    var parcalar = [
      dosyaAdiParcasi(veri.firma) || 'Fatura',
      dosyaAdiParcasi(veri.tarihGoster || veri.tarih),
      dosyaAdiParcasi(veri.faturaNo)
    ].filter(Boolean);
    return parcalar.join('_').slice(0, 150) + '.' + uzanti;
  }

  function sayi(v) {
    var n = parseFloat(v);
    return isFinite(n) ? n : 0;
  }

  // Excel sayfası için satırlar (saf fonksiyon; Node testlerinde de kullanılır)
  function excelSatirlari(veri) {
    var satirlar = [
      [veri.baslik || 'Fatura'],
      ['Fatura No', veri.faturaNo || ''],
      ['Tarih', veri.tarihGoster || veri.tarih || ''],
      [(veri.firmaEtiketi || 'Müşteri'), veri.firma || '']
    ];
    if (veri.adres) satirlar.push(['Adres', veri.adres]);
    if (veri.telefon) satirlar.push(['Telefon', veri.telefon]);
    if (veri.not) satirlar.push(['Not', veri.not]);
    satirlar.push([]);
    var basliklarSatiri = satirlar.length;
    satirlar.push(['#', 'Ürün Kodu', 'Ürün', 'Miktar', 'Birim', 'Birim Fiyat (₺)', 'Toplam (₺)', 'İskonto %', 'İskonto (₺)', 'KDV %', 'Net Tutar (₺)', 'Ürün Notu']);
    var araToplam = 0, iskonto = 0;
    (veri.kalemler || []).forEach(function (k, i) {
      araToplam += sayi(k.toplam);
      iskonto += sayi(k.iskontoTutari);
      satirlar.push([
        i + 1, k.kod || '', k.ad || '', sayi(k.miktar), k.birim || '', sayi(k.birimFiyat), sayi(k.toplam),
        sayi(k.iskontoOrani), sayi(k.iskontoTutari), sayi(k.kdvOrani), sayi(k.net), k.not || ''
      ]);
    });
    satirlar.push([]);
    satirlar.push(['', '', '', '', '', 'Ara Toplam', round2(araToplam)]);
    if (iskonto > 0) satirlar.push(['', '', '', '', '', 'İskonto', round2(-iskonto)]);
    satirlar.push(['', '', '', '', '', 'Genel Toplam', round2(sayi(veri.genelToplam))]);
    return { satirlar: satirlar, baslikSatiri: basliklarSatiri };
  }

  function round2(n) { return Math.round(n * 100) / 100; }

  function indir(blob, ad) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = ad;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1000);
  }

  // Fatura alanının görüntüsünü alır (arama kutusu gibi ekran öğeleri hariç)
  function faturaGoruntusu(eleman) {
    return scriptYukle('html2canvas').then(function () {
      var GENISLIK = 1000; // px: sütunların sığacağı sabit A4 dostu genişlik
      return root.html2canvas(eleman, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
        logging: false,
        windowWidth: GENISLIK + 40,
        ignoreElements: function (el) {
          return el.classList && (el.classList.contains('print:hidden') || el.classList.contains('no-export'));
        },
        // Kopya belgede faturayı menü/yan panel düzeninden ayırıp tek başına, sabit genişlikte çiz
        // (aksi halde sayfa düzeni kayıyor ve sağda gri boşluk oluşuyordu)
        onclone: function (belge) {
          var kopya = belge.getElementById(eleman.id);
          if (!kopya) return;
          // Diğer öğeleri gizle (gövdedeki <style>/<link> stil tanımları korunur)
          Array.prototype.forEach.call(belge.body.children, function (c) {
            if (!/^(STYLE|LINK|SCRIPT|META)$/.test(c.tagName)) c.style.setProperty('display', 'none', 'important');
          });
          belge.body.style.cssText = 'margin:0;padding:0;background:#fff;width:' + (GENISLIK + 20) + 'px';
          kopya.style.cssText = 'display:block;width:' + GENISLIK + 'px;margin:0;box-shadow:none;border:0';
          belge.body.insertBefore(kopya, belge.body.firstChild);
        }
      });
    });
  }

  function pngIndir(veri, eleman) {
    return faturaGoruntusu(eleman).then(function (canvas) {
      return new Promise(function (coz) {
        canvas.toBlob(function (blob) { indir(blob, dosyaAdi(veri, 'png')); coz(); }, 'image/png');
      });
    });
  }

  function pdfIndir(veri, eleman) {
    return Promise.all([faturaGoruntusu(eleman), scriptYukle('jspdf')]).then(function (sonuc) {
      var canvas = sonuc[0];
      var jsPDF = root.jspdf.jsPDF;
      var pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
      var kenar = 10;
      var sayfaG = pdf.internal.pageSize.getWidth() - kenar * 2;
      var sayfaY = pdf.internal.pageSize.getHeight() - kenar * 2;
      var pxPerMm = canvas.width / sayfaG;
      var dilimPx = Math.floor(sayfaY * pxPerMm);

      // Uzun faturaları A4 sayfalarına böl
      for (var y = 0, sayfa = 0; y < canvas.height; y += dilimPx, sayfa++) {
        var yukseklik = Math.min(dilimPx, canvas.height - y);
        var dilim = document.createElement('canvas');
        dilim.width = canvas.width;
        dilim.height = yukseklik;
        dilim.getContext('2d').drawImage(canvas, 0, y, canvas.width, yukseklik, 0, 0, canvas.width, yukseklik);
        if (sayfa > 0) pdf.addPage();
        pdf.addImage(dilim.toDataURL('image/jpeg', 0.92), 'JPEG', kenar, kenar, sayfaG, yukseklik / pxPerMm);
      }
      pdf.save(dosyaAdi(veri, 'pdf'));
    });
  }

  function excelIndir(veri) {
    return scriptYukle('xlsx').then(function () {
      var XLSX = root.XLSX;
      var sonuc = excelSatirlari(veri);
      var ws = XLSX.utils.aoa_to_sheet(sonuc.satirlar);
      ws['!cols'] = [{ wch: 5 }, { wch: 14 }, { wch: 36 }, { wch: 10 }, { wch: 7 }, { wch: 15 }, { wch: 14 }, { wch: 10 }, { wch: 12 }, { wch: 7 }, { wch: 14 }, { wch: 30 }];
      // Para ve miktar sütunlarına sayı biçimi uygula
      var aralik = XLSX.utils.decode_range(ws['!ref']);
      for (var r = sonuc.baslikSatiri + 1; r <= aralik.e.r; r++) {
        [3, 5, 6, 8, 10].forEach(function (c) {
          var hucre = ws[XLSX.utils.encode_cell({ r: r, c: c })];
          if (hucre && hucre.t === 'n') hucre.z = c === 3 ? '#,##0.###' : '#,##0.00';
        });
      }
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Fatura');
      XLSX.writeFile(wb, dosyaAdi(veri, 'xlsx'));
    });
  }

  function disaAktar(format, veri, eleman, bildir) {
    bildir = bildir || function () {};
    var islem = { pdf: pdfIndir, xlsx: excelIndir, png: pngIndir }[format];
    if (!islem) return Promise.reject(new Error('Bilinmeyen format: ' + format));
    var adlar = { pdf: 'PDF', xlsx: 'Excel', png: 'Görüntü' };
    bildir(adlar[format] + ' hazırlanıyor...');
    return islem(veri, eleman).then(function () {
      bildir('Fatura ' + adlar[format] + ' olarak indirildi.');
    }, function (hata) {
      if (root.console) console.error('Dışa aktarma hatası:', hata);
      bildir('Dışa aktarma başarısız: ' + (hata && hata.message ? hata.message : 'bilinmeyen hata'));
      throw hata;
    });
  }

  var FaturaDisaAktar = { disaAktar: disaAktar, excelSatirlari: excelSatirlari, dosyaAdi: dosyaAdi };
  if (typeof module !== 'undefined' && module.exports) module.exports = FaturaDisaAktar;
  root.FaturaDisaAktar = FaturaDisaAktar;
})(typeof window !== 'undefined' ? window : globalThis);
