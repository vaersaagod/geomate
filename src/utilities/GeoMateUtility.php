<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
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
    public static function iconPath(): ?string
    {
        return Craft::getAlias('@vaersaagod/geomate/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return GeoMate::getInstance()->database->hasDatabase() ? 0 : 1;
    }
    
    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'globe';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        
        try {
            Craft::$app->getView()->registerAssetBundle(GeoMateAssets::class);
        } catch (InvalidConfigException) {
            return Craft::t('geomate', 'Could not load asset bundle');
        }
        
        return Craft::$app->getView()->renderTemplate(
            'geomate/utility/_render',
            [
                'hasDatabase' => GeoMate::getInstance()->database->hasDatabase(),
                'dbTimestamp' => GeoMate::getInstance()->database->getDatabaseTimestamp(),
                'settings' => $settings,
                'countryDbDownloadUrl' => self::redactUrl($settings->countryDbDownloadUrl),
                'cityDbDownloadUrl' => self::redactUrl($settings->cityDbDownloadUrl),
                'memoryLimit' => ini_get('memory_limit'),
            ]
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a display-safe version of a download URL, with any credentials (userinfo, and
     * sensitive query values like license_key) redacted.
     *
     * @param string|null $url
     * @return string|null
     */
    private static function redactUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'])) {
            return str_repeat('•', 8);
        }

        $security = Craft::$app->getSecurity();

        $redacted = ($parts['scheme'] ?? 'https') . '://';

        if (isset($parts['user'])) {
            $redacted .= str_repeat('•', 8) . '@';
        }

        $redacted .= $parts['host'];

        if (isset($parts['port'])) {
            $redacted .= ':' . $parts['port'];
        }

        $redacted .= $parts['path'] ?? '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $pairs = [];

            foreach (explode('&', $parts['query']) as $pair) {
                if (!str_contains($pair, '=')) {
                    $pairs[] = $pair;
                    continue;
                }

                [$key, $value] = explode('=', $pair, 2);
                $pairs[] = $key . '=' . $security->redactIfSensitive(urldecode($key), $value);
            }

            $redacted .= '?' . implode('&', $pairs);
        }

        return $redacted;
    }
}
