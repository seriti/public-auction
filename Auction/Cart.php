<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\STORAGE;

use App\Auction\Helpers;

class Cart extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    protected $order_id;
    protected $auction_id;
    
    //configure
    public function setup($param = []) 
    {
        //NB: csrf verification turned off as public user can update cart without being logged in or a user at all 
        $table_param = ['row_name'=>'Cart item','col_label'=>'lot_id','table_edit_all'=>true,'verify_csrf'=>false];
        parent::setup($table_param);
       
        //if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        $this->order_id = $param['order_id'];
        $this->auction_id = $param['auction_id'];

        $access['read_only'] = false;  
        $access['edit'] = true;                         
        $access['delete'] = true;
        $access['add'] = false;
        $access['email'] = false;
        $this->modifyAccess($access);

        //default of "Proceed" similar with title "Proceed to checkout"
        $this->changeText('btn_action','Update cart');

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Cart item ID','key'=>true,'key_auto'=>true,'list'=>false));
        
        $this->addTableCol(array('id'=>'lot_id','type'=>'STRING','title'=>'Lot name','edit'=>false));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price'));
        $this->addTableCol(array('id'=>'subtotal','type'=>'DECIMAL','title'=>'Subtotal','edit'=>false));
        $this->addTableCol(array('id'=>'tax','type'=>'DECIMAL','title'=>'Tax','edit'=>false));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total','edit'=>false));

        $this->addSql('WHERE','T.order_id = "'.$this->db->escapeSql($param['order_id']).'" ');
        
        $this->addAction(['type'=>'check_box','text'=>'','checked'=>true]);
        $this->addAction(['type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R']);
    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $sql = 'SELECT T.price, L.name, L.price_reserve '.
               'FROM '.$this->table.' AS T JOIN '.$this->table_prefix.'lot AS L ON(T.lot_id = L.lot_id) '.
               'WHERE T.item_id = "'.$this->db->escapeSql($id).'" ';
        $rec = $this->db->readSqlRecord($sql);
        if($rec == 0) {
            $error .= $this->row_name.' linked Lot no longer available.';
        }  else {
            if($data['price'] < $rec['price_reserve']) $error .= 'Bid price['.$data['price'].'] Cannot be LESS than Reserve price['.$rec['price_reserve'].']. ';
        }     

    }

    //NB: this will update cart item with latest lot totals
    protected function afterUpdate($id,$context,$data) 
    {
        $error = '';
        $error_tmp = '';

        if($context === 'UPDATE') {
            //NB: lot_id NOT available in $data as not included in form
            $item = $this->get($id);
            
            $sql = 'SELECT lot_id,name,status,price_reserve,weight,volume '.
                   'FROM '.$this->table_prefix.'lot '.
                   'WHERE lot_id = "'.$this->db->escapeSql($item['lot_id']).'" ';
            $lot = $this->db->readSqlRecord($sql);

            if($lot === 0) {
                $error = 'Could not find lot data to update cart item totals!';
                if($this->debug) $error .= ': lot_id['.$item['lot_id'].'] SQL:'.$sql;
                $this->addError($error);
            } else {
                $data['price'] = $lot['price_reserve'];
                //tax is a text fields which need to be converted to a numerical format
                $data['tax'] = '';
                $data['weight'] = $lot['weight'];
                $data['volume'] = $lot['volume'];
                Helpers::calcOrderItemTotals($data);

                $where = ['item_id'=>$id];
                $this->db->updateRecord($this->table,$data,$where,$error_tmp);
                if($error_tmp !== '') {
                    $error = 'Could not find update cart item totals!';
                    if($this->debug) $error .= ': item_id['.$id.'] error:'.$error_tmp;
                    $this->addError($error);
                }
            }
        }         
    } 
    
    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            $value = Helpers::getLotSummary($this->db,$this->table_prefix,$s3,$lot_id);
        }
    }


    protected function afterDelete($id) 
    {
        $error = '';
        $count = $this->count();

        if($count['row_count'] == 0) {
            $sql = 'DELETE FROM '.$this->table_prefix.'order WHERE order_id = "'.$this->db->escapeSql($this->order_id).'" ';
            $this->db->executeSql($sql,$error);
            if($error !== '') $this->addError('Could not erase auction order.');
        }

    } 
    //protected function beforeUpdate($id,$context,&$data,&$error) {}
    //protected function beforeDelete($id,&$error) {}
    
}
?>
