<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    /**
     * Search users by name or email for async-select filters.
     *
     * GET /app/users/search?search=<query>
     *
     * Returns: { data: [ { label, value }, ... ] }
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $query = User::query()->select(['id', 'name', 'email']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->limit(10)->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'label' => $user->name,
                'value' => (string) $user->id,
            ])->all(),
        ]);
    }
}
