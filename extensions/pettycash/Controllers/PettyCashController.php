<?php

namespace Extensions\pettycash\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Extensions\pettycash\Models\PettyCashCategory;
use Extensions\pettycash\Models\PettyCashTransaction;
use Extensions\pettycash\Models\PettyCashUserRole;
use Illuminate\Http\Request;

class PettyCashController extends Controller
{
    /**
     * Get the petty cash role for the authenticated user.
     * Returns 'admin' or 'staff' (default if no row exists).
     */
    private function getUserRole(): string
    {
        $userId = auth()->id();
        $role = PettyCashUserRole::where('user_id', $userId)->value('role');

        return $role ?? 'staff';
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $userRole = $this->getUserRole();
        $isAdmin = $userRole === 'admin';
        $categories = PettyCashCategory::active()->orderBy('sort_order')->get();

        $query = PettyCashTransaction::with('user', 'creator')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        // Filters
        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($isAdmin && $request->filled('staff')) {
            $query->where('user_id', (int) $request->staff);
        }

        $transactions = $query->paginate(50)->appends($request->query());

        // Balance cards
        $balanceQuery = PettyCashTransaction::selectRaw(
            'user_id,
             SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) as total_credits,
             SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expenses'
        )->groupBy('user_id');

        if (!$isAdmin) {
            $balanceQuery->where('user_id', $user->id);
        }

        $staffBalances = $balanceQuery->get()->keyBy('user_id');

        // Ensure current user always has a balance entry (even if no transactions)
        if (!$isAdmin && !$staffBalances->has($user->id)) {
            $staffBalances->put($user->id, (object) [
                'user_id'        => $user->id,
                'total_credits'  => 0,
                'total_expenses' => 0,
            ]);
        }

        // Load user names for balance cards
        $userIds = $staffBalances->keys()->toArray();
        if (!empty($userIds)) {
            $users = User::whereIn('id', $userIds)->pluck('name', 'id');
            foreach ($staffBalances as $sb) {
                $sb->user_name = $users[$sb->user_id] ?? 'Unknown';
            }
        }

        $staffList = $isAdmin ? User::orderBy('name')->get(['id', 'name']) : collect();

        return view('ext-pettycash::index', compact(
            'transactions', 'categories', 'staffBalances',
            'staffList', 'userRole'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $userRole = $this->getUserRole();
        $isAdmin = $userRole === 'admin';

        $rules = [
            'type'             => 'required|in:credit,expense',
            'amount'           => 'required|numeric|min:0.01|max:9999999999.99',
            'category'         => 'nullable|string|max:128',
            'description'      => 'nullable|string|max:1000',
            'transaction_date' => 'required|date',
            'notes'            => 'nullable|string|max:2000',
        ];

        if ($request->type === 'credit') {
            if (!$isAdmin) {
                abort(403, 'You do not have permission to add credits.');
            }
            $rules['user_id'] = 'required|exists:users,id';
        }

        $request->validate($rules);

        PettyCashTransaction::create([
            'user_id'          => $request->type === 'credit' ? (int) $request->user_id : $user->id,
            'type'             => $request->type,
            'amount'           => $request->amount,
            'category'         => $request->type === 'expense' ? $request->category : null,
            'description'      => $request->description,
            'transaction_date' => $request->transaction_date,
            'created_by'       => $user->id,
            'notes'            => $request->notes,
        ]);

        return redirect()->route('ext.pettycash.index')->with('status', 'Transaction added.');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $userRole = $this->getUserRole();
        $isAdmin = $userRole === 'admin';
        $txn = PettyCashTransaction::findOrFail($id);

        // Staff can only edit own expenses
        if (!$isAdmin && ($txn->user_id !== $user->id || $txn->type === 'credit')) {
            abort(403);
        }

        $rules = [
            'amount'           => 'required|numeric|min:0.01|max:9999999999.99',
            'category'         => 'nullable|string|max:128',
            'description'      => 'nullable|string|max:1000',
            'transaction_date' => 'required|date',
            'notes'            => 'nullable|string|max:2000',
        ];

        // Admin editing a credit can change the staff member
        if ($isAdmin && $txn->type === 'credit') {
            $rules['user_id'] = 'required|exists:users,id';
        }

        $request->validate($rules);

        $txn->update([
            'amount'           => $request->amount,
            'category'         => $txn->type === 'expense' ? $request->category : null,
            'description'      => $request->description,
            'transaction_date' => $request->transaction_date,
            'notes'            => $request->notes,
            'user_id'          => ($isAdmin && $txn->type === 'credit' && $request->filled('user_id'))
                                    ? (int) $request->user_id
                                    : $txn->user_id,
        ]);

        return redirect()->route('ext.pettycash.index')->with('status', 'Transaction updated.');
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $userRole = $this->getUserRole();
        $isAdmin = $userRole === 'admin';
        $txn = PettyCashTransaction::findOrFail($id);

        if (!$isAdmin && ($txn->user_id !== $user->id || $txn->type === 'credit')) {
            abort(403);
        }

        $txn->delete();

        return redirect()->route('ext.pettycash.index')->with('status', 'Transaction deleted.');
    }
}
