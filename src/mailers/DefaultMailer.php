<?php

namespace barrelstrength\sproutbase\mailers;

use barrelstrength\sproutbase\base\TemplateTrait;
use barrelstrength\sproutbase\contracts\sproutemail\BaseMailer;
use barrelstrength\sproutbase\contracts\sproutemail\NotificationEmailSenderInterface;
use barrelstrength\sproutbase\models\sproutemail\Message;
use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutbase\elements\sproutemail\NotificationEmail;
use barrelstrength\sproutemail\models\CampaignType;
use barrelstrength\sproutbase\models\sproutbase\Response;
use barrelstrength\sproutbase\models\sproutemail\Recipient;
use barrelstrength\sproutemail\SproutEmail;
use barrelstrength\sproutlists\integrations\sproutlists\SubscriberListType;
use barrelstrength\sproutlists\records\Lists;
use barrelstrength\sproutlists\SproutLists;
use craft\helpers\Json;
use craft\helpers\Template;
use Craft;
use craft\helpers\UrlHelper;
use yii\base\Exception;
use yii\base\InvalidArgumentException;


class DefaultMailer extends BaseMailer implements NotificationEmailSenderInterface
{
    use TemplateTrait;

    /**
     * @var
     */
    protected $lists;

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Sprout Email';
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Craft::t('sprout-base', 'Smart transactional email, easy recipient management, and advanced third party integrations.');
    }

    /**
     * @inheritdoc
     */
    public function hasCpSection()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(array $settings = [])
    {
        /** @noinspection NullCoalescingOperatorCanBeUsedInspection */
        $settings = isset($settings['settings']) ? $settings['settings'] : $this->getSettings();

        $html = Craft::$app->getView()->renderTemplate('sprout-base/_integrations/mailers/defaultmailer/settings', [
            'settings' => $settings
        ]);

        return Template::raw($html);
    }

    /**
     * @inheritdoc
     */
    public function sendCampaignEmail(CampaignEmail $campaignEmail, CampaignType $campaignType)
    {
        $email = new Message();

        try {
            $response = [];

            $params = [
                'email' => $campaignEmail,
                'campaignType' => $campaignType,
            ];

            $email->setFrom([$campaignEmail->fromEmail => $campaignEmail->fromName]);
            $email->setSubject($campaignEmail->subjectLine);

            if ($campaignEmail->replyToEmail && filter_var($campaignEmail->replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $email->setReplyTo($campaignEmail->replyToEmail);
            }

            $recipients = Craft::$app->getRequest()->getBodyParam('recipients');

            if ($recipients === null) {
                throw new InvalidArgumentException(Craft::t('sprout-base', 'Empty recipients.'));
            }

            $result = $this->getValidAndInvalidRecipients($recipients);

            $invalidRecipients = $result['invalid'];
            $validRecipients = $result['valid'];

            if (!empty($invalidRecipients)) {
                $invalidEmails = implode('<br/>', $invalidRecipients);

                throw new InvalidArgumentException(Craft::t('sprout-base', 'The following recipient email addresses do not validate: {invalidEmails}',
                    [
                        'invalidEmails' => $invalidEmails
                    ]));
            }

            $recipients = $validRecipients;

            foreach ($recipients as $recipient) {
                try {
                    $params['recipient'] = $recipient;
                    $body = $this->renderSiteTemplateIfExists($campaignType->template.'.txt', $params);

                    $email->setTextBody($body);
                    $htmlBody = $this->renderSiteTemplateIfExists($campaignType->template, $params);

                    $email->setHtmlBody($htmlBody);
                    $name = $recipient->firstName.' '.$recipient->lastName;
                    $email->setTo([$recipient->email => $name]);

                    SproutBase::$app->mailers->sendEmail($email);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            $response['emailModel'] = $email;

            return Response::createModalResponse(
                'sprout-base/sproutemail/_modals/response',
                [
                    'email' => $campaignEmail,
                    'campaign' => $campaignType,
                    'emailModel' => $response['emailModel'],
                    'message' => Craft::t('sprout-base', 'Campaign sent successfully to email.'),
                ]
            );
        } catch (Exception $e) {
            SproutBase::$app->common->addError('fail-campaign-email', $e->getMessage());

            return Response::createErrorModalResponse(
                'sprout-base/sproutemail/_modals/response',
                [
                    'email' => $campaignEmail,
                    'campaign' => $campaignType,
                    'message' => Craft::t('sprout-base', $e->getMessage()),
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function sendNotificationEmail(NotificationEmail $notificationEmail, $object = null, $useMockData = false)
    {
        // Allow disabled emails to be tested
        if (!$notificationEmail->isReady() && !$useMockData) {
            return false;
        }

        $recipients = $this->prepareRecipients($notificationEmail, $object, $useMockData);

        if (empty($recipients)) {
            SproutBase::$app->common->addError('no-recipients', Craft::t('sprout-base', 'No recipients found.'));
        }

        $template = SproutBase::$app->sproutEmail->getEmailTemplate($notificationEmail);

        $view = Craft::$app->getView();
        $oldTemplatePath = $view->getTemplatesPath();

        $view->setTemplatesPath($template);

        /** @var Message $message */
        $message = SproutBase::$app->notifications->getNotificationEmailMessage($notificationEmail, $object);

        $view->setTemplatesPath($oldTemplatePath);

        $body = $message->renderedBody;
        $htmlBody = $message->renderedHtmlBody;

        $templateErrors = SproutBase::$app->common->getErrors();

        SproutBase::error($templateErrors);

        if (empty($templateErrors) && (empty($body) || empty($htmlBody))) {
            $message = Craft::t('sprout-base', 'Email Text or HTML template cannot be blank. Check template setting.');

            SproutBase::$app->common->addError('blank-template', $message);
        }

        $processedRecipients = [];

        foreach ($recipients as $recipient) {
            $toEmail = $this->renderObjectTemplateSafely($recipient->email, $object);

            $name = $recipient->firstName.' '.$recipient->lastName;

            /**
             * @var $message Message
             */
            $message->setTo([$toEmail => $name]);

            if (array_key_exists($toEmail, $processedRecipients)) {
                continue;
            }

            try {
                $variables = [];

                if (Craft::$app->plugins->getPlugin('sprout-email')) {
                    $infoTable = SproutEmail::$app->sentEmails->createInfoTableModel('sprout-email', [
                        'emailType' => Craft::t('sprout-base', 'Notification'),
                        'deliveryType' => $useMockData ? Craft::t('sprout-base', 'Test') : Craft::t('sprout-base', 'Live')
                    ]);

                    $variables = [
                        'email' => $notificationEmail,
                        'renderedEmail' => $message,
                        'object' => $object,
                        'recipients' => $recipients,
                        'processedRecipients' => null,
                        'info' => $infoTable
                    ];
                }

                if (SproutBase::$app->mailers->sendEmail($message, $variables)) {
                    $processedRecipients[] = $toEmail;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                SproutBase::$app->common->addError('fail-send-email', $e->getMessage());
            }
        }

        // Trigger on send notification event
        if (!empty($processedRecipients)) {
            $variables['processedRecipients'] = $processedRecipients;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPrepareModalHtml(CampaignEmail $campaignEmail, CampaignType $campaignType)
    {
        if (!empty($campaignEmail->recipients)) {
            $recipients = $campaignEmail->recipients;
        }

        if (empty($recipients)) {
            $recipients = Craft::$app->getUser()->getIdentity()->email;
        }

        $errors = [];

        $errors = $this->getErrors($campaignEmail, $campaignType, $errors);

        return Craft::$app->getView()->renderTemplate('sprout-email/_modals/campaigns/prepareEmailSnapshot', [
            'campaignEmail' => $campaignEmail,
            'campaignType' => $campaignType,
            'recipients' => $recipients,
            'errors' => $errors
        ]);
    }

    /**
     * @inheritdoc
     */
    public function hasInlineRecipients()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getLists()
    {
        if ($this->lists === null && Craft::$app->getPlugins()->getPlugin('sprout-lists') != null) {
            $listType = SproutLists::$app->lists
                ->getListType(SubscriberListType::class);

            $this->lists = $listType ? $listType->getLists() : [];
        }

        return $this->lists;
    }

    /**
     * @inheritdoc
     */
    public function getListsHtml($values = [])
    {
        $selected = [];
        $options = [];
        $lists = $this->getLists();

        if (!count($lists)) {
            return '';
        }

        foreach ($lists as $list) {
            $listName = $list->name;

            if (count($list->totalSubscribers)) {
                $listName .= ' ('.$list->totalSubscribers.')';
            } else {
                $listName .= ' (0)';
            }

            $options[] = [
                'label' => $listName,
                'value' => $list->id
            ];
        }

        $listIds = [];

        // Convert json format to array
        if ($values != null AND is_string($values)) {
            $listIds = Json::decode($values);
            $listIds = $listIds['listIds'];
        }

        if (!empty($listIds)) {
            foreach ($listIds as $key => $listId) {
                $selected[] = $listId;
            }
        }

        return Craft::$app->getView()->renderTemplate('sprout-base/sproutemail/_mailers/defaultmailer/lists', [
            'options' => $options,
            'values' => $selected,
        ]);
    }

    /**
     * @param NotificationEmail $notificationEmail
     * @param                   $object
     * @param                   $useMockData
     *
     * @return array
     */
    protected function prepareRecipients(NotificationEmail $notificationEmail, $object, $useMockData)
    {
        // Get recipients for test notifications
        if ($useMockData) {
            $recipients = Craft::$app->getRequest()->getBodyParam('recipients');

            if (empty($recipients)) {
                return [];
            }

            $recipients = Craft::$app->getRequest()->getBodyParam('recipients');

            $result = $this->getValidAndInvalidRecipients($recipients);

            $invalidRecipients = $result['invalid'];
            $validRecipients = $result['valid'];

            if (!empty($invalidRecipients)) {
                $invalidEmails = implode('<br>', $invalidRecipients);

                throw new InvalidArgumentException(Craft::t('sprout-base', 'Recipient email addresses do not validate: <br /> {invalidEmails}', [
                    'invalidEmails' => $invalidEmails
                ]));
            }

            return $validRecipients;
        }

        // Get recipients for live emails
        // @todo Craft 3 - improve and standardize how we use entryRecipients
        $recipients = $this->getRecipientsFromEmailElement($notificationEmail, $object);

        // @todo implement this when we develop sprout lists plugin
        if (Craft::$app->getPlugins()->getPlugin('sprout-lists') != null) {

            $listSettings = $notificationEmail->listSettings;
            $listIds = [];
            // Convert json format to array
            if ($listSettings != null AND is_string($listSettings)) {
                $listIds = Json::decode($listSettings);
                $listIds = $listIds['listIds'];
            }

            // Get all subscribers by list IDs from the SproutLists_SubscriberListType
            $listRecords = Lists::find()
                ->where(['id' => $listIds])->all();

            $sproutListsRecipientsInfo = [];
            if ($listRecords != null) {
                foreach ($listRecords as $listRecord) {
                    if (!empty($listRecord->subscribers)) {
                        foreach ($listRecord->subscribers as $subscriber) {
                            // Assign email as key to not repeat subscriber
                            $sproutListsRecipientsInfo[$subscriber->email] = $subscriber->getAttributes();
                        }
                    }
                }
            }

            $listRecipients = [];
            if ($sproutListsRecipientsInfo) {
                foreach ($sproutListsRecipientsInfo as $listRecipient) {
                    $recipientModel = new Recipient();
                    $recipientModel->setAttributes($listRecipient, false);

                    $listRecipients[] = $recipientModel;
                }
            }

            $recipients = array_merge($recipients, $listRecipients);
        }

        return $recipients;
    }

    /**
     * @param $email   The Notification Email or Campaign Email Element
     * @param $object  The $object defined by the custom event
     *
     * @return array
     */
    public function getRecipientsFromEmailElement($email, $object)
    {
        $recipients = [];

        $onTheFlyRecipients = $email->getRecipients($object);

        if (is_string($onTheFlyRecipients)) {
            $onTheFlyRecipients = explode(',', $onTheFlyRecipients);
        }

        if (count($onTheFlyRecipients)) {
            foreach ($onTheFlyRecipients as $index => $recipient) {
                $recipients[$index] = Recipient::create(
                    [
                        'firstName' => '',
                        'lastName' => '',
                        'email' => $recipient
                    ]
                );
            }
        }

        return $recipients;
    }

    /**
     * @param CampaignEmail     $campaignEmail
     * @param CampaignType      $campaignType
     * @param                   $errors
     *
     * @return array
     */
    public function getErrors(CampaignEmail $campaignEmail, CampaignType $campaignType, $errors)
    {
        $currentPluginHandle = Craft::$app->getRequest()->getSegment(1);
        $notificationEditSettingsUrl = UrlHelper::cpUrl($currentPluginHandle.'/settings/notifications/edit/'.$campaignType->id);

        if (empty($campaignType->template)) {
            $errors[] = Craft::t('sprout-base', 'Email Template setting is blank. <a href="{url}">Edit Settings</a>.',
                [
                    'url' => $notificationEditSettingsUrl
                ]);
        }

        return $errors;
    }
}
