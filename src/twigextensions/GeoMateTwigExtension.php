<?php

namespace vaersaagod\geomate\twigextensions;

use vaersaagod\geomate\GeoMate;

class GeoMateTwigExtension extends \Twig_Extension
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

    /**
     * Returns an array of Twig filters, used in Twig templates via:
     *
     *      {{ 'something' | someFilter }}
     *
     * @return array
     */
    public function getFilters(): array
    {
        return [
            new \Twig_SimpleFilter('addOverrideParam', [$this, 'addOverrideParam']),
            new \Twig_SimpleFilter('addRedirectParam', [$this, 'addRedirectParam']),
        ];
    }

    /**
     * Returns an array of Twig functions, used in Twig templates via:
     *
     *      {% set this = someFunction('something') %}
     *
     * @return array
     */
    public function getFunctions():array
    {
        return [
            //new \Twig_SimpleFunction('lorem', [$this, 'lorem']),
        ];
    }

    /**
     * @param string $val
     * @return string
     */
    public function addOverrideParam($val): string
    {
        return GeoMate::$plugin->redirect->addOverrideParam($val);
    }

    /**
     * @param string $val
     * @return string
     */
    public function addRedirectParam($val): string
    {
        return GeoMate::$plugin->redirect->addRedirectParam($val);
    }
}
