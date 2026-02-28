# VyOS PHP MCP Server

## Quick start

```bash
composer install
VYOS_HOST=https://your-router VYOS_API_KEY=your-key php server.php
```

The server uses stdio transport — it reads JSON-RPC from stdin and writes to stdout. No HTTP server or systemd service needed.

## Project structure

- `server.php` — Entry point. Creates VyosClient from env vars, registers it in the DI container, discovers tools via `#[McpTool]` attributes, runs stdio transport.
- `src/VyosClient.php` — HTTP client wrapping the VyOS REST API. All endpoints use POST with multipart form data (`data` + `key` fields). Uses cURL with SSL verification disabled for self-signed certs.
- `src/VyosTools.php` — MCP tool definitions. Each public method with `#[McpTool]` becomes a tool. Parameter schemas are generated from PHP type hints and `#[Schema]` attributes.

## Adding a new tool

Add a method to `VyosTools.php`:

```php
#[McpTool(name: 'vyos_my_tool', description: 'What it does')]
public function myTool(string $param): mixed
{
    return $this->wrap(fn() => $this->client->show(['some', 'command']));
}
```

Use `#[Schema]` on parameters when you need constraints beyond what PHP types express (enums, min/max, array item types).

Always wrap tool bodies with `$this->wrap()` — it converts exceptions to `ToolCallException` so the MCP client gets a proper error response instead of a crash.

## VyOS REST API

All endpoints accept POST with two form fields:
- `data`: JSON object with `op` and usually `path`
- `key`: API key string

See `VyOS-PHP-MCP.md` for the full endpoint table.

## Testing

```bash
# Lint
php -l server.php && php -l src/VyosClient.php && php -l src/VyosTools.php

# Send an MCP initialize + tools/list handshake
printf '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}\n{"jsonrpc":"2.0","method":"notifications/initialized"}\n{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}\n' \
  | VYOS_HOST=https://your-router VYOS_API_KEY=your-key php server.php

# Call a tool
printf '...initialize...\n{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"vyos_system_info","arguments":{}}}\n' \
  | VYOS_HOST=https://your-router VYOS_API_KEY=your-key php server.php
```

## Dependencies

- PHP >= 8.1 (uses attributes, named arguments, fibers)
- `mcp/sdk` ^0.4 — official PHP MCP SDK from modelcontextprotocol/php-sdk
- cURL extension
