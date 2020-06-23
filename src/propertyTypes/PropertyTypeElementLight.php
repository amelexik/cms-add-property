<?php

namespace skeeks\modules\cms\addProperty\propertyTypes;

use skeeks\cms\components\Cms;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\relatedProperties\models\RelatedPropertiesModel;
use skeeks\cms\relatedProperties\PropertyType;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use Yii;

/**
 * Class PropertyTypeElement
 * @package skeeks\cms\relatedProperties\propertyTypes
 */
class PropertyTypeElementLight extends PropertyType
{


    public $code = 'EL';
    public $name = "Привязка к элементу (облегченная)";

    const FIELD_ELEMENT_SELECT = "select";
    const FIELD_ELEMENT_SELECT_MULTI = "selectMulti";

    public $fieldElement = self::FIELD_ELEMENT_SELECT;
    public $content_id;

    public static function fieldElements()
    {
        return [
            self::FIELD_ELEMENT_SELECT       => \Yii::t('skeeks/cms', 'Combobox') . ' (select)',
            self::FIELD_ELEMENT_SELECT_MULTI => \Yii::t('skeeks/cms', 'Combobox') . ' (select multiple)',
        ];
    }

    public function init()
    {
        parent::init();

        if (!$this->name) {
            $this->name = \Yii::t('skeeks/cms', 'Binding to an element');
        }
    }

    /**
     * @return bool
     */
    public function getIsMultiple()
    {
        if (in_array($this->fieldElement, [
            self::FIELD_ELEMENT_SELECT_MULTI
        ])) {
            return true;
        }

        return false;
    }

    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(),
            [
                'content_id'   => \Yii::t('skeeks/cms', 'Content'),
                'fieldElement' => \Yii::t('skeeks/cms', 'Form element type'),
            ]);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(),
            [
                ['content_id', 'integer'],
                ['fieldElement', 'in', 'range' => array_keys(static::fieldElements())],
                ['fieldElement', 'string'],
            ]);
    }

    /**
     * @return \yii\widgets\ActiveField
     */
    public function renderForActiveForm()
    {
        $field = parent::renderForActiveForm();

        $query = (new \yii\db\Query())
            ->select(['cce.active', 'cce.id', 'cce.content_id', 'CONCAT(cce.name, " (", ct.name,")") as name'])
            ->where(['cce.active' => Cms::BOOL_Y])
            ->from("{{%cms_content_element}} cce")
            ->leftJoin('{{%cms_tree}} ct','ct.id = cce.tree_id');


        if ($this->content_id) {
            $query->andWhere(['cce.content_id' => $this->content_id]);
        }

        if ($this->fieldElement == self::FIELD_ELEMENT_SELECT) {
            $config = [];
            if ($this->property->is_required == Cms::BOOL_Y) {
                $config['allowDeselect'] = false;
            } else {
                $config['allowDeselect'] = true;
            }

            $field = $this->activeForm->fieldSelect(
                $this->property->relatedPropertiesModel,
                $this->property->code,
                ArrayHelper::map($query->all(), 'id', 'name'),
                $config
            );
        } else {
            if ($this->fieldElement == self::FIELD_ELEMENT_SELECT_MULTI) {
                $field = $this->activeForm->fieldSelectMulti(
                    $this->property->relatedPropertiesModel,
                    $this->property->code,
                    ArrayHelper::map($query->all(), 'id', 'name'),
                    []
                );
            }
        }


        if (!$field) {
            return '';
        }


        return $field;
    }


    /**
     * @return string
     */
    public function renderConfigFormFields(ActiveForm $activeForm)
    {
        $result = $activeForm->fieldSelect($this, 'fieldElement',
            self::fieldElements());
        $result .= $activeForm->fieldSelect($this, 'content_id', \skeeks\cms\models\CmsContent::getDataForSelect());

        return $result;
    }

    /**
     * @varsion > 3.0.2
     *
     * @return $this
     */
    public function addRules()
    {
        if ($this->isMultiple) {
            $this->property->relatedPropertiesModel->addRule($this->property->code, 'safe');
        } else {
            $this->property->relatedPropertiesModel->addRule($this->property->code, 'integer');
        }

        if ($this->property->isRequired) {
            $this->property->relatedPropertiesModel->addRule($this->property->code, 'required');
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getAsText()
    {
        $value = $this->property->relatedPropertiesModel->getAttribute($this->property->code);

        if ($this->isMultiple) {
            $data = ArrayHelper::map(CmsContentElement::find()->where(['id' => $value])->all(), 'id', 'name');
            return implode(', ', $data);
        } else {
            if ($element = CmsContentElement::find()->where(['id' => $value])->one()) {
                return $element->name;
            }

            return "";
        }
    }
}