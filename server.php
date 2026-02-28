#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use VyosMcp\VyosClient;

$container = new Container();
$container->set(VyosClient::class, VyosClient::fromEnv());

$server = Server::builder()
    ->setServerInfo('VyOS Router', '1.0.0')
    ->setContainer($container)
    ->setDiscovery(__DIR__, ['src'])
    ->build();

$result = $server->run(new StdioTransport());
exit($result);
