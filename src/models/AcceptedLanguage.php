<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class AcceptedLanguage extends Model
{
    /**
     * @var int
     */
    public int $quality = 80;

    /**
     * @var null|string
     */
    public ?string $language = null;

    /**
     * @var null|string
     */
    public ?string $region = null;

    /**
     * @var null|string
     */
    public ?string $script = null;


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
