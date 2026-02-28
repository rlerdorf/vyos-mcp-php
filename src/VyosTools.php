<?php

namespace VyosMcp;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;

class VyosTools
{
    private VyosClient $client;

    public function __construct(VyosClient $client)
    {
        $this->client = $client;
    }

    // --- Config queries ---

    /**
     * Retrieve VyOS configuration at the given path.
     *
     * @param string[] $path Configuration path components, e.g. ["interfaces", "ethernet", "eth0"]
     * @param string $format Output format
     */
    #[McpTool(name: 'vyos_show_config', description: 'Retrieve VyOS configuration at a path')]
    public function showConfig(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Configuration path components')]
        array $path = [],
        #[Schema(type: 'string', enum: ['json', 'raw'], description: 'Output format')]
        string $format = 'json',
    ): mixed {
        return $this->wrap(fn() => $this->client->showConfig($path, $format));
    }

    /**
     * Set a VyOS configuration value.
     *
     * @param string[] $path Configuration path including value, e.g. ["interfaces", "ethernet", "eth0", "description", "LAN"]
     */
    #[McpTool(name: 'vyos_set_config', description: 'Set a VyOS configuration value')]
    public function setConfig(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Configuration path including value as last element')]
        array $path,
    ): string {
        return $this->wrap(function () use ($path) {
            $this->client->setConfig($path);
            return 'Configuration set successfully';
        });
    }

    /**
     * Delete a VyOS configuration node.
     *
     * @param string[] $path Configuration path to delete
     */
    #[McpTool(name: 'vyos_delete_config', description: 'Delete a VyOS configuration node')]
    public function deleteConfig(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Configuration path to delete')]
        array $path,
    ): string {
        return $this->wrap(function () use ($path) {
            $this->client->deleteConfig($path);
            return 'Configuration deleted successfully';
        });
    }

    /**
     * Check if a VyOS configuration path exists.
     *
     * @param string[] $path Configuration path to check
     */
    #[McpTool(name: 'vyos_config_exists', description: 'Check if a configuration path exists')]
    public function configExists(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Configuration path to check')]
        array $path,
    ): mixed {
        return $this->wrap(fn() => ['exists' => $this->client->configExists($path)]);
    }

    /**
     * Get values at a VyOS configuration path.
     *
     * @param string[] $path Configuration path
     */
    #[McpTool(name: 'vyos_return_values', description: 'Get values at a configuration path')]
    public function returnValues(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Configuration path')]
        array $path,
    ): mixed {
        return $this->wrap(fn() => $this->client->returnValues($path));
    }

    // --- Config persistence ---

    /**
     * Commit pending VyOS configuration changes.
     *
     * @param string|null $comment Optional commit comment
     * @param int|null $confirmTimeout Minutes before auto-rollback if not confirmed
     */
    #[McpTool(name: 'vyos_commit', description: 'Commit pending configuration changes')]
    public function commit(
        ?string $comment = null,
        ?int $confirmTimeout = null,
    ): string {
        return $this->wrap(function () use ($comment, $confirmTimeout) {
            $this->client->commit($comment, $confirmTimeout);
            return 'Configuration committed successfully';
        });
    }

    /**
     * Save running configuration to startup config.
     */
    #[McpTool(name: 'vyos_save_config', description: 'Save running configuration to startup config')]
    public function saveConfig(): string
    {
        return $this->wrap(function () {
            $this->client->save();
            return 'Configuration saved successfully';
        });
    }

    // --- Operational commands ---

    /**
     * Run an operational show command on the VyOS router.
     *
     * @param string[] $path Command path, e.g. ["version"], ["interfaces"], ["ip", "route"]
     */
    #[McpTool(name: 'vyos_show', description: 'Run an operational show command')]
    public function show(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Command path components')]
        array $path,
    ): mixed {
        return $this->wrap(fn() => $this->client->show($path));
    }

