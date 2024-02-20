<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoMateAssets extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@vaersaagod/geomate/assetbundles/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'geomate.js',
        ];

        $this->css = [
            'geomate.css',
        ];

        parent::init();
    }
}
