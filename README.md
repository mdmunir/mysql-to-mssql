# Migrasi dari mysql ke mssql

## Instalasi
```
# git clone https://github.com/mdmunir/mysql-to-mssql.git
# cd mysql-to-mssql
# php init
# composer install
```

Kemudian ubah pengaturan koneksi database asal dan tujuan di file `.env`.

## Penggunaan
Untuk menggunakan tool ini, pertama-tama buat file task di folder `tasks`. 
Isi dari file tersebut adalah list maping tabel asal dan tabel tujuan migrasi.
Satu task bisa berisi beberapa tabel sesuai kebutuhan.

Ada 2 metode migrasi yang tersedia yaitu export ke file .sql atau mengkopi langsung ke database.

## Metode export ke file.
```
#php yii mssql/export-to-file <name> [...options...]

```
![method-1](https://github.com/user-attachments/assets/9da1caad-dd01-469c-aeb5-feb4fae58482)


## Metode kopi ke database tujuan.
```
#php yii mssql/export-copy <name> [...options...]

```
![method-2](https://github.com/user-attachments/assets/ab0f2bf9-8775-472d-9fbc-ef88c54973fd)
