<?php

namespace barrelstrength\sproutbase\elements\sproutemail\db;

use craft\elements\db\ElementQuery;
use craft\base\Element;
use Craft;

class NotificationEmailQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('sproutemail_notificationemails');
        $this->query->select([
            'sproutemail_notificationemails.name',
            'sproutemail_notificationemails.template',
            'sproutemail_notificationemails.eventId',
            'sproutemail_notificationemails.options',
            'sproutemail_notificationemails.subjectLine',
            'sproutemail_notificationemails.recipients',
            'sproutemail_notificationemails.listSettings',
            'sproutemail_notificationemails.fromName',
            'sproutemail_notificationemails.fromEmail',
            'sproutemail_notificationemails.replyToEmail',
            'sproutemail_notificationemails.enableFileAttachments',
            'sproutemail_notificationemails.dateCreated',
            'sproutemail_notificationemails.dateUpdated',
            'sproutemail_notificationemails.fieldLayoutId'
        ]);

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status)
    {
        $currentBase = Craft::$app->getRequest()->getSegment(1);

        /**
         * To show disabled notification emails on integrated plugins
         */
        if ($currentBase != 'sprout-email') {
            return ['elements.enabled' => ['0', '1']];
        }

        switch ($status) {
            case Element::STATUS_ENABLED:
                return ['elements.enabled' => '1'];
            case Element::STATUS_DISABLED:
                return ['elements.enabled' => '0'];
            default:
                return false;
        }
    }
}