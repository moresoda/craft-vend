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
     * @return \yii\web\Response
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function actionList(): \yii\web\Response
    {
        $vendApi = Vend::$plugin->api;
        $request = Craft::$app->getRequest();

        // Set the page size
        $params = [
            'page_size' => 100
        ];

        // Set the after param which will be the max version number in the
        // previous collection
        $after = $request->getQueryParam('after');
        if ($after) {
            $params['after'] = $after;
        }

        // Fetch the products
        $response = $vendApi->getResponse('2.0/products', $params);

        // Make our response array
        $return = [
            'products' => $response['data']
        ];

        // Sort out the next URL
        $nextUrl = UrlHelper::actionUrl('vend/products/list', [
            'after' => $response['version']['max']
        ]);
        $return['nextUrl'] = $nextUrl;

        return $this->asJson($return);
    }
}