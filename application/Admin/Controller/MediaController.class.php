<?php
/**
 * Created by PhpStorm.
 * User: Jason
 * Date: 2016-07-08
 * Time: 14:47
 */

namespace Admin\Controller;


require SITE_PATH . 'public/js/elfinder/php/autoload.php';
use Common\Controller\AdminbaseController;

class MediaController extends AdminbaseController
{
    protected $media_path;
    function _initialize() {
        parent::_initialize();
        $this->media_path = C('UPLOADPATH').'portal/';
        if(!is_dir($this->media_path)){
            mkdir($this->media_path, 0755, true);
        }
    }

    public function index(){
        $this->display();
    }

    public function explorer(){
        $this->display();
    }

    public function connector(){
        error_reporting(0);
        $quarantine = '../.quarantine/';
        $opts = array(
            // 'debug' => true,
            'roots' => array(
                array(
                    'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
                    'path'          => $this->media_path,           // path to files (REQUIRED)
                    'URL'           => '/'.$this->media_path, // URL to files (REQUIRED)
                    'alias'         => 'Home',
                    'quarantine'    => $quarantine,
                    'uploadDeny'    => array('all'),                // All Mimetypes not allowed to upload
                    'uploadAllow'   => array('image','audio', 'text/plain'),// Mimetype `image` and `text/plain` allowed to upload
                    'uploadOrder'   => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                    'uploadMaxSize' => '5M',
                )
            )
        );
        $connector = new \elFinderConnector(new \elFinder($opts));
        $connector->run();
    }

    function access($attr, $path, $data, $volume) {
        return strpos(basename($path), '.') === 0       // if file/folder begins with '.' (dot)
            ? !($attr == 'read' || $attr == 'write')    // set read+write to false, other (locked+hidden) set to true
            :  null;                                    // else elFinder decide it itself
    }

}