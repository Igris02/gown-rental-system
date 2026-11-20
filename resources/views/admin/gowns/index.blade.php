@extends('layouts.admin')

@section('title', 'Gowns')

@section('content')
    <div class="page-head">
        <h1>Gowns</h1>
        <a href="{{ route('admin.gowns.create') }}" class="btn">Add gown</a>
    </div>

    <form method="GET" action="{{ route('admin.gowns.index') }}" class="filters">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search name, design, color, or code"
            aria-label="Search gowns"
        >

        <select name="silhouette" aria-label="Filter by silhouette">
            <option value="">All silhouettes</option>
            @foreach ($silhouettes as $silhouette)
                <option value="{{ $silhouette }}" @selected(request('silhouette') === $silhouette)>{{ $silhouette }}</option>
            @endforeach
        </select>

        <select name="color" aria-label="Filter by color">
            <option value="">All colors</option>
            @foreach ($colors as $color)
                <option value="{{ $color }}" @selected(request('color') === $color)>{{ $color }}</option>
            @endforeach
        </select>

        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-outline">Filter</button>

        @if (request()->hasAny(['search', 'silhouette', 'color', 'status']))
            <a href="{{ route('admin.gowns.index') }}">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Design</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>Silhouette</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($gowns as $gown)
                    <tr>
                        <td>
                            @if ($gown->thumbnail_url)
                                <img src="{{ $gown->thumbnail_url }}" alt="{{ $gown->name }}" class="thumb" loading="lazy">
                            @else
                                <span class="thumb thumb-empty">No photo</span>
                            @endif
                        </td>
                        <td>{{ $gown->gown_code }}</td>
                        <td>{{ $gown->name }}</td>
                        <td>{{ $gown->design }}</td>
                        <td>{{ $gown->color }}</td>
                        <td>{{ $gown->size }}</td>
                        <td>{{ $gown->silhouette }}</td>
                        <td>{{ $gown->formatted_price }}</td>
                        <td><span class="badge badge-{{ $gown->status }}">{{ ucfirst($gown->status) }}</span></td>
                        <td class="actions">
                            <a href="{{ route('admin.gowns.edit', $gown) }}">Edit</a>

                            @if (auth()->user()->role === 'admin')
                                <form
                                    method="POST"
                                    action="{{ route('admin.gowns.destroy', $gown) }}"
                                    data-confirm="Delete {{ $gown->gown_code }} ({{ $gown->name }})? It will be removed from the catalog."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-btn">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="empty">No gowns found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($gowns->hasPages())
        <div class="pager">
            @if ($gowns->onFirstPage())
                <span class="pager-off">Previous</span>
            @else
                <a href="{{ $gowns->previousPageUrl() }}">Previous</a>
            @endif

            <span>Page {{ $gowns->currentPage() }} of {{ $gowns->lastPage() }}</span>

            @if ($gowns->hasMorePages())
                <a href="{{ $gowns->nextPageUrl() }}">Next</a>
            @else
                <span class="pager-off">Next</span>
            @endif
        </div>
    @endif
@endsection
