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
use craft\feedme\Plugin as FeedMe;
use craft\feedme\queue\jobs\FeedImport;
use craft\helpers\StringHelper;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Feeds controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.2.0
 */
class FeedsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Runs the full import, which will cascade down and run everything in turn.
     *
     * @throws BadRequestHttpException
     */
    public function actionFull(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $feeds = FeedMe::$plugin->getFeeds();
        $queue = Craft::$app->getQueue();

        foreach ($feeds->getFeeds() as $feed) {

            // Hit the main import and bail
            if (StringHelper::contains($feed->feedUrl, 'vend/products/list')) {
                $processedElementIds = [];

                $queue->delay(0)->push(new FeedImport([
                    'feed' => $feed,
                    'limit' => null,
                    'offset' => null,
                    'processedElementIds' => $processedElementIds,
                ]));

                return $this->asJson([
                    'success' => true
                ]);
            }

        }

        return $this->asJson([
            'success' => false
        ]);
    }

}