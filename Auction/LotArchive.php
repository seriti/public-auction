<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Audit;

class LotArchive extends Table 
{
    protected $labels = MODULE_AUCTION['labels'];

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot','col_label'=>'name'];
        parent::setup($param);

        //DO NOT ALLOW ANY EDITING HERE EVER
        $this->modifyAccess(['read_only'=>true]);
        
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'auction_id','type'=>'INTEGER','title'=>'Auction','join'=>'name FROM '.TABLE_PREFIX.'auction WHERE auction_id'));
        $this->addTableCol(array('id'=>'lot_no','type'=>'INTEGER','title'=>'Catalog No.','edit'=>false));
        $this->addTableCol(array('id'=>'seller_id','type'=>'INTEGER','title'=>'Seller','join'=>'name FROM '.TABLE_PREFIX.'seller WHERE seller_id'));
        $this->addTableCol(array('id'=>'category_id','type'=>'INTEGER','title'=>$this->labels['category'],'join'=>'title FROM '.TABLE_PREFIX.'category WHERE id'));
        $this->addTableCol(array('id'=>'type_id','type'=>'INTEGER','title'=>$this->labels['type'],'join'=>'name FROM '.TABLE_PREFIX.'type WHERE type_id'));
        $this->addTableCol(array('id'=>'type_txt1','type'=>'STRING','title'=>$this->labels['type_txt1'],'required'=>false));
        $this->addTableCol(array('id'=>'type_txt2','type'=>'STRING','title'=>$this->labels['type_txt2'],'required'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Lot Name','hint'=>'Lots are ordered by category and then name','secure'=>false));
        $this->addTableCol(array('id'=>'condition_id','type'=>'INTEGER','title'=>'Condition','join'=>'name FROM '.TABLE_PREFIX.'condition WHERE condition_id'));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Lot Description','list'=>false));
        $this->addTableCol(array('id'=>'index_terms','type'=>'TEXT','title'=>'Index on terms','hint'=>'Use comma to separate multiple index terms for catalogue index','required'=>false));
        $this->addTableCol(array('id'=>'postal_only','type'=>'BOOLEAN','title'=>'Postal only','list'=>false));
        $this->addTableCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price'));
        $this->addTableCol(array('id'=>'price_estimate','type'=>'DECIMAL','title'=>'Estimate Price'));
        $this->addTableCol(array('id'=>'bid_final','type'=>'DECIMAL','title'=>'Final bid','edit'=>false));
        $this->addTableCol(array('id'=>'weight','type'=>'DECIMAL','title'=>'Weight Kg','new'=>0,'list'=>false));
        $this->addTableCol(array('id'=>'volume','type'=>'DECIMAL','title'=>'Volume Litres','new'=>0,'list'=>false));
        $this->addTableCol(array('id'=>'buyer_id','type'=>'INTEGER','title'=>'Buyer ID','edit'=>false,'list'=>true));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
                
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'category AS CT ON(T.category_id = CT.'.$this->tree_cols['node'].')');
        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'condition AS CN ON(T.condition_id = CN.condition_id)');

        //$this->addSortOrder('CT.rank,T.name','Category, then Name');
        //$this->addSortOrder('CT.rank,T.name',$this->labels['category'].', then Name');
        $this->addSortOrder('CT.rank,T.type_txt1,T.type_txt2,CN.sort',$this->labels['category'].', then '.$this->labels['type_txt1'].', then '.$this->labels['type_txt2'].', then Condition');
        $this->addSortOrder('T.lot_id DESC','Order of creation, most recent first.','DEFAULT');

        $this->addAction(array('type'=>'view','text'=>'view','icon_text'=>'view'));
        //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));

        //NB: need to update lot_info to be AUCTION_ID agnostic
        //$this->addAction(array('type'=>'popup','text'=>'Info','url'=>'lot_info','mode'=>'view','width'=>600,'height'=>600)); 

        $sql_cat = 'SELECT id,CONCAT(IF(level > 1,REPEAT("--",level - 1),""),title) FROM '.TABLE_PREFIX.'category  ORDER BY rank';
        $this->addSelect('category_id',$sql_cat);
        $sql_condition = 'SELECT condition_id,name FROM '.TABLE_PREFIX.'condition WHERE status <> "HIDE" ORDER BY sort';
        $this->addSelect('condition_id',$sql_condition);
        $sql_status = '(SELECT "NEW") UNION (SELECT "OK") UNION (SELECT "SOLD") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);

        $this->addSelect('auction_id','SELECT auction_id,name FROM '.TABLE_PREFIX.'auction ORDER BY name');
        $this->addSelect('seller_id','SELECT seller_id,name FROM '.TABLE_PREFIX.'seller WHERE status <> "HIDE" ORDER BY sort');
        $this->addSelect('type_id','SELECT type_id,name FROM '.TABLE_PREFIX.'type WHERE status <> "HIDE" ORDER BY sort');


        $this->addSearch(['lot_id','auction_id','lot_no','type_id','type_txt1','type_txt2','name','description','category_id','index_terms',
                          'postal_only','price_reserve','seller_id','status','buyer_id'],['rows'=>3]);

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'LOT','max_no'=>10,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,'access'=>IMAGE_CONFIG['access'],
                                  'link_url'=>'lot_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

    }


}
?>
