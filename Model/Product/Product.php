<?php
class Delight_Speedapi_Model_Product_Product extends Mage_Catalog_Model_Product {

	public function cleanCache() {
		//Mage::app()->cleanCache('catalog_product_'.$this->getId());
		return $this;
	}

	public function save() {
		return parent::save();

		// for time-debuging
		$t = microtime(true);
		if ($this->isDeleted()) {
			return $this->delete();
		}
		$this->_getResource()->beginTransaction();
		echo 'beginTransaction '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
		$dataCommited = false;
		try {
			$this->_beforeSave();
			echo 'beforeSave '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
			if ($this->_dataSaveAllowed) {
				$this->_getResource()->save($this);
				echo 'save '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
				$this->_afterSave();
				echo 'afterSave '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
			}
			$res = $this->_getResource()->addCommitCallback(array($this, 'afterCommitCallback'));
			echo 'addCommitCallback '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
			$res->commit();
			echo 'commit '; echo microtime(true)-$t; echo '<br>'; $t = microtime(true);
			$dataCommited = true;
		} catch (Exception $e) {
			$this->_getResource()->rollBack();
			throw $e;
		}
		if ($dataCommited) {
			$this->_afterSaveCommit();
		}

		echo 'afterSaveCommit '; echo microtime(true)-$t; die();

		return $this;
	}
}