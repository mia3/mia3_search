<?php
namespace MIA3\Mia3Search\ParameterProviders;

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