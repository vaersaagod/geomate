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
use craft\helpers\FileHelper;
use craft\base\Component;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class DatabaseService extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return bool
     */
    public function updateDatabase(): bool
    {
        $resultCountry = $this->getDatabase('country');
        $resultCity = $this->getDatabase('city');
        
        return $resultCountry && $resultCity;
    }

    /**
     * @param string $type
     * @return bool
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     */
    private function getDatabase($type = 'country'): bool 
    {
        GeoMate::log('Updating database ' . $type, Logger::LEVEL_INFO);
        
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $dbPath = $settings->dbPath;
        $tempPath = $settings->getTempPath();
        
        if (!file_exists($dbPath)) {
            FileHelper::createDirectory($dbPath);

            if (!file_exists($dbPath)) {
                GeoMate::log('Could not create GeoMate database path. Please check your `dbPath` config setting.', Logger::LEVEL_ERROR);
                return false;
            }
        }

        if (!FileHelper::isWritable($dbPath)) {
            GeoMate::log('The GeoMate database path is not writeable. Please check your `dbPath` config setting.', Logger::LEVEL_ERROR);
            return false;
        }

        if (!file_exists($tempPath)) {
            FileHelper::createDirectory($tempPath);

            if (!file_exists($tempPath)) {
                GeoMate::log('Could not create GeoMate temporary path.', Logger::LEVEL_ERROR);
                return false;
            }
        }

        if (!FileHelper::isWritable($dbPath)) {
            GeoMate::log('The GeoMate temporary path is not writeable.', Logger::LEVEL_ERROR);
            return false;
        }

        $url = $type === 'city' ? $settings->cityDbDownloadUrl : $settings->countryDbDownloadUrl;
        $filename = $type === 'city' ? $settings->cityDbFilename : $settings->countryDbFilename;

        $pathinfo = pathinfo($url);
        $basename = $pathinfo['basename'];
        $sourcepath = FileHelper::normalizePath($tempPath . DIRECTORY_SEPARATOR . $basename);

        $client = Craft::createGuzzleClient();
        $response = $client->get($url, ['save_to' => $sourcepath]);

        $destpath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $filename);

        $unzippedData = '';   
        $zd = gzopen($sourcepath, 'r');
        
        while ($data = gzread($zd, 10000000)){
            $unzippedData .= $data;
        }

        gzclose($zd);
        
        $result = file_put_contents($destpath, $unzippedData);
        
        @unlink($sourcepath);
        
        if (!$result) {
            GeoMate::log('An error occurred when saving to ' . $type . ' database to `' . $destpath . '`.', Logger::LEVEL_ERROR);
            return false;
        }
        
        return true;
    }

    /**
     * @return bool
     */
    public function hasDatabase(): bool 
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $dbPath = $settings->dbPath;
        $countryPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->countryDbFilename);
        $cityPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->cityDbFilename);
        
        return file_exists($countryPath) && file_exists($cityPath);
    }

    /**
     * @return \DateTime|null
     */
    public function getDatabaseTimestamp() 
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $dbPath = $settings->dbPath;
        $countryPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->countryDbFilename);
        
        return file_exists($countryPath) ? new \DateTime('@' . filemtime($countryPath)) : null;
    }
}
