<?php
/*
 * Applies the "multiple sizes" update to the existing project files.
 * Run from the project root:   php apply_size_update.php
 *
 * It checks every edit first and writes NOTHING unless all of them match.
 * Commit or back up your project before running it.
 */

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\nNothing was changed.\n");
    exit(1);
}

final class Patch
{
    public string $text;
    public bool $crlf;
    public bool $skip = false;

    public function __construct(public string $path, string $alreadyMarker)
    {
        $file = __DIR__ . '/' . $path;

        if (! is_file($file)) {
            fail("File not found: {$path}. Run this script from the project root.");
        }

        $raw = file_get_contents($file);
        $this->crlf = str_contains($raw, "\r\n");
        $this->text = str_replace("\r\n", "\n", $raw);

        if ($alreadyMarker !== '' && str_contains($this->text, $alreadyMarker)) {
            $this->skip = true;
        }
    }

    /** Replace one exact piece of text that must appear exactly once. */
    public function rep(string $old, string $new): void
    {
        if ($this->skip) {
            return;
        }

        $count = substr_count($this->text, $old);

        if ($count !== 1) {
            fail("{$this->path}: expected 1 match but found {$count} for:\n" . substr($old, 0, 160));
        }

        $this->text = str_replace($old, $new, $this->text);
    }

    /**
     * Replace everything from the line $startLine up to (not including) the line $endLine.
     * $startToken / $endToken move each position back to the start of that block (for example its docblock).
     */
    public function section(string $startLine, string $endLine, string $new, string $startToken = '', string $endToken = ''): void
    {
        if ($this->skip) {
            return;
        }

        $s = $this->locate($startLine, $startToken);
        $e = $this->locate($endLine, $endToken);

        if ($e <= $s) {
            fail("{$this->path}: section end comes before start ({$startLine}).");
        }

        $this->text = substr($this->text, 0, $s) . $new . substr($this->text, $e);
    }

    /** Replace everything from $startLine to the end of the file. */
    public function tail(string $startLine, string $new, string $startToken = ''): void
    {
        if ($this->skip) {
            return;
        }

        $s = $this->locate($startLine, $startToken);
        $this->text = substr($this->text, 0, $s) . $new;
    }

    private function locate(string $line, string $token): int
    {
        $count = substr_count($this->text, $line);

        if ($count !== 1) {
            fail("{$this->path}: expected 1 match but found {$count} for:\n{$line}");
        }

        $pos = strpos($this->text, $line);

        if ($token === '') {
            return $pos;
        }

        $back = strrpos(substr($this->text, 0, $pos), $token);

        if ($back === false) {
            fail("{$this->path}: could not find the start of the block for:\n{$line}");
        }

        return $back;
    }

    public function save(): void
    {
        if ($this->skip) {
            echo "  skipped (already updated): {$this->path}\n";

            return;
        }

        $out = $this->crlf ? str_replace("\n", "\r\n", $this->text) : $this->text;
        file_put_contents(__DIR__ . '/' . $this->path, $out);
        echo "  updated: {$this->path}\n";
    }
}

$patches = [];

// =====================================================================================
// app/Models/Gown.php
// =====================================================================================
$p = new Patch('app/Models/Gown.php', 'SIZE_TYPE_SINGLE');

$p->rep(
    "use Illuminate\\Support\\Collection;\n",
    "use Illuminate\\Support\\Collection;\nuse Illuminate\\Support\\Facades\\DB;\n"
);

$p->rep(
    "    public const SIZES = ['XS', 'S', 'M', 'L', 'XL'];\n",
    <<<'EOT'
    public const SIZES = ['XS', 'S', 'M', 'L', 'XL'];

    public const SIZE_TYPE_SINGLE = 'single';      // one piece, fits all listed sizes
    public const SIZE_TYPE_SEPARATE = 'separate';  // one gown per size

    public const SIZE_TYPES = [self::SIZE_TYPE_SINGLE, self::SIZE_TYPE_SEPARATE];

EOT
);

$p->rep("        'size',\n", "        'size_type',\n");

