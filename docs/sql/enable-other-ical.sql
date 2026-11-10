-- Pilih database PropertyMe di phpMyAdmin sebelum menjalankan SQL ini.
-- Aktifkan satu kolom iCal tambahan memakai platform Lainnya yang sudah ada.
UPDATE `booking_platforms`
SET `supports_ical` = 1
WHERE `slug` = 'lainnya';
