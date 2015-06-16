<?php

/*
 * This file is part of the PHP_CodeCoverage package.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generates an HTML report from an PHP_CodeCoverage object.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright  Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      Class available since Release 1.0.0
 */
class PHP_CodeCoverage_Report_HTML {

    /**
     * @var string
     */
    private $templatePath;

    /**
     * @var string
     */
    private $generator;

    /**
     * @var integer
     */
    private $lowUpperBound;

    /**
     * @var integer
     */
    private $highLowerBound;

    /**
     * Constructor.
     *
     * @param integer $lowUpperBound
     * @param integer $highLowerBound
     * @param string  $generator
     */
    public function __construct($lowUpperBound = 50, $highLowerBound = 90, $generator = '') {
        $this->generator = $generator;
        $this->highLowerBound = $highLowerBound;
        $this->lowUpperBound = $lowUpperBound;

        $this->templatePath = sprintf(
                '%s%sHTML%sRenderer%sTemplate%s', dirname(__FILE__), DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR
        );
    }

    /**
     * @param PHP_CodeCoverage $coverage
     * @param string           $target
     */
    public function process(PHP_CodeCoverage $coverage, $target) {
        $target = $this->getDirectory($target);
        $report = $coverage->getReport();
        $tmp = $coverage;
        unset($coverage);

        if (!isset($_SERVER['REQUEST_TIME'])) {
            $_SERVER['REQUEST_TIME'] = time();
        }

        $date = date('D M j G:i:s T Y', $_SERVER['REQUEST_TIME']);

        $dashboard = new PHP_CodeCoverage_Report_HTML_Renderer_Dashboard(
                $this->templatePath, $this->generator, $date, $this->lowUpperBound, $this->highLowerBound
        );

        $directory = new PHP_CodeCoverage_Report_HTML_Renderer_Directory(
                $this->templatePath, $this->generator, $date, $this->lowUpperBound, $this->highLowerBound
        );

        $file = new PHP_CodeCoverage_Report_HTML_Renderer_File(
                $this->templatePath, $this->generator, $date, $this->lowUpperBound, $this->highLowerBound
        );

        $directory->render($report, $target . 'index.html');
        $dashboard->render($report, $target . 'dashboard.html');

        foreach ($report as $node) {
            $id = $node->getId();

            if ($node instanceof PHP_CodeCoverage_Report_Node_Directory) {
                if (!file_exists($target . $id)) {
                    mkdir($target . $id, 0777, true);
                }

                $directory->render($node, $target . $id . '/index.html');
                $dashboard->render($node, $target . $id . '/dashboard.html');
            } else {
                $dir = dirname($target . $id);

                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }

                $file->render($node, $target . $id . '.html');
            }
        }

        $this->copyFiles($target);

