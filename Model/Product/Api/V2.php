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
class Delight_Speedapi_Model_Product_Api_V2 extends Mage_Catalog_Model_Product_Api_V2 {
	const DEBUG_TIME = true;
	const STRIP_HTML_SHORT = false; // set to true if HTML-Tags should be stripped out in short_description on products

	private $_websiteIdsCache = array();
	protected $_baseProductInfoAttributes = array('sku','set','type','categories','websites','name');
	protected $_priceAttributes = array(
		'price',
		'coast',
		'tax_class_id'
	);
	protected $_specialpriceAttributes = array(
		'special_price',
		'special_from_date',
		'special_to_date'
	);
	protected $_mediaAttributes = array(
		'image',
		'small_image',
		'thumbnail'
	);
	protected $_galleryAttributes = array(
		'label',
		'exclude',
		'disabled',
		'sort_order'
	);
	protected $_productdataAttributes = array(
		'name',
		'description',
		'short_description',
		'news_from_date',
		'news_to_date',
		'status',
		'url_key',
		'visibility'
	);
	protected $_designAttributes = array(
		'custom_design',
		'custom_design_from',
		'custom_design_to',
		'custom_layout_update',
		'page_layout',
		'options_container'
	);
	protected $_metadataAttributes = array(
		'meta_title',
		'meta_keyword',
		'meta_description'
	);
	protected $_otherAttributes = array(
		'enable_googlecheckout'
	);

	private $productIds = array();
	private $defaultWebsite = null;

	/**
	 * Allowed mime types for image
	 *
	 * @var array
	 */
	protected $_mimeTypes = array(
		'image/jpeg' => 'jpg',
		'image/gif'  => 'gif',
		'image/png'  => 'png'
	);

	public function multipleGet(array $attributes=null, stdClass $filters=null, $offset=0, $count=0) {
		$result = array();

		$collection = Mage::getModel('catalog/product')->getCollection()
			->setStoreId($this->_getStoreId(null))
			->addAttributeToSelect('name')
			->setOrder('entity_id', Varien_Data_Collection::SORT_ORDER_ASC);

		$preparedFilters = array();
		if (isset($filters->filter)) {
			foreach ($filters->filter as $_filter) {
				$preparedFilters[$_filter->key] = $_filter->value;
			}
		}
		if (isset($filters->complex_filter)) {
			foreach ($filters->complex_filter as $_filter) {
				$_value = $_filter->value;
				$preparedFilters[$_filter->key] = array( $_value->key => $_value->value );
			}
		}

		if (!empty($preparedFilters)) {
			try {
				foreach ($preparedFilters as $field => $value) {
					if (isset($this->_filtersMap[$field])) {
						$field = $this->_filtersMap[$field];
					}
					$collection->addFieldToFilter($field, $value);
				}
			} catch (Mage_Core_Exception $e) {
				$this->_fault('filters_invalid', $e->getMessage());
			}
		}

		// TODO: Debug Lukas
		//$collection->addFieldToFilter('sku', 'SPEED-API-5');

		// Check for default attributes
		if (!is_array($attributes)) {
			$attributes = array();
		}
		if (!in_array('sku', $attributes)) {
			$attributes[] = 'sku';
		}

		// Cache Websites
		$websiteIdList = array();
		$websiteNameList = array();

		// Check for Paging-request
		if ($count > 0) {
			$page = ((int)floor($offset / $count)) + 1;
			$collection->setPage($page, $count);
		}

		// If a page later the last one is called, just return an empty Resultset
		if ($collection->getLastPageNumber() < $page) {
			return $result;
		}

		// Get all Products from requested Page
		foreach ($collection as $product) {
			$product->load($product->getId());
			$res = new stdClass();
			foreach ($attributes as $attr) {
				switch ($attr) {
					case 'sku':
						$res->sku = $product->getSku();
						break;

					case 'set':
						$res->set = $product->getAttributeSetId();
						break;

					case 'type':
						$res->type = $product->getTypeId();
						break;

					case 'categories':
						$res->categories = $product->getCategoryIds();
						break;

					case 'websites':
						$res->websites = array();
						foreach ($product->getWebsiteIds() as $id) {
							if (!in_array($id, $websiteIdList)) {
								$w = $this->_getWebsite($id);
								$websiteIdList[] = $id;
								$websiteNameList[] = $w->getCode();
							}
							$res->websites[] = $websiteNameList[array_search($id, $websiteIdList)];
						}
						break;

					case 'tier_prices':
						$res->tier_prices = array();
						foreach ($product->getTierPrice() as $data) {
							$price = new stdClass();
							$price->price = $data['price'];
							$price->qty = $data['price_qty'];
							$price->customer_group_id = ($data['cust_group'] == Mage_Customer_Model_Group::CUST_GROUP_ALL) ? 'all' : $data['cust_group'];
							if (!in_array($data['website_id'], $websiteIdList)) {
								$w = $this->_getWebsite($data['website_id']);
								$websiteIdList[] = $data['website_id'];
								$websiteNameList[] = $w->getCode();
							}
							$price->website = $websiteNameList[array_search($data['website_id'], $websiteIdList)];

							$res->tier_prices[] = $price;
						}
						break;

					case 'website_prices':
						break;

					case 'special_prices':
						break;

					case 'media_list':
						$res->media_list = array();
						$mediaAttributes = $product->getTypeInstance(true)->getSetAttributes($product);
						if (isset($mediaAttributes['media_gallery'])) {
							$gallery = $product->getData('media_gallery');
							if (is_array($gallery) && array_key_exists('images', $gallery)) {
								foreach ($gallery['images'] as $image) {
									$obj = new stdClass();
									$obj->label = $image['label'];
									$obj->position = (int)$image['position'];
									$obj->exclude = $image['disabled'];
									$obj->download_url = Mage::app()->getStore($product->getStore())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product'.$image['file'];

									$types = array();
									foreach (array('image','thumbnail','small_image') as $imageType) {
										if ($product->getData($imageType) == $image['file']) {
											$types[] = $imageType;
										}
									}
									if (!empty($types)) {
										$obj->types = $types;
									}

									$filedata = pathinfo($image['file']);
									$file = new stdClass();
									$file->mime = array_search($filedata['extension'], $this->_mimeTypes);
									$file->name = $filedata['filename'];
									$file->content = '';
									$obj->file = $file;

									$res->media_list[] = $obj;
								}

							}
						}
						break;

					case 'link_list':
						break;

					case 'meta_informations':
						$obj = new stdClass();
						$obj->website = '';
						$obj->meta_title = $product->getData('meta_title');
						$obj->meta_keyword = $product->getData('meta_keyword');
						$obj->meta_description = $product->getData('meta_description');
						$res->meta_informations = array($obj);
						break;

					case 'design_parameters':
						$obj = new stdClass();
						$obj->website = '';
						$obj->custom_design = $product->getData('custom_design');
						$obj->custom_design_from = $product->getData('custom_design_from');
						$obj->custom_design_to = $product->getData('custom_design_to');
						$obj->custom_layout_update = $product->getData('custom_layout_update');
						$obj->page_layout = $product->getData('page_layout');
						$res->design_parameters = array($obj);
						break;

					case 'product_data':
						$obj = new stdClass();
						$obj->website = '';
						$obj->name = $product->getData('name');
						$obj->description = $product->getData('description');
						$obj->short_description = $product->getData('short_description');
						$obj->news_from_date = $product->getData('news_from_date');
						$obj->news_to_date = $product->getData('news_to_date');
						$obj->status = $product->getData('status');
						$obj->url_key = $product->getData('url_key');
						$obj->visibility = $product->getData('visibility');
						$res->product_data = array($obj);
						break;

					default:
						$res->$attr = $product->getData($attr);
						break;
				}
			}
			$result[] = $res;
			//break; // TODO: Lukas debug
		}

		return $result;
	}

