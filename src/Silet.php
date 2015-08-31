<?php

namespace moe;

use ReflectionClass;

class Silet extends Prefab
{
    //@{ Error messages
    const
        E_Method='Call to undefined method %s()';
    //@}

    protected
        //! trigger
        $trigger,
        //! tag compiler
        $tags  = '',
        //! Custom tag handlers
        $custom=array(),
        //! tag that in other tag
        $intag = array(
            'foreach' => array('elseforeach'),
            'if'      => array('elseif', 'else'),
            'switch'  => array('case', 'default'),
            ),
        //! MIME type
        $mime,
        //! token filter
        $filter = array(
            'esc'=>'$this->esc',
            'raw'=>'$this->raw',
            'alias'=>'moe\Instance::alias',
            'format'=>'moe\Instance::format',
            'decode'=>'moe\Instance::decode',
            'encode'=>'moe\Instance::encode',
            'url'=>'moe\Instance::siteUrl',
        );

    /**
    *   Render template
    *   @return string
    *   @param $file string
    *   @param $mime string
    *   @param $hive array
    *   @param $ttl int
    **/
    function render($file,$mime='text/html',array $hive=NULL,$ttl=0) {
        $fw=Base::instance();
        $cache=Cache::instance();
        if (!is_dir($tmp=$fw->get('TEMP')))
            mkdir($tmp,Base::MODE,TRUE);
        foreach ($fw->split($fw->get('UI')) as $dir) {
            $cached=$cache->exists($hash=$fw->hash($dir.$file),$data);
            if ($cached && $cached[0]+$ttl>microtime(TRUE))
                return $data;
            if (is_file($view=$fw->fixslashes($dir.$file))) {
                if (!is_file($this->view=($tmp.
                    $fw->hash($fw->get('ROOT').$fw->get('BASE')).'.'.
                    $fw->hash($view).'.php')) ||
                    filemtime($this->view)<filemtime($view)) {
                    // Remove PHP code and comments
                    $text=preg_replace(
                        '/(?<!["\'])\h*<\?(?:php|\s*=).+?\?>\h*'.
                        '(?!["\'])|\{\*.+?\*\}/is','',
                        $fw->read($view));
                    $text = $this->parse($text);
                    $fw->write($this->view,$this->build($text));
                }
                if (isset($_COOKIE[session_name()]))
                    @session_start();
                $fw->sync('SESSION');
                if ($mime && PHP_SAPI!='cli' && !headers_sent())
                    header('Content-Type: '.($this->mime=$mime).'; '.
                        'charset='.$fw->get('ENCODING'));
                $data=$this->sandbox($hive);
                if(isset($this->trigger['afterrender']))
                    foreach ($this->trigger['afterrender'] as $func)
                        $data = $fw->call($func, $data);
                if ($ttl)
                    $cache->set($hash,$data);
                return $data;
            }
        }
        user_error(sprintf(Base::E_Open,$file),E_USER_ERROR);
    }

    /**
    *   Encode characters to equivalent HTML entities
    *   @return string
    *   @param $arg mixed
    **/
    function esc($arg) {
        $fw=Base::instance();
        return $fw->recursive($arg,
            function($val) use($fw) {
                return is_string($val)?$fw->encode($val):$val;
            }
        );
    }

    /**
    *   Decode HTML entities to equivalent characters
    *   @return string
    *   @param $arg mixed
    **/
    function raw($arg) {
        $fw=Base::instance();
        return $fw->recursive($arg,
            function($val) use($fw) {
                return is_string($val)?$fw->decode($val):$val;
            }
        );
    }

    /**
    *   Convert token to variable
    *   @return string
    *   @param $str string
    **/
    function token($str) {
        return trim(preg_replace('/\{\{(.+?)\}\}/s',trim('\1'),
            Base::instance()->compile($str)));
    }

    /**
    *   register token filter
    *   @param string $key
    *   @param string $func
    *   @return array
    */
    function filter($key=NULL,$func=NULL) {
        if (!$key)
            return array_keys($this->filter);
        if (!$func)
            return $this->filter[$key];
        $this->filter[$key]=$func;
    }

    /**
    *   post rendering handler
    *   @param $func callback
    */
    function afterrender($func) {
        $this->trigger['afterrender'][]=$func;
    }

    /**
    *   Extend template with custom tag
    *   @return NULL
    *   @param $tag string
    *   @param $func callback
    **/
    function extend($tag,$func) {
        $this->tags.='|'.$tag;
        $this->custom['_'.$tag]=$func;
    }

