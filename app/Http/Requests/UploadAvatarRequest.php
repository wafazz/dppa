<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The browser crops the picture to a square before sending it, so the ratio is checked here too.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=512,max_height=512,ratio=1'],
        ];
    }
}