         $str_data = file_get_contents(dirname(dirname(dirname(__FILE__))) . "/data.json");
        //var_dump($str_data);
        $arr_data = json_decode($str_data , true);
        //var_dump($arr_data);
        $this->process_branch($tmp, $arr_data, $target, dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))));
        
        unlink(dirname(dirname(dirname(__FILE__))) . "/data.json");
        $str_xml_name = $target . "../cov-clover.xml";
        //$str_xml_name = $target . "../index.xml";
        $this->process_xml($tmp, $arr_data, $str_xml_name);

        unset($tmp);
    }

    /**
     * @param string $target
     */
    private function copyFiles($target) {
        $dir = $this->getDirectory($target . 'css');
        copy($this->templatePath . 'css/bootstrap.min.css', $dir . 'bootstrap.min.css');
        copy($this->templatePath . 'css/nv.d3.min.css', $dir . 'nv.d3.min.css');
        copy($this->templatePath . 'css/style.css', $dir . 'style.css');

        $dir = $this->getDirectory($target . 'fonts');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.eot', $dir . 'glyphicons-halflings-regular.eot');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.svg', $dir . 'glyphicons-halflings-regular.svg');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.ttf', $dir . 'glyphicons-halflings-regular.ttf');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.woff', $dir . 'glyphicons-halflings-regular.woff');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.woff2', $dir . 'glyphicons-halflings-regular.woff2');

        $dir = $this->getDirectory($target . 'js');
        copy($this->templatePath . 'js/bootstrap.min.js', $dir . 'bootstrap.min.js');
        copy($this->templatePath . 'js/d3.min.js', $dir . 'd3.min.js');
        copy($this->templatePath . 'js/holder.min.js', $dir . 'holder.min.js');
        copy($this->templatePath . 'js/html5shiv.min.js', $dir . 'html5shiv.min.js');
        copy($this->templatePath . 'js/jquery.min.js', $dir . 'jquery.min.js');
        copy($this->templatePath . 'js/nv.d3.min.js', $dir . 'nv.d3.min.js');
        copy($this->templatePath . 'js/respond.min.js', $dir . 'respond.min.js');
    }

    /**
     * @param  string                     $directory
     * @return string
     * @throws PHP_CodeCoverage_Exception
     * @since  Method available since Release 1.2.0
     */
    private function getDirectory($directory) {
        if (substr($directory, -1, 1) != DIRECTORY_SEPARATOR) {
            $directory .= DIRECTORY_SEPARATOR;
        }

        if (is_dir($directory)) {
            return $directory;
        }

        if (@mkdir($directory, 0777, true)) {
            return $directory;
        }

        throw new PHP_CodeCoverage_Exception(
        sprintf(
                'Directory "%s" does not exist.', $directory
        )
        );
    }

    private function process_xml(PHP_CodeCoverage $coverage, $data = null, $name = null){

        $cl_xml = new PHP_CodeCoverage_Report_Clover();

        $cl_xml->process_branch($coverage, $data, $name);
    }

    /* rewrite branch attribute . 
     * 
     * @param   PHP_CodeCoverage    $coverage
     * @param   string              $xml
     * @param   string              $data
     * 
     * @return  string
     * 
     */

    public function process_branch(PHP_CodeCoverage $coverage, $data = null, $target = null, $sorce = null) {
        $target = $this->getDirectory($target);
        require_once dirname(__FILE__) . '/../../../../php-simple-html-dom/simple_html_dom.php';
        //var_dump($data);
        //var_dump($data);
        if(!is_array($data)){
            return false;
        }
        foreach ($data as $file_name => $data2) {
            //echo "$file_name\n";
            if(strstr($file_name, "vendor")){
                continue;
            }
            //var_dump($file_name, $target, $sorce);
            //var_dump(strpos($file_name, $sorce));
            $file_name_target = $file_name;
            $file_name = $target . substr($file_name, strpos($file_name, $sorce) + strlen($sorce));
            $file_name = $file_name . ".html";
            if(!file_exists($file_name)){
                //var_dump($file_name);
                //var_dump($file_name, $file_name_target, $target);
                continue;
            }
            var_dump($file_name);
            
            $html = file_get_html($file_name);
            $ret = $html->find('body div[@class=container]');
            //var_dump(count($ret));
            $html_row_td = $html->find('body div[@class=container] table thead td');
            //var_dump($html_row_td[1]->outertext);
            $html_row_td[1]->outertext = '<td colspan="18"><div align="center"><strong>Code Coverage</strong></div></td>';

            $html_row_tr = $html->find('body div[@class=container] table thead tr');
            //var_dump($html_row_tr[1]->outertext);

            $html_row_tr[1]->outertext = '<tr>'
                    . '<td>&nbsp;</td>'
                    . '<td colspan="3">'
                    . '<div align="center"><strong>Lines</strong></div>'
                    . '</td>'
                    . '<td colspan="4">'
                    . '<div align="center"><strong>Functions and Methods</strong></div>'
                    . '</td>'
                    . '<td colspan="3">'
                    . '<div align="center"><strong>Classes and Traits</strong></div>'
                    . '</td>'
                    . '<td colspan="3">'
                    . '<div align="center"><strong>Branches</strong></div>'
                    . '</td>'
//                . '<td colspan="3">'
//                . '<div align="center"><strong>Paths</strong></div>'
//                . '</td>'
                    . '</tr>';


            $html_row_tr = $html->find('body div[@class="container"] table[class="table table-bordered"] tbody tr');
            for ($i = 2; $i < count($html_row_tr); $i++) {
                //echo $html_row_tr[$i]->innertext . "\n\n";

                $tmp_html_row = str_get_html($html_row_tr[$i]->innertext);
                $ret_td = $tmp_html_row->find('td');
                $name = $ret_td[0]->plaintext . "\n\n";

                $str = $this->process_branch_html($data, trim($name), $file_name_target);
                $html_row_tr[$i]->innertext = $html_row_tr[$i]->innertext . $str;
            }


            $html->save($file_name);
        }

        $arr_dir = $this->process_branch_index($target);
        //print_r($arr_dir);
        return true;
    }

    /*
     * count all file branch count
     */

    private function process_branch_count($data, $functionname = 'Total', $target = null) {

        //var_dump($functionname);
        $arr_return['hit_count'] = 0;
        $arr_return['branch_count'] = 0;
        //var_dump($data);
        $arr_count = $this->count_branch($data);
        //var_dump($arr_count);
        if ('Total' == $functionname) {

            $str_dir = dirname($target);
            //var_dump($str_dir);
            if (null !== $target) {
                
            }
            //echo "**********\n";
            foreach ($arr_count as $files => $file_count) {
                //$str_file_name = str_replace(dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))), $target, $files) . ".html";
                //var_dump($files);

                if (strstr($files, $str_dir)) {
                    $int_layer_file = count(explode("/", $files));
                    $int_layer_dir = count(explode("/", $str_dir));
                    if($int_layer_file == $int_layer_dir + 1) {
       
                        foreach ($file_count as $function => $count_values) {
                            //var_dump($count_values);                    
                            $arr_return['hit_count'] += $count_values['hit_count'];
                            $arr_return['branch_count'] += $count_values['branch_count'];
                        }
                    }
                }
            }
        } else {
            foreach ($arr_count as $files => $file_count) {
                //var_dump($file_count);
                //echo "****";
                //var_dump(strpos($files, $functionname),$files, trim(str_replace('&nbsp;','',$functionname)));
                $functionname = trim(str_replace('&nbsp;', '', $functionname));
                //var_dump($functionname);
                foreach ($arr_count[$files] as $function => $count_values) {
                    $pos = strstr($function, $functionname);

                    if (strstr($function, $functionname)) {
                        //var_dump($function);         
                        //var_dump($pos, $functionname);
                        if ($pos == $functionname) {
                            $arr_return['hit_count'] += $count_values['hit_count'];
                            $arr_return['branch_count'] += $count_values['branch_count'];
                        } elseif (strstr($function, $functionname . "->")) {
                            $arr_return['hit_count'] += $count_values['hit_count'];
                            $arr_return['branch_count'] += $count_values['branch_count'];
                        }
                    }
                }
            }
        }
        //var_dump($arr_return);
        return $arr_return;
    }

    private function process_branch_index($target = null) {
        $a_tree = $this->process_branch_index_tree($target);
        //print_r($a_tree);
        $this->process_branch_index_e($target, $a_tree);
        return true;
    }

    private function process_branch_index_e($target, $a_tree) {
        if (is_dir($target)) {
            if (file_exists($target . "/index.html")) {
                //var_dump($target . "/index.html");
                $html = file_get_html($target . "/index.html");
                $html_row_td = $html->find('body div[@class=container] table thead td');
                //var_dump($html_row_td[1]->outertext);
                $html_row_td[1]->outertext = '<td colspan="18"><div align="center"><strong>Code Coverage</strong></div></td>';

                $arr_html_tr = $html->find('body div[@class="container"] table[class="table table-bordered"] tbody tr');
                //var_dump(count($arr_html_tr));

                $arr_html_tr[1]->outertext = '<tr>'
                        . '<td>&nbsp;</td>'
                        . '<td colspan="3">'
                        . '<div align="center"><strong>Lines</strong></div>'
                        . '</td>'
                        . '<td colspan="3">'
                        . '<div align="center"><strong>Functions and Methods</strong></div>'
                        . '</td>'
                        . '<td colspan="3">'
                        . '<div align="center"><strong>Classes and Traits</strong></div>'
                        . '</td>'
                        . '<td colspan="3">'
                        . '<div align="center"><strong>Branches</strong></div>'
                        . '</td>'
//                . '<td colspan="3">'
//                . '<div align="center"><strong>Paths</strong></div>'
//                . '</td>'
                        . '</tr>';

                for ($i = 2; $i < count($arr_html_tr); $i++) {


                    $tmp_html_row = str_get_html($arr_html_tr[$i]->innertext);
                    $ret_td = $tmp_html_row->find('td');
                    $name = trim($ret_td[0]->plaintext);
                    //echo $target . "/" . $name;
                    //var_dump($name);
                    if ("Total" == $name) {
                        $str = $this->process_branch_html_index($a_tree);
                        //echo $str . "\n\n";
                        $arr_html_tr[$i]->innertext = $arr_html_tr[$i]->innertext . $str;
                    } elseif (is_dir($target . "/" . $name)) {
                        $str = $this->process_branch_html_index($a_tree[$name]);
                        //echo $str . "\n\n";
                        $arr_html_tr[$i]->innertext = $arr_html_tr[$i]->innertext . $str;
                        $this->process_branch_index_e($target . "/" . $name, $a_tree[$name]);
                    } else {
                        //echo $name . ".html";
                        $str = $this->process_branch_html_index($a_tree[$name . ".html"]);
                        //echo $str . "\n\n";
                        $arr_html_tr[$i]->innertext = $arr_html_tr[$i]->innertext . $str;
                    }
                }
            }
            $html->save($target . "/index.html");
        }
    }

    private function process_branch_index_tree($target = null) {
        //var_dump($target);
        $root = dir($target);
        $a_return = array(
            'hit' => 0,
            'count' => 0,
        );

        while ($file = $root->read()) {
            if ("." == $file || ".." == $file || "dashboard.html" == $file) {
                
            } else {
                if (is_dir($target . "/" . $file)) {
                    $a_return[$file] = $this->process_branch_index_tree($target . "/" . $file);
                    //var_dump($a_return[$file]);
                    $a_return['hit'] += $a_return[$file]['hit'];
                    $a_return['count'] += $a_return[$file]['count'];
                } else {
                    if (strstr($file, ".php.html")) {
                        require_once dirname(__FILE__) . '/../../../../php-simple-html-dom/simple_html_dom.php';
                        $filename = $target . '/' . $file;
                        $html = file_get_html($filename);
                        $html_row_tr = $html->find('body div[@class="container"] table[class="table table-bordered"] tbody tr');


                        if (isset($html_row_tr[2])) {
                            $html_row_total = $html_row_tr[2];
                            $row = $html_row_total->outertext;
                            $row_html = str_get_html($row);
                            $arr_tr = $row_html->find('td');
                            //var_dump($arr_tr[13]->plaintext);
                            if(!isset($arr_tr[13])){
                                $a_count = array();
                            } else {
                               $a_count = explode("/", $arr_tr[13]->plaintext);
                            }
                            if(!isset($a_count[0])){
                                $a_count[0] = 0;
                            }
                            $a_return[$file]['hit'] = trim(str_replace('&nbsp;', '', $a_count[0]));
                            if(!isset($a_count[1])){
                                $a_count[1] = 0;
                            }
                            $a_return[$file]['count'] = trim(str_replace('&nbsp;', '', $a_count[1]));
                            $a_return['hit'] += $a_return[$file]['hit'];
                            $a_return['count'] += $a_return[$file]['count'];
                        }
                        //$a_return[$file][''] = $file;
                    }
                }
                //var_dump($file);
            }
        }

        //var_dump($a_return);
        return $a_return;
    }

    private function count_branch($data) {
        //print_r($data);
        //echo json_encode($data);
        //var_dump($data);

        $data2 = array();
        foreach ($data as $key => $arr_data) {
            if(strstr($key , "vendor")){
                continue;
            } 
            if(isset($arr_data['functions'])){

                //var_dump($key);
                $data2[$key] = $arr_data['functions'];
            }
        }

        $arr_count = array();
        foreach ($data2 as $file => $arr_file) {
            foreach ($arr_file as $function => $arr_func) {
                foreach ($arr_func['branches'] as $arr_branch) {
                    @ $arr_count[$file][$function]['hit_count'] += $arr_branch['hit'];
                    @ $arr_count[$file][$function]['branch_count'] ++;
                }
            }
        }
        return $arr_count;
    }

    private function count_path($data) {
        $data2 = array();
        foreach ($data as $key => $arr_data) {
            $data2[$key] = $arr_data['functions'];
        }

        $arr_count = array();
        foreach ($data2 as $file => $arr_file) {
            foreach ($arr_file as $function => $arr_func) {
                foreach ($arr_func['paths'] as $arr_branch) {
                    @ $arr_count[$file][$function]['hit_count'] += $arr_branch['hit'];
                    @ $arr_count[$file][$function]['path_count'] ++;
                }
            }
        }
        return $arr_count;
    }

    /*
     * rewrite the html counter with branch count 
     * 
     *  @param  array   $data
     *  @param  string  $filename  or "Total"
     * 
     *  @return string
     */

    private function process_branch_html($data, $filename = 'Total', $target = null) {
        $arr_return = array(
            'hit_count' => 0,
            'branch_count' => 0,
        );
        $arr_return = $this->process_branch_count($data, $filename, $target);
        //var_dump($data);
        

        $hit_count = $arr_return['hit_count'];
        $branch_count = $arr_return['branch_count'];

        if (0 === $branch_count) {
            $cpl = 100;
        } else {
            $cpl = round($hit_count * 100 / $branch_count, 2);
        }
        if ($cpl < 50) {
            $style = "danger";
        } elseif ($cpl < 100 && $cpl >= 50) {
            $style = "warning";
        } else {
            $style = "success";
        }
        $str = ''
                . '<td class=" ' . $style . ' big">'
                . '    <div class="progress">'
                . '        <div class="progress-bar progress-bar-' . $style . '" role="progressbar" aria-valuenow="' . $cpl . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $cpl . '%">'
                . '            <span class="sr-only">' . $cpl . '% covered (' . $style . ')</span>'
                . '        </div>'
                . '    </div>'
                . '</td>'
                . '<td class="' . $style . ' small"><div align="right">' . $cpl . '%</div></td>'
                . '<td class="' . $style . ' small"><div align="right">' . $hit_count . '&nbsp;/&nbsp;' . $branch_count . '</div></td>';

        return $str;
    }
    /*
    * process_branch_html_index
    * 
    * @input $data      array
    * @input $target    string
    *
    * @return $str      string
    */
    private function process_branch_html_index($data, $target = null) {

        $hit_count = $data['hit'];
        $branch_count = $data['count'];
        //var_dump($arr_return);
        if (0 == $branch_count) {
            $cpl = 100;
        } else {
            if(0 == $branch_count){
                $cpl = 100;
            } else {
                $cpl = round($hit_count * 100 / $branch_count, 2);
            }
        }
        if ($cpl < 50) {
            $style = "danger";
        } elseif ($cpl < 100 && $cpl >= 50) {
            $style = "warning";
        } else {
            $style = "success";
        }
        $str = ''
                . '<td class=" ' . $style . ' big">'
                . '    <div class="progress">'
                . '        <div class="progress-bar progress-bar-' . $style . '" role="progressbar" aria-valuenow="' . $cpl . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $cpl . '%">'
                . '            <span class="sr-only">' . $cpl . '% covered (' . $style . ')</span>'
                . '        </div>'
                . '    </div>'
                . '</td>'
                . '<td class="' . $style . ' small"><div align="right">' . $cpl . '%</div></td>'
                . '<td class="' . $style . ' small"><div align="right">' . $hit_count . '&nbsp;/&nbsp;' . $branch_count . '</div></td>';

        return $str;
    }

}
