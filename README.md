# Dokumentasi Proyek

**Nama:** Anas Syihabuddin  
**Domisili:** Magelang

## Dokumentasi

- [Dokumentasi Github](https://github.com/NRayaa/Anas-Dibuiltadi) | https://github.com/NRayaa/Anas-Dibuiltadi
- [Dokumentasi API (Postman)](https://documenter.getpostman.com/view/31001466/2sAXjNZrfa) | https://documenter.getpostman.com/view/31001466/2sAXjNZrfa

## Catatan Pengerjaan

- API sesuai dengan soal yang diberikan.
- Dokumentasi timeline pengerjaan tersedia di [link GitHub bagian Projects](https://github.com/NRayaa/Anas-Dibuiltadi/projects) (sudah saya public).
- Dokumentasi juga bisa di-clone dari GitHub.
- Pengerjaan menggunakan Redis untuk menyimpan cache.

## Catatan Pemakaian

1. **Start server di terminal folder proyek dengan perintah :**
   ```bash
   php artisan serve

2. **Start redis-server dengan menggunakan wsl (jika windows install wsl terlebih dahulu) menggunakan perintah :**
   ```bash
   sudo service redis-server start

3. **Cek cache menggunakan perintah :**
   ```bash
   redis-cli

4. **Cek key yang ada di cache dengan perintah :**
   ```bash
   keys *

5. **Cek data yang ada di cache dengan perintah :**
   ```bash
   get "nama keys"
