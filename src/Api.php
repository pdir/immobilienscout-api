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

namespace Pdir\Immoscout;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Psr7\Response;
use Pdir\Immoscout\Exceptions\ImmoscoutApiException;
use Pdir\Immoscout\Exceptions\ImmoscoutRatelimitExceededException;

class Api
{
    /**
     * the immoscout api url
     *
     * @var string $apiUrl
     */
    private $api = 'https://rest.immobilienscout24.de';

    /**
     * the immoscout api version
     *
     * @var string $apiVersion
     */
    protected $apiVersion = 'v1.0';

    /**
     * the immoscout consumer key
     *
     * @var string $consumerKey
     */
    private $consumerKey;

    /**
     * the immoscout consumer secret
     *
     * @var string $consumerSecret
     */
    private $consumerSecret;

    /**
     * the immoscout token key
     *
     * @var string $tokenKey
     */
    private $tokenKey;

    /**
     * the immoscout token secret
     *
     * @var string $tokenSecret
     */
    private $tokenSecret;

    /** @var Client */
    private $client;

    public function __construct(array $credentials = [])
    {
        $this->consumerKey = $credentials['consumerKey'] ? : getenv('IS24_CONSUMER_KEY');
        $this->consumerSecret = $credentials['consumerSecret'] ? : getenv('IS24_CONSUMER_SECRET');
        $this->tokenKey = $credentials['tokenKey'] ? : getenv('IS24_TOKEN_KEY');
        $this->tokenSecret = $credentials['tokenSecret'] ? : getenv('IS24_TOKEN_SECRET');

        $this->prepareClient();
    }

    public function getAllRealEstates(bool $withDetails = false, bool $archived = false, bool $activeOnly = false) {
        $estates = [];

        $response = $this->getRealEstates(1, 100, true, $archived);

        $next = $response['Paging']['next']['@xlink.href'] ?? null;

        if (null !== $next)
        {
            for($i=1; $i <= $response['Paging']['numberOfPages']; $i++)
            {
                $estates = array_merge($this->getRealEstates($i, 100, false, $archived), $estates);
            }
        }

        if (null === $next) {
            $estates = $response['realEstateList']['realEstateElement'];
        }

        if (null === $estates) {
            return null;
        }

        // import only active estates and remove inactive ones
        if($activeOnly)
        {
            foreach($estates as $key => $estate)
            {
                if(isset($estate['realEstateState']) && 'INACTIVE' === $estate['realEstateState']) {
                    unset($estates[$key]);
                }
            }
        }

        // add detail data to array
        if($withDetails)
        {            
            foreach($estates as $key => $estate)
            {
                $data = $this->getRealEstate($estate['@id']);
                $type = lcfirst(str_replace('offerlistelement:Offer', '', $estate['@xsi.type']));
                
                // set real estate type
                $data['type'] = $type;

                // overwrite estate data
                $estates[$key] = $data['realestates.' . $type];
            }
        }

        return $estates;

    }

    public function getRealEstates(int $pageNumber = 1, int $pageSize = 100, bool $pagination = false, bool $archived = false, string $publishChannel = null)
    {
        $resource = sprintf('user/me/realestate?pagenumber=%s&pagesize=%s&archivedobjectsincluded=%s',
            $pageNumber,
            $pageSize,
            $archived ? 'true' : 'false',
        );

        if($publishChannel)
        {
            $resource . '&publishchannel=' . $publishChannel;
        }

        $data = $this->requestGet($resource);
        
        if($pagination)
        {
            return $data['realestates.realEstates'];
        }

        return $data['realestates.realEstates']['realEstateList']['realEstateElement'];
    }

    public function getRealEstate(int $id)
    {
        $resource = sprintf('user/me/realestate/%s', $id);

        $data = $this->requestGet($resource);

        if (null === $data) {
            return null;
        }

        return $data;
    }

    public function getAttachments(int $id)
    {
        $resource = sprintf('user/me/realestate/%s/attachment', $id);

        $data = $this->requestGet($resource);

        if (null === $data) {
            return null;
        }

        return $data;
    }

    public function getAttachmentFilename($url)
    {
        $items = parse_url($url);
        $parts = explode('/', $items['path']);
        return $parts[2];
    }

    public function getContact(int $id)
    {
        $resource = sprintf('user/me/contact/%s', $id);

        $data = $this->requestGet($resource);

        if (null === $data) {
            return null;
        }

        return $data;
    }

    private function requestGet($resource)
    {
        /* @var Response $response */
        $response = $this->request('get', $resource);

        return $this->getArrayFromJsonBody($response);
    }

    public function request($method, $resource)
    {
        try {
            $response = $this->client->$method($this->createApiUrl($resource));
        } catch (\Exception $e) {
            if ($e->getCode() === 503) {
                throw new ImmoscoutRatelimitExceededException;
            }

            throw new ImmoscoutApiException($this->container[count($this->container)-1]['response']->getBody()->getContents(), 0, $e);
        }

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        return $response;
    }

    /**
     * @param Response $response
     * @return array
     */
    private function getArrayFromJsonBody($response)
    {
        $contents = $response->getBody()->getContents();
        return \GuzzleHttp\json_decode($contents, true);
    }

    /**
     * @param string $resource
     * @return string
     */
    protected function createApiUrl(string $resource = ''): string
    {
        return $this->api . '/restapi/api/offer/' . $this->apiVersion . '/' . $resource;
    }

    private function prepareClient()
    {
        $stack = HandlerStack::create();

        $stack->push($this->getOAuthMiddleware());

        $this->container = [];
        $history = Middleware::history($this->container);
        $stack->push($history);

        $client = new Client([
            'base_uri' => $this->createApiUrl(),
            'handler' => $stack,
            RequestOptions::AUTH => 'oauth',
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
        ]);

        $this->client = $client;
    }

    private function getOAuthMiddleware(): Oauth1
    {
        return new Oauth1([
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'token' => $this->tokenKey,
            'token_secret' => $this->tokenSecret
        ]);
    }
}
