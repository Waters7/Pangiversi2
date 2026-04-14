<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DokumenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'surat_tugas' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'sppd' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'boarding_pass' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'faktur' => ['nullable', 'file', 'mimes:pdf,doc,docx,xlsx,xls', 'max:2048'],
            'kwintasi' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'bill_hotel' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
            'laporan_hasil' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];

    }
}
