<?php 
namespace App\Auction;

use Exception;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Audit;

use Seriti\Tools\TABLE_USER;

use App\Auction\Helpers;

class Invoice extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Invoice','col_label'=>'invoice_no'];
        parent::setup($param); 

        $access['add'] = false;
        $this->modifyAccess($access);       

        $this->addTableCol(array('id'=>'invoice_id','type'=>'INTEGER','title'=>'Invoice ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'invoice_no','type'=>'STRING','title'=>'Invoice no','edit'=>false));
        $this->addTableCol(array('id'=>'user_id','type'=>'INTEGER','title'=>'User',
                                 'join'=>'`name` FROM `'.TABLE_USER.'` WHERE `user_id`','edit'=>false));
        $this->addTableCol(array('id'=>'date','type'=>'DATE','title'=>'Date','edit'=>false));
        $this->addTableCol(array('id'=>'sub_total','type'=>'DECIMAL','title'=>'Amount','edit'=>false));
        $this->addTableCol(array('id'=>'tax','type'=>'DECIMAL','title'=>'VAT','edit'=>false));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total','edit'=>false));
        $this->addTableCol(array('id'=>'comment','type'=>'TEXT','title'=>'Comment','required'=>false));
        //$this->addTableCol(array('id'=>'doc_name','type'=>'FILE','title'=>'Invoice document','required'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'OK'));
        $this->addTableCol(array('id'=>'order_id','type'=>'INTEGER','title'=>'Order ID','edit'=>false));

        $this->addSortOrder('T.`invoice_id` DESC','Create date latest','DEFAULT');

        $this->addSql('WHERE','T.`auction_id` = "'.AUCTION_ID.'" ');

        $this->setupFiles(array('location'=>'INV','max_no'=>10,
                                'table'=>TABLE_PREFIX.'file','list'=>true,'list_no'=>10,
                                'link_url'=>'invoice_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

        $this->addAction(array('type'=>'check_box','text'=>'')); 
        //$this->addAction(array('type'=>'edit','text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));
        $this->addAction(array('type'=>'popup','text'=>'Items','url'=>'invoice_item','mode'=>'view','width'=>600,'height'=>800)); 
        $this->addAction(array('type'=>'popup','text'=>'Payments','url'=>'invoice_payment','mode'=>'view','width'=>600,'height'=>800)); 

        $this->addSearch(array('user_id','invoice_no','date','total','comment','status'),array('rows'=>2));

        $this->addSelect('user_id','SELECT `user_id`,`name` FROM `'.TABLE_USER.'` WHERE `zone` = "PUBLIC" ORDER BY `name`');
        $this->addSelect('status','(SELECT "OK") UNION (SELECT "PAID") UNION (SELECT "BAD_DEBT")');
    }

    protected function beforeDelete($id,&$error) 
    {
        $error_tmp = '';
        
        $sql = 'SELECT COUNT(*) FROM `'.TABLE_PREFIX.'payment` '.
               'WHERE `invoice_id` = "'.$this->db->escapeSql($id).'" ';
        $count = $this->db->readSqlValue($sql);
        if($count > 0) {
            $error .= 'Cannot delete invoice as '.$count.' payments are linked to it!';
        }    
    }

    protected function afterDelete($id) 
    {
        $error = '';

        $sql = 'DELETE FROM `'.TABLE_PREFIX.'invoice_item` WHERE `invoice_id` = "'.$this->db->escapeSql($id).'" ';
        $this->db->executeSql($sql,$error);
        if($error !== '') {
            throw new Exception('AUCTION_INVOICE_DELETE: Could not remove invoice['.$id.'] items');
        }

    } 

    protected function viewTableActions() {
        $html = '';
        $list = array();
            
        $status_set = 'NEW';
        $date_set = date('Y-m-d');
        
        if(!$this->access['read_only']) {
            $list['SELECT'] = 'Action for selected '.$this->row_name_plural;
            $list['STATUS_CHANGE'] = 'Change invoice Status.';
            $list['EMAIL_INVOICE'] = 'Email invoice';
        }  
        
        if(count($list) != 0){
            $html .= '<span style="padding:8px;"><input type="checkbox" id="checkbox_all"></span> ';
            $param['class'] = 'form-control input-medium input-inline';
            $param['onchange'] = 'javascript:change_table_action()';
            $action_id = '';
            $status_change = 'NONE';
            $email_address = '';
            
            $html .= Form::arrayList($list,'table_action',$action_id,true,$param);
            
            //javascript to show collection list depending on selecetion      
            $html .= '<script type="text/javascript">'.
                     '$("#checkbox_all").click(function () {$(".checkbox_action").prop(\'checked\', $(this).prop(\'checked\'));});'.
                     'function change_table_action() {'.
                     'var table_action = document.getElementById(\'table_action\');'.
                     'var action = table_action.options[table_action.selectedIndex].value; '.
                     'var status_select = document.getElementById(\'status_select\');'.
                     'var email_invoice = document.getElementById(\'email_invoice\');'.
                     'status_select.style.display = \'none\'; '.
                     'email_invoice.style.display = \'none\'; '.
                     'if(action==\'STATUS_CHANGE\') status_select.style.display = \'inline\';'.
                     'if(action==\'EMAIL_INVOICE\') email_invoice.style.display = \'inline\';'.
                     '}'.
                     '</script>';
            
            $param = array();
            $param['class'] = 'form-control input-small input-inline';
            //$param['class']='form-control col-sm-3';
            $sql = '(SELECT "NONE") UNION (SELECT "OK") UNION (SELECT "PAID") UNION (SELECT "BAD_DEBT")';
            $html .= '<span id="status_select" style="display:none"> status&raquo;'.
                     Form::sqlList($sql,$this->db,'status_change',$status_change,$param).
                     '</span>'; 
            
            $param['class'] = 'form-control input-medium input-inline';       
            $html .= '<span id="email_invoice" style="display:none"> Email address&raquo;'.
                     Form::textInput('email_address',$email_address,'','','',$param).
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
          
          if($action === 'EMAIL_INVOICE') {
            $email_address = Secure::clean('email',$_POST['email_address']);
            Validate::email('email address',$email_address,$error_str);
            $audit_str = 'Email invoice to['.$email_address.'] ';
            if($error_str != '') $this->addError('INVAID email address['.$email_address.']!');
          }
          
          if(!$this->errors_found) {     
            foreach($_POST as $key => $value) {
              if(substr($key,0,8) === 'checked_') {
                $invoice_id = substr($key,8);
                $audit_str .= 'invoice ID['.$invoice_id.'] ';
                                    
                if($action === 'STATUS_CHANGE') {
                  $sql = 'UPDATE `'.$this->table.'` SET `status` = "'.$this->db->escapeSql($status_change).'" '.
                         'WHERE `invoice_id` = "'.$this->db->escapeSql($invoice_id).'" ';
                  $this->db->executeSql($sql,$error_tmp);
                  if($error_tmp === '') {
                    $message_str = 'Status set['.$status_change.'] for Invoice ID['.$invoice_id.'] ';
                    $audit_str .= ' success!';
                    $audit_count++;
                    
                    $this->addMessage($message_str);                
                  } else {
                    $this->addError('Could not update status for invoice['.$invoice_id.']: '.$error_tmp);                
                  }  
                }
                
                if($action === 'EMAIL_INVOICE') {
                  $sql = 'SELECT `user_id`,`doc_name`,`invoice_no` FROM `'.$this->table.'` '.
                         'WHERE `invoice_id` = "'.$this->db->escapeSql($invoice_id).'" ';
                  $invoice = $this->db->readSqlRecord($sql);
                  
                  HelpersPayment::sendInvoice($this->db,$this->container,$invoice['user_id'],$invoice_id,$email_address,$error_tmp);
                  if($error_tmp === '') {
                    $audit_str .= ' success!';
                    $audit_count++;
                    $this->addMessage('Invoice['.$invoice['invoice_no'].'] sent to email['.$email_address.']');      
                  } else {
                    $this->addError('Cound not send invoice['.$invoice['invoice_no'].'] to email address['.$email_address.']!');
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



//$tbl->add_href='invoice_wizard.php';



?>
