<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 Atlas Srl, ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\SDK\Test\TestCase;

use BEdita\SDK\BaseClient;
use Psr\Http\Message\ResponseInterface;

/**
 * A simple class that extends BaseClient.
 * Used to test protected methods.
 */
class MyBaseClient extends BaseClient
{
    /**
     * @inheritDoc
     */
    public function sendRequestRetry(
        string $method,
        string $path,
        ?array $query = null,
        ?array $headers = null,
        $body = null
    ): ResponseInterface {
        return parent::sendRequestRetry($method, $path, $query, $headers, $body);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(
        string $method,
        string $path,
        ?array $query = null,
        ?array $headers = null,
        $body = null
    ): ResponseInterface {
        return parent::sendRequest($method, $path, $query, $headers, $body);
    }
}