    /**
     * Run a reset command on the VyOS router.
     *
     * @param string[] $path Command path components
     */
    #[McpTool(name: 'vyos_reset', description: 'Run a reset command')]
    public function reset(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Command path components')]
        array $path,
    ): mixed {
        return $this->wrap(fn() => $this->client->reset($path));
    }

    /**
     * Run a generate command on the VyOS router.
     *
     * @param string[] $path Command path components
     */
    #[McpTool(name: 'vyos_generate', description: 'Run a generate command')]
    public function generate(
        #[Schema(type: 'array', items: ['type' => 'string'], description: 'Command path components')]
        array $path,
    ): mixed {
        return $this->wrap(fn() => $this->client->generate($path));
    }

    // --- Convenience tools ---

    /**
     * Get VyOS system information including version, uptime, and resources.
     */
    #[McpTool(name: 'vyos_system_info', description: 'Get system version, uptime, and resource usage')]
    public function systemInfo(): mixed
    {
        return $this->wrap(function () {
            $version = $this->client->show(['version']);
            return $version;
        });
    }

    /**
     * Ping a host from the VyOS router.
     *
     * Uses traceroute (mtr) under the hood since VyOS has no ping API endpoint.
     * Returns hop-by-hop latency data including the final destination.
     *
     * @param string $host Hostname or IP to ping
     * @param int $count Number of pings to send (used as mtr report cycles)
     */
    #[McpTool(name: 'vyos_ping', description: 'Ping a host from the router')]
    public function ping(
        string $host,
        #[Schema(type: 'integer', minimum: 1, maximum: 20, description: 'Number of pings')]
        int $count = 4,
    ): mixed {
        // VyOS has no /ping API endpoint. Use /traceroute (mtr) which provides
        // latency data for each hop including the destination.
        return $this->wrap(fn() => $this->client->traceroute($host));
    }

    /**
     * Traceroute to a host from the VyOS router.
     *
     * @param string $host Hostname or IP to trace
     */
    #[McpTool(name: 'vyos_traceroute', description: 'Traceroute to a host from the router')]
    public function traceroute(string $host): mixed
    {
        return $this->wrap(fn() => $this->client->traceroute($host));
    }

    /**
     * Show interface statistics.
     */
    #[McpTool(name: 'vyos_interface_stats', description: 'Show interface statistics')]
    public function interfaceStats(): mixed
    {
        return $this->wrap(fn() => $this->client->show(['interfaces']));
    }

    /**
     * Show the routing table.
     */
    #[McpTool(name: 'vyos_routing_table', description: 'Show routing table')]
    public function routingTable(): mixed
    {
        return $this->wrap(fn() => $this->client->show(['ip', 'route']));
    }

    /**
     * Show DHCP server leases.
     */
    #[McpTool(name: 'vyos_dhcp_leases', description: 'Show DHCP server leases')]
    public function dhcpLeases(): mixed
    {
        return $this->wrap(fn() => $this->client->show(['dhcp', 'server', 'leases']));
    }

    /**
     * Run a system health check including CPU, memory, storage, and uptime.
     */
    #[McpTool(name: 'vyos_health_check', description: 'System health check: CPU, memory, storage, uptime')]
    public function healthCheck(): mixed
    {
        return $this->wrap(function () {
            $results = [];
            $checks = [
                'version' => ['version'],
                'uptime' => ['system', 'uptime'],
                'cpu' => ['system', 'cpu'],
                'memory' => ['system', 'memory'],
                'storage' => ['system', 'storage'],
            ];

            foreach ($checks as $label => $path) {
                try {
                    $results[$label] = $this->client->show($path);
                } catch (\Throwable $e) {
                    $results[$label] = "Error: {$e->getMessage()}";
                }
            }

            return $results;
        });
    }

    /**
     * Wrap a tool call, converting exceptions to ToolCallException.
     */
    private function wrap(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ToolCallException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
