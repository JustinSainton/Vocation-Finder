<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The account a demo is given from.
 *
 * The account is an adult's with a generic trial, because the person holding
 * it is the one giving the demo: a minor's account would stop at parent
 * consent before the coach, which is the right behaviour for a student and the
 * wrong one for a walkthrough. The persona in the answers is still seventeen.
 */
class CreateDemoUser extends Command
{
    protected $signature = 'demo:user
        {email : The demo account email}
        {--name=Maya (Demo) : Display name}
        {--password= : Password; generated and printed when omitted}
        {--trial-days=30 : Generic trial length, so the coach is reachable}';

    protected $description = 'Create or refresh an account that sees the assessment pre-filled in demo mode';

    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $user = User::firstOrNew(['email' => $this->argument('email')]);
        $user->fill([
            'name' => $this->option('name'),
            'password' => $password,
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $user->role = DemoMode::ROLE;
        $user->trial_ends_at = now()->addDays((int) $this->option('trial-days'));
        $user->email_verified_at ??= now();
        $user->save();

        $this->info("Demo account ready: {$user->email}");

        if (! $this->option('password')) {
            $this->line("Password: {$password}");
        }

        if (! config('vocation.demo.enabled')) {
            $this->warn('VOCATION_DEMO_MODE is off here, so this account will see an empty assessment until it is turned on.');
        }

        return self::SUCCESS;
    }
}