	/**
	 * Create or Update multiple Products in one Request
	 *
	 * @param array $products Array of stdClass-Products
	 * @return array
	 */
	public function multiple($products) {
		// Get all INdexing-Modes and disable it (enabled after all products are uploaded)
		$indexer = Mage::getModel('speedapi/admin_indexer');
		$indexStates = $indexer->getIndexingModes();
		$indexer->setIndexerState(false);

		// Upload all Products
		$result = array();
		foreach ($products as $productData) {
			$timeStart = microtime(true);

			if (self::DEBUG_TIME) {
				$timeDebug = microtime(true);
				$times = array('load'=>0, 'websites'=>0, 'stock'=>0, 'attrib'=>0, 'media'=>0, 'additional'=>0, 'save'=>0);
			}
			if (property_exists($productData, 'tier_prices')) { // we use this in our products
				$productData->tier_price = $productData->tier_prices;
			}
			if (property_exists($productData, 'tier_price')) { // this is the original Mage-Tierprice which has to be fixed
				if (Mage::helper('delightapi')->isPrevious16()) {
					$productData->tier_price = $this->_prepareTierPrices($productData->tier_price);
				}
			}

			// If a Product failes to create, try all others
			$res = new stdClass();
			try {
				// Try to load the Product
				try {
					$product = $this->_getProduct($productData->sku);
					if (Mage::helper('delightapi')->isPrevious16() && !$product->getId()) {
						throw new Exception('product_not_exists');
					}
				} catch (Exception $e) {
					$this->_checkPropertyValidity($productData);

					$product = Mage::getModel('catalog/product');
					/* @var $product Mage_Catalog_Model_Product */
					$product->setStoreId($this->_getStoreId(null))
						->setAttributeSetId($productData->set)
						->setTypeId($productData->type)
						->setSku($productData->sku);
				}
				if (self::DEBUG_TIME) {
					$times['load'] = microtime(true) - $timeDebug;
					$timeDebug = microtime(true);
				}

				// synchronize website_ids and websites on productData
				if (property_exists($productData, 'websites') && !is_array($productData->websites)) {
					$productData->websites = array();
				}
				if (property_exists($productData, 'website_ids') && !is_array($productData->website_ids)) {
					$productData->website_ids = array();
				}

				if (!Mage::helper('delightapi')->isPrevious15()) {
					if (property_exists($productData, 'category_ids')) {
						$productData->categories = $productData->category_ids;
					}
				}

				// Only set websites if the property websites or website_ids is set on the request
				// Reason: If the arrays are not set, leave it untouched, if they are set but empty, remove the product from all websites
				if (property_exists($productData, 'websites') || property_exists($productData, 'website_ids')) {
					$productData->websiteIds = array();
					$productData->websiteNames = array();

					foreach ($this->_getWebsiteIds($productData, false) as $name => $id) {
						if ((property_exists($productData, 'websites') && in_array($name, $productData->websites))
							|| (property_exists($productData, 'website_ids') && in_array($id, $productData->website_ids)) ) {
							$productData->websiteIds[] = $id;
							$productData->websiteNames[] = $name;
						}
					}
					$product->setWebsiteIds( $productData->websiteIds );
					$productData->websites = $productData->websiteNames;
				}
				if (self::DEBUG_TIME) {
					$times['websites'] = microtime(true) - $timeDebug;
					$timeDebug = microtime(true);
				}

				$productData->stock_data = $this->_prepareStockData($productData);
				if (self::DEBUG_TIME) {
					$times['stock'] = microtime(true) - $timeDebug;
					$timeDebug = microtime(true);
				}

				if (property_exists($productData, 'additional_attributes')) {
					foreach ($productData->additional_attributes as $_attribute) {
						$_attrCode = $_attribute->key;
						$productData->$_attrCode = $_attribute->value;
					}
					unset($productData->additional_attributes);
				}

				/* Testing because of strange side-effects on special-prices
				$productData->special_price = null;
				$productData->special_from_date = null;
				$productData->special_to_date = null;*/

				foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
					$_attrCode = $attribute->getAttributeCode();
					if ($this->_isAllowedAttribute($attribute) && isset($productData->$_attrCode)) {
						// Strip out HTML-Tags from shortDescription so we can use the default template
						if (self::STRIP_HTML_SHORT && ($_attrCode == 'short_description')) {
							$productData->short_description = strip_tags($productData->short_description);
						}

						// Set the Attribute on the Product
						$product->setData( $attribute->getAttributeCode(), $productData->$_attrCode );
					}
				}
				if (self::DEBUG_TIME) {
					$times['attrib'] = microtime(true) - $timeDebug;
					$timeDebug = microtime(true);
				}

				// Prepare the Return-Value
				$res->product = new stdClass();
				$res->product->identifier = $productData->sku;
				$res->product->success = true;
				$res->product->website = Mage::app()->getStore(0)->getCode();
				$res->media_list = array();

				// Save main product
				$this->_prepareDataForSave($product, $productData);
				if (is_array($errors = $product->validate())) {
					$res->product->error = implode("\n", $errors);
					$res->product->success = false;
				} else {
					try {
						$res->media_list = $this->_updateMediaList($product, $productData);
						if (self::DEBUG_TIME) {
							$times['media'] = microtime(true) - $timeDebug;
							$timeDebug = microtime(true);
						}

						$product->save();
						$this->productIds[] = $product->getId();
						if (self::DEBUG_TIME) {
							$times['save'] = microtime(true) - $timeDebug;
							$timeDebug = microtime(true);
						}

					} catch (Mage_Core_Exception $e) {
						$res->product->error = $e->getMessage();
						$res->product->success = false;
					}
				}

				// If the Main-Product could be saved, update Website-Parameters from this Product
				if ($res->product->success) {
					$res->website_attributes = $this->_updateAdditionalParameters($product, $productData);
				}
				if (self::DEBUG_TIME) {
					$times['additional'] = microtime(true) - $timeDebug;
					$timeDebug = microtime(true);
				}

			} catch (Exception $e) {
				$res->product = new stdClass();
				$res->product->website = Mage::app()->getStore(0)->getCode();
				if (!property_exists($productData, 'sku')) {
					$res->product->identifier = $productData->sku;
				} else {
					$res->product->identifier = 'undefined';
				}
				$res->product->success = false;
				$res->product->error = $e->getMessage();
			}
			$res->product->error .= chr(10).'Time: '.(microtime(true)-$timeStart);
			if (self::DEBUG_TIME) {
				foreach ($times as $k=>$v) {
					$res->product->error .= chr(10).'    '.$k.': '.$v;
				}
			}
			$result[] = $res;
			//break; // TODO: Only one Product for Debugging...
		}