$p->rep(
    "    public function reservations(): HasMany\n",
    <<<'EOT'
    public function sizes(): HasMany
    {
        return $this->hasMany(GownSize::class);
    }

    /**
     * Sizes in the order of Gown::SIZES, for example ['M', 'L', 'XL'].
     *
     * @return string[]
     */
    public function sizeList(): array
    {
        $order = array_flip(self::SIZES);

        return $this->sizes->pluck('size')
            ->sortBy(fn ($size) => $order[$size] ?? 99)
            ->values()
            ->all();
    }

    /** "M / L / XL" */
    public function getSizeLabelAttribute(): string
    {
        return implode(' / ', $this->sizeList()) ?: '—';
    }

    public function isSeparateSizes(): bool
    {
        return $this->size_type === self::SIZE_TYPE_SEPARATE;
    }

    public function getSizeTypeLabelAttribute(): string
    {
        return $this->isSeparateSizes() ? 'Separate gown per size' : 'One piece, all sizes';
    }

    public function reservations(): HasMany

EOT
);

$p->section(
    '     * Gowns with no active reservation on any day from $start to $end (Y-m-d strings).',
    '     * Optional catalog filters: color, size, silhouette, and a search word.',
    <<<'EOT'
    /**
     * Gowns that can be rented on every day from $start to $end (Y-m-d strings).
     * One-piece gowns need no active reservation at all. Separate gowns need at least one size that is free.
     */
    public function scopeFreeBetween(Builder $query, string $start, string $end): Builder
    {
        $active = Reservation::ACTIVE_STATUSES;

        return $query->where(function (Builder $q) use ($start, $end, $active) {
            $q->where(function (Builder $q) use ($start, $end) {
                $q->where('size_type', self::SIZE_TYPE_SINGLE)
                    ->whereDoesntHave('reservations', fn ($r) => $r->active()->overlapping($start, $end));
            })->orWhere(function (Builder $q) use ($start, $end, $active) {
                $q->where('size_type', self::SIZE_TYPE_SEPARATE)
                    ->whereExists(function ($sizes) use ($start, $end, $active) {
                        $sizes->select(DB::raw(1))
                            ->from('gown_sizes')
                            ->whereColumn('gown_sizes.gown_id', 'gowns.id')
                            ->whereNotExists(function ($taken) use ($start, $end, $active) {
                                $taken->select(DB::raw(1))
                                    ->from('reservations')
                                    ->whereColumn('reservations.gown_id', 'gown_sizes.gown_id')
                                    ->whereColumn('reservations.size', 'gown_sizes.size')
                                    ->whereIn('reservations.status', $active)
                                    ->where('reservations.start_date', '<=', $end)
                                    ->where('reservations.end_date', '>=', $start);
                            });
                    });
            });
        });
    }


EOT,
    '    /**',
    '    /**'
);

$p->rep(
    "            ->when(\$filters['size'] ?? null, fn (\$q, \$v) => \$q->where('size', \$v))",
    "            ->when(\$filters['size'] ?? null, fn (\$q, \$v) => \$q->whereHas('sizes', fn (\$s) => \$s->where('size', \$v)))"
);
$patches[] = $p;

// =====================================================================================
// app/Models/Reservation.php
// =====================================================================================
$p = new Patch('app/Models/Reservation.php', "        'gown_id',\n        'size',\n");
$p->rep("        'gown_id',\n        'start_date',\n", "        'gown_id',\n        'size',\n        'start_date',\n");
$patches[] = $p;

// =====================================================================================
// app/Services/ReservationService.php
// =====================================================================================
$p = new Patch('app/Services/ReservationService.php', 'blockedBySize');

