<?php

class AdminAttCategoriesController extends FwAdminController {
    const route_default_action = '';
    public $base_url = '/Admin/AttCategories';
    public $required_fields = 'iname';
    public $save_fields = 'icode iname idesc status';
    public $model_name = 'AttCategories';
    /*REMOVE OR OVERRIDE*/
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        );

    public function __construct() {
        parent::__construct();

        //optionally init controller
    }


}//end of class

?>