<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiRefreshToken;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\User;
use App\Support\MemberContactValidation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    private const DEFAULT_REFRESH_DAYS = 7;

    private const REMEMBER_ME_REFRESH_DAYS = 30;

    public function register(Request $request): JsonResponse
    {
        $baseRules = [
            'role' => 'required|in:member,merchant',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'remember_me' => 'nullable|boolean',
        ];

        $validator = Validator::make($request->all(), $baseRules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower($request->email);

        if ($this->emailExistsAnywhere($email)) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered.',
                'errors' => ['email' => ['This email is already registered.']],
            ], 422);
        }

        if ($request->role === 'member') {
            return $this->registerMember($request, $email);
        }

        return $this->registerMerchant($request, $email);
    }

    private function registerMember(Request $request, string $email): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:25',
            'ic_number' => ['nullable', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'occupation' => 'nullable|string|max:255',
            'ic_front_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'ic_back_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request): void {
            $msg = MemberContactValidation::phoneError($request->input('phone'));
            if ($msg !== null) {
                $v->errors()->add('phone', $msg);
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $memberData = [
            'full_name' => $request->full_name,
            'display_name' => $request->filled('display_name')
                ? $request->string('display_name')->toString()
                : $request->full_name,
            'email' => $email,
            'phone' => $request->phone,
            'ic_number' => $request->ic_number,
            'occupation' => $request->occupation,
            'password' => $request->password,
            'membership_id' => Member::generateMembershipId(),
            'status' => 'pending_subscription',
            'subscription_state' => 'pending_subscription',
            'membership_status' => 'active',
            'total_points' => 0,
        ];

        if ($request->hasFile('ic_front_image')) {
            $memberData['ic_front_image'] = $request->file('ic_front_image')
                ->store('ic_images', 'public');
        }

        if ($request->hasFile('ic_back_image')) {
            $memberData['ic_back_image'] = $request->file('ic_back_image')
                ->store('ic_images', 'public');
        }

        $member = Member::create($memberData);

        [$token, $expiresIn] = $this->issueAccessToken('member_api', $member);
        [$refreshToken, $refreshExpiresIn] = $this->issueRefreshToken(
            $member,
            (bool) $request->boolean('remember_me')
        );

        return response()->json([
            'success' => true,
            'message' => 'Member registered successfully.',
            'data' => [
                'user' => $member,
                'role' => 'member',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $expiresIn,
                'refresh_token' => $refreshToken,
                'refresh_expires_in' => $refreshExpiresIn,
                'remember_me' => (bool) $request->boolean('remember_me'),
            ],
        ], 201);
    }

    private function registerMerchant(Request $request, string $email): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:25',
            'registration_number' => 'required|string|max:100',
            'ssm_document' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request): void {
            $msg = MemberContactValidation::phoneError($request->input('phone'));
            if ($msg !== null) {
                $v->errors()->add('phone', $msg);
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchantData = [
            'company_name' => $request->company_name,
            'company_email' => $email,
            'phone' => $request->phone,
            'registration_number' => $request->registration_number,
            'street_address' => $request->street_address,
            'city' => $request->city,
            'postcode' => $request->postcode,
            'country' => $request->country,
            'password' => $request->password,
            'status' => 'pending',
            'subscription_state' => 'pending_subscription',
        ];

        if ($request->hasFile('ssm_document')) {
            $merchantData['ssm_document'] = $request->file('ssm_document')
                ->store('ssm_documents', 'public');
        }

        $merchant = Merchant::create($merchantData);

        [$token, $expiresIn] = $this->issueAccessToken('merchant_api', $merchant);
        [$refreshToken, $refreshExpiresIn] = $this->issueRefreshToken(
            $merchant,
            (bool) $request->boolean('remember_me')
        );

        return response()->json([
            'success' => true,
            'message' => 'Merchant registered successfully. Awaiting admin approval.',
            'data' => [
                'user' => $merchant,
                'role' => 'merchant',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $expiresIn,
                'refresh_token' => $refreshToken,
                'refresh_expires_in' => $refreshExpiresIn,
                'remember_me' => (bool) $request->boolean('remember_me'),
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember_me' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower($request->email);

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This account cannot be used with the mobile app.',
            ], 403);
        }

        $rememberMe = (bool) $request->boolean('remember_me');

        $member = Member::where('email', $email)->first();
        if ($member) {
            $memberHash = $member->password;
            if (! is_string($memberHash) || $memberHash === '' || ! Hash::check($request->password, $memberHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            if ($member->status === 'deactivated') {
                return response()->json([
                    'success' => false,
                    'message' => 'This account has been deactivated. Please contact support to restore it.',
                    'status' => $member->status,
                ], 403);
            }

            // Sync subscription_state from live subscriptions table before
            // issuing the token so the Flutter app gets the correct state immediately.
            $member->refreshSubscriptionState();
            $member->ensureMembershipId();
            $member->refresh();

            [$token, $expiresIn] = $this->issueAccessToken('member_api', $member);
            [$refreshToken, $refreshExpiresIn] = $this->issueRefreshToken($member, $rememberMe);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $member,
                    'role' => 'member',
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $expiresIn,
                    'refresh_token' => $refreshToken,
                    'refresh_expires_in' => $refreshExpiresIn,
                    'remember_me' => $rememberMe,
                    'has_active_subscription' => $member->hasActiveSubscription(),
                ],
            ]);
        }

        $merchant = Merchant::where('company_email', $email)->first();
        if ($merchant) {
            $merchantHash = $merchant->password;
            if (! is_string($merchantHash) || $merchantHash === '' || ! Hash::check($request->password, $merchantHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // Sync subscription_state from live subscriptions table before
            // issuing the token so the Flutter app gets the correct state immediately.
            $merchant->refreshSubscriptionState();

            if ($merchant->status === 'suspended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your merchant account has been suspended. Please contact support.',
                    'status' => $merchant->status,
                ], 403);
            }

            if ($merchant->status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your merchant application has been rejected. Please contact support for more information.',
                    'status' => $merchant->status,
                ], 403);
            }

            if ($merchant->status === 'deactivated') {
                return response()->json([
                    'success' => false,
                    'message' => 'This account has been deactivated. Please contact support to restore it.',
                    'status' => $merchant->status,
                ], 403);
            }

            [$token, $expiresIn] = $this->issueAccessToken('merchant_api', $merchant);
            [$refreshToken, $refreshExpiresIn] = $this->issueRefreshToken($merchant, $rememberMe);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $merchant,
                    'role' => 'merchant',
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $expiresIn,
                    'refresh_token' => $refreshToken,
                    'refresh_expires_in' => $refreshExpiresIn,
                    'remember_me' => $rememberMe,
                    'has_active_subscription' => $merchant->hasActiveSubscription(),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials.',
        ], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        if (is_string($refreshToken) && $refreshToken !== '' && $request->user()) {
            $tokenHash = hash('sha256', $refreshToken);

            ApiRefreshToken::query()
                ->where('token_hash', $tokenHash)
                ->where('authenticatable_type', get_class($request->user()))
                ->where('authenticatable_id', $request->user()->getAuthIdentifier())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        $this->currentJwtGuard()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $user->status = 'deactivated';
        $user->save();

        // Revoke all of this user's refresh tokens (not just the one in the request)
        // since deactivation is more severe than a normal logout.
        ApiRefreshToken::query()
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->currentJwtGuard()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated.',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $providedToken = (string) $request->input('refresh_token');
        $tokenHash = hash('sha256', $providedToken);

        /** @var ApiRefreshToken|null $storedToken */
        $storedToken = ApiRefreshToken::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $storedToken || $storedToken->revoked_at || $storedToken->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        /** @var Authenticatable|null $user */
        $user = $storedToken->authenticatable;

        if (! $user || (! $user instanceof Member && ! $user instanceof Merchant)) {
            $storedToken->update(['revoked_at' => now()]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        [$guard, $role] = $this->resolveGuardAndRole($user);
        [$accessToken, $accessExpiresIn] = $this->issueAccessToken($guard, $user);

        $storedToken->update([
            'last_used_at' => now(),
            'revoked_at' => now(),
        ]);

        [$newRefreshToken, $refreshExpiresIn] = $this->issueRefreshToken($user, (bool) $storedToken->remember_me);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed.',
            'data' => [
                'role' => $role,
                'token' => $accessToken,
                'token_type' => 'bearer',
                'expires_in' => $accessExpiresIn,
                'refresh_token' => $newRefreshToken,
                'refresh_expires_in' => $refreshExpiresIn,
                'remember_me' => (bool) $storedToken->remember_me,
            ],
        ]);
    }

    private function emailExistsAnywhere(string $email): bool
    {
        return Member::where('email', $email)->exists()
            || Merchant::where('company_email', $email)->exists()
            || User::where('email', $email)->exists();
    }

    /**
     * @return array{0:string,1:int}
     */
    private function issueAccessToken(string $guard, Authenticatable&JWTSubject $user): array
    {
        $jwtGuard = $this->jwtGuard($guard);
        $token = $jwtGuard->login($user);
        $expiresIn = $jwtGuard->factory()->getTTL() * 60;

        return [$token, $expiresIn];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function issueRefreshToken(Authenticatable $user, bool $rememberMe): array
    {
        $expiresAt = now()->addDays($rememberMe ? self::REMEMBER_ME_REFRESH_DAYS : self::DEFAULT_REFRESH_DAYS);
        $plainToken = Str::random(80);

        ApiRefreshToken::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->getAuthIdentifier(),
            'token_hash' => hash('sha256', $plainToken),
            'remember_me' => $rememberMe,
            'expires_at' => $expiresAt,
        ]);

        return [$plainToken, now()->diffInSeconds($expiresAt, false)];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveGuardAndRole(Authenticatable $user): array
    {
        if ($user instanceof Member) {
            return ['member_api', 'member'];
        }

        if ($user instanceof Merchant) {
            return ['merchant_api', 'merchant'];
        }

        abort(401, 'Unauthorized account type.');
    }

    private function currentJwtGuard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth()->guard();

        return $guard;
    }

    private function jwtGuard(string $guard): JWTGuard
    {
        /** @var JWTGuard $jwtGuard */
        $jwtGuard = auth($guard);

        return $jwtGuard;
    }
}
