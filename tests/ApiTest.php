<?php

/*
 * Immobilienscout24 PHP API
 *
 * Copyright (c) 2020 pdir / digital agentur // pdir GmbH
 *
 * @package    immobilienscout-api
 * @link       https://github.com/pdir/immobilienscout-api
 * @license    MIT
 * @author     Mathias Arzberger <develop@pdir.de>
 * @author     pdir GmbH <https://pdir.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Pdir\Immoscout\Api;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $api = new Api();
        $this->assertInstanceOf(Api::class, $api);
    }
}