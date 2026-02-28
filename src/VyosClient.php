<?php

namespace VyosMcp;

class VyosClient
{
    private string $host;
    private string $apiKey;

    public function __construct(string $host, string $apiKey)
    {
        $this->host = rtrim($host, '/');
        $this->apiKey = $apiKey;
    }

    public static function fromEnv(): self
    {
        $host = getenv('VYOS_HOST');
        $apiKey = getenv('VYOS_API_KEY');

        if (!$host || !$apiKey) {
            throw new \RuntimeException('VYOS_HOST and VYOS_API_KEY environment variables are required');
        }

        return new self($host, $apiKey);
    }

    // --- Config operations → /retrieve ---

    /**
     * Show configuration at the given path.
     *
     * @param string[] $path Configuration path components
     * @param string $format Output format: 'json' or 'raw'
     * @return mixed
     */
    public function showConfig(array $path = [], string $format = 'json'): mixed
    {
        $data = ['op' => 'showConfig', 'path' => $path];
        if ($format === 'raw') {
            $data['configFormat'] = 'raw';
        }
        return $this->request('/retrieve', $data);
    }

    /**
     * Check if a configuration path exists.
     *
     * @param string[] $path Configuration path components
     */
    public function configExists(array $path): bool
    {
        $result = $this->request('/retrieve', [
            'op' => 'exists',
            'path' => $path,
        ]);
        return (bool) $result;
    }

    /**
     * Return values at a configuration path.
     *
     * @param string[] $path Configuration path components
     * @return mixed
     */
    public function returnValues(array $path): mixed
    {
        return $this->request('/retrieve', [
            'op' => 'returnValues',
            'path' => $path,
        ]);
    }

    // --- Config changes → /configure ---

    /**
     * Set a configuration value.
     *
     * @param string[] $path Configuration path components (include value as last element)
     */
    public function setConfig(array $path): void
    {
        $this->request('/configure', [
            'op' => 'set',
            'path' => $path,
        ]);
    }

    /**
     * Delete a configuration node.
     *
     * @param string[] $path Configuration path components
     */
    public function deleteConfig(array $path): void
    {
        $this->request('/configure', [
            'op' => 'delete',
            'path' => $path,
        ]);
    }

    // --- Config persistence → /config-file ---

    /**
     * Commit pending configuration changes.
     */
    public function commit(?string $comment = null, ?int $confirmTimeout = null): void
    {
        $data = ['op' => 'commit'];
        if ($comment !== null) {
            $data['comment'] = $comment;
        }
        if ($confirmTimeout !== null) {
            $data['confirm'] = $confirmTimeout;
        }
        $this->request('/config-file', $data);
    }

    /**
     * Save running configuration to startup config.
     */
    public function save(): void
    {
        $this->request('/config-file', ['op' => 'save']);
    }

    // --- Operational → /show, /reset, /generate ---

    /**
     * Run an operational show command.
     *
     * @param string[] $path Command path components
     * @return mixed
     */
    public function show(array $path): mixed
    {
        return $this->request('/show', ['op' => 'show', 'path' => $path]);
    }

    /**
     * Run a reset command.
     *
     * @param string[] $path Command path components
     * @return mixed
     */
    public function reset(array $path): mixed
    {
        return $this->request('/reset', ['op' => 'reset', 'path' => $path]);
    }

    /**
     * Run a generate command.
     *
     * @param string[] $path Command path components
     * @return mixed
     */
    public function generate(array $path): mixed
    {
        return $this->request('/generate', ['op' => 'generate', 'path' => $path]);
    }

    // --- System → /reboot, /poweroff ---

    /**
     * Reboot the router.
     */
    public function reboot(): void
    {
        $this->request('/reboot', ['op' => 'reboot']);
    }

    /**
     * Power off the router.
     */
    public function poweroff(): void
    {
        $this->request('/poweroff', ['op' => 'poweroff']);
    }

    // --- Private ---

    /**
     * Send a request to the VyOS REST API.
     */
    private function request(string $endpoint, array $data = []): mixed
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->host . $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'data' => json_encode($data),
                'key' => $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("VyOS API request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("VyOS API returned HTTP {$httpCode}: {$response}");
        }

        $decoded = json_decode($response, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Not JSON — return raw response (some commands return plain text)
            return $response;
        }

        if (isset($decoded['success']) && !$decoded['success']) {
            $msg = $decoded['error'] ?? $decoded['data'] ?? 'Unknown error';
            throw new \RuntimeException("VyOS API error: {$msg}");
        }

        return $decoded['data'] ?? $decoded;
    }
}
