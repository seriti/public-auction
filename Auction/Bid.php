<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\TABLE_USER;

class Bid extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot bid','col_label'=>'lot_id'];
        parent::setup($param);        
        
        $user = $this->getContainer('user'); 
        $access = $user->getAccessLevel();

        if($access !== 'GOD') {
            $access['read_only'] = true;                         
            $this->modifyAccess($access);
        }    

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'lot_no','type'=>'STRING','title'=>'Lot No.','linked'=>'L.lot_no'));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','edit'=>false));
        $this->addTableCol(array('id'=>'user','type'=>'STRING','title'=>'User ID: Name','linked'=>'CONCAT(O.user_id,": ",U.name)'));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Create Date & Time','linked'=>'O.date_create'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','edit'=>false));

        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'order AS O ON(T.order_id = O.order_id)');
        $this->addSql('JOIN','JOIN '.TABLE_USER.' AS U ON(O.user_id = U.user_id)');
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'lot AS L ON(T.lot_id = L.lot_id)');
        $this->addSql('WHERE','O.auction_id = "'.AUCTION_ID.'" AND O.user_id <> 0 ');
        
        $this->addSortOrder('L.lot_no, T.price DESC, O.date_create','Catalog Lot No, then price, then create date','DEFAULT');

        if($access === 'GOD') {
            $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
            $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));    
        }
        
        $this->addSearch(array('lot_id','status','price'),array('rows'=>2));
        $this->addSearchXtra('L.lot_no','Lot no');
        $this->addSearchXtra('O.user_id','User ID');
        $this->addSearchXtra('U.name','User name');
        $this->addSearchXtra('U.email','User email');
    } 

    
    
}

?>
