<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;
use vaersaagod\geomate\services\DatabaseService;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class DatabaseController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * Permission that lets a control panel user force a database update (the GeoMate utility permission)
     */
    public const PERMISSION_UPDATE_DATABASE = 'utility:geomate-utility';

    /**
     * Minimum age (in seconds) of the installed databases before a request without a key starts a new download
     */
    private const UNPRIVILEGED_MIN_DATABASE_AGE = 21600;

    /**
     * Cache key that throttles download attempts from requests without a key
     */
    private const UNPRIVILEGED_THROTTLE_CACHE_KEY = 'geomate-update-database-throttle';

    /**
     * Number of seconds between download attempts from requests without a key
     */
    private const UNPRIVILEGED_THROTTLE_DURATION = 600;

    // Protected Properties
    // =========================================================================

    /**
     * Anonymous requests are allowed so that existing cron URLs keep working, and are limited in actionUpdateDatabase()
     *
     * @var array<int|string>|bool|int
     */
    protected array|int|bool $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * Updates the databases.
     *
     * Privileged requests (a CSRF-validated POST from a user with the GeoMate utility permission, or a
     * request with a valid `updateDatabaseKey`) always download. Other requests are refused when
     * `updateDatabaseKey` is set, and are otherwise only allowed to download when the installed databases
     * are missing or older than six hours, at most once every ten minutes, and never in parallel.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws \yii\base\ExitException
     */
    public function actionUpdateDatabase(): Response
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $returnResult = Craft::$app->getRequest()->getParam('returnResult', '') === '1';
        $isPrivileged = $this->isPrivilegedRequest($settings);

        if (!$isPrivileged) {
            if ($this->getExpectedKey($settings) !== '') {
                throw new ForbiddenHttpException('A valid key is required to update the GeoMate databases.');
            }

            if ($this->isDatabaseFresh($settings)) {
                return $this->respond($returnResult, ['success' => true, 'skipped' => 'fresh']);
            }

            if (!Craft::$app->getCache()->add(self::UNPRIVILEGED_THROTTLE_CACHE_KEY, time(), self::UNPRIVILEGED_THROTTLE_DURATION)) {
                return $this->respond($returnResult, ['success' => false, 'skipped' => 'throttled']);
            }
        }

        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(DatabaseService::DOWNLOAD_MUTEX_NAME)) {
            return $this->respond($returnResult, ['success' => false, 'skipped' => 'running']);
        }

        try {
            $result = GeoMate::getInstance()->database->updateDatabase();
        } finally {
            $mutex->release(DatabaseService::DOWNLOAD_MUTEX_NAME);
        }

        return $this->respond($returnResult, ['success' => $result]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Whether the request may force a database update: a CSRF-validated POST from a user with the
     * GeoMate utility permission, or a request with a valid key.
     *
     * @param Settings $settings
     * @return bool
     */
    private function isPrivilegedRequest(Settings $settings): bool
    {
        $request = Craft::$app->getRequest();

        // Craft validates the CSRF token for POST requests before the action runs
        if ($request->getIsPost() && Craft::$app->getUser()->checkPermission(self::PERMISSION_UPDATE_DATABASE)) {
            return true;
        }

        $expectedKey = $this->getExpectedKey($settings);

        if ($expectedKey === '') {
            return false;
        }

        // Not named "token": Craft reserves that query parameter and rejects unknown values
        $key = $request->getQueryParam('geomateKey') ?? $request->getHeaders()->get('X-GeoMate-Key');

        return is_string($key) && hash_equals($expectedKey, $key);
    }

    /**
     * Returns the configured update key, with environment variables resolved, or an empty string if none is set.
     *
     * @param Settings $settings
     * @return string
     */
    private function getExpectedKey(Settings $settings): string
    {
        if (empty($settings->updateDatabaseKey)) {
            return '';
        }

        return (string)App::parseEnv($settings->updateDatabaseKey);
    }

    /**
     * Whether both databases exist and were updated within the last six hours.
     *
     * @param Settings $settings
     * @return bool
     */
    private function isDatabaseFresh(Settings $settings): bool
    {
        $countryPath = $settings->getDbFilePath('country');
        $cityPath = $settings->getDbFilePath('city');

        if (!file_exists($countryPath) || !file_exists($cityPath)) {
            return false;
        }

        $oldest = min((int)filemtime($countryPath), (int)filemtime($cityPath));

        return (time() - $oldest) < self::UNPRIVILEGED_MIN_DATABASE_AGE;
    }

    /**
     * Ends the request without a body, as before, unless the caller asked for a JSON result.
     *
     * @param bool $returnResult
     * @param array $data
     * @return Response
     * @throws \yii\base\ExitException
     */
    private function respond(bool $returnResult, array $data): Response
    {
        if (!$returnResult) {
            Craft::$app->end();
        }

        return $this->asJson($data);
    }
}
