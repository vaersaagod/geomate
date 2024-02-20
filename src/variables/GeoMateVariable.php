<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\variables;

use GeoIp2\Model\City;
use GeoIp2\Model\Country;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\helpers\GeoMateHelper;

use vaersaagod\geomate\models\RedirectInfo;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoMateVariable
{
    /**
     * @param null|string $ip
     * @return City|Country|mixed|null
     */
    public function country($ip = null)
    {
        return GeoMate::getInstance()->geo->getCountryInfo($ip);
    }

    /**
     * @param null|string $ip
     * @return null|string
     */
    public function countryCode($ip = null)
    {
        return GeoMate::getInstance()->geo->getCountryCode($ip);
    }

    /**
     * @param null|string $ip
     * @return City|Country|mixed|null
     */
    public function city($ip = null)
    {
        return GeoMate::getInstance()->geo->getCityInfo($ip);
    }

    /**
     * @param null|string $ip
     * @return null|RedirectInfo
     */
    public function redirectInformation($ip = null)
    {
        return GeoMate::getInstance()->redirect->getRedirectInfo($ip);
    }

    public function isCrawler(): bool
    {
        return GeoMateHelper::isCrawler();
    }

    public function isRedirected(): bool
    {
        return GeoMateHelper::isRedirected();
    }

    public function isOverridden(): bool
    {
        return GeoMateHelper::isOverridden();
    }

    public function getSiteLinks(): array
    {
        return GeoMateHelper::getSiteLinks();
    }

    public function getLanguages(): array
    {
        return GeoMateHelper::getLanguages();
    }
}
