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
use craft\web\Controller;
use vaersaagod\geomate\GeoMate;
use yii\web\Response;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class DatabaseController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    public function actionUpdateDatabase(): Response
    {
        $returnResult = Craft::$app->getRequest()->getParam('returnResult', '') === '1';
        $result = GeoMate::getInstance()->database->updateDatabase();
        
        if (!$returnResult) {
            Craft::$app->end();
        }
        
        return $this->asJson(['success' => $result]);
    }
}
