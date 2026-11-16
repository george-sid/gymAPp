<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Gym-wide settings with defaults from config/gym.php, cached until changed.
 */
class Settings
{
    private const string CACHE_KEY = 'gym-settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    public function get(string $key): mixed
    {
        $all = $this->all();

        if (! array_key_exists($key, $all)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        return $all[$key];
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resolved ??= [
            ...config('gym.defaults'),
            ...Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $unknown = array_diff(array_keys($values), array_keys(config('gym.defaults')));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown settings: '.implode(', ', $unknown));
        }

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
