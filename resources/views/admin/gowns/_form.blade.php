{{-- Shared by the add and edit pages. Expects: $gown, $sizes, $silhouettes, $statuses, $action, $method, $submitLabel --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-gown-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <div class="field">
            <label for="gown_code">Gown code</label>
            <input
                type="text"
                id="gown_code"
                name="gown_code"
                value="{{ old('gown_code', $gown->gown_code) }}"
                maxlength="20"
                required
                class="@error('gown_code') is-invalid @enderror"
            >
            @error('gown_code')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $gown->name) }}"
                maxlength="255"
                required
                class="@error('name') is-invalid @enderror"
            >
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="design">Design</label>
            <input
                type="text"
                id="design"
                name="design"
                value="{{ old('design', $gown->design) }}"
                maxlength="255"
                placeholder="Floral lace, beaded bodice, etc."
                required
                class="@error('design') is-invalid @enderror"
            >
            @error('design')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="color">Color</label>
            <input
                type="text"
                id="color"
                name="color"
                value="{{ old('color', $gown->color) }}"
                maxlength="50"
                required
                class="@error('color') is-invalid @enderror"
            >
            @error('color')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="size">Size</label>
            <select id="size" name="size" required class="@error('size') is-invalid @enderror">
                <option value="">Choose a size</option>
                @foreach ($sizes as $size)
                    <option value="{{ $size }}" @selected(old('size', $gown->size) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            @error('size')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="silhouette">Silhouette</label>
            <input
                type="text"
                id="silhouette"
                name="silhouette"
                value="{{ old('silhouette', $gown->silhouette) }}"
                list="silhouette-options"
                maxlength="50"
                placeholder="Type a silhouette, such as A-line"
                autocomplete="off"
                required
                class="@error('silhouette') is-invalid @enderror"
            >
            <datalist id="silhouette-options">
                @foreach ($silhouettes as $silhouette)
                    <option value="{{ $silhouette }}"></option>
                @endforeach
            </datalist>
            <p class="hint">Type any silhouette. Ones you used before are suggested, so the spelling stays the same.</p>
            @error('silhouette')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="rental_price">Rental price (₱)</label>
            <input
                type="number"
                id="rental_price"
                name="rental_price"
                value="{{ old('rental_price', $gown->getRawOriginal('rental_price')) }}"
                min="0"
                max="99999999.99"
                step="0.01"
                required
                class="@error('rental_price') is-invalid @enderror"
            >
            @error('rental_price')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" required class="@error('status') is-invalid @enderror">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $gown->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <p class="hint">Only "Available" gowns can be reserved by customers.</p>
            @error('status')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field full">
            <label for="description">Description (optional)</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                maxlength="1000"
                class="@error('description') is-invalid @enderror"
            >{{ old('description', $gown->description) }}</textarea>
            @error('description')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field full">
            <label for="image">Photo</label>

            @if ($gown->thumbnail_url)
                <div class="current-photo">
                    <img src="{{ $gown->thumbnail_url }}" alt="Current photo of {{ $gown->name }}" class="thumb thumb-large">
                    <label class="check">
                        <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))>
                        Remove current photo
                    </label>
                </div>
                <p class="hint">Choose a new file below to replace the current photo.</p>
            @endif

            <input
                type="file"
                id="image"
                name="image"
                accept="image/jpeg,image/png,image/webp"
                data-image-input
                class="@error('image') is-invalid @enderror"
            >
            <p class="hint">JPG, PNG, or WEBP, up to 4 MB. Best results: a portrait photo (3:4) at least 1200 x 1600 px. The catalog shows it at 600 x 800 and crops other shapes to fit.</p>
            <p class="error" data-image-error hidden>That photo is larger than 4 MB. Please choose a smaller one.</p>
            @error('image')
                <p class="error">{{ $message }}</p>
            @enderror

            <img src="" alt="Preview of the selected photo" class="preview" data-image-preview hidden>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">{{ $submitLabel }}</button>
        <a href="{{ route('admin.gowns.index') }}">Cancel</a>
    </div>
</form>