$p->section(
    '     * Is the gown free on every day from $start to $end?',
    '    // Booking and status changes',
    <<<'EOT'
    /**
     * Is the gown free on every day from $start to $end?
     * With $lock = true the matching rows are locked until the transaction ends.
     * With $size, only reservations for that size count (used for gowns that have a separate gown per size).
     */
    public function isFree(int $gownId, string $start, string $end, bool $lock = false, ?string $size = null): bool
    {
        $query = Reservation::query()
            ->active()
            ->where('gown_id', $gownId)
            ->when($size !== null, fn ($q) => $q->where('size', $size))
            ->overlapping($start, $end);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first(['id']) === null;
    }

    /**
     * Blocked days for each size of the gown, for the calendar on the gown page:
     * ['M' => ['2026-12-01', ...], 'L' => [...]].
     * A one-piece gown blocks every size. A separate-per-size gown blocks only the size that was booked.
     * The calendar starts on $from (tomorrow for customers, today for walk-ins).
     *
     * @return array<string, string[]>
     */
    public function blockedBySize(Gown $gown, ?string $from = null): array
    {
        $from ??= self::earliestStart();
        $to = self::endDate(self::latestStart());

        $sizes = $gown->sizeList();
        $map = array_fill_keys($sizes, []);

        $reservations = Reservation::query()
            ->active()
            ->where('gown_id', $gown->id)
            ->overlapping($from, $to)
            ->get(['start_date', 'end_date', 'size']);

        foreach ($reservations as $reservation) {
            $days = self::datesBetween($reservation->start_date->toDateString(), $reservation->end_date->toDateString());
            $targets = $gown->isSeparateSizes() ? [$reservation->size] : $sizes;

            foreach ($targets as $size) {
                if (! isset($map[$size])) {
                    continue;
                }

                foreach ($days as $day) {
                    $map[$size][$day] = true;
                }
            }
        }

        return array_map(function ($days) {
            $days = array_keys($days);
            sort($days);

            return $days;
        }, $map);
    }

    /**
     * The size to pre-select: the requested one if the gown has it, otherwise the first size.
     *
     * @param string[] $sizes
     */
    public static function pickSize(array $sizes, mixed $wanted): string
    {
        return is_string($wanted) && in_array($wanted, $sizes, true) ? $wanted : ($sizes[0] ?? '');
    }

EOT,
    '    /**',
    '    // ---'
);

$p->section(
    '     * A customer books a gown online for 3 days starting on $start. The reservation starts',
    '     * Moves a reservation to a new status, only when the move is allowed.',
    <<<'EOT'
    /**
     * A customer books a gown online for 3 days starting on $start, in the chosen $size. The
     * reservation starts as pending and waits for staff. Throws ReservationException with a
     * message that can be shown on screen when a rule is broken.
     */
    public function create(User $user, int $gownId, mixed $start, ?string $size, ?string $note = null, ?string $phone = null): Reservation
    {
        $startDate = $this->checkedStartDate($start, self::earliestStart());

        return $this->book($gownId, $startDate, $size, [
            'user_id' => $user->id,
            'status' => Reservation::STATUS_PENDING,
            'source' => Reservation::SOURCE_ONLINE,
            // Optional mobile number for text messages. The customer's name and email come from their account.
            'customer_phone' => $this->cleanText($phone),
            'customer_note' => $this->cleanText($note),
        ]);
    }

    /**
     * Staff book a gown at the shop for a customer who may not have an account. The booking
     * is confirmed straight away, because the customer is there and the staff member agreed it.
     *
     * @param array{name?: ?string, phone?: ?string, email?: ?string} $customer
     */
    public function createWalkIn(User $staff, int $gownId, mixed $start, ?string $size, array $customer, ?string $note = null): Reservation
    {
        $startDate = $this->checkedStartDate($start, self::walkInEarliestStart());

        $name = $this->cleanText($customer['name'] ?? null);
        $phone = $this->cleanText($customer['phone'] ?? null);

        if ($name === null || $phone === null) {
            throw new ReservationException("Please enter the customer's name and phone number.");
        }

        return $this->book($gownId, $startDate, $size, [
            'user_id' => null,
            'status' => Reservation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'source' => Reservation::SOURCE_WALK_IN,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $this->cleanText($customer['email'] ?? null),
            'customer_note' => $this->cleanText($note),
            'created_by' => $staff->id,
        ]);
    }

EOT,
    '    /**',
    '    /**'
);

