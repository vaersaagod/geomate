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
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use GuzzleHttp\ClientInterface;
use MaxMind\Db\Reader;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class DatabaseService extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * Name of the mutex that serializes web-triggered database downloads
     * (the update action and automatic downloads when a database is missing)
     */
    public const DOWNLOAD_MUTEX_NAME = 'geomate-db-download';

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
     * @throws ErrorException
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getDatabase($type = 'country'): bool
    {
        GeoMate::log('Updating database ' . $type, Logger::LEVEL_INFO);

        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $dbPath = $settings->getDbPath();
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

        if (!FileHelper::isWritable($tempPath)) {
            GeoMate::log('The GeoMate temporary path is not writeable.', Logger::LEVEL_ERROR);
            return false;
        }

        $url = $type === 'city' ? $settings->cityDbDownloadUrl : $settings->countryDbDownloadUrl;
        $filename = $type === 'city' ? $settings->cityDbFilename : $settings->countryDbFilename;

        if (empty($url)) {
            GeoMate::log('No download URL is configured for the ' . $type . ' database. Please check your `' . $type . 'DbDownloadUrl` config setting.', Logger::LEVEL_ERROR);
            return false;
        }

        // Each run downloads into its own temporary directory, so concurrent runs never share a file
        $runPath = FileHelper::normalizePath($tempPath . DIRECTORY_SEPARATOR . $type . '-' . StringHelper::randomString(12));
        FileHelper::createDirectory($runPath);

        $pathinfo = pathinfo($url);
        $basename = $pathinfo['basename'];
        $sourcepath = FileHelper::normalizePath($runPath . DIRECTORY_SEPARATOR . $basename);
        $destpath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $filename);

        // The new database is written next to the live one (same filesystem), so it can be moved into place atomically
        $tmpDestpath = $destpath . '.' . StringHelper::randomString(12) . '.tmp';

        try {
            $client = Craft::createGuzzleClient();

            if (defined('\GuzzleHttp\ClientInterface::MAJOR_VERSION') && ClientInterface::MAJOR_VERSION >= 7) {
                $client->get($url, ['sink' => $sourcepath]);
            } else {
                $client->get($url, ['save_to' => $sourcepath]);
            }

            $archive = new \PharData($sourcepath);
            $found = false;

            foreach (new \RecursiveIteratorIterator($archive) as $file) {
                $fileInfo = pathinfo($file);

                if (isset($fileInfo['extension']) && $fileInfo['extension'] === 'mmdb') {
                    $found = true;
                    $content = $file->getContent();

                    if (file_put_contents($tmpDestpath, $content) !== strlen($content)) {
                        GeoMate::log('An error occurred when saving the ' . $type . ' database to `' . $destpath . '`.', Logger::LEVEL_ERROR);
                        return false;
                    }
                }
            }

            unset($archive);

            if (!$found) {
                GeoMate::log('No .mmdb files were found in the downloaded ' . $type . ' database archive.', Logger::LEVEL_ERROR);
                return false;
            }

            if (!$this->isValidDatabaseFile($tmpDestpath)) {
                GeoMate::log('The downloaded ' . $type . ' database is not a valid MaxMind database. The existing database was not replaced.', Logger::LEVEL_ERROR);
                return false;
            }

            // Keep the permissions of the database being replaced
            if (file_exists($destpath)) {
                @chmod($tmpDestpath, fileperms($destpath) & 0777);
            }

            // Replace the live database atomically, so lookups never read a partially written file
            if (!@rename($tmpDestpath, $destpath)) {
                GeoMate::log('An error occurred when moving the ' . $type . ' database into place at `' . $destpath . '`.', Logger::LEVEL_ERROR);
                return false;
            }

            return true;
        } finally {
            if (file_exists($tmpDestpath)) {
                @unlink($tmpDestpath);
            }

            try {
                FileHelper::removeDirectory($runPath);
            } catch (\Throwable) {
            }
        }
    }

    public function hasDatabase(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $dbPath = $settings->getDbPath();
        $countryPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->countryDbFilename);
        $cityPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->cityDbFilename);

        return file_exists($countryPath) && file_exists($cityPath);
    }

    /**
     * @return \DateTime|null
     * @throws \Exception
     */
    public function getDatabaseTimestamp()
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $dbPath = $settings->getDbPath();
        $countryPath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $settings->countryDbFilename);

        return file_exists($countryPath) ? new \DateTime('@' . filemtime($countryPath)) : null;
    }

    // Private Methods
    // =========================================================================

    /**
     * Whether the file is a readable MaxMind database (its metadata can be parsed).
     *
     * @param string $path
     * @return bool
     */
    private function isValidDatabaseFile(string $path): bool
    {
        try {
            $reader = new Reader($path);
            $reader->metadata();
            $reader->close();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
