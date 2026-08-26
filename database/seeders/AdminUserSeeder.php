<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if admin already exists
        if (User::where('role', UserRole::Admin)->exists()) {
            $this->command->info('Admin-Benutzer existiert bereits – übersprungen.');
            return;
        }

        $password = Str::random(16);

        // Die Adresse ergibt sich aus APP_URL, lässt sich aber über
        // ADMIN_EMAIL vorgeben – etwa wenn Post an eine andere Domain soll.
        $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.org';
        $email = env('ADMIN_EMAIL', 'admin@' . $host);

        $user = User::create([
            'name'     => 'Administrator',
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => UserRole::Admin,
            'active'   => true,
        ]);

        $this->command->newLine();
        $this->command->info('✓ Admin-Konto angelegt:');
        $this->command->table(
            ['Feld', 'Wert'],
            [
                ['E-Mail',    $user->email],
                ['Passwort',  $password],
                ['Rolle',     $user->role->label()],
            ]
        );
        $this->command->warn('  → Bitte das Passwort nach dem ersten Login unter "Mein Profil" ändern!');
        $this->command->newLine();
    }
}
