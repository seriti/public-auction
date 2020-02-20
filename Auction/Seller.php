<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class Seller extends Table 
{
        
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Seller','col_label'=>'name'];
        parent::setup($param);        

        $this->addTableCol(array('id'=>'seller_id','type'=>'INTEGER','title'=>'Seller ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'sort','type'=>'INTEGER','title'=>'Sort order','hint'=>'Seller drop down list will be sorted by this number'));
        $this->addTableCol(array('id'=>'cell','type'=>'STRING','title'=>'Cellphone','required'=>false));
        $this->addTableCol(array('id'=>'tel','type'=>'STRING','title'=>'Telephone','required'=>false));
        $this->addTableCol(array('id'=>'email','type'=>'EMAIL','title'=>'Email','required'=>false));
        $this->addTableCol(array('id'=>'address','type'=>'TEXT','title'=>'Billing address','required'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('seller_id','name','cell','tel','email','address','status'),array('rows'=>2));

        $this->addSelect('status','(SELECT "OK") UNION (SELECT "HIDE")');
    }    
}
?>
