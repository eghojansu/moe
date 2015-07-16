<?php

namespace moe;

use tools\Template;

class AbstractController
{
    /**
     * Render view or array (as json)
     * @param  mixed  $object  String view or array
     * @param  boolean $return wether to return content or not
     * @return string or nothing
     */
    public function render($object, $return = false)
    {
        $content = is_array($object)?json_encode($object):
            Template::instance()->render($object.(
                (strpos($object, '.')===false?'.html':'')));
        if ($return)
            return $content;
        echo $content;
    }
}
