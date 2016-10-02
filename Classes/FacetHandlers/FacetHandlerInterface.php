<?php
namespace MIA3\Mia3Search\FacetHandlers;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Interface FacetHandlerInterface
 * @package MIA3\Mia3Search\FacetHandlers
 */
interface FacetHandlerInterface
{

    /**
     * @param array $facet
     * @return array
     */
    public function addOptionLabels($facet);
}