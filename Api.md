# Dokumentasi API BaBook (Autentikasi)

Dokumen ini untuk tim frontend (Flutter). Semua endpoint menerima dan membalas **JSON**.

## Base URL

| Lingkungan                               | Base URL                      |
| ---------------------------------------- | ----------------------------- |
| Emulator Android                         | `http://10.0.2.2:8080/api`    |
| HP asli (satu Wi-Fi dengan laptop)       | `http://<IP-laptop>:8080/api` |
| Tes di laptop (Thunder Client / Postman) | `http://localhost:8080/api`   |

Catatan: `localhost` di emulator menunjuk ke emulator itu sendiri, bukan ke laptop. Karena itu emulator memakai `10.0.2.2`.

## Aturan Umum

- Header wajib untuk request ber-body: `Content-Type: application/json`
- Endpoint yang butuh login memakai header: `Authorization: Bearer <token>`
- Token berlaku **7 hari**. Setelah kedaluwarsa, user harus login ulang.
- Kode OTP reset password berlaku **10 menit** dan hanya bisa dipakai **sekali**.

### Format Respons

Sukses:

```json
{
  "success": true,
  "message": "Pesan sukses",
  "data": {}
}
```

Gagal:

```json
{
  "success": false,
  "message": "Pesan error"
}
```

Field `data` hanya ada jika ada data yang dikembalikan.

### Kode Status HTTP

| Kode | Arti                                                        |
| ---- | ----------------------------------------------------------- |
| 200  | Berhasil                                                    |
| 201  | Berhasil dibuat (register)                                  |
| 400  | Body bukan JSON valid, atau kode OTP salah/kedaluwarsa      |
| 401  | Belum login, token salah/kedaluwarsa, atau kredensial salah |
| 405  | Method HTTP tidak sesuai                                    |
| 409  | Konflik (email sudah terdaftar)                             |
| 422  | Validasi input gagal                                        |
| 500  | Kesalahan server                                            |

---

## 1. Register

Membuat akun baru.

- **Method:** `POST`
- **URL:** `/register.php`
- **Auth:** tidak perlu

**Body**

| Field      | Tipe   | Aturan                                           |
| ---------- | ------ | ------------------------------------------------ |
| `name`     | string | wajib, maksimal 100 karakter                     |
| `email`    | string | wajib, format email valid, maksimal 150 karakter |
| `password` | string | wajib, minimal 8 karakter                        |

```json
{
  "name": "Budi",
  "email": "budi@mail.com",
  "password": "rahasia123"
}
```

**Respons sukses (201)**

```json
{
  "success": true,
  "message": "Registrasi berhasil",
  "data": {
    "user": { "id": 1, "name": "Budi", "email": "budi@mail.com" }
  }
}
```

**Respons gagal**

| Status | Kondisi               | Contoh respons                                                         |
| ------ | --------------------- | ---------------------------------------------------------------------- |
| 409    | Email sudah terdaftar | `{ "success": false, "message": "Email sudah terdaftar" }`             |
| 422    | Validasi gagal        | lihat di bawah                                                         |
| 400    | Body bukan JSON       | `{ "success": false, "message": "Body harus berupa JSON yang valid" }` |

Contoh 422 (detail per field ada di `data.errors`):

```json
{
  "success": false,
  "message": "Validasi gagal",
  "data": {
    "errors": {
      "email": "Format email tidak valid",
      "password": "Password minimal 8 karakter"
    }
  }
}
```

Catatan: email disimpan dalam huruf kecil, jadi `Budi@mail.com` dan `budi@mail.com` dianggap sama. Register **tidak** langsung memberi token; arahkan user ke halaman login.

---

## 2. Login

Memverifikasi email dan password, lalu mengembalikan token.

- **Method:** `POST`
- **URL:** `/login.php`
- **Auth:** tidak perlu

**Body**

| Field      | Tipe   | Aturan |
| ---------- | ------ | ------ |
| `email`    | string | wajib  |
| `password` | string | wajib  |

```json
{
  "email": "budi@mail.com",
  "password": "rahasia123"
}
```

**Respons sukses (200)**

```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "8eea24403845dd7902e778df203ea9...(64 karakter)",
    "user": { "id": 1, "name": "Budi", "email": "budi@mail.com" }
  }
}
```

Simpan `data.token` di penyimpanan aman (misalnya paket `flutter_secure_storage`) dan kirim di header `Authorization` pada request berikutnya.

**Respons gagal**

| Status | Kondisi                                   | Pesan                               |
| ------ | ----------------------------------------- | ----------------------------------- |
| 401    | Email tidak terdaftar atau password salah | `Email atau password salah`         |
| 422    | Email atau password kosong                | `Email dan password wajib diisi`    |
| 400    | Body bukan JSON                           | `Body harus berupa JSON yang valid` |

Pesan 401 sengaja sama untuk "email tidak ada" dan "password salah". Jangan coba membedakannya di aplikasi.

---

## 3. Data User yang Sedang Login (Me)

Mengecek token dan mengambil data user. Cocok dipakai saat aplikasi dibuka untuk menentukan user masih login atau tidak.

