<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\variables;

use vaersaagod\geomate\GeoMate;

use vaersaagod\geomate\helpers\GeoMateHelper;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoMateVariable
{
    /**
     * @param null|string $ip
     * @return \GeoIp2\Model\City|\GeoIp2\Model\Country|mixed|null
     */
    public function country($ip = null)
    {
        return GeoMate::$plugin->geo->getCountryInfo($ip);
    }

    /**
     * @param null|string $ip
     * @return null|string
     */
    public function countryCode($ip = null)
    {
        return GeoMate::$plugin->geo->getCountryCode($ip);
    }

    /**
     * @param null|string $ip
     * @return \GeoIp2\Model\City|\GeoIp2\Model\Country|mixed|null
     */
    public function city($ip = null)
    {
        return GeoMate::$plugin->geo->getCityInfo($ip);
    }

    /**
     * @param null|string $ip
     * @return null|\vaersaagod\geomate\models\RedirectInfo
     */
    public function redirectInformation($ip = null)
    {
        return GeoMate::$plugin->redirect->getRedirectInfo($ip);
    }

    /**
     * @return bool
     */
    public function isCrawler(): bool
    {
        return GeoMateHelper::isCrawler();
    }

    /**
     * @return bool
     */
    public function isRedirected(): bool
    {
        return GeoMateHelper::isRedirected();
    }

    /**
     * @return bool
     */
    public function isOverridden(): bool
    {
        return GeoMateHelper::isOverridden();
    }

    /**
     * @return array
     */
    public function getSiteLinks(): array
    {
        return GeoMateHelper::getSiteLinks();
    }

    /**
     * @return array
     */
    public function getLanguages(): array
    {
        return GeoMateHelper::getLanguages();
    }
}
