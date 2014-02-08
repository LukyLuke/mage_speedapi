<?php

class Delight_Speedapi_Model_Admin_Indexer {

	private $productIndexer = array('catalog_product_attribute','catalog_product_price','catalog_url','catalog_product_flat');
	private $categoryIndexer = array('catalog_category_flat','catalog_category_product');
	private $searchIndexer = array('catalogsearch_fulltext','tag_summary');
	private $inventoryIndexer = array('cataloginventory_stock');

	/**
	 * Enable/Disable indexing on all or on the given types
	 *
	 * @param unknown_type $state
	 * @param array $types
	 * @return unknown_type
	 */
	public function setIndexerState($state, array $types=null) {
		if (!is_bool($state)) {
			$state = true;
		}
		$mode = $state ? 'real_time' : 'manual';
		if (!$state) {
			$this->flushCache();
		}
		if (!is_array($types) || empty($types)) {
			$types = array('product','categories','search','inventory');
		}

		$collection = Mage::getResourceModel('index/process_collection');
		foreach ($collection as $process) {
			if ($this->_indexMatchesGroup($process->getIndexerCode(), $types)) {
				$process->setMode($mode)->save();
			}
		}

		// Disable also awadvancedsearch temporarly
// 		if (Mage::helper('awadvancedsearch/config')) {
// 			Mage::app()->getStore()->setConfig('awadvancedsearch/general/enable', 0);
// 		}
		return true;
	}

	/**
	 * Reset all indexer to previous state and reindex all enabled
	 * @param array $types
	 * @return unknown_type
	 */
	public function resetIndexerState(array $types) {
		$keys = array_keys($types);
		$collection = Mage::getResourceModel('index/process_collection');
		foreach ($collection as $process) {
			$code = $process->getIndexerCode();
			if (in_array($code, $keys)) {
				$process->setMode($types[$code])->save();
				if ($process->getMode() == 'real_time') {
					$process->reindexEverything();
				}
			}
		}
		// Reenable also awadvancedsearch temporarly
// 		if (Mage::helper('awadvancedsearch/config')) {
// 			Mage::app()->getStore()->setConfig('awadvancedsearch/general/enable', 1);
// 		}
	}

	/**
	 * Reindex all indexes in $types if $state is true,
	 * otherwise index all others
	 *
	 * @param unknown_type $state
	 * @param array $types
	 * @return unknown_type
	 */
	public function reindexAll($state=true, array $types=null) {
		if (!is_bool($state)) {
			$state = true;
		}
		if (!is_array($types) || empty($types)) {
			$types = array('product','categories','search','inventory');
		}

		$collection = Mage::getResourceModel('index/process_collection');
		foreach ($collection as $process) {
			if ( ($state && ($this->_indexMatchesGroup($process->getIndexerCode(), $types) || in_array($process->getIndexerCode(), $types))) ||
			     (!$state && !$this->_indexMatchesGroup($process->getIndexerCode(), $types))
			   ) {
				$process->reindexEverything();
			}
		}

		// Reindex AWAdvancedsearch
// 		if (Mage::helper('awadvancedsearch/config')) {
// 			Mage::getSingleton('awadvancedsearch/observer')->productSavePostDispatch(null);
// 		}
		return true;
	}

	/**
	 * Get all modes from all/requested indexers as an Array
	 *
	 * @param array $types
	 * @return unknown_type
	 */
	public function getIndexingModes(array $types = null) {
		$result = array();

		if (!is_array($types) || empty($types)) {
			$types = array('product','categories','search','inventory');
		}

		$collection = Mage::getResourceModel('index/process_collection');
		foreach ($collection as $process) {
			if ($this->_indexMatchesGroup($process->getIndexerCode(), $types)) {
				$result[$process->getIndexerCode()] = $process->getMode();
			}
		}
		return $result;
	}

	/**
	 * Check if the given Indexer is requested in $groups
	 *
	 * @param unknown_type $index
	 * @param unknown_type $groups
	 * @return unknown_type
	 */
	protected function _indexMatchesGroup($index, $groups) {
		if (!is_array($groups)) {
			$groups = array($groups);
		}
		return (
			(in_array($index, $this->productIndexer) && in_array('product', $groups)) ||
			(in_array($index, $this->categoryIndexer) && in_array('category', $groups)) ||
			(in_array($index, $this->searchIndexer) && in_array('search', $groups)) ||
			(in_array($index, $this->inventoryIndexer) && in_array('inventory', $groups))
		);
	}

	/**
	 * Flush the whole Cache
	 *
	 * @return boolean Always true
	 */
	public function flushCache() {
		Mage::app()->getCacheInstance()->flush();
		return true;
	}

}
