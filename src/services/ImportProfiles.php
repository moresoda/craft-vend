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

use angellco\vend\errors\ImportProfileNotFoundException;
use angellco\vend\models\ImportProfile;
use angellco\vend\records\ImportProfile as ImportProfileRecord;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig;
use craft\helpers\StringHelper;
use Exception;
use Throwable;
use yii\base\ErrorException;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

/**
 * Import Profiles service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 *
 * @property array       $all
 * @property int         $total
 * @property array|int[] $allIds
 */
class ImportProfiles extends Component
{
    // Constants
    // =========================================================================

    public const CONFIG_PROFILES_KEY = 'vend.importProfiles';

    // Private Properties
    // =========================================================================

    /**
     * @var int[]|null
     */
    private $_allProfileIds;

    /**
     * @var ImportProfile[]|null
     */
    private $_profilesById;

    /**
     * @var bool
     */
    private $_fetchedAllProfiles = false;


    // Public Methods
    // =========================================================================

    /**
     * Returns all of the profile IDs.
     *
     * @return int[]
     */
    public function getAllIds(): array
    {
        if ($this->_allProfileIds !== null) {
            return $this->_allProfileIds;
        }

        if ($this->_fetchedAllProfiles) {
            return $this->_allProfileIds = array_keys($this->_profilesById);
        }

        return $this->_allProfileIds = (new Query())
            ->select([ 'id' ])
            ->from([ '{{%vend_importprofiles}}' ])
            ->column();
    }

    /**
     * Returns all profiles.
     *
     * @return array
     */
    public function getAll(): array
    {
        if ($this->_fetchedAllProfiles) {
            return array_values($this->_profilesById);
        }

        $this->_profilesById= [ ];

        /** @var ImportProfileRecord[] $profileRecords */
        $profileRecords = ImportProfileRecord::find()
            ->orderBy([ 'name' => SORT_ASC ])
            ->all();

        foreach ($profileRecords as $profileRecord) {
            $this->_profilesById[$profileRecord->id] = $this->_createProfileFromRecord($profileRecord);
        }

        $this->_fetchedAllProfiles = true;

        return array_values($this->_profilesById);
    }


    /**
     * Gets the total number of profiles.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return count($this->getAllIds());
    }

    /**
     * Returns a profile by its ID.
     *
     * @param int $profileId
     * @return ImportProfile|null
     */
    public function getById(int $profileId): ?ImportProfile
    {
        if ($this->_profilesById !== null && array_key_exists($profileId, $this->_profilesById)) {
            return $this->_profilesById[$profileId];
        }

        if ($this->_fetchedAllProfiles) {
            return null;
        }

        $profileRecord = ImportProfileRecord::find()
            ->where(['id' => $profileId])
            ->one();

        if ($profileRecord === null) {
            return $this->_profilesById[$profileId] = null;
        }

        /** @var ImportProfileRecord $profileRecord */
        return $this->_profilesById[$profileId] = $this->_createProfileFromRecord($profileRecord);
    }

    /**
     * Saves a profile into the project config
     *
     * @param ImportProfile $profile
     *
     * @return bool
     * @throws Exception
     * @throws ImportProfileNotFoundException
     * @throws ErrorException
     * @throws \yii\base\Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function save(ImportProfile $profile): bool
    {
        $isNew = !$profile->id;

        // Ensure the profile has a UID
        if ($isNew) {
            $profile->uid = StringHelper::UUID();
        } else if (!$profile->uid) {
            $existingRecord = ImportProfileRecord::findOne($profile->id);

            if (!$existingRecord) {
                throw new ImportProfileNotFoundException("No Import Profile exists with the ID “{$profile->id}”");
            }

            $profile->uid = $existingRecord->uid;
        }

        // Save it to the project config
        $configData = [
            'name' => $profile->name,
            'handle' => $profile->handle,
            'map' => $profile->map
        ];

        $configPath = self::CONFIG_PROFILES_KEY . '.' . $profile->uid;

        Craft::$app->projectConfig->set($configPath, $configData);

        if ($isNew) {
            $profile->id = Db::idByUid('{{%vend_importprofiles}}', $profile->uid);
        }

        return true;
    }

    /**
     * Handles a changed profile and saves it to the database.
     *
     * @param ConfigEvent $event
     *
     * @throws Throwable
     */
    public function handleChangedProfile(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        // Make sure fields and sites are processed
        ProjectConfig::ensureAllSitesProcessed();
        ProjectConfig::ensureAllFieldsProcessed();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Get the record
            $record = $this->_getRecord($uid);

            // Prep the record with the new data
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->map = $data['map'];
            $record->uid = $uid;

            // Save the record
            $record->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete’s a profile by its ID from the project config.
     *
     * @param int $profileId
     *
     * @return bool
     */
    public function deleteById(int $profileId): bool
    {
        $profile = $this->getById($profileId);

        if ($profile) {
            Craft::$app->getProjectConfig()->remove(self::CONFIG_PROFILES_KEY.'.'.$profile->uid);
        }

        return true;
    }

    /**
     * Handles a deleted profile and removes it from the database
     *
     * @param ConfigEvent $event
     *
     * @throws Throwable
     */
    public function handleDeletedProfile(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $record = $this->_getRecord($uid);

        if (!$record->id) {
            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            // Delete the block type record
            $db->createCommand()
                ->delete('{{%vend_importprofiles}}', ['id' => $record->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Gets a profile’s record by uid.
     *
     * @param string $uid
     *
     * @return ImportProfileRecord
     */
    private function _getRecord(string $uid): ImportProfileRecord
    {
        $record = ImportProfileRecord::findOne(['uid' => $uid]);
        return $record ?? new ImportProfileRecord();
    }

    /**
     * Creates a profile model with attributes from the record.
     *
     * @param ImportProfileRecord|null $profileRecord
     *
     * @return ImportProfile|null
     */
    private function _createProfileFromRecord(ImportProfileRecord $profileRecord = null)
    {
        if (!$profileRecord) {
            return null;
        }

        $profile = new ImportProfile($profileRecord->toArray([
            'id',
            'name',
            'handle',
            'map',
            'uid',
        ]));

        return $profile;
    }
}