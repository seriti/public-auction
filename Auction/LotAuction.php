<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;
use Seriti\Tools\TABLE_USER;

class LotAuction extends Table 
{
    protected $labels = MODULE_AUCTION['labels'];

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
        $this->addTableCol(array('id'=>'lot_no','type'=>'INTEGER','title'=>'Catalog No.','edit'=>false,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Lot Name','edit'=>false));
        //$this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Lot Description','edit'=>false));
        //$this->addTableCol(array('id'=>'category_id','type'=>'INTEGER','title'=>'Category','join'=>'title FROM '.TABLE_PREFIX.'category WHERE id','edit'=>false));
        //$this->addTableCol(array('id'=>'postal_only','type'=>'BOOLEAN','title'=>'Postal only','edit'=>false));
        $this->addTableCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price','edit'=>false));
        $this->addTableCol(array('id'=>'bid_open','type'=>'DECIMAL','title'=>'Opening bid'));
        $this->addTableCol(array('id'=>'bid_book_top','type'=>'DECIMAL','title'=>'Bid book top'));
        $this->addTableCol(array('id'=>'bid_final','type'=>'DECIMAL','title'=>'Bid FINAL'));
        $this->addTableCol(array('id'=>'bid_no','type'=>'STRING','title'=>'Buyer ID/Number'));
        $this->addTableCol(array('id'=>'buyer_id','type'=>'INTEGER','title'=>'Buyer ID','edit'=>false,'list'=>false));
        
        $this->addSql('WHERE','T.auction_id = "'.AUCTION_ID.'" and T.Status <> "HIDE" ');
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'category AS CT ON(T.category_id = CT.'.$this->tree_cols['node'].')');
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'condition AS CN ON(T.condition_id = CN.condition_id)');

        $this->addSortOrder('CT.rank,T.type_txt1,T.type_txt2,CN.sort',$this->labels['category'].', then '.$this->labels['type_txt1'].', then '.$this->labels['type_txt2'].', then Condition','DEFAULT');
        
        $this->addAction(array('type'=>'check_box','text'=>''));
        //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'popup','text'=>'Info','url'=>'lot_info','mode'=>'view','width'=>600,'height'=>600)); 
        
        $sql_cat = 'SELECT id,CONCAT(IF(level > 1,REPEAT("--",level - 1),""),title) FROM '.TABLE_PREFIX.'category  ORDER BY rank';
        $this->addSelect('category_id',$sql_cat);
        
        $this->addSearch(array('lot_id','lot_no','name','bid_final','buyer_id'),array('rows'=>2));

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'LOT','max_no'=>10,'manage'=>false,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,
                                  'link_url'=>'lot_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $error_tmp = '';

        //allows user to reset all values if incorrectly captured
        if($data['bid_no'] === '0') {
            if($data['bid_final'] > 0 or $data['bid_book_top'] > 0 or $data['bid_open'] > 0) {
                $error .= 'NO Buyer ID/Number entered. Enter valid value or Set ALL Bid values to zero if you want to remove bid.';
            } else {
                $data['buyer_id'] = '0';
            }
        } else {
            //determine buyer_id
            if(strtoupper(substr($data['bid_no'],0,1)) === 'N') {
                $bid_no = substr($data['bid_no'],1);
                $buyer = Helpers::getUserData($this->db,'BID_NO',$bid_no);
                if($buyer == 0) $error .= 'Invalid buyer Number['.$bid_no.'] entered. ';
            } else {
                $user_id = $data['bid_no'];
                $buyer = Helpers::getUserData($this->db,'USER_ID',$user_id);
                if($buyer == 0) $error .= 'Invalid buyer User ID['.$user_id.'] entered. ';
            }

            //assign correct user_id
            $data['buyer_id'] = $buyer['user_id']; 

            Helpers::checkLotPriceValid($this->db,TABLE_PREFIX,$id,AUCTION_ID,$data['bid_final'],$error_tmp);
            if($error_tmp !== '') $error .= $error_tmp;   
        }    
    }

    protected function afterUpdate($id,$edit_type,$data) 
    {
        //NB: THIS ALSO HAPPENS AT INVOICE CREATION TIME AND WILL OVERWRITE ANYTHING SET HERE. INTENDED AS A LIVE UPDATE TO ONLINE USERS 
        $error = '';
        $user_id = $data['buyer_id'];
        $lot_id = $id;

        //NB: user_id = 0 assumes reset of a mistaken result capture
        if($user_id == 0) {
            $sql = 'UPDATE '.TABLE_PREFIX.'order AS O JOIN '.TABLE_PREFIX.'order_item AS I ON(O.auction_id = "'.AUCTION_ID.'" AND O.order_id = I.order_id) '.
                   'SET I.status = "BID" '.
                   'WHERE I.lot_id = "'.$this->db->escapeSql($lot_id).'" ';
            $this->db->executeSql($sql,$error);
        } else {
            $buyer = Helpers::getUserData($this->db,'USER_ID',$user_id);

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
}
?>