$p->tail(
    '     * The one place a reservation is saved. Online and walk-in bookings both pass through',
    <<<'EOT'
    /**
     * The one place a reservation is saved. Online and walk-in bookings both pass through
     * here, so both get the same lock and the same availability check.
     *
     * @param array<string, mixed> $attributes who is booking and how
     */
    private function book(int $gownId, string $startDate, ?string $size, array $attributes): Reservation
    {
        $endDate = self::endDate($startDate);

        return DB::transaction(function () use ($gownId, $startDate, $endDate, $size, $attributes) {
            // Lock this gown's row first. Anyone else booking the same gown, online or at the
            // shop, has to wait here until this transaction finishes, so two bookings can never
            // both pass the check.
            $gown = Gown::query()->whereKey($gownId)->lockForUpdate()->first();

            if ($gown === null || $gown->status !== Gown::STATUS_AVAILABLE) {
                throw new ReservationException('This gown is not available for reservation.');
            }

            $sizes = $gown->sizeList();
            $size = $this->cleanText($size) ?? (count($sizes) === 1 ? $sizes[0] : null);

            if ($size === null || ! in_array($size, $sizes, true)) {
                throw new ReservationException('Please choose one of the available sizes for this gown.');
            }

            // A gown with a separate piece per size is checked per size.
            // A one-piece gown is checked as a whole, whichever size was picked.
            $checkSize = $gown->isSeparateSizes() ? $size : null;

            if (! $this->isFree($gown->id, $startDate, $endDate, lock: true, size: $checkSize)) {
                throw new ReservationException(
                    'Sorry, this gown is already reserved on at least one of those days'
                    . ($checkSize ? " in size {$size}" : '')
                    . '. Please choose another pick-up date' . ($checkSize ? ' or size.' : '.')
                );
            }

            return Reservation::create($attributes + [
                'gown_id' => $gown->id,
                'size' => $size,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rental_price' => $gown->rental_price,
            ]);
        });
    }
}

EOT,
    '    /**'
);
$patches[] = $p;

// =====================================================================================
// app/Services/ReportService.php  (double booking check)
// =====================================================================================
$p = new Patch('app/Services/ReportService.php', "g.size_type = 'single'");
$p->rep(
    <<<'EOT'
        WHERE a.status IN ('pending', 'confirmed', 'picked_up')
          AND b.status IN ('pending', 'confirmed', 'picked_up')
    SQL;
EOT,
    <<<'EOT'
        JOIN gowns g ON g.id = a.gown_id
        WHERE a.status IN ('pending', 'confirmed', 'picked_up')
          AND b.status IN ('pending', 'confirmed', 'picked_up')
          AND (g.size_type = 'single' OR a.size = b.size)
    SQL;
EOT
);
$patches[] = $p;

// =====================================================================================
// app/Http/Requests/GownRequest.php
// =====================================================================================
$p = new Patch('app/Http/Requests/GownRequest.php', "'sizes.*'");
$p->rep(
    "            'size' => ['required', Rule::in(Gown::SIZES)],",
    <<<'EOT'
            'size_type' => ['required', Rule::in(Gown::SIZE_TYPES)],
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*' => ['required', 'distinct', Rule::in(Gown::SIZES)],
EOT
);
$p->rep(
    "            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',",
    <<<'EOT'
            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',
            'sizes.required' => 'Please tick at least one size.',
            'sizes.min' => 'Please tick at least one size.',
EOT
);
$patches[] = $p;

// =====================================================================================
// app/Http/Controllers/Admin/GownController.php
// =====================================================================================
$p = new Patch('app/Http/Controllers/Admin/GownController.php', 'syncSizes');

$p->rep(
    "use App\\Services\\CloudinaryService;\n",
    "use App\\Services\\AuditLogger;\nuse App\\Services\\CloudinaryService;\n"
);
$p->rep(
    "use Illuminate\\Http\\Request;\n",
    "use Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n"
);

$p->rep(
    "        \$gowns = Gown::query()\n            ->filter(",
    "        \$gowns = Gown::query()\n            ->with('sizes')\n            ->filter("
);