    protected function _foreach(array $node) {
        $attrib=$node['@attrib'];
        $in = isset($node['@in'])?$node['@in']:array();
        unset($node['@attrib'], $node['@in']);

        return
            '<?php '.
                (isset($attrib[1])?
                    (($ctr=$this->token($attrib[1])).'=0; '):'').
                'foreach (('.
                ($var = $this->token($attrib[0])).'?:array()) as '.
                (isset($attrib['as'][1])?
                    ($this->token(array_shift($attrib['as'])).'=>'):'').
                $this->token(reset($attrib['as'])).') {'.
                (isset($ctr)?(' ++'.$ctr.';'):'').' ?>'.
                $this->build($node).
            '<?php } ?>'.
            (empty($in)?'':
                '<?php if (!'.$var.') { ?>'.
                $this->build($in[0][1]).
                '<?php } ?>');
    }

    protected function _while(array $node) {
        $attrib=$node['@attrib'];
        unset($node['@attrib']);

        return
            '<?php while ('.$this->token($attrib[0]).') { ?>'.
            $this->build($node).
            '<?php } ?>';
    }

    protected function _do(array $node) {
        $attrib=$node['@attrib'];
        unset($node['@attrib']);

        return
            '<?php do { ?>'.
            $this->build($node).
            '<?php } while ('.$this->token($attrib[0]).'); ?>';
    }

    protected function _for(array $node) {
        $attrib=$node['@attrib'];
        unset($node['@attrib']);

        return
            '<?php for ('.
                (isset($attrib[0])?$this->token($attrib[0]):'').';'.
                (isset($attrib[1])?$this->token($attrib[1]):'').';'.
                (isset($attrib[2])?$this->token($attrib[2]):'').') { ?>'.
                $this->build($node).
            '<?php } ?>';
    }

    protected function _if(array $node) {
        $attrib=$node['@attrib'];
        $in = isset($node['@in'])?$node['@in']:array();
        unset($node['@attrib'], $node['@in']);

        $else = '';
        foreach ($in as $key => $value)
            $else .= '<?php } '.$value[0].' '.
                ($value[0]==='else'?'':'(').
                $this->token($value['@attrib'][0]).
                ($value[0]==='else'?'':') ').
                '{ ?>'.$this->build($value[1]);

        return
            '<?php if ('.$this->token($attrib[0]).') { ?>'.
                $this->build($node).
                $else.
            '<?php } ?>';
    }

    protected function _switch(array $node) {
        $attrib=$node['@attrib'];
        $in = isset($node['@in'])?$node['@in']:array();
        unset($node['@attrib'], $node['@in']);

        $content = '';
        foreach ($in as $key => $value)
            $content .= '<?php '.$value[0].' '.$this->token($value['@attrib'][0]).': ?>'.
                $this->build($value[1]).
                (((isset($value['@attrib'][1]) && $value['@attrib'][1]==='continue') && $value[0]=='default')?'':'<?php break; ?>');

        return
            '<?php switch ('.$this->token($attrib[0]).') { ?>'.
                $content.
            '<?php } ?>';
    }

    protected function _comment(array $node) {
        return '';
    }

    /**
    *   Template -include- tag handler
    *   @return string
    *   @param $node array
    **/
    protected function _include(array $node) {
        $attrib=$node['@attrib'];
        $hive='get_defined_vars()';
        $if = false;
        $include = $attrib[0];
        if (isset($attrib[1]) && strpos($attrib[0], 'if ')) {
            $if = substr($attrib[0], 3);
            $include = $attrib[1];
        }

        return
            '<?php '.($if?
                ('if ('.$this->token($if).') '):'').
                ('echo $this->render('.
                    (preg_match('/@.*$/',$include)?
                        $this->token($include):
                        Base::instance()->stringify($include)).','.
                    '$this->mime,'.$hive.'); ?>');
    }

    /**
    *   Assemble markup
    *   @return string
    *   @param $node array|string
    **/
    protected function build($node) {
        if (is_string($node))
            return $this->buildString($node);
        $out='';
        foreach ($node as $key=>$val)
            $out.=is_int($key)?$this->build($val):$this->{'_'.$key}($val);

        return $out;
    }

    /**
    *   Assemble markup
    *   @return string
    *   @param $node string
    **/
    protected function buildString($node) {
        $self=$this;

        return preg_replace_callback(
            '/\{\-(.+?)\-\}|\{\{(.+?)\}\}(\n+)?/s',
            function($expr) use($self) {
                if ($expr[1])
                    return $expr[1];
                $str=trim($self->token($expr[2]));
                if (preg_match('/^([^|]+?)\h*\|(\h*\w+(?:\h*[,;]\h*\w+)*)/',
                    $str,$parts)) {
                    $str=$parts[1];
                    foreach (Base::instance()->split($parts[2]) as $func)
                        $str=$self->filter($func).'('.$str.')';
                }
                return '<?php echo '.$str.'; ?>'.
                    (isset($expr[3])?$expr[3]."\n":'');
            },
            preg_replace_callback(
                '/\{~(.+?)~\}/s',
                function($expr) use($self) {
                    return '<?php '.$self->token($expr[1]).' ?>';
                },
                $node
            )
        );
    }

