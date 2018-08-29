<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\services;

use Craft;
use craft\base\Component;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use vaersaagod\geomate\models\Settings;
use vaersaagod\geomate\GeoMate;
use GeoIp2\Database\Reader;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @param null|string $ip
     * @return \GeoIp2\Model\City|\GeoIp2\Model\Country|mixed|null
     */
    public function getCountryInfo($ip = null)
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $ip = $this->getIp($ip);

        if (!$ip) {
            return null;
        }

        $key = $this->getCacheKey('country', $ip);
        $cache = Craft::$app->getCache();

        if ($settings->cacheEnabled && $cache->get($key) !== false) {
            return $cache->get($key);
        }

        $data = $this->getInfoByType('country', $ip);

        if ($data !== null && $settings->cacheEnabled) {
            $cache->set($key, $data, $settings->cacheDuration);
        }

        return $data;
    }

    /**
     * @param null|string $ip
     * @return null|string
     */
    public function getCountryCode($ip = null)
    {
        $info = $this->getCountryInfo($ip);
        return $info->country->isoCode ?? '';
    }

    /**
     * @param null|string $ip
     * @return \GeoIp2\Model\City|\GeoIp2\Model\Country|mixed|null
     */
    public function getCityInfo($ip = null)
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $ip = $this->getIp($ip);

        if (!$ip) {
            return null;
        }

        $key = $this->getCacheKey('city', $ip);
        $cache = Craft::$app->getCache();

        if ($settings->cacheEnabled && $cache->get($key) !== false) {
            return $cache->get($key);
        }

        $data = $this->getInfoByType('city', $ip);

        if ($data !== null && $settings->cacheEnabled) {
            $cache->set($key, $data, $settings->cacheDuration);
        }

        return $data;
    }


    // Private Methods
    // =========================================================================

    /**
     * @param $type
     * @param $ip
     * @return \GeoIp2\Model\City|\GeoIp2\Model\Country|null
     */
    private function getInfoByType($type, $ip)
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $dbPath = $settings->getDbFilePath($type);

        if (!file_exists($dbPath)) {
            $errorMsg = 'The ' . $type . ' database does not exist in `' . $dbPath . '`. Please check your config settings, and run an automatic database download or place the database in the appropriate path.';
            
            if ($settings->downloadDbIfMissing) {
                GeoMate::$plugin->database->updateDatabase();
                
                if (!file_exists($dbPath)) {
                    GeoMate::log($errorMsg, Logger::LEVEL_ERROR);
                    return null;
                }
            } else {
                GeoMate::log($errorMsg, Logger::LEVEL_ERROR);
                return null;
            }
        }

        try {
            $reader = new Reader($dbPath);
        } catch (InvalidDatabaseException $e) {
            GeoMate::log('Database read error (InvalidDatabaseException) :: ' . $e->getMessage(), Logger::LEVEL_ERROR);
            return null;
        }

        try {
            if ($type === 'city') {
                $data = $reader->city($ip);
            } else {
                $data = $reader->country($ip);
            }
        } catch (InvalidDatabaseException $e) {
            GeoMate::log('Database read error (InvalidDatabaseException) :: ' . $e->getMessage(), Logger::LEVEL_ERROR);
            return null;
        } catch (AddressNotFoundException $e) {
            GeoMate::log('Database read error (AddressNotFoundException) :: ' . $e->getMessage(), Logger::LEVEL_ERROR);
            return null;
        }

        return $data;
    }

    /**
     * @param string $type
     * @param string $ip
     * @return string
     */
    private function getCacheKey($type, $ip): string
    {
        return 'geomate-' . $type . '-' . $ip;
    }

    /**
     * @param null|string $ip
     * @return null|string
     */
    private function getIp($ip)
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();

        $localIps = array('127.0.0.1', '::1');

        if ($ip !== null) {
            return $ip;
        }

        if ($settings->forceIp !== null && filter_var($settings->forceIp, FILTER_VALIDATE_IP)) {
            return $settings->forceIp;
        }

        $ip = Craft::$app->getRequest()->getUserIP();

        if (!\in_array($ip, $localIps, true)) {
            return $ip;
        }

        if ($settings->fallbackIp !== null && filter_var($settings->fallbackIp, FILTER_VALIDATE_IP)) {
            return $settings->fallbackIp;
        }

        // if we get here, there's nothing more to do
        return null;
    }
}
