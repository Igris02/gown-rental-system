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
            'size_type' => ['required', Rule::in(Gown::SIZE_TYPES)],
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*' => ['required', 'distinct', Rule::in(Gown::SIZES)],
            'silhouette' => ['required', 'string', 'max:50'],
            'rental_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::in(Gown::STATUSES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'extra_images' => ['nullable', 'array'],
            'extra_images.1' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'extra_images.2' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_extra' => ['nullable', 'array'],
            'remove_extra.1' => ['nullable', 'boolean'],
            'remove_extra.2' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Extra photos are shown under the main photo, so a gown needs a main photo before it can have extras.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasFile('extra_images.1') && ! $this->hasFile('extra_images.2')) {
                return;
            }

            $gown = $this->route('gown');
            $hasMain = $this->hasFile('image')
                || ($gown && $gown->image_url && ! $this->boolean('remove_image'));

            if (! $hasMain) {
                $validator->errors()->add('extra_images', 'Add the main photo first. The extra photos are shown under it.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'gown_code.unique' => 'This code is already used by another gown, including deleted ones.',
            'image.max' => 'The photo must not be larger than 4 MB.',
            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',
            'extra_images.*.max' => 'Each extra photo must not be larger than 4 MB.',
            'extra_images.*.mimes' => 'Each extra photo must be a JPG, PNG, or WEBP file.',
            'extra_images.*.image' => 'Each extra photo must be an image.',
            'sizes.required' => 'Please tick at least one size.',
            'sizes.min' => 'Please tick at least one size.',
        ];
    }
}
