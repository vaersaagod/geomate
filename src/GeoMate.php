<?php
/**
 * GeoMate plugin for Craft CMS 4.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\geomate;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\errors\MissingComponentException;
use craft\errors\SiteNotFoundException;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
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

        // Create a separate log file for GeoMate to keep things sane
        if ($settings->useSeparateLogfile) {
            // TODO 4.0: Need to replace this with... monolog?
            /*
            $fileTarget = new \craft\log\FileTarget([
                'logFile' => Craft::$app->getPath()->getLogPath(true) . '/geomate.log',
                'categories' => ['geomate']
            ]);

            Craft::getLogger()->dispatcher->targets[] = $fileTarget;
            */
        }

        // Add template variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('geomate', GeoMateVariable::class);
            }
        );

        // Add utility
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES, function(RegisterComponentTypesEvent $event) {
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
        $settings = self::$plugin->getSettings();

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
     * @throws MissingComponentException
     * @throws SiteNotFoundException
     * @throws BadRequestHttpException
     */
    protected function redirectCheck()
    {

        /** @var Settings $settings */
        $settings = $this->getSettings();
        
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsPreview() || (bool)Craft::$app->getRequest()->getToken() || $request->getIsLivePreview()) {
            return;
        }

        $session = Craft::$app->getSession();

        if ($session->hasFlash('geomateIsRedirected') || $request->getParam($settings->redirectedParam, null)) {
            self::$isRedirected = true;
        }

        if ($request->getParam($settings->redirectOverrideParam, null)) {
            $this->redirect->registerOverride();
        }

        if ($settings->autoRedirectEnabled && !in_array(Craft::$app->getSites()->getCurrentSite()->handle, $settings->autoRedirectExclude, true)) {
            $this->redirect->autoRedirect();
        }
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
