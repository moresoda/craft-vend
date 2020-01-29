<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\services;

use angellco\vend\oauth\providers\Vend as OauthProvider;
use Craft;
use craft\base\Component;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\StreamInterface;
use venveo\oauthclient\models\Token as OauthToken;
use venveo\oauthclient\Plugin as OauthPlugin;

/**
 * Api service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Api extends Component
{
    // Public Properties
    // =========================================================================

    /**
     * @var OauthPlugin
     */
    public $oauthPlugin;

    /**
     * @var mixed|OauthToken
     */
    public $oauthToken;

    /**
     * @var OauthProvider
     */
    public $oauthProvider;

    // Public Methods
    // =========================================================================

    /**
     * Api constructor.
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        // Cache the OAuth Plugin
        $this->oauthPlugin = OauthPlugin::$plugin;

        // Try and get a valid token and cache the results
        try {
            $tokens = $this->oauthPlugin->credentials->getValidTokensForAppAndUser('vend');

            if ($tokens) {
                /** @var OauthToken oauthToken */
                $this->oauthToken = $tokens[0];
                /** @var OauthProvider $provider */
                $this->oauthProvider = $this->oauthToken->getApp()->getProviderInstance()->getConfiguredProvider();
            }
        } catch (Exception $e) {
            throw $e;
        }

    }

    /**
     * Gets an authenticated response and returns the parsed result.
     *
     * Caches the response against the current token, uri and params
     * for 5 minutes.
     *
     * @param       $uri
     * @param array $params
     *
     * @return mixed
     * @throws IdentityProviderException
     */
    public function getResponse($uri, $params = [])
    {
        $cache = Craft::$app->getCache();

        // Make the cache key
        $key = 'vend.'.md5($this->oauthToken.$uri.serialize($params));

        // Check if we already have a cached version and return it if we do
        $response = $cache->get($key);
        if ($response)
        {
            return $response;
        }

        // We didn’t so fetch the request and cache it
        $url = $this->getPreparedUrl($uri, $params);
        $request = $this->oauthProvider->getAuthenticatedRequest('GET', $url, $this->oauthToken);
        $response = $this->oauthProvider->getParsedResponse($request);
        $cache->set($key, $response, 300);

        return $response;
    }

    /**
     * Makes an authenticated POST request and returns the parsed result.
     *
     * @param string                               $uri
     * @param string|null|resource|StreamInterface $body
     * @param array                                $headers
     *
     * @return mixed
     * @throws IdentityProviderException
     */
    public function postRequest($uri, $body, $headers = [])
    {
        $url = $this->oauthProvider->getApiUrl($uri);

        $options = [];

        if ($body) {
            $options['body'] = $body;
        }

        if ($headers) {
            $options['headers'] = $headers;
        }

        $request = $this->oauthProvider->getAuthenticatedRequest('POST', $url, $this->oauthToken, $options);

        return $this->oauthProvider->getParsedResponse($request);
    }

    /**
     * Makes an authenticated PUT request and returns the parsed result.
     *
     * @param string                               $uri
     * @param string|null|resource|StreamInterface $body
     * @param array                                $headers
     *
     * @return mixed
     * @throws IdentityProviderException
     */
    public function putRequest($uri, $body, $headers = [])
    {
        $url = $this->oauthProvider->getApiUrl($uri);

        $options = [];

        if ($body) {
            $options['body'] = $body;
        }

        if ($headers) {
            $options['headers'] = $headers;
        }

        $request = $this->oauthProvider->getAuthenticatedRequest('PUT', $url, $this->oauthToken, $options);

        return $this->oauthProvider->getParsedResponse($request);
    }

    /**
     * Makes an authenticated DELETE request and returns the parsed result.
     *
     * @param $uri
     *
     * @return mixed
     * @throws IdentityProviderException
     */
    public function deleteRequest($uri)
    {
        $url = $this->oauthProvider->getApiUrl($uri);
        $request = $this->oauthProvider->getAuthenticatedRequest('DELETE', $url, $this->oauthToken);
        return $this->oauthProvider->getParsedResponse($request);
    }

    /**
     * Returns a prepared URL for the API for a given URI and optional
     * query parameters
     *
     * @param       $uri
     * @param array $params
     *
     * @return string
     */
    public function getPreparedUrl($uri, $params = []): string
    {
        $url = $this->oauthProvider->getApiUrl($uri);

        if ($params) {
            $query = UrlHelper::buildQuery($params);
            $url .= '?'.$query;
        }

        return $url;
    }

}
