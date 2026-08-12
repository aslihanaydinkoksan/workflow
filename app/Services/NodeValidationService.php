<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TreeType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class NodeValidationService
{
    /**
     * Dışarıdan gelen metadata dizisini, TreeType şemasına göre doğrular.
     * Kurallara uymazsa ValidationException fırlatır.
     * * @throws ValidationException
     */
    public function validateMetadata(TreeType $treeType, ?array $metadata): void
    {
        $schema = $treeType->schema ?? [];

        if (empty($schema)) {
            return; // Şema tanımlanmamışsa validasyona gerek yok
        }

        $rules = [];
        $attributes = [];

        // JSON formatındaki şemayı Laravel Validation kurallarına dönüştürüyoruz
        foreach ($schema as $fieldDef) {
            $field = $fieldDef['field'];
            $type = $fieldDef['type'] ?? 'text';
            $isRequired = $fieldDef['required'] ?? false;

            // Gerekli mi opsiyonel mi?
            $fieldRules = [$isRequired ? 'required' : 'nullable'];

            // Veri tipine göre kural ekle
            switch ($type) {
                case 'number':
                    $fieldRules[] = 'numeric'; // Integer yerine numeric, ondalık sayı da kabul etmesi için
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'select':
                    $fieldRules[] = 'string';
                    // Değer, tanımlanan seçeneklerden (options) biri olmak ZORUNDA
                    if (!empty($fieldDef['options'])) {
                        $fieldRules[] = Rule::in($fieldDef['options']);
                    }
                    break;
                case 'multiselect':
                    $fieldRules[] = 'array'; // Çoklu seçim bir dizi (array) olmalı
                    // Dizinin içindeki her bir elemanın da seçeneklerde olması ZORUNDA
                    if (!empty($fieldDef['options'])) {
                        $rules[$field . '.*'] = [Rule::in($fieldDef['options'])];
                    }
                    break;
                default:
                    // text ve textarea
                    $fieldRules[] = 'string';
                    break;
            }

            $rules[$field] = $fieldRules;
            $attributes[$field] = $field; // Hata mesajlarında alan adının temiz görünmesi için
        }

        $validator = Validator::make($metadata ?? [], $rules, [], $attributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
