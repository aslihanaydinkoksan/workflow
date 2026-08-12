<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\FormTemplate;
use App\Models\FormCategory;
use App\Models\Setting;
use App\Models\FormTemplateRevision;

class FormTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = FormTemplate::with(['creator', 'primaryWorkflows', 'category'])->latest();

        // Filtreleme: İsim
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filtreleme: Aktif/Pasif
        if ($request->filled('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        $templates = $query->get();

        return Inertia::render('FormBuilder/Index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    public function create()
    {
        return Inertia::render('FormBuilder/Designer', [
            'categories' => FormCategory::orderBy('name')->get(),
            'app_logo' => Setting::where('key', 'app_logo')->value('value'),
            'available_bindings' => $this->getAvailableBindings() // Eklendi
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schema' => 'required|array',
            'category_id' => 'nullable|exists:form_categories,id',
            'document_no' => 'nullable|string|max:255',
            'publish_date' => 'nullable|date',
            'revision_no' => 'nullable|string|max:255',
            'revision_date' => 'nullable|date',
            'page_no' => 'nullable|integer',
            'logo_width' => 'nullable|numeric|min:1|max:10',
            'logo_height' => 'nullable|numeric|min:1|max:10',
        ]);

        $template = FormTemplate::create([
            ...$validated,
            'created_by' => Auth::id(),
            'is_active' => true,
        ]);

        return redirect()->route('form-templates.index')->with('success', 'Form başarıyla kaydedildi.');
    }

    public function edit(FormTemplate $formTemplate)
    {
        return Inertia::render('FormBuilder/Designer', [
            'template' => $formTemplate,
            'categories' => FormCategory::orderBy('name')->get(),
            'app_logo' => Setting::where('key', 'app_logo')->value('value'),
            'available_bindings' => $this->getAvailableBindings() // Eklendi
        ]);
    }

    public function update(Request $request, FormTemplate $formTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schema' => 'required|array',
            'category_id' => 'nullable|exists:form_categories,id',
            'document_no' => 'nullable|string|max:255',
            'publish_date' => 'nullable|date',
            'revision_no' => 'nullable|string|max:255',
            'revision_date' => 'nullable|date',
            'page_no' => 'nullable|integer',
            'logo_width' => 'nullable|numeric|min:1|max:10',
            'logo_height' => 'nullable|numeric|min:1|max:10',
        ]);

        // Revizyon logla
        FormTemplateRevision::create([
            'form_template_id' => $formTemplate->id,
            'schema' => $formTemplate->schema,
            'revision_no' => $formTemplate->revision_no,
            'revision_date' => $formTemplate->revision_date,
            'created_by' => Auth::id(),
        ]);

        $formTemplate->update($validated);

        return redirect()->route('form-templates.index')->with('success', 'Form güncellendi.');
    }

    public function destroy(FormTemplate $formTemplate)
    {
        // İstekte bulunanın yetkisi kontrol edilebilir
        $formTemplate->delete();
        return redirect()->route('form-templates.index')->with('success', 'Form şablonu silindi.');
    }

    public function toggleStatus(FormTemplate $formTemplate)
    {
        $formTemplate->update([
            'is_active' => !$formTemplate->is_active
        ]);

        return redirect()->back()->with('success', 'Form durumu güncellendi.');
    }
    /**
     * Frontend'in (Vue) "Array of Objects" olarak beklediği veri bağlama listesini hazırlar.
     */
    private function getAvailableBindings(): array
    {
        $bindings = [];
        $treeTypes = \App\Models\TreeType::whereNotNull('schema')->where('is_active', true)->get();

        foreach ($treeTypes as $treeType) {
            $schema = $treeType->schema ?? [];

            // Personel şemalarını 'actor', diğerlerini 'target' olarak sınıflandır
            $isActor = str_contains(strtolower($treeType->key), 'personel')
                || str_contains(strtolower($treeType->display_name), 'personel');

            $prefix = $isActor ? 'actor.metadata.' : 'target.metadata.';
            $labelPrefix = $treeType->display_name . ' -> ';

            foreach ($schema as $col) {
                // DİKKAT: Eski kodda $bindings[$prefix...] = ... şeklindeydi (Yani PHP bunu Object yapıyordu).
                // ŞİMDİ dizinin içine yeni bir Dizi itiyoruz (Vue'nun beklediği Array yapısı).
                $bindings[] = [
                    'value'   => $prefix . $col['field'],
                    'label'   => $labelPrefix . $col['field'],
                    'type'    => $col['type'] ?? 'string',
                    'options' => $col['options'] ?? null
                ];
            }
        }

        // Vue'nun kesinlikle Array ([]) olarak algılaması için indexleri sıfırlayarak dönüyoruz
        return array_values($bindings);
    }
}
