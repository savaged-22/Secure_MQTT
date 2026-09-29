<?php

namespace App\Services\Access;

use App\Models\AccessRule;
use App\Models\Turnstile;
use App\Models\User;
use Carbon\CarbonInterface;

class AccessPolicyService
{
    /**
     * Motivos de rechazo: turnstile_unavailable | user_inactive |
     * not_authorized_for_building | outside_schedule
     */
    public function evaluate(User $user, Turnstile $turnstile, CarbonInterface $at): AccessDecision
    {
        if (! $turnstile->isActive()) {
            return AccessDecision::deny('turnstile_unavailable');
        }

        // Salida libre: con un QR válido basta
        if ($turnstile->direction === 'out' && config('access.free_exit')) {
            return AccessDecision::grant('free_exit');
        }

        if (! $user->is_active) {
            return AccessDecision::deny('user_inactive');
        }

        // Reglas del edificio que aplican a este rol y programa
        $rules = AccessRule::where('building_id', $turnstile->building_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (AccessRule $rule) => ($rule->role === null || $rule->role === $user->role->value)
                && ($rule->program === null || $rule->program === $user->program));

        if ($rules->isEmpty()) {
            return AccessDecision::deny('not_authorized_for_building');
        }

        // Horario en la zona del campus
        $local = $at->copy()->setTimezone(config('access.timezone'));
        $day = $local->dayOfWeekIso;   // 1 = lunes ... 7 = domingo
        $time = $local->format('H:i');

        foreach ($rules as $rule) {
            if (in_array($day, $rule->days_of_week, true)
                && $time >= substr($rule->start_time, 0, 5)
                && $time <= substr($rule->end_time, 0, 5)) {
                return AccessDecision::grant();
            }
        }

        return AccessDecision::deny('outside_schedule');
    }
}
