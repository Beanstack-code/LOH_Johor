<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformBenefit;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;

class PlatformBenefitController extends Controller
{
    public function index(): JsonResponse
    {
        $role = $this->getRole();
        $audience = in_array($role, ['member', 'merchant'], true) ? $role : 'both';

        $benefits = PlatformBenefit::query()
            ->where('is_active', true)
            ->whereIn('audience', ['both', $audience])
            ->orderByDesc('id')
            ->get(['id', 'title', 'title_ms', 'title_zh', 'content', 'content_ms', 'content_zh', 'audience'])
            ->map(fn (PlatformBenefit $b) => [
                'id' => $b->id,
                'title' => ApiLocale::resolve($b->title_i18n, (string) $b->title),
                'content' => ApiLocale::resolve($b->content_i18n, (string) $b->content),
                'audience' => $b->audience,
                'title_i18n' => $b->title_i18n,
                'content_i18n' => $b->content_i18n,
            ]);

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $benefits,
        ]);
    }
}
