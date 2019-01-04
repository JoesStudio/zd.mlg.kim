<?php
use Tf\Service\ApiService;
function sp_admin_get_tpl_file_list(){
    
}

function sp_sql_tf_paged_bykeyword($keyword,$tag,$pagesize=20,$pagetpl='{first}{prev}{liststart}{list}{listend}{next}{last}'){
    return ApiService::tfPagedByKeyword($keyword,$tag,$pagesize,$pagetpl);
}

function sp_sql_tf_paged_byurl($url,$tag,$pagesize=20,$pagetpl='{first}{prev}{liststart}{list}{listend}{next}{last}'){
    return ApiService::tfPagedByUrl($url,$tag,$pagesize,$pagetpl);
}