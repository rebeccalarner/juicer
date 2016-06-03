<?php

use Keboola\Juicer\Client\RestRequest,
    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Common\Logger;
use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;

class RestClientTest extends ExtractorTestCase
{

    public function testCreateRequest()
    {
        $arr = [
            'first' => 1,
            'second' => 'two'
        ];
        $jobConfig = JobConfig::create([
            'endpoint' => 'ep',
            'params' => $arr
        ]);

        $client = new RestClient(new Client);
        $request = $client->createRequest($jobConfig->getConfig());

        $expected = new RestRequest('ep', $arr);

        self::assertEquals($expected, $request);
    }

    public function testGetGuzzleRequest()
    {
        $client = new RestClient(new Client);
        $requestGet = new RestRequest('ep', ['a' => 1]);
        $requestPost = new RestRequest('ep', ['a' => 1], 'POST');
        $requestForm = new RestRequest('ep', ['a' => 1], 'FORM');

        $get = $this->callMethod($client, 'getGuzzleRequest', [$requestGet]);
        $post = $this->callMethod($client, 'getGuzzleRequest', [$requestPost]);
        $form = $this->callMethod($client, 'getGuzzleRequest', [$requestForm]);

        self::assertEquals('ep?a=1', $get->getUrl());

        self::assertEquals('ep', $post->getUrl());
        self::assertEquals('{"a":1}', $post->getBody());

        self::assertEquals('ep', $form->getUrl());
        self::assertEquals(['a' => 1], $form->getBody()->getFields());
    }

    public function testDownload()
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $guzzle = new Client();
        $guzzle->setDefaultOption('headers', ['X-Test' => '1234']);

        $mock = new Mock([
            new Response(200, [], Stream::factory($body))
        ]);
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle);

        $request = new RestRequest('ep', ['a' => 1]);

        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals('ep?a=1', $history->getLastRequest()->getUrl());
        self::assertEquals('GET', $history->getLastRequest()->getMethod());
        self::assertEquals(
            [1234],
            $history->getLastRequest()->getHeaders()['X-Test']
        );
    }

    public function testRequestHeaders()
    {
        $guzzle = new Client();
        $guzzle->setDefaultOption('headers', ['X-Test' => '1234']);

        $mock = new Mock([
            new Response(200, [], Stream::factory('{}'))
        ]);
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle);

        $request = new RestRequest('ep', [], 'GET', ['X-RTest' => 'requestHeader']);
        $restClient->download($request);

        self::assertEquals(
            [
                'X-RTest' => ['requestHeader'],
                'X-Test' => ['1234']
            ],
            $history->getLastRequest()->getHeaders()
        );
    }

    /**
     * @dataProvider retryProvider
     */
    public function testBackoff(RestClient $restClient, Response $errResponse)
    {
        Logger::setLogger($this->getLogger('test', true));

        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $mock = new Mock([
            $errResponse,
            new Response(200, [], Stream::factory($body))
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $history = new History();
        $restClient->getClient()->getEmitter()->attach($history);

        $request = new RestRequest('ep', ['a' => 1]);

        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals(5000, $history->getLastRequest()->getConfig()['delay'], '', 1000);
    }

    public function retryProvider()
    {
        return [
            'default' => [
                RestClient::create(),
                new Response(429, ['Retry-After' => 5])
            ],
            'custom' => [
                RestClient::create([], [
                    'headerName' => 'X-Rate-Limit-Reset',
        //             'relative' => false, // is "guessed" by the app
                    'httpCodes' => [403, 429],
                    'maxRetries' => 8
                ]),
                new Response(403, ['X-Rate-Limit-Reset' => 5])
            ],
            'absolute' => [
                RestClient::create(),
                new Response(429, ['Retry-After' => time() + 5])
            ]
        ];
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Invalid JSON response from API: JSON decode error:
     */
    public function testMalformedJson()
    {
        $body = '[
                {"field": "d
        ]';

        $restClient = RestClient::create();

        $mock = new Mock([
            new Response(200, [], Stream::factory($body))
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $request = new RestRequest('ep');

        try {
            $restClient->download($request);
        } catch(\Keboola\Juicer\Exception\UserException $e) {
            self::assertArrayHasKey('errDetail', $e->getData());
            self::assertArrayHasKey('json', $e->getData());
            throw $e;
        }

        throw new \Exception;
    }

    public function testDefaultRequestOptions()
    {
        $defaultOptions = [
            'method' => 'POST',
            'params' => [
                'defA' => 'defValA',
                'defB' => 'defValB'
            ]
        ];

        $client = RestClient::create();
        $client->setDefaultRequestOptions($defaultOptions);

        $requestOptions = [
            'endpoint' => 'ep',
            'params' => [
                'defB' => 'overrideB'
            ]
        ];
        $request = $client->createRequest($requestOptions);

        self::assertEquals($defaultOptions['method'], $request->getMethod());
        self::assertEquals($requestOptions['endpoint'], $request->getEndpoint());
        self::assertEquals(
            array_replace($defaultOptions['params'], $requestOptions['params']),
            $request->getParams()
        );
    }
}
