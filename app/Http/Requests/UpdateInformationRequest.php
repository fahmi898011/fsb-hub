<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('information.update');
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
        ];
    }
}