$p->rep(
    "            'gown_code' => sprintf('A-%03d', \$next),\n            'status' => Gown::STATUS_AVAILABLE,\n",
    "            'gown_code' => sprintf('A-%03d', \$next),\n            'status' => Gown::STATUS_AVAILABLE,\n            'size_type' => Gown::SIZE_TYPE_SINGLE,\n"
);

// store()
$p->rep(
    "        \$data = \$request->safe()->except(['image', 'remove_image']);\n        \$upload = null;\n",
    "        \$data = \$request->safe()->except(['image', 'remove_image', 'sizes']);\n        \$sizes = \$request->validated('sizes');\n        \$upload = null;\n"
);
$p->rep(
    "            \$gown = Gown::create(\$data);\n",
    <<<'EOT'
            $gown = DB::transaction(function () use ($data, $sizes) {
                $gown = Gown::create($data);
                $this->syncSizes($gown, $sizes);

                return $gown;
            });

EOT
);

// update()
$p->rep(
    "        \$data = \$request->safe()->except(['image', 'remove_image']);\n        \$oldPublicId = \$gown->image_public_id;\n        \$upload = null;\n",
    <<<'EOT'
        $data = $request->safe()->except(['image', 'remove_image', 'sizes']);
        $sizes = $request->validated('sizes');
        $oldSizes = $gown->sizeList();

        // Check this first, so a photo is never uploaded for a change that gets refused
        if ($problem = $this->sizeProblem($gown, $request->validated('size_type'), $sizes)) {
            return back()->withInput()->withErrors(['sizes' => $problem]);
        }

        $oldPublicId = $gown->image_public_id;
        $upload = null;

EOT
);
$p->rep(
    "            \$gown->update(\$data);\n",
    <<<'EOT'
            DB::transaction(function () use ($gown, $data, $sizes) {
                $gown->update($data);
                $this->syncSizes($gown, $sizes);
            });

EOT
);
$p->rep(
    "        // The database is saved, so it is now safe to remove the old photo\n",
    <<<'EOT'
        // Sizes live in their own table, so the audit observer cannot see them: log them here
        $newSizes = $gown->sizeList();

        if ($oldSizes !== $newSizes) {
            AuditLogger::record(
                'gown.updated',
                "Edited gown {$gown->gown_code} {$gown->name}: sizes.",
                $gown,
                trim("{$gown->gown_code} {$gown->name}"),
                ['sizes' => [implode(' / ', $oldSizes), implode(' / ', $newSizes)]]
            );
        }

        // The database is saved, so it is now safe to remove the old photo

EOT
);

$p->rep(
    "    private function uploadErrorMessage(Throwable \$e): string\n",
    <<<'EOT'
    /**
     * Saves the ticked sizes: removes the unticked ones and adds the new ones.
     *
     * @param string[] $sizes
     */
    private function syncSizes(Gown $gown, array $sizes): void
    {
        $sizes = array_values(array_unique($sizes));

        $gown->sizes()->whereNotIn('size', $sizes)->delete();

        $existing = $gown->sizes()->pluck('size')->all();

        foreach (array_diff($sizes, $existing) as $size) {
            $gown->sizes()->create(['size' => $size]);
        }

        $gown->unsetRelation('sizes');
    }

    /**
     * Stops size changes that would break open reservations.
     *
     * @param string[] $newSizes
     */
    private function sizeProblem(Gown $gown, string $newType, array $newSizes): ?string
    {
        $removed = array_diff($gown->sizeList(), $newSizes);

        if ($removed !== [] && $gown->reservations()->active()->whereIn('size', $removed)->exists()) {
            return 'A size you removed (' . implode(', ', $removed) . ') still has open reservations. Complete or cancel them first.';
        }

        if ($gown->isSeparateSizes() && $newType === Gown::SIZE_TYPE_SINGLE && $gown->reservations()->active()->exists()) {
            return 'This gown still has open reservations, so it cannot be changed from "separate per size" to "one piece" yet. Complete or cancel them first.';
        }

        return null;
    }

    private function uploadErrorMessage(Throwable $e): string

EOT
);

