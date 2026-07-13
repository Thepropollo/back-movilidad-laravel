<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Auth\Models\SystemLog;

class SystemLogListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $search = $request->query('search');
        $action = $request->query('action');

        $query = SystemLog::with(['user.role'])
            ->orderBy('id', 'desc');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($action) {
            $query->where('action', 'like', "%{$action}%");
        }

        $logs = $query->paginate(10);

        return response()->json($logs);
    }
}
