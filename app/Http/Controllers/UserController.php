<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = User::query();
        $perPage = $this->resolvePerPage($request, $query);
        $users = $query->paginate($perPage)->withQueryString();

        return view($this->viewName('users.index'), compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view($this->viewName('users.create'), [
            'roles' => Role::cases(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
        ]);

        User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
        ]);

        return redirect()->route('users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        return view($this->viewName('users.edit'), [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
        ]);

        $user->update([
            'role' => $request->input('role'),
        ]);

        return redirect()->route('users.index');
    }
}
