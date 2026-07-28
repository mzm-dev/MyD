# mzm/mydigitalid

Local package untuk integrasi log masuk **MyDigital ID** (MIMOS / Keycloak OpenID Connect) ke dalam aplikasi iBaucar.

Package ini menyediakan flow SSO penuh: redirect ke MyDigital ID → callback → tukar `code` kepada access token → ambil `userinfo` → padankan NRIC dengan pengguna sedia ada → log masuk terus.

## Keperluan

- PHP ^8.3
- Laravel ^13.0
- Model `App\Models\User` dengan kolum NRIC (default `no_kp`, boleh ditukar — lihat [Konfigurasi](#konfigurasi))

## Pemasangan (Local Path Package)

Package ini **tidak** diterbitkan di Packagist — ia dimuatkan terus dari folder `packages/mzm/mydigitalid` dalam repo yang sama menggunakan [Composer path repository](https://getcomposer.org/doc/05-repositories.md#path).

### 1. Daftar path repository

Di `composer.json` root aplikasi:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/mzm/mydigitalid"
        }
    ],
    "require": {
        "mzm/mydigitalid": "@dev"
    }
}
```

> Guna constraint `@dev` (bukan `*`) kerana package path tanpa tag version akan dikesan sebagai `dev-<nama-branch>`, dan root `composer.json` biasanya `minimum-stability: stable`.

### 2. Install / update

```bash
composer update mzm/mydigitalid
```

Composer akan **symlink** folder `packages/mzm/mydigitalid` ke dalam `vendor/mzm/mydigitalid` dan auto-discover `MyDigitalIdServiceProvider` (tiada perlu daftar manual dalam `bootstrap/providers.php` / `config/app.php`).

### 3. Jalankan migration

```bash
php artisan migrate
```

> Package ini pernah menyimpan audit trail dalam table `mydigitalid_logs`, tetapi telah dipindah ke log file khusus (lihat [Audit Trail / Logging](#audit-trail--logging)) — data sensitif (token, NRIC) tidak lagi duduk dalam DB yang boleh diakses ramai role/admin. Migration terkini akan `dropIfExists` table tersebut jika ia wujud dari versi lama.

Migration dimuatkan terus dari package (`loadMigrationsFrom`) — tiada fail migration disalin ke `database/migrations/` aplikasi utama.

### 4. Tetapkan environment variables

Tambah dalam `.env` aplikasi (nilai sebenar dapatkan daripada MIMOS/pentadbir MyDigital ID):

```env
MYDIGITALID_BASE_URL=https://sso.digital-id.my
MYDIGITALID_REALM=nsgovmy
MYDIGITALID_CLIENT_ID=nsgovmy-ibaucar
MYDIGITALID_CLIENT_SECRET=
MYDIGITALID_REDIRECT_URI="${APP_URL}/mydigitalid/callback"
MYDIGITALID_LOGOUT_REDIRECT_URI="${APP_URL}"

# Optional — default sudah mencukupi untuk kebanyakan kes
MYDIGITALID_USER_PROVIDER_MODEL=
MYDIGITALID_USER_PROVIDER_CREDENTIAL=no_kp
MYDIGITALID_USER_PROVIDER_RELATION=
MYDIGITALID_SUCCESS_REDIRECT_ROUTE=home
MYDIGITALID_NOTFOUND_REDIRECT_ROUTE=
MYDIGITALID_LOG_CHANNEL=mydigitalid
MYDIGITALID_LOG_RETENTION_DAYS=30
```

> `MYDIGITALID_USER_PROVIDER_MODEL` / `_RELATION` hanya perlu diisi jika NRIC **tidak** disimpan terus pada table `users` — lihat [Konfigurasi](#konfigurasi).

Tiada fail config perlu di-publish — semua key di-`mergeConfigFrom` secara automatik oleh service provider. Jika perlu override struktur config sepenuhnya, boleh publish salinan lokal:

```bash
php artisan vendor:publish --tag=mydigitalid-config
```

Ini akan salin `config/mydigitalid.php` package ke `config/mydigitalid.php` aplikasi (override penuh).

## Pemasangan di Projek Laravel Lain (Standalone Repo)

Selain `ibaucar`, package ini boleh disync ke repo git berasingan (cth: GitHub/GitLab) menggunakan `git subtree`, supaya projek Laravel lain boleh guna `mzm/mydigitalid` tanpa perlu akses terus ke monorepo `ibaucar`.

### 1. Sync dari `ibaucar` ke repo berasingan (sekali sahaja setup)

Dijalankan dari root repo `ibaucar` (bukan dari dalam folder package):

```bash
# Setup sekali sahaja — daftar remote untuk repo package berasingan
git remote add mydigitalid-package git@github.com:org-anda/mydigitalid-package.git

