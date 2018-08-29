<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\models;

use craft\base\Model;
use craft\models\Site;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class RedirectInfo extends Model
{

    /**
     * @var null|Site
     */
    public $site = null;

    /**
     * @var null|string
     */
    public $url = null;

    /**
     * @var boolean
     */
    public $isOverridden = false;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }
}
