<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbase\web\assets\sproutfields\selectother;

use craft\web\AssetBundle;

class SelectOtherFieldAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@sproutbase/web/assets/sproutfields/selectother/dist';

        // @todo - update this file to be named better
        $this->js = [
            'js/sproutfields.js',
            'js/EditableTable.js',
            'js/initialize.js'
        ];

        parent::init();
    }
}