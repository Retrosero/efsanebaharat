-- Faturalar tablosuna para_birimi ve onay_durumu sütunlarını ekle
ALTER TABLE faturalar ADD COLUMN para_birimi VARCHAR(10) DEFAULT 'TRY' AFTER kalan_tutar;
ALTER TABLE faturalar ADD COLUMN onay_durumu VARCHAR(20) DEFAULT 'bekliyor' AFTER para_birimi;

-- Odeme_tahsilat tablosuna onay_durumu sütununu ekle
ALTER TABLE odeme_tahsilat ADD COLUMN onay_durumu VARCHAR(20) DEFAULT 'bekliyor';