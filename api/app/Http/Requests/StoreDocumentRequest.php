<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'number' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9\/\-\s]+$/'],
            // Type is read from the bytes (finfo), not trusted from the file name.
            'file' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Upload a photo (JPG, PNG) or a PDF of the document.',
            'file.max' => 'The file must be 5 MB or smaller.',
        ];
    }
}
