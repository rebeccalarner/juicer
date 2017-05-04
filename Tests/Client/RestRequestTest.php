<?php

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Tests\ExtractorTestCase;

class RestRequestTest extends ExtractorTestCase
{
    public function testCreate()
    {
        $arr = [
            'first' => 1,
            'second' => 'two'
        ];
        $request = RestRequest::create([
            'endpoint' => 'ep',
            'params' => $arr
        ]);

        $expected = new RestRequest('ep', $arr);

        self::assertEquals($expected, $request);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Request params must be an array
     */
    public function testValidateConfig()
    {
        RestRequest::create(['endpoint' => 'ep', 'params' => 'string']);
    }
}
