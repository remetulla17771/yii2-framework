<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use yii\base\InvalidConfigException;
use Yii;
use yii\base\Model;

/**
 * EachValidator serves validation of the array attributes.
 * It perform validation of each array element using any other validator specified by [[rule]].
 *
 * ~~~php
 * class MyModel extends Model
 * {
 *     public $arrayAttribute = [];
 *
 *     public function rules()
 *     {
 *         return [
 *             ['arrayAttribute', 'each', 'rule' => ['trim']],
 *             ['arrayAttribute', 'each', 'rule' => ['integer']],
 *         ]
 *     }
 * }
 * ~~~
 *
 * Note: this validator will not work with validation declared via model inline method. If you declare inline
 * validation rule for attribute, you should avoid usage of this validator and iterate over array attribute
 * values manually inside your code.
 *
 * @property Validator $validator related validator instance. This property is read only.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class EachValidator extends Validator
{
    /**
     * @var array|Validator definition of the validation rule, which should be used on array values.
     * It should be specified in the same format as at [[yii\base\Model::rules()]], except it should not
     * contain attribute list as the first element.
     * For example:
     *
     * ~~~
     * ['integer']
     * ['match', 'pattern' => '/[a-z]/is']
     * ~~~
     *
     * Please refer to [[yii\base\Model::rules()]] for more details.
     */
    public $rule;
    /**
     * @var boolean whether to use error message composed by validator declared via [[rule]] if its validation fails.
     * If enabled, error message specified for this validator itself will appear only if attribute value is not an array.
     * If disabled, own error message value will be used always.
     */
    public $allowMessageFromRule = true;

    /**
     * @var Validator validator instance.
     */
    private $_validator;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = $this->allowMessageFromRule ? Yii::t('yii', '{attribute} should be an array.') : Yii::t('yii', '{attribute} is invalid.');
        }
    }

    /**
     * Returns the validator declared in [[rule]].
     * @return Validator the declared validator.
     */
    public function getValidator()
    {
        if ($this->_validator === null) {
            $this->_validator = $this->createValidators();
        }
        return $this->_validator;
    }

    /**
     * Creates validator object based on the validation rule specified in [[rule]].
     * @return Validator validator instance
     * @throws InvalidConfigException if any validation rule configuration is invalid
     */
    private function createValidators()
    {
        $rule = $this->rule;
        if ($rule instanceof Validator) {
            return $rule;
        } elseif (is_array($rule) && isset($rule[0])) { // validator type
            return Validator::createValidator($rule[0], new Model(), $this->attributes, array_slice($rule, 1));
        } else {
            throw new InvalidConfigException('Invalid validation rule: a rule must be an array specifying validator type.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        $validator = $this->getValidator();
        if ($validator instanceof FilterValidator && is_array($value)) {
            $filteredValue = [];
            foreach ($value as $k => $v) {
                if (!$validator->skipOnArray || !is_array($v)) {
                    $filteredValue[$k] = call_user_func($validator->filter, $v);
                }
            }
            $model->$attribute = $filteredValue;
        } else {
            parent::validateAttribute($model, $attribute);
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        if (!is_array($value)) {
            return [$this->message, []];
        }

        $validator = $this->getValidator();
        foreach ($value as $v) {
            $result = $validator->validateValue($v);
            if ($result !== null) {
                return $this->allowMessageFromRule ? $result : [$this->message, []];
            }
        }

        return null;
    }
}