<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GownRequest;
use App\Models\Gown;
use App\Services\CloudinaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        return view('admin.gowns.create', $this->formData($gown));
    }

    public function store(GownRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);
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

        try {
            $gown = Gown::create($data);
        } catch (Throwable $e) {
            // Do not leave an orphan photo on Cloudinary if the database save fails
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
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
        $data = $request->safe()->except(['image', 'remove_image']);
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

        try {
            $gown->update($data);
        } catch (Throwable $e) {
            if ($upload) {
                $this->cloudinary->delete($upload['public_id']);
            }

            throw $e;
        }

        // The database is saved, so it is now safe to remove the old photo
        if ($oldPublicId && array_key_exists('image_public_id', $data)) {
            $this->cloudinary->delete($oldPublicId);
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
        return [
            'gown' => $gown,
            'sizes' => Gown::SIZES,
            'silhouettes' => Gown::silhouettes(),
            'statuses' => Gown::STATUSES,
        ];
    }
}
