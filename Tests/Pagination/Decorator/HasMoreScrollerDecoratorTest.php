<?php

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Tests\ExtractorTestCase;

class HasMoreScrollerDecoratorTest extends ExtractorTestCase
{
    public function testGetNextRequestHasMore()
    {
        $client = RestClient::create();
        $jobConfig = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false
            ]
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $decorated = new HasMoreScrollerDecorator($scroller, $config);
        self::assertInstanceOf('Keboola\Juicer\Pagination\OffsetScroller', $decorated->getScroller());

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $next);

        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => false],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertFalse($noNext);
    }

    public function testHasMore()
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, [
            'nextPageFlag' => [
                'field' => 'finished',
                'stopOn' => true
            ]
        ]);

        $yes = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertTrue($yes);
        $no = self::callMethod($scroller, 'hasMore', [(object) ['finished' => true]]);
        self::assertFalse($no);
    }

    public function testHasMoreNotSet()
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, []);

        $null = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertNull($null);
    }
}
