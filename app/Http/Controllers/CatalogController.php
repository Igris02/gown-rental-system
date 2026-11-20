<?php

namespace App\Http\Controllers;

use App\Models\Gown;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const SORTS = ['newest', 'price_low', 'price_high', 'name'];

    public function __construct(private ReservationService $reservations)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'color', 'size', 'silhouette']);

        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? $request->query('sort')
            : 'newest';

        // Optional: only gowns that are free for all 3 days of a rental starting on this date
        $pickup = ReservationService::validPickupOrNull($request->query('date'));

        // Customers only ever see gowns marked "available"
        $query = Gown::query()
            ->available()
            ->filter($filters)
            ->when($pickup, fn ($q) => $q->freeBetween($pickup, ReservationService::endDate($pickup)));

        match ($sort) {
            'price_low' => $query->orderBy('rental_price')->orderBy('id'),
            'price_high' => $query->orderByDesc('rental_price')->orderBy('id'),
            'name' => $query->orderBy('name'),
            default => $query->latest('id'),
        };

        $gowns = $query->paginate(12)->withQueryString();

        return view('catalog.index', [
            'gowns' => $gowns,
            'sort' => $sort,
            'pickup' => $pickup,
            'pickupEnd' => $pickup ? ReservationService::endDate($pickup) : null,
            'minDate' => ReservationService::earliestStart(),
            'maxDate' => ReservationService::latestStart(),
            'hasFilters' => collect($filters)->filter()->isNotEmpty() || $sort !== 'newest' || $pickup !== null,
            'colors' => Gown::query()->available()->distinct()->orderBy('color')->pluck('color'),
            'sizes' => Gown::SIZES,
            'silhouettes' => Gown::silhouettes(availableOnly: true),
        ]);
    }

    public function show(Request $request, Gown $gown): View
    {
        // Gowns in maintenance or retired are hidden from customers
        abort_unless($gown->status === Gown::STATUS_AVAILABLE, 404);

        // Pre-select a date when the customer came from the catalog's pick-up date filter,
        // or when the form is shown again after an error
        $selected = ReservationService::validPickupOrNull(old('start_date', $request->query('date'))) ?? '';

        return view('catalog.show', [
            'gown' => $gown,
            'blockedDays' => $this->reservations->blockedDays($gown),
            'minDate' => ReservationService::earliestStart(),
            'maxDate' => ReservationService::latestStart(),
            'selected' => $selected,
        ]);
    }
}
