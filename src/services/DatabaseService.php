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
use vaersaagod\geomate\helpers\GeoMateHelper;
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

    /**
     * Number of seconds a database download may take to connect, unless config/guzzle.php sets `connect_timeout`
     */
    private const DOWNLOAD_CONNECT_TIMEOUT = 15;

    /**
     * Number of seconds a database download may take in total, unless config/guzzle.php sets `timeout`
     */
    private const DOWNLOAD_TIMEOUT = 600;

    // Public Methods
    // =========================================================================

    /**
     * @return bool
     * @throws ErrorException
     * @throws Exception
     */
    public function updateDatabase(): bool
    {
        $resultCountry = false;
        $resultCity = false;

        try {
            $resultCountry = $this->getDatabase('country');
            $resultCity = $this->getDatabase('city');
        } finally {
            // Cached lookups were made with the replaced database
            if ($resultCountry || $resultCity) {
                GeoMate::getInstance()->geo->invalidateCache();
            }
        }

        return $resultCountry && $resultCity;
    }

    /**
     * @param string $type
     * @throws ErrorException
     * @throws Exception
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

        // Not named after the URL, which can contain the license key. PharData detects the archive format
        // from the file contents, and a neutral extension works for both tar.gz and zip archives.
        $sourcepath = FileHelper::normalizePath($runPath . DIRECTORY_SEPARATOR . $type . '.archive');
        $destpath = FileHelper::normalizePath($dbPath . DIRECTORY_SEPARATOR . $filename);

        // The new database is written next to the live one (same filesystem), so it can be moved into place atomically
        $tmpDestpath = $destpath . '.' . StringHelper::randomString(12) . '.tmp';

        try {
            $this->downloadArchive($url, $sourcepath, $type);

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
     * Downloads a database archive to the given path.
     *
     * Guzzle's exception messages include the request URL, which can contain the license key, so a
     * failed download is rethrown with the credentials redacted (and without the original exception,
     * whose message would otherwise end up in the logs).
     *
     * @param string $url
     * @param string $sourcepath
     * @param string $type
     * @return void
     * @throws Exception
     */
    private function downloadArchive(string $url, string $sourcepath, string $type): void
    {
        // Timeouts make sure a stalled download can't hold the update lock indefinitely.
        // Timeouts set in config/guzzle.php take precedence.
        $options = array_diff_key([
            'connect_timeout' => self::DOWNLOAD_CONNECT_TIMEOUT,
            'timeout' => self::DOWNLOAD_TIMEOUT,
        ], Craft::$app->getConfig()->getConfigFromFile('guzzle'));

        try {
            $client = Craft::createGuzzleClient();

            if (defined('\GuzzleHttp\ClientInterface::MAJOR_VERSION') && ClientInterface::MAJOR_VERSION >= 7) {
                $client->get($url, $options + ['sink' => $sourcepath]);
            } else {
                $client->get($url, $options + ['save_to' => $sourcepath]);
            }
        } catch (\Throwable $throwable) {
            throw new Exception(
                'Could not download the ' . $type . ' database (' . get_class($throwable) . '): ' . GeoMateHelper::redactUrlCredentials($throwable->getMessage(), [$url]),
                (int)$throwable->getCode()
            );
        }
    }

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
