<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowCategoryController extends Controller
{
    public function index()
    {
        $categories = WorkflowCategory::latest()->get();
        return Inertia::render('Admin/WorkflowCategories/Index', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:workflow_categories,name',
        ]);

        WorkflowCategory::create($request->only('name'));

        return redirect()->back()->with('success', 'Kategori başarıyla eklendi.');
    }

    public function update(Request $request, WorkflowCategory $workflowCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:workflow_categories,name,' . $workflowCategory->id,
        ]);

        $workflowCategory->update($request->only('name'));

        return redirect()->back()->with('success', 'Kategori başarıyla güncellendi.');
    }

    public function destroy(WorkflowCategory $workflowCategory)
    {
        $workflowCategory->delete();

        return redirect()->back()->with('success', 'Kategori silindi.');
    }
}
