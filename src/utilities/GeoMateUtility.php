<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\utilities;

use Craft;
use craft\base\Utility;
use vaersaagod\geomate\assetbundles\GeoMateAssets;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;
use yii\base\InvalidConfigException;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoMateUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('geomate', 'GeoMate');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'geomate-utility';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@vaersaagod/geomate/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return !GeoMate::$plugin->database->hasDatabase() ? 1 : 0;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        
        try {
            Craft::$app->getView()->registerAssetBundle(GeoMateAssets::class);
        } catch (InvalidConfigException $e) {
            return Craft::t('geomate', 'Could not load asset bundle');
        }
        
        return Craft::$app->getView()->renderTemplate(
            'geomate/utility/_render',
            [
                'hasDatabase'  => GeoMate::$plugin->database->hasDatabase(),
                'dbTimestamp'  => GeoMate::$plugin->database->getDatabaseTimestamp(),
                'settings' => $settings,
                'memoryLimit' => ini_get('memory_limit')
            ]
        );
    }
}
