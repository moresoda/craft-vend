<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\controllers;

use angellco\vend\Vend;
use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use yii\web\Response;

/**
 * Products controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ProductsController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * @return Response
     * @throws IdentityProviderException
     */
    public function actionList(): Response
    {
        $api = Vend::$plugin->api;
        $profiles = Vend::$plugin->importProfiles;
        $request = Craft::$app->getRequest();

        // Set the page size
        $params = [
            'page_size' => 10 // DEBUG
        ];

        // Set the after param which will be the max version number in the
        // previous collection
        $after = $request->getQueryParam('after');
        if ($after) {
            $params['after'] = $after;
        }

        // Fetch the products
        $response = $api->getResponse('2.0/products', $params);

        // Check if we got nothing back and bail
        if (!$response['data']) {
            return $this->asJson([
                'products' => [],
                'nextUrl' => null
            ]);
        }

        // Fetch the profile if there is one
        $profileHandle = (string) $request->getQueryParam('profile');
        $profile = $profiles->getByHandle($profileHandle);

        // TODO And apply it

        // TODO If we now have no products, fetch again

        // Make our response array
        $return = [
            'products' => $response['data']
        ];

        // Sort out the next URL
        $nextUrl = UrlHelper::actionUrl('vend/products/list', [
            'after' => $response['version']['max']
        ]);
        $return['nextUrl'] = $nextUrl;

        Craft::dd($return);

        return $this->asJson($return);
    }
}