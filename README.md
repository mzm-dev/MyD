# mzm/mydigitalid

Package untuk integrasi log masuk **MyDigital ID** (MIMOS / Keycloak OpenID Connect) ke dalam aplikasi Laravel.

Package ini menyediakan flow SSO penuh: redirect ke MyDigital ID → callback → tukar `code` kepada access token → ambil `userinfo` → padankan NRIC dengan pengguna sedia ada → log masuk terus.

## Keperluan

- PHP ^8.3
- Laravel ^12.0 atau ^13.0
- Model `App\Models\User` dengan kolum NRIC (default `nokp`, boleh ditukar — lihat [Konfigurasi](#konfigurasi))

## Pemasangan

Package ini **tidak** diterbitkan di Packagist. Daftarkan repo git package ini sebagai [VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs).

### 1. Daftar repository

Di `composer.json` aplikasi, gantikan `url` dengan alamat repo package ini:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@example.com:organisasi/mydigitalid.git"
    }
  ]
}
```

Jika repo bersifat private, pelayan dan CI memerlukan akses git (SSH deploy key atau token).

### 2. Install

```bash
composer require mzm/mydigitalid:^1.1
```

`MyDigitalIdServiceProvider` dikesan secara automatik, jadi tiada perlu daftar manual dalam `bootstrap/providers.php`. Untuk versi terkini dalam julat yang sama, jalankan `composer update mzm/mydigitalid`.

### 3. Tetapkan environment variables

Tambah dalam `.env` aplikasi (nilai sebenar dapatkan daripada MIMOS/pentadbir MyDigital ID):

```env
MYDIGITALID_BASE_URL=https://sso.digital-id.my
MYDIGITALID_REALM=realm
MYDIGITALID_CLIENT_ID=realm-aps
MYDIGITALID_CLIENT_SECRET=
MYDIGITALID_REDIRECT_URI="${APP_URL}/mydigitalid/callback"
MYDIGITALID_LOGOUT_REDIRECT_URI="${APP_URL}"

