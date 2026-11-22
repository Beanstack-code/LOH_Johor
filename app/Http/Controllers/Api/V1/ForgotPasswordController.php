<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);

        // Mobile app forgot password only supports member/merchant accounts.
        // If this is a portal account email, do not send any reset email.
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This account cannot be used with the mobile app.',
            ], 403);
        }

        $isMember   = Member::where('email', $email)->exists();
        $isMerchant = !$isMember && Merchant::where('company_email', $email)->exists();

        if (!$isMember && !$isMerchant) {
            return response()->json([
                'success' => false,
                'message' => 'No account is registered with this email address.',
            ], 404);
        }

        if ($isMember) {
            Password::broker('members')->sendResetLink(['email' => $email]);
        } else {
            Password::broker('merchants')->sendResetLink(['company_email' => $email]);
        }

        return response()->json([
            'success' => true,
            'message' => 'A password reset link has been sent to your email.',
        ]);
    }
}
