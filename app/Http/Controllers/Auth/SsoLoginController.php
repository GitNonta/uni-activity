<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * (8) ล็อกอิน SSO OAuth2/OIDC — ทำงานกับโปรโตคอลมาตรฐาน
 * อ่านค่า discovery document (.well-known/openid-configuration) ก่อน
 * ถ้าไม่ได้เปิดใช้ (ซอร์สเซิฟเวอร์มาช่วย) จะไม่เปิดเส้นทาง
 * -- config/services.php 'sso'
 */
class SsoLoginController extends Controller
{
    private function cfg(): array
    {
        $cfg = config('services.sso');
        if (! ($cfg['enabled'] ?? false)) {
            abort(404);
        }
        $discovery = [];
        if (! empty($cfg['discovery_url'])) {
            try {
                $discovery = Http::timeout(8)->get($cfg['discovery_url'])->throw()->json() ?: [];
            } catch (\Throwable $e) {
                Log::warning('SSO discovery failed', ['error' => $e->getMessage()]);
            }
        }

        return [$cfg, $discovery];
    }

    public function redirect()
    {
        [$cfg, $discovery] = $this->cfg();
        $authUrl = $discovery['authorization_endpoint'] ?? null;
        if (! $authUrl || empty($cfg['client_id']) || empty($cfg['redirect_uri'])) {
            return redirect()->route('login')->withErrors(['sso' => 'SSO ยังไม่พร้อม (ตรวจ SSO_ISSUER / discovery)']);
        }

        return redirect($authUrl.'?'.http_build_query([
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope'         => $cfg['scopes'],
            'state'         => session()->token(),
        ]));
    }

    public function callback(Request $request)
    {
        [$cfg, $discovery] = $this->cfg();
        if ($request->input('state') !== session()->token()) {
            return redirect()->route('login')->withErrors(['sso' => 'SSO state ไม่ถูกต้อง']);
        }
        if ($request->has('error') || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors(['sso' => 'SSO callback ไม่สมบูรณ์']);
        }

        $tokenUrl     = $discovery['token_endpoint']    ?? null;
        $userinfoUrl  = $discovery['userinfo_endpoint'] ?? null;
        if (! $tokenUrl) {
            return redirect()->route('login')->withErrors(['sso' => 'SSO token endpoint ไม่พบ']);
        }

        try {
            $token = Http::asForm()->timeout(12)->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'code'          => $request->input('code'),
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri'  => $cfg['redirect_uri'],
            ])->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('SSO token exchange failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors(['sso' => 'SSO ล้มเหลว']);
        }

        $profile = [];
        if ($userinfoUrl && ! empty($token['access_token'])) {
            $profile = Http::withToken($token['access_token'])->timeout(10)->get($userinfoUrl)->json() ?: [];
        }
        $email = $profile['email'] ?? null;
        $name  = $profile['name'] ?? ($email ? explode('@', $email)[0] : null);
        if (! $email) {
            return redirect()->route('login')->withErrors(['sso' => 'ไม่ได้รับ email จากผู้ให้บริการ']);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['full_name' => $name, 'username' => null, 'password' => bin2hex(random_bytes(16)), 'usertype' => 1]
        );
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
