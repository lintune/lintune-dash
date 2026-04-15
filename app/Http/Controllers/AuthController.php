<?php

namespace App\Http\Controllers;

use App\Models\DomainRealmMap;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('access_token')) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function lookupRealm(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $domain = Str::after($request->email, '@');
        $map = DomainRealmMap::where('domain', $domain)->first();

        if (!$map) {
            return redirect()->route('login.contact');
        }

        // Build PKCE challenge
        $verifier = $this->generateVerifier();
        $challenge = $this->generateChallenge($verifier);

        session([
            'pkce_verifier' => $verifier,
            'pkce_realm'    => $map->realm,
            'login_hint'    => $request->email,
        ]);

        $params = http_build_query([
            'client_id'             => config('keycloak.client_id'),
            'redirect_uri'          => route('auth.callback'),
            'response_type'         => 'code',
            'scope'                 => 'openid profile email',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'login_hint'            => $request->email,
        ]);

        $base = config('keycloak.base_url');
        $realm = $map->realm;

        return redirect("{$base}/realms/{$realm}/protocol/openid-connect/auth?{$params}");
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('login')->withErrors(['auth' => 'Authentication failed. Please try again.']);
        }

        $realm    = session('pkce_realm');
        $verifier = session('pkce_verifier');
        $base     = config('keycloak.base_url');

        $response = \Http::asForm()->post("{$base}/realms/{$realm}/protocol/openid-connect/token", [
            'grant_type'    => 'authorization_code',
            'client_id'     => config('keycloak.client_id'),
            'redirect_uri'  => route('auth.callback'),
            'code'          => $request->code,
            'code_verifier' => $verifier,
        ]);

        if ($response->failed()) {
            return redirect()->route('login')->withErrors(['auth' => 'Token exchange failed.']);
        }

        $tokens  = $response->json();
        $payload = $this->parseJwt($tokens['access_token']);
        $groups  = $payload['groups'] ?? [];

        $allowed = config('keycloak.allowed_groups');
        if (empty(array_intersect($groups, $allowed))) {
            return redirect()->route('login.contact');
        }

        session([
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'id_token'      => $tokens['id_token'],
            'user_name'     => $payload['name'] ?? $payload['preferred_username'] ?? '',
            'user_email'    => $payload['email'] ?? '',
            'realm'         => $realm,
        ]);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        $realm   = session('realm');
        $idToken = session('id_token');
        $base    = config('keycloak.base_url');

        session()->flush();

        $params = http_build_query([
            'post_logout_redirect_uri' => route('login'),
            'id_token_hint'            => $idToken,
        ]);

        return redirect("{$base}/realms/{$realm}/protocol/openid-connect/logout?{$params}");
    }

    private function generateVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function parseJwt(string $token): array
    {
        $parts = explode('.', $token);
        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
    }
}
