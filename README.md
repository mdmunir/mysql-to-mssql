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
Format file task adalah
```
<nama_tabel_asal>=<nama_tabel_tujuan> [;begin=xxx; end=xxx]
```
Parameter `begin` dan `end` pada file task bersifat opsional dan hanya dipakai di metode ketiga.

Ada 3 metode migrasi yang tersedia yaitu export ke file .sql, mengkopi langsung ke database atau mengkopi ke database dengan merge.
Dua metode yang pertama akan menghapus terlebih dahulu isi tabel. Sedangkan metode ketiga melakukan merge ke data eksisting.
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

## Metode kopi ke database tujuan dengan merge.
```
#php yii mssql/export-merge <name> [...options...]

```
![method-2](https://github.com/user-attachments/assets/ab0f2bf9-8775-472d-9fbc-ef88c54973fd)
