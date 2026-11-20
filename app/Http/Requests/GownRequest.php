<?php

namespace App\Http\Requests;

use App\Models\Gown;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        // On the edit page this is the gown being edited, on the add page it is null
        $gown = $this->route('gown');

        return [
            'gown_code' => ['required', 'string', 'max:20', Rule::unique('gowns', 'gown_code')->ignore($gown)],
            'name' => ['required', 'string', 'max:255'],
            'design' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:50'],
            'size' => ['required', Rule::in(Gown::SIZES)],
            'silhouette' => ['required', 'string', 'max:50'],
            'rental_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::in(Gown::STATUSES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'gown_code.unique' => 'This code is already used by another gown, including deleted ones.',
            'image.max' => 'The photo must not be larger than 4 MB.',
            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',
        ];
    }
}
