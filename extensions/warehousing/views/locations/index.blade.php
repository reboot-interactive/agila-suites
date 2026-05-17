@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Locations')

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Locations</h2>
        <div class="page-header-actions">
            <a class="btn" href="{{ route('ext.warehousing.locations.create') }}">Add Location</a>
        </div>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Default</th>
            <th>Sellable</th>
            <th>Sort Order</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($warehouses as $w)
            <tr>
                <td>{{ $w->name }}</td>
                <td>{{ $w->code }}</td>
                <td>
                    @if($w->is_default)
                        <span class="badge green">Default</span>
                    @endif
                </td>
                <td>
                    @if($w->is_sellable)
                        <span class="badge badge-green">Yes</span>
                    @else
                        <span class="badge badge-red">No</span>
                    @endif
                </td>
                <td>{{ $w->sort_order }}</td>
                <td class="d-flex gap-8 items-center">
                    <a class="btn small" href="{{ route('ext.warehousing.locations.edit', $w->id) }}">Edit</a>
                    @unless($w->is_default)
                    <form method="POST" action="{{ route('ext.warehousing.locations.destroy', $w->id) }}" data-confirm="Delete this location? This cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <button class="btn danger small" type="submit">Delete</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No locations yet. Create your first location to get started.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
