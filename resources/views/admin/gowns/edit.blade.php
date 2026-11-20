@extends('layouts.admin')

@section('title', 'Edit Gown')

@section('content')
    <div class="page-head">
        <h1>Edit {{ $gown->gown_code }}</h1>
    </div>

    @include('admin.gowns._form', [
        'action' => route('admin.gowns.update', $gown),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
@endsection