$p->rep(
    "    private function formData(Gown \$gown): array\n    {\n",
    "    private function formData(Gown \$gown): array\n    {\n        if (\$gown->exists) {\n            \$gown->loadMissing('sizes');\n        }\n\n"
);
$patches[] = $p;

// =====================================================================================
// app/Http/Controllers/CatalogController.php
// =====================================================================================
$p = new Patch('app/Http/Controllers/CatalogController.php', 'blockedBySize');

$p->rep(
    "        \$query = Gown::query()\n            ->available()\n",
    "        \$query = Gown::query()\n            ->with('sizes')\n            ->available()\n"
);
$p->tail(
    "        return view('catalog.show', [",
    <<<'EOT'
        $gown->load('sizes');
        $sizes = $gown->sizeList();

        return view('catalog.show', [
            'gown' => $gown,
            'sizes' => $sizes,
            'selectedSize' => ReservationService::pickSize($sizes, old('size', $request->query('size'))),
            'blockedBySize' => $this->reservations->blockedBySize($gown),
            'minDate' => ReservationService::earliestStart(),
            'maxDate' => ReservationService::latestStart(),
            'selected' => $selected,
        ]);
    }
}

EOT
);
$patches[] = $p;

// =====================================================================================
// app/Http/Controllers/Admin/WalkInController.php
// =====================================================================================
$p = new Patch('app/Http/Controllers/Admin/WalkInController.php', 'blockedBySize');

$p->section(
    '            if ($gown) {',
    '            $gownNotFound = true;',
    <<<'EOT'
            if ($gown) {
                $sizes = $gown->sizeList();

                return view('admin.walk-ins.create', [
                    'gown' => $gown,
                    'sizes' => $sizes,
                    'selectedSize' => ReservationService::pickSize($sizes, old('size', $request->query('size'))),
                    'blockedBySize' => $this->reservations->blockedBySize($gown, ReservationService::walkInEarliestStart()),
                    'minDate' => ReservationService::walkInEarliestStart(),
                    'maxDate' => ReservationService::latestStart(),
                    'selected' => ReservationService::validWalkInPickupOrNull(
                        old('start_date', $request->query('date'))
                    ) ?? '',
                ]);
            }


EOT
);
$p->rep(
    "        \$gowns = Gown::query()\n            ->available()\n            ->filter(['search' => \$search])",
    "        \$gowns = Gown::query()\n            ->with('sizes')\n            ->available()\n            ->filter(['search' => \$search])"
);
$p->rep(
    "            'gown_id' => ['required', 'integer'],\n",
    "            'gown_id' => ['required', 'integer'],\n            'size' => ['required', 'string', 'max:20'],\n"
);
$p->rep(
    "            'start_date.required' => 'Please choose the pick-up date on the calendar.',\n",
    "            'start_date.required' => 'Please choose the pick-up date on the calendar.',\n            'size.required' => 'Please choose a size.',\n"
);
$p->rep(
    "                \$data['start_date'],\n                [\n",
    "                \$data['start_date'],\n                \$data['size'],\n                [\n"
);
$patches[] = $p;

// =====================================================================================
// app/Http/Controllers/ReservationController.php  (customer)
// =====================================================================================
$p = new Patch('app/Http/Controllers/ReservationController.php', "'size' =>");
$p->rep(
    "            'start_date' => ['required', 'string'],\n",
    "            'start_date' => ['required', 'string'],\n            'size' => ['required', 'string', 'max:20'],\n"
);
$p->rep(
    "            'start_date.required' => 'Please choose your pick-up date on the calendar.',\n",
    "            'start_date.required' => 'Please choose your pick-up date on the calendar.',\n            'size.required' => 'Please choose a size.',\n"
);
$p->rep(
    "                \$data['start_date'],\n                \$data['customer_note'] ?? null,\n",
    "                \$data['start_date'],\n                \$data['size'],\n                \$data['customer_note'] ?? null,\n"
);
$patches[] = $p;

