<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbase\contracts\sproutreports;

use barrelstrength\sproutbase\SproutBase;
use Craft;
use barrelstrength\sproutbase\records\sproutreports\DataSource;
use barrelstrength\sproutbase\models\sproutreports\Report as ReportModel;
use craft\base\Plugin;
use craft\helpers\UrlHelper;

/**
 * Class BaseDataSource
 *
 * @package Craft
 */
abstract class BaseDataSource
{
    /**
     * @var int
     */
    public $databaseId;

    /**
     * @var string
     */
    protected $dataSourceSlug;

    /**
     * @var string
     */
    protected $plugin;

    /**
     * @var ReportModel()
     */
    protected $report;

    /**
     * BaseDataSource constructor.
     */
    public function __construct()
    {
        $namespaces = explode('\\', get_class($this));

        $class = (new \ReflectionClass($this))->getShortName();

        // get plugin name on second array
        $dataSourceClass = $namespaces[1].'-'.$class;

        $this->dataSourceSlug = strtolower($dataSourceClass);


        $pluginHandle = Craft::$app->getPlugins()->getPluginHandleByClass(get_class($this));

        $this->plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);
    }

    /**
     * Returns a fully qualified string that uniquely identifies the given data source
     *
     * @format {plugin}-{source}
     * 1. {plugin} should be the lower case version of the plugin handle
     * 3. {source} should be the lower case version of your data source without prefixes or suffixes
     *
     * @example
     * - SproutFormsSubmissionsDataSource   > sproutforms-submissions
     * - CustomQuery > sproutreports-customquery
     *
     * @return string
     */
    final public function getDataSourceSlug()
    {
        return $this->dataSourceSlug;
    }

    /**
     * Set a ReportModel on our data source.
     *
     * @param ReportModel|null $report
     */
    public function setReport(ReportModel $report = null)
    {
        if (null === $report) {
            $report = new ReportModel();
        }

        $this->report = $report;
    }

    /**
     * Returns the CP URL for the given data source with the option to append to it once composed
     *
     * @legend
     * Breaks apart the data source id and transforms its components into a URL friendly string
     *
     * @example
     * sproutReports.customQuery > sproutreports/customquery
     * sproutreports.customquery > sproutreports/customquery
     *
     * @see getDataSourceSlug()
     *
     * @param string $append
     *
     * @return string
     */
    public function getUrl($append = null)
    {
        $pluginHandle = Craft::$app->getRequest()->getSegment(1);

        $baseUrl = $pluginHandle.'/reports/'. $this->databaseId . '-' . $this->getDataSourceSlug() . '/';

        $appendedUrl = ltrim($append, '/');

        return UrlHelper::cpUrl($baseUrl . $appendedUrl);
    }

    /**
     * Returns the name of the plugin name that the given data source is bundled with
     *
     * @param string $name
     */
    final public function setPluginName($name)
    {
        $this->pluginName = $name;
    }

    /**
     * @return string
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @var $plugin Plugin
     *
     * @return string
     */
    public function getPluginName()
    {
        return $this->getPlugin()->name;
    }

    /**
     * @return string
     */
    public function getPluginHandle()
    {
        return $this->getPlugin()->getHandle();
    }

    /**
     * @return string
     */
    public function getLowerPluginHandle()
    {
        return strtolower($this->getPluginHandle());
    }

    /**
     * Returns the total count of reports created based on the given data source
     *
     * @return int
     */
    final public function getReportCount()
    {
        return SproutBase::$app->reports->getCountByDataSourceId($this->getDataSourceSlug());
    }

    /**
     * Should return a human readable name for your data source
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Should return an string containing the necessary HTML to capture user input
     *
     * @return null|string
     */
    public function getSettingsHtml()
    {
        return null;
    }

    /**
     * Should return an array of strings to be used as column headings in display/output
     *
     * @param ReportModel $report
     * @param array       $settings
     *
     * @return array
     */
    public function getDefaultLabels(ReportModel $report, array $settings = [])
    {
        return [];
    }

    /**
     * Should return an array of records to use in the report
     *
     * @param ReportModel $report
     * @param array       $settings
     *
     * @return array
     */
    public function getResults(ReportModel $report, array $settings = [])
    {
        return [];
    }

    /**
     * Give a Data Source a chance to prepare settings before they are processed by the Dynamic Name field
     *
     * @param array $settings
     *
     * @return null
     */
    public function prepSettings(array $settings)
    {
        return $settings;
    }

    /**
     * Validate the data sources settings
     *
     * @param array $settings
     * @param array $errors
     *
     * @return bool
     */
    public function validateSettings(array $settings = [], array &$errors)
    {
        return true;
    }

    /**
     * Allows a user to disable a Data Source from displaying in the New Report dropdown
     *
     * @return bool|mixed
     */
    public function allowNew()
    {
        $record = DataSource::findOne(['type' => $this->dataSourceSlug]);

        // $record->allowNew != null
        if ($record != null) {
            return $record->allowNew;
        }

        return true;
    }

    /**
     * Allow a user to toggle the Allow Html setting.
     *
     * @return bool
     */
    public function isAllowHtmlEditable()
    {
        return false;
    }

    /**
     * Define the default value for the Allow HTML setting. Setting Allow HTML
     * to true enables a report to output HTML on the Results page.
     *
     * @return bool
     */
    public function getDefaultAllowHtml()
    {
        return false;
    }
}
