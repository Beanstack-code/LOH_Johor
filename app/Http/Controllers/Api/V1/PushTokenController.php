<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $role = $this->getRole();
        if (! in_array($role, ['member', 'merchant'], true)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:2048',
            'platform' => 'required|in:android,ios',
            'app_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $rawToken = (string) $request->input('fcm_token');

        $token = PushDeviceToken::query()->updateOrCreate(
            ['fcm_token_hash' => hash('sha256', $rawToken)],
            [
                'fcm_token' => $rawToken,
                'authenticatable_type' => get_class($user),
                'authenticatable_id' => $user->getAuthIdentifier(),
                'platform' => (string) $request->input('platform'),
                'app_version' => $request->input('app_version'),
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered successfully.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'is_active' => (bool) $token->is_active,
                'last_seen_at' => $token->last_seen_at,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $role = $this->getRole();
        if (! in_array($role, ['member', 'merchant'], true)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $fcmTokenHash = hash('sha256', (string) $request->input('fcm_token'));

        PushDeviceToken::query()
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->getAuthIdentifier())
            ->where('fcm_token_hash', hash('sha256', (string) $request->input('fcm_token')))
            ->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Push token removed successfully.',
        ]);
    }
}