# Optional — default sudah mencukupi untuk kebanyakan kes
MYDIGITALID_SUCCESS_REDIRECT_ROUTE=home
MYDIGITALID_NOTFOUND_REDIRECT_ROUTE=
MYDIGITALID_LOG_CHANNEL=mydigitalid
MYDIGITALID_LOG_RETENTION_DAYS=30
```

> Jika NRIC **tidak** disimpan terus pada table `users`, lihat [Konfigurasi](#konfigurasi).

Tiada fail config perlu di-publish — semua key di-`mergeConfigFrom` secara automatik oleh service provider. Jika perlu override struktur config sepenuhnya, boleh publish salinan lokal:

```bash
php artisan vendor:publish --tag=mydigitalid-config
```

Ini akan salin `config/mydigitalid.php` package ke `config/mydigitalid.php` aplikasi (override penuh).

## Konfigurasi (`config('mydigitalid')`)

| Key                                 | Env                                    | Default                   | Kegunaan                                                                                                                                                                                       |
| ----------------------------------- | -------------------------------------- | ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `base_url`                          | `MYDIGITALID_BASE_URL`                 | —                         | URL pelayan Keycloak/MyDigital ID                                                                                                                                                              |
| `realm`                             | `MYDIGITALID_REALM`                    | —                         | Nama realm Keycloak                                                                                                                                                                            |
| `client_id`                         | `MYDIGITALID_CLIENT_ID`                | —                         | Client ID OIDC                                                                                                                                                                                 |
| `client_secret`                     | `MYDIGITALID_CLIENT_SECRET`            | —                         | Client secret OIDC (rahsia)                                                                                                                                                                    |
| `redirect_uri`                      | `MYDIGITALID_REDIRECT_URI`             | —                         | URL callback, biasanya `{APP_URL}/mydigitalid/callback`                                                                                                                                        |
| `post_logout_redirect_uri`          | `MYDIGITALID_LOGOUT_REDIRECT_URI`      | fallback `route('login')` | Destinasi selepas logout OIDC                                                                                                                                                                  |
| `user_providers` | — (edit config selepas publish) | `User` / `nokp` | Senarai `model`, `credential` dan `relation` yang disemak mengikut turutan sehingga NRIC dijumpai |
| `home_route`                        | `MYDIGITALID_SUCCESS_REDIRECT_ROUTE`   | `home`                    | Nama route Laravel destinasi selepas `/mydigitalid/home` — tukar jika aplikasi lain guna nama route landing page berbeza                                                                       |
| `notfound_route` | `MYDIGITALID_NOTFOUND_REDIRECT_ROUTE` | `null` | Nama route aplikasi untuk pengguna yang NRIC-nya tidak dijumpai, contohnya halaman pendaftaran. Jika kosong, `notfound_message` dipaparkan dengan status 403 |
| `notfound_message` | `MYDIGITALID_NOTFOUND_MESSAGE` | `NRIC NOT FOUND` | Mesej apabila `notfound_route` tidak ditetapkan |
| `status_field`                      | `MYDIGITALID_STATUS_FIELD`             | `null`                    | Kolum status pada model `User`. Jika kosong/null, status tidak disemak                                                                                                                         |
| `active_value`                      | `MYDIGITALID_ACTIVE_VALUE`             | `1`                       | Nilai `status_field` yang membenarkan log masuk. Pengguna lain dihantar ke `/login` dengan mesej ralat                                                                                         |
| `log_channel`                       | `MYDIGITALID_LOG_CHANNEL`              | `mydigitalid`             | Nama log channel (`config('logging.channels.*')`) untuk audit trail. Jika aplikasi dah define channel dengan nama sama dalam `config/logging.php`, package akan guna channel itu terus         |
| `log_retention_days`                | `MYDIGITALID_LOG_RETENTION_DAYS`       | `30`                      | Bilangan hari fail log disimpan sebelum auto-dipadam — hanya terpakai pada default channel package                                                                                             |
| `log_file_permission`               | — (edit config selepas publish)        | `0640`                    | Unix file permission fail log baru — hanya terpakai pada default channel package                                                                                                               |
| `cache_openid` / `cache_openif_ttl` | —                                      | `false` / `3600`          | Placeholder untuk caching konfigurasi OpenID (belum digunakan)                                                                                                                                 |
| `append_decoded_token`              | —                                      | `false`                   | Placeholder (belum digunakan)                                                                                                                                                                  |
| `allowed_resources`                 | —                                      | `null`                    | Placeholder (belum digunakan)                                                                                                                                                                  |
| `guzzle_options`                    | —                                      | `[]`                      | Placeholder untuk opsyen HTTP client tambahan (belum digunakan)                                                                                                                                |

### Contoh: NRIC pada table berasingan (bukan `users`)

Publish config, kemudian kemas kini `user_providers`. Contoh ini untuk table `staffs` yang mempunyai `belongsTo(User::class)` bernama `user`:

```php
'user_providers' => [
    [
        'model' => \App\Models\Staff::class,
        'credential' => 'no_kp',
        'relation' => 'user',
    ],
],
```

Package akan mencari `Staff::where('no_kp', $nric)->first()`, kemudian menggunakan `$staff->user` untuk log masuk.

## Audit Trail / Logging

Setiap langkah flow SSO (`AUTH_REDIRECT`, `STATE_VALIDATION_FAIL`, `SECURITY_FAIL`, `TOKEN_EXCHANGE_*`, `USERINFO*`, `NRIC_MISSING`, `LOGIN_SUCCESS`/`LOGIN_FAILED`, `LOGOUT`) direkod ke **log channel khusus** (`Log::channel(config('mydigitalid.log_channel'))`) — **bukan** ke table DB.

Sebab: data audit ni kadang mengandungi PII (NRIC) dan token OIDC. Menyimpannya dalam DB app bermakna ia boleh diakses oleh mana-mana role/admin dengan akses DB/backup. Fail log pula boleh dikawal di peringkat server (file permission, retention pendek, tak masuk backup DB rutin).

Secara default, package akan daftar channel `daily` sendiri ke:

```
storage/logs/mydigitalid-YYYY-MM-DD.log
```

dengan file permission `0640` dan retention `30` hari (boleh tukar via `MYDIGITALID_LOG_RETENTION_DAYS` / edit `log_file_permission` selepas publish config).

**Override penuh** — jika aplikasi nak kawal channel ini sepenuhnya (cth: hantar ke Papertrail/CloudWatch/Slack, gabung dalam `stack`), definisikan channel dengan nama yang sama (default `mydigitalid`) terus dalam `config/logging.php` aplikasi. Package akan **hormati** definisi sedia ada dan tidak timpa:

```php
// config/logging.php
'channels' => [
    // ...
    'mydigitalid' => [
        'driver' => 'daily',
        'path' => storage_path('logs/mydigitalid.log'),
        'level' => 'debug',
        'days' => 90,
        'permission' => 0640,
    ],
],
```

> Payload sensitif (`access_token`, `refresh_token`, `id_token`, `nric`, `preferred_username`) tetap di-redact (`***REDACTED***`) sebelum ditulis ke log — walaupun fail log dikawal permission, ini elak kebocoran tidak sengaja bila fail log disalin/dihantar ke sistem lain (log aggregator, tiket sokongan, dsb).

## Route yang didaftarkan

Semua route berprefix `/mydigitalid` dan bernama `mydigitalid.*`:

| Method | URI                     | Nama Route             | Middleware |
| ------ | ----------------------- | ---------------------- | ---------- |
| GET    | `/mydigitalid/login`    | `mydigitalid.login`    | `guest`    |
| GET    | `/mydigitalid/callback` | `mydigitalid.callback` | `guest`    |
| GET    | `/mydigitalid/notfound` | `mydigitalid.notfound` | `guest`    |
| GET    | `/mydigitalid/link`     | `mydigitalid.link`     | `guest`    |
| GET    | `/mydigitalid/home`     | `mydigitalid.home`     | `auth`     |
| POST   | `/mydigitalid/logout`   | `mydigitalid.logout`   | `auth`     |

## Penggunaan di UI

Package ini sediakan Blade component siap-pakai untuk butang log masuk:

```blade
<x-mydigitalid::login-button />
```

Ia render kepada pautan `<a href="{{ route('mydigitalid.login') }}">`. Boleh sesuaikan label, ikon, dan class terus melalui props/attributes tanpa perlu publish apa-apa:

```blade
<x-mydigitalid::login-button
    label="Log Masuk MyDigital ID"
    icon="bi-person-badge"
    class="btn btn-primary w-100"
