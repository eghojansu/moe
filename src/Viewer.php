<?php

namespace moe;

class Viewer extends AbstractModel
{
    protected $isView = true;

    public function schema()
    {
        return array();
    }

    public function __construct($view_name)
    {
        parent::__construct();
        $this->_properties['table_name'] = $view_name;
    }
}
