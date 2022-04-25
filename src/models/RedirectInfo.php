<?php
/**
 * GeoMate plugin for Craft CMS 4.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
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
    public ?Site $site = null;

    /**
     * @var null|string
     */
    public ?string $url = null;

    /**
     * @var boolean
     */
    public bool $isOverridden = false;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }
}
