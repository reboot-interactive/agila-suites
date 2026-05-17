<?php

namespace Extensions\pettycash\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Extensions\pettycash\Models\PettyCashCategory;
use Extensions\pettycash\Models\PettyCashUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettyCashSettingsController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name']);

        $roleMap = PettyCashUserRole::pluck('role', 'user_id');

        $categories = PettyCashCategory::orderBy('sort_order')->get();

        return view('ext-pettycash::settings', compact('users', 'roleMap', 'categories'));
    }

    public function updateRoles(Request $request)
    {
        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'in:admin,staff',
        ]);

        $roles = $request->input('roles', []);

        DB::transaction(function () use ($roles) {
            foreach ($roles as $userId => $role) {
                // Validate user exists
                if (!User::where('id', (int) $userId)->exists()) {
                    continue;
                }

                if ($role === 'staff') {
                    // Staff is the default, so remove the row if it exists
                    PettyCashUserRole::where('user_id', (int) $userId)->delete();
                } else {
                    PettyCashUserRole::updateOrCreate(
                        ['user_id' => (int) $userId],
                        ['role' => $role]
                    );
                }
            }
        });

        return redirect()->route('ext.pettycash.settings')->with('status', 'User roles updated.');
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:128|unique:petty_cash_categories,name',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'status'     => 'nullable|boolean',
        ]);

        PettyCashCategory::create([
            'name'       => $request->name,
            'sort_order' => $request->input('sort_order', 0),
            'status'     => $request->boolean('status', true),
        ]);

        return redirect()->route('ext.pettycash.settings')->with('status', 'Category added.');
    }

    public function updateCategory(Request $request, $id)
    {
        $category = PettyCashCategory::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:128|unique:petty_cash_categories,name,' . $category->id,
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'status'     => 'nullable|boolean',
        ]);

        $category->update([
            'name'       => $request->name,
            'sort_order' => $request->input('sort_order', 0),
            'status'     => $request->boolean('status', true),
        ]);

        return redirect()->route('ext.pettycash.settings')->with('status', 'Category updated.');
    }

    public function destroyCategory($id)
    {
        $category = PettyCashCategory::findOrFail($id);
        $category->delete();

        return redirect()->route('ext.pettycash.settings')->with('status', 'Category deleted.');
    }
}
