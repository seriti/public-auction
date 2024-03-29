<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Audit;

class Lot extends Table 
{
    protected $labels = MODULE_AUCTION['labels'];

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot','col_label'=>'name'];
        parent::setup($param);

        $this->modifyAccess(['copy'=>true]);
        
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'order_item','col_id'=>'lot_id','message'=>'Auction Order item'));

        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'lot_no','type'=>'INTEGER','title'=>'Catalog No.','edit'=>true,'required'=>false));
        $this->addTableCol(array('id'=>'seller_id','type'=>'INTEGER','title'=>'Seller',
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'seller` WHERE `seller_id`'));
        $this->addTableCol(array('id'=>'category_id','type'=>'INTEGER','title'=>$this->labels['category'],
                                 'join'=>'`title` FROM `'.TABLE_PREFIX.'category` WHERE `id`'));
        $this->addTableCol(array('id'=>'type_id','type'=>'INTEGER','title'=>$this->labels['type'],
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'type` WHERE `type_id`'));
        $this->addTableCol(array('id'=>'type_txt1','type'=>'STRING','title'=>$this->labels['type_txt1'],'required'=>false));
        $this->addTableCol(array('id'=>'type_txt2','type'=>'STRING','title'=>$this->labels['type_txt2'],'required'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Lot Name','hint'=>'Lots are ordered by category and then name','secure'=>false));
        $this->addTableCol(array('id'=>'condition_id','type'=>'INTEGER','title'=>'Condition',
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'condition` WHERE `condition_id`'));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Lot Description','list'=>false));
        $this->addTableCol(array('id'=>'index_terms','type'=>'TEXT','title'=>'Index on terms',
                                 'hint'=>'Use comma to separate multiple index terms for catalogue index','required'=>false));
        $this->addTableCol(array('id'=>'postal_only','type'=>'BOOLEAN','title'=>'Postal only','list'=>false));
        $this->addTableCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price'));
        $this->addTableCol(array('id'=>'price_estimate','type'=>'DECIMAL','title'=>'Estimate Price'));
        $this->addTableCol(array('id'=>'bid_final','type'=>'DECIMAL','title'=>'Final bid','edit'=>false));
        $this->addTableCol(array('id'=>'weight','type'=>'DECIMAL','title'=>'Weight Kg','new'=>0,'list'=>false));
        $this->addTableCol(array('id'=>'volume','type'=>'DECIMAL','title'=>'Volume Litres','new'=>0,'list'=>false));
        $this->addTableCol(array('id'=>'buyer_id','type'=>'INTEGER','title'=>'Buyer ID','edit'=>false,'list'=>true));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        $this->addSql('WHERE','T.`auction_id` = "'.AUCTION_ID.'"');
        $this->addSql('JOIN','JOIN `'.TABLE_PREFIX.'category` AS CT ON(T.`category_id` = CT.`'.$this->tree_cols['node'].'`)');
        $this->addSql('JOIN','JOIN `'.TABLE_PREFIX.'condition` AS CN ON(T.`condition_id` = CN.`condition_id`)');

        //$this->addSortOrder('CT.rank,T.name','Category, then Name');
        //$this->addSortOrder('CT.rank,T.name',$this->labels['category'].', then Name');
        $this->addSortOrder('CT.`rank`,T.`type_txt1`,T.`type_txt2`,CN.`sort`',$this->labels['category'].', then '.$this->labels['type_txt1'].', then '.$this->labels['type_txt2'].', then Condition');
        $this->addSortOrder('T.`lot_id` DESC','Order of creation, most recent first.','DEFAULT');


        $this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
        $this->addAction(array('type'=>'popup','text'=>'Info','url'=>'lot_info','mode'=>'view','width'=>600,'height'=>600)); 

        $sql_cat = 'SELECT `id`,CONCAT(IF(`level` > 1,REPEAT("--",`level` - 1),""),`title`) FROM `'.TABLE_PREFIX.'category`  ORDER BY `rank`';
        $this->addSelect('category_id',$sql_cat);
        $sql_condition = 'SELECT `condition_id`,`name` FROM `'.TABLE_PREFIX.'condition` WHERE `status` <> "HIDE" ORDER BY `sort`';
        $this->addSelect('condition_id',$sql_condition);
        $sql_status = '(SELECT "NEW") UNION (SELECT "OK") UNION (SELECT "SOLD") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);

        $this->addSelect('seller_id','SELECT `seller_id`,`name` FROM `'.TABLE_PREFIX.'seller` WHERE `status` <> "HIDE" ORDER BY `sort`');
        $this->addSelect('type_id','SELECT `type_id`,`name` FROM `'.TABLE_PREFIX.'type` WHERE `status` <> "HIDE" ORDER BY `sort`');


        $this->addSearch(array('lot_id','lot_no','type_id','type_txt1','type_txt2','name','description','category_id','index_terms','postal_only','price_reserve','seller_id','status','buyer_id'),array('rows'=>3));

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'LOT','max_no'=>10,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,'access'=>IMAGE_CONFIG['access'],
                                  'link_url'=>'lot_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $data['index_terms'] = strtolower(trim($data['index_terms']));    
        if($data['index_terms'] !== '') {
            $sql = 'SELECT `term_code` FROM `'.TABLE_PREFIX.'index_term` ';
            $valid_terms = $this->db->readSqlList($sql);
            $lot_terms = explode(',',$data['index_terms']);
            foreach($lot_terms as $term) {
                $term = trim($term);
                if(!in_array($term,$valid_terms)) {
                    $error .= 'Index term['.$term.'] is not a valid term. ';
                }
            }

            if($error !== '') $error .= 'Please create index term codes on Tasks page before using them here.';

        }
          

    }

    protected function afterUpdate($id,$edit_type,$form) {
        $error = '';
        if($edit_type === 'INSERT') {
            $sql = 'UPDATE `'.$this->table.'` SET `auction_id` = "'.AUCTION_ID.'" '.
                   'WHERE `lot_id` = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error);
        } 
    }

    protected function viewTableActions() {
        $html = '';
        $list = array();
            
        $status_set = 'NEW';
        $date_set = date('Y-m-d');
        
        if(!$this->access['read_only']) {
            $list['SELECT'] = 'Action for selected '.$this->row_name_plural;
            $list['STATUS_CHANGE'] = 'Change Lot Status.';
            $list['COPY_LOT'] = 'Copy lot to another auction';
            $list['REVERSE_SOLD'] = 'Reverse Lot Sale.';
        }  
        
        if(count($list) != 0){
            $html .= '<span style="padding:8px;"><input type="checkbox" id="checkbox_all"></span> ';
            $param['class'] = 'form-control input-medium input-inline';
            $param['onchange'] = 'javascript:change_table_action()';
            $action_id = '';
            $status_change = 'NONE';
            $auction_id_copy = '';
            
            $html .= Form::arrayList($list,'table_action',$action_id,true,$param);
            
            //javascript to show collection list depending on selecetion      
            $html .= '<script type="text/javascript">'.
                     '$("#checkbox_all").click(function () {$(".checkbox_action").prop(\'checked\', $(this).prop(\'checked\'));});'.
                     'function change_table_action() {'.
                     'var table_action = document.getElementById(\'table_action\');'.
                     'var action = table_action.options[table_action.selectedIndex].value; '.
                     'var status_select = document.getElementById(\'status_select\');'.
                     'var auction_select = document.getElementById(\'auction_select\');'.
                     'status_select.style.display = \'none\'; '.
                     'auction_select.style.display = \'none\'; '.
                     'if(action==\'STATUS_CHANGE\') status_select.style.display = \'inline\';'.
                     'if(action==\'COPY_LOT\') auction_select.style.display = \'inline\';'.
                     '}'.
                     '</script>';
            
            $param = array();
            $param['class'] = 'form-control input-small input-inline';
            //$param['class']='form-control col-sm-3';
            $sql = '(SELECT "NONE") UNION (SELECT "NEW") UNION (SELECT "OK") UNION (SELECT "SOLD") UNION (SELECT "HIDE")';
            $html .= '<span id="status_select" style="display:none"> status&raquo;'.
                     Form::sqlList($sql,$this->db,'status_change',$status_change,$param).
                     '</span>'; 
            
            $param['class'] = 'form-control input-medium input-inline';       
            $sql = 'SELECT `auction_id`,`name` FROM `'.TABLE_PREFIX.'auction` WHERE `status` <> "HIDE" ';
            $html .= '<span id="auction_select" style="display:none"> To Auction&raquo;'.
                     Form::sqlList($sql,$this->db,'auction_id_copy',$auction_id_copy,$param).
                     '</span>';
                    
            $html .= '&nbsp;<input type="submit" name="action_submit" value="Apply action to selected '.
                     $this->row_name_plural.'" class="btn btn-primary">';
        }  
        
        return $html; 
    }
  
    //update multiple records based on selected action
    protected function updateTable() {
        $error_str = '';
        $error_tmp = '';
        $message_str = '';
        $audit_str = '';
        $audit_count = 0;
        $html = '';
            
        $action = Secure::clean('basic',$_POST['table_action']);
        if($action === 'SELECT') {
            $this->addError('You have not selected any action to perform on '.$this->row_name_plural.'!');
        } else {
            if($action === 'STATUS_CHANGE') {
                $status_change = Secure::clean('alpha',$_POST['status_change']);
                $audit_str = 'Status change['.$status_change.'] ';
                if($status_change === 'NONE') $this->addError('You have not selected a valid status['.$status_change.']!');
            }
            
            if($action === 'COPY_LOT') {
                $auction_id_copy = Secure::clean('integer',$_POST['auction_id_copy']);
                $audit_str = 'Copy Lot to auction['.$auction_id_copy.'] ';
            }
            
            if($action === 'REVERSE_SOLD') {
                $audit_str = 'REVERSE lot sales ';
            }

            if(!$this->errors_found) {     
                foreach($_POST as $key => $value) {
                    if(substr($key,0,8) === 'checked_') {
                        $lot_id = substr($key,8);
                        $audit_str .= 'Lot ID['.$lot_id.'] ';
                                            
                        if($action === 'STATUS_CHANGE') {
                            $sql = 'UPDATE `'.$this->table.'` SET `status` = "'.$this->db->escapeSql($status_change).'" '.
                                   'WHERE `lot_id` = "'.$this->db->escapeSql($lot_id).'" ';
                            $this->db->executeSql($sql,$error_tmp);
                            if($error_tmp === '') {
                                $message_str = 'Status set['.$status_change.'] for lot ID['.$lot_id.'] ';
                                $audit_str .= ' success!';
                                $audit_count++;
                                
                                $this->addMessage($message_str);                
                            } else {
                                $this->addError('Could not update status for lot['.$lot_id.']: '.$error_tmp);                
                            }  
                        }
                        
                        if($action === 'COPY_LOT') {
                            Helpers::copyLot($this->db,$lot_id,$auction_id_copy,$error_tmp);
                            
                            if($error_tmp === '') {
                                $audit_str .= ' success!';
                                $audit_count++;
                                $this->addMessage('lot['.$lot_id.'] copied to auction['.$auction_id_copy.']');      
                            } else {
                                $this->addError('Could NOT copy lot['.$lot_id.']:'.$error_tmp);
                            }   
                        } 

                        if($action === 'REVERSE_SOLD') {
                            Helpers::reverseSale($this->db,$lot_id,$error_tmp);
                            
                            if($error_tmp === '') {
                                $audit_str .= ' success!';
                                $audit_count++;
                                $this->addMessage('lot['.$lot_id.'] REVERSED sale');      
                            } else {
                                $this->addError('Could not REVERSE sale for lot['.$lot_id.']:'.$error_tmp);
                            }   
                        }  
                    }   
                }  
            
            }  
        }  
        
        //audit any updates except for deletes as these are already audited 
        if($audit_count != 0 and $action != 'DELETE') {
            $audit_action = $action.'_'.strtoupper($this->table);
            Audit::action($this->db,$this->user_id,$audit_action,$audit_str);
        }  
            
        $this->mode = 'list';
        $html .= $this->viewTable();
            
        return $html;
    }
}
?>
