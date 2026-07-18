<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected WhatsAppGatewayInterface $gateway
    ) {}

    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Login and get an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke previous tokens (optional: single-session)
        // $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Request a password reset OTP, delivered via WhatsApp to the account's registered number.
     * Always returns a generic success message regardless of whether the email matched, so the
     * endpoint doesn't leak which emails have an account.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $generic = ['message' => 'Jika email terdaftar, kode OTP telah dikirim ke nomor WhatsApp akun tersebut.'];

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->phone) {
            // No phone on file means there's nowhere to deliver an OTP — fail silently to the
            // caller (same generic message) but log it, since this is otherwise a dead end for
            // a real user who forgot their password.
            if ($user && ! $user->phone) {
                Log::warning('AuthController::forgotPassword: user has no phone on file, cannot deliver OTP', ['user_id' => $user->id]);
            }
            return response()->json($generic);
        }

        $otp = (string) random_int(100000, 999999);

        PasswordResetOtp::updateOrCreate(
            ['email' => $user->email],
            ['otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(10)]
        );

        $this->gateway->sendMessage($user->phone, "Kode reset password Wassis kamu: {$otp}\n\nBerlaku 10 menit. Jangan bagikan kode ini ke siapa pun.");

        return response()->json($generic);
    }

    /**
     * Complete a password reset using the OTP sent via WhatsApp. Revokes every active token —
     * unlike changePassword(), there's no "current session" to preserve here, since this flow
     * exists precisely for when the owner is logged out and locked out.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'        => 'required|email',
            'otp'          => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $record = PasswordResetOtp::where('email', $validated['email'])->first();

        if (! $record || $record->expires_at->isPast() || ! Hash::check($validated['otp'], $record->otp_hash)) {
            throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah atau sudah kedaluwarsa.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update(['password' => Hash::make($validated['new_password'])]);
        $user->tokens()->delete();
        $record->delete();

        return response()->json(['message' => 'Password berhasil direset. Silakan login dengan password baru.']);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Change the authenticated user's password (requires the current password).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Revoke every other token so a stolen/old session can't keep using the old password's
        // trust — only the token used for this request survives.
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }

    /**
     * Update FCM token for push notifications.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'message' => 'FCM token updated.',
        ]);
    }
}
