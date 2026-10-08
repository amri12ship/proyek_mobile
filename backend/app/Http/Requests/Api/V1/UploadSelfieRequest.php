<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadSelfieRequest extends FormRequest
{
    public const MAX_KILOBYTES = 4096;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'selfie' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selfie.required' => 'Foto selfie wajib diunggah.',
            'selfie.image' => 'Berkas yang diunggah harus berupa gambar.',
            'selfie.mimes' => 'Format selfie harus JPG, PNG, atau WEBP.',
            'selfie.max' => 'Ukuran selfie maksimal 4 MB.',
        ];
    }
}
