<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MerchantGallery;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role === 'member') {
            /** @var Member $user */
            $user->ensureMembershipId();
            $user->refresh();
            $user->load('activeSubscription.plan');

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'member',
                    'profile' => $user,
                    'summary' => [
                        'total_points' => (int) $user->total_points,
                        'total_redemptions' => (int) Redemption::where('member_id', $user->id)->count(),
                        'joined_months' => $this->calculateJoinedMonths($user->created_at),
                    ],
                ],
            ]);
        }

        $user->load(['activeSubscription.plan', 'galleries', 'benefits' => fn ($q) => $q->orderBy('display_order')]);
        $merchantTotalPoints = (int) PointTransaction::where('merchant_id', $user->id)
            ->where('type', 'allocation')
            ->sum('points');
        $merchantTotalRedemptions = (int) PointTransaction::where('merchant_id', $user->id)
            ->where('type', 'redemption')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => 'merchant',
                'profile' => $user,
                'summary' => [
                    'total_points' => $merchantTotalPoints,
                    'total_redemptions' => $merchantTotalRedemptions,
                    'joined_months' => $this->calculateJoinedMonths($user->created_at),
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role === 'member') {
            return $this->updateMember($request, $user);
        }

        return $this->updateMerchant($request, $user);
    }

    private function updateMember(Request $request, Member $member): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'occupation' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        if (array_key_exists('display_name', $payload) && $payload['display_name'] === '') {
            $payload['display_name'] = null;
        }

        $member->update($payload);
        $member->load('activeSubscription.plan');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'role' => 'member',
                'profile' => $member,
            ],
        ]);
    }

    private function updateMerchant(Request $request, Merchant $merchant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registration_number' => 'prohibited',
            'company_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:30',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'short_description' => 'nullable|string|max:1000',
            'about_description' => 'nullable|string|max:5000',
            'about_description_ms' => 'nullable|string|max:5000',
            'about_description_zh' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant->update($validator->validated());
        $merchant->load(['activeSubscription.plan', 'galleries', 'benefits' => fn ($q) => $q->orderBy('display_order')]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'role' => 'merchant',
                'profile' => $merchant,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        if (! \Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        $user->update([
            'password' => $request->password,
            // If a temporary password was issued, hide it immediately after the user
            // successfully changes their password.
            'temp_password_issued_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    public function updateMerchantCoverImage(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role !== 'merchant' || ! $user instanceof Merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'cover_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldPath = $user->getRawOriginal('cover_image');
        $newPath = $request->file('cover_image')->store('merchant/cover-images', 'public');

        $user->update([
            'cover_image' => $newPath,
        ]);

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->load(['activeSubscription.plan', 'galleries', 'benefits' => fn ($q) => $q->orderBy('display_order')]);

        return response()->json([
            'success' => true,
            'message' => 'Cover image updated successfully.',
            'data' => [
                'role' => 'merchant',
                'profile' => $user,
            ],
        ]);
    }

    public function uploadMerchantGalleryImage(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role !== 'merchant' || ! $user instanceof Merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm,m4v|max:20480',
            'images' => 'nullable|array|min:1',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm,m4v|max:20480',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm,m4v|max:20480',
            'videos' => 'nullable|array|min:1',
            'videos.*' => 'file|mimes:mp4,mov,avi,webm,m4v|max:20480',
            'caption' => 'nullable|string|max:255',
            'captions' => 'nullable|array',
            'captions.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $files = [];
        if ($request->hasFile('images')) {
            $files = array_merge($files, $request->file('images'));
        }
        if ($request->hasFile('image')) {
            $files[] = $request->file('image');
        }
        if ($request->hasFile('videos')) {
            $files = array_merge($files, $request->file('videos'));
        }
        if ($request->hasFile('video')) {
            $files[] = $request->file('video');
        }

        if (count($files) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'media' => ['Please upload at least one image or video file.'],
                ],
            ], 422);
        }

        $captions = $request->input('captions', []);
        $singleCaption = $request->input('caption');
        $createdItems = [];

        foreach ($files as $index => $file) {
            $imagePath = $file->store('merchant/gallery-media', 'public');
            $caption = $captions[$index] ?? ($index === 0 ? $singleCaption : null);
            $mimeType = (string) ($file->getMimeType() ?? '');
            $mediaType = str_starts_with($mimeType, 'video/') ? 'video' : 'image';

            $createdItems[] = MerchantGallery::create([
                'merchant_id' => $user->id,
                'image_path' => $imagePath,
                'media_type' => $mediaType,
                'caption' => $caption,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($createdItems) === 1
                ? 'Gallery media uploaded successfully.'
                : 'Gallery media files uploaded successfully.',
            'data' => [
                'uploaded_count' => count($createdItems),
                'gallery_items' => $createdItems,
            ],
        ], count($createdItems) === 1 ? 201 : 200);
    }

    public function uploadMemberKyc(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role !== 'member' || ! $user instanceof Member) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ic_number' => 'nullable|string|max:20',
            'ic_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'ic_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [];

        if ($request->filled('ic_number')) {
            $payload['ic_number'] = $request->input('ic_number');
        }

        if ($request->hasFile('ic_front')) {
            $oldPath = $user->getRawOriginal('ic_front_image');
            $payload['ic_front_image'] = $request->file('ic_front')->store('member/kyc', 'public');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        if ($request->hasFile('ic_back')) {
            $oldPath = $user->getRawOriginal('ic_back_image');
            $payload['ic_back_image'] = $request->file('ic_back')->store('member/kyc', 'public');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $user->update($payload);
        $user->load('activeSubscription.plan');

        return response()->json([
            'success' => true,
            'message' => 'KYC information updated successfully.',
            'data' => [
                'role' => 'member',
                'profile' => $user,
            ],
        ]);
    }

    public function uploadMerchantSsm(Request $request): JsonResponse
    {
        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        if ($role !== 'merchant' || ! $user instanceof Merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ssm_document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldPath = $user->getRawOriginal('ssm_document');
        $newPath = $request->file('ssm_document')->store('merchant/ssm', 'public');

        $user->update(['ssm_document' => $newPath]);

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->load(['activeSubscription.plan', 'galleries', 'benefits' => fn ($q) => $q->orderBy('display_order')]);

        return response()->json([
            'success' => true,
            'message' => 'SSM document uploaded successfully.',
            'data' => [
                'role' => 'merchant',
                'profile' => $user,
            ],
        ]);
    }

    private function calculateJoinedMonths($createdAt): int
    {
        if (! $createdAt) {
            return 0;
        }

        return max(1, $createdAt->diffInMonths(now()) + 1);
    }
}
