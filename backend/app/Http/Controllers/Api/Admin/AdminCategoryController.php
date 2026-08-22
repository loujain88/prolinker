<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * GET    /api/admin/categories       — All categories with service count
 * POST   /api/admin/categories       — Create new category
 * PUT    /api/admin/categories/{id}  — Update name / icon
 * DELETE /api/admin/categories/{id}  — Delete (blocked if services exist)
 */
class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ServiceCategory::withCount('services')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                'icon' => $c->icon, 'services_count' => $c->services_count,
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $category = ServiceCategory::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
        ]);

        return response()->json([
            'message' => "تم إضافة التصنيف «{$category->name}» بنجاح.",
            'data'    => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'icon' => $category->icon, 'services_count' => 0],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category  = ServiceCategory::findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('service_categories','name')->ignore($id)],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['name'])) $validated['slug'] = Str::slug($validated['name']);
        $category->update($validated);

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح.',
            'data'    => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'icon' => $category->icon],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = ServiceCategory::withCount('services')->findOrFail($id);

        if ($category->services_count > 0) {
            return response()->json([
                'message' => "لا يمكن حذف التصنيف «{$category->name}» — يحتوي على {$category->services_count} خدمة.",
            ], 422);
        }

        $name = $category->name;
        $category->delete();

        return response()->json(['message' => "تم حذف التصنيف «{$name}» بنجاح."]);
    }
}
