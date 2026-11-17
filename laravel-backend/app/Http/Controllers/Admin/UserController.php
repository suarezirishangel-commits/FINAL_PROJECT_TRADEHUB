<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'roles' => Role::query()->orderBy('id')->get(),
            'users' => User::query()
                ->with('role')
                ->addSelect([
                    'order_count' => Transaction::query()
                        ->selectRaw('COUNT(DISTINCT reference)')
                        ->whereColumn('transactions.user_id', 'users.id'),
                    'spent' => Transaction::query()
                        ->selectRaw('COALESCE(SUM(total_amount), 0)')
                        ->whereColumn('transactions.user_id', 'users.id')
                        ->where('status', '<>', 'cancelled'),
                ])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'in:role,status'],
            'role_id' => ['required_if:action,role', 'integer', 'exists:roles,id'],
        ]);
        $user = User::query()->findOrFail($data['id']);

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', 'You cannot change your own role or status.');
        }

        if ($data['action'] === 'role') {
            $role = Role::query()->findOrFail($data['role_id']);
            if ($role->name !== 'Seller' && $user->products()->exists()) {
                return back()->with('error', 'Reassign this user’s products before changing the Seller role.');
            }

            $user->role_id = $data['role_id'];
            $user->save();

            return back()->with('success', 'User role updated.');
        }

        if ($user->role?->name === 'Seller'
            && $user->status === 'active'
            && $user->products()->where('is_active', true)->exists()) {
            return back()->with('error', 'Reassign or hide this seller’s active products before deactivation.');
        }

        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        return back()->with('success', 'User status updated.');
    }
}
