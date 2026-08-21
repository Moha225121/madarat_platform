<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\JobSeekerProfile;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(string $accountType = 'job-seeker'): Response
    {
        return Inertia::render('Auth/Register', [
            'accountType' => $accountType,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'account_type' => 'nullable|in:job-seeker,employer,trainer,training-company',
            'role' => 'nullable|in:job_seeker,employer,training_provider',
            'phone' => 'nullable|string|max:30|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $accountType = $request->string('account_type')->toString() ?: match ($request->string('role')->toString()) {
            'employer' => 'employer',
            'training_provider' => 'training-company',
            default => 'job-seeker',
        };
        $role = match ($accountType) {
            'employer' => 'employer',
            'trainer', 'training-company' => 'training_provider',
            default => 'job_seeker',
        };

        $user = DB::transaction(function () use ($request, $accountType, $role): User {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $role,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            if ($user->role === 'job_seeker') {
                JobSeekerProfile::create(['user_id' => $user->id]);
            } elseif ($user->role === 'employer') {
                CompanyProfile::create([
                    'user_id' => $user->id,
                    'company_name' => $user->name,
                    'verification_status' => 'unverified',
                ]);
            } else {
                TrainingProviderProfile::create([
                    'user_id' => $user->id,
                    'provider_type' => $accountType === 'trainer' ? 'trainer' : 'company',
                    'display_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'verification_status' => 'incomplete',
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
