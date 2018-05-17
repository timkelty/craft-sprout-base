<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbase\app\forms\web\assets\fontawesome;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class FontAwesomeAsset extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@sproutbase/app/forms/web/assets/fontawesome/dist';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/font-awesome.min.css'
        ];

        parent::init();
    }
}