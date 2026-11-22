<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class Controller
{
    protected function getRole(): string
    {
        return JWTAuth::parseToken()->getPayload()->get('role') ?? '';
    }

    protected function requireRole(string $role): ?JsonResponse
    {
        if ($this->getRole() !== $role) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return null;
    }

    protected function paginatedResponse(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, string $key = 'data'): JsonResponse
    {
        return response()->json([
            'success' => true,
            $key => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    protected function generateMemberHmac(int $memberId): string
    {
        return hash_hmac('sha256', "PKPMS:MEMBER:{$memberId}", config('app.key'));
    }

    protected function buildMemberHmacToken(int $memberId): string
    {
        $hmac = $this->generateMemberHmac($memberId);

        return "PKPMS:MEMBER:{$memberId}:{$hmac}";
    }

    protected function resolveMemberFromQrToken(string $token): array
    {
        if (str_starts_with($token, 'PKPMS:MEMBER:')) {
            return $this->resolveMemberFromHmacToken($token);
        }

        return $this->resolveMemberFromJwtToken($token);
    }

    private function resolveMemberFromHmacToken(string $token): array
    {
        $parts = explode(':', $token, 4);

        if (count($parts) !== 4 || $parts[0] !== 'PKPMS' || $parts[1] !== 'MEMBER') {
            return ['member' => null, 'error' => 'Invalid QR code format.'];
        }

        $memberId = (int) $parts[2];
        $signature = $parts[3];
        $expected = $this->generateMemberHmac($memberId);

        if (! hash_equals($expected, $signature)) {
            return ['member' => null, 'error' => 'QR code signature is invalid.'];
        }

        $member = \App\Models\Member::find($memberId);

        if (! $member) {
            return ['member' => null, 'error' => 'Member not found.'];
        }

        return ['member' => $member, 'error' => null];
    }

    private function resolveMemberFromJwtToken(string $token): array
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (\Exception) {
            return ['member' => null, 'error' => 'QR code has expired or is invalid. Please refresh.'];
        }

        if ($payload->get('type') !== 'member_qr') {
            return ['member' => null, 'error' => 'Invalid QR code type.'];
        }

        $member = \App\Models\Member::find($payload->get('sub'));

        if (! $member) {
            return ['member' => null, 'error' => 'Member not found.'];
        }

        return ['member' => $member, 'error' => null];
    }
}
