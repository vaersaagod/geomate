<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;
use vaersaagod\geomate\GeoMate;

/**
 * @author    Værsågod
 * @package   GeoMate
 */
class DatabaseController extends Controller
{
    public function actionUpdateDatabase()
    {
        $this->stdout("Updating databases...\n");

        GeoMate::$plugin->database->updateDatabase();
        return ExitCode::OK;
    }
}
