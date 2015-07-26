<?php

namespace moe;

class Helper
{
    /**
     * Generate random string
     * @param  int $len random string length
     * @return string     random string
     */
    public static function random($len) {
        $len     = abs($len);
        $pool    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $poolLen = strlen($pool);
        $str     = '';
        while ($len-- > 0)
            $str .= substr($pool, rand(0, $poolLen), 1);
        return $str;
    }

    /**
     * Output the data
     * @param  mixed  $data
     * @param  bool $exit wether to exit after dumping or not
     */
    public static function pre($data, $exit = false)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        !$exit || exit(str_repeat('<br>', 2).' '.$exit);
        echo '<hr>';
    }

    /**
     * Generate html list menu
     * @param  array $menu
     * @param  string $active active url (token alias)
     * @return string         ul html
     */
    public static function bootstrapMenu($menu, $active) {
        $menu || $menu = array();
        $active = str_replace('@', '', $active);
        isset($menu['items']) || $menu['items'] = array();
        isset($menu['level']) || $menu['level'] = -1;
        $menu['level']++;
        $eol = "\n";

        $str = sprintf('<ul class="%s">',
            ($menu['level']>0?'dropdown-menu':$menu['class'])).$eol;
        foreach ($menu['items'] as $key => $value) {
            if (isset($value['show']) && !$value['show'])
                continue;

            isset($value['label']) || $value['label'] = $key;
            $hasChild = isset($value['items']);
            $liAttr   = array(
                'class'=>array(),
                );
            $aAttr    = array(
                'href'=>Instance::get('ALIASES.'.$key)?Instance::siteUrl($key):'javascript:;',
                'class'=>array(),
                'data-toggle'=>array(),
                );

            if ($menu['level'] < 1 and $hasChild) {
                array_push($aAttr['class'], 'dropdown-toggle');
                array_push($aAttr['data-toggle'], 'dropdown');
                array_push($liAttr['class'], 'dropdown');
                $value['label'] .= ' <span class="caret"></span>';
            }
            $child = '';
            if ($hasChild) {
                $value['level']  = $menu['level'] + 1;
                $child = $eol.self::bootstrapMenu($value, $active).$eol;
            }

            !($active === $key || strpos($child, 'class="active"')!==false) ||
                array_push($liAttr['class'], 'active');

            $str .= '<li';
            foreach ($liAttr as $key2 => $value2)
                !$value2 || $str .= ' '.$key2.'="'.
                    (is_array($value2)?implode(' ', $value2):$value2).'"';
            $str .= '><a';
            foreach ($aAttr as $key2 => $value2)
                !$value2 || $str .= ' '.$key2.'="'.
                    (is_array($value2)?implode(' ', $value2):$value2).'"';
            $str .= '>'.$value['label'].'</a>';
            $str .= $child;
            $str .= '</li>'.$eol;
        }
        $str .= '</ul>';

        return $str;
    }

    public static function optionMonth($selected) {
        $result = '';
        for ($i=1; $i <= 12; $i++)
            $result .= '<option value="'.$i.'"'.($i==$selected?' selected':'').
                '>'.date("F", mktime(0, 0, 0, $i, 10)).'</option>';
        return $result;
    }

    public static function optionRange($start, $end, $selected = null) {
        $result = '';
        if ($start <= $end)
            for ($i=$start; $i <= $end; $i++)
                $result .= '<option value="'.$i.'"'.
                    ($i==$selected?' selected':'').'>'.$i.'</option>';
        else
            for ($i=$start; $i >= $end; $i--)
                $result .= '<option value="'.$i.'"'.
                    ($i==$selected?' selected':'').'>'.$i.'</option>';
        return $result;
    }
}
