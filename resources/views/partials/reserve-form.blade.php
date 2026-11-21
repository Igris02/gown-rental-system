{{-- Reserve form with an availability calendar. Used on the gown page (customers) and on the
     walk-in booking page (staff).
     Expects: $gown, $blockedDays, $minDate, $maxDate, $selected
     Optional: $walkIn, true on the staff walk-in page --}}
@php($walkIn = $walkIn ?? false)
<section class="reserve" aria-labelledby="reserve-title">
    <h2 id="reserve-title">{{ $walkIn ? 'Choose the rental dates' : 'Reserve this gown' }}</h2>

    <ol class="rental-steps">
        <li><strong>Day 1</strong><span>Pick up</span></li>
        <li><strong>Day 2</strong><span>Use</span></li>
        <li><strong>Day 3</strong><span>Return</span></li>
    </ol>
    <p class="hint">{{ $walkIn ? 'Choose Day 1. All 3 days are held for this customer.' : 'Choose your Day 1. All 3 days are held for you, so nobody else can book this gown on them.' }}</p>

    <form
        method="POST"
        action="{{ $walkIn ? route('admin.walk-ins.store') : route('reservations.store', $gown) }}"
        data-reserve
        data-blocked-by-size="{{ json_encode($blockedBySize) }}"
        data-min="{{ $minDate }}"
        data-max="{{ $maxDate }}"
        data-selected="{{ $selected }}"
        data-price="{{ $gown->formatted_price }}"
    >
        @csrf
        <input type="hidden" name="start_date" value="{{ $selected }}" data-start-input>

        <div class="field size-picker">
            <label>Size</label>
            @if (count($sizes) === 1)
                <input type="hidden" name="size" value="{{ $sizes[0] }}" data-size-input>
                <p>Size {{ $sizes[0] }}</p>
            @else
                <div class="size-options" role="radiogroup" aria-label="Size">
                    @foreach ($sizes as $size)
                        <label class="size-option">
                            <input type="radio" name="size" value="{{ $size }}" data-size-input @checked($selectedSize === $size)>
                            <span>{{ $size }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="hint">
                    @if ($gown->isSeparateSizes())
                        Each size is a separate gown, so the calendar shows availability for the size you choose.
                    @else
                        This is one adjustable gown, so any size blocks the dates for everyone.
                    @endif
                </p>
            @endif
            @error('size')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="cal">
            <div class="cal-head">
                <button type="button" class="cal-nav" data-prev aria-label="Previous month">&larr;</button>
                <strong data-title aria-live="polite"></strong>
                <button type="button" class="cal-nav" data-next aria-label="Next month">&rarr;</button>
            </div>

            <div class="cal-weekdays" aria-hidden="true">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>

            <div class="cal-grid" data-grid></div>
        </div>

        <div class="cal-legend">
            <span><i class="sw sw-pickup"></i>Day 1 pick up</span>
            <span><i class="sw sw-use"></i>Day 2 use</span>
            <span><i class="sw sw-return"></i>Day 3 return</span>
            <span><i class="sw sw-booked"></i>Reserved</span>
        </div>

        <noscript><p class="error">Please turn on JavaScript to choose the dates.</p></noscript>

        <div class="cal-summary" data-summary aria-live="polite">Choose the pick-up date (Day 1) on the calendar.</div>

        @error('start_date')
            <p class="error">{{ $message }}</p>
        @enderror

        @auth
            @if ($walkIn)
                <input type="hidden" name="gown_id" value="{{ $gown->id }}">

                <div class="walkin-fields">
                    <div class="field">
                        <label for="customer_name">Customer name</label>
                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            value="{{ old('customer_name') }}"
                            maxlength="255"
                            autocomplete="off"
                            required
                            class="@error('customer_name') is-invalid @enderror"
                        >
                        @error('customer_name')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="customer_phone">Phone number</label>
                        <input
                            type="tel"
                            id="customer_phone"
                            name="customer_phone"
                            value="{{ old('customer_phone') }}"
                            maxlength="30"
                            placeholder="0917 123 4567"
                            autocomplete="off"
                            required
                            class="@error('customer_phone') is-invalid @enderror"
                        >
                        @error('customer_phone')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="customer_email">Email (optional)</label>
                        <input
                            type="email"
                            id="customer_email"
                            name="customer_email"
                            value="{{ old('customer_email') }}"
                            maxlength="255"
                            autocomplete="off"
                            class="@error('customer_email') is-invalid @enderror"
                        >
                        @error('customer_email')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @else
                <div class="field">
                    <label for="customer_phone">Mobile number for text updates (optional)</label>
                    <input
                        type="tel"
                        id="customer_phone"
                        name="customer_phone"
                        value="{{ old('customer_phone') }}"
                        maxlength="30"
                        placeholder="0917 123 4567"
                        autocomplete="tel"
                        class="@error('customer_phone') is-invalid @enderror"
                    >
                    <p class="hint">We will text you when the shop answers your reservation, and the day before pick-up and return. Emails are sent to your account email.</p>
                    @error('customer_phone')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="field">
                <label for="customer_note">{{ $walkIn ? 'Note (optional)' : 'Note for the shop (optional)' }}</label>
                <textarea id="customer_note" name="customer_note" rows="3" maxlength="500">{{ old('customer_note') }}</textarea>
                @error('customer_note')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn" data-submit disabled>{{ $walkIn ? 'Save walk-in booking' : 'Reserve this gown' }}</button>
            <p class="hint">{{ $walkIn ? 'The booking is confirmed right away and the 3 days are held for this customer.' : 'The shop will review and confirm your reservation. The dates are held for you as soon as you send it.' }}</p>
        @else
            <a href="{{ route('login') }}" class="btn">Sign in to reserve</a>
        @endauth
    </form>
</section>
