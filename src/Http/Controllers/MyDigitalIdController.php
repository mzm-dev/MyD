<?php

namespace Mzm\MyDigitalId\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyDigitalIdController extends Controller
{
    // 1. Redirect dengan STATE disimpan dalam Cache
    public function redirectToKeycloak(Request $request)
    {
        $config = config('mydigitalid');

        // 1. Jana Token Unik (Ini yang anda maksudkan 'token')
        // Kita panggil ia 'state' untuk patuh standard.
        $uniqueTransactionId = bin2hex(random_bytes(20));

        // 2. Simpan Data Penting Berkaitan Token Ini
        // Contoh: sistem asal yang pengguna ingin masuk
        // Gunakan Cache untuk auto-expiration, kerana Session::put() tidak support chaining
        cache()->put('state_'.$uniqueTransactionId, [
            'target_app' => $request->query('app_id'), // ID sistem asal
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ], now()->addMinutes(5)); // Auto-expire dalam 5 minit

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $uniqueTransactionId,
            'nonce' => bin2hex(random_bytes(16)),
            'prompt' => 'login',
        ]);

        $url = $config['base_url'].'/realms/'.$config['realm'].'/protocol/openid-connect/auth?'.$query;

        // Log Transaksi Mula
        $this->logMyDigitalId('AUTH_REDIRECT', 'PENDING', ['step' => 'Redirect to MyDigital ID'], $uniqueTransactionId, null, $url);

        return redirect()->away($url);
    }

    // 2. Handle Callback dengan Validasi & Logging
    public function handleKeycloakCallback(Request $request)
    {
        // 1. Ambil Token (State) dari URL
        $returnedState = $request->input('state');
        $code = $request->input('code');

        // 2. Validasi Token: Ada tak dalam Cache kita?
        // Gunakan cache()->pull() kerana data disimpan di cache (bukan session)
        $originalData = cache()->pull('state_'.$returnedState); // 'pull' ambil & padam terus

        if (! $originalData) {
            // Token tak wujud atau dah expired
            $this->logMyDigitalId('STATE_VALIDATION_FAIL', 'FAILED', ['error' => 'State not found or expired'], $returnedState);

            return redirect('/login')->withErrors(['error' => 'Sesi tamat tempoh atau pautan tidak sah.']);
        }

        // 3. Validasi Keselamatan Tambahan: Mitigasi state-hijacking
        if ($originalData['ip_address'] !== $request->ip()) {
            $this->logMyDigitalId('SECURITY_FAIL', 'FAILED', ['error' => 'IP Mismatch on callback'], $returnedState);

            return abort(403);
        }

        if (! $code) {
            return redirect('/login')->withErrors(['error' => 'Authorization code not received.']);
        }

        // B. Tukar Code ke Token
        $tokenData = $this->getAccessTokenFromKeycloak($code, $returnedState);

        if (empty($tokenData->access_token)) {
            return redirect('/login')->withErrors(['error' => 'Gagal menghubungi pelayan MyDigital ID.']);
        }

        // C. Dapatkan User Info
        $userInfo = $this->getUserInfoFromKeycloak($tokenData->access_token, $returnedState);

        if (! $userInfo) {
            return redirect('/login')->withErrors(['error' => 'Gagal mendapatkan maklumat pengguna daripada MyDigital ID.']);
        }

        // Mapping data (Pastikan nama field betul ikut spec MIMOS)
        // Biasanya: 'sub' adalah ID unik, 'preferred_username' atau 'nric' adalah IC
        $nric = $userInfo->nric ?? $userInfo->preferred_username ?? null;
        $name = $userInfo->name ?? 'New User';

        // D. Carian User Di Database
        // NRIC dari MyDigital ID mungkin datang berformat (dengan sempang) — bersihkan dahulu
        // supaya sepadan dengan format digit-sahaja yang disimpan di kolum no_kp / no_kad_pengenalan.
        $cleanNric = $nric ? preg_replace('/[^0-9]/', '', $nric) : null;

        // Tanpa NRIC, carian menjadi "IS NULL" dan boleh memadankan pengguna lain
        if (blank($cleanNric)) {
            $this->logMyDigitalId('NRIC_MISSING', 'FAILED', ['error' => 'NRIC not found in userinfo'], $returnedState);

            return redirect('/login')->withErrors(['error' => 'Maklumat pengenalan diri tidak diterima daripada MyDigital ID.']);
        }

        // Senarai model/kolum/relation yang perlu disemak, secara berurutan, sehingga jumpa padanan.
        // Boleh ditukar via config('mydigitalid.user_providers') / .env tanpa perlu edit kod ini.
        $providers = config('mydigitalid.user_providers', [
            [
                'model' => config('mydigitalid.user_provider_model', User::class),
                'credential' => config('mydigitalid.user_provider_credential', 'no_kp'),
                'relation' => config('mydigitalid.user_provider_relation'),
            ],
        ]);

        $user = null;

        foreach ($providers as $provider) {
            $modelClass = $provider['model'];
            $nricColumn = $provider['credential'];
            $relation = $provider['relation'] ?? null;

            $record = $modelClass::where($nricColumn, $cleanNric)->first();

            if ($record) {
                $user = $relation ? $record->{$relation} : $record;

                if ($user) {
                    break;
                }
            }
        }

        // E. Logik Login Aplikasi
        if ($user) {
            if (! $this->isActive($user)) {
                $this->logMyDigitalId('LOGIN_FAILED', 'FAILED', ['error' => 'User inactive', 'method' => 'MyDigital ID'], $returnedState, $user->id);

                return redirect('/login')->withErrors(['error' => 'Akaun anda tidak aktif. Sila hubungi pentadbir sistem.']);
            }

            // Jika user wujud, login terus menggunakan getAuth (SSO login flow)
            return $this->getAuth($request, $user);
        }

        // Jika user baru / tiada dalam sistem
        session()->put('temp_nric', $nric);
        session()->put('temp_name', $name);

        return redirect()->route('mydigitalid.notfound');
    }

    // Fungsi Helper: Get Token
    private function getAccessTokenFromKeycloak($code, $transactionId)
    {
        $config = config('mydigitalid');

        try {
            $response = Http::asForm()->post($config['base_url'].'/realms/'.$config['realm'].'/protocol/openid-connect/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'redirect_uri' => $config['redirect_uri'],
            ]);

            if ($response->failed()) {
                $this->logMyDigitalId('TOKEN_EXCHANGE_FAIL', 'FAILED', ['error' => 'Keycloak API Error', 'response' => $this->redactPayload($response->json() ?? [])], $transactionId);
                throw new \Exception('Token Error: '.$response->body());
            }

            // Log Kejayaan Token
            $this->logMyDigitalId('TOKEN_EXCHANGE_SUCCESS', 'SUCCESS', $this->redactPayload($response->json() ?? []), $transactionId);

            return json_decode($response->body());
        } catch (\Exception $e) {
            // LOG ERROR
            $this->logMyDigitalId('TOKEN_EXCHANGE_FAIL', 'FAILED', ['error' => $e->getMessage()], $transactionId);

            return null;
        }
    }

    // Fungsi Helper: Get User Info
    private function getUserInfoFromKeycloak($accessToken, $transactionId)
    {
        $config = config('mydigitalid');
        $url = $config['base_url'].'/realms/'.$config['realm'].'/protocol/openid-connect/userinfo';

        try {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->failed()) {
                $this->logMyDigitalId('USERINFO_FAIL', 'FAILED', ['error' => 'Keycloak API Error', 'status' => $response->status()], $transactionId, null, $url);

                return null;
            }

            // Log User info
            $this->logMyDigitalId('USERINFO', 'SUCCESS', $this->redactPayload($response->json() ?? []), $transactionId, null, $url);

            return json_decode($response->body());
        } catch (\Exception $e) {
            $this->logMyDigitalId('USERINFO_FAIL', 'FAILED', ['error' => $e->getMessage()], $transactionId, null, $url);

            return null;
        }
    }

    // Fungsi Helper: Logout (Browser Redirect)
    public function logout(Request $request)
    {
        $config = config('mydigitalid');
        $user = Auth::user();

        // Log Logout
        $this->logMyDigitalId('LOGOUT', 'SUCCESS', null, null, Auth::id());

        // Revoke all tokens issued to the user
        if ($user) {
            $user->tokens()->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Format Logout OIDC yang sebenar adalah redirect Browser, bukan Guzzle POST
        // Kita hantar user ke page logout MyDigitalID, kemudian dia akan redirect balik ke Login page kita
        $logoutUrl = $config['base_url'].'/realms/'.$config['realm'].'/protocol/openid-connect/logout?'.http_build_query([
            'post_logout_redirect_uri' => $config['post_logout_redirect_uri'] ?? route('login'),
            // 'id_token_hint' => ... (perlu simpan id_token dalam session jika nak logout automatik sepenuhnya)
        ]);

        return redirect($logoutUrl);
    }

    public function getAuth(Request $request, User $user)
    {
        // Jika User disediakan (dari SSO), gunakan langsung; jika tidak, attempt login
        if ($user && Auth::guard()->loginUsingId($user->id)) {
            // Log Kejayaan Login SSO
            $this->logMyDigitalId('LOGIN_SUCCESS', 'SUCCESS', ['method' => 'MyDigital ID'], null, $user->id, url(config('mydigitalid.home_route', 'home')));

            return redirect()->route('mydigitalid.home');
        }

        // Log Kegagalan Login SSO
        $this->logMyDigitalId('LOGIN_FAILED', 'FAILED', ['error' => 'Gagal mendaftar masuk ke dalam sistem.', 'method' => 'MyDigital ID']);

        return redirect('/login')->withErrors(['error' => 'Gagal mendaftar masuk ke dalam sistem.']);
    }

    /**
     * Helper: Semak status pengguna jika 'status_field' ditetapkan
     */
    private function isActive(User $user): bool
    {
        $statusField = config('mydigitalid.status_field');

        return ! $statusField || $user->{$statusField} == config('mydigitalid.active_value');
    }

    /**
     * Helper: Redact field sensitif (token & NRIC) sebelum disimpan ke log
     */
    private function redactPayload(array $payload): array
    {
        $sensitiveKeys = ['access_token', 'refresh_token', 'id_token', 'nric', 'preferred_username'];

        foreach ($sensitiveKeys as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '***REDACTED***';
            }
        }

        return $payload;
    }

    /**
     * Helper: Log MyDigital ID Transaction ke log channel khusus (bukan DB)
     *
     * @param  string  $action  - Tindakan (e.g., 'AUTH_REDIRECT', 'LOGIN_SUCCESS')
     * @param  string  $status  - Status (e.g., 'SUCCESS', 'FAILED', 'PENDING')
     * @param  array|string|null  $payload  - Data tambahan untuk log
     * @param  string|null  $transactionId  - ID transaksi (optional)
     * @param  int|null  $userId  - ID pengguna (optional)
     * @param  string|null  $url  - Custom URL (optional, default request()->url())
     */
    private function logMyDigitalId($action, $status, $payload = null, $transactionId = null, $userId = null, $url = null)
    {
        Log::channel(config('mydigitalid.log_channel', 'mydigitalid'))->info($action, [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'status' => $status,
            'url' => $url ?? url('login'),
            'ip_address' => request()->ip(),
            'payload' => $payload,
        ]);
    }
}
