<?php

namespace Wingman\Core\App;

use Wingman\Config\Globals;

class RateLimiter {
    public function __construct(
        private int $maxRequests = 60,
        private int $windowSeconds = 60
    ) {
        if (!is_dir(Globals::getDir('RATE_LIMITS')))
            mkdir(Globals::getDir('RATE_LIMITS'), 0755, true);
    }

    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';
    }

    public function isAllowed(): bool {
        $identifier = $this->getClientIp();
        $file = Globals::getDir('RATE_LIMITS') . '/' . md5($identifier) . '.json';
        $now = time();
        $data = ['count' => 0, 'reset_at' => $now + $this->windowSeconds];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($now > $data['reset_at']) {
                // Window expired, reset
                $data = ['count' => 0, 'reset_at' => $now + $this->windowSeconds];
            }
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['count'] <= $this->maxRequests;
    }

    public function getReadableWindow(): string
    {
        if ($this->windowSeconds < 60)
        {
            $seconds = $this->windowSeconds === 1 ? 'second' : 'seconds';
            return "{$this->windowSeconds} {$seconds}";
        }

        if ($this->windowSeconds < 3600)
        {
            $minutes = (int) ($this->windowSeconds / 60);
            $seconds = $minutes === 1 ? 'minute' : 'minutes';
            return "{$minutes} {$seconds}";
        }

        $hours = (int) ($this->windowSeconds / 3600);
        $seconds = $hours === 1 ? 'hour' : 'hours';
        return "{$hours} {$seconds}";
    }
}   