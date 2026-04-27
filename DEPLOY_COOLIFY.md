# Coolify Kurulum Rehberi

Bu proje klasik `PHP + Apache + MariaDB` yapısında. Coolify'da en pratik yöntem Docker ile çalıştırmaktır.

## 1) Ön hazırlık

1. Bu repoyu GitHub'a push et.
2. Repoda şu dosyaların olduğundan emin ol:
   - `Dockerfile`
   - `docker-compose.coolify.yml`
   - `.env.example`

## 2) Coolify ile yayınlama (Compose yöntemi)

1. Coolify panelinde `New Resource` -> `Docker Compose` seç.
2. Git deposunu bağla.
3. Compose file olarak `docker-compose.coolify.yml` seç.
4. Environment Variables alanına şunları gir:
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `DB_ROOT_PASS`
5. Domain bağla ve `Deploy` et.

Not: İlk kurulumda `efsanebaharat.sql` otomatik olarak DB içine import edilir. Daha önce volume oluşmuşsa tekrar import etmez.

## 3) Uygulama ayarları

Kod artık veritabanı bilgisini ortam değişkenlerinden okuyor:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Compose içinde `DB_HOST=db` olarak ayarlanmıştır.

## 4) Veri kalıcılığı

Aşağıdaki veriler volume ile kalıcıdır:

- DB verisi: `db_data`
- Yüklenen dosyalar: `uploads_data`
- Loglar: `logs_data`
- Geçici dosyalar: `temp_data`

## 5) Güvenlik notu (önemli)

Bu proje geçmişte DB şifresini düz metin olarak içeriyordu. Canlıya çıkmadan önce:

1. Eski veritabanı şifrelerini değiştir.
2. Sadece yeni environment şifrelerini kullan.
3. Mümkünse `config.php` içinde `display_errors` ayarını üretimde kapat (`0`).
