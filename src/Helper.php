<?php

namespace moe;

class Helper
{
    public static function pre($data, $exit = 0)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        !$exit || exit(str_repeat('<br>', 5).' '.$exit);
        echo '<hr>';
    }

    public static function sendJson($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(0);
    }

    /**
     * @source https://gist.github.com/chlab/4283560
     */
    public static function parseBody()
    {
        $result = array(
            'data'=>array(),
            'files'=>array(),
            );

         // read incoming data
        $input = Instance::get('BODY');

        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);

        // content type is probably regular form-encoded
        if (!count($matches)) {
            // we expect regular puts to containt a query string containing data
            parse_str($input, $result['data']);
        } else {
            $boundary = $matches[1];
            // split content by boundary and get rid of last -- element
            $a_blocks = preg_split("/-+$boundary/", $input);
            array_pop($a_blocks);

            // loop data blocks
            foreach ($a_blocks as $id => $block) {
                if (empty($block))
                    continue;
                // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
                // parse uploaded files
                if (strpos($block, 'application/octet-stream') !== FALSE) {
                    // match "name", then everything after "stream" (optional) except for prepending newlines
                    // preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                    // $a_data['files'][$matches[1]] = isset($matches[2])?$matches[2]:null;
                }
                // parse all other fields
                else {
                    // match "name" and optional value in between newline sequences
                    if (strpos($block, 'filename="')===false) {
                        preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                        $result['data'][$matches[1]] = isset($matches[2])?$matches[2]:null;
                    } else {
                        preg_match('/; name=\"([^\"]*)\"; filename=\"([^\"]*)\"\s*Content\-Type: ([\w\/]+)[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                        $result['files'][$matches[1]] = array(
                            'name' => $matches[2],
                            'type' => $matches[3],
                            'tmp_name' => tempnam( ini_get( 'upload_tmp_dir' ), 'php' ),
                            'error' => UPLOAD_ERR_OK,
                            'size' => 0);
                        $result['files'][$matches[1]]['size'] = file_put_contents($result['files'][$matches[1]]['tmp_name'], $matches[4]);
                        $result['files'][$matches[1]]['size']!==false || $result['files'][$matches[1]]['error'] = UPLOAD_ERR_CANT_WRITE;
                    }
                }
            }
        }

        return $result;
    }
}
