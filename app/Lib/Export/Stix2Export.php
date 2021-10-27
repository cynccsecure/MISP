<?php
App::uses('StixExport', 'Export');

class Stix2Export extends StixExport
{
    protected $__attributes_limit = 15000;
    protected $__default_version = '2.0';
    protected $__sane_versions = array('2.0', '2.1');

    protected function __initiate_framing_params()
    {
        return $this->pythonBin() . ' ' . $this->__framing_script . ' stix2 -v ' . $this->__version . ' --uuid ' . escapeshellarg(CakeText::uuid()) . $this->__end_of_cmd;
    }

    protected function __parse_misp_events(array $filenames)
    {
        $scriptFile = $this->__scripts_dir . 'stix2/misp2stix2.py';
        $filenames = implode(' ', $filenames);
        $result = shell_exec($this->pythonBin() . ' ' . $scriptFile . ' -v ' . $this->__version . ' -i ' . $filenames . $this->__end_of_cmd);
        $result = preg_split("/\r\n|\n|\r/", trim($result));
        return end($result);
    }
}
