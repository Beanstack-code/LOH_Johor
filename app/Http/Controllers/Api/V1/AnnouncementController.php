<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('is_highlighted')
            ->orderByDesc('starts_at')
            ->paginate($request->get('per_page', 20));

        $announcements->setCollection(
            $announcements->getCollection()->map(fn (Announcement $a) => $this->formatList($a))
        );

        return $this->paginatedResponse($announcements);
    }

    public function show(int $id): JsonResponse
    {
        $announcement = Announcement::where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->find($id);

        if (! $announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $this->formatDetail($announcement),
        ]);
    }

    private function formatList(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => ApiLocale::resolve($a->title_i18n, (string) $a->title),
            'category' => $a->category,
            'cover_image_url' => $a->cover_image_url,
            'starts_at' => $a->starts_at?->toIso8601String(),
            'ends_at' => $a->ends_at?->toIso8601String(),
            'is_highlighted' => (bool) $a->is_highlighted,
            'title_i18n' => $a->title_i18n,
        ];
    }

    private function formatDetail(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => ApiLocale::resolve($a->title_i18n, (string) $a->title),
            'category' => $a->category,
            'content' => ApiLocale::resolve($a->content_i18n, (string) $a->content),
            'terms_and_conditions' => ApiLocale::resolve($a->terms_and_conditions_i18n, (string) $a->terms_and_conditions),
            'cover_image_url' => $a->cover_image_url,
            'starts_at' => $a->starts_at?->toIso8601String(),
            'ends_at' => $a->ends_at?->toIso8601String(),
            'is_highlighted' => (bool) $a->is_highlighted,
            'title_i18n' => $a->title_i18n,
            'content_i18n' => $a->content_i18n,
            'terms_and_conditions_i18n' => $a->terms_and_conditions_i18n,
        ];
    }
}
