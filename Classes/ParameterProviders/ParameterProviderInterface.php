<?php
namespace MIA3\Mia3Search\ParameterProviders;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Interface ParameterProviderInterface
 * @package MIA3\Mia3Search\ParameterProviders
 */
interface ParameterProviderInterface
{
    /**
     * @return integer
     */
    public function getPriority();

    /**
     * @param array $contentRow
     * @return array
     */
    public function extendParameterGroups($contentRow);
}