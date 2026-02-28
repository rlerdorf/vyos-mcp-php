<?php

namespace VyosMcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * InitializeHandler that negotiates protocol version with the client.
 *
 * The SDK's built-in handler ignores the client's requested version and
 * always responds with a hardcoded default. This handler uses the client's
 * requested version if we support it, falling back to a configured default.
 *
 * Registered before the SDK's handler via addRequestHandler() so it wins
 * the supports() check.
 *
 * @implements RequestHandlerInterface<InitializeResult>
 */
final class NegotiatingInitializeHandler implements RequestHandlerInterface
{
    /** @var array<string, ProtocolVersion> */
    private array $supported;

    public function __construct(
        private readonly Implementation $serverInfo,
        private readonly ProtocolVersion $default = ProtocolVersion::V2025_06_18,
    ) {
        $this->supported = [];
        foreach (ProtocolVersion::cases() as $v) {
            $this->supported[$v->value] = $v;
        }
    }

    public function supports(Request $request): bool
    {
        return $request instanceof InitializeRequest;
    }

    /**
     * @return Response<InitializeResult>
     */
    public function handle(Request $request, SessionInterface $session): Response
    {
        \assert($request instanceof InitializeRequest);

        $session->set('client_info', $request->clientInfo->jsonSerialize());
        $session->set('client_capabilities', $request->capabilities->jsonSerialize());

        // Use the client's version if we support it, otherwise our default
        $version = $this->supported[$request->protocolVersion] ?? $this->default;

        return new Response(
            $request->getId(),
            new InitializeResult(
                new ServerCapabilities(tools: true, logging: true, completions: true),
                $this->serverInfo,
                null,
                null,
                $version,
            ),
        );
    }
}
