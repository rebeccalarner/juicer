<?php

namespace Keboola\Juicer\Tests\Parser;

use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class JsonTest extends ExtractorTestCase
{
    public function testProcess()
    {
        $parser = new Json(new NullLogger(), [], 3);

        $data = json_decode('[
            {
                "pk": 1,
                "arr": [1,2,3]
            },
            {
                "pk": 2,
                "arr": ["a","b","c"]
            }
        ]');

        $parser->process($data, 'test', ['parent' => 'iAreId']);

        self::assertEquals(
            '"pk","arr","parent"
"1","test_2901753343d19a32b8cd49e31aab748c","iAreId"
"2","test_5e36066fa62399eedd858f5e374c0c21","iAreId"
',
            file_get_contents($parser->getResults()['test'])
        );

        self::assertEquals(
            '"data","JSON_parentId"
"1","test_2901753343d19a32b8cd49e31aab748c"
"2","test_2901753343d19a32b8cd49e31aab748c"
"3","test_2901753343d19a32b8cd49e31aab748c"
"a","test_5e36066fa62399eedd858f5e374c0c21"
"b","test_5e36066fa62399eedd858f5e374c0c21"
"c","test_5e36066fa62399eedd858f5e374c0c21"
',
            file_get_contents($parser->getResults()['test_arr'])
        );
    }

    public function testGetMetadata()
    {
        $parser = new Json(new NullLogger(), [], 3);

        $data = [
            (object) ['id' => 1]
        ];

        $parser->process($data, 'metadataTest');

        self::assertEquals(
            [
                'json_parser.struct' => [
                    '_metadataTest' => [
                        '[]' => [
                            '_id' => [
                                'nodeType' => 'scalar',
                                'headerNames' => 'id',
                            ],
                            'nodeType' => 'object',
                            'headerNames' => 'data',
                        ],
                        'nodeType' => 'array'
                    ],
                ],
                'json_parser.structVersion' => 3
            ],
            $parser->getMetadata()
        );
    }

    public function testUpdateStruct()
    {
        $json = '{
            "root.arr.arr1": {
                "c": "string"
            },
            "root.arr.arr2": {
                "data": "integer"
            },
            "root.arr": {
                "a": "string",
                "b": "double",
                "arr1": "array",
                "arr2": "array"
            },
            "root": {
                "id": "integer",
                "arr": "array"
            }
        }';
        $parser = new Json(new NullLogger(), $json, 3);

        $updated = self::callMethod($parser, 'updateStruct', [$struct]);

        $parser->getParser()->getAnalyzer()->getStructure()->load($updated);

        $parser->process([
            (object) [
                'id' => 1,
                'arr' => [
                    (object) [
                        'a' => "hello",
                        'b' => 1.1,
                        'arr1' => [(object) ['c' => 'd']],
                        'arr2' => [1,2]
                    ]
                ]
            ]
        ], 'root');

        self::assertEquals(
            [
                'root.arr.arr1' => ['c' => 'scalar'],
                'root.arr.arr2' => ['data' => 'scalar'],
                'root.arr' => [
                    'a' => 'scalar',
                    'b' => 'scalar',
                    'arr1' => 'arrayOfobject',
                    'arr2' => 'arrayOfscalar'
                ],
                'root' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfobject'
                ]
            ],
            $parser->getParser()->getAnalyzer()->getStructure()->getData()
        );

        self::assertEquals('"id","arr"
"1","root_a52f96d95586c8de1e8fa67b77597262"
', file_get_contents($parser->getParser()->getCsvFiles()['root']));

        self::assertEquals('"a","b","arr1","arr2","JSON_parentId"
"hello","1.1","root.arr_a75f0a3e0b848d52033929a761e6c997","root.arr_a75f0a3e0b848d52033929a761e6c997","root_a52f96d95586c8de1e8fa67b77597262"
', file_get_contents($parser->getParser()->getCsvFiles()['root_arr']));

        self::assertEquals('"c","JSON_parentId"
"d","root.arr_a75f0a3e0b848d52033929a761e6c997"
', file_get_contents($parser->getParser()->getCsvFiles()['root_arr_arr1']));

        self::assertEquals('"data","JSON_parentId"
"1","root.arr_a75f0a3e0b848d52033929a761e6c997"
"2","root.arr_a75f0a3e0b848d52033929a761e6c997"
', file_get_contents($parser->getParser()->getCsvFiles()['root_arr_arr2']));
    }

    public function testProcessNoData()
    {
        $logHandler = new TestHandler();
        $logger = new Logger('test', [$logHandler]);
        $parser = new Json($logger, [], 3);

        $parser->process([], 'empty');
        self::assertTrue($logHandler->hasDebug("No data returned in 'empty'"));
    }
}
