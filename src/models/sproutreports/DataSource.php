<?php
namespace barrelstrength\sproutcore\models\sproutreports;

use craft\base\Model;

class DataSource extends Model
{
	public $id;

	public $dataSourceId;

	public $options;

	public $allowNew;

	/**
	 * @return array
	 */
	public function safeAttributes()
	{
		 return ['id', 'dataSourceId', 'options' , 'allowNew'];
	}
}