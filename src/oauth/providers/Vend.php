<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\oauth\providers;

use Craft;
use angellco\vend\Vend as VendPlugin;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Vend League Provider class.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Vend extends AbstractProvider {

    use BearerAuthorizationTrait;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $domainPrefix;

    // Public Methods
    // =========================================================================

    public function __construct(array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);

        $pluginSettings = VendPlugin::$plugin->getSettings();
        if ($pluginSettings && $pluginSettings->domainPrefix) {
            $this->domainPrefix = $pluginSettings->domainPrefix;
        }
    }

    /**
     * Returns the base URL for authorizing a client.
     *
     * Eg. https://oauth.service.com/authorize
     *
     * @return string
     */
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://secure.vendhq.com/connect';
    }

    /**
     * Returns the base URL for requesting an access token.
     *
     * Eg. https://oauth.service.com/token
     *
     * @param array $params
     *
     * @return string
     * @throws \yii\web\BadRequestHttpException
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        $this->domainPrefix = Craft::$app->getRequest()->getRequiredQueryParam('domain_prefix');
        return $this->getApiUrl('1.0/token');
    }

    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        throw new \RuntimeException('Vend does not allow access to the resource owner details.');
    }

    /**
     * Returns the base URL for this Vend store.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return "https://{$this->domainPrefix}.vendhq.com";
    }

    /**
     * Returns an API URL for a given URI path.
     *
     * @param $uri
     *
     * @return string
     */
    public function getApiUrl($uri): string
    {
        return "{$this->getBaseUrl()}/api/{$uri}";
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns the default scopes used by this provider.
     *
     * This should only be the scopes that are required to request the details
     * of the resource owner, rather than all the available scopes.
     *
     * @return array
     */
    protected function getDefaultScopes(): array
    {
        return ['authorization_code'];
    }

    /**
     * Checks a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  array|string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        // TODO sort this out
//        error=access_denied
//        var_dump($data);
    }


    /**
     * Generates a resource owner object from a successful resource owner
     * details request.
     *
     * @param  array $response
     * @param  AccessToken $token
     * @return ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token) {
        throw new \RuntimeException('Vend does not allow access to the resource owners.');
    }

//
//    /**
//     * Get a Vend API URL, depending on path.
//     *
//     * @param  string $path
//     * @return string
//     */
//    protected function getApiUrl($path)
//    {
//        return "https://{$this->domainPrefix}.vendhq.com/api/{$path}";
//    }
//
//    public function urlAuthorize()
//    {
//        return 'https://secure.vendhq.com/connect';
//
//    }
//
//    public function urlAccessToken()
//    {
//        return $this->getApiUrl('1.0/token');
//    }
//
//    public function urlUserDetails(AccessToken $token)
//    {
//        throw new \RuntimeException('Vend does not provide details for single users');
//    }
//
//    public function userDetails($response, AccessToken $token)
//    {
//        return [];
//    }
//
//    /**
//     * Helper method that can be used to fetch API responses.
//     *
//     * @param  string      $path
//     * @param  AccessToken $token
//     * @param  boolean     $as_array
//     * @return array|object
//     */
//    public function getApiResponse($path, AccessToken $token, $as_array = true)
//    {
//        $url = $this->getApiUrl($path);
//        $headers = $this->getHeaders($token);
//        return json_decode($this->fetchProviderData($url, $headers), $as_array);
//    }
    
}