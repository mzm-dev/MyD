<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MyDigitalID Configuration
|--------------------------------------------------------------------------
|
| This section contains the configuration for connecting to MyDigitalID.
|
*/
return [
    'base_url' => env('MYDIGITALID_BASE_URL'),
    'realm' => env('MYDIGITALID_REALM'),
    'client_id' => env('MYDIGITALID_CLIENT_ID'),
    'client_secret' => env('MYDIGITALID_CLIENT_SECRET'),
    'redirect_uri' => env('MYDIGITALID_REDIRECT_URI'),
    'post_logout_redirect_uri' => env('MYDIGITALID_LOGOUT_REDIRECT_URI'),
    'cache_openid' => false,
    'cache_openif_ttl' => 3600,
    // Model Eloquent yang disimpan kolum NRIC — default terus pada model User
    // Tukar jika NRIC disimpan pada table lain (cth: staffs, user_profiles) yang belongsTo(User)
    // Dikekalkan untuk backward-compat; guna 'user_providers' di bawah jika perlu semak > 1 table.
    'user_provider_model' => env('MYDIGITALID_USER_PROVIDER_MODEL', \App\Models\User::class),
    // Nama kolum pada `user_provider_model` yang dipadankan dengan nric/IC dari MyDigitalID
    'user_provider_credential' => env('MYDIGITALID_USER_PROVIDER_CREDENTIAL', 'no_kp'),
    // Nama relation belongsTo(User::class) pada `user_provider_model` untuk dapatkan balik instance User
    // null = user_provider_model itu sendiri ialah model User (kes biasa)
    'user_provider_relation' => env('MYDIGITALID_USER_PROVIDER_RELATION'),
    // Senarai model/kolum/relation yang disemak secara berurutan bagi carian NRIC.
    // Digunakan oleh controller jika ditetapkan (override 'user_provider_*' di atas).
    // Setiap NRIC pemohon/staff disemak sehingga jumpa padanan pertama.
    'user_providers' => [
        // [
        //     'model' => \App\Models\User::class,
        //     'credential' => 'nokp',
        //     'relation' => null,
        // ],
        [
            'model' => \App\Models\StaffProfile::class,
            'credential' => 'no_kp',
            'relation' => 'user',
        ],
        [
            'model' => \App\Models\PemohonProfile::class,
            'credential' => 'no_kad_pengenalan',
            'relation' => 'user',
        ],
    ],
    // Nama route destinasi selepas login/berjaya diarah dari /mydigitalid/home
    'home_route' => env('MYDIGITALID_SUCCESS_REDIRECT_ROUTE', 'home'),
    // Nama route destinasi apabila NRIC tidak dijumpai (/mydigitalid/notfound); null = abort 404 (default)
    'notfound_route' => env('MYDIGITALID_NOTFOUND_REDIRECT_ROUTE'),
    'notfound_message' => env('MYDIGITALID_NOTFOUND_MESSAGE',"NRIC NOT FOUND"),
    // Nama log channel (config('logging.channels.*')) untuk audit trail MyDigitalID.
    // Jika channel ini belum wujud dalam config/logging.php aplikasi, service provider
    // akan daftar default channel 'daily' ke storage/logs/mydigitalid-*.log
    'log_channel' => env('MYDIGITALID_LOG_CHANNEL', 'mydigitalid'),
    // Bilangan hari fail log disimpan sebelum dipadam (hanya terpakai pada default channel)
    'log_retention_days' => (int) env('MYDIGITALID_LOG_RETENTION_DAYS', 30),
    // Unix file permission untuk fail log baru (hanya terpakai pada default channel).
    // Sengaja tidak env-driven — nilai octal melalui .env (string) mudah tersalah tafsir jadi decimal.
    // Edit terus di sini (selepas vendor:publish --tag=mydigitalid-config) jika perlu tukar.
    'log_file_permission' => 0640,
    'append_decoded_token' => false,
    'allowed_resources' => null,
    'guzzle_options' => [],
];
