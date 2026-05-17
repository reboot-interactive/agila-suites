@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Locations / ' . (isset($warehouse) ? 'Edit' : 'Add'))

@php
    $isEdit = isset($warehouse);
@endphp

@section('content')
<div class="card">
    <div class="page-header">
        <h2>{{ $isEdit ? 'Edit Location — ' . $warehouse->name : 'Add Location' }}</h2>
        <div class="page-header-actions">
            <a class="btn secondary" href="{{ route('ext.warehousing.locations.index') }}">Back</a>
            <button class="btn" type="submit" form="location-form">{{ $isEdit ? 'Update' : 'Save Location' }}</button>
        </div>
    </div>

    <form id="location-form" method="POST"
        action="{{ $isEdit ? route('ext.warehousing.locations.update', $warehouse->id) : route('ext.warehousing.locations.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div>
                <label class="required">Name</label>
                <input class="input" name="name" value="{{ old('name', $isEdit ? $warehouse->name : '') }}" maxlength="128">
            </div>

            <div>
                <label class="required">Code</label>
                <input class="input" name="code" value="{{ old('code', $isEdit ? $warehouse->code : '') }}" maxlength="32" placeholder="e.g. MAIN, WH-01">
            </div>

            <div>
                <label>Sort Order</label>
                <input class="input" type="number" name="sort_order" value="{{ old('sort_order', $isEdit ? $warehouse->sort_order : 0) }}" min="0">
            </div>

            <div>
                <label>Default Location</label>
                <label class="d-flex items-center gap-8">
                    <input type="checkbox" name="is_default" value="1"
                        {{ old('is_default', $isEdit && $warehouse->is_default ? '1' : '') ? 'checked' : '' }}>
                    Set as the default warehouse location
                </label>
            </div>

            <div>
                <label>Sellable</label>
                <label class="d-flex items-center gap-8">
                    <input type="checkbox" name="is_sellable" value="1"
                        {{ old('is_sellable', $isEdit ? ($warehouse->is_sellable ? '1' : '') : '1') ? 'checked' : '' }}>
                    Stock in this location counts toward marketplace availability
                </label>
                <div class="text-xs text-secondary" style="margin-top:4px;">Uncheck for overseas storage, in-transit, or non-sellable locations.</div>
            </div>
        </div>
    </form>

    @if($isEdit)
        @unless($warehouse->is_default)
        <form id="delete-location-form" method="POST" action="{{ route('ext.warehousing.locations.destroy', $warehouse->id) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>

        <div class="mt-16 d-flex justify-start">
            <button class="btn danger" type="button"
                data-confirm="Delete this location? This cannot be undone." data-confirm-submit="delete-location-form">
                Delete
            </button>
        </div>
        @endunless
    @endif
</div>
@endsection
