<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\errors\MissingComponentException;
use craft\errors\SiteNotFoundException;
use craft\events\RegisterComponentTypesEvent;
use craft\log\MonologTarget;
use craft\services\Utilities;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;

use Monolog\Formatter\LineFormatter;

use Psr\Log\LogLevel;

use vaersaagod\geomate\models\Settings;
use vaersaagod\geomate\services\CookieService;
use vaersaagod\geomate\services\DatabaseService;
use vaersaagod\geomate\services\GeoService;
use vaersaagod\geomate\services\RedirectService;
use vaersaagod\geomate\twigextensions\GeoMateTwigExtension;
use vaersaagod\geomate\utilities\GeoMateUtility;
use vaersaagod\geomate\variables\GeoMateVariable;

use yii\base\Event;
use yii\log\Logger;
use yii\web\BadRequestHttpException;

/**
 * Class GeoMate
 *
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 *
 * @property  GeoService $geo
 * @property  DatabaseService $database
 * @property  RedirectService $redirect
 * @property  CookieService $cookie
 */
class GeoMate extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var GeoMate
     * @deprecated in 3.0. `getInstance()` should be used instead
     */
    public static GeoMate $plugin;

    /**
     * @var boolean
     */
    public static bool $isRedirected = false;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        // Deprecated, but keeping this around until 4.0 in case someone relies on it.
        self::$plugin = $this;

        /** @var Settings $settings */
        $settings = $this->getSettings();

        // Register services
        $this->setComponents([
            'geo' => GeoService::class,
            'database' => DatabaseService::class,
            'redirect' => RedirectService::class,
            'cookie' => CookieService::class,
        ]);

        // Create a separate log file for GeoMate?
        if ($settings->useSeparateLogfile) {
            Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
                'name' => 'geomate',
                'categories' => ['geomate', 'vaersaagod\\geomate\\*'],
                'level' => LogLevel::INFO,
                'logContext' => false,
                'allowLineBreaks' => false,
                'formatter' => new LineFormatter(
                    format: "[%datetime%] %message%\n",
                    dateFormat: 'Y-m-d H:i:s',
                ),
            ]);
        }

        // Add template variable
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('geomate', GeoMateVariable::class);
            }
        );

        // Add utility
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = GeoMateUtility::class;
        });

        // Register Twig extensions
        Craft::$app->view->registerTwigExtension(new GeoMateTwigExtension());

        // Handle redirect functionality
        Event::on(Application::class, Application::EVENT_INIT, function() {
            $this->redirectCheck();
        }, append: false);
    }

    /**
     * @param string $message
     * @param int $logLevel
     */
    public static function log($message, $logLevel = Logger::LEVEL_INFO)
    {
        /** @var Settings $settings */
        $settings = self::getInstance()->getSettings();

        if ($settings->useSeparateLogfile) {
            if ($logLevel <= $settings->logLevel || Craft::$app->getConfig()->getGeneral()->devMode) {
                Craft::getLogger()->log($message, $logLevel, 'geomate');
            }
        } else {
            Craft::getLogger()->log($message, $logLevel, 'geomate');
        }
    }

    // Protected Methods
    // =========================================================================
    /**
     * @return void
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     * @throws SiteNotFoundException
     * @throws \yii\base\InvalidRouteException
     */
    protected function redirectCheck(): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || !$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsPreview() || Craft::$app->getRequest()->getHadToken() || $request->getIsLivePreview()) {
            return;
        }

        // Resource requests (published CP/asset bundle files served by PHP) are not page views
        if ($this->isResourceRequest()) {
            return;
        }

        /** @var Settings $settings */
        $settings = $this->getSettings();

        $redirectOverrideParam = $request->getQueryParam($settings->redirectOverrideParam);
        if (!empty($redirectOverrideParam) && $redirectOverrideParam === $settings->paramValue) {
            $this->redirect->registerOverride();
        }

        if (!$settings->autoRedirectEnabled || in_array(Craft::$app->getSites()->getCurrentSite()->handle, $settings->autoRedirectExclude, true)) {
            return;
        }

        if ($settings->addGetParameterOnRedirect) {
            $redirectedParam = $request->getQueryParam($settings->redirectedParam);
            self::$isRedirected = !empty($redirectedParam) && $redirectedParam === $settings->paramValue;
        } else {
            // Reading the flash starts a session, so only do it when the visitor already has one.
            // Without a session ID there can be no redirect flash, and starting a session here would
            // set a session cookie on every page view (bots and visitors that are never redirected).
            $session = Craft::$app->getSession();
            self::$isRedirected = ($session->getIsActive() || $session->getHasSessionId()) && $session->hasFlash('geomateIsRedirected');
        }

        if (self::$isRedirected) {
            return;
        }

        $this->redirect->autoRedirect();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Whether the current request is for a published resource (resourceBaseUrl, `/cpresources` by default).
     * Craft serves these through PHP when the file isn't published to the webroot, after EVENT_INIT.
     * Uses the same test as Craft's own resource request handling.
     *
     * @return bool
     */
    private function isResourceRequest(): bool
    {
        $resourceBaseUrl = Craft::getAlias(Craft::$app->getConfig()->getGeneral()->resourceBaseUrl, false);

        if (!is_string($resourceBaseUrl)) {
            return false;
        }

        $resourceBaseUri = parse_url($resourceBaseUrl, PHP_URL_PATH);

        if (!is_string($resourceBaseUri) || trim($resourceBaseUri, '/') === '') {
            return false;
        }

        $resourceBaseUri = '/' . trim($resourceBaseUri, '/');

        return str_starts_with('/' . Craft::$app->getRequest()->getFullPath() . '/', $resourceBaseUri . '/');
    }
}
