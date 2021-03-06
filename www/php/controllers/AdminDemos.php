<?php

class AdminDemosController extends FwAdminController {
    const access_level = 100;
    const route_default_action = '';
    public $base_url='/Admin/Demos';
    public $required_fields = 'iname email';
    public $save_fields = 'parent_id demo_dicts_id iname idesc email fint ffloat fcombo fradio fyesno fdate_pop fdatetime dict_link_multi att_id status';
    public $save_fields_checkboxes = 'is_checkbox';
    public $model_name = 'Demos';
    public $model_related;

    /*REMOVE OR OVERRIDE*/
    public $related_field_name = 'demo_dicts_id';
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'iname',
                        'add_time'      => 'add_time',
                        'demo_dicts_id' => 'demo_dicts_id',
                        'email'         => 'email',
                        'status'        => 'status',
                        );
    public $form_new_defaults=array(
                        'fint'=>0,
                        'ffloat'=>0,
                        );

    public function __construct() {
        parent::__construct();

        $this->model_related = fw::model('DemoDicts');
    }

    //override due to custom search filter on status
    public function setListSearch() {
        parent::setListSearch();

        if ($this->list_filter['status']>''){
            $this->list_where .= ' and status='.dbqi($this->list_filter['status']);
        }
    }

    // override get list rows as list need to be modified
    public function getListRows() {
        parent::getListRows();

        #add/modify rows from db
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['demo_dicts'] = $this->model_related->one( $row['demo_dicts_id'] );
        }
    }

    //View item screen
    public function ShowAction($form_id) {
        $id = $form_id+0;
        $item = $this->model->one($id);
        if (!$item) throw new ApplicationException("Not Found", 404);

        $item["ftime_str"] = DateUtils::int2timestr( $item["ftime"] );
        $dict_link_multi = FormUtils::ids2multi($item['dict_link_multi']);

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,

            'parent'            => $this->model->one( $item['parent_id'] ),
            'demo_dicts'        => $this->model_related->one( $item['demo_dicts_id'] ),
            'dict_link_auto'    => $this->model_related->one( $item['dict_link_auto_id'] ),
            'multi_datarow'     => $this->model_related->getMultiList( $dict_link_multi ),
            'att'               => fw::model('Att')->one($item['att_id']+0),
            'att_links'         => fw::model('Att')->getAttLinks($this->model->table_name, $id),
        );

        return $ps;
    }

    //Add/Edit item form screen
    public function ShowFormAction($form_id) {
        $id = $form_id+0;
        $dict_link_multi=array();

        if ($this->fw->isGetRequest()){
            if ($id>0){
                $item = $this->model->one($id);
                $item["ftime_str"] = DateUtils::int2timestr( $item["ftime"] );
                $dict_link_multi = FormUtils::ids2multi($item['dict_link_multi']);
            }else{
                #defaults
                $item=$this->form_new_defaults;
                if ($this->related_id) $item['demo_dicts_id']=$this->related_id;
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = array_merge($itemdb, reqh('item'));
            $dict_link_multi = req('dict_link_multi');
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
            'add_users_id_name'  => fw::model('Users')->getFullName($item['add_users_id']),
            'upd_users_id_name'  => fw::model('Users')->getFullName($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,

            #read dropdowns lists from db
            'select_options_parent_id'      => $this->model->getSelectOptionsParent( $item['parent_id'] ),
            'select_options_demo_dicts_id'  => $this->model_related->getSelectOptions( $item['demo_dicts_id'] ),
            'dict_link_auto_id_iname'       => $item['dict_link_auto_id'] ? $this->model_related->iname( $item['dict_link_auto_id'] ) : $item['dict_link_auto_id_iname'],
            'multi_datarow'                 => $this->model_related->getMultiList( $dict_link_multi ),
            'att'                           => fw::model('Att')->one($item['att_id']+0),
            'att_links'                     => fw::model('Att')->getAttLinks($this->model->table_name, $id),
        );
        #combo date
        #TODO FormUtils::comboForDate( $item['fdate_combo'], $ps, 'fdate_combo');

        return $ps;
    }

    // override to modify some fields before save
    public function getSaveFields($id, $item){
        $itemdb=parent::getSaveFields($id, $item);

        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb['dict_link_auto_id'] = $this->model_related->addOrUpdateByIname( $item['dict_link_auto_id_iname'] );
        $itemdb['dict_link_multi'] = FormUtils::multi2ids( req('dict_link_multi') );
        $itemdb['fdate_pop']= DateUtils::Str2SQL($itemdb['fdate_pop']);
        #TODO $itemdb['fdate_combo'] = FormUtils::date4combo($item, 'fdate_combo');
        $itemdb['ftime'] = DateUtils::timestr2int( $item['ftime_str'] ); #ftime - convert from HH:MM to int (0-24h in seconds)

        return $itemdb;
    }

    // override because we need to update att links
    public function modelAddOrUpdate($id, $fields){
        $id=parent::modelAddOrUpdate($id, $fields);

        fw::model('Att')->updateAttLinks($this->model->table_name, $id, reqh('att'));

        return $id;
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, $this->required_fields);

        //check $result here used only to disable further validation if required fields validation failed
        if ($result){
            if ($this->model->isExists( $item['email'], $id ) ){
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail( $item['email'] ) ){
                $this->setError('email', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

    public function AutocompleteAction(){
        $query = reqs('q');

        $ps=array(
            '_json' => $this->model_related->getAutocompleteList($query),
        );
        return $ps;
    }

}//end of class

?>