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
 * Generates a Clover XML logfile from an PHP_CodeCoverage object.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright  Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      Class available since Release 1.0.0
 */
class PHP_CodeCoverage_Report_Clover {

    /**
     * @param  PHP_CodeCoverage $coverage
     * @param  string           $target
     * @param  string           $name
     * @return string
     */
    public function process(PHP_CodeCoverage $coverage, $target = null, $name = null) {

        //print_r($coverage);
        $xmlDocument = new DOMDocument('1.0', 'UTF-8');
        $xmlDocument->formatOutput = true;

        $xmlCoverage = $xmlDocument->createElement('coverage');
        $xmlCoverage->setAttribute('generated', (int) $_SERVER['REQUEST_TIME']);
        $xmlDocument->appendChild($xmlCoverage);

        $xmlProject = $xmlDocument->createElement('project');
        $xmlProject->setAttribute('timestamp', (int) $_SERVER['REQUEST_TIME']);

        if (is_string($name)) {
            $xmlProject->setAttribute('name', $name);
        }

        $xmlCoverage->appendChild($xmlProject);

        $packages = array();
        //$data = $coverage->getData();
        //var_dump($data);
        $report = $coverage->getReport();
        //print_r($report);
        unset($coverage);
        foreach ($report as $item) {
//            var_dump($item->getPath());

            $namespace = 'global';

            if (!$item instanceof PHP_CodeCoverage_Report_Node_File) {
                continue;
            }

            $xmlFile = $xmlDocument->createElement('file');
            $xmlFile->setAttribute('name', $item->getPath());

            $classes = $item->getClassesAndTraits();
            $coverage = $item->getCoverageData();
            $lines = array();

            foreach ($classes as $className => $class) {
                $classStatements = 0;
                $coveredClassStatements = 0;
                $coveredMethods = 0;
                $classMethods = 0;

                foreach ($class['methods'] as $methodName => $method) {
                    if ($method['executableLines'] == 0) {
                        continue;
                    }

                    $classMethods++;
                    $classStatements += $method['executableLines'];
                    $coveredClassStatements += $method['executedLines'];
                    if ($method['coverage'] == 100) {
                        $coveredMethods++;
                    }

                    $methodCount = 0;
                    for ($i = $method['startLine']; $i <= $method['endLine']; $i++) {
                        if (isset($coverage[$i]) && ($coverage[$i] !== null)) {
                            $methodCount = max($methodCount, count($coverage[$i]));
                        }
                    }

                    $lines[$method['startLine']] = array(
                        'count' => $methodCount,
                        'crap' => $method['crap'],
                        'type' => 'method',
                        'name' => $methodName
                    );
                }

                if (!empty($class['package']['namespace'])) {
                    $namespace = $class['package']['namespace'];
                }

                $xmlClass = $xmlDocument->createElement('class');
                $xmlClass->setAttribute('name', $className);
                $xmlClass->setAttribute('namespace', $namespace);

                if (!empty($class['package']['fullPackage'])) {
                    $xmlClass->setAttribute(
                            'fullPackage', $class['package']['fullPackage']
                    );
                }

                if (!empty($class['package']['category'])) {
                    $xmlClass->setAttribute(
                            'category', $class['package']['category']
                    );
                }

                if (!empty($class['package']['package'])) {
                    $xmlClass->setAttribute(
                            'package', $class['package']['package']
                    );
                }

                if (!empty($class['package']['subpackage'])) {
                    $xmlClass->setAttribute(
                            'subpackage', $class['package']['subpackage']
                    );
                }

                $xmlFile->appendChild($xmlClass);

                $xmlMetrics = $xmlDocument->createElement('metrics');
                $xmlMetrics->setAttribute('methods', $classMethods);
                $xmlMetrics->setAttribute('coveredmethods', $coveredMethods);
                $xmlMetrics->setAttribute('conditionals', 0);
                $xmlMetrics->setAttribute('coveredconditionals', 0);
                $xmlMetrics->setAttribute('statements', $classStatements);
                $xmlMetrics->setAttribute(
                        'coveredstatements', $coveredClassStatements
                );
                $xmlMetrics->setAttribute(
                        'elements', $classMethods +
                        $classStatements
                        /* + conditionals */
                );
                $xmlMetrics->setAttribute(
                        'coveredelements', $coveredMethods +
                        $coveredClassStatements
                        /* + coveredconditionals */
                );
                $xmlClass->appendChild($xmlMetrics);
            }

            foreach ($coverage as $line => $data) {
                if ($data === null || isset($lines[$line])) {
                    continue;
                }

                $lines[$line] = array(
                    'count' => count($data), 'type' => 'stmt'
                );
            }

            ksort($lines);

            foreach ($lines as $line => $data) {
                $xmlLine = $xmlDocument->createElement('line');
                $xmlLine->setAttribute('num', $line);
                $xmlLine->setAttribute('type', $data['type']);

                if (isset($data['name'])) {
                    $xmlLine->setAttribute('name', $data['name']);
                }

                if (isset($data['crap'])) {
                    $xmlLine->setAttribute('crap', $data['crap']);
                }

                $xmlLine->setAttribute('count', $data['count']);
                $xmlFile->appendChild($xmlLine);
            }

            $linesOfCode = $item->getLinesOfCode();

            $xmlMetrics = $xmlDocument->createElement('metrics');
            $xmlMetrics->setAttribute('loc', $linesOfCode['loc']);
            $xmlMetrics->setAttribute('ncloc', $linesOfCode['ncloc']);
            $xmlMetrics->setAttribute('classes', $item->getNumClassesAndTraits());
            $xmlMetrics->setAttribute('methods', $item->getNumMethods());
            $xmlMetrics->setAttribute(
                    'coveredmethods', $item->getNumTestedMethods()
            );
            $xmlMetrics->setAttribute('conditionals', 0);
            $xmlMetrics->setAttribute('coveredconditionals', 0);
            $xmlMetrics->setAttribute(
                    'statements', $item->getNumExecutableLines()
            );
            $xmlMetrics->setAttribute(
                    'coveredstatements', $item->getNumExecutedLines()
            );
            $xmlMetrics->setAttribute(
                    'elements', $item->getNumMethods() + $item->getNumExecutableLines()
                    /* + conditionals */
            );
            $xmlMetrics->setAttribute(
                    'coveredelements', $item->getNumTestedMethods() + $item->getNumExecutedLines()
                    /* + coveredconditionals */
            );
            $xmlFile->appendChild($xmlMetrics);

            if ($namespace == 'global') {
                $xmlProject->appendChild($xmlFile);
            } else {
                if (!isset($packages[$namespace])) {
                    $packages[$namespace] = $xmlDocument->createElement(
                            'package'
                    );

                    $packages[$namespace]->setAttribute('name', $namespace);
                    $xmlProject->appendChild($packages[$namespace]);
                }

                $packages[$namespace]->appendChild($xmlFile);
            }
        }

        $linesOfCode = $report->getLinesOfCode();

        $xmlMetrics = $xmlDocument->createElement('metrics');
        $xmlMetrics->setAttribute('files', count($report));
        $xmlMetrics->setAttribute('loc', $linesOfCode['loc']);
        $xmlMetrics->setAttribute('ncloc', $linesOfCode['ncloc']);
        $xmlMetrics->setAttribute(
                'classes', $report->getNumClassesAndTraits()
        );
        $xmlMetrics->setAttribute('methods', $report->getNumMethods());
        $xmlMetrics->setAttribute(
                'coveredmethods', $report->getNumTestedMethods()
        );
        $xmlMetrics->setAttribute('conditionals', 0);
        $xmlMetrics->setAttribute('coveredconditionals', 0);
        $xmlMetrics->setAttribute(
                'statements', $report->getNumExecutableLines()
        );
        $xmlMetrics->setAttribute(
                'coveredstatements', $report->getNumExecutedLines()
        );
        $xmlMetrics->setAttribute(
                'elements', $report->getNumMethods() + $report->getNumExecutableLines()
                /* + conditionals */
        );
        $xmlMetrics->setAttribute(
                'coveredelements', $report->getNumTestedMethods() + $report->getNumExecutedLines()
                /* + coveredconditionals */
        );

        $xmlProject->appendChild($xmlMetrics);

        if ($target !== null) {
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }

            return $xmlDocument->save($target);
        } else {
            return $xmlDocument->saveXML();
        }

