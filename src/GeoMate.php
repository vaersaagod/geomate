<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;

use vaersaagod\geomate\services\CookieService;
use vaersaagod\geomate\services\DatabaseService;
use vaersaagod\geomate\services\GeoService;
use vaersaagod\geomate\services\RedirectService;
use vaersaagod\geomate\twigextensions\GeoMateTwigExtension;
use vaersaagod\geomate\utilities\GeoMateUtility;
use vaersaagod\geomate\variables\GeoMateVariable;
use vaersaagod\geomate\models\Settings;

use yii\base\Event;

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
    public static $plugin;

    /**
     * @var boolean
     */
    public static $isRedirected = false;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
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
            $fileTarget = new \craft\log\FileTarget([
                'logFile' => Craft::$app->getPath()->getLogPath(true) . '/geomate.log', 
                'categories' => ['geomate']
            ]);
        
            Craft::getLogger()->dispatcher->targets[] = $fileTarget;
        }

        // Add template variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
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
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();
        
        if ($request->getIsSiteRequest() && !$request->getIsLivePreview() && !$request->getIsActionRequest() && $request->getMethod() === 'GET') {
            if ($session->hasFlash('geomateIsRedirected') || $request->getParam($settings->redirectedParam, '') !== '') {
                self::$isRedirected = true;
            }
            
            if ($request->getParam($settings->redirectOverrideParam, '') !== '') {
                $this->redirect->registerOverride();
            }
            
            if ($settings->autoRedirectEnabled) {
                $this->redirect->autoRedirect();
            }
        }
    }

    /**
     * @param string $message
     * @param int $logLevel
     */
    public static function log($message, $logLevel = \yii\log\Logger::LEVEL_INFO)
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
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

}
