<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GownRequest;
use App\Models\Gown;
use App\Services\AuditLogger;
use App\Services\CloudinaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class GownController extends Controller
{
    public function __construct(private CloudinaryService $cloudinary)
    {
    }

    public function index(Request $request): View
    {
        $gowns = Gown::query()
            ->with('sizes')
            ->filter($request->only(['search', 'silhouette', 'color']))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.gowns.index', [
            'gowns' => $gowns,
            'silhouettes' => Gown::silhouettes(),
            'statuses' => Gown::STATUSES,
            // Every color currently used by a gown, for the color filter
            'colors' => Gown::query()->distinct()->orderBy('color')->pluck('color'),
        ]);
    }

    public function create(): View
    {
        // Suggest the next code, such as G-011. The admin can still change it.
        $next = (Gown::withTrashed()->max('id') ?? 0) + 1;

        $gown = new Gown([
            'gown_code' => sprintf('A-%03d', $next),
            'status' => Gown::STATUS_AVAILABLE,
            'size_type' => Gown::SIZE_TYPE_SINGLE,
        ]);

        return view('admin.gowns.create', $this->formData($gown));
    }

    public function store(GownRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image', 'sizes', 'extra_images', 'remove_extra']);
        $sizes = $request->validated('sizes');
        $upload = null;

        if ($request->hasFile('image')) {
            try {
                $upload = $this->cloudinary->upload($request->file('image'));
            } catch (Throwable $e) {
                report($e);

                return back()->withInput()->withErrors([
                    'image' => $this->uploadErrorMessage($e),
                ]);
            }

            $data['image_url'] = $upload['url'];
            $data['image_public_id'] = $upload['public_id'];
        }

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

                return $gown;
            });
        } catch (Throwable $e) {
            // Do not leave an orphan photo on Cloudinary if the database save fails
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
            }

            foreach ($extraUploads as $extra) {
                $this->cloudinary->delete($extra['public_id']);
            }

            throw $e;
        }

        return redirect()
            ->route('admin.gowns.index')
            ->with('success', "Gown {$gown->gown_code} was added.");
    }

    public function edit(Gown $gown): View
    {
        return view('admin.gowns.edit', $this->formData($gown));
    }

    public function update(GownRequest $request, Gown $gown): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image', 'sizes', 'extra_images', 'remove_extra']);
        $sizes = $request->validated('sizes');
        $oldSizes = $gown->sizeList();

        // Check this first, so a photo is never uploaded for a change that gets refused
        if ($problem = $this->sizeProblem($gown, $request->validated('size_type'), $sizes)) {
            return back()->withInput()->withErrors(['sizes' => $problem]);
        }

        $oldPublicId = $gown->image_public_id;
        $upload = null;

        if ($request->hasFile('image')) {
            try {
                $upload = $this->cloudinary->upload($request->file('image'));
            } catch (Throwable $e) {
                report($e);

                return back()->withInput()->withErrors([
                    'image' => $this->uploadErrorMessage($e),
                ]);
            }

            $data['image_url'] = $upload['url'];
            $data['image_public_id'] = $upload['public_id'];
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

        try {
            DB::transaction(function () use ($gown, $data, $sizes, $extraUploads, $removeExtras, &$replacedExtras) {
                $gown->update($data);
                $this->syncSizes($gown, $sizes);
                $replacedExtras = $this->syncExtraPhotos($gown, $extraUploads, $removeExtras);
            });
        } catch (Throwable $e) {
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
            }

            foreach ($extraUploads as $extra) {
                $this->cloudinary->delete($extra['public_id']);
            }

            throw $e;
        }

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
        if ($oldPublicId && array_key_exists('image_public_id', $data)) {
            $this->cloudinary->delete($oldPublicId);
        }

        // Same for extra photos that were replaced or removed
        foreach ($replacedExtras as $publicId) {
            $this->cloudinary->delete($publicId);
        }

        return redirect()
            ->route('admin.gowns.index')
            ->with('success', "Gown {$gown->gown_code} was updated.");
    }

    public function destroy(Gown $gown): RedirectResponse
    {
        // A gown with open reservations (pending, confirmed, or picked up) cannot be removed
        if ($gown->reservations()->active()->exists()) {
            return redirect()
                ->route('admin.gowns.index')
                ->with('error', "Gown {$gown->gown_code} still has open reservations. Complete or cancel them first, or set the gown to Maintenance instead.");
        }

        // Soft delete: the gown disappears from the catalog but stays in old reservation
        // and audit records, so its photo is kept on Cloudinary on purpose.
        $gown->delete();

        return redirect()
            ->route('admin.gowns.index')
            ->with('success', "Gown {$gown->gown_code} was deleted.");
    }

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
    {
        $message = 'The photo could not be uploaded. Check your Cloudinary settings and try again.';

        // While developing (APP_DEBUG=true), show the real reason so problems are easy to find
        if (config('app.debug')) {
            $message .= ' [' . class_basename($e) . ': ' . $e->getMessage() . ']';
        }

        return $message;
    }

    private function formData(Gown $gown): array
    {
        if ($gown->exists) {
            $gown->loadMissing('sizes', 'photos');
        }

        return [
            'gown' => $gown,
            'sizes' => Gown::SIZES,
            'silhouettes' => Gown::silhouettes(),
            'statuses' => Gown::STATUSES,
        ];
    }
}
