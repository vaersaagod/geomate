<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */


namespace vaersaagod\geomate\twigextensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use vaersaagod\geomate\GeoMate;

class GeoMateTwigExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'GeoMate';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('addOverrideParam', fn(string $val): string => $this->addOverrideParam($val)),
            new TwigFilter('addRedirectParam', fn(string $val): string => $this->addRedirectParam($val)),
        ];
    }


    public function addOverrideParam(string $val): string
    {
        return GeoMate::getInstance()->redirect->addOverrideParam($val);
    }

    public function addRedirectParam(string $val): string
    {
        return GeoMate::getInstance()->redirect->addRedirectParam($val);
    }
}
