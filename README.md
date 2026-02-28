# VyOS PHP MCP Server

A custom MCP server for managing a VyOS router via its REST API, built with the official PHP MCP SDK (`mcp/sdk` v0.4.0).

## Why not `@danielbodnar/vyos-mcp`?

The JS package (`v0.1.0`) depended on `muppet` for SSE streaming, which removed the required export in `v0.3.0`. It also required a persistent systemd service and a manual `vyos-connect` call at the start of each Claude Code session. The VyOS REST API is simple enough (POST with form data) that a thin wrapper is all that's needed.

## Why MCP over SSH?

SSH to VyOS works but has friction. Configuration changes require sourcing `/opt/vyatta/etc/functions/script-template`, entering `configure` mode, and wrapping commands in `/opt/vyatta/bin/vyatta-op-cmd-wrapper` for operational commands. The agent has to manage shell state across multiple `ssh` invocations or construct multi-line scripts uploaded via `scp`. Output is unstructured text that needs parsing.

The REST API returns structured JSON, handles session state server-side, and exposes every operation as a stateless POST. MCP tools map directly to these endpoints, giving the agent typed parameters with JSON schemas and structured responses without any shell wrangling. SSH remains useful for log inspection and file access on the router itself.

This also saves tokens. On the input side, a tool call is a name + small JSON object vs. a full `ssh router "/opt/vyatta/bin/vyatta-op-cmd-wrapper show ..."` command string. On the output side, the REST API returns compact JSON instead of the verbose human-formatted text that VyOS operational commands produce (banners, column headers, whitespace). The biggest difference is config queries — `vyos_show_config` returns a JSON tree directly, while SSH requires parsing `show configuration commands` output (one `set` line per leaf) or VyOS's custom config.boot format.

## Architecture

```
server.php              stdio entry point - Coding agent can spawn it on demand
src/VyosClient.php      cURL client wrapping VyOS REST API endpoints
src/VyosTools.php       17 MCP tools defined with #[McpTool] attributes
```

**Transport**: stdio (no systemd service, no SSE, no port)
**Auth**: `VYOS_HOST` and `VYOS_API_KEY` env vars injected by Claude Code
**SSL**: `CURLOPT_SSL_VERIFYPEER => false` for the router's self-signed cert

### VyOS REST API endpoints

All requests are `POST` with multipart form data (`data` = JSON payload, `key` = API key).

| Endpoint | Operations |
|----------|-----------|
| `/retrieve` | showConfig, exists, returnValues |
| `/configure` | set, delete |
| `/config-file` | commit, save |
| `/show` | Operational show commands |
| `/reset` | Reset commands |
| `/generate` | Generate commands |
| `/reboot` | Reboot |
| `/poweroff` | Power off |

## Tools

| Tool | Description |
|------|-------------|
| `vyos_show_config` | Retrieve configuration at a path (json or raw) |
| `vyos_set_config` | Set a configuration value |
| `vyos_delete_config` | Delete a configuration node |
| `vyos_config_exists` | Check if a config path exists |
| `vyos_return_values` | Get values at a config path |
| `vyos_commit` | Commit pending changes (optional comment, confirm timeout) |
| `vyos_save_config` | Save running config to startup |
| `vyos_show` | Run any operational show command |
| `vyos_reset` | Run a reset command |
| `vyos_generate` | Run a generate command |
| `vyos_system_info` | Router version info |
| `vyos_ping` | Ping from the router |
| `vyos_traceroute` | Traceroute from the router |
| `vyos_interface_stats` | Interface statistics |
| `vyos_routing_table` | IP routing table |
| `vyos_dhcp_leases` | DHCP server leases |
| `vyos_health_check` | CPU, memory, storage, uptime |

## Usage with Claude Code

### Prerequisites

- PHP >= 8.1 with the cURL extension
- Composer
- A VyOS router with the HTTPS API enabled

### Install

```bash
git clone https://github.com/rlerdorf/vyos-mcp-php.git
cd vyos-mcp-php
composer install
```

### Configure Claude Code

Add the server to `.mcp.json` in your project root (or `~/.claude.json` for user-wide scope):

```json
{
  "mcpServers": {
    "vyos": {
      "command": "php",
      "args": ["/path/to/vyos-mcp-php/server.php"],
      "env": {
        "VYOS_HOST": "https://your-router-ip",
        "VYOS_API_KEY": "your-api-key"
      }
    }
  }
}
```

Or via the CLI:

```bash
claude mcp add --transport stdio \
  --env VYOS_HOST=https://your-router-ip \
  --env VYOS_API_KEY=your-key \
  vyos -- php /path/to/vyos-mcp-php/server.php
```

Claude Code spawns the PHP process on demand and injects credentials via environment variables. No systemd service, no background process, no per-session connect step — tools are available immediately.

### Verify

Start a new Claude Code session and ask it to call `vyos_system_info`. If the router responds with version info, everything is working.

## Usage with other MCP clients

Any MCP client that supports stdio transport can use this server. The server reads JSON-RPC from stdin and writes responses to stdout.

### Cursor

In `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "vyos": {
      "command": "php",
      "args": ["/path/to/vyos-mcp-php/server.php"],
      "env": {
        "VYOS_HOST": "https://your-router-ip",
        "VYOS_API_KEY": "your-api-key"
      }
    }
  }
}
```

### Windsurf

In `~/.codeium/windsurf/mcp_config.json`:

```json
{
  "mcpServers": {
    "vyos": {
      "command": "php",
      "args": ["/path/to/vyos-mcp-php/server.php"],
      "env": {
        "VYOS_HOST": "https://your-router-ip",
        "VYOS_API_KEY": "your-api-key"
      }
    }
  }
}
```

### Generic stdio

```bash
VYOS_HOST=https://your-router-ip VYOS_API_KEY=your-key php server.php
```

Then send JSON-RPC messages on stdin. The server follows the [MCP specification](https://modelcontextprotocol.io/) — initialize, then call `tools/list` or `tools/call`.

## Config change workflow

Configuration changes require explicit commit and save steps:

```
vyos_set_config    → stages the change
vyos_commit        → applies to running config
vyos_save_config   → persists across reboots
```

This mirrors VyOS's native configure/commit/save model and prevents accidental persistent changes.
