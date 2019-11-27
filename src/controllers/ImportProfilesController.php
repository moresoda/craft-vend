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

use Craft;
use angellco\vend\models\ImportProfile;
use angellco\vend\Vend;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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
     * @throws \yii\web\ForbiddenHttpException
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
     * @param ImportProfile|null $profile The profile being edited, if there were any validation errors.
     *
     * @return Response
     * @throws NotFoundHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionEdit(int $profileId = null, ImportProfile $profile = null): Response
    {
        $this->requireAdmin();

        $variables = [];

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

        return $this->renderTemplate('vend/import-profiles/_edit', $variables);
    }

    /**
     * Saves a profile.
     *
     * @return Response|null
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionSave()
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $profile = new ImportProfile();

        $profile->id = $request->getBodyParam('profileId');
        $profile->name = (string) $request->getBodyParam('name');
        $profile->handle = (string) $request->getBodyParam('handle');

        // TODO
        $profile->map = ['thing' => 'other thing'];

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
}