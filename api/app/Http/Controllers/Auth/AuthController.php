<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\FacebookAuthRequest;
use App\Http\Requests\GoogleAuthRequest;
use App\Http\Requests\UserLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\PendingProfile;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\PendingRegistrationVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginForm()
    {
        return response()->json([
            'login Page' => 'Prihlasovanie je povolené'
        ]);
    }

    public function login(UserLoginRequest $request)
    {
        $email = $request->input('email');

        if (PendingRegistration::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Email not verified',
                'code' => 'email_not_verified',
            ], 409);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        $user = User::where('email', $email)->firstOrFail();

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function register(AuthRegisterRequest $request)
    {
        $registeredVia = $request->input('registered_via', 'local');
        $consent = $this->termsConsent();

        if ($registeredVia === 'local') {
            $rawToken = Str::random(64);
            $hashedToken = hash('sha256', $rawToken);

            $ttlHours = (int) config('registration.verification_ttl_hours', 48);

            PendingRegistration::create([
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'display_name' => $request->input('display_name'),
                'registered_via' => $registeredVia,
                'verification_token' => $hashedToken,
                'expires_at' => now()->addHours($ttlHours),
                ...$consent,
            ]);

            Notification::route('mail', $request->input('email'))
                ->notify(new PendingRegistrationVerification($rawToken, $ttlHours));

            return response()->json([
                'message' => 'Registration created. Please verify your email.',
            ], 201);
        }

        $password = $request->input('password') ?? Str::random(32);

        $user = User::create([
            'email' => $request->input('email'),
            'password' => Hash::make($password),
            'registered_via' => $registeredVia,
            ...$consent,
        ]);

        if ($request->filled('display_name')) {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => $request->input('display_name'),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->assignSuperAdminIfFirstUser($user);

        return response()->json($user, 201);
    }

    public function googleAuth(GoogleAuthRequest $request)
    {
        $googleClientId = (string) config('services.google.client_id');
        if ($googleClientId === '') {
            return response()->json([
                'message' => 'Google authentication is not configured',
            ], 500);
        }

        $idToken = $request->input('id_token');

        $response = Http::timeout(8)
            ->acceptJson()
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Invalid Google token',
            ], 401);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid Google token payload',
            ], 401);
        }

        $audience = (string) ($payload['aud'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $providerId = (string) ($payload['sub'] ?? '');
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($audience !== $googleClientId || $email === '' || $providerId === '' || ! $emailVerified) {
            return response()->json([
                'message' => 'Google token validation failed',
            ], 401);
        }

        // ID token z Google Identity Services nesie okrem e-mailu aj profilové
        // claimy (name, given_name, family_name, picture). `name` býva vyplnené
        // takmer vždy; keď chýba, poskladáme ho z krstného a priezviska.
        $displayName = trim((string) ($payload['name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim(
                trim((string) ($payload['given_name'] ?? '')) . ' ' . trim((string) ($payload['family_name'] ?? ''))
            );
        }

        return $this->authenticateSocialUser(
            provider: 'google',
            email: $email,
            providerId: $providerId,
            displayName: $displayName,
            avatarUrl: $this->googleAvatarUrl((string) ($payload['picture'] ?? '')),
            termsAccepted: $request->boolean('terms_accepted'),
        );
    }

    public function facebookAuth(FacebookAuthRequest $request)
    {
        $facebookAppId = (string) config('services.facebook.app_id');
        $facebookAppSecret = (string) config('services.facebook.app_secret');

        if ($facebookAppId === '' || $facebookAppSecret === '') {
            return response()->json([
                'message' => 'Facebook authentication is not configured',
            ], 500);
        }

        $accessToken = $request->input('access_token');
        $appAccessToken = $facebookAppId . '|' . $facebookAppSecret;

        $debugResponse = Http::timeout(8)
            ->acceptJson()
            ->get('https://graph.facebook.com/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $appAccessToken,
            ]);

        if (! $debugResponse->ok()) {
            return response()->json([
                'message' => 'Invalid Facebook token',
            ], 401);
        }

        $debugPayload = $debugResponse->json();
        if (! is_array($debugPayload) || ! is_array($debugPayload['data'] ?? null)) {
            return response()->json([
                'message' => 'Invalid Facebook token payload',
            ], 401);
        }

        $debugData = $debugPayload['data'];
        $isValid = (bool) ($debugData['is_valid'] ?? false);
        $tokenAppId = (string) ($debugData['app_id'] ?? '');

        if (! $isValid || $tokenAppId !== $facebookAppId) {
            return response()->json([
                'message' => 'Facebook token validation failed',
            ], 401);
        }

        $meResponse = Http::timeout(8)
            ->acceptJson()
            ->get('https://graph.facebook.com/me', [
                // `picture.width(512)` vráti aj profilovku — bez uvedenia
                // v `fields` ju Graph API do odpovede nedá vôbec.
                'fields' => 'id,name,email,picture.width(512)',
                'access_token' => $accessToken,
            ]);

        if (! $meResponse->ok()) {
            return response()->json([
                'message' => 'Unable to load Facebook profile',
            ], 401);
        }

        $mePayload = $meResponse->json();
        if (! is_array($mePayload)) {
            return response()->json([
                'message' => 'Invalid Facebook profile payload',
            ], 401);
        }

        $email = (string) ($mePayload['email'] ?? '');
        $providerId = (string) ($mePayload['id'] ?? '');
        $displayName = trim((string) ($mePayload['name'] ?? ''));

        if ($email === '' || $providerId === '') {
            return response()->json([
                'message' => 'Facebook account does not provide required email',
            ], 422);
        }

        // Graph API zabaľuje profilovku do picture.data.url; `is_silhouette`
        // označuje generický zástupný obrázok, ten preberať nemá zmysel.
        $picture = $mePayload['picture']['data'] ?? [];
        $avatarUrl = is_array($picture) && ! ($picture['is_silhouette'] ?? false)
            ? trim((string) ($picture['url'] ?? ''))
            : '';

        return $this->authenticateSocialUser(
            provider: 'facebook',
            email: $email,
            providerId: $providerId,
            displayName: $displayName,
            avatarUrl: $avatarUrl,
            termsAccepted: $request->boolean('terms_accepted'),
        );
    }

    /**
     * Google vracia avatar v rozmere, ktorý si vyžiadal front (`=s96-c`).
     * Pre hlavný obrázok kanála je to málo, tak si vypýtame väčšiu variantu;
     * keď URL tento tvar nemá, necháme ju tak, ako prišla.
     */
    protected function googleAvatarUrl(string $picture): string
    {
        $picture = trim($picture);

        if ($picture === '' || ! str_starts_with($picture, 'https://')) {
            return '';
        }

        return (string) preg_replace('/=s\d+(-c)?$/', '=s512$1', $picture);
    }

    protected function authenticateSocialUser(string $provider, string $email, string $providerId, string $displayName = '', string $avatarUrl = '', bool $termsAccepted = false)
    {
        $normalizedProviderId = $provider . ':' . $providerId;

        $user = User::where('provider_id', $normalizedProviderId)
            ->orWhere('provider_id', $providerId)
            ->orWhere('email', $email)
            ->first();

        // Prihlásenie cez Google/Facebook zakladá účet aj bez formulára, takže
        // súhlas s podmienkami tu treba vypýtať skôr, než účet vznikne. Pre už
        // existujúci účet sa nepýta — ten súhlas udelil pri registrácii.
        if (! $user && ! $termsAccepted) {
            return response()->json([
                'message' => 'You must agree to the terms and conditions.',
                'code' => 'terms_required',
            ], 422);
        }

        // Social auth is already identity-verified, so local pending records are ignored.
        PendingRegistration::where('email', $email)->delete();

        $created = false;
        if (! $user) {
            $user = User::create([
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'registered_via' => $provider,
                'provider_id' => $normalizedProviderId,
                ...$this->termsConsent(),
            ]);
            $created = true;
        }

        // Meno z Google/Facebooku musí byť v PendingProfile skôr, než sa doplní
        // email_verified_at — ten totiž cez UserObserver spustí založenie
        // osobného kanála a PersonalCanalProvisioner berie názov práve odtiaľto.
        // Neskôr už kanál existuje a meno by ostalo nevyužité (kanál by sa volal
        // podľa časti e-mailu pred zavináčom).
        if ($displayName !== '' && ! $user->canals()->exists() && ! $user->pendingProfile()->exists()) {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            ]);
        }

        if ($user->provider_id === null || $user->provider_id === '') {
            $user->provider_id = $normalizedProviderId;
        }

        if ($user->provider_id === $providerId) {
            $user->provider_id = $normalizedProviderId;
        }

        if ($user->registered_via === 'local') {
            $user->registered_via = $provider;
        }

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->last_login_at = now();
        $user->save();

        if ($created) {
            $this->assignSuperAdminIfFirstUser($user);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Rovnaký tvar odpovede ako pri login() — front (unwrapIdentity, auth
        // store) čaká UserResource, nie surový model. Bez Auth::login by
        // UserResource nevidel prihláseného používateľa (e-mail, permissions).
        Auth::login($user);

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'is_new_user' => $created,
        ]);
    }

    public function verifyRegistration(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        return $this->verifyRegistrationToken($validated['token']);
    }

    public function resendRegistrationVerification(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $validated['email'];

        $user = User::where('email', $email)->first();
        if ($user) {
            return response()->json([
                'message' => 'User already exists',
                'code' => $user->email_verified_at ? 'already_verified' : 'user_exists',
            ], 409);
        }

        $pending = PendingRegistration::where('email', $email)->first();
        if (! $pending) {
            return response()->json([
                'message' => 'Pending registration not found',
                'code' => 'pending_not_found',
            ], 404);
        }

        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        $ttlHours = (int) config('registration.verification_ttl_hours', 48);

        $pending->forceFill([
            'verification_token' => $hashedToken,
            'expires_at' => now()->addHours($ttlHours),
        ])->save();

        Notification::route('mail', $email)
            ->notify(new PendingRegistrationVerification($rawToken, $ttlHours));

        return response()->json([
            'message' => 'Verification email resent.',
        ], 200);
    }

    public function verifyRegistrationLink(string $token)
    {
        $response = $this->verifyRegistrationToken($token);

        $status = $response->getStatusCode();
        $message = match ($status) {
            200 => 'Email verified successfully.',
            404 => 'Invalid or expired token.',
            410 => 'Token expired.',
            409 => 'User already exists.',
            default => 'Verification failed.',
        };

        return response($message, $status);
    }

    protected function verifyRegistrationToken(string $token)
    {
        $hashedToken = hash('sha256', $token);

        $pending = PendingRegistration::where('verification_token', $hashedToken)->first();
        if (! $pending) {
            return response()->json(['message' => 'Invalid or expired token'], 404);
        }

        if ($pending->expires_at && now()->greaterThan($pending->expires_at)) {
            $pending->delete();
            return response()->json(['message' => 'Token expired'], 410);
        }

        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();
            return response()->json(['message' => 'User already exists'], 409);
        }

        // Súhlas sa neudeľuje znova pri overení e-mailu — prenášame ten, ktorý
        // človek dal pri odoslaní registračného formulára, aj s jeho dátumom.
        $user = User::create([
            'email' => $pending->email,
            'password' => $pending->password,
            'registered_via' => $pending->registered_via,
            'terms_accepted_at' => $pending->terms_accepted_at,
            'terms_version' => $pending->terms_version,
        ]);

        PendingProfile::create([
            'user_id' => $user->id,
            'display_name' => $pending->display_name,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->assignSuperAdminIfFirstUser($user);

        $pending->delete();

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user,
        ], 200);
    }

    /**
     * Stopa o udelenom súhlase s obchodnými podmienkami a o tom, že sa človek
     * oboznámil so zásadami ochrany osobných údajov.
     *
     * Bez dátumu a verzie dokumentov by sa po prvej zmene textov nedalo
     * preukázať, s čím konkrétne kto súhlasil (čl. 7 ods. 1 GDPR).
     *
     * @return array{terms_accepted_at: \Illuminate\Support\Carbon, terms_version: string}
     */
    protected function termsConsent(): array
    {
        return [
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('legal.version'),
        ];
    }

    /**
     * Prvý zaregistrovaný používateľ v systéme sa automaticky stáva super-adminom.
     * Všetci ďalší používatelia žiadnu rolu automaticky nedostávajú.
     */
    protected function assignSuperAdminIfFirstUser(User $user): void
    {
        if (User::count() === 1 && ! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
