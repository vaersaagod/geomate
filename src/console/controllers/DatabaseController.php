<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\console\controllers;

use craft\console\Controller;
use vaersaagod\geomate\GeoMate;
use yii\console\ExitCode;

/**
 * @author    Værsågod
 * @package   GeoMate
 */
class DatabaseController extends Controller
{
    public function actionUpdateDatabase(): int
    {
        $this->stdout("Updating databases...\n");

        GeoMate::getInstance()->database->updateDatabase();
        return ExitCode::OK;
    }
}
