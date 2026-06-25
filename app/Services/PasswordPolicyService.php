<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyService
{
    public const MIN_LENGTH     = 10;
    public const HISTORY_COUNT  = 5;
    public const EXPIRY_DAYS    = 90;   // applies to admin + supervisor
    private const ADMIN_ROLES   = ['admin', 'supervisor'];

    /**
     * Validate a new password against complexity and history rules.
     * Returns an array of error strings (empty = valid).
     */
    public function validate(User $user, string $newPassword): array
    {
        $errors = [];

        if (strlen($newPassword) < self::MIN_LENGTH) {
            $errors[] = 'Mínimo ' . self::MIN_LENGTH . ' caracteres';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Al menos una letra mayúscula';
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = 'Al menos una letra minúscula';
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Al menos un número';
        }
        if (!preg_match('/[\W_]/', $newPassword)) {
            $errors[] = 'Al menos un carácter especial (!@#$%^&*...)';
        }

        // History check
        $history = $user->passwordHistories()->latest()->take(self::HISTORY_COUNT)->get();
        foreach ($history as $record) {
            if (Hash::check($newPassword, $record->password)) {
                $errors[] = 'No puede reutilizar las últimas ' . self::HISTORY_COUNT . ' contraseñas';
                break;
            }
        }

        return $errors;
    }

    /**
     * Record a successful password change and update expiration.
     */
    public function recordChange(User $user, string $hashedPassword): void
    {
        // Save to history
        $user->passwordHistories()->create(['password' => $hashedPassword]);

        // Prune old records beyond HISTORY_COUNT
        $old = $user->passwordHistories()->latest()->skip(self::HISTORY_COUNT)->pluck('id');
        if ($old->isNotEmpty()) {
            $user->passwordHistories()->whereIn('id', $old)->delete();
        }

        $isAdminRole = in_array($user->role?->name, self::ADMIN_ROLES);

        $user->update([
            'password_changed_at' => now(),
            'password_expires_at' => $isAdminRole ? now()->addDays(self::EXPIRY_DAYS) : null,
        ]);
    }
}
