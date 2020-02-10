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

use angellco\vend\models\ParkedSale;
use angellco\vend\records\ParkedSale as ParkedSaleRecord;
use craft\base\Component;
use craft\db\Query;

/**
 * Parked Sales service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 *
 * @property array       $all
 * @property int         $total
 * @property array|int[] $allIds
 */
class ParkedSales extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var int[]|null
     */
    private $_allParkedSaleIds;

    /**
     * @var ParkedSale[]|null
     */
    private $_parkedSalesById;

    /**
     * @var bool
     */
    private $_fetchedAllParkedSales = false;


    // Public Methods
    // =========================================================================

    /**
     * Returns all of the parked sales IDs.
     *
     * @return int[]
     */
    public function getAllIds(): array
    {
        if ($this->_allParkedSaleIds !== null) {
            return $this->_allParkedSaleIds;
        }

        if ($this->_fetchedAllParkedSales) {
            return $this->_allParkedSaleIds = array_keys($this->_parkedSalesById);
        }

        return $this->_allParkedSaleIds = (new Query())
            ->select(['id'])
            ->from(['{{%vend_parkedsales}}'])
            ->column();
    }

    /**
     * Returns all parked sales.
     *
     * @return array
     */
    public function getAll(): array
    {
        if ($this->_fetchedAllParkedSales) {
            return array_values($this->_parkedSalesById);
        }

        $this->_parkedSalesById = [];

        /** @var ParkedSaleRecord[] $parkedSaleRecords */
        $parkedSaleRecords = ParkedSaleRecord::find()->all();

        foreach ($parkedSaleRecords as $parkedSaleRecord) {
            $this->_parkedSalesById[$parkedSaleRecord->id] = $this->_createParkedSaleFromRecord($parkedSaleRecord);
        }

        $this->_fetchedAllParkedSales = true;

        return array_values($this->_parkedSalesById);
    }

    /**
     * Gets the total number of parked sales.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return count($this->getAllIds());
    }

    /**
     * Returns a parked sale by its ID.
     *
     * @param int $parkedSaleId
     * @return ParkedSale|null
     */
    public function getById(int $parkedSaleId)
    {
        if ($this->_parkedSalesById !== null && array_key_exists($parkedSaleId, $this->_parkedSalesById)) {
            return $this->_parkedSalesById[$parkedSaleId];
        }

        if ($this->_fetchedAllParkedSales) {
            return null;
        }

        $parkedSaleRecord = ParkedSaleRecord::find()
            ->where(['id' => $parkedSaleId])
            ->one();

        if ($parkedSaleRecord === null) {
            return $this->_parkedSalesById[$parkedSaleId] = null;
        }

        /** @var ParkedSaleRecord $parkedSaleRecord */
        return $this->_parkedSalesById[$parkedSaleId] = $this->_createParkedSaleFromRecord($parkedSaleRecord);
    }


    // Private Methods
    // =========================================================================

    /**
     * Creates a parked sale model with attributes from the record.
     *
     * @param ParkedSaleRecord|null $parkedSaleRecord
     *
     * @return ParkedSale|null
     */
    private function _createParkedSaleFromRecord(ParkedSaleRecord $parkedSaleRecord = null)
    {
        if (!$parkedSaleRecord) {
            return null;
        }

        return new ParkedSale($parkedSaleRecord->toArray([
            'id',
            'orderId',
            'retryAfter',
            'uid',
        ]));
    }
}