		foreach ($this->productIds as $id) {
			Mage::app()->cleanCache('catalog_product_'.$id);
		}

		// Reenable indexer and run indexing
		$indexer->resetIndexerState($indexStates);

		return $result;
	}

	/**
	 * Update the Products Media-List
	 *
	 * @param Mage_Catalog_Model_Product $product
	 * @param stdClass $productData
	 * @return array <multitype:, stdClass>
	 */
	protected function _updateMediaList(Mage_Catalog_Model_Product $product, stdClass $productData) {
		$state = array();

		if (property_exists($productData, 'media_list')) {
			$mediaCount = -1;

			foreach ($productData->media_list as $media) {
				$mediaCount++;

				// Check for all needed properties
				if (!property_exists($media, 'file')) {
					$media->file = new stdClass();
					$media->file->name = $product->getSku().'-'.$mediaCount;
				}

				// Check for a valid Filename
				if (empty($media->file->name)) {
					$media->file->name = $product->getSku().'-'.$mediaCount;
				}
				$media->file->name = strtolower($media->file->name);

				// Check for a valid Mime-Type to create the Unique-Filename
				$media->filename = 'nonexistent';
				if (!isset($this->_mimeTypes[$media->file->mime])) {
					$obj->error = Mage::helper('catalog')->__('Invalid image type.');
					$obj->success = false;
				} else {
					$media->filename = Varien_File_Uploader::getDispretionPath($media->file->name).DS.$media->file->name.'.'.$this->_mimeTypes[$media->file->mime];
				}

				// The Return-Object
				$obj = new stdClass();
				$obj->success = true;
				$obj->identifier = $media->file->name;
				$obj->error = '';
				$obj->website = $this->_getDefaultWebsite()->getCode();

				// Check if this Product has the Ability to use images
				$attributes = $product->getTypeInstance(true)->getSetAttributes($product);
				if (isset($attributes['media_gallery'])) {
					$gallery = $attributes['media_gallery'];

					// Update the Image itselfs if the properties file->mime and file->content are set
					if ($obj->success && property_exists($media->file, 'content')) {
						$fileContent = @base64_decode($media->file->content, true);
						if (!$fileContent) {
							$obj->error = Mage::helper('catalog')->__('Image content is not valid base64 data.');
							$obj->success = false;
						}
						unset($media->file->content);

						if ($obj->success) {
							$ioAdapter = new Varien_Io_File();

							// Remove the Original File
							$fileName = Mage::getBaseDir(Mage_Core_Model_Store::URL_TYPE_MEDIA).DS.'catalog'.DS.'product'.$media->filename;
							$ioAdapter->checkAndCreateFolder(dirname($fileName));
							$ioAdapter->open(array('path' => dirname($fileName)));
							if ($ioAdapter->fileExists(basename($fileName), true)) {
								$ioAdapter->rm(basename($fileName));
							}

							// Remove tmp files
							$fileName = Mage::getSingleton('catalog/product_media_config')->getTmpMediaPath($media->filename);
							$ioAdapter->checkAndCreateFolder(dirname($fileName));
							$ioAdapter->open(array('path' => dirname($fileName)));
							if ($ioAdapter->fileExists(basename($fileName))) {
								$ioAdapter->rm(basename($fileName));
							}

							// Media-Update
							if ($gallery->getBackend()->getImage($product, $media->filename)) {
								try {
									$fileName = Mage::getBaseDir(Mage_Core_Model_Store::URL_TYPE_MEDIA).DS.'catalog'.DS.'product'.$media->filename;
									$ioAdapter->open(array('path' => dirname($fileName)));
									if ($ioAdapter->fileExists(basename($fileName), true)) {
										$ioAdapter->rm(basename($fileName));
									}
									$ioAdapter->write(basename($fileName), $fileContent, 0666);
									unset($fileContent);

								} catch(Exception $e) {
									$obj->success = false;
									$obj->error = $e->getMessage();
								}

							// Media-Create
							} else {
								$tmpDirectory = Mage::getBaseDir('var').DS.'api'.DS.$this->_getSession()->getSessionId();
								$fileName = basename($media->filename);

								try {
									$ioAdapter->checkAndCreateFolder($tmpDirectory);
									$ioAdapter->open(array('path' => $tmpDirectory));
									if ($ioAdapter->fileExists($fileName, true)) {
										$ioAdapter->rm($fileName);
									}
									$ioAdapter->write($fileName, $fileContent, 0666);
									unset($fileContent);

									$gallery->getBackend()->addImage(
										$product,
										$tmpDirectory.DS.$fileName,
										null,
										true
									);
									$ioAdapter->rmdir($tmpDirectory, true);

								} catch(Exception $e) {
									$obj->error = $e->getMessage();
									$obj->success = false;
								}
							}
						}
					}

					if ($obj->success) {
						if (!property_exists($media, 'exclude')) {
							$media->exclude = 0;
						}
						$_imageData = $this->_prepareImageData($media);
						$gallery->getBackend()->updateImage($product, $media->filename, $_imageData);

						if (isset($media->types) && is_array($media->types)) {
							$oldTypes = array();
							foreach ($product->getMediaAttributes() as $attribute) {
								if ($product->getData($attribute->getAttributeCode()) == $media->filename) {
									$oldTypes[] = $attribute->getAttributeCode();
								}
							}
							$clear = array_diff($oldTypes, $media->types);
							if (count($clear) > 0) {
								$gallery->getBackend()->clearMediaAttribute($product, $clear);
							}
							$gallery->getBackend()->setMediaAttribute($product, $media->types, $media->filename);
						}
					}

				} else {
					$obj->error = 'Product-Type does not support "media_gallery"';
					$obj->success = false;
				}
				$state[] = $obj;
			}
		}
		return $state;
	}

	/**
	 * Check for all needed Attributes to create a new Product
	 *
	 * @param stdClass $productData submitted ProductData
	 * @access protected
	 */
	protected function _checkPropertyValidity(stdClass $productData) {
		if (!property_exists($productData, 'sku')) {
			$this->_fault('data_invalid','Attribute "sku" needed');
		}
		if (!property_exists($productData, 'set')) {
			$this->_fault('data_invalid','Attribute "set" needed');
		}
		if (!property_exists($productData, 'type')) {
			$this->_fault('data_invalid','Attribute "type" needed');
		}
	}

	/**
	 * Update additional attributes which are not included in the default update/create procedure
	 *
	 * @param Mage_Catalog_Model_Product $product the product to add additional parameters
	 * @param stdClass $productData
	 * @return array [ attributeName=>success [, ...] ]
	 * @access protected
	 */
	protected function _updateAdditionalParameters(Mage_Catalog_Model_Product $product, stdClass $productData) {
		$doStore = false;
		$state = array();
		$defaultStoreId = $this->_getDefaultWebsite()->getDefaultStore()->getId();
		$productId = $product->getId();

		// add additional information for each website
		foreach ($this->_getWebsiteIds($productData) as $storeName => $storeId) {
			$isDefault = $storeId == $defaultStoreId;
			$data = new stdClass();

			// Load the Product from the given StoreView
			$product->setStoreId($storeId);
			$product->load($productId);

			// Remove all values first if this is not the "default"-Website
			// Remove them only if the Property exists, if it doesn't, dont touch the values
			if (!$isDefault) {
				foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
					$_attrCode = $attribute->getAttributeCode();
					// Prices
					if (property_exists($productData, 'website_prices') && in_array($_attrCode, $this->_priceAttributes)) {
						$data->$_attrCode = null;
					}

					// Special-Prices
					if (property_exists($productData, 'special_prices') && in_array($_attrCode, $this->_specialpriceAttributes)) {
						$data->$_attrCode = null;
					}

					// Meta-Informations
					if (property_exists($productData, 'meta_informations') && in_array($_attrCode, $this->_metadataAttributes)) {
						$data->$_attrCode = null;
					}

					// Design-Parameters
					if (property_exists($productData, 'design_parameters') && in_array($_attrCode, $this->_designAttributes)) {
						$data->$_attrCode = null;
					}

					// Global Datas
					if (property_exists($productData, 'product_data') && in_array($_attrCode, $this->_productdataAttributes)) {
						$data->$_attrCode = null;
					}

					// Other datas
					if (property_exists($productData, 'other_attributes') && in_array($_attrCode, $this->_otherAttributes)) {
						$data->$_attrCode = null;
					}
				}
			}

			// Check for Media-List types
			if (!$isDefault && property_exists($productData, 'media_list')) {
				$attributes = $product->getTypeInstance(true)->getSetAttributes($product);
				if (isset($attributes['media_gallery'])) {
					$gallery = $attributes['media_gallery'];

					// Get current Media-Types to set them after we get the new ones
					$updateMediaTypes = false;
					$productMediaTypes = array('image'=>null, 'thumbnail'=>null, 'small_image'=>null);
					foreach ($this->_mediaAttributes as $_attr) {
						$productMediaTypes[$_attr] = $product->getData($_attr);
					}

					$mediaCount = -1;
					foreach ($productData->media_list as $media) {
						$mediaCount++;
						if (property_exists($media, 'website_data') && is_array($media->website_data)) {
							$updateMediaTypes = true;

							// Check for all needed properties
							if (!property_exists($media, 'file')) {
								$media->file = new stdClass();
								$media->file->name = $product->getSku().'-'.$mediaCount;
							}

							// Check for a valid Filename
							if (empty($media->file->name)) {
								$media->file->name = $product->getSku().'-'.$mediaCount;
							}
							$media->file->name = strtolower($media->file->name);

							// Check for a valid Mime-Type to create the Unique-Filename
							if (isset($this->_mimeTypes[$media->file->mime])) {
								$media->filename = Varien_File_Uploader::getDispretionPath($media->file->name).DS.$media->file->name.'.'.$this->_mimeTypes[$media->file->mime];
								//$gallery->getBackend()->clearMediaAttribute($product, $this->_mediaAttributes);

								foreach ($media->website_data as $mediadata) {
									if (!isset($mediadata->website)) {
										$mediadata->website = $this->_getDefaultWebsite()->getCode();
									}
									if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $mediadata->website)) {
										$imageData = array('label'=>null, 'exclude'=>null, 'sort_order'=>null, 'position'=>null);

										// Update Imagedata for the given Website
										if (property_exists($mediadata, 'label')) {
											$imageData['label'] = $mediadata->label;
										}
										if (property_exists($mediadata, 'exclude')) {
											$imageData['exclude'] = $mediadata->exclude;
										}
										if (property_exists($mediadata, 'sort_order')) {
											$imageData['position'] = $mediadata->sort_order;
										}
										$gallery->getBackend()->updateImage($product, $media->filename, $imageData);

										// Get new types
										if (property_exists($mediadata, 'use_as_image')) {
											$productMediaTypes['image'] = $media->filename;
										}
										if (property_exists($mediadata, 'use_as_thumbnail')) {
											$productMediaTypes['thumbnail'] = $media->filename;
										}
										if (property_exists($mediadata, 'use_as_smallimage')) {
											$productMediaTypes['small_image'] = $media->filename;
										}
										break;
									}
								}

							}
						}
					}

					// Update ProductMedia-Types
					if ($updateMediaTypes) {
						$doStore = true;
						foreach ($productMediaTypes as $_attr => $file) {
							$product->setData($_attr, $file);
						}
					}

				}
			}

			// Check for Website based Prices
			if (property_exists($productData, 'website_prices')) {
				foreach ($productData->website_prices as $attribute) {
					if (!isset($attribute->website)) {
						$attribute->website = $this->_getDefaultWebsite()->getCode();
					}
					if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $attribute->website)) {
						$data->price = $attribute->price;
						if (property_exists($attribute, 'tax_class')) {
							$data->tax_class_id = $attribute->tax_class;
						}
					}
				}
			}

			// Check for Website based Special-Prices
	    	if (property_exists($productData, 'special_prices')) {
				foreach ($productData->special_prices as $attribute) {
					if (!isset($attribute->website)) {
						$attribute->website = $this->_getDefaultWebsite()->getCode();
					}
					if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $attribute->website)) {
						if (property_exists($attribute, 'from') && property_exists($attribute, 'to')) {
							$data->special_price = $attribute->price;
							$data->special_from_date = $attribute->from;
							$data->special_to_date = $attribute->to;
						}
					}
				}
			}

			// Check for Meta-Informations
			if (property_exists($productData, 'meta_informations')) {
				foreach ($productData->meta_informations as $attribute) {
					if (!isset($attribute->website)) {
						$attribute->website = $this->_getDefaultWebsite()->getCode();
					}
					if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $attribute->website)) {
						foreach ($this->_metadataAttributes as $attr) {
							if (property_exists($attribute, $attr)) {
								$data->{$attr} = $attribute->$attr;
							}
						}
					}
				}
			}

			// Check for ProductData Attributes
			if (property_exists($productData, 'product_data')) {
				foreach ($productData->product_data as $attribute) {
					if (!isset($attribute->website)) {
						$attribute->website = $this->_getDefaultWebsite()->getCode();
					}
					if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $attribute->website)) {
						foreach ($this->_productdataAttributes as $attr) {
							if (property_exists($attribute, $attr)) {
								if ($attr == 'status') {
									$data->status = $attribute->status ? 1 : 2;

								} else if (self::STRIP_HTML_SHORT && ($attr == 'short_description')) {
									// Strip out HTML-Tags from ShortDescription so we can use the default template
									$data->short_description = strip_tags($attribute->short_description);

								} else {
									$data->{$attr} = $attribute->$attr;
								}
							}
						}
					}
				}
			}

			// Check for Design-Parameters
			if (property_exists($productData, 'design_parameters')) {
				foreach ($productData->design_parameters as $attribute) {
					if (!isset($attribute->website)) {
						$attribute->website = $this->_getDefaultWebsite()->getCode();
					}
					if ($this->_attributeNeedsUpdate($isDefault, $storeId, $storeName, $attribute->website)) {
						foreach ($this->_designAttributes as $attr) {
							if (property_exists($attribute, $attr)) {
								if ($attr == 'options_container') {
									$data->options_container = 'container'.$attribute->options_container;
								} else {
									$data->{$attr} = $attribute->$attr;
								}
							}
						}
					}
				}
			}

			// Check for Links


			// Set Product-Attributes
			foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
				$_attrCode = $attribute->getAttributeCode();
				//if ($this->_isAllowedAttribute($attribute) && isset($data->$_attrCode)) {
				if (isset($data->$_attrCode)) {
					//$product->setData( $attribute->getAttributeCode(), $data->$_attrCode );
					$product->setData( $_attrCode, $data->$_attrCode );
					$doStore = true;
				}
			}
			$this->_prepareDataForSave($product, $data);

			// Save the Product for this Website/Storeview
			$obj = new stdClass();
			$obj->identifier = $product->getSku();
			$obj->success = true;
			$obj->error = '';
			$obj->website = $storeName;
			try {
				if ($doStore) {
					$product->save();
				}
			} catch (Mage_Core_Exception $e) {
				$obj->success = false;
				$obj->error = $e->getCustomMessage();
			}
			//$obj->error .= $debugErrorMessage;
			$state[] = $obj;
		}
		return $state;
	}

	/**
	 * Check if the $attributeWebsite website is same as $storeId or $storeName or if it is the default website
	 * @param unknown_type $isDefault
	 * @param unknown_type $storeId
	 * @param unknown_type $storeName
	 * @param unknown_type $attributeWebsite
	 * @return boolean
	 */
	protected function _attributeNeedsUpdate($isDefault, $storeId, $storeName, $attributeWebsite) {
		return (($isDefault && ((is_numeric($attributeWebsite) && ($attributeWebsite == 0)) || ($attributeWebsite == 'all')))
		     || (is_numeric($attributeWebsite) && ($attributeWebsite == $storeId)) || ($attributeWebsite == $storeName));
	}

	/**
	 * Prepare data to create or update image
	 *
	 * @param stdClass $data
	 * @return array
	 */
	protected function _prepareImageData($data) {
		$_imageData = array();
		if (isset($data->label)) {
			$_imageData['label'] = $data->label;
		}
		if (isset($data->position)) {
			$_imageData['position'] = $data->position;
		}
		if (isset($data->disabled)) {
			$_imageData['disabled'] = $data->disabled;
		}
		if (isset($data->exclude)) {
			$_imageData['exclude'] = $data->exclude;
		}
		return $_imageData;
	}

	/**
	 * Collect all website-id's from all subproperties which can include website-attributes
	 *
	 * @param stdClass $productData
	 * @return unknown_type
	 */
	protected function _getWebsiteIds(stdClass $productData, $storeViewIds=true) {
		// Don't get all websites on each request
		if (!is_array($this->_websiteIdsCache) || (count($this->_websiteIdsCache) < 2)) {
			$this->_websiteIdsCache = array(0=>array(), 1=>array());
		}
		if (!$storeViewIds && (count($this->_websiteIdsCache[0]) > 0) ) {
			return $this->_websiteIdsCache[0];
		} else if ($storeViewIds && (count($this->_websiteIdsCache[1]) > 0) ) {
			return $this->_websiteIdsCache[1];
		}

		// Get all Website IDs
		$list = array();
		if (property_exists($productData, 'website_ids')) {
			foreach ($productData->website_ids as $id) {
				$w = $this->_getWebsite($id);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}

		if (property_exists($productData, 'websites')) {
			foreach ($productData->websites as $id) {
				$w = $this->_getWebsite($id);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}

		if (property_exists($productData, 'website_prices')) {
			foreach ($productData->website_prices as $p) {
				$w = $this->_getWebsite($p->website);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}

		if (property_exists($productData, 'special_prices')) {
			foreach ($productData->special_prices as $p) {
				$w = $this->_getWebsite($p->website);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}

		if (property_exists($productData, 'media_list')) {
			foreach ($productData->media_list as $p) {
				$w = $this->_getWebsite($p->website);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}

		if (property_exists($productData, 'link_list')) {
			foreach ($productData->link_list as $p) {
				$w = $this->_getWebsite($p->website);
				$list[$w->getCode()] = $storeViewIds ? $w->getDefaultStore()->getId() : $w->getId();
			}
		}
		$this->_websiteIdsCache[$storeViewIds ? 1 : 0] = $list;
		return $list;
	}

	/**
		* Get the Website-Object Mage_Core_Website from given ID/Code
		*
		* @param int/string $id The WebsiteID or Code ("0" or "all" means the default-Website)
		* @return Mage_Core_Model_Website
		*/
	protected function _getWebsite($id=null) {
		if (is_null($id) || (is_numeric($id) && ($id == 0)) || (is_string($id) && ($id == 'all')) ) {
			return $this->_getDefaultWebsite();
		}
		return Mage::app()->getWebsite($id);
	}

	/**
		* Get the Default Website-ID
		* @return unknown_type
		*/
	protected function _getDefaultWebsite() {
		if (is_null($this->defaultWebsite)) {
			foreach (Mage::app()->getWebsites() as $w) {
				if ($w->getIsDefault()) {
					$this->defaultWebsite = $w;
					break;
				}
			}
		}
		if (!is_null($this->defaultWebsite)) {
			return $this->defaultWebsite;
		}
		$this->_fault('no_default_website', 'No default Website found');
	}

	/**
	 * Parse tier_price and tier_prices request parameters and prepare all data so magento could validate them
	 *
	 * @param array $tierPrices
	 * @return array()
	 */
	protected function _prepareTierPrices($tierPrices=null) {
		if (!is_array($tierPrices)) {
			return array();
		}
		$prices = array();
		foreach ($tierPrices as $tierPrice) {
			if (!is_object($tierPrice)
			    || !isset($tierPrice->qty)
			    || !isset($tierPrice->price)) {
				$this->_fault('tierprice_data_invalid', Mage::helper('catalog')->__('Invalid Tier Prices'));
			}

			if (!isset($tierPrice->website) || $tierPrice->website == 'all') {
				$tierPrice->website = 0;
			} else {
				try {
					$tierPrice->website = Mage::app()->getWebsite($tierPrice->website)->getId();
				} catch (Mage_Core_Exception $e) {
					$tierPrice->website = 0;
				}
			}

			/*if (intval($tierPrice->website) > 0 && !in_array($tierPrice->website, $product->getWebsiteIds())) {
				$this->_fault('data_invalid', Mage::helper('catalog')->__('Invalid tier prices. Product is not associated to the requested website.'));
			}*/

			if (!isset($tierPrice->customer_group_id)) {
				$tierPrice->customer_group_id = 'all';
			}

			if ($tierPrice->customer_group_id == 'all') {
				$tierPrice->customer_group_id = Mage_Customer_Model_Group::CUST_GROUP_ALL;
			}

			$prices[] = array(
				'website_id' => $tierPrice->website,
				'cust_group' => $tierPrice->customer_group_id,
				'price_qty'  => $tierPrice->qty,
				'price'      => $tierPrice->price
			);
		}
		return $prices;
	}

	/**
	 * Prepare stock-data to use the given values or if no values are given, just use the config-ones
	 *
	 * @param stdClass $productData
	 * @return stdClass
	 */
	protected function _prepareStockData(stdClass $productData) {
		$data = new stdClass();
		if (!property_exists($productData, 'stock_data')) {
			$productData->stock_data = new stdClass();
		}

		if (property_exists($productData->stock_data, 'qty')) {
			$data->qty = $productData->stock_data->qty;
		}
		if (property_exists($productData->stock_data, 'is_in_stock')) {
			$data->is_in_stock = $productData->stock_data->is_in_stock;
		}

		if (property_exists($productData->stock_data, 'use_config_manage_stock')) {
			$data->use_config_manage_stock = $productData->stock_data->use_config_manage_stock;
		}
		if (property_exists($productData->stock_data, 'manage_stock')) {
			$data->manage_stock = $productData->stock_data->manage_stock;
			$data->use_config_manage_stock = 0;
		}

		if (property_exists($productData->stock_data, 'use_config_min_qty')) {
			$data->use_config_min_qty = $productData->stock_data->use_config_max_sale_qty;
		}
		if (property_exists($productData->stock_data, 'min_qty')) {
			$data->min_qty = $productData->stock_data->min_qty;
			$data->use_config_min_qty = 0;
		}

		if (property_exists($productData->stock_data, 'use_config_min_sale_qty')) {
			$data->use_config_min_sale_qty = $productData->stock_data->use_config_min_sale_qty;
		}
		if (property_exists($productData->stock_data, 'min_sale_qty')) {
			$data->min_sale_qty = $productData->stock_data->min_sale_qty;
			$data->use_config_min_sale_qty = 0;
		}

		if (property_exists($productData->stock_data, 'use_config_max_sale_qty')) {
			$data->use_config_max_sale_qty = $productData->stock_data->use_config_max_sale_qty;
		}
		if (property_exists($productData->stock_data, 'max_sale_qty')) {
			$data->max_sale_qty = $productData->stock_data->max_sale_qty;
			$data->use_config_max_sale_qty = 0;
		}

		if (property_exists($productData->stock_data, 'use_config_min_sale_qty')) {
			$data->use_config_backorders = $productData->stock_data->use_config_min_sale_qty;
		}
		if (property_exists($productData->stock_data, 'backorders')) {
			$data->backorders = $productData->stock_data->backorders;
			$data->use_config_backorders = 0;
		}

		if (property_exists($productData->stock_data, 'use_config_notify_stock_qty')) {
			$data->use_config_notify_stock_qty = $productData->stock_data->use_config_notify_stock_qty;
		}
		if (property_exists($productData->stock_data, 'notify_stock_qty')) {
			$data->notify_stock_qty = $productData->stock_data->notify_stock_qty;
			$data->use_config_notify_stock_qty = 0;
		}

		return $data;
	}
}
