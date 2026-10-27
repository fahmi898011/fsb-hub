<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('information.create');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:information_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'version' => ['nullable', 'string', 'max:50'],
            'change_notes' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
            'document' => [
                Rule::requiredIf(fn () => $this->status === 'published'),
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:' . config('documents.max_size_kb', 20480),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document.mimes' => 'Dokumen harus berformat PDF.',
            'document.max' => 'Ukuran dokumen terlalu besar.',
        ];
    }
}
