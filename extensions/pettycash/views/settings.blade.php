@extends('layouts.app')
@section('breadcrumb')
    <a href="{{ route('ext.pettycash.index') }}">Petty Cash</a> / Settings
@endsection

@section('content')
<div class="page-header">
    <h2>Petty Cash Settings</h2>
</div>

{{-- User Roles --}}
<div class="card mb-24">
    <div class="card-header">
        <h3>User Roles</h3>
        <p class="text-sm text-secondary mt-4">Assign petty cash roles. Admins can add credits, view all staff, and manage all transactions. Staff can only add and manage their own expenses.</p>
    </div>
    <form method="POST" action="{{ route('ext.pettycash.settings.roles') }}">
        @csrf
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th style="width:200px;">Role</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>
                                <select name="roles[{{ $u->id }}]" class="input">
                                    <option value="staff" {{ ($roleMap[$u->id] ?? 'staff') === 'staff' ? 'selected' : '' }}>Staff</option>
                                    <option value="admin" {{ ($roleMap[$u->id] ?? 'staff') === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-16" style="text-align:right;">
            <button type="submit" class="btn">Save Roles</button>
        </div>
    </form>
</div>

{{-- Categories --}}
<div class="card mb-24">
    <div class="card-header">
        <h3>Categories</h3>
        <p class="text-sm text-secondary mt-4">Manage expense categories available in petty cash transactions.</p>
    </div>

    @if($categories->isNotEmpty())
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:100px;">Sort Order</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $cat)
                    <tr>
                        <form method="POST" action="{{ route('ext.pettycash.settings.categories.update', $cat->id) }}">
                            @csrf
                            @method('PUT')
                            <td>
                                <input type="text" name="name" value="{{ $cat->name }}" class="input" required maxlength="128">
                            </td>
                            <td>
                                <input type="number" name="sort_order" value="{{ $cat->sort_order }}" class="input" min="0" max="9999">
                            </td>
                            <td>
                                <select name="status" class="input">
                                    <option value="1" {{ $cat->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$cat->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </td>
                            <td>
                                <div class="d-flex gap-8">
                                    <button type="submit" class="btn small">Update</button>
                        </form>
                                    <form method="POST" action="{{ route('ext.pettycash.settings.categories.destroy', $cat->id) }}" data-confirm="Delete category '{{ e($cat->name) }}'?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn small secondary">Delete</button>
                                    </form>
                                </div>
                            </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <p class="text-secondary p-16">No categories yet. Add one below.</p>
    @endif

    {{-- Add Category Form --}}
    <div class="mt-16 p-16" style="border-top:1px solid var(--border);">
        <h4 class="mb-12">Add Category</h4>
        <form method="POST" action="{{ route('ext.pettycash.settings.categories.store') }}" class="d-flex gap-12 flex-wrap items-end">
            @csrf
            <div>
                <label class="text-xs text-secondary">Name</label>
                <input type="text" name="name" class="input" required maxlength="128" placeholder="Category name">
            </div>
            <div>
                <label class="text-xs text-secondary">Sort Order</label>
                <input type="number" name="sort_order" class="input" value="0" min="0" max="9999" style="width:80px;">
            </div>
            <div>
                <label class="text-xs text-secondary">Status</label>
                <select name="status" class="input">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn">Add Category</button>
            </div>
        </form>
    </div>
</div>
@endsection
