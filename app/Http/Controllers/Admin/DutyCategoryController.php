<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DutyCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class DutyCategoryController extends Controller
{
    public function index()
    {
        $categories = DutyCategory::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Admin/DutyCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50|unique:duty_categories,code',
            'icon' => 'required|string|max:20',
            'color' => 'required|string|max:30',
            'description' => 'nullable|string|max:500',
            'is_field_duty' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
            // Uniqueness check for generated code
            $baseCode = $validated['code'];
            $i = 1;
            while (DutyCategory::where('code', $validated['code'])->exists()) {
                $validated['code'] = $baseCode . '-' . $i++;
            }
        }

        DutyCategory::create($validated);

        return redirect()->back()->with('success', 'Görev türü başarıyla oluşturuldu.');
    }

    public function update(Request $request, DutyCategory $dutyCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:duty_categories,code,' . $dutyCategory->id,
            'icon' => 'required|string|max:20',
            'color' => 'required|string|max:30',
            'description' => 'nullable|string|max:500',
            'is_field_duty' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $dutyCategory->update($validated);

        return redirect()->back()->with('success', 'Görev türü başarıyla güncellendi.');
    }

    public function destroy(DutyCategory $dutyCategory)
    {
        $dutyCategory->delete();

        return redirect()->back()->with('success', 'Görev türü silindi.');
    }

    public function toggleActive(DutyCategory $dutyCategory)
    {
        $dutyCategory->update([
            'is_active' => !$dutyCategory->is_active,
        ]);

        return redirect()->back()->with('success', 'Kategori durumu güncellendi.');
    }
}
