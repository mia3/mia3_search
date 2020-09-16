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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $statement = $queryBuilder
	        ->select('*')
	        ->from('sys_category')
	        ->add(
	        	'where',
	        	'`parent` IN (' . $parentUids . ') AND `sys_language_uid` = 0' .
		        static::enableFields('sys_category'),
		        true
	        )
	        ->execute();

        $rows = array();
        while ($row = $statement->fetch()) {
        	$rows[$row['uid']] = $row;
        }

        $children = array_replace($children, (array)$rows);

        return $children;
    }

    public static function getCategory($uid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $row = $queryBuilder
	        ->select('*')
	        ->from('sys_category')
	        ->add(
	        	'where',
		        '`uid` IN (' . $uid . ')' .
		        static::enableFields('sys_category'),
		        true
	        )
	        ->execute()
	        ->fetch();

        return $row;
    }

    public static function getRelatedCategories($uids, $field, $tablenames = 'tt_content')
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        return $queryBuilder
	        ->select('sys_category.*')
	        ->from('sys_category')
	        ->leftJoin(
	        	'sys_category',
		        'sys_category_record_mm',
		        'sys_category_record_mm',
		        $queryBuilder->expr()->eq('sys_category.uid', $queryBuilder->quoteIdentifier('sys_category_record_mm.uid_local'))
	        )
	        ->add(
	        	'where',
		        '`fieldname` = "' . $field . '"
		        AND `tablenames` = "' . $tablenames . '"
		        AND `uid_foreign` IN (' . $uids . ') ' .
		        static::enableFields('sys_category'),
		        true
	        )
	        ->execute()
	        ->fetchAll();
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
	    $queryWhere = '`tablenames` = "' . $tableName . '"
		AND `uid_local` IN (' . $categories . ') ' .
            static::enableFields($tableName);

        if ($field !== null) {
	        $queryWhere .= ' AND `fieldname` = "' . $field . '"';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        return $queryBuilder
	        ->select($tableName . '.*')
	        ->from($tableName)
	        ->leftJoin(
		        $tableName,
		        'sys_category_record_mm',
		        'sys_category_record_mm',
		        $queryBuilder->expr()->eq($tableName . '.uid', $queryBuilder->quoteIdentifier('sys_category_record_mm.uid_foreign'))
	        )
	        ->add(
	        	'where',
		        $queryWhere,
		        true
	        )
	        ->execute()
	        ->fetchAll();
    }

    public static function enableFields($table)
    {
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        if ($pageRepository) {
            return $pageRepository->enableFields($table);
        }

        return BackendUtility::BEenableFields($table);
    }

}
