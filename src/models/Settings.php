<?php
/**
 * GeoMate plugin for Craft CMS 4.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\geomate\models;

use Craft;
use craft\base\Model;
use craft\helpers\ConfigHelper;
use craft\helpers\FileHelper;
use vaersaagod\geomate\GeoMate;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 *
 * @property-read string $tempPath
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var boolean
     */
    public bool $cacheEnabled = true;

    /**
     * @var int|string
     */
    public int|string $cacheDuration = 'P7D';

    /**
     * @var boolean
     */
    public $useSeparateLogfile = true;

    /**
     * @var int
     */
    public $logLevel = Logger::LEVEL_ERROR;

    /**
     * Location of Maxmind DB's. If none given, they will be stored inside /storage.
     *
     * @var string
     */
    public $dbPath = '';

    /**
     * @var string
     */
    public $countryDbFilename = 'GeoLite2-Country.mmdb';

    /**
     * @var string
     */
    public $cityDbFilename = 'GeoLite2-City.mmdb';

    /**
     * @var string|null
     */
    public $countryDbDownloadUrl;

    /**
     * @var string|null
     */
    public $cityDbDownloadUrl;

    /**
     * @var boolean
     */
    public $downloadDbIfMissing = false;

    /**
     * @var boolean
     */
    public $autoRedirectEnabled = false;

    /**
     * @var array
     */
    public $autoRedirectExclude = [];

    /**
     * @var array
     */
    public $redirectMap = [];

    /**
     * @var boolean
     */
    public $redirectMatchingElementOnly = false;

    /**
     * @var string
     */
    public $redirectMapSimpleModeKey = 'country';

    /**
     * @var boolean
     */
    public $redirectIgnoreBots = true;

    /**
     * @var boolean
     */
    public $redirectIgnoreAdmins = true;

    /**
     * Array of user group handles or ids that should be ignored.
     *
     * @var array
     */
    public $redirectIgnoreUserGroups = [];

    /**
     * Array of url patterns that should be ignored.
     *
     * @var array
     */
    public $redirectIgnoreUrlPatterns = [];

    /**
     * @var string
     */
    public $redirectOverrideCookieName = 'GeoMateRedirectOverride';

    /**
     * @var int|string
     */
    public $cookieDuration = 'P7D';
    
    /**
     * @var boolean
     */
    public $addGetParameterOnRedirect = false;

    /**
     * @var string
     */
    public $redirectOverrideParam = '__geom';

    /**
     * @var string
     */
    public $redirectedParam = '__redir';

    /**
     * @var string
     */
    public $paramValue = '✪';

    /**
     * @var null|string
     */
    public $forceIp;

    /**
     * @var null|string
     */
    public $fallbackIp;

    /**
     * @var int
     */
    public $minimumAcceptLanguageQuality = 80;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }

    public function init(): void
    {
        if (empty($this->dbPath)) {
            try {
                $this->dbPath = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'geomate' . DIRECTORY_SEPARATOR;
            } catch (Exception) {
            }
        }
        
        try {
            $this->cacheDuration = ConfigHelper::durationInSeconds($this->cacheDuration);
            $this->cookieDuration = ConfigHelper::durationInSeconds($this->cookieDuration);
        } catch (InvalidConfigException) {
        }
    }

    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    /**
     * @param string $type
     */
    public function getDbFilePath($type = 'country'): string
    {
        return FileHelper::normalizePath($this->dbPath . DIRECTORY_SEPARATOR . ($type === 'city' ? $this->cityDbFilename : $this->countryDbFilename));
    }

    public function getTempPath(): string
    {
        return FileHelper::normalizePath(Craft::$app->getPath()->getTempPath(true) . DIRECTORY_SEPARATOR . 'geomate' . DIRECTORY_SEPARATOR);
    }
}
