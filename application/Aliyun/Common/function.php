<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-17
 * Time: 11:15
 */


function build_verify_no(){
    /* 选择一个随机的方案 */
    mt_srand((double) microtime() * 1000000);
    return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}