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
use craft\base\Component;
use venveo\oauthclient\models\Token as OauthToken;
use venveo\oauthclient\Plugin as OauthPlugin;

/**
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Api extends Component {

    public $oauthPlugin;

    public $oauthToken;

    public $oauthProvider;

    /**
     * Api constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->oauthPlugin = OauthPlugin::$plugin;

        try {
            $tokens = $this->oauthPlugin->credentials->getValidTokensForAppAndUser('vend');

            if ($tokens) {
                /** @var OauthToken oauthToken */
                $this->oauthToken = $tokens[0];
                /** @var OauthProvider $provider */
                $this->oauthProvider = $this->oauthToken->getApp()->getProviderInstance()->getConfiguredProvider();
            }
        } catch (\Exception $e) {
            throw $e;
        }

    }

    /**
     * @param $uri
     *
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getResponse($uri)
    {
        $url = $this->oauthProvider->getApiUrl($uri);
        $request = $this->oauthProvider->getAuthenticatedRequest('GET', $url, $this->oauthToken);
        return $this->oauthProvider->getParsedResponse($request);
    }

}
