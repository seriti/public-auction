<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;
use Seriti\Tools\TABLE_USER;

class LotAuction extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot','col_label'=>'name','table_edit_all'=>true,'max_rows'=>10];
        parent::setup($param);

        $access['delete']=false;
        $access['email']=false;
        $this->modifyAccess($access);
        
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'order_item ','col_id'=>'lot_id','message'=>'Auction Order item'));

        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Lot Name','edit'=>false));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Lot Description','edit'=>false));
        $this->addTableCol(array('id'=>'category_id','type'=>'INTEGER','title'=>'Category','join'=>'title FROM '.TABLE_PREFIX.'category WHERE id','edit'=>false));
        $this->addTableCol(array('id'=>'postal_only','type'=>'BOOLEAN','title'=>'Postal only','edit'=>false));
        $this->addTableCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price','edit'=>false));
        $this->addTableCol(array('id'=>'bid_open','type'=>'DECIMAL','title'=>'Opening bid'));
        $this->addTableCol(array('id'=>'bid_book_top','type'=>'DECIMAL','title'=>'Bid book top'));
        $this->addTableCol(array('id'=>'bid_final','type'=>'DECIMAL','title'=>'Bid FINAL'));
        $this->addTableCol(array('id'=>'bid_no','type'=>'STRING','title'=>'Buyer Number'));
        $this->addTableCol(array('id'=>'buyer_id','type'=>'INTEGER','title'=>'Buyer ID','edit'=>false,'list'=>false));
        
        $this->addSql('WHERE','T.auction_id = "'.AUCTION_ID.'" and Status <> "HIDE" ');
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'category AS C ON(T.category_id = C.id) ');

        $this->addSortOrder('C.rank, T.name','Lot category and then name','DEFAULT');

        $this->addAction(array('type'=>'check_box','text'=>''));
        //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'popup','text'=>'Info','url'=>'lot_info','mode'=>'view','width'=>600,'height'=>600)); 
        
        $sql_cat = 'SELECT id,CONCAT(IF(level > 1,REPEAT("--",level - 1),""),title) FROM '.TABLE_PREFIX.'category  ORDER BY rank';
        $this->addSelect('category_id',$sql_cat);
        
        $this->addSearch(array('lot_id','name','description','category_id','postal_only'),array('rows'=>2));

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'LOT','max_no'=>10,'manage'=>false,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,
                                  'link_page'=>'lot_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $error_tmp = '';

        //need to check all entered price data and buyer id
        $buyer = Helpers::getUserData($this->db,'BID_NO',$data['bid_no']);
        if($buyer == 0) {
            $error .= 'Invalid Bid number entered. ';
        } else {
            $data['buyer_id'] = $buyer['user_id'];
        }    

        Helpers::checkLotPriceValid($this->db,TABLE_PREFIX,$id,AUCTION_ID,$data['bid_final'],$error_tmp);
        if($error_tmp !== '') $error .= $error_tmp;

        /*
        if($data['bid_final'] < $data['price_reserve']) {
            $error .= 'Final bid['.$data['bid_final'].'] less than reserve price['.$data['price_reserve'].']';
        }

        //check that no valid order exists with a higher bid 
        if($error === '') {
            $sql = 'SELECT O.order_id,O.user_id,I.price '.
                   'FROM '.TABLE_PREFIX.'order AS O JOIN '.TABLE_PREFIX.'order_item AS I ON(O.order_id = I.order_id) '.
                   'WHERE O.auction_id = "'.AUCTION_ID.'" AND O.status <> "HIDE" AND '.
                         'I.lot_id = "'.$this->db->escapeSql($id).'" AND I.price > "'.$this->db->escapeSql($data['bid_final']).'" ';
            $shafted = $this->db->readSqlArray($sql);            
            if($shafted != 0) {
                foreach($shafted as $order_id => $order) {
                    $user = Helpers::getUserData($this->db,'USER_ID',$order['user_id']);
                    $error .= 'User :'.$user['name'].' ID['.$order['user_id'].'] ';
                    if($user['bid_no'] != '') $error .= 'with Bid code['.$user['bid_no'].'] ';
                    $error .= 'Submitted a higher online bid['.$order['price'].'] in order ID['.$order_id.']<br/>';
                }
                $error .= 'You can change Order status to HIDE if you wish to ignore this order.';
            }

        }  
        */  
          

    }

    protected function afterUpdate($id,$edit_type,$form) 
    {
        //NB: THIS ALSO HAPPENS AT INVOICE CREATION TIME AND WILL OVERWRITE ANYTHING SET HERE. INTENDED AS A LIVE UPDATE TO ONLINE USERS 
        $error = '';
        $bid_no = $form['bid_no'];
        $lot_id = $id;

        $buyer = Helpers::getUserData($this->db,'BID_NO',$bid_no);

        $sql = 'UPDATE '.TABLE_PREFIX.'order AS O JOIN '.TABLE_PREFIX.'order_item AS I ON(O.auction_id = "'.AUCTION_ID.'" AND O.user_id = "'.$buyer['user_id'].'" AND O.order_id = I.order_id) '.
               'SET I.status = "SUCCESS" '.
               'WHERE I.lot_id = "'.$this->db->escapeSql($lot_id).'" ';
        $this->db->executeSql($sql,$error);

        $sql = 'UPDATE '.TABLE_PREFIX.'order AS O JOIN '.TABLE_PREFIX.'order_item AS I ON(O.auction_id = "'.AUCTION_ID.'" AND O.user_id <> "'.$buyer['user_id'].'" AND O.order_id = I.order_id) '.
               'SET I.status = "OUT_BID" '.
               'WHERE I.lot_id = "'.$this->db->escapeSql($lot_id).'" ';
        $this->db->executeSql($sql,$error);
        
    }
}
?>
