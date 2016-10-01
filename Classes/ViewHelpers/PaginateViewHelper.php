<?php
namespace MIA3\Mia3Search\ViewHelpers;

use MIA3\Saku\SearchResults;


/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Marc Neuhaus <mneuhaus@famelo.com>, Famelo
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class PaginateViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {
	/**
	 * Initialize arguments.
	 *
	 * @return void
	 */
	public function initializeArguments() {
		parent::initializeArguments();
		$this->registerArgument('items', '\TYPO3\CMS\Extbase\Persistence\Generic\QueryResult', 'QueryResult to paginate', TRUE);
		$this->registerArgument('as', 'string', 'name of the variable that will contain the paginated QueryResult', FALSE, 'paginatedItems');
		$this->registerArgument('limitAs', 'string', 'name of the variable that will contain the relevant limits', FALSE, 'limits');
		$this->registerArgument('paginationAs', 'string', 'name of the variable that will contain the page information', FALSE, 'pagination');
		$this->registerArgument('settings', 'array', 'contains the settings for this pagination', FALSE, array(
			'limits' => array(10, 20, 30, 50),
			'defaultLimit' => 10,
			'maxPages' => 8
		));
	}

	/**
	 *
	 * @return string Rendered string
	 * @author Marc Neuhaus <apocalip@gmail.com>
	 * @api
	 */
	public function render() {
		$this->query = $this->arguments['items'];
        if (!$this->query instanceof SearchResults) {
			return '';
		}
		$this->request = $this->controllerContext->getRequest();

		$this->settings = $this->arguments['settings'];

		$this->total = $this->query->getTotal();
		$limits = $this->handleLimits();
		$pagination = $this->handlePagination();

		$pagination['showPagination'] = isset($pagination['pages']);

		$this->templateVariableContainer->add($this->arguments['limitAs'], $limits);
		$this->templateVariableContainer->add($this->arguments['paginationAs'], $pagination);
		$this->templateVariableContainer->add($this->arguments['as'], $this->query);
		$content = $this->renderChildren();
		$this->templateVariableContainer->remove($this->arguments['limitAs']);
		$this->templateVariableContainer->remove($this->arguments['paginationAs']);
		$this->templateVariableContainer->remove($this->arguments['as']);

		return $content;
	}

	public function handleLimits(){
		$limits = array();
		foreach ($this->settings["limits"] as $limit) {
			$limits[$limit] = false;
		}

		if($this->request->hasArgument("limit"))
			$this->limit = intval($this->request->getArgument("limit"));
		else
			$this->limit = intval($this->settings["defaultLimit"]);

		$unset = false;
		foreach ($limits as $key => $value) {
			$limits[$key] = ($this->limit == $key);

			if(!$unset && intval($key) >= intval($this->total)){
				$unset = true;
				continue;
			}
			if($unset)
				unset($limits[$key]);
		}

		if(count($limits) == 1)
			$limits = array();

		$this->query->setLimit($this->limit);

		return $limits;
	}

	public function handlePagination(){
		$currentPage = 1;

		if( $this->request->hasArgument("page") )
			$currentPage = $this->request->getArgument("page");

		$pages = array();
		for($i=0; $i < ($this->total / $this->limit); $i++) {
			$pages[] = $i + 1;
		}

		if($currentPage > count($pages))
			$currentPage = count($pages);

		$offset = ($currentPage - 1) * $this->limit;
		$offset = $offset < 0 ? 0 : $offset;
		$this->query->setPage($currentPage > 0 ? $currentPage - 1 : 0);
		$pagination = array("offset" => $offset);

		if(count($pages) > 1){
			$pagination["currentPage"] = $currentPage;

			if($currentPage < count($pages))
				$pagination["nextPage"] = $currentPage + 1;

			if($currentPage > 1)
				$pagination["prevPage"] = $currentPage - 1;

			if(count($pages) > $this->settings["maxPages"]) {
				$max = $this->settings["maxPages"];
				$start = $currentPage - ( ($max + ($max % 2) ) / 2);
				$start = $start > 0 ? $start : 0;
				$start = $start > 0 ? $start : 0;
				$start = $start + $max > count($pages) ? count($pages) - $max : $start;
				$pages = array_slice($pages, $start, $max);
			}

			$pagination["pages"] = $pages;
		}

		return $pagination;
	}
}

?>