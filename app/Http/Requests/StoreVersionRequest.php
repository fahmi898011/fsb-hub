<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('information.version');
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50'],
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:' . config('documents.max_size_kb', 20480),
            ],
            'change_notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Dokumen wajib diunggah untuk versi baru.',
            'document.mimes' => 'Dokumen harus berformat PDF.',
        ];
    }
}
