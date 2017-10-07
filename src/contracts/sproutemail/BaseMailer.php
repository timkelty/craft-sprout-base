<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutcore\contracts\sproutemail;

use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutemail\elements\NotificationEmail;
use barrelstrength\sproutemail\models\CampaignTypeModel;
use barrelstrength\sproutemail\SproutEmail;
use yii\base\Model;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

abstract class BaseMailer
{
	/**
	 * The settings for this mailer stored in the sproutemail_mailers table
	 *
	 * @var Model
	 */
	protected $settings;

	/**
	 * Whether this mailer has been initialized
	 *
	 * @var bool
	 */
	protected $initialized = false;

	/**
	 * Whether this mailer has been installed
	 *
	 * @var bool
	 */
	protected $installed = false;

	/**
	 * Initializes the mailer by fetching its settings and loading necessary configs
	 */
	public function init()
	{
		if (!$this->initialized)
		{
			$this->initialized = true;
		}
	}


	/**
	 * Returns the mailer title when used in string context
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->getTitle();
	}

	/**
	 * Returns whether or not the mailer has been properly initialized by Sprout Email
	 *
	 * @return bool
	 */
	public function isInitialized()
	{
		return $this->initialized;
	}

	/**
	 * Returns the mailer id to use as the formal identifier
	 *
	 * @example sproutemail
	 *
	 * @return string
	 */
	final public function getId()
	{
		return preg_replace('/[^a-z0-9]+/i', '', StringHelper::toLowerCase($this->getName()));
	}

	public function getName()
	{
		return get_class($this);
	}

	/**
	 * Returns the mailer title to use when displaying a label or similar use case
	 *
	 * @example Sprout Email Service
	 *
	 * @return string
	 */
	abstract public function getTitle();

	/**
	 * Returns a short description of this mailer
	 *
	 * @example The Sprout Email Core Mailer uses the Craft API to send emails
	 *
	 * @return string
	 */
	abstract public function getDescription();

	/**
	 * Returns whether or not the mailer has registered routes to accomplish tasks within Sprout Email
	 *
	 * @return bool
	 */
	public function hasCpSection()
	{
		return false;
	}

	/**
	 * Returns whether or not the mailer has settings to display
	 *
	 * @return bool
	 */
	public function hasCpSettings()
	{
		$settings = $this->defineSettings();

		return is_array($settings) && count($settings);
	}

	/**
	 * Returns the URL for this Mailer's CP Settings
	 *
	 * @return null|string
	 */
	public function getCpSettingsUrl()
	{
		if (!$this->hasCpSettings())
		{
			return null;
		}

		return UrlHelper::cpUrl('sprout-email/settings/mailers/' . $this->getId());
	}

	/**
	 * Enables mailers to define their own settings and validation for them
	 *
	 * @return array
	 */
	public function defineSettings()
	{
		return array();
	}

	/**
	 * Returns the value that should be saved to the settings column for this mailer
	 *
	 * @example
	 * return craft()->request->getPost('sproutemail');
	 *
	 * @return mixed
	 */
	public function prepSettings()
	{
		return \Craft::$app->getRequest()->getParam($this->getId());
	}

	/**
	 * Returns the settings model for this mailer
	 *
	 * @return Model
	 */
	public function getSettings()
	{
		$record = SproutEmail::$app->mailers->getMailerRecordByName($this->getId());

		$settingsFromDb = isset($record->settings) ? $record->settings : array();

		$model = new Model($this->defineSettings());

		$model->setAttributes($settingsFromDb);

		return $model;
	}

	/**
	 * Returns a rendered html string to use for capturing settings input
	 *
	 * @return string
	 */
	public function getSettingsHtml(array $settings = array())
	{
		return '';
	}

	/**
	 * Gives a mailer the responsibility to send Notification Emails
	 * if they implement SproutEmailNotificationEmailSenderInterface
	 *
	 * @param NotificationEmail $notificationEmail
	 * @param                                    $object
	 */
	public function sendNotificationEmail(NotificationEmail $notificationEmail, $object)
	{
	}

	/**
	 * Gives a mailer the responsibility to send Campaign Emails
	 * if they implement SproutEmailCampaignEmailSenderInterface
	 *
	 * @param CampaignEmail $campaignEmail
	 * @param CampaignTypeModel  $campaign
	 *
	 * @internal param SproutEmail_CampaignEmailModel $campaignEmail
	 */
	abstract public function sendCampaignEmail(CampaignEmail $campaignEmail,
	                                           CampaignTypeModel $campaignType);

	/**
	 * Gives mailers the ability to include their own modal resources and register their dynamic action handlers
	 *
	 * @example
	 * Mailers should be calling the following functions from within their implementation
	 *
	 * craft()->templates->includeJs(File|Resource)();
	 * craft()->templates->includeCss(File|Resource)();
	 *
	 * @note
	 * To register a dynamic action handler, mailers should listen for sproutEmailBeforeRender
	 * $(document).on('sproutEmailBeforeRender', function(e, content) {});
	 */
	public function includeModalResources()
	{
	}

	/**
	 * Gives a mailer the ability to register an action to post to when a [prepare] modal is launched
	 *
	 * @return string
	 */
	public function getActionForPrepareModal()
	{
		return 'sprout-email/mailer/get-prepareModal';
	}

	/**
	 * @param CampaignEmail $campaignEmail
	 * @param CampaignTypeModel  $campaignType
	 *
	 * @return mixed
	 */
	abstract public function getPrepareModalHtml(CampaignEmail $campaignEmail,
	                                             CampaignTypeModel $campaignType);

	/**
	 * Returns whether this Mailer supports mailing lists
	 *
	 * @return bool Whether this Mailer supports lists. Default is `true`.
	 */
	public function hasLists()
	{
		return true;
	}

	/**
	 * Returns the Lists available to this Mailer
	 */
	public function getLists()
	{
		return array();
	}

	/**
	 * Returns the HTML for our List Settings on the Campaign and Notification Email edit page
	 *
	 * @param array $values
	 *
	 * @return null
	 */
	public function getListsHtml($values = array())
	{
		return null;
	}

	/**
	 * Return true to allow and show mailer dynamic recipients
	 *
	 * @return bool
	 */
	public function hasInlineRecipients()
	{
		return false;
	}

	/**
	 * Prepare the list data before we save it in the database
	 *
	 * @param $lists
	 *
	 * @return mixed
	 */
	public function prepListSettings($lists)
	{
		return $lists;
	}

	/**
	 * Allow modification of campaignType model before it is saved.
	 *
	 * @param CampaignTypeModel $model
	 *
	 * @return CampaignTypeModel
	 */
	public function prepareSave(CampaignTypeModel $model)
	{
		return $model;
	}
}