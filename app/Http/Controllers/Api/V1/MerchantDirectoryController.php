<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Merchant::where('status', 'active')
            ->with(['benefits' => fn ($q) => $q->orderBy('display_order')])
            ->select([
                'id',
                'company_name',
                'company_name_ms',
                'company_name_zh',
                'company_email',
                'phone',
                'whatsapp_number',
                'categories',
                'cover_image',
                'short_description',
                'short_description_ms',
                'short_description_zh',
                'about_description',
                'about_description_ms',
                'about_description_zh',
                'city',
                'street_address',
                'postcode',
                'country',
                'is_featured',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('about_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereJsonContains('categories', $request->category);
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        $merchants = $query->orderBy('is_featured', 'desc')
            ->orderBy('company_name')
            ->paginate($request->get('per_page', 15));

        $merchants->setCollection(
            $merchants->getCollection()->map(fn (Merchant $m) => $this->formatList($m))
        );

        return $this->paginatedResponse($merchants, 'merchants');
    }

    public function show(int $id): JsonResponse
    {
        $merchant = Merchant::where('status', 'active')
            ->with([
                'galleries' => function ($query) {
                    $query->where('status', 'approved')
                        ->orWhereNull('status');
                },
                'benefits' => fn ($q) => $q->orderBy('display_order'),
            ])
            ->select([
                'id',
                'company_name',
                'company_name_ms',
                'company_name_zh',
                'company_email',
                'phone',
                'whatsapp_number',
                'categories',
                'registration_number',
                'business_address',
                'street_address',
                'city',
                'postcode',
                'country',
                'cover_image',
                'short_description',
                'short_description_ms',
                'short_description_zh',
                'about_description',
                'about_description_ms',
                'about_description_zh',
                'is_featured',
            ])
            ->find($id);

        if (! $merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $this->formatDetail($merchant),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = MerchantCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ms', 'name_zh'])
            ->map(fn (MerchantCategory $c) => [
                'id' => $c->id,
                'name' => ApiLocale::resolve($c->name_i18n, (string) $c->name),
                'name_i18n' => $c->name_i18n,
            ]);

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $categories,
        ]);
    }

    private function formatList(Merchant $m): array
    {
        return [
            'id' => $m->id,
            'company_name' => ApiLocale::resolve($m->company_name_i18n, (string) $m->company_name),
            'short_description' => ApiLocale::resolve($m->short_description_i18n, (string) $m->short_description),
            'about_description' => ApiLocale::resolve($m->about_description_i18n, (string) ($m->about_description ?? '')),
            'company_email' => $m->company_email,
            'phone' => $m->phone,
            'whatsapp_number' => $m->whatsapp_number,
            'categories' => $m->categories,
            'cover_image_url' => $m->cover_image_url,
            'city' => $m->city,
            'street_address' => $m->street_address,
            'postcode' => $m->postcode,
            'country' => $m->country,
            'is_featured' => (bool) $m->is_featured,
            'company_name_i18n' => $m->company_name_i18n,
            'short_description_i18n' => $m->short_description_i18n,
            'about_description_i18n' => $m->about_description_i18n,
            'benefits' => $m->benefits,
        ];
    }

    private function formatDetail(Merchant $m): array
    {
        return [
            'id' => $m->id,
            'company_name' => ApiLocale::resolve($m->company_name_i18n, (string) $m->company_name),
            'short_description' => ApiLocale::resolve($m->short_description_i18n, (string) $m->short_description),
            'about_description' => ApiLocale::resolve($m->about_description_i18n, (string) ($m->about_description ?? '')),
            'company_email' => $m->company_email,
            'phone' => $m->phone,
            'whatsapp_number' => $m->whatsapp_number,
            'categories' => $m->categories,
            'registration_number' => $m->registration_number,
            'business_address' => $m->business_address,
            'street_address' => $m->street_address,
            'city' => $m->city,
            'postcode' => $m->postcode,
            'country' => $m->country,
            'cover_image_url' => $m->cover_image_url,
            'is_featured' => (bool) $m->is_featured,
            'company_name_i18n' => $m->company_name_i18n,
            'short_description_i18n' => $m->short_description_i18n,
            'about_description_i18n' => $m->about_description_i18n,
            'galleries' => $m->galleries,
            'benefits' => $m->benefits,
        ];
    }
}
