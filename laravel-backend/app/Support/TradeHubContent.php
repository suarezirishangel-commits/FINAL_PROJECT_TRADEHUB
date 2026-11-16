<?php

namespace App\Support;

use App\Models\Announcement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class TradeHubContent
{
    private bool $resolved = false;

    private ?array $wordpressContent = null;

    private ?array $settings = null;

    public function setting(string $key, string $default = ''): string
    {
        $content = $this->wordpressContent();
        if (isset($content['settings'][$key]) && is_string($content['settings'][$key])) {
            return $content['settings'][$key];
        }

        return $this->settings()[$key] ?? $default;
    }

    public function settings(): array
    {
        return $this->settings ??= DB::table('settings')->pluck('value', 'key')->all();
    }

    public function announcements(): Collection|array
    {
        $content = $this->wordpressContent();
        if ($content !== null) {
            return collect($content['announcements']);
        }

        return Announcement::query()
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function wordpressConfigured(): bool
    {
        return config('services.wordpress.url') !== '';
    }

    public function wordpressAdminUrl(): string
    {
        return config('services.wordpress.url').'/wp-admin/options-general.php?page=tradehub-content';
    }

    private function wordpressContent(): ?array
    {
        if ($this->resolved) {
            return $this->wordpressContent;
        }

        $this->resolved = true;
        $baseUrl = config('services.wordpress.url');

        if ($baseUrl === '') {
            return null;
        }

        try {
            $content = Http::acceptJson()
                ->timeout(3)
                ->get($baseUrl.'/wp-json/tradehub/v1/content')
                ->throw()
                ->json();

            if (! is_array($content)
                || ! isset($content['settings'], $content['announcements'])
                || ! is_array($content['settings'])
                || ! is_array($content['announcements'])) {
                throw new UnexpectedValueException('The WordPress CMS response has an invalid format.');
            }

            return $this->wordpressContent = $content;
        } catch (ConnectionException|RequestException|UnexpectedValueException $error) {
            Log::warning('TradeHub WordPress CMS request failed.', ['message' => $error->getMessage()]);

            return null;
        }
    }
}
