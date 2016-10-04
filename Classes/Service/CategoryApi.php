<?php
namespace MIA3\Mia3Search\Service;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class CategoryApi
 * @package MIA3\Mia3Search\Service
 */
class CategoryApi
{

    public static function getCategories($categoryUids, $includeChildren = false)
    {
        $categories = array();
        foreach (explode(',', $categoryUids) as $baseCategory) {
            $category = static::getCategory($baseCategory);
            if ($includeChildren) {
                $category['children'] = static::getChildCategories($baseCategory);
            }
            $categories[] = $category;
        }

        return $categories;
    }

    public static function expandCategoryList($categoryUids)
    {
        $keys = array_keys(static::getChildCategories($categoryUids));
        $keys[] = $categoryUids;

        return implode(',', $keys);
    }

    public static function getChildCategories($parentUids, $children = array())
    {
        $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'sys_category',
            'parent IN (' . $parentUids . ') AND sys_language_uid = 0' .
            static::enableFields('sys_category'),
            '',
            '',
            '',
            'uid'
        );
        $children = array_replace($children, (array)$rows);

        return $children;
    }

    public static function getCategory($uid)
    {
        $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            '*',
            'sys_category',
            'uid IN (' . $uid . ')' .
            static::enableFields('sys_category')
        );

        return $row;
    }

    public static function getRelatedCategories($uids, $field, $tablenames = 'tt_content')
    {
        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'sys_category.*',
            'sys_category, sys_category_record_mm',
            '
				sys_category.uid = sys_category_record_mm.uid_local
				AND fieldname = "' . $field . '"
				AND tablenames = "' . $tablenames . '"
				AND uid_foreign IN (' . $uids . ') ' .
            static::enableFields('sys_category')
        );
    }

    public function getItemsByCategories($categories, $tableName, $field = null, $limit = 10, $offset = 0)
    {
        if (is_array($categories)) {
            foreach ($categories as $key => $category) {
                if (is_array($category)) {
                    $categories[$key] = $category['uid'];
                }
            }
            $categories = implode(',', $categories);
        }
        $query = $tableName . '.uid = sys_category_record_mm.uid_foreign
		AND tablenames = "' . $tableName . '"
		AND uid_local IN (' . $categories . ') ' .
            static::enableFields($tableName);

        if ($field !== null) {
            $query .= ' AND fieldname = "' . $field . '"';
        }

        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            $tableName . '.*',
            $tableName . ', sys_category_record_mm',
            $query,
            $tableName . '.uid',
            '',
            $offset . ',' . $limit
        );
    }

    public static function enableFields($table)
    {
        if (isset($GLOBALS['TSFE']->cObj)) {
            return $GLOBALS['TSFE']->cObj->enableFields($table);
        }

        return BackendUtility::BEenableFields($table);
    }

}
