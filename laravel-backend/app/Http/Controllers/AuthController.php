<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $key = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many failed attempts. Try again in about '.RateLimiter::availableIn($key).' second(s).',
            ]);
        }

        if (! Auth::attempt([
            'email' => strtolower($credentials['email']),
            'password' => $credentials['password'],
            'status' => 'active',
        ])) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();

        return redirect()->intended(
            match (true) {
                $user->isAdministrator() => route('admin.dashboard'),
                $user->isDeliveryRider() => route('delivery.orders'),
                default => route('home'),
            }
        )->with('success', 'Welcome back!');
    }

    public function registerForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ], [
            'password.regex' => 'Password must contain at least one letter and one number.',
            'phone.regex' => 'Enter a valid contact number.',
        ]);

        $customerRole = Role::query()->where('name', 'Customer')->firstOrFail();
        $user = User::query()->create([
            'role_id' => $customerRole->id,
            'full_name' => $validated['full_name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
            'password_hash' => Hash::make($validated['password']),
            'status' => 'active',
            'created_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Welcome to TradeHub! Your account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been logged out.');
    }
}
