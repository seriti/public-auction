<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\TABLE_USER;

class UserExtend extends Table 
{
        
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Setting','col_label'=>'parameter'];
        parent::setup($param);        

        $this->addTableCol(array('id'=>'extend_id','type'=>'INTEGER','title'=>'Extend ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'user_id','type'=>'INTEGER','title'=>'User ID - name: email','edit_title'=>'User','join'=>'CONCAT(user_id," - ",name,": ",email) FROM '.TABLE_USER.' WHERE user_id'));
        $this->addTableCol(array('id'=>'bid_no','type'=>'STRING','title'=>'Buyer No.','required'=>false));
        $this->addTableCol(array('id'=>'seller_id','type'=>'INTEGER','title'=>'Linked seller','new'=>0));
        $this->addTableCol(array('id'=>'cell','type'=>'STRING','title'=>'Cellphone','required'=>false));
        $this->addTableCol(array('id'=>'tel','type'=>'STRING','title'=>'Telephone','required'=>false));
        $this->addTableCol(array('id'=>'email_alt','type'=>'EMAIL','title'=>'Email alternative','required'=>false));
        $this->addTableCol(array('id'=>'bill_address','type'=>'TEXT','title'=>'Billing address','required'=>false));
        $this->addTableCol(array('id'=>'ship_address','type'=>'TEXT','title'=>'Shipping address','required'=>false));

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('user_id','cell','tel','email_alt','bill_address','ship_address'),array('rows'=>2));

        $this->addSelect('user_id','SELECT user_id,name FROM '.TABLE_USER.' ');
        $this->addSelect('seller_id','(SELECT "0","NOT a seller") UNION SELECT seller_id,name FROM '.TABLE_PREFIX.'seller ');
    }    

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        //bid no must be unique if set
        if($data['bid_no'] !== '') {
            $sql = 'SELECT COUNT(*) FROM '.$this->table.' '.
                   'WHERE bid_no = "'.$this->db->escapeSql($data['bid_no']).'" ';
            if($context === 'UPDATE') $sql .= 'AND extend_id <> "'.$this->db->escapeSql($id).'" ';

            $count = $this->db->readSqlValue($sql);
            if($count > 0) $error .= 'Bid number['.$data['bid_no'].'] is already in use with another user!';
        }

    }

}
?>
