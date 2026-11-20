{{-- Gown photo (600 x 800) that opens a 1200 x 1600 zoom view when clicked.
     Expects: $gown. Reuse this with @include on any page that shows a single gown. --}}
@if ($gown->zoom_url)
    <button
        type="button"
        class="zoom-trigger"
        data-zoom-src="{{ $gown->zoom_url }}"
        aria-label="Zoom in on the photo of {{ $gown->name }}"
    >
        <img src="{{ $gown->card_url }}" alt="{{ $gown->name }}" width="600" height="800">
        <span class="zoom-hint">Click to zoom</span>
    </button>

    <div
        class="lightbox"
        data-lightbox
        role="dialog"
        aria-modal="true"
        aria-label="Zoomed photo of {{ $gown->name }}"
        hidden
    >
        <button type="button" class="lightbox-close" data-lightbox-close>Close</button>

        <div class="lightbox-stage" data-lightbox-stage>
            <img alt="{{ $gown->name }}, zoomed" data-lightbox-image>
        </div>

        <p class="lightbox-note">Click the photo to switch between fit and full size (1200 &times; 1600)</p>
    </div>
@else
    <span>No photo</span>
@endif
