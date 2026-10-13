<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlatformPaymentSettings
{
    public function isPlatformAdmin($user): bool
    {
        if (!$user || !isset($user->email)) return false;
        return in_array(strtolower((string) $user->email), config('platform.admin_emails', []), true);
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        try {
            $stored = DB::table('platform_settings')->where('key', $key)->value('value');
        } catch (Throwable) {
            return $fallback;
        }
        if (!is_string($stored) || $stored === '') return $fallback;
        try { return Crypt::decryptString($stored); } catch (Throwable) { return $fallback; }
    }

    public function put(string $key, string|int|float|bool $value, ?int $actorId = null): void
    {
        $existing = DB::table('platform_settings')->where('key', $key)->first();
        DB::table('platform_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => Crypt::encryptString(is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                'updated_by' => $actorId,
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function clear(string $key, ?int $actorId = null): void
    {
        DB::table('platform_settings')->where('key', $key)->delete();
    }

    public function plans(): array
    {
        $plans = config('billing.plans', []);
        foreach ($plans as $key => &$plan) {
            $plan['stripe_monthly_minor'] = (int) $this->get('plan_'.$key.'_stripe_monthly_minor', $plan['stripe_monthly_minor']);
            $plan['sslcommerz_monthly_bdt'] = (float) $this->get('plan_'.$key.'_sslcommerz_monthly_bdt', $plan['sslcommerz_monthly_bdt']);
        }
        unset($plan);
        return $plans;
    }
}
