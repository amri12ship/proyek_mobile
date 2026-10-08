<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('profile', ['user' => auth()->user()->load('employee.department', 'employee.position')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        if ($user->employee !== null) {
            $user->employee->update(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
        }

        $this->audit->success('profile.update', $user, $user->employee, 'Profil diperbarui');

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.'])->with('error', 'Password saat ini salah.');
        }

        $user->update(['password' => $data['password']]);

        $this->audit->success('profile.password', $user, $user->employee, 'Password diubah');

        return back()->with('success', 'Password berhasil diubah.');
    }
}