/>
```

| Prop                | Default                                                                                | Kegunaan                                                           |
| ------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `label`             | `Log Masuk dengan MyDigital ID`                                                        | Teks pada butang                                                   |
| `icon`              | `bi-shield-lock`                                                                       | Nama class Bootstrap Icon (`bi-*`); set `icon=""` untuk buang ikon |
| `class` (attribute) | `btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2` | Digabung (`merge`) dengan class sedia ada component                |

### Ubah reka bentuk sepenuhnya (stub)

Jika perubahan props tidak mencukupi (contoh: perlu tukar struktur HTML sepenuhnya), publish fail view component ke aplikasi supaya boleh diedit terus — Laravel akan utamakan versi yang di-publish berbanding versi dalam package:

```bash
php artisan vendor:publish --tag=mydigitalid-views
```

Ini akan salin `resources/views/components/login-button.blade.php` ke:

```
resources/views/vendor/mydigitalid/components/login-button.blade.php
```

Edit fail tersebut mengikut keperluan reka bentuk aplikasi — `<x-mydigitalid::login-button />` akan terus guna versi yang di-publish tanpa perlu ubah kod package.

## Struktur folder

```
mydigitalid/
├── composer.json
├── config/mydigitalid.php
├── resources/views/components/login-button.blade.php
├── routes/web.php
└── src/
    ├── MyDigitalIdServiceProvider.php
    └── Http/Controllers/MyDigitalIdController.php
```
