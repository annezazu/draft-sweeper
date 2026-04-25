<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

/**
 * Finds the first AI-provider connector that has a usable API key.
 * Returns null when no provider is configured (no-AI path).
 */
final class AiProviderResolver
{
    public function resolve(): ?array
    {
        if (! function_exists('wp_get_connectors')) {
            return null;
        }

        $connectors = wp_get_connectors();
        if (! is_array($connectors)) {
            return null;
        }

        foreach ($connectors as $id => $connector) {
            if (($connector['type'] ?? '') !== 'ai_provider') {
                continue;
            }

            $auth = $connector['authentication'] ?? [];
            if (($auth['method'] ?? '') !== 'api_key') {
                continue;
            }

            if ($this->hasKey($id, $auth['setting_name'] ?? null)) {
                return ['id' => (string) $id] + $connector;
            }
        }

        return null;
    }

    private function hasKey(string $id, ?string $settingName): bool
    {
        if ($settingName !== null) {
            $option = get_option($settingName);
            if (is_string($option) && $option !== '') {
                return true;
            }
        }
        $envName = strtoupper($id) . '_API_KEY';
        $envValue = getenv($envName);
        return is_string($envValue) && $envValue !== '';
    }
}
