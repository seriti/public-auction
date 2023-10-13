<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\STORAGE;

use App\Auction\Helpers;

class AccountInvoice extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    protected $payment_gateway = MODULE_AUCTION['access']['payment'];
    protected $user_id = 0;

    //configure
    public function setup($param = []) 
    {
        $table_param = ['row_name'=>'Invoice','col_label'=>'date_create','action_header'=>'View info'];
        parent::setup($table_param);
       
        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        if(isset($param['user_id'])) $this->user_id = $param['user_id'];

        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'invoice_id','type'=>'INTEGER','title'=>'Invoice ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'auction_id','type'=>'INTEGER','title'=>'Auction',
                                 'join'=>'`name` FROM `'.$this->table_prefix.'auction` WHERE `auction_id`'));
        $this->addTableCol(array('id'=>'invoice_no','type'=>'STRING','title'=>'Invoice no'));
        $this->addTableCol(array('id'=>'date','type'=>'DATE','title'=>'Date'));
        $this->addTableCol(array('id'=>'sub_total','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'tax','type'=>'DECIMAL','title'=>'VAT'));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total'));
        $this->addTableCol(array('id'=>'comment','type'=>'TEXT','title'=>'Comment'));
        //$this->addTableCol(array('id'=>'doc_name','type'=>'FILE','title'=>'Invoice document','required'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'OK'));

        $this->addSortOrder('T.`invoice_id` DESC','Most recent latest','DEFAULT');

        $this->addSql('WHERE','T.`user_id` = "'.$this->db->escapeSql($this->user_id).'" ');

        //testing to allow pdf download but NO upload
        $this->setupFiles(array('location'=>'INV','max_no'=>10,'manage'=>false,
                                'table'=>$this->table_prefix.'file','list'=>true,'list_no'=>10,
                                'link_url'=>'account_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));
       
        //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        //$this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
        $this->addAction(array('type'=>'popup','text'=>'Items','url'=>'invoice_item','mode'=>'view','width'=>600,'height'=>600)); 
        $this->addAction(array('type'=>'popup','text'=>'Payments','url'=>'invoice_payment','mode'=>'view','width'=>600,'height'=>600)); 
       
    }

    
    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'status' and $this->payment_gateway) {
            if($value === "OK") {
                
                $value .= ' <a href="payment_wizard?id='.$data['invoice_id'].'" target="_blank">Pay now</a>';
                

            }
        
        }
    }

    //protected function beforeUpdate($id,$context,&$data,&$error) {}
    //protected function beforeDelete($id,&$error) {}
    //protected function afterDelete($id) {} 
}
?>
