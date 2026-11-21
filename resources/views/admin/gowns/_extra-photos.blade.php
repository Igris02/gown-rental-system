{{-- Two optional extra photos. Included by the gown form. Expects: $gown --}}
<div class="field full">
    <label>Extra photos (optional)</label>
    <p class="hint">Up to 2 more photos, such as the back or a close-up. They appear small under the main photo and zoom the same way. JPG, PNG, or WEBP, up to 4 MB each. Add the main photo first.</p>
    @error('extra_images')
        <p class="error">{{ $message }}</p>
    @enderror

    <div class="extra-slots">
        @foreach (\App\Models\Gown::EXTRA_PHOTO_SLOTS as $slot)
            @php($photo = $gown->exists ? $gown->photos->firstWhere('position', $slot) : null)
            <div class="extra-slot" data-extra-slot>
                <p class="extra-title">Extra photo {{ $slot }}</p>

                @if ($photo)
                    <img src="{{ $photo->thumbnail_url }}" alt="Current extra photo {{ $slot }}" class="thumb thumb-large">
                    <label class="check">
                        <input type="checkbox" name="remove_extra[{{ $slot }}]" value="1" @checked(old("remove_extra.{$slot}"))>
                        Remove this photo
                    </label>
                @endif

                <input
                    type="file"
                    name="extra_images[{{ $slot }}]"
                    accept="image/jpeg,image/png,image/webp"
                    data-extra-input
                    class="@error("extra_images.{$slot}") is-invalid @enderror"
                >
                @if ($photo)
                    <p class="hint">Choose a file to replace it.</p>
                @endif
                <p class="error" data-extra-error hidden>That photo is larger than 4 MB. Please choose a smaller one.</p>
                @error("extra_images.{$slot}")
                    <p class="error">{{ $message }}</p>
                @enderror

                <img src="" alt="Preview of the selected photo" class="preview preview-small" data-extra-preview hidden>
            </div>
        @endforeach
    </div>
</div>
