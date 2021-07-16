<?php
/**
 * Copyright 2017 Lengow SAS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @subpackage  Model
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Model\Export;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product\Interceptor as ProductInterceptor;
use Magento\Store\Model\Store\Interceptor as StoreInterceptor;

/**
 * Lengow export category
 */
class Category
{
    /**
     * @var CategoryRepository Magento category repository instance
     */
    protected $_categoryRepository;

    /**
     * @var ProductInterceptor Magento product instance
     */
    protected $_product;

    /**
     * @var StoreInterceptor Magento store instance
     */
    protected $_store;

    /**
     * @var array cache category names
     */
    protected $_cacheCategoryNames = [];

    /**
     * @var array cache category breadcrumb
     */
    protected $_cacheCategoryBreadcrumbs = [];

    /**
     * @var string category breadcrumb
     */
    protected $_categoryBreadcrumb;

    /**
     * Constructor
     *
     * @param CategoryRepository $categoryRepository Magento category repository instance
     */
    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->_categoryRepository = $categoryRepository;
    }

    /**
     * init a new category
     *
     * @param array $params optional options for load a specific product
     * StoreInterceptor store Magento store instance
     */
    public function init($params)
    {
        $this->_store = $params['store'];
    }

    /**
     * Load a new category with a specific params
     *
     * @param array $params optional options for load a specific category
     * ProductInterceptor product Magento product instance
     *
     * @throws \Exception
     */
    public function load($params)
    {
        $this->_product = $params['product'];
        $defaultCategory = $this->_getDefaultCategory();
        $this->_categoryBreadcrumb = $defaultCategory['id'] > 0
            ? $this->_getBreadcrumb((int) $defaultCategory['id'], $defaultCategory['path'])
            : '';
    }

    /**
     * Get category breadcrumb for a product
     *
     * @return string
     */
    public function getCategoryBreadcrumb()
    {
        return $this->_categoryBreadcrumb;
    }

    /**
     * Clean category for a next product
     */
    public function clean()
    {
        $this->_product = null;
        $this->_categoryBreadcrumb = null;
    }

    /**
     * Get default category id and path
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function _getDefaultCategory()
    {
        $currentLevel = 0;
        $defaultCategory = [];
        // get category collection for one product
        $categoryCollection = $this->_product->getCategoryCollection()
            ->addPathsFilter('1/' . $this->_store->getRootCategoryId() . '/')
            ->exportToArray();
        if (!empty($categoryCollection)) {
            // select category with max level by default
            foreach ($categoryCollection as $categoryArray) {
                if ($categoryArray['level'] > $currentLevel) {
                    $currentLevel = $categoryArray['level'];
                    $defaultCategory = $categoryArray;
                }
            }
        }
        return [
            'id' => isset($defaultCategory['entity_id']) ? (int) $defaultCategory['entity_id'] : 0,
            'path' => isset($defaultCategory['path']) ? $defaultCategory['path'] : '',
        ];
    }

    /**
     * Get category breadcrumb
     *
     * @param integer $categoryId Magento category id
     * @param string $categoryPath Magento category path
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function _getBreadcrumb($categoryId, $categoryPath)
    {
        if ($categoryId === 0 || $categoryPath === '') {
            return '';
        }
        $categoryNames = [];
        if (isset($this->_cacheCategoryBreadcrumbs[$categoryId])) {
            return $this->_cacheCategoryBreadcrumbs[$categoryId];
        }
        // create breadcrumb with categories
        $categoryIds = explode('/', $categoryPath);
        foreach ($categoryIds as $id) {
            // no root category in breadcrumb
            if ((int) $id !== 1) {
                $categoryNames[] = $this->_getName((int) $id);
            }
        }
        $categoryBreadcrumb = implode(' > ', $categoryNames);
        // set breadcrumb in category cache
        $this->_cacheCategoryBreadcrumbs[$categoryId] = $categoryBreadcrumb;
        return $categoryBreadcrumb;
    }

    /**
     * Get category name
     *
     * @param integer $categoryId Magento category id
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function _getName($categoryId)
    {
        if ($categoryId === 0) {
            return '';
        }
        if (isset($this->_cacheCategoryNames[$categoryId])) {
            $categoryName = $this->_cacheCategoryNames[$categoryId];
        } else {
            $category = $this->_categoryRepository->get($categoryId, $this->_store->getId());
            $name = $category->getName();
            $categoryName = $name;
            $this->_cacheCategoryNames[$categoryId] = $name;
        }
        return $categoryName;
    }
}
