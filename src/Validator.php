<?php

namespace moe;

use GUMP;

class Validator extends GUMP
{
    /**
     * Build validator
     *
     * Format:
     * [
     *     'field/fieldname'=>[ // fieldname
     *         'rule,param|rule2,param1,param2||'. // validation rule
     *         'rule,param|rule2,param1,param2', // sanitation rule
     *      ]
     * ]
     *
     * @param array $rules Validator rule
     */
    public function __construct(array $rules)
    {
        $validation = array();
        $sanitation = array();
        foreach ($rules as $key => $value) {
            $fields = explode('/', $key);
            $field  = $fields[0];
            !isset($fields[1]) || self::set_field_name($field, $fields[1]);
            $rule   = explode('||', $value);
            $validation[$field] = $rule[0];
            !isset($rule[1])   || $sanitation[$field] = $rule[1];
        }
        $this->validation_rules($validation);
        $this->filter_rules($sanitation);
    }
}
