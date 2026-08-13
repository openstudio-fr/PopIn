<?php

namespace PopIn\Form;

use PopIn\Form\Base\PopInCampaignCreateForm as BasePopInCampaignCreateForm;
use PopIn\PopIn;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Pop-in campaign creation form.
 */
class PopInCampaignCreateForm extends BaseForm
{
    const PHP_DATETIME_FORMAT = "Y-m-d H:i:s";
    const PHP_INTLDATE_FORMAT = "yyyy-MM-dd HH:mm:ss";
    const MOMENT_JS_DATE_FORMAT = "YYYY-MM-DD HH:mm:ss";

    const FORM_NAME = "pop_in_campaign_create";

    public static function getName(): string
    {
        return static::FORM_NAME;
    }

    public function readKey($key, array $keys, $default = '')
    {
        if (isset($keys[$key])) {
            return $keys[$key];
        }

        return $default;
    }

    public function getFieldsIdKeys()
    {
        return array(
            "start" => "pop_in_campaign_start",
            "end" => "pop_in_campaign_end",
            "content_source_type" => "pop_in_campaign_content_source_type",
            "content_source_id" => "pop_in_campaign_content_source_id",
            "custom_title" => "pop_in_campaign_custom_title",
            "custom_image" => "pop_in_campaign_custom_image",
            "custom_description" => "pop_in_campaign_custom_description",
            "custom_postscriptum" => "pop_in_campaign_custom_postscriptum",
            "custom_link" => "pop_in_campaign_custom_link",
            "custom_link_text" => "pop_in_campaign_custom_link_text",
            "exclude_category_ids" => "pop_in_exclude_category_ids",
            "exclude_folder_ids" => "pop_in_exclude_folder_ids",
            "exclude_content_ids" => "pop_in_exclude_content_ids",
            "exclude_home" => "pop_in_exclude_home",
            "exclude_url" => "pop_in_exclude_url",
            "persistent" => "pop_in_persistent"
        );
    }

    public function buildForm()
    {
        $translationKeys = $this->getTranslationKeys();
        $fieldsIdKeys = $this->getFieldsIdKeys();

        $this->addStartField($translationKeys, $fieldsIdKeys);
        $this->addEndField($translationKeys, $fieldsIdKeys);
        $this->addContentSourceTypeField($translationKeys, $fieldsIdKeys);
        $this->addContentSourceIdField($translationKeys, $fieldsIdKeys);

        $this->addTextField("custom_title");
        $this->addTextField("custom_description");
        $this->addTextField("custom_postscriptum");
        $this->addTextField("custom_link");
        $this->addTextField("custom_link_text");
        $this->addTextField("exclude_category_ids");
        $this->addTextField("exclude_folder_ids");
        $this->addTextField("exclude_content_ids");
        $this->addTextField("exclude_home");
        $this->addTextField("exclude_url");
        $this->addTextField("persistent");

        $this->formBuilder->add(
            "custom_image",
            FileType::class,
            [
                "label" => $this->translator->trans(
                    $this->readKey("custom_image", $translationKeys),
                    [],
                    PopIn::MESSAGE_DOMAIN
                ),
                "label_attr" => [
                    "for" => $this->readKey("custom_image", $fieldsIdKeys)
                ],
                "required" => false
            ]
        );
    }

    public function getTranslationKeys()
    {
        return array(
            "start" => "Start",
            "end" => "End",
            "content_source_type" => "Content source type",
            "content_source_data" => "Content source data",
            "exclude_category_ids" => "Exclude category ids",
            "exclude_folder_ids" => "Exclude Folder ids",
            "exclude_content_ids" => "Exclude content ids",
            "exclude_home" => "Exclude home",
            "exclude_url" => "Exclude URL",
            "persistent" => "Persistent"
        );
    }

    protected function addDateField($fieldName, array $translationKeys, array $fieldsIdKeys)
    {
        $this->formBuilder->add(
            $fieldName,
            DateTimeType::class,
            [
                "label" => $this->translator->trans(
                    $this->readKey($fieldName, $translationKeys),
                    [],
                    PopIn::MESSAGE_DOMAIN
                ),
                "label_attr" => [
                    "for" => $this->readKey($fieldName, $fieldsIdKeys),
                    "php_datetime_format" => static::PHP_DATETIME_FORMAT,
                    "moment_js_date_format" => static::MOMENT_JS_DATE_FORMAT,
                ],
                "required" => false,
                "widget" => "single_text"
            ]
        );
    }

    protected function addStartField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->addDateField("start", $translationKeys, $fieldsIdKeys);
    }

    protected function addEndField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->addDateField("end", $translationKeys, $fieldsIdKeys);
    }

    protected function addContentSourceTypeField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->formBuilder->add(
            "content_source_type",
            ChoiceType::class,
            [
                "label" => $this->translator->trans(
                    $this->readKey("content_source_type", $translationKeys),
                    [],
                    PopIn::MESSAGE_DOMAIN
                ),
                "label_attr" => [
                    "for" => $this->readKey("content_source_type", $fieldsIdKeys)
                ],
                "required" => false,
                'constraints' => [
                    new NotBlank(),
                ],
                "choices" => [
                    $this->translator->trans("Custom", [], PopIn::MESSAGE_DOMAIN)=> "custom",
                    $this->translator->trans("Content")=> "content",
                    $this->translator->trans("Image") => "content-image",
                    // do not use the core "Template" translation, as it refers to product template
                    $this->translator->trans("Template", [], PopIn::MESSAGE_DOMAIN) => "template",
                    $this->translator->trans("Hook") => "hook",
                ],
            ]
        );
    }

    protected function addContentSourceIdField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->formBuilder->add("content_source_id", TextType::class, array(
            "label" => $this->translator->trans($this->readKey("content_source_id", $translationKeys), [], PopIn::MESSAGE_DOMAIN),
            "label_attr" => ["for" => $this->readKey("content_source_id", $fieldsIdKeys)],
            "required" => false,
            "constraints" => array(
            ),
            "attr" => array(
            )
        ));
    }

    protected function addTextField($name)
    {
        $translationKeys = $this->getTranslationKeys();
        $fieldsIdKeys = $this->getFieldsIdKeys();

        $this->formBuilder->add(
            $name,
            TextType::class,
            [
                "label" => $this->translator->trans(
                    $this->readKey($name, $translationKeys),
                    [],
                    PopIn::MESSAGE_DOMAIN
                ),
                "label_attr" => [
                    "for" => $this->readKey($name, $fieldsIdKeys)
                ],
                "required" => false
            ]
        );
    }
}