    /**
    *   Parse string for template directives and tokens
    *   @return string|array
    *   @param $text string
    **/
    protected function parse($text) {
        // Build tree structure
        $in = array();
        for ($ptr=0,$len=strlen($text),$tree=array(),$node=&$tree,
            $stack=array(),$depth=0,$tmp='';$ptr<$len;)
            if (preg_match('/^%(end)?('.$this->tags.')\b(.*)/i', substr($text,$ptr),$match)) {
                $single = substr($match[3], strlen($match[3])-1)==='%';
                !$single || $match[3] = substr($match[3], 0, strlen($match[3])-1);
                if (strlen($tmp))
                    $node[] = $tmp;
                // Element node
                if ($match[1]) {
                    if ($in) {
                        // in element
                        for ($i=count($in)-1; $i > -1 ; --$i)
                            $in[$i][1] = array_pop($node);
                        $node['@in'] = $in;
                        $in = array();
                    }
                    // Find matching start tag
                    $save  = $depth;
                    $found = false;
                    while ($depth>0) {
                        $depth--;
                        foreach ($stack[$depth] as $item)
                            if (is_array($item) && isset($item[$match[2]])) {
                                // Start tag found
                                $found = true;
                                break 2;
                            }
                    }
                    if (!$found)
                        // Unbalanced tag
                        $depth = $save;
                    $node =& $stack[$depth];
                }
                elseif ($this->intag($match[2])) {
                    $in[] = array($match[2], '@attrib'=>$this->parseAttrib($match[3]), '');
                }
                else {
                    // Start tag
                    $stack[$depth]   =& $node;
                    $node            =& $node[][$match[2]];
                    $node['@attrib'] = $this->parseAttrib($match[3]);
                    if ($single)
                        // single
                        $node =& $stack[$depth];
                    else
                        $depth++;
                }
                $tmp = '';
                $ptr += strlen($match[0]);
            }
            else {
                // Text node
                $tmp .= substr($text,$ptr,1);
                $ptr++;
            }
        if (strlen($tmp))
            // Append trailing text
            $node[] = $tmp;
        // Break references
        unset($node, $stack);

        return $tree;
    }

    protected function intag($intag) {
        foreach ($this->intag as $key => $value)
            if (in_array($intag, $value))
                return $key;

        return false;
    }

    protected function parseAttrib($attrib) {
        $attrib = trim($attrib);
        $as = explode(' as ', $attrib);
        $attrib = array();
        foreach ($as as $key => $value) {
            $value = explode(';', $value);
            foreach ($value as $key2 => $value2)
                $value[$key2] = trim($value2);

            if ($key === 1)
                $attrib['as'] = $value;
            else
                $attrib = array_merge($attrib, $value);
        }

        return $attrib;
    }

    /**
    *   Create sandbox for template execution
    *   @return string
    *   @param $hive array
    **/
    protected function sandbox(array $hive=NULL) {
        $this->level++;
        $fw=Base::instance();
        $implicit=false;
        if ($hive === null) {
            $implicit=true;
            $hive=$fw->hive();
        }
        if ($this->level<2 || $implicit) {
            if ($fw->get('ESCAPE'))
                $hive=$this->esc($hive);
            if (isset($hive['ALIASES']))
                $hive['ALIASES']=$fw->build($hive['ALIASES']);
        }
        unset($fw, $implicit);
        extract($hive);
        unset($hive);
        ob_start();
        require($this->view);
        $this->level--;
        return ob_get_clean();
    }

    /**
    *   Call custom tag handler
    *   @return string|FALSE
    *   @param $func callback
    *   @param $args array
    **/
    function __call($func,array $args) {
        if ($func[0]=='_')
            return call_user_func_array($this->custom[$func],$args);
        if (method_exists($this,$func))
            return call_user_func_array(array($this,$func),$args);
        user_error(sprintf(self::E_Method,$func),E_USER_ERROR);
    }

    /**
    *   Class constructor
    *   return object
    **/
    function __construct() {
        $ref=new ReflectionClass(__CLASS__);
        foreach ($this->intag as $key => $value)
            $this->tags .= (strlen($this->tags)?'|':'').implode('|', $value);
        foreach ($ref->getmethods() as $method)
            if (preg_match('/^_(?=[[:alpha:]])/',$method->name))
                $this->tags.=(strlen($this->tags)?'|':'').
                    substr($method->name,1);
    }
}
