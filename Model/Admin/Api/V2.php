<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product api V2
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Delight_Speedapi_Model_Admin_Api_V2 extends Mage_Catalog_Model_Api_Resource {

	/**
	 * Set indexer-state on given (or all) index-types in $list to $state
	 * index-types can be a combination of "product, categories, search, inventory"
	 *
	 * @param unknown_type $state
	 * @param array $list
	 * @return unknown_type
	 */
    public function setIndexerState($state = false, array $list = null) {
		$indexer = Mage::getModel('speedapi/admin_indexer');
		$indexer->setIndexerState($state, $list);
		/*if ($state) {
			$indexer->reindexAll();
		}*/
		return true;
	}

	/**
	 * Reindex all indexes given in $list (or all) if $state is true
	 * if $state is false, reindex all other indexes
	 *
	 * @param $state
	 * @param $list
	 * @return unknown_type
	 */
	public function reindex($state = null, array $list = null) {
		@ignore_user_abort(true);
		@set_time_limit(0);
		$indexer = Mage::getModel('speedapi/admin_indexer');
		return $indexer->reindexAll($state, $list);
	}

	/**
	 * Flush the whole cache. The Systems seams to slow down after a couple
	 * of weeks without running and updateing Products through the API daily
	 *
	 * @return boolean Always Success
	 */
	public function flushCache() {
		$indexer = Mage::getModel('speedapi/admin_indexer');
		return $indexer->flushCache();
	}
}