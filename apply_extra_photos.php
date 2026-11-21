<?php
/*
 * Adds the "2 extra gown photos" feature to the existing project files.
 * Run from the project root (next to artisan):   php apply_extra_photos.php
 *
 * It checks every edit first and writes NOTHING unless all of them match.
 * Commit or back up your project before running it. Run it once.
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

        if (str_contains($this->text, $alreadyMarker)) {
            $this->skip = true;
        }
    }

    /** Replace one exact piece of text that must appear exactly $times times (default once). */
    public function rep(string $old, string $new, int $times = 1): void
    {
        if ($this->skip) {
            return;
        }

        $count = substr_count($this->text, $old);

        if ($count !== $times) {
            fail("{$this->path}: expected {$times} match(es) but found {$count} for:\n" . substr($old, 0, 160));
        }

        $this->text = str_replace($old, $new, $this->text);
    }

    /** Same, but fine when the text is not there at all (used to repair a known problem only if it is present). */
    public function repIfPresent(string $old, string $new): void
    {
        if ($this->skip) {
            return;
        }

        $count = substr_count($this->text, $old);

        if ($count > 1) {
            fail("{$this->path}: found {$count} matches for an optional fix:\n" . substr($old, 0, 160));
        }

        if ($count === 1) {
            $this->text = str_replace($old, $new, $this->text);
        }
    }

    public function append(string $new): void
    {
        if (! $this->skip) {
            $this->text = rtrim($this->text) . "\n" . $new;
        }
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

foreach ([
    'app/Models/GownPhoto.php',
    'database/migrations/2026_11_21_000010_create_gown_photos_table.php',
    'resources/views/partials/extra-photos.blade.php',
    'resources/views/admin/gowns/_extra-photos.blade.php',
] as $newFile) {
    if (! is_file(__DIR__ . '/' . $newFile)) {
        fail("New file missing: {$newFile}. Unzip the whole folder into the project root first.");
    }
}

$patches = [];

// ---------------------------------------------------------------- app/Models/Gown.php
$p = new Patch('app/Models/Gown.php', 'EXTRA_PHOTO_SLOTS');
$p->rep(
    "    public const SIZE_TYPES = [self::SIZE_TYPE_SINGLE, self::SIZE_TYPE_SEPARATE];\n",
    "    public const SIZE_TYPES = [self::SIZE_TYPE_SINGLE, self::SIZE_TYPE_SEPARATE];\n\n    /** Each gown has a main photo plus up to 2 extra photos, in position 1 and 2. */\n    public const EXTRA_PHOTO_SLOTS = [1, 2];\n"
);
$p->rep(
    "    public function sizes(): HasMany\n",
    <<<'EOT'
    public function photos(): HasMany
    {
        return $this->hasMany(GownPhoto::class)->orderBy('position');
    }

    public function sizes(): HasMany

EOT
);
$patches[] = $p;

// ---------------------------------------------------------------- app/Http/Requests/GownRequest.php
$p = new Patch('app/Http/Requests/GownRequest.php', 'extra_images');
$p->rep(
    "            'remove_image' => ['nullable', 'boolean'],\n",
    <<<'EOT'
            'remove_image' => ['nullable', 'boolean'],
            'extra_images' => ['nullable', 'array'],
            'extra_images.1' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'extra_images.2' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_extra' => ['nullable', 'array'],
            'remove_extra.1' => ['nullable', 'boolean'],
            'remove_extra.2' => ['nullable', 'boolean'],

EOT
);
$p->rep(
    "            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',\n",
    "            'image.mimes' => 'The photo must be a JPG, PNG, or WEBP file.',\n            'extra_images.*.max' => 'Each extra photo must not be larger than 4 MB.',\n            'extra_images.*.mimes' => 'Each extra photo must be a JPG, PNG, or WEBP file.',\n            'extra_images.*.image' => 'Each extra photo must be an image.',\n"
);
$p->rep(
    "    public function messages(): array\n",
    <<<'EOT'
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

EOT
);
$patches[] = $p;

// ---------------------------------------------------------------- app/Http/Controllers/Admin/GownController.php
$p = new Patch('app/Http/Controllers/Admin/GownController.php', 'syncExtraPhotos');

// store() and update() both start from the same validated data: keep the extra photo fields out of it
$p->rep(
    "except(['image', 'remove_image', 'sizes'])",
    "except(['image', 'remove_image', 'sizes', 'extra_images', 'remove_extra'])",
    2
);

// store(): upload the extras before saving, save them with the gown
$p->rep(
    "        try {\n            \$gown = DB::transaction(function () use (\$data, \$sizes) {\n                \$gown = Gown::create(\$data);\n                \$this->syncSizes(\$gown, \$sizes);\n",
    <<<'EOT'
        [$extraUploads, $extraErrors] = $this->uploadExtraPhotos($request);

        if ($extraErrors) {
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
            }

            return back()->withInput()->withErrors($extraErrors);
        }

        try {
            $gown = DB::transaction(function () use ($data, $sizes, $extraUploads) {
                $gown = Gown::create($data);
                $this->syncSizes($gown, $sizes);
                $this->syncExtraPhotos($gown, $extraUploads, []);

EOT
);
$p->rep(
    "            // Do not leave an orphan photo on Cloudinary if the database save fails\n            if (\$upload) {\n                \$this->cloudinary->delete(\$upload['public_id']);\n            }\n",
    "            // Do not leave an orphan photo on Cloudinary if the database save fails\n            if (\$upload) {\n                \$this->cloudinary->delete(\$upload['public_id']);\n            }\n\n            foreach (\$extraUploads as \$extra) {\n                \$this->cloudinary->delete(\$extra['public_id']);\n            }\n"
);

// update(): same idea, and remember the replaced photos so they can be removed once the save worked
$p->rep(
    "        } elseif (\$request->boolean('remove_image')) {\n            \$data['image_url'] = null;\n            \$data['image_public_id'] = null;\n        }\n",
    <<<'EOT'
        } elseif ($request->boolean('remove_image')) {
            $data['image_url'] = null;
            $data['image_public_id'] = null;
        }

        [$extraUploads, $extraErrors] = $this->uploadExtraPhotos($request);

        if ($extraErrors) {
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
            }

            return back()->withInput()->withErrors($extraErrors);
        }

        $removeExtras = array_values(array_filter(
            Gown::EXTRA_PHOTO_SLOTS,
            fn ($slot) => $request->boolean("remove_extra.{$slot}")
        ));
        $oldExtraCount = $gown->photos()->count();
        $replacedExtras = [];

EOT
);
$p->rep(
    "            DB::transaction(function () use (\$gown, \$data, \$sizes) {\n                \$gown->update(\$data);\n                \$this->syncSizes(\$gown, \$sizes);\n            });\n",
    <<<'EOT'
            DB::transaction(function () use ($gown, $data, $sizes, $extraUploads, $removeExtras, &$replacedExtras) {
                $gown->update($data);
                $this->syncSizes($gown, $sizes);
                $replacedExtras = $this->syncExtraPhotos($gown, $extraUploads, $removeExtras);
            });

EOT
);
$p->rep(
    "            if (\$upload) {\n                \$this->cloudinary->delete(\$upload['public_id']);\n            }\n\n            throw \$e;\n        }\n\n        // Sizes live",
    "            if (\$upload) {\n                \$this->cloudinary->delete(\$upload['public_id']);\n            }\n\n            foreach (\$extraUploads as \$extra) {\n                \$this->cloudinary->delete(\$extra['public_id']);\n            }\n\n            throw \$e;\n        }\n\n        // Sizes live"
);
$p->rep(
    "        // The database is saved, so it is now safe to remove the old photo\n",
    <<<'EOT'
        // Extra photos live in their own table, so the audit observer cannot see them either
        $newExtraCount = $gown->photos()->count();

        if ($extraUploads !== [] || $removeExtras !== []) {
            AuditLogger::record(
                'gown.updated',
                "Edited gown {$gown->gown_code} {$gown->name}: extra photos.",
                $gown,
                trim("{$gown->gown_code} {$gown->name}"),
                ['extra photos' => [(string) $oldExtraCount, (string) $newExtraCount]]
            );
        }

        // The database is saved, so it is now safe to remove the old photo
EOT
    . "\n"
);
$p->rep(
    "        return redirect()\n            ->route('admin.gowns.index')\n            ->with('success', \"Gown {\$gown->gown_code} was updated.\");",
    "        // Same for extra photos that were replaced or removed\n        foreach (\$replacedExtras as \$publicId) {\n            \$this->cloudinary->delete(\$publicId);\n        }\n\n        return redirect()\n            ->route('admin.gowns.index')\n            ->with('success', \"Gown {\$gown->gown_code} was updated.\");"
);

// helpers
$p->rep(
    "    private function uploadErrorMessage(Throwable \$e): string\n",
    <<<'EOT'
    /**
     * Uploads the extra photos picked on the form, before anything is saved.
     * If one fails, the ones already uploaded are removed again.
     *
     * @return array{0: array<int, array{url: string, public_id: string}>, 1: ?array<string, string>} [uploads by slot, errors]
     */
    private function uploadExtraPhotos(Request $request): array
    {
        $uploads = [];

        foreach (Gown::EXTRA_PHOTO_SLOTS as $slot) {
            $file = $request->file("extra_images.{$slot}");

            if (! $file) {
                continue;
            }

            try {
                $uploads[$slot] = $this->cloudinary->upload($file);
            } catch (Throwable $e) {
                report($e);

                foreach ($uploads as $done) {
                    $this->cloudinary->delete($done['public_id']);
                }

                return [[], ["extra_images.{$slot}" => $this->uploadErrorMessage($e)]];
            }
        }

        return [$uploads, null];
    }

    /**
     * Saves new, replaced and removed extra photos for a gown.
     * Returns the Cloudinary ids of the photos that are no longer used, to delete after the save worked.
     *
     * @param array<int, array{url: string, public_id: string}> $uploads new photos by slot
     * @param int[] $remove slots to empty
     * @return string[]
     */
    private function syncExtraPhotos(Gown $gown, array $uploads, array $remove): array
    {
        $existing = $gown->photos()->get()->keyBy('position');
        $unused = [];

        foreach (Gown::EXTRA_PHOTO_SLOTS as $slot) {
            $old = $existing->get($slot);

            if (isset($uploads[$slot])) {
                $values = ['image_url' => $uploads[$slot]['url'], 'image_public_id' => $uploads[$slot]['public_id']];

                if ($old) {
                    $unused[] = $old->image_public_id;
                    $old->update($values);
                } else {
                    $gown->photos()->create($values + ['position' => $slot]);
                }
            } elseif ($old && in_array($slot, $remove, true)) {
                $unused[] = $old->image_public_id;
                $old->delete();
            }
        }

        $gown->unsetRelation('photos');

        return array_values(array_filter($unused));
    }

    private function uploadErrorMessage(Throwable $e): string

EOT
);
$p->rep(
    "            \$gown->loadMissing('sizes');\n",
    "            \$gown->loadMissing('sizes', 'photos');\n"
);
$patches[] = $p;

// ---------------------------------------------------------------- app/Http/Controllers/CatalogController.php
$p = new Patch('app/Http/Controllers/CatalogController.php', "load('sizes', 'photos')");
$p->rep("        \$gown->load('sizes');\n", "        \$gown->load('sizes', 'photos');\n");
$patches[] = $p;

// ---------------------------------------------------------------- views
$p = new Patch('resources/views/catalog/show.blade.php', 'detail-media');
$p->rep(
    "        <div class=\"detail-image\">\n            @include('partials.photo-zoom', ['gown' => \$gown])\n        </div>\n",
    "        <div class=\"detail-media\">\n            <div class=\"detail-image\">\n                @include('partials.photo-zoom', ['gown' => \$gown])\n            </div>\n\n            @include('partials.extra-photos', ['gown' => \$gown])\n        </div>\n"
);
$patches[] = $p;

$p = new Patch('resources/views/admin/gowns/_form.blade.php', "admin.gowns._extra-photos");
// Repair: an earlier size update could leave the Silhouette field without its opening <div class="field">
$p->repIfPresent(
    "        </div>\n            <label for=\"silhouette\">Silhouette</label>",
    "        </div>\n\n        <div class=\"field\">\n            <label for=\"silhouette\">Silhouette</label>"
);
$p->rep(
    "    </div>\n\n    <div class=\"form-actions\">",
    "\n        @include('admin.gowns._extra-photos')\n    </div>\n\n    <div class=\"form-actions\">"
);
$patches[] = $p;

// ---------------------------------------------------------------- public/js/catalog.js  (the zoom view now serves several photos)
$p = new Patch('public/js/catalog.js', 'activeTrigger');
$p->rep(
    "    var trigger = document.querySelector('[data-zoom-src]');\n",
    "    var triggers = document.querySelectorAll('[data-zoom-src]');\n    var activeTrigger = null;\n"
);
$p->rep("    if (trigger && lightbox) {\n", "    if (triggers.length && lightbox) {\n");
$p->rep(
    "        var openLightbox = function () {\n",
    "        var openLightbox = function (trigger) {\n            activeTrigger = trigger;\n"
);
$p->rep("            trigger.focus();\n        };\n", "            if (activeTrigger) {\n                activeTrigger.focus();\n            }\n        };\n");
$p->rep(
    "        trigger.addEventListener('click', openLightbox);\n",
    "        triggers.forEach(function (trigger) {\n            trigger.addEventListener('click', function () {\n                openLightbox(trigger);\n            });\n        });\n"
);
$patches[] = $p;

// ---------------------------------------------------------------- public/js/admin.js
$p = new Patch('public/js/admin.js', 'data-extra-input');
$p->rep(
    "    // Stop double submits while a photo is uploading\n",
    <<<'EOT'
    // Preview and size check for the two extra photos
    document.querySelectorAll('[data-extra-input]').forEach(function (input) {
        var slot = input.closest('[data-extra-slot]');
        var extraPreview = slot.querySelector('[data-extra-preview]');
        var extraError = slot.querySelector('[data-extra-error]');

        input.addEventListener('change', function () {
            var file = input.files[0];

            extraError.hidden = true;
            extraPreview.hidden = true;
            extraPreview.removeAttribute('src');

            if (!file) {
                return;
            }

            if (file.size > MAX_BYTES) {
                input.value = '';
                extraError.hidden = false;
                return;
            }

            extraPreview.src = URL.createObjectURL(file);
            extraPreview.hidden = false;
        });
    });

    // Stop double submits while a photo is uploading

EOT
);
$patches[] = $p;

// ---------------------------------------------------------------- CSS
$p = new Patch('public/css/catalog.css', '.extra-photos');
$p->append(<<<'EOT'

/* Extra photos: two small thumbnails under the main photo (the main photo stays 600 x 800) */
.detail-media {
    width: 100%;
    min-width: 0;
}

.extra-photos {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}

.extra-photo {
    display: block;
    width: 120px;
    aspect-ratio: 3 / 4;
    padding: 0;
    border: 1px solid var(--black);
    background: none;
    cursor: zoom-in;
}

.extra-photo img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.extra-photo:hover {
    outline: 2px solid var(--black);
    outline-offset: 2px;
}
EOT
);
$patches[] = $p;

$p = new Patch('public/css/admin.css', '.extra-slots');
$p->append(<<<'EOT'

/* Extra photo slots on the gown form */
.extra-slots {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.extra-slot {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--black);
}

.extra-title {
    margin: 0;
    font-weight: 600;
}

.preview-small {
    max-width: 120px;
    max-height: 160px;
    margin-top: 4px;
}

@media (max-width: 640px) {
    .extra-slots {
        grid-template-columns: 1fr;
    }
}
EOT
);
$patches[] = $p;

echo "All edits matched. Writing files...\n";

foreach ($patches as $patch) {
    $patch->save();
}

echo "\nDone. Now run:  php artisan migrate\n";
