<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\FormCategory;

class FormCategoryController extends Controller
{
    public function index()
    {
        $categories = FormCategory::latest()->get();
        return Inertia::render('Admin/FormCategories/Index', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:form_categories,name',
            'description' => 'nullable|string',
        ]);

        FormCategory::create($validated);

        return redirect()->back()->with('success', 'Form kategorisi oluşturuldu.');
    }

    public function update(Request $request, FormCategory $formCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:form_categories,name,' . $formCategory->id,
            'description' => 'nullable|string',
        ]);

        $formCategory->update($validated);

        return redirect()->back()->with('success', 'Form kategorisi güncellendi.');
    }

    public function destroy(FormCategory $formCategory)
    {
        // İstekte bulunanın yetkisi kontrol edilebilir
        $formCategory->delete();
        return redirect()->back()->with('success', 'Form kategorisi silindi.');
    }
}
