<?php

namespace barrelstrength\sproutbase\elements\sproutemail;

use barrelstrength\sproutemail\assetbundles\email\EmailAsset;
use barrelstrength\sproutemail\elements\actions\DeleteEmail;
use barrelstrength\sproutemail\elements\db\NotificationEmailQuery;
use barrelstrength\sproutemail\records\NotificationEmail as NotificationEmailRecord;
use barrelstrength\sproutemail\SproutEmail;
use craft\base\Element;
use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;

class NotificationEmail extends Element
{
    public $subjectLine;
    public $name;
    public $template;
    public $eventId;
    public $options;
    public $recipients;
    public $listSettings;
    public $fromName;
    public $fromEmail;
    public $replyToEmail;
    public $enableFileAttachments;
    public $dateCreated;
    public $dateUpdated;
    public $fieldLayoutId;
    public $send;
    public $preview;

    const ENABLED = 'enabled';
    const PENDING = 'pending';
    const DISABLED = 'disabled';

    public static function displayName(): string
    {
        return SproutEmail::t('Notification Email');
    }

    public static function refHandle()
    {
        return 'notificationEmail';
    }


    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    public function getStatuses()
    {
        return [
            self::ENABLED => Craft::t('sprout-email', 'enabled'),
            self::PENDING => Craft::t('sprout-email', 'pending'),
            self::DISABLED => Craft::t('sprout-email', 'disabled')
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'sprout-email/notifications/edit/'.$this->id
        );
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => SproutEmail::t('All notifications')
            ]
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'title' => ['label' => Craft::t('sprout-email', 'Subject Line')],
            'name' => ['label' => Craft::t('sprout-email', 'Notification Name')],
            'dateCreated' => ['label' => Craft::t('sprout-email', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('sprout-email', 'Date Updated')],
            'send' => ['label' => Craft::t('sprout-email', 'Send')],
            'preview' => ['label' => Craft::t('sprout-email', 'Preview'), 'icon' => 'view']
        ];

        return $attributes;
    }

    /**
     * @param string $attribute
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getTableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'send') {
            return Craft::$app->getView()->renderTemplate('sprout-email/_partials/notifications/prepare-link', [
                'notification' => $this
            ]);
        }

        if ($attribute === 'preview') {
            $shareUrl = null;

            if ($this->id && $this->getUrl()) {
                $shareUrl = UrlHelper::actionUrl('sprout-email/notification-emails/share-notificationEmail', [
                    'notificationId' => $this->id,
                ]);
            }

            return Craft::$app->getView()->renderTemplate('sprout-email/_partials/notifications/preview-links', [
                'email' => $this,
                'shareUrl' => $shareUrl,
                'type' => $attribute
            ]);
        }
        return parent::getTableAttributeHtml($attribute);
    }

    public static function find(): ElementQueryInterface
    {
        return new NotificationEmailQuery(static::class);
    }

    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(static::class);
    }

    public function afterSave(bool $isNew)
    {
        // Get the entry record
        if (!$isNew) {
            $record = NotificationEmailRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid campaign email ID: '.$this->id);
            }
        } else {
            $record = new NotificationEmailRecord();
            $record->id = $this->id;
        }

        $record->name = $this->name;
        $record->template = $this->template;
        $record->eventId = $this->eventId;
        $record->options = $this->options;
        $record->subjectLine = $this->subjectLine;
        $record->fieldLayoutId = $this->fieldLayoutId;
        $record->fromName = $this->fromName;
        $record->fromEmail = $this->fromEmail;
        $record->replyToEmail = $this->replyToEmail;
        $record->recipients = $this->recipients;
        $record->dateCreated = $this->dateCreated;
        $record->dateUpdated = $this->dateUpdated;

        $record->save(false);

        // Update the entry's descendants, who may be using this entry's URI in their own URIs
        Craft::$app->getElements()->updateElementSlugAndUri($this, true, true);

        parent::afterSave($isNew);
    }

    public static function indexHtml(ElementQueryInterface $elementQuery, array $disabledElementIds = null, array $viewState, string $sourceKey = null, string $context = null, bool $includeContainer, bool $showCheckboxes): string
    {
        $html = parent::indexHtml($elementQuery, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer,
            true);

        Craft::$app->getView()->registerAssetBundle(EmailAsset::class);
        Craft::$app->getView()->registerJs("var sproutModalInstance = new SproutModal(); sproutModalInstance.init();");
        SproutEmail::$app->mailers->includeMailerModalResources();

        return $html;
    }

    public function getMailer()
    {
        // All Notification Emails use the Default Mailer
        return SproutEmail::$app->mailers->getMailerByName('barrelstrength\\sproutemail\\integrations\\sproutemail\\mailers\\DefaultMailer');
    }

    public function isReady()
    {
        return (bool)($this->getStatus() == static::ENABLED);
    }

    /**
     * @param mixed|null $element
     *
     * @throws \Exception
     * @return array|string
     */
    public function getRecipients($element = null)
    {
        return SproutEmail::$app->getRecipients($element, $this);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = DeleteEmail::class;

        return $actions;
    }

    public function getUriFormat()
    {
        return "sprout-email/{slug}";
    }

    public function getUrl()
    {
        if ($this->uri !== null) {
            $url = UrlHelper::siteUrl($this->uri, null, null);

            return $url;
        }

        return null;
    }

    public function route()
    {
        // Only expose notification emails that have tokens and allow Live Preview requests
        if (!Craft::$app->request->getParam(Craft::$app->config->getGeneral()->tokenParam)
            && !Craft::$app->getRequest()->getIsLivePreview()) {
            throw new \HttpException(404);
        }
        $extension = null;

        if (($type = Craft::$app->request->get('type'))) {
            $extension = in_array(strtolower($type), ['txt', 'text']) ? '.txt' : null;
        }

        if (!Craft::$app->getView()->doesTemplateExist($this->template.$extension)) {
            $templateName = $this->template.$extension;

            SproutEmail::$app->utilities->addError(Craft::t('sprout-email', "The template '{templateName}' could not be found", [
                'templateName' => $templateName
            ]));
        }

        $event = SproutEmail::$app->notificationEmails->getEventById($this->eventId);

        $object = $event ? $event->getMockedParams() : null;

        return [
            'templates/render', [
                'template' => $this->template.$extension,
                'variables' => [
                    'email' => $this,
                    'object' => $object
                ]
            ]
        ];
    }

    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['subjectLine', 'name'], 'required'];

        return $rules;
    }
}