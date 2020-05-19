<?php 
namespace App\Auction;

use Seriti\Tools\Listing;

//use Seriti\Tools\Form;
use Seriti\Tools\Secure;
//use Seriti\Tools\Template;
//use Seriti\Tools\Image;
//use Seriti\Tools\Calc;
//use Seriti\Tools\Menu;

//use Seriti\Tools\DbInterface;
//use Seriti\Tools\IconsClassesLinks;
//use Seriti\Tools\MessageHelpers;
//use Seriti\Tools\ContainerHelpers;
//use Seriti\Tools\STORAGE;
//use Seriti\Tools\UPLOAD_DOCS;
//use Seriti\Tools\BASE_PATH;
//use Seriti\Tools\BASE_TEMPLATE;
use Seriti\Tools\BASE_URL;

use Seriti\Tools\STORAGE;
//use Seriti\Tools\BASE_UPLOAD_WWW;

use Psr\Container\ContainerInterface;

class LotList extends Listing
{
    protected $auction_id;
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    //for storing linked auction details
    protected $auction;

    //configure
    public function setup($param = []) 
    {
        if(!isset($param['auction_id'])) {
           $this->addError('NO auction specified'); 
        } else {
            $this->auction_id = Secure::clean('integer',$param['auction_id']);
            //only list auction lots with status = OK 
            $this->addSql('WHERE','T.auction_id = "'.$this->db->escapeSql($this->auction_id).'" AND T.status = "OK"');
        }

        $labels = MODULE_AUCTION['labels'];
        $image_access = MODULE_AUCTION['images']['access'];
        
        $currency = 'R';

        $image_popup = ['show'=>true,'width'=>600,'height'=>500];

        //javascript to process add to bid form button
        //cart_icon.innerHTML = "WTF";
        $action_callback = 'var cart_icon = document.getElementById("menu_cart");
                            cart_icon.style.display="inline";
                           ';

        $param = ['row_name'=>'Lot','col_label'=>'name','show_header'=>false,'order_by'=>'name',
                  'image_pos'=>'LEFT','image_width'=>200,'no_image_src'=>BASE_URL.'images/no_image.png',
                  'image_popup'=>$image_popup,'format'=>'MERGE_COLS', //'format'=>'MERGE_COLS' or 'STANDARD'
                  'action_route'=>BASE_URL.'public/ajax?mode=list_add',
                  'action_callback'=>$action_callback,
                  'action_button_text'=>'Add to '.$labels['order']]; 
        parent::setup($param);

        $this->addListCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addListCol(array('id'=>'category_id','type'=>'INTEGER','title'=>$labels['category'],'class'=>'list_item_title','list'=>true,'tree'=>'CT','join'=>'title FROM '.$this->table_prefix.'category WHERE id'));
        $this->addListCol(array('id'=>'name','type'=>'STRING','title'=>'Name','class'=>'list_item_title'));

        $this->addListCol(array('id'=>'type_id','type'=>'INTEGER','title'=>$labels['type'],'join'=>'name FROM '.$this->table_prefix.'type WHERE type_id'));
        $this->addListCol(array('id'=>'type_txt1','type'=>'STRING','title'=>$labels['type_txt1']));
        $this->addListCol(array('id'=>'type_txt2','type'=>'STRING','title'=>$labels['type_txt2']));
        $this->addListCol(array('id'=>'condition_id','type'=>'INTEGER','title'=>'Condition','join'=>'name FROM '.$this->table_prefix.'condition WHERE condition_id'));

        $this->addListCol(array('id'=>'description','type'=>'TEXT','title'=>'Description','class'=>'list_item_text'));
        $this->addListCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price','prefix'=>$currency));
        $this->addListCol(array('id'=>'price_estimate','type'=>'DECIMAL','title'=>'Estimate Price','prefix'=>$currency));
        $this->addListCol(array('id'=>'index_terms','type'=>'STRING','title'=>'Index terms','list'=>false));

        //NB: must have to be able to search on products below category_id in tree
        $this->addSql('JOIN','JOIN '.$this->table_prefix.'category AS CT ON(T.category_id = CT.'.$this->tree_cols['node'].')');
        $this->addSql('JOIN','JOIN '.$this->table_prefix.'condition AS CN ON(T.condition_id = CN.condition_id)');

        //$this->addSql('JOIN','JOIN '.$this->table_prefix.'type AS L ON(T.type_id = L.type_id)');
        
        //sort by primary category, then name, tyen description
        //$this->addSortOrder('CT.rank,T.name,T.description ',$labels['category'].', then Name then Description','DEFAULT');
        //if not using this sort order then JOIN to condition table above not necessary for CN.sort
        $this->addSortOrder('CT.rank,T.type_txt1,T.type_txt2,CN.sort',$labels['category'].', then '.$labels['type_txt1'].', then '.$labels['type_txt2'].', then Condition','DEFAULT');
                
        //add empty text action just to specify where Add to Order button appears
        $this->addListAction('submit',['type'=>'text','text'=>'','pos'=>'R']);

        $sql_cat = 'SELECT id,CONCAT(IF(level > 1,REPEAT("--",level - 1),""),title) FROM '.$this->table_prefix.'category  ORDER BY rank';
        $this->addSelect('category_id',$sql_cat);

        $this->addSelect('type_id','SELECT type_id,name FROM '.$this->table_prefix.'type WHERE status <> "HIDE" ORDER BY sort');
        $this->addSelect('index_terms','SELECT term_code,name FROM '.$this->table_prefix.'index_term WHERE status <> "HIDE" ORDER BY name');
        $this->addSelect('condition_id','SELECT condition_id,name FROM '.$this->table_prefix.'condition WHERE status <> "HIDE" ORDER BY sort');
        
        //left out index_terms for now
        $this->addSearch(array('category_id','name','type_id','type_txt1','type_txt2','condition_id','description','index_terms'),array('rows'=>3));

        $this->setupListImages(array('table'=>$this->table_prefix.'file','location'=>'LOT','max_no'=>100,'manage'=>false,
                                     'list'=>true,'list_no'=>1,'storage'=>STORAGE,'title'=>'Lot','access'=>$image_access,
                                     'link_url'=>'lot_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

        $this->setupAuction();
       
    }

    protected function modifyRowFormatted($row_no,&$actions_left,&$actions_right,&$images,&$files,&$items)
    {
       $lot_id = $items[$this->key['id']]['value'];

       $gallery_link = '<a href="javascript:open_popup(\'image_popup?id='.$lot_id.'\','.$this->image_popup['width'].','.$this->image_popup['height'].')">'.
                        $this->icons['gallery'].'</a>';

       $items['name']['formatted'] .= '&nbsp;'.$gallery_link;
        
    }

    protected function setupAuction() 
    {
        $sql = 'SELECT name,summary,description,date_start_postal,date_start_live,status '.
               'FROM '.$this->table_prefix.'auction '.
               'WHERE auction_id = "'.$this->db->escapeSql($this->auction_id).'" ';
        $this->auction = $this->db->readSqlRecord($sql);

        //if($this->auction['status'] <> 'ACTIVE') $this->addMessage('The auction is currently not ACTIVE');
        if($this->auction['status'] === 'CLOSED') $this->addMessage('The auction has been CLOSED');

    }

    public function getAuction() 
    {
        return $this->auction;
    }
}  

?>
