<!DOCTYPE html>
<html lang="tr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Müşteri Detayları</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
    />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: "#3176FF",
              secondary: "#FF6B6B",
            },
            borderRadius: {
              none: "0px",
              sm: "4px",
              DEFAULT: "8px",
              md: "12px",
              lg: "16px",
              xl: "20px",
              "2xl": "24px",
              "3xl": "32px",
              full: "9999px",
              button: "8px",
            },
          },
        },
      };
    </script>
    <style>
      :where([class^="ri-"])::before { content: "\f3c2"; }
      .tab-active {
      color: #3176FF;
      border-bottom: 2px solid #3176FF;
      margin-bottom: -2px;
      }
      .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      }
    </style>
  </head>
  <body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
      <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h1 class="text-2xl font-bold">Ahmet Yılmaz</h1>
            <p class="text-gray-600">Müşteri ID: #MUS12345</p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">İletişim Bilgileri</h3>
            <p><i class="ri-phone-line mr-2"></i>+90 532 123 4567</p>
            <p><i class="ri-mail-line mr-2"></i>ahmet.yilmaz@email.com</p>
            <p><i class="ri-map-pin-line mr-2"></i>İstanbul, Türkiye</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">Hesap Bilgileri</h3>
            <p>Hesap Açılış: 15.06.2023</p>
            <p>Müşteri Grubu: Premium</p>
            <p>Durum: <span class="text-green-600">Aktif</span></p>
          </div>
          <div class="bg-primary bg-opacity-10 p-4 rounded-lg">
            <h3 class="font-semibold mb-2">Cari Durum</h3>
            <div class="text-2xl font-bold text-primary">₺24,850.00</div>
            <p class="text-sm text-gray-600">Son İşlem: 25.02.2025</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex space-x-6 border-b mb-6">
          <button class="tab-btn tab-active px-4 py-2" data-tab="siparisler">
            Sipariş Geçmişi
          </button>
          <button class="tab-btn px-4 py-2" data-tab="hareketler">
            Hesap Hareketleri
          </button>
          <button class="tab-btn px-4 py-2" data-tab="urunler">
            Satın Alınan Ürünler
          </button>
          <button class="tab-btn px-4 py-2" data-tab="duzenle">Düzenle</button>
        </div>
        <div id="siparisler" class="tab-content">
          <div class="flex justify-end mb-4">
            <div class="relative">
              <button
                id="exportBtnSiparisler"
                class="bg-primary text-white px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center"
              >
                <i class="ri-download-line mr-2"></i>Dışa Aktar
                <i class="ri-arrow-down-s-line ml-2"></i>
              </button>
              <div
                id="exportMenuSiparisler"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border"
              >
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('siparisler', 'pdf')"
                >
                  <i class="ri-file-pdf-line mr-2"></i>PDF olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('siparisler', 'xlsx')"
                >
                  <i class="ri-file-excel-line mr-2"></i>XLSX olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('siparisler', 'png')"
                >
                  <i class="ri-image-line mr-2"></i>PNG olarak kaydet
                </button>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-2 text-left">Tarih</th>
                  <th class="px-4 py-2 text-left">Sipariş No</th>
                  <th class="px-4 py-2 text-left">Tutar</th>
                  <th class="px-4 py-2 text-left">Durum</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b">
                  <td class="px-4 py-2">25.02.2025</td>
                  <td class="px-4 py-2">#SIP98765</td>
                  <td class="px-4 py-2">₺1,250.00</td>
                  <td class="px-4 py-2">
                    <span
                      class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm"
                      >Tamamlandı</span
                    >
                  </td>
                </tr>
                <tr class="border-b">
                  <td class="px-4 py-2">24.02.2025</td>
                  <td class="px-4 py-2">#SIP98764</td>
                  <td class="px-4 py-2">₺2,800.00</td>
                  <td class="px-4 py-2">
                    <span
                      class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm"
                      >Hazırlanıyor</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div id="hareketler" class="tab-content hidden">
          <div class="flex justify-end mb-4">
            <div class="relative">
              <button
                id="exportBtnHareketler"
                class="bg-primary text-white px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center"
              >
                <i class="ri-download-line mr-2"></i>Dışa Aktar
                <i class="ri-arrow-down-s-line ml-2"></i>
              </button>
              <div
                id="exportMenuHareketler"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border"
              >
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('hareketler', 'pdf')"
                >
                  <i class="ri-file-pdf-line mr-2"></i>PDF olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('hareketler', 'xlsx')"
                >
                  <i class="ri-file-excel-line mr-2"></i>XLSX olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('hareketler', 'png')"
                >
                  <i class="ri-image-line mr-2"></i>PNG olarak kaydet
                </button>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-2 text-left">Tarih</th>
                  <th class="px-4 py-2 text-left">İşlem</th>
                  <th class="px-4 py-2 text-left">Açıklama</th>
                  <th class="px-4 py-2 text-right">Tutar</th>
                  <th class="px-4 py-2 text-right">Bakiye</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b">
                  <td class="px-4 py-2">25.02.2025</td>
                  <td class="px-4 py-2">Ödeme</td>
                  <td class="px-4 py-2">Havale ile ödeme</td>
                  <td class="px-4 py-2 text-right text-green-600">
                    +₺5,000.00
                  </td>
                  <td class="px-4 py-2 text-right">₺24,850.00</td>
                </tr>
                <tr class="border-b">
                  <td class="px-4 py-2">24.02.2025</td>
                  <td class="px-4 py-2">Satış</td>
                  <td class="px-4 py-2">Ürün satışı</td>
                  <td class="px-4 py-2 text-right text-red-600">-₺2,800.00</td>
                  <td class="px-4 py-2 text-right">₺19,850.00</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div id="urunler" class="tab-content hidden">
          <div class="flex justify-between items-center mb-4">
            <div class="relative w-64">
              <input
                type="text"
                placeholder="Ürün ara..."
                class="w-full px-4 py-2 pr-10 border rounded-lg focus:outline-none focus:border-primary"
              />
              <i
                class="ri-search-line absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
              ></i>
            </div>
            <div class="relative">
              <button
                id="exportBtnUrunler"
                class="bg-primary text-white px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center"
              >
                <i class="ri-download-line mr-2"></i>Dışa Aktar
                <i class="ri-arrow-down-s-line ml-2"></i>
              </button>
              <div
                id="exportMenuUrunler"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border"
              >
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('urunler', 'pdf')"
                >
                  <i class="ri-file-pdf-line mr-2"></i>PDF olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('urunler', 'xlsx')"
                >
                  <i class="ri-file-excel-line mr-2"></i>XLSX olarak kaydet
                </button>
                <button
                  class="w-full text-left px-4 py-2 hover:bg-gray-50"
                  onclick="exportTable('urunler', 'png')"
                >
                  <i class="ri-image-line mr-2"></i>PNG olarak kaydet
                </button>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="border rounded-lg p-4">
              <img
                src="https://public.readdy.ai/ai/img_res/503fb0f92f3a3010f1af3c71a1e839b5.jpg"
                class="w-full h-40 object-cover rounded-lg mb-4"
              />
              <h3 class="font-semibold">Nike Air Max</h3>
              <p class="text-gray-600 text-sm mb-2">Spor Ayakkabı</p>
              <p class="text-primary font-bold">₺2,499.00</p>
              <p class="text-sm text-gray-500">Satın Alma: 24.02.2025</p>
            </div>
            <div class="border rounded-lg p-4">
              <img
                src="https://public.readdy.ai/ai/img_res/f16f17641219e585d2306d652cd8559c.jpg"
                class="w-full h-40 object-cover rounded-lg mb-4"
              />
              <h3 class="font-semibold">Deri Cüzdan</h3>
              <p class="text-gray-600 text-sm mb-2">Aksesuar</p>
              <p class="text-primary font-bold">₺899.00</p>
              <p class="text-sm text-gray-500">Satın Alma: 23.02.2025</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div id="pasifModal" class="modal">
      <div
        class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-xl p-6 w-96"
      >
        <h3 class="text-xl font-bold mb-4">
          Müşteriyi Pasif Yapmak İstediğinizden Emin misiniz?
        </h3>
        <p class="text-gray-600 mb-6">
          Bu işlem geri alınamaz ve müşterinin hesabı pasif duruma geçecektir.
        </p>
        <div class="flex justify-end space-x-4">
          <button
            id="iptalBtn"
            class="px-4 py-2 border !rounded-button hover:bg-gray-50"
          >
            İptal
          </button>
          <button
            id="onayBtn"
            class="bg-secondary text-white px-4 py-2 !rounded-button hover:bg-opacity-90"
          >
            Onayla
          </button>
        </div>
      </div>
    </div>
    <div id="duzenle" class="tab-content hidden">
      <div class="bg-white rounded-lg">
        <form id="editForm" class="space-y-6">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >Ad Soyad</label
              >
              <input
                type="text"
                value="Ahmet Yılmaz"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >Telefon</label
              >
              <input
                type="tel"
                value="+90 532 123 4567"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >E-posta</label
              >
              <input
                type="email"
                value="ahmet.yilmaz@email.com"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >Adres</label
              >
              <input
                type="text"
                value="İstanbul, Türkiye"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >Müşteri Grubu</label
              >
              <select
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary"
              >
                <option>Premium</option>
                <option>Standard</option>
                <option>Basic</option>
              </select>
            </div>
          </div>
          <div class="flex justify-between pt-6 border-t">
            <button
              type="button"
              id="pasifYapBtn"
              class="bg-secondary text-white px-4 py-2 !rounded-button hover:bg-opacity-90"
            >
              <i class="ri-user-unfollow-line mr-2"></i>Müşteriyi Pasif Yap
            </button>
            <button
              type="submit"
              class="bg-primary text-white px-6 py-2 !rounded-button hover:bg-opacity-90"
            >
              Değişiklikleri Kaydet
            </button>
          </div>
        </form>
      </div>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const tabBtns = document.querySelectorAll(".tab-btn");
        const tabContents = document.querySelectorAll(".tab-content");
        const pasifYapBtn = document.getElementById("pasifYapBtn");
        const pasifModal = document.getElementById("pasifModal");
        const iptalBtn = document.getElementById("iptalBtn");
        const onayBtn = document.getElementById("onayBtn");
        const exportBtns = ["Siparisler", "Hareketler", "Urunler"].map((id) =>
          document.getElementById(`exportBtn${id}`),
        );
        const exportMenus = ["Siparisler", "Hareketler", "Urunler"].map((id) =>
          document.getElementById(`exportMenu${id}`),
        );
        exportBtns.forEach((btn, index) => {
          btn.addEventListener("click", () => {
            exportMenus.forEach((menu, i) => {
              if (i === index) {
                menu.classList.toggle("hidden");
              } else {
                menu.classList.add("hidden");
              }
            });
          });
        });
        document.addEventListener("click", (e) => {
          if (!e.target.closest('[id^="exportBtn"]')) {
            exportMenus.forEach((menu) => menu.classList.add("hidden"));
          }
        });
        window.exportTable = (tableId, format) => {
          const notification = document.createElement("div");
          notification.className =
            "fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50";
          notification.textContent = `Tablo ${format.toUpperCase()} formatında kaydedildi`;
          document.body.appendChild(notification);
          setTimeout(() => notification.remove(), 3000);
          exportMenus.forEach((menu) => menu.classList.add("hidden"));
        };
        document.getElementById("editForm").addEventListener("submit", (e) => {
          e.preventDefault();
          const notification = document.createElement("div");
          notification.className =
            "fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50";
          notification.textContent = "Değişiklikler başarıyla kaydedildi";
          document.body.appendChild(notification);
          setTimeout(() => notification.remove(), 3000);
        });
        tabBtns.forEach((btn) => {
          btn.addEventListener("click", () => {
            tabBtns.forEach((b) => b.classList.remove("tab-active"));
            btn.classList.add("tab-active");
            const tabId = btn.dataset.tab;
            tabContents.forEach((content) => {
              content.classList.add("hidden");
              if (content.id === tabId) {
                content.classList.remove("hidden");
              }
            });
          });
        });
        pasifYapBtn.addEventListener("click", () => {
          pasifModal.style.display = "block";
        });
        iptalBtn.addEventListener("click", () => {
          pasifModal.style.display = "none";
        });
        onayBtn.addEventListener("click", () => {
          pasifModal.style.display = "none";
          const notification = document.createElement("div");
          notification.className =
            "fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg";
          notification.textContent = "Müşteri başarıyla pasif duruma alındı";
          document.body.appendChild(notification);
          setTimeout(() => notification.remove(), 3000);
        });
        window.addEventListener("click", (e) => {
          if (e.target === pasifModal) {
            pasifModal.style.display = "none";
          }
        });
      });
    </script>
  </body>
</html>
