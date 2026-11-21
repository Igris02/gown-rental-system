{{-- Up to 2 small extra photos under the main photo. Each one opens the same zoom view as the main photo.
     Expects: $gown with its photos loaded. Only shown when the gown has a main photo (that is where the zoom view lives). --}}
@if ($gown->zoom_url && $gown->photos->isNotEmpty())
    <div class="extra-photos">
        @foreach ($gown->photos as $photo)
            <button
                type="button"
                class="extra-photo"
                data-zoom-src="{{ $photo->zoom_url }}"
                aria-label="Zoom in on extra photo {{ $loop->iteration }} of {{ $gown->name }}"
            >
                <img src="{{ $photo->thumb_url }}" alt="{{ $gown->name }}, extra photo {{ $loop->iteration }}" width="240" height="320" loading="lazy">
            </button>
        @endforeach
    </div>
@endif