- **Method:** `GET`
- **URL:** `/me.php`
- **Auth:** wajib

**Header**

```
Authorization: Bearer <token>
```

Tulis token apa adanya setelah satu spasi, tanpa tanda `<` `>` dan tanpa tanda kutip.

**Respons sukses (200)**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "user": { "id": 1, "name": "Budi", "email": "budi@mail.com" }
  }
}
```

**Respons error**

| Status | Pesan                                      | Tindakan di aplikasi                            |
| ------ | ------------------------------------------ | ----------------------------------------------- |
| 401    | `Token tidak ditemukan`                    | Arahkan ke halaman login                        |
| 401    | `Token tidak valid atau sudah kedaluwarsa` | Hapus token tersimpan, arahkan ke halaman login |

---

## 4. Lupa Password (Minta Kode)

Mengirim kode OTP 6 digit ke email user.

- **Method:** `POST`
- **URL:** `/forgot-password.php`
- **Auth:** tidak perlu

**Body**

```json
{
  "email": "budi@mail.com"
}
```

**Respons sukses (200)**

```json
{
  "success": true,
  "message": "Jika email terdaftar, kode verifikasi telah dikirim"
}
```

Respons ini **sama persis** baik email terdaftar maupun tidak (agar email yang terdaftar tidak bisa ditebak). Jadi tampilkan pesan netral di aplikasi, misalnya "Jika email terdaftar, cek kotak masuk kamu", lalu arahkan ke halaman input kode.

**Respons gagal**

| Status | Kondisi                  | Pesan                      |
| ------ | ------------------------ | -------------------------- |
| 422    | Format email tidak valid | `Format email tidak valid` |

Saat development, email tidak dikirim ke inbox sungguhan melainkan ke Mailpit di `http://localhost:8025`.

---

## 5. Reset Password (Pakai Kode)

Mengganti password memakai kode OTP dari email.

- **Method:** `POST`
- **URL:** `/reset-password.php`
- **Auth:** tidak perlu

**Body**

| Field          | Tipe   | Aturan                                                                       |
| -------------- | ------ | ---------------------------------------------------------------------------- |
| `email`        | string | wajib, email yang sama dengan saat minta kode                                |
| `otp`          | string | wajib, tepat 6 digit angka (kirim sebagai **string**, karena bisa diawali 0) |
| `new_password` | string | wajib, minimal 8 karakter                                                    |

```json
{
  "email": "budi@mail.com",
  "otp": "048213",
  "new_password": "passwordbaru123"
}
```

**Respons sukses (200)**

```json
{
  "success": true,
  "message": "Password berhasil diubah, silakan login kembali"
}
```

Setelah sukses, **semua token lama user dihapus** (logout dari semua perangkat). Arahkan user ke halaman login.

**Respons gagal**

| Status | Kondisi                                     | Pesan                               |
| ------ | ------------------------------------------- | ----------------------------------- |
| 400    | Kode salah, sudah dipakai, atau kedaluwarsa | `Kode salah atau sudah kedaluwarsa` |
| 422    | Email/kode tidak valid                      | `Email atau kode tidak valid`       |
| 422    | Password baru kurang dari 8 karakter        | `Password minimal 8 karakter`       |

Jika kode kedaluwarsa, user harus meminta kode baru lewat endpoint Lupa Password.

---

## Alur Layar yang Disarankan

1. **Register** (201) → ke layar Login
2. **Login** (200) → simpan token → ke layar utama
3. **Buka aplikasi** → panggil `/me.php`: 200 = lanjut ke layar utama, 401 = ke layar Login
4. **Lupa Password** → kirim email → layar input kode + password baru → **Reset Password** → ke layar Login

## Contoh Pemanggilan di Flutter (paket `http`)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

const baseUrl = 'http://10.0.2.2:8080/api';

Future<Map<String, dynamic>> login(String email, String password) async {
  final res = await http.post(
    Uri.parse('$baseUrl/login.php'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({'email': email, 'password': password}),
  );
  final body = jsonDecode(res.body) as Map<String, dynamic>;
  // res.statusCode: 200 = sukses, 401 = kredensial salah, dst.
  return body;
}

Future<Map<String, dynamic>> getMe(String token) async {
  final res = await http.get(
    Uri.parse('$baseUrl/me.php'),
    headers: {'Authorization': 'Bearer $token'},
  );
  return jsonDecode(res.body) as Map<String, dynamic>;
}
```

Untuk HTTP (bukan HTTPS) di Android, tambahkan `android:usesCleartextTraffic="true"` pada tag `<application>` di `android/app/src/main/AndroidManifest.xml`. Ini hanya untuk development.

## Menjalankan Backend

```powershell
docker compose up -d --build
docker compose exec web composer install
```

| Layanan                   | Alamat                |
| ------------------------- | --------------------- |
| API                       | http://localhost:8080 |
| phpMyAdmin                | http://localhost:8081 |
| Mailpit (inbox email tes) | http://localhost:8025 |

File `.env` tidak ada di repositori. Salin dari `.env.example` lalu isi password-nya.
