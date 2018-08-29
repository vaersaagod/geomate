<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site.
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\controllers;

use Craft;
use craft\web\Controller;
use vaersaagod\geomate\GeoMate;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class DatabaseController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    public function actionUpdateDatabase()
    {
        $returnResult = Craft::$app->getRequest()->getParam('returnResult', '') === '1';
        $result = GeoMate::$plugin->database->updateDatabase();
        
        if (!$returnResult) {
            Craft::$app->end();
        }
        
        return $this->asJson(['success' => $result]);
    }
}
