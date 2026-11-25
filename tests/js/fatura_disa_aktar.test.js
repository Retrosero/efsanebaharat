const test = require('node:test');
const assert = require('node:assert/strict');
const F = require('../../assets/js/fatura_disa_aktar.js');

const veri = {
  baslik: 'Satış Faturası', faturaNo: 'INV-00000012', tarih: '2026-11-25', tarihGoster: '25.11.2026',
  firmaEtiketi: 'Müşteri', firma: 'Ali Veli', adres: 'İstanbul', telefon: '', not: 'Acil',
  genelToplam: 157.5,
  kalemler: [
    { kod: 'K1', ad: 'Karabiber', miktar: 0.25, birim: 'kg', birimFiyat: 500, toplam: 125, iskontoOrani: 10, iskontoTutari: 12.5, kdvOrani: 0, net: 112.5, not: '' },
    { kod: 'A1', ad: 'Kavanoz', miktar: 2, birim: 'adet', birimFiyat: 25, toplam: 50, iskontoOrani: 10, iskontoTutari: 5, kdvOrani: 0, net: 45, not: 'kapaklı' },
  ],
};

test('excelSatirlari: başlık bilgileri, kalemler ve toplamlar', () => {
  const { satirlar, baslikSatiri } = F.excelSatirlari(veri);
  assert.deepEqual(satirlar[0], ['Satış Faturası']);
  assert.deepEqual(satirlar[1], ['Fatura No', 'INV-00000012']);
  assert.deepEqual(satirlar[3], ['Müşteri', 'Ali Veli']);
  assert.ok(!satirlar.some(s => s[0] === 'Telefon'), 'boş alanlar eklenmemeli');
  assert.equal(satirlar[baslikSatiri][2], 'Ürün');
  const k1 = satirlar[baslikSatiri + 1];
  assert.deepEqual(k1, [1, 'K1', 'Karabiber', 0.25, 'kg', 500, 125, 10, 12.5, 0, 112.5, '']);
  assert.equal(typeof k1[3], 'number', 'miktar sayı olmalı (Excel hesaplayabilsin)');
  const son = satirlar.slice(-3);
  assert.deepEqual(son[0], ['', '', '', '', '', 'Ara Toplam', 175]);
  assert.deepEqual(son[1], ['', '', '', '', '', 'İskonto', -17.5]);
  assert.deepEqual(son[2], ['', '', '', '', '', 'Genel Toplam', 157.5]);
});

test('excelSatirlari: kalemsiz/eksik veride hata vermez', () => {
  const { satirlar } = F.excelSatirlari({});
  assert.deepEqual(satirlar.at(-1), ['', '', '', '', '', 'Genel Toplam', 0]);
});

test('dosyaAdi: cari adı ve tarih dosya adında', () => {
  assert.equal(F.dosyaAdi(veri, 'pdf'), 'Ali_Veli_25.11.2026_INV-00000012.pdf');
  assert.equal(F.dosyaAdi({ ...veri, firma: 'BERK BATUHAN KİBAR' }, 'xlsx'), 'BERK_BATUHAN_KİBAR_25.11.2026_INV-00000012.xlsx');
});

test('dosyaAdi: geçersiz karakterler ve yol ayraçları temizlenir', () => {
  assert.equal(F.dosyaAdi({ firma: '../Kötü/Ad: "x"?', tarihGoster: '01.02.2026', faturaNo: 'INV-1' }, 'png'), 'Kötü_Ad_x_01.02.2026_INV-1.png');
  assert.equal(F.dosyaAdi({ firma: '', tarih: '2026-01-02' }, 'pdf'), 'Fatura_2026-01-02.pdf');
});

test('disaAktar: bilinmeyen formatı reddeder', async () => {
  await assert.rejects(F.disaAktar('docx', veri, null), /Bilinmeyen format/);
});

test('dosyaAdi: ters eğik çizgi ve kontrol karakterleri temizlenir', () => {
  assert.equal(F.dosyaAdi({ firma: 'A\\B\u0001C', tarihGoster: '01.02.2026' }, 'pdf'), 'A_B_C_01.02.2026.pdf');
});