        $str_data = file_get_contents(dirname(dirname(dirname(__FILE__))) . "/data.json");
        $arr_data = json_decode($str_data , true);
        //var_dump($name, $str_data);
        //$this->process_branch($coverage, $arr_data, $name);
    }

    /*
     * All branch and path count into xml
     * @param  PHP_CodeCoverage $coverage
     * @param  string           $target
     * @param  string           $name
     * @return bool
     * 
     */

    public function process_branch(PHP_CodeCoverage $coverage, $data = null, $name = null) {

        $data2 = array();
        foreach ($data as $key => $arr_data) {
            
            if (false === strstr($key, "vendor")) {
                //var_dump($key, $arr_data);
                if(isset($arr_data['functions'])){
                    //var_dump($key);
                    $data2[$key] = $arr_data['functions'];
                }
                
            } else {
                unset($data[$key]);
            }
        }
        //var_dump($name);
        $xml = simplexml_load_file($name);
        $xml_bak = simplexml_load_file($name);
        $xml = $this->rewrite_xml_file_attribute($xml, $data2);
        $xml = $this->rewrite_xml_line_attribute($xml, $data2);


        $xml_bak->saveXML($name . "_1.xml");
        $xml->saveXML($name);
        
        return true;
    }

    /*
     * rewrite file attibute
     * 
     * @param  string           $xml
     * @param  string           $data
     * @return string
     * 
     */

    private function rewrite_xml_file_attribute($xml, $data) {
        $data2 = $data;
        $file_xml = $xml->xpath("//file");

        $arr_project_branch_counter = array(
            'branch_count' => 0,
            'hit_count' => 0,
        );
        $ret_counter = $this->count_branch($data2);
        foreach ($file_xml as $element_file) {
            $attributes_name = (string) $element_file->attributes()->name;
            //echo $attributes_name . "\n";
            //var_dump($ret_counter[$attributes_name]);
            $arr_branch_counter = array(
                'branch_count' => 0,
                'hit_count' => 0,
            );
            foreach ($ret_counter[$attributes_name] as $branch_counter) {
                $arr_branch_counter['branch_count'] += $branch_counter['branch_count'];
                $arr_branch_counter['hit_count'] += $branch_counter['hit_count'];
                $arr_project_branch_counter['branch_count'] += $branch_counter['branch_count'];
                $arr_project_branch_counter['hit_count'] += $branch_counter['hit_count'];
            }
            $element_file->metrics->addAttribute("coverdbranch", $arr_branch_counter['hit_count']);
            $element_file->metrics->addAttribute("branches", $arr_branch_counter['branch_count']);
            //var_dump($arr_branch_counter);
        }
        $project_xml = $xml->xpath("//project");
        //var_dump($project_xml[0]->metrics);
        foreach ($project_xml as $element_project) {
            $element_project->metrics->addAttribute("coveredbranch", $arr_project_branch_counter['hit_count']);
            $element_project->metrics->addAttribute("branches", $arr_project_branch_counter['branch_count']);
        }
        $ret = $this->count_path($data2);
        return $xml;
    }

    /*
     * 
     */

    private function rewrite_xml_function_attribute($xml, $data) {
        $file_xml = $xml->xpath("//file");
        foreach ($file_xml as $element_file) {
            
        }
    }

    private function rewrite_xml_class_attribute() {
        
    }

    /* rewrite line attribute . 
     * add branch and if branch = true add coverredbranch and branhcount
     * 
     * @param  string           $xml
     * @param  string           $data
     * @return string
     */

    private function rewrite_xml_line_attribute($xml, $data) {
        $file_xml = $xml->xpath("//file");
        foreach ($file_xml as $element_file) {
            $arr_line = array();
            $i = 0;
            $attributes_name = (string) $element_file->attributes()->name;
            //var_dump($attributes_name);
            foreach ($data[$attributes_name] as $arr_function) {
                //var_dump($arr_function);
                foreach ($arr_function['branches'] as $a_line) {
                    //var_dump($a_line);
                    if (!isset($arr_line[$a_line['line_start']])) {
                        $arr_line[$a_line['line_start']] = array(
                            'count' => 0,
                            'hit' => 0,
                        );
                    }
                    $arr_line[$a_line['line_start']]['count'] ++;
                    $arr_line[$a_line['line_start']]['hit'] += $a_line['hit'];
                    $i++;
                }
            }
            //var_dump($arr_line);
            $line_xml = $xml->xpath("//line");
            foreach ($line_xml as $element_line) {
                $line_num = (string) $element_line->attributes()->num;
                if (isset($arr_line[$line_num])) {                    
                    if ("" != (string) $element_line->attributes()->branch) {
                        //var_dump((string) $element_line->attributes()->branch);
                        $element_line->attributes()->branch = 'true';          
                        if("" == (string) $element_line->attributes()->coveredbranch){
                            //var_dump($line_num, (string) $element_line->attributes()->coveredbranch);                            
                            $element_line->addAttribute("coveredbranch", $arr_line[$line_num]['hit']);
                            $element_line->addAttribute("branchcount", $arr_line[$line_num]['count']);
                        }
                    } else {
                        $element_line->addAttribute("branch", 'true');
                        //var_dump($arr_line[$line_num]);
                        $element_line->addAttribute("coveredbranch", $arr_line[$line_num]['hit']);
                        $element_line->addAttribute("branchcount", $arr_line[$line_num]['count']);
                    }
                } else {
                    if ((string) $element_line->attributes()->branch) {
                        
                    } else {
                        $element_line->addAttribute("branch", 'false');
                    }
                }
            }
        }
        return $xml;
    }

    /*
     * 
     */

    public function count_branch($data) {
        $arr_count = array();
        foreach ($data as $file => $arr_file) {
            foreach ($arr_file as $function => $arr_func) {
                foreach ($arr_func['branches'] as $arr_branch) {
                    @ $arr_count[$file][$function]['hit_count'] += $arr_branch['hit'];
                    @ $arr_count[$file][$function]['branch_count'] ++;
                }
            }
        }
        return $arr_count;
    }

    public function count_path($data) {
        $arr_count = array();
        foreach ($data as $file => $arr_file) {
            foreach ($arr_file as $function => $arr_func) {
                foreach ($arr_func['paths'] as $arr_branch) {
                    @ $arr_count[$file][$function]['hit_count'] += $arr_branch['hit'];
                    @ $arr_count[$file][$function]['path_count'] ++;
                }
            }
        }
        return $arr_count;
    }

}
