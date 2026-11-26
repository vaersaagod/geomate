<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\services;

use Craft;
use craft\base\Component;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use GeoIp2\Model\Country;
use MaxMind\Db\Reader\InvalidDatabaseException;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoService extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * Cache key that marks a recently failed automatic database download
     */
    private const DOWNLOAD_BACKOFF_CACHE_KEY = 'geomate-db-download-backoff';

    /**
     * Number of seconds to wait before retrying a failed automatic database download
     */
    private const DOWNLOAD_BACKOFF_DURATION = 600;

    // Public Methods
    // =========================================================================
    /**
     * @param null|string $ip
     * @return City|Country|mixed|null
     */
    public function getCountryInfo($ip = null)
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
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
     * @return City|Country|mixed|null
     */
    public function getCityInfo($ip = null)
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
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
     * @return City|Country|null
     */
    private function getInfoByType($type, $ip)
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $dbPath = $settings->getDbFilePath($type);

        if (!file_exists($dbPath)) {
            $errorMsg = 'The ' . $type . ' database does not exist in `' . $dbPath . '`. Please check your config settings, and run an automatic database download or place the database in the appropriate path.';

            if ($settings->downloadDbIfMissing) {
                $this->downloadMissingDatabase($dbPath);
            }

            if (!file_exists($dbPath)) {
                GeoMate::log($errorMsg, Logger::LEVEL_ERROR);
                return null;
            }
        }

        try {
            $reader = new Reader($dbPath);
        } catch (InvalidDatabaseException $invalidDatabaseException) {
            GeoMate::log('Database read error (InvalidDatabaseException) :: ' . $invalidDatabaseException->getMessage(), Logger::LEVEL_ERROR);
            return null;
        }

        try {
            $data = $type === 'city' ? $reader->city($ip) : $reader->country($ip);
        } catch (InvalidDatabaseException $invalidDatabaseException) {
            GeoMate::log('Database read error (InvalidDatabaseException) :: ' . $invalidDatabaseException->getMessage(), Logger::LEVEL_ERROR);
            return null;
        } catch (AddressNotFoundException $addressNotFoundException) {
            GeoMate::log('Database read error (AddressNotFoundException) :: ' . $addressNotFoundException->getMessage(), Logger::LEVEL_ERROR);
            return null;
        }

        return $data;
    }

    /**
     * Tries to download the databases when a database file is missing and downloadDbIfMissing is enabled.
     *
     * Failures are logged instead of thrown, so a failing download never breaks the page. After a failed
     * attempt, no new attempt is made until the backoff period has passed, and concurrent requests
     * do not start parallel downloads.
     *
     * @param string $dbPath
     * @return void
     */
    private function downloadMissingDatabase(string $dbPath): void
    {
        $cache = Craft::$app->getCache();

        if ($cache->get(self::DOWNLOAD_BACKOFF_CACHE_KEY) !== false) {
            return;
        }

        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(DatabaseService::DOWNLOAD_MUTEX_NAME)) {
            return;
        }

        try {
            // Another request may have finished the download since the file was checked
            if (!file_exists($dbPath)) {
                GeoMate::getInstance()->database->updateDatabase();
            }
        } catch (\Throwable $throwable) {
            // Only log the exception class and code, the message can contain the download URL
            GeoMate::log('Automatic database download failed (' . get_class($throwable) . ', code ' . $throwable->getCode() . ')', Logger::LEVEL_ERROR);
        } finally {
            $mutex->release(DatabaseService::DOWNLOAD_MUTEX_NAME);
        }

        if (!file_exists($dbPath)) {
            $cache->set(self::DOWNLOAD_BACKOFF_CACHE_KEY, true, self::DOWNLOAD_BACKOFF_DURATION);
        }
    }

    /**
     * @param string $type
     * @param string $ip
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
        $settings = GeoMate::getInstance()->getSettings();

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
