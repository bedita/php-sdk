<?php
declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test for class BEditaClientException
 */
class BEditaClientExceptionTest extends TestCase
{
    /**
     * Data provider for `testConstruct`
     */
    public static function exceptionsProvider(): array
    {
        return [
            '400' => [
                ['File not found', 400],
                ['File not found', 400],
            ],
            '401' => [
                ['Unauthorized', 401],
                ['Unauthorized', 401],
            ],
            '403' => [
                ['Forbidden', 403],
                ['Forbidden', 403],
            ],
            '500' => [
                [['System error', 'Something very bad happened'], 500],
                [['System error', 'Something very bad happened'], 500],
            ],
            'null' => [
                ['File not found', null],
                ['File not found', 503],
            ],
        ];
    }

    /**
     * Test exception constructor
     *
     * @param array $input Input data
     * @param array $expected Expected data for exception
     * @return void
     */
    #[DataProvider('exceptionsProvider')]
    public function testConstruct(array $input, array $expected): void
    {
        $expected = new BEditaClientException($expected[0], $expected[1]);
        $this->expectException(get_class($expected));
        $this->expectExceptionCode($expected->getCode());
        $this->expectExceptionMessage($expected->getMessage());
        throw new BEditaClientException($input[0], $input[1]);
    }

    /**
     * Test `getAttributes()`
     *
     * @param array $input Input data
     * @param array $expected Expected data for exception
     * @return void
     */
    #[DataProvider('exceptionsProvider')]
    public function testAttributes(array $input, array $expected): void
    {
        $expected = new BEditaClientException($expected[0], $expected[1]);
        $this->expectException(get_class($expected));
        if (is_array($input[0])) {
            static::assertEquals($expected->getAttributes(), $input[0]);
        }
        throw new BEditaClientException($input[0], $input[1]);
    }
}
