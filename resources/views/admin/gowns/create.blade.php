@extends('layouts.admin')

@section('title', 'Add Gown')

@section('content')
    <div class="page-head">
        <h1>Add gown</h1>
    </div>

    @include('admin.gowns._form', [
        'action' => route('admin.gowns.store'),
        'method' => 'POST',
        'submitLabel' => 'Add gown',
    ])
@endsection
