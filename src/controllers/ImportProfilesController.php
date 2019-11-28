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

use angellco\vend\errors\ImportProfileNotFoundException;
use angellco\vend\models\ImportProfile;
use angellco\vend\Vend;
use Craft;
use craft\errors\MissingComponentException;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Import Profiles controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ImportProfilesController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * Import profiles index page.
     *
     * @return Response
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $profiles = Vend::$plugin->importProfiles->getAll();

        return $this->renderTemplate('vend/import-profiles/_index', [
            'profiles' => $profiles
        ]);
    }

    /**
     * Edit a profile.
     *
     * @param int|null           $profileId The profile’s ID, if editing an existing target.
     * @param ImportProfile|null $profile   The profile being edited, if there were any validation errors.
     *
     * @return Response
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws IdentityProviderException
     */
    public function actionEdit(int $profileId = null, ImportProfile $profile = null): Response
    {
        $this->requireAdmin();

        $variables = [];

        $vendApi = Vend::$plugin->api;

        // Vend objects
        // ---------------------------------------------------------------------

        // Product types
        $vendProductTypes = $vendApi->getResponse('2.0/product_types');
        $variables['vendProductTypes'] = [
            [
                'label' => '',
                'value' => ''
            ]
        ];
        if (isset($vendProductTypes['data']))
        {
            foreach ($vendProductTypes['data'] as $vendProductType)
            {
                $variables['vendProductTypes'][] = [
                    'label' => $vendProductType['name'],
                    'value' => $vendProductType['id']
                ];
            }
        }

        // Brand
        $vendBrands = $vendApi->getResponse('2.0/brands');
        $variables['vendBrands'] = [
            [
                'label' => '',
                'value' => ''
            ]
        ];
        if (isset($vendBrands['data']))
        {
            foreach ($vendBrands['data'] as $vendBrand)
            {
                $variables['vendBrands'][] = [
                    'label' => $vendBrand['name'],
                    'value' => $vendBrand['id']
                ];
            }
        }

        // Supplier
        $vendSuppliers = $vendApi->getResponse('2.0/suppliers');
        $variables['vendSuppliers'] = [
            [
                'label' => '',
                'value' => ''
            ]
        ];
        if (isset($vendSuppliers['data']))
        {
            foreach ($vendSuppliers['data'] as $vendSupplier)
            {
                $variables['vendSuppliers'][] = [
                    'label' => $vendSupplier['name'],
                    'value' => $vendSupplier['id']
                ];
            }
        }

        // Breadcrumbs
        $variables[ 'crumbs' ] = [
            [
                'label' => Craft::t('vend', 'Vend'),
                'url' => UrlHelper::url('vend')
            ],
            [
                'label' => Craft::t('vend', 'Import Profiles'),
                'url' => UrlHelper::url('vend/import-profiles')
            ]
        ];

        // Set up the model
        $variables[ 'brandNewProfile' ] = false;

        if ($profileId !== null) {
            if ($profile === null) {
                $profile = Vend::$plugin->importProfiles->getById($profileId);

                if (!$profile) {
                    throw new NotFoundHttpException('Import profile not found');
                }
            }

            $variables[ 'title' ] = $profile->name;
        } else {
            if ($profile === null) {
                $profile = new ImportProfile();
                $variables[ 'brandNewProfile' ] = true;
            }

            $variables[ 'title' ] = Craft::t('vend', 'Create a new import profile');
        }

        $variables[ 'profileId' ] = $profileId;
        $variables[ 'profile' ] = $profile;

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = "vend/import-profiles/{$profile->id}";

        return $this->renderTemplate('vend/import-profiles/_edit', $variables);
    }

    /**
     * Saves a profile.
     *
     * @return Response|null
     * @throws ImportProfileNotFoundException
     * @throws MissingComponentException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $profile = new ImportProfile();

        $profile->id = $request->getBodyParam('profileId');
        $profile->name = (string) $request->getBodyParam('name');
        $profile->handle = (string) $request->getBodyParam('handle');

        $map = (array) $request->getBodyParam('map');
        $profile->setMap($map);

        if (!Vend::$plugin->importProfiles->save($profile)) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save the import profile.'));

            // Send the model back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'profile' => $profile
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Import profile saved.'));

        return $this->redirectToPostedUrl($profile);
    }

    /**
     * Deletes a profile.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $profileId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Vend::$plugin->importProfiles->deleteById($profileId);

        return $this->asJson(['success' => true]);
    }
}