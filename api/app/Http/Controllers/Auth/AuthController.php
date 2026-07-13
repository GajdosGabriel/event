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

        return $this->authenticateSocialUser(
            provider: 'google',
            email: $email,
            providerId: $providerId,
            displayName: trim((string) ($payload['name'] ?? '')),
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
                'fields' => 'id,name,email',
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

        return $this->authenticateSocialUser(
            provider: 'facebook',
            email: $email,
            providerId: $providerId,
            displayName: $displayName,
        );
    }

    protected function authenticateSocialUser(string $provider, string $email, string $providerId, string $displayName = '')
    {
        $normalizedProviderId = $provider . ':' . $providerId;

        // Social auth is already identity-verified, so local pending records are ignored.
        PendingRegistration::where('email', $email)->delete();

        $user = User::where('provider_id', $normalizedProviderId)
            ->orWhere('provider_id', $providerId)
            ->orWhere('email', $email)
            ->first();

        $created = false;
        if (! $user) {
            $user = User::create([
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'registered_via' => $provider,
                'provider_id' => $normalizedProviderId,
            ]);
            $created = true;
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

        if ($displayName !== '' && ! $user->pendingProfile()->exists()) {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => $displayName,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
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

        $user = User::create([
            'email' => $pending->email,
            'password' => $pending->password,
            'registered_via' => $pending->registered_via,
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
