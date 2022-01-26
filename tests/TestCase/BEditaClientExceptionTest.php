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

use BEdita\SDK\BEditaClientException;
use PHPUnit\Framework\TestCase;

/**
 * \BEdita\SDK\BEditaClientException Test Case
 *
 * @coversDefaultClass \BEdita\SDK\BEditaClientException
 */
class BEditaClientExceptionTest extends TestCase
{
    /**
     * Default code for exception
     *
     * @var int
     */
    private $defaultCode;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->defaultCode = 500;
    }

    /**
     * Data provider for `testConstruct`
     */
    public function exceptionsProvider(): array
    {
        return [
            '400' => [
                ['File not found', 400],
                new BEditaClientException('File not found', 400),
            ],
            '401' => [
                ['Unauthorized', 401],
                new BEditaClientException('Unauthorized', 401),
            ],
            '403' => [
                ['Forbidden', 403],
                new BEditaClientException('Forbidden', 403),
            ],
            '500' => [
                [['System error', 'Something very bad happened'], 500],
                new BEditaClientException(['System error', 'Something very bad happened'], 500),
            ],
            'null' => [
                ['File not found', null],
                new BEditaClientException('File not found', $this->defaultCode),
            ],
        ];
    }

    /**
     * Test exception constructor
     *
     * @return void
     *
     * @covers ::__construct()
     * @dataProvider exceptionsProvider()
     */
    public function testConstruct($input, $expected): void
    {
        $this->expectException(get_class($expected));
        $this->expectExceptionCode($expected->getCode());
        $this->expectExceptionMessage($expected->getMessage());
        throw new BEditaClientException($input[0], $input[1]);
    }

    /**
     * Test `getAttributes()`
     *
     * @return void
     *
     * @covers ::getAttributes()
     * @dataProvider exceptionsProvider()
     */
    public function testAttributes($input, $expected): void
    {
        $this->expectException(get_class($expected));
        if (is_array($input[0])) {
            static::assertEquals($expected->getAttributes(), $input[0]);
        }
        throw new BEditaClientException($input[0], $input[1]);
    }
}