# Push history packages/mzm/mydigitalid ke remote tu
git subtree push --prefix=packages/mzm/mydigitalid mydigitalid-package main
```

> Repo remote (`mydigitalid-package`) perlu **kosong sepenuhnya** semasa push pertama (tiada README/license auto-generate) untuk elak error "unrelated histories".

Ulang `git subtree push` yang sama setiap kali ada commit baru pada `packages/mzm/mydigitalid` dalam `ibaucar` untuk sync perubahan terkini.

### 2. Consume di projek Laravel lain

Berbeza dengan `ibaucar` (guna `path` repository sebab folder wujud secara fizikal dalam repo yang sama), projek lain daftar sebagai **VCS repository** dalam `composer.json` mereka:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:org-anda/mydigitalid-package.git"
        }
    ],
    "require": {
        "mzm/mydigitalid": "dev-main"
    }
}
```

```bash
composer require mzm/mydigitalid:dev-main
```

Composer akan **clone** (bukan symlink) repo tu ke `vendor/mzm/mydigitalid`. Auto-discovery `MyDigitalIdServiceProvider` tetap berfungsi sama seperti di `ibaucar`. Selepas itu, ikut langkah [migration](#3-jalankan-migration) dan [environment variables](#4-tetapkan-environment-variables) yang sama.

### Nota

- **Repo private** — perlukan akses git (SSH deploy key, atau HTTPS token via `composer config --global --auth github-oauth.github.com <token>`) supaya `composer install`/CI boleh clone.
- **Versioning** — `dev-main` ikut branch terkini (boleh berubah bila-bila). Untuk stabiliti, tag release kat repo package (`git tag v1.0.0 && git push mydigitalid-package v1.0.0`) supaya projek lain boleh pin `"mzm/mydigitalid": "^1.0"` instead of `dev-main`.
- **Update** — projek lain cukup `composer update mzm/mydigitalid` untuk tarik versi/commit terkini selepas `ibaucar` sync perubahan baru.

## Konfigurasi (`config('mydigitalid')`)

| Key | Env | Default | Kegunaan |
|---|---|---|---|
| `base_url` | `MYDIGITALID_BASE_URL` | — | URL pelayan Keycloak/MyDigital ID |
| `realm` | `MYDIGITALID_REALM` | — | Nama realm Keycloak |
| `client_id` | `MYDIGITALID_CLIENT_ID` | — | Client ID OIDC |
| `client_secret` | `MYDIGITALID_CLIENT_SECRET` | — | Client secret OIDC (rahsia) |
| `redirect_uri` | `MYDIGITALID_REDIRECT_URI` | — | URL callback, biasanya `{APP_URL}/mydigitalid/callback` |
| `post_logout_redirect_uri` | `MYDIGITALID_LOGOUT_REDIRECT_URI` | fallback `route('login')` | Destinasi selepas logout OIDC |
| `user_provider_model` | `MYDIGITALID_USER_PROVIDER_MODEL` | `App\Models\User` | Model Eloquent yang disimpan kolum NRIC. Kekal default jika NRIC terus di table `users`; tukar (cth: `App\Models\Staff`) jika NRIC disimpan di table lain |
| `user_provider_credential` | `MYDIGITALID_USER_PROVIDER_CREDENTIAL` | `no_kp` | Nama kolum pada `user_provider_model` yang dipadankan dengan NRIC dari MyDigital ID — tukar jika skema aplikasi lain guna nama kolum berbeza |
| `user_provider_relation` | `MYDIGITALID_USER_PROVIDER_RELATION` | `null` | Nama relation `belongsTo(User::class)` pada `user_provider_model` untuk dapatkan balik instance `User` selepas carian NRIC. Biarkan kosong jika `user_provider_model` itu sendiri ialah `User` |
| `home_route` | `MYDIGITALID_SUCCESS_REDIRECT_ROUTE` | `home` | Nama route Laravel destinasi selepas `/mydigitalid/home` — tukar jika aplikasi lain guna nama route landing page berbeza |
| `notfound_route` | `MYDIGITALID_NOTFOUND_REDIRECT_ROUTE` | `null` | Nama route Laravel destinasi apabila NRIC tidak dijumpai (`/mydigitalid/notfound`). Jika kosong/null, kekal `abort(404)` seperti asal |
| `log_channel` | `MYDIGITALID_LOG_CHANNEL` | `mydigitalid` | Nama log channel (`config('logging.channels.*')`) untuk audit trail. Jika aplikasi dah define channel dengan nama sama dalam `config/logging.php`, package akan guna channel itu terus |
| `log_retention_days` | `MYDIGITALID_LOG_RETENTION_DAYS` | `30` | Bilangan hari fail log disimpan sebelum auto-dipadam — hanya terpakai pada default channel package |
| `log_file_permission` | — (edit config selepas publish) | `0640` | Unix file permission fail log baru — hanya terpakai pada default channel package |
| `cache_openid` / `cache_openif_ttl` | — | `false` / `3600` | Placeholder untuk caching konfigurasi OpenID (belum digunakan) |
| `append_decoded_token` | — | `false` | Placeholder (belum digunakan) |
| `allowed_resources` | — | `null` | Placeholder (belum digunakan) |
| `guzzle_options` | — | `[]` | Placeholder untuk opsyen HTTP client tambahan (belum digunakan) |

Kunci `user_provider_model`, `user_provider_credential`, `user_provider_relation`, `home_route`, dan `notfound_route` sengaja dijadikan configurable supaya aplikasi lain yang guna package ini tidak perlu edit kod dalam `src/` — cukup tetapkan `.env`.

### Contoh: NRIC pada table berasingan (bukan `users`)

Jika NRIC disimpan pada table lain (cth: `staffs`) yang mempunyai `belongsTo(User::class, ...)` bernama `user`:

```env
MYDIGITALID_USER_PROVIDER_MODEL=App\Models\Staff
MYDIGITALID_USER_PROVIDER_CREDENTIAL=no_kp
MYDIGITALID_USER_PROVIDER_RELATION=user
```

Package akan cari `Staff::where('no_kp', $nric)->first()`, kemudian ambil `$staff->user` untuk login. Pastikan model `Staff` ada relation `user()` yang betul.

## Audit Trail / Logging

Setiap langkah flow SSO (`AUTH_REDIRECT`, `STATE_VALIDATION_FAIL`, `SECURITY_FAIL`, `TOKEN_EXCHANGE_*`, `USERINFO*`, `LOGIN_SUCCESS`/`LOGIN_FAILED`, `LOGOUT`) direkod ke **log channel khusus** (`Log::channel(config('mydigitalid.log_channel'))`) — **bukan** ke table DB.

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

| Method | URI | Nama Route | Middleware |
|---|---|---|---|
| GET | `/mydigitalid/login` | `mydigitalid.login` | `guest` |
| GET | `/mydigitalid/callback` | `mydigitalid.callback` | `guest` |
| GET | `/mydigitalid/notfound` | `mydigitalid.notfound` | `guest` |
| GET | `/mydigitalid/link` | `mydigitalid.link` | `guest` |
| GET | `/mydigitalid/home` | `mydigitalid.home` | `auth` |
| POST | `/mydigitalid/logout` | `mydigitalid.logout` | `auth` |

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

| Prop | Default | Kegunaan |
|---|---|---|
| `label` | `Log Masuk dengan MyDigital ID` | Teks pada butang |
| `icon` | `bi-shield-lock` | Nama class Bootstrap Icon (`bi-*`); set `icon=""` untuk buang ikon |
| `class` (attribute) | `btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2` | Digabung (`merge`) dengan class sedia ada component |

### Ubah reka bentuk sepenuhnya (stub)

Jika perubahan props tidak mencukupi (contoh: perlu tukar struktur HTML sepenuhnya), publish fail view component ke aplikasi supaya boleh diedit terus — Laravel akan utamakan versi yang di-publish berbanding versi dalam package:

```bash
php artisan vendor:publish --tag=mydigitalid-views
```

Ini akan salin `resources/views/components/login-button.blade.php` ke:

```
resources/views/vendor/mydigitalid/components/login-button.blade.php
```

Edit fail tersebut mengikut keperluan reka bentuk aplikasi — `<x-mydigitalid::login-button />` akan terus guna versi yang di-publish tanpa perlu ubah kod dalam `packages/mzm/mydigitalid/`.

## Struktur folder

```
packages/mzm/mydigitalid/
├── composer.json
├── config/mydigitalid.php
├── database/migrations/
│   ├── 2026_02_10_000000_create_mydigitalid_logs_table.php
│   └── 2026_02_11_000000_drop_mydigitalid_logs_table.php
├── resources/views/components/login-button.blade.php
├── routes/web.php
└── src/
    ├── MyDigitalIdServiceProvider.php
    └── Http/Controllers/MyDigitalIdController.php
```
