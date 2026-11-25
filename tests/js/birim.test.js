// node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const Birim = require('../../assets/js/birim.js');

test('miktarCevir: gr <-> kg', () => {
  assert.equal(Birim.miktarCevir(250, 'gr', 'kg'), 0.25);
  assert.equal(Birim.miktarCevir(1.5, 'kg', 'gr'), 1500);
  assert.equal(Birim.miktarCevir(3, 'adet', 'kg'), 3);
  assert.equal(Birim.miktarCevir('abc', 'kg', 'gr'), 0);
});

test('birimFiyat: kg fiyatlı ürün gramla satılınca gram fiyatı', () => {
  assert.equal(Birim.birimFiyat(500, 'kg', 'gr'), 0.5);
  assert.equal(Birim.birimFiyat(0.4, 'gr', 'kg'), 400);
  assert.equal(Birim.birimFiyat(500, 'kg', 'kg'), 500);
});

test('yuvarla: kg 3 hane, gr tam sayı', () => {
  assert.equal(Birim.yuvarla(0.12345, 'kg'), 0.123);
  assert.equal(Birim.yuvarla(250.6, 'gr'), 251);
});

test('escHtml: HTML özel karakterlerini kaçışlar', () => {
  assert.equal(Birim.escHtml('<img src=x onerror="alert(1)">'), '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;');
  assert.equal(Birim.escHtml(null), '');
  assert.equal(Birim.escHtml("O'Neil & `x`"), 'O&#39;Neil &amp; &#96;x&#96;');
});

// --- satis.php ürün kartı kg/gr davranışı (asıl hata) ---
// satis.php içindeki toggleWeightUnit / validateAndAddToCartWeight fonksiyonlarını
// sahte bir DOM ile çalıştırıp sepete hangi birimle eklendiğini doğrular.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function satisKartOrtami(urun, varsayilanBirim) {
  const kaynak = fs.readFileSync(path.join(__dirname, '../../satis.php'), 'utf8');
  const bas = kaynak.indexOf('// Ağırlık birimini değiştirme fonksiyonu');
  const son = kaynak.indexOf('// Ürün kartlarındaki ilk ağırlık birimini ayarlamak için');
  assert.ok(bas > 0 && son > bas, 'satis.php içinde kart fonksiyonları bulunamadı');
  const kod = kaynak.slice(bas, son);

  const elemanlar = {
    [`weightToggle_${urun.id}`]: { dataset: { currentUnit: varsayilanBirim }, textContent: varsayilanBirim },
    [`directQty_${urun.id}`]: { value: '0', step: 1, min: 1, dataset: {} },
  };
  const alertlar = [];
  const ortam = {
    Birim, cart: [], products: [urun], alertlar,
    document: { getElementById: (id) => elemanlar[id] || null },
    alert: (m) => alertlar.push(m),
    updateCartCount() {}, renderCart() {},
  };
  vm.createContext(ortam);
  vm.runInContext(kod + '\nthis.toggleWeightUnit = toggleWeightUnit; this.validateAndAddToCartWeight = validateAndAddToCartWeight;', ortam);
  return { ortam, input: elemanlar[`directQty_${urun.id}`], buton: elemanlar[`weightToggle_${urun.id}`] };
}

const kiloUrun = { id: 7, urun_adi: 'Karabiber', olcum_birimi: 'kg', satis_fiyati: 500, stok_miktari: 10 };

test('kart: kg seçiliyken girilen miktar kg olarak eklenir ve fiyat kg fiyatıdır', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'kg');
  input.value = '2';
  ortam.validateAndAddToCartWeight(7);
  assert.equal(ortam.cart.length, 1);
  assert.equal(ortam.cart[0].olcumBirimi, 'kg');
  assert.equal(ortam.cart[0].qty, 2);
  assert.equal(ortam.cart[0].price, 500);
});

test('kart: gr seçiliyken sepete gr ve gram fiyatıyla eklenir', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'gr');
  input.value = '250';
  ortam.validateAndAddToCartWeight(7);
  assert.equal(ortam.cart[0].olcumBirimi, 'gr');
  assert.equal(ortam.cart[0].qty, 250);
  assert.equal(ortam.cart[0].price, 0.5);
  assert.equal(ortam.cart[0].qty * ortam.cart[0].price, 125);
});

test('HATA DÜZELTMESİ: kg seçiliyken 250 yazıp gr butonuna basınca sepete 250 gr eklenir', () => {
  const { ortam, input, buton } = satisKartOrtami(kiloUrun, 'kg');
  // Kullanıcı yazar (oninput), butona basar (mousedown preventDefault -> blur/onchange olmaz)
  input.value = '250';
  input.dataset.dirty = '1';
  ortam.toggleWeightUnit(7);
  assert.equal(buton.dataset.currentUnit, 'gr');
  assert.equal(ortam.cart.length, 1, 'aynı ürün için tek ağırlık satırı olmalı');
  assert.equal(ortam.cart[0].olcumBirimi, 'gr');
  assert.equal(ortam.cart[0].qty, 250);
  assert.equal(ortam.cart[0].price, 0.5);
  assert.equal(ortam.alertlar.length, 0);
});

test('HATA DÜZELTMESİ: sepete kg ile eklenmiş ürün gr birimine çevrilince sepet satırı da gr olur', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'kg');
  input.value = '2';
  input.dataset.dirty = '1';
  ortam.validateAndAddToCartWeight(7); // onchange (onaylandı)
  assert.equal(input.dataset.dirty, undefined);
  ortam.toggleWeightUnit(7);
  assert.equal(ortam.cart.length, 1);
  assert.equal(ortam.cart[0].olcumBirimi, 'gr');
  assert.equal(ortam.cart[0].qty, 2000);
  assert.equal(ortam.cart[0].qty * ortam.cart[0].price, 1000); // 2 kg x 500 TL
});

test('kart: birim değişince sepet satırı yeni birime çevrilir (toplam tutar korunur)', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'gr');
  input.value = '500';
  ortam.validateAndAddToCartWeight(7);
  ortam.toggleWeightUnit(7); // gr -> kg
  assert.equal(ortam.cart.length, 1);
  assert.equal(ortam.cart[0].olcumBirimi, 'kg');
  assert.equal(ortam.cart[0].qty, 0.5);
  assert.equal(ortam.cart[0].price, 500);
  assert.equal(input.value, '0.500');
});

test('kart: stoktan fazla miktar stokla sınırlanır', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'gr');
  input.value = '20000'; // 20 kg, stok 10 kg
  ortam.validateAndAddToCartWeight(7);
  assert.equal(ortam.cart[0].qty, 10000);
  assert.equal(ortam.alertlar.length, 1);
});

test('kart: 0 girilince ürün sepetten çıkar, not korunur', () => {
  const { ortam, input } = satisKartOrtami(kiloUrun, 'gr');
  input.value = '100';
  ortam.validateAndAddToCartWeight(7);
  ortam.cart[0].note = 'ince çekilsin';
  input.value = '200';
  ortam.validateAndAddToCartWeight(7);
  assert.equal(ortam.cart[0].note, 'ince çekilsin');
  input.value = '0';
  ortam.validateAndAddToCartWeight(7);
  assert.equal(ortam.cart.length, 0);
});
