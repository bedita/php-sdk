<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\SDK\Test\TestCase;

use BEdita\SDK\BEditaClient;
use PHPUnit\Framework\TestCase;

/**
 * \BEdita\SDK\BEditaClient Test Case
 *
 * @coversDefaultClass \BEdita\SDK\BEditaClient
 */
class BEditaClientTest extends TestCase
{
    /**
     * Test client constructor
     *
     * @return void
     *
     * @covers ::__construct()
     */
    public function testConstruct()
    {
        $apiBaseUrl = getenv('BEDITA_API');
        $apiKey = getenv('BEDITA_API_KEY');

        $client = new BEditaClient($apiBaseUrl, $apiKey);
        static::assertNotEmpty($client);
        static::assertEquals($client->getApiBaseUrl(), $apiBaseUrl);
    }
}
