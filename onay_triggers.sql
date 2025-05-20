-- Satış onaylandığında stok azaltma ve cari bakiye arttırma
DELIMITER //

CREATE TRIGGER after_satis_onay
AFTER UPDATE ON onay_islemleri
FOR EACH ROW
BEGIN
    DECLARE fatura_id INT;
    
    -- Sadece satış işlemi ve durumu 'bekliyor'dan 'onaylandi'ya değişirse
    IF NEW.islem_tipi = 'satis' AND NEW.durum = 'onaylandi' AND OLD.durum = 'bekliyor' THEN
        -- İşlem ID'sini al
        SET fatura_id = NEW.islem_id;
        
        -- Fatura detaylarını döngüyle işle
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE urun_id INT;
            DECLARE miktar DECIMAL(10,3);
            DECLARE olcum_birimi VARCHAR(20);
            DECLARE urun_cursor CURSOR FOR 
                SELECT fd.urun_id, fd.miktar, fd.olcum_birimi
                FROM fatura_detaylari fd
                WHERE fd.fatura_id = fatura_id;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
            
            OPEN urun_cursor;
            
            read_loop: LOOP
                FETCH urun_cursor INTO urun_id, miktar, olcum_birimi;
                IF done THEN
                    LEAVE read_loop;
                END IF;
                
                -- Ürünün stoğunu güncelle
                UPDATE urunler 
                SET stok_miktari = CASE 
                    -- Negatif stok olmasın
                    WHEN stok_miktari < miktar THEN 0 
                    ELSE stok_miktari - miktar 
                END
                WHERE id = urun_id;
                
                -- Stok hareketi ekle
                INSERT INTO stok_hareketleri (
                    urun_id, hareket_tipi, miktar, olcum_birimi, 
                    aciklama, fatura_id, kullanici_id, created_at
                ) VALUES (
                    urun_id, 'cikis', miktar, olcum_birimi, 
                    'Satış faturası (onaylandı)', fatura_id, NEW.onaylayan_id, NOW()
                );
            END LOOP;
            
            CLOSE urun_cursor;
        END;
        
        -- Müşteri bakiyesini güncelle
        UPDATE musteriler 
        SET cari_bakiye = cari_bakiye + NEW.tutar
        WHERE id = NEW.musteri_id;
        
        -- Fatura durumunu güncelle
        UPDATE faturalar 
        SET onayli = 1, onay_durumu = 'onaylandi'
        WHERE id = fatura_id;
    END IF;
END //

DELIMITER ;

-- Tahsilat onaylandığında cari bakiye azaltma
DELIMITER //

CREATE TRIGGER after_tahsilat_onay
AFTER UPDATE ON onay_islemleri
FOR EACH ROW
BEGIN
    -- Sadece tahsilat işlemi ve durumu 'bekliyor'dan 'onaylandi'ya değişirse
    IF NEW.islem_tipi = 'tahsilat' AND NEW.durum = 'onaylandi' AND OLD.durum = 'bekliyor' THEN
        -- Müşteri bakiyesini azalt
        UPDATE musteriler 
        SET cari_bakiye = cari_bakiye - NEW.tutar
        WHERE id = NEW.musteri_id;
        
        -- Tahsilat durumunu güncelle
        UPDATE odeme_tahsilat 
        SET onayli = 1, onay_durumu = 'onaylandi'
        WHERE id = NEW.islem_id;
    END IF;
END //

DELIMITER ;

-- Alış onaylandığında stok arttırma ve tedarikçi cari bakiye arttırma
DELIMITER //

CREATE TRIGGER after_alis_onay
AFTER UPDATE ON onay_islemleri
FOR EACH ROW
BEGIN
    DECLARE fatura_id INT;
    
    -- Sadece alış işlemi ve durumu 'bekliyor'dan 'onaylandi'ya değişirse
    IF NEW.islem_tipi = 'alis' AND NEW.durum = 'onaylandi' AND OLD.durum = 'bekliyor' THEN
        -- İşlem ID'sini al
        SET fatura_id = NEW.islem_id;
        
        -- Fatura detaylarını döngüyle işle
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE urun_id INT;
            DECLARE miktar DECIMAL(10,3);
            DECLARE olcum_birimi VARCHAR(20);
            DECLARE urun_cursor CURSOR FOR 
                SELECT fd.urun_id, fd.miktar, fd.olcum_birimi
                FROM fatura_detaylari fd
                WHERE fd.fatura_id = fatura_id;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
            
            OPEN urun_cursor;
            
            read_loop: LOOP
                FETCH urun_cursor INTO urun_id, miktar, olcum_birimi;
                IF done THEN
                    LEAVE read_loop;
                END IF;
                
                -- Ürünün stoğunu arttır
                UPDATE urunler 
                SET stok_miktari = stok_miktari + miktar
                WHERE id = urun_id;
                
                -- Stok hareketi ekle
                INSERT INTO stok_hareketleri (
                    urun_id, hareket_tipi, miktar, olcum_birimi, 
                    aciklama, fatura_id, kullanici_id, created_at
                ) VALUES (
                    urun_id, 'giris', miktar, olcum_birimi, 
                    'Alış faturası (onaylandı)', fatura_id, NEW.onaylayan_id, NOW()
                );
            END LOOP;
            
            CLOSE urun_cursor;
        END;
        
        -- Tedarikçi bakiyesini güncelle
        UPDATE tedarikciler 
        SET cari_bakiye = cari_bakiye + NEW.tutar
        WHERE id = NEW.tedarikci_id;
        
        -- Fatura durumunu güncelle
        UPDATE faturalar 
        SET onayli = 1, onay_durumu = 'onaylandi'
        WHERE id = fatura_id;
    END IF;
END //

DELIMITER ;

-- Ödeme onaylandığında tedarikçi cari bakiye azaltma
DELIMITER //

CREATE TRIGGER after_odeme_onay
AFTER UPDATE ON onay_islemleri
FOR EACH ROW
BEGIN
    -- Sadece ödeme işlemi ve durumu 'bekliyor'dan 'onaylandi'ya değişirse
    IF NEW.islem_tipi = 'odeme' AND NEW.durum = 'onaylandi' AND OLD.durum = 'bekliyor' THEN
        -- Tedarikçi bakiyesini azalt
        UPDATE tedarikciler 
        SET cari_bakiye = cari_bakiye - NEW.tutar
        WHERE id = NEW.tedarikci_id;
        
        -- Ödeme durumunu güncelle
        UPDATE odeme_tahsilat 
        SET onayli = 1, onay_durumu = 'onaylandi'
        WHERE id = NEW.islem_id;
    END IF;
END //

DELIMITER ; 