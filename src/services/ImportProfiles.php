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

use angellco\vend\models\ImportProfile;
use angellco\vend\records\ImportProfile as ImportProfileRecord;
use craft\base\Component;
use craft\db\Query;

/**
 * Import Profiles service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ImportProfiles extends Component
{
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
        return $this->_profilesById[$profileId] = $this->_createTargetFromRecord($profileRecord);
    }


    //  TODO
    public function save(ImportProfile $profile)
    {

    }

    public function deleteById(int $profileId)
    {

    }

    public function delete(ImportProfile $profile)
    {

    }
}