// =====================================================================================
// Emails and text messages show the size
// =====================================================================================
$p = new Patch('app/Services/ReservationNotifier.php', "'size' => \$reservation->size");
$p->rep(
    "            'code' => \$gown->gown_code,\n",
    "            'code' => \$gown->gown_code,\n            'size' => \$reservation->size,\n"
);
$patches[] = $p;

$p = new Patch('app/Support/ReservationMessages.php', "! empty(\$d['size'])");
$p->rep(
    "        \$gown = \"{\$d['gown']} ({\$d['code']})\";",
    "        \$gown = \"{\$d['gown']} ({\$d['code']})\" . (! empty(\$d['size']) ? \", size {\$d['size']}\" : '');"
);
$patches[] = $p;

// =====================================================================================
// Views
// =====================================================================================
$p = new Patch('resources/views/admin/gowns/_form.blade.php', 'name="sizes[]"');
$p->section(
    '            <label for="size">Size</label>',
    '            <label for="silhouette">Silhouette</label>',
    <<<'EOT'
        <div class="field full">
            <label>Sizes</label>
            @php($chosen = old('sizes', $gown->exists ? $gown->sizeList() : []))
            <div class="size-checks">
                @foreach ($sizes as $size)
                    <label class="check">
                        <input type="checkbox" name="sizes[]" value="{{ $size }}" @checked(in_array($size, $chosen, true))>
                        {{ $size }}
                    </label>
                @endforeach
            </div>
            <p class="hint">Tick every size this gown comes in, for example M / L / XL.</p>
            @error('sizes')
                <p class="error">{{ $message }}</p>
            @enderror
            @error('sizes.*')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field full">
            <label>How do these sizes work?</label>
            @php($type = old('size_type', $gown->size_type ?? 'single'))
            <div class="size-types">
                <label class="check">
                    <input type="radio" name="size_type" value="single" @checked($type === 'single')>
                    <span><strong>One piece, all sizes.</strong> One physical gown that fits every ticked size (adjustable or altered). Only one customer can rent it at a time, whichever size they pick.</span>
                </label>
                <label class="check">
                    <input type="radio" name="size_type" value="separate" @checked($type === 'separate')>
                    <span><strong>Separate gown for each size.</strong> Same design, one physical gown per size. Each size is booked on its own, so M and L can be rented on the same days.</span>
                </label>
            </div>
            @error('size_type')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

EOT,
    '        <div class="field">',
    ''
);
$patches[] = $p;

$p = new Patch('resources/views/admin/gowns/index.blade.php', 'size_label');
$p->rep('<td>{{ $gown->size }}</td>', '<td>{{ $gown->size_label }}<br><small>{{ $gown->size_type_label }}</small></td>');
$patches[] = $p;

$p = new Patch('resources/views/admin/walk-ins/pick.blade.php', 'size_label');
$p->rep('<td>{{ $gown->size }}</td>', '<td>{{ $gown->size_label }}<br><small>{{ $gown->size_type_label }}</small></td>');
$patches[] = $p;

$p = new Patch('resources/views/catalog/index.blade.php', 'size_label');
$p->rep('Size {{ $gown->size }}', 'Sizes {{ $gown->size_label }}');
$patches[] = $p;

$p = new Patch('resources/views/catalog/show.blade.php', 'size_label');
$p->rep('<dd>{{ $gown->size }}</dd>', '<dd>{{ $gown->size_label }}</dd>');
$patches[] = $p;

$p = new Patch('resources/views/admin/walk-ins/create.blade.php', 'size_label');
$p->rep('Size {{ $gown->size }}', 'Sizes {{ $gown->size_label }} ({{ $gown->size_type_label }})');
$patches[] = $p;

foreach (['resources/views/reservations/index.blade.php', 'resources/views/admin/reservations/index.blade.php'] as $view) {
    $p = new Patch($view, '$reservation->size');
    $p->rep(
        '{{ $reservation->gown->gown_code }}',
        '{{ $reservation->gown->gown_code }}@if ($reservation->size)<br>Size {{ $reservation->size }}@endif'
    );
    $patches[] = $p;
}

$p = new Patch('resources/views/partials/reserve-form.blade.php', 'data-blocked-by-size');
$p->rep(
    'data-blocked="{{ json_encode($blockedDays) }}"',
    'data-blocked-by-size="{{ json_encode($blockedBySize) }}"'
);
$p->rep(
    "        <input type=\"hidden\" name=\"start_date\" value=\"{{ \$selected }}\" data-start-input>\n",
    <<<'EOT'
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

EOT
);
$patches[] = $p;

// =====================================================================================
// public/js/reservation.js
// =====================================================================================
$p = new Patch('public/js/reservation.js', 'blockedBySize');

$p->rep(
    "    var blocked = new Set(readList(root.dataset.blocked));\n",
    <<<'EOT'
    var blockedBySize = readMap(root.dataset.blockedBySize);
    var sizeInputs = root.querySelectorAll('[data-size-input]');
    var blocked = new Set();

EOT
);

$p->rep(
    "    function toTime(date) {\n",
    <<<'EOT'
    function readMap(text) {
        try {
            var map = JSON.parse(text || '{}');
            return map && typeof map === 'object' ? map : {};
        } catch (e) {
            return {};
        }
    }

    // The size picked right now (a hidden input when the gown has only one size)
    function currentSize() {
        var size = '';

        sizeInputs.forEach(function (input) {
            if (input.type === 'hidden' || input.checked) {
                size = input.value;
            }
        });

        return size;
    }

    // Use the blocked days that belong to the chosen size
    function loadBlocked() {
        blocked = new Set(blockedBySize[currentSize()] || []);
    }

    function toTime(date) {

EOT
);

$p->rep(
    "        [\n            ['Day 1 · Pick up', selected],",
    "        [\n            ['Size', null],\n            ['Day 1 · Pick up', selected],"
);
$p->rep(
    "            value.textContent = pretty(row[1]);",
    "            value.textContent = row[1] === null ? currentSize() : pretty(row[1]);"
);

$p->rep(
    "    // ---- Start\n",
    <<<'EOT'
    sizeInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            loadBlocked();

            // The chosen dates may be taken in the new size
            if (selected && !canStart(selected)) {
                selected = '';
                startInput.value = '';
            }

            render();
            showSummary();
        });
    });

    // ---- Start

EOT
);

$p->rep(
    "    var preset = root.dataset.selected;\n\n    if (preset && canStart(preset)) {",
    <<<'EOT'
    var preset = root.dataset.selected;

    loadBlocked();

    // A date chosen on the catalog may be taken in the default size: try the other sizes
    if (preset && !canStart(preset)) {
        var original = null;
        var found = false;

        sizeInputs.forEach(function (input) {
            if (input.type === 'radio' && input.checked) {
                original = input;
            }
        });

        sizeInputs.forEach(function (input) {
            if (found || input.type !== 'radio') {
                return;
            }

            input.checked = true;
            loadBlocked();
            found = canStart(preset);
        });

        if (!found && original) {
            original.checked = true;
            loadBlocked();
        }
    }

    if (preset && canStart(preset)) {
EOT
);
$patches[] = $p;

// =====================================================================================
// public/css/calendar.css
// =====================================================================================
$p = new Patch('public/css/calendar.css', '.size-option');
$p->text = rtrim($p->text) . "\n" . <<<'EOT'

/* Size picker (customer and walk-in) and size tick boxes (admin gown form) */
.size-options,
.size-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
}

.size-option {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 52px;
    margin: 0;
    padding: 8px 14px;
    border: 1px solid var(--black);
    font-weight: 600;
    cursor: pointer;
}

.size-option:has(input:checked) {
    background: var(--black);
    color: var(--white);
}

.size-types {
    display: grid;
    gap: 10px;
}

.size-types .check {
    align-items: flex-start;
}

EOT;
$patches[] = $p;

// =====================================================================================
// Everything matched: write the files
// =====================================================================================
echo "All edits matched. Writing files...\n";

foreach ($patches as $patch) {
    $patch->save();
}

echo "\nDone. Now run:  php artisan migrate\n";
