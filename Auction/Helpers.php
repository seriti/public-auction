<?php 
namespace App\Auction;

use Exception;
use Seriti\Tools\Secure;
use Seriti\Tools\Crypt;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Image;
use Seriti\Tools\Calc;
use Seriti\Tools\Date;
use Seriti\Tools\Audit;

use Seriti\Tools\MAIL_FROM;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\TABLE_USER;
use Seriti\Tools\TABLE_SYSTEM;
use Seriti\Tools\SITE_NAME;

use Psr\Container\ContainerInterface;


//static functions for auction module
//see also HelpersPayment and HelperReport
class Helpers {
    
    public static function copyLot($db,$lot_id,$auction_id_copy,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_lot = TABLE_PREFIX.'lot';
        $table_file = TABLE_PREFIX.'file';

        $sql = 'SELECT * FROM '.$table_lot.' '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
        $lot = $db->readSqlRecord($sql);
    
        unset($lot['lot_id']);
        $lot['auction_id'] = $auction_id_copy;
    
        $lot_id_copy = $db->insertRecord($table_lot,$lot,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Cound not copy lot['.$lot_id.'] to auction['.$auction_id_copy.']';
        } else {
            $location_id = 'LOT'.$lot_id;
            $location_id_copy = 'LOT'.$lot_id_copy;
        
            $sql = 'SELECT * FROM '.$table_file.' WHERE location_id = "'.$db->escapeSql($location_id).'" ';
            $files = $db->readSqlArray($sql);
            if($files != 0) {
                foreach($files as $file) {
                    $file_id_copy = Calc::getFileId($db);
                    $file['file_id'] = $file_id_copy;
                    $file['location_id'] = $location_id_copy;
                    $db->insertRecord($table_file,$file,$error_tmp);
                    if($error_tmp !== '') $error .= 'Cound not copy lot['.$lot_id.'] file['.$file['file_name_orig'].'] ' ;
                }
            }
        }

        if($error === '') return true; else return false;
                  
    }

    //used to check final price is best price and not sold before
    public static function checkLotPriceValid($db,$table_prefix,$lot_id,$auction_id,$price,&$error)
    {
        $error = '';

        $table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_order_item = $table_prefix.'order_item';
        $table_invoice_item = $table_prefix.'invoice_item';

        $sql = 'SELECT auction_id,price_reserve,bid_final,buyer_id,bid_no,status '.
               'FROM '.$table_lot.' WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
        $lot = $db->readSqlRecord($sql);
        if($lot_id == 0) {
            $error .= 'Unrecognised Lot['.$lot_id.']';
        } else {
            if($lot['auction_id'] !== $auction_id) $error .= 'Lot['.$lot_id.'] auction['.$lot['auction_id'].'] not same as active auction['.$auction_id.'] ';
            if($lot['status'] === 'SOLD') {
                $sql = 'SELECT invoice_id,price '.
                       'FROM '.$table_invoice_item.' WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
                $invoice_item = $db->readSqlRecord($sql);

                $error .= 'Lot['.$lot_id.'] has a already been SOLD, see Invoice ID['.$invoice_item['invoice_id'].'] at price['.$invoice_item['price'].'] ';
            }    
        }    
        

        if($error === '') {
            //check above reserve price
            if($price < $lot['price_reserve']) {
                $error .= 'Lot price['.$price.'] less than reserve price['.$lot['price_reserve'].']. ';
            }    
       
            //check that no valid order exists with a higher bid 
            $sql = 'SELECT O.order_id,O.user_id,I.price '.
                   'FROM '.$table_order.' AS O JOIN '.$table_order_item.' AS I ON(O.order_id = I.order_id) '.
                   'WHERE O.auction_id = "'.$db->escapeSql($auction_id).'" AND O.status <> "HIDE" AND '.
                         'I.lot_id = "'.$db->escapeSql($id).'" AND I.price > "'.$db->escapeSql($price).'" ';
            $shafted = $db->readSqlArray($sql);            
            if($shafted != 0) {
                foreach($shafted as $order_id => $order) {
                    $user = Self::getUserData($db,'USER_ID',$order['user_id']);
                    $error .= 'User :'.$user['name'].' ID['.$order['user_id'].'] ';
                    if($user['bid_no'] != '') $error .= 'with Bid code['.$user['bid_no'].'] ';
                    $error .= 'Submitted a higher online bid['.$order['price'].'] in '.AUCTION_ORDER_NAME.' ID['.$order_id.']<br/>';
                }
                $error .= 'You can change '.AUCTION_ORDER_NAME.' status to HIDE if you wish to ignore this '.AUCTION_ORDER_NAME.'.';
            }

        } 
    }

    public static function updateSoldLot($db,$table_prefix,$lot_id,$price,$auction_id,$user_id,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_order_item = $table_prefix.'order_item';

        //bid_final is NOT set for post auction orders, and may also be modified in invoice creation process
        $sql = 'UPDATE '.$table_lot.' SET status = "SOLD", bid_final = "'.$db->escapeSql($price).'" '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') {
            $error .= 'Could not set status = SOLD for Lot['.$lot_id.'] '; 
        } else {

        }

        //update any related orders for this auction
        $sql = 'UPDATE '.$table_order.' AS O JOIN '.$table_order_item.' AS I ON(O.auction_id = "'.$auction_id.'" AND O.user_id = "'.$user_id.'" AND O.order_id = I.order_id) '.
               'SET I.status = "SUCCESS" '.
               'WHERE I.lot_id = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') $error .= 'Could not set user['.$user_id.'] order item status = SUCCESS for Lot['.$lot_id.'] '; 

        $sql = 'UPDATE '.$table_order.' AS O JOIN '.$table_order_item.' AS I ON(O.auction_id = "'.$auction_id.'" AND O.user_id <> "'.$user_id.'" AND O.order_id = I.order_id) '.
               'SET I.status = "OUT_BID" '.
               'WHERE I.lot_id = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp); 
        if($error_tmp != '') $error .= 'Could not set other users '.AUCTION_ORDER_NAME.' item status = OUT_BID for Lot['.$lot_id.'] user['.$user_id.'] '; 
    }

    public static function checkOrderUpdateOk($db,$table_prefix,$order_id,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_auction = $table_prefix.'auction';
        $table_order = $table_prefix.'order';

        $sql = 'SELECT T.order_id,T.auction_id,T.status,'.
                      'A.status AS auction_status,A.date_start_postal,A.date_start_live '.
               'FROM '.$table_order.' AS T JOIN '.$table_auction.' AS A ON(T.auction_id = A.auction_id) '.
               'WHERE order_id = "'.$db->escapeSql($order_id).'" ';
        $data = $db->readSqlRecord($sql);       
        if($data == 0) {
            $error .= 'Could not find '.AUCTION_ORDER_NAME.' details.';
        } else {
            $date_cut = Date::mysqlGetDate($data['date_start_live']);
            $time_now = time();
            if($time_now >= $date_cut[0]) $error .= 'You cannot modify an '.AUCTION_ORDER_NAME.' after auction start date. ';

            if($data['status'] === 'CLOSED') $error .= 'You cannot modify a CLOSED '.AUCTION_ORDER_NAME.'. ';
            if($data['auction_status'] === 'CLOSED') $error .= 'You cannot modify an '.AUCTION_ORDER_NAME.' for a CLOSED auction. ';
        }

        if($error === '') return true; else return false;
    }

    public static function updateAuctionStatus($db,$auction_id,$status_new,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_auction = TABLE_PREFIX.'auction';
        $table_order = TABLE_PREFIX.'order';

        $sql = 'SELECT auction_id,status FROM '.$table_auction.' WHERE auction_id = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql); 
        if($auction['status'] !== $status_new) {
            if($status_new === 'CLOSED') {
                $sql = 'UPDATE '.$table_order.' SET status = "CLOSED" WHERE auction_id = "'.$db->escapeSql($auction_id).'" ';
                $db->executeSql($sql,$error_tmp); 
            }
            if($status_new === 'ACTIVE' and $auction['status'] === 'CLOSED') {
                $sql = 'UPDATE '.$table_order.' SET status = "ACTIVE" WHERE auction_id = "'.$db->escapeSql($auction_id).'" ';
                $db->executeSql($sql,$error_tmp); 
            }
            
            if($error_tmp != '') {
                $error .= 'Could not close '.AUCTION_ORDER_NAME.'s for auction. ';
                if(DEBUG) $error .= $error_tmp;
            }    
        }
    }

    public static function updateOrderTotals($db,$table_prefix,$order_id,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_order = $table_prefix.'order';
        $table_item = $table_prefix.'order_item';

        $sql = 'SELECT SUM(price) as total_bid,COUNT(*) as no_items FROM '.$table_item.' '.
               'WHERE order_id = "'.$db->escapeSql($order_id).'" ';
        $totals = $db->readSqlRecord($sql);
        if($totals == 0) {
            //maybe just delete order if not closed
            $error .= 'No '.AUCTION_ORDER_NAME.' items exist.';
        } else {
            $sql = 'UPDATE '.$table_order.' SET total_bid = "'.$totals['total_bid'].'", no_items = "'.$totals['no_items'].'" '.
                   'WHERE order_id = "'.$db->escapeSql($order_id).'" ';
            $db->executeSql($sql,$error_tmp);
            if($error_tmp !== '') $error = 'could not update '.AUCTION_ORDER_NAME.' totals';
        }

        if($error === '') return $totals; else return false;
    }    

    public static function getOrderDetails($db,$table_prefix,$order_id,&$error)
    {
        $error = '';
        $output = [];
        
        $table_auction = $table_prefix.'auction';
        $table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_item = $table_prefix.'order_item';
        $table_ship_location = $table_prefix.'ship_location';
        $table_ship_option = $table_prefix.'ship_option';
        //NB: payment is associated with Auction Invoices NOT Orders
        //$table_payment = $table_prefix.'payment';

        $sql = 'SELECT O.order_id,O.auction_id,O.date_create,O.status,O.total_bid,O.total_success,'.
                      'O.ship_address,O.ship_location_id,O.ship_option_id, '.
                      'A.name AS auction, A.date_start_postal AS auction_start_postal, A.date_start_live AS auction_start_live, '.
                      'O.user_id, U.name AS user_name, U.email AS user_email, L.name AS ship_location, S.name AS ship_option '.
               'FROM '.$table_order.' AS O '.
                     'JOIN '.$table_auction.' AS A ON(O.auction_id = A.auction_id) '.
                     'LEFT JOIN '.TABLE_USER.' AS U ON(O.user_id = U.user_id) '.
                     'LEFT JOIN '.$table_ship_location.' AS L ON(O.ship_location_id = L.location_id) '.
                     'LEFT JOIN '.$table_ship_option.' AS S ON(O.ship_option_id = S.option_id) '.
               'WHERE O.order_id = "'.$db->escapeSql($order_id).'" ';
        $order = $db->readSqlRecord($sql);
        if($order === 0) {
            $error .= 'Invalid auction '.AUCTION_ORDER_NAME.' ID['.$order_id.']. ';
        } else {
            $output['order'] = $order;
        }

        $sql = 'SELECT I.item_id,I.lot_id,L.name,I.price,I.status,L.weight,L.volume '.
               'FROM '.$table_item.' AS I LEFT JOIN '.$table_lot.' AS L ON(I.lot_id = L.lot_id) '.
               'WHERE I.order_id = "'.$db->escapeSql($order_id).'" ';
        $items = $db->readSqlArray($sql);
        if($items === 0) {
            $error .= 'Invalid or no auction lots for '.AUCTION_ORDER_NAME.' ID['.$order_id.']. ';
        } else {
            $output['items'] = $items;
        }

        /*
        $sql = 'SELECT  date_create,amount,status '.
               'FROM '.$table_payment.' WHERE order_id = "'.$db->escapeSql($order_id).'" ';
        $output['payments'] = $db->readSqlArray($sql);
        */

        if($error !== '') return false; else return $output;
    }    


    public static function sendOrderMessage($db,$table_prefix,ContainerInterface $container,$order_id,$subject,$message,$param=[],&$error)
    {
        $html = '';
        $error = '';
        $error_tmp = '';

        if(!isset($param['cc_admin'])) $param['cc_admin'] = true;

        $system = $container['system'];
        $mail = $container['mail'];

        //setup email parameters
        $mail_footer = $system->getDefault('AUCTION_EMAIL_FOOTER','');
        $mail_param = [];
        $mail_param['format'] = 'html';
        if($param['cc_admin']) $mail_param['bcc'] = MAIL_FROM;
       
        $data = self::getOrderDetails($db,$table_prefix,$order_id,$error_tmp);
        if($data === false or $error_tmp !== '') {
            $error .= 'Could not get '.AUCTION_ORDER_NAME.' details: '.$error_tmp;
        } else {
            if($data['order']['user_id'] == 0 or $data['order']['user_email'] === '') $error .= 'No user data linked to '.AUCTION_ORDER_NAME;
        }    

        if($error === '') {
            $mail_from = ''; //will use default MAIL_FROM
            $mail_to = $data['order']['user_email'];

            $mail_subject = SITE_NAME.' '.AUCTION_ORDER_NAME.' ID['.$order_id.'] ';

            if($subject !== '') $mail_subject .= ': '.$subject;
            
            $mail_body = '<h1>Hi there '.$data['order']['user_name'].'</h1>';
            $mail_body .= '<h2>Auction: '.$data['order']['auction'].'</h2>';

            if($message !== '') $mail_body .= '<h2>'.$message.'</h2>';
            
            //do not want bootstrap class default
            $html_param = ['class'=>''];

            $mail_body .= '<h3>'.AUCTION_ORDER_NAME.' lots:</h3>'.Html::arrayDumpHtml($data['items'],$html_param);

            /* Payments lonked to invoices NOT orders
            if($data['payments'] !== 0) {
                $mail_body .= '<h3>Payments</h3>'.Html::arrayDumpHtml($data['payments'],$html_param);
            }
            */
    
            $mail_body .= '<br/><br/>'.$mail_footer;
            
            $mail->sendEmail($mail_from,$mail_to,$mail_subject,$mail_body,$error_tmp,$mail_param);
            if($error_tmp != '') { 
                $error .= 'Error sending '.AUCTION_ORDER_NAME.' details to email['. $mail_to.']:'.$error_tmp; 
            }
        }

        if($error === '') return true; else return false;
    }
    
    //create gallery of s3 lot images 
    public static function getLotImageGallery($db,$table_prefix,$s3,$lot_id,$param = [])
    {
        $html = '';

        if(!isset($param['access'])) $param['access'] = 'PUBLIC';

        $sql = 'SELECT name,description '.
               'FROM '.$table_prefix.'lot '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" AND status <> "HIDE"';
        $lot = $db->readSqlRecord($sql);
        if($lot === 0) {
            $html = '<h1>lot no longer available.</h1>';
            return $html;
        } else {
            $html .= '<h1>'.$lot['name'].'</h1>';
        }


        $location_id = 'LOT'.$lot_id;
        $sql = 'SELECT file_id,file_name,file_name_tn,caption AS title '.
               'FROM '.$table_prefix.'file WHERE location_id = "'.$db->escapeSql($location_id).'" '.
               'ORDER BY location_rank ';
        $images = $db->readSqlArray($sql);
        if($images != 0) {
            //setup amazon links
            $s3_param['access'] = $param['access'];
            foreach($images as $id => $image) {
                $url = $s3->getS3Url($image['file_name'],$s3_param);
                $images[$id]['src'] = $url;
            }

            if(count($images) == 1) {
                foreach($images as $image) {
                    $html .= '<img src="'.$image['src'].'" class="img-responsive center-block">';    
                }  
            } else {  
                $options = array();
                $options['img_style'] = 'max-height:600px;';
                //$options['src_root'] = ''; stored on AMAZON
                $type = 'CAROUSEL'; //'THUMBNAIL'
                
                $html .= Image::buildGallery($images,$type,$options);
                
            }  
            
        } 

        return $html; 
    }

    
    public static function getLotSummary($db,$table_prefix,$s3,$lot_id)
    {
        $html = '';
        $no_image_src = BASE_URL.'images/no_image.png';

        $sql = 'SELECT lot_id,name,description,price_reserve,status '.
               'FROM '.$table_prefix.'lot '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" AND status <> "HIDE"';
        $lot = $db->readSqlRecord($sql);
        if($lot === 0) {
            $html = '<p>lot no longer available.</p>';
            return $html;
        } else {
            $html .= '&nbsp;<strong>'.$lot['name'].' (ID:'.$lot['lot_id'].')</strong><br/>'.
                     '&nbsp;Reserve price: '.CURRENCY_SYMBOL.number_format($lot['price_reserve'],2);
        }


        $location_id = 'LOT'.$lot_id;
        $sql = 'SELECT file_id,file_name_tn AS file_name,file_name_orig AS name '.
               'FROM '.$table_prefix.'file WHERE location_id = "'.$db->escapeSql($location_id).'" '.
               'ORDER BY location_rank, file_date DESC LIMIT 1';
        $image = $db->readSqlRecord($sql);
        if($image != 0) {
            $url = $s3->getS3Url($image['file_name']);
            $title = $image['name'];
        } else {
            $url = $no_image_src;
            $title = 'No image available';
        } 

        $html = '<img class="list_image" src="'.$url.'" title="'.$title.'" align="left" height="50">'.$html;
        //$html = '<a href="javascript:open_popup(\'image_popup?id='.$lot_id.'\',600,600)">'.$html.'</a>'; 

        return $html; 
    }

    public static function cleanUserData($db,&$error)
    {
        $error = '';

        $sql = 'DELETE E FROM '.TABLE_PREFIX.'user_extend AS E LEFT JOIN '.TABLE_USER.' AS U ON(E.user_id = U.user_id) '.
               'WHERE U.name is NULL ';
        $recs = $db->executeSql($sql,$error);

        return $recs;
    }

    public static function getUserData($db,$ref_type,$ref_value)
    {
        $rec = 0;
        $ref_value = trim($ref_value);

        if($ref_value != '') {
            $where = '';
            if($ref_type === 'USER_ID') $where .= 'U.user_id = "'.$db->escapeSql($ref_value).'" ';
            if($ref_type === 'USER_EMAIL') $where .= 'U.email = "'.$db->escapeSql($ref_value).'" ';
            if($ref_type === 'BID_NO') $where .= 'E.bid_no = "'.$db->escapeSql($ref_value).'" ';

            if($where !== '') {
                $sql = 'SELECT U.user_id,U.name,U.email,U.access,E.extend_id,E.bid_no,E.seller_id,E.cell,E.tel,E.email_alt,E.bill_address,E.ship_address '.
                       'FROM '.TABLE_USER.' AS U LEFT JOIN '.TABLE_PREFIX.'user_extend AS E ON(U.user_id = E.user_id) '.
                       'WHERE '.$where;
                $rec = $db->readSqlRecord($sql);    
            }
        }    

        return $rec;
    }

    //NB: Cart is a special case of an order
    //$table_prefix must be passed in as not always called within auction module
    public static function getCart($db,$table_prefix,$temp_token)  
    {
        $error = '';
        $table_auction = $table_prefix.'auction';
        $table_lot = $table_prefix.'lot';
        $table_cart = $table_prefix.'order';
        $table_item = $table_prefix.'order_item';

        $sql = 'SELECT C.order_id,C.auction_id,C.date_create,C.status,A.name AS auction '.
               'FROM '.$table_cart.' AS C JOIN '.$table_auction.' AS A ON(C.auction_id = A.auction_id) '.
               'WHERE C.temp_token = "'.$db->escapeSql($temp_token).'" AND C.user_id = 0 ';
        $cart = $db->readSqlRecord($sql);

        if($cart !==0 ) {
            $sql = 'SELECT I.item_id,I.lot_id,L.name,I.price,I.status,L.weight,L.volume '.
                   'FROM '.$table_item.' AS I LEFT JOIN '.$table_lot.' AS L ON(I.lot_id = L.lot_id) '.
                   'WHERE I.order_id = "'.$cart['order_id'].'" ';
            $cart['items'] = $db->readSqlArray($sql);

            $sql = 'SELECT SUM(price) AS total,COUNT(*) AS no_items '.
                   'FROM '.$table_item.' '.
                   'WHERE order_id = "'.$cart['order_id'].'" ';
            $totals = $db->readSqlRecord($sql);
            if($totals == 0) {
                $cart['item_count'] = 0;
                $cart['total'] = 0;
            } else {
                $cart['item_count'] = $totals['no_items'];
                $cart['total'] = $totals['total'];
            }
        }
        
        return $cart;
    }


    public static function getCartItemTotals($db,$table_prefix,$order_id)  
    {
        $error = '';

        $table = $table_prefix.'order_item';
        $sql = 'SELECT SUM(price) AS total,COUNT(*) AS no_items '.
               'FROM '.$table.' '.
               'WHERE order_id = "'.$db->escapeSql($order_id).'" ';
        $totals = $db->readSqlRecord($sql);
        
        if($totals === 0) {
            unset($totals);
            $totals['total'] = 0.00;
            $totals['no_items'] = 0;
        }

        return $totals;
    }

    //NB: recalculates all item and cart totals based on latest lot data, ONLY call BEFORE order finalised.
    public static function calcCartTotals($db,$table_prefix,$temp_token,$ship_option_id,$ship_location_id,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $output = [];

        $table_cart = $table_prefix.'order';
        $table_ship = $table_prefix.'ship_cost';
        $table_item = $table_prefix.'order_item';
        $table_lot = $table_prefix.'lot';

        $cart = Helpers::getCart($db,$table_prefix,$temp_token);
        if($cart === 0) $error .= 'Cart has expired';
        
        if($error === '') {
            $cart_update = [];
            $cart_update['ship_location_id'] = $ship_location_id;
            $cart_update['ship_option_id'] = $ship_option_id;
            $cart_update['total_bid'] = $cart['total'];
            $cart_update['no_items'] = $cart['item_count'];
            
            $where = ['order_id'=>$cart['order_id']];
            $db->updateRecord($table_cart,$cart_update,$where,$error_tmp);
            if($error_tmp !== '') $error .= 'Could not update cart details: '.$error_tmp;
        }


        if($error === '') {
            return $cart;
        } else {
            return false;
        }    
    }

    //NB: recalculates all item and cart totals based on latest lot data, ONLY call BEFORE invoice finalised.
    //**** not finished ***
    public static function calcInvoiceTotals($db,$table_prefix,$temp_token,$ship_option_id,$ship_location_id,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $output = [];

        $table_cart = $table_prefix.'order';
        $table_ship = $table_prefix.'ship_cost';
        $table_item = $table_prefix.'order_item';
        $table_lot = $table_prefix.'lot';

        $cart = Helpers::getCart($db,$table_prefix,$temp_token);
        if($cart === 0) {
            $error .= 'Cart has expired';
        } else { 
            $order_id = $cart['order_id'];

            $sql = 'SELECT I.item_id,I.price,L.name,L.tax,L.weight,L.volume '.
                   'FROM '.$table_item.' AS I LEFT JOIN '.$table_lot.' AS L ON(I.lot_id = L.lot_id) '.
                   'WHERE order_id = "'.$db->escapeSql($order_id).'" ';
            $items = $db->readSqlArray($sql);
            if($items === 0) {
                $error .= 'Cart no longer exists.';
            } else {
                foreach($items as $item_id => $item) {
                    self::calcOrderItemTotals($item);
                    $items[$item_id] = $item;
                    //update database;
                    $where = ['item_id'=>$item_id];
                    //remove item fields not in table or required for update
                    unset($item['name']);
                    $db->updateRecord($table_item,$item,$where,$error_tmp);
                    if($error_tmp !== '') $error .= 'Could not update cart item totals: '.$error_tmp;
                }
            }
        }    

        //get shipping costs
        if($error === '') {
            $sql = 'SELECT  cost_free,cost_max,cost_base,cost_weight,cost_volume,cost_item FROM '.$table_ship .' '.
                   'WHERE option_id = "'.$db->escapeSql($ship_option_id).'" AND location_id = "'.$db->escapeSql($ship_location_id).'" ';
            $ship_setup = $db->readSqlRecord($sql);
            if($ship_setup === 0) $error .= 'There is no valid shipping costs setup for your location and shipping option.'; 
        }


        //calculate cart totals
        if($error === '') {
            $totals = self::getCartItemTotals($db,$table_prefix,$order_id);

            $cart_update = [];
            $cart_update['ship_location_id'] = $ship_location_id;
            $cart_update['ship_option_id'] = $ship_option_id;
            $cart_update['subtotal'] = $totals['subtotal'];
            $cart_update['tax'] = $totals['tax'];
            
            $cart_update['no_items'] = $totals['no_items'];
            $cart_update['weight'] = $totals['weight'];
            $cart_update['volume'] = $totals['volume'];

            if($ship_setup['cost_free'] > 0.1 and $totals['total'] > $ship_setup['cost_free']) {
                $ship_cost = 0.00;
            }  else {
                $ship_cost = $ship_setup['cost_base'] + 
                             $totals['no_items']*$ship_setup['cost_item'] +
                             $totals['weight']*$ship_setup['cost_weight'] +
                             $totals['volume']*$ship_setup['cost_volume'];

                if($ship_setup['cost_max'] > 0.1 and $ship_cost > $ship_setup['cost_max']) $ship_cost = $ship_setup['cost_max'];
            }          

            $cart_update['ship_cost'] = $ship_cost;
            $cart_update['total'] = $cart_update['subtotal']+$cart_update['tax']+$cart_update['ship_cost'];

            $where = ['order_id'=>$order_id];
            $db->updateRecord($table_cart,$cart_update,$where,$error_tmp);
            if($error_tmp !== '') $error .= 'Could not update cart totals: '.$error_tmp;
        }


        if($error === '') {
            $output['order_id'] = $order_id;
            $output['items'] = $items;
            $output['totals'] = $cart_update;
            return $output;
        } else {
            return false;
        }    
    }


    public static function setupOrder($db,$table_prefix,$temp_token,$auction_id,&$error)  
    {
        $error = '';
        $table = $table_prefix.'order';

        $sql = 'SELECT order_id,auction_id,date_create FROM '.$table.' '.
               'WHERE user_id = 0 AND temp_token = "'.$db->escapeSql($temp_token).'" ';
        $order = $db->readSqlRecord($sql);
        if($order === 0) {
            $data = [];
            $data['date_create'] = date('Y-m-d H:i:s');
            $data['status'] = 'NEW';
            $data['temp_token'] = $temp_token;
            $data['auction_id'] = $auction_id;

            $order_id = $db->insertRecord($table,$data,$error); 
        } else {
            if($order['auction_id'] !== $auction_id) {
                $error = 'Your auction '.AUCTION_ORDER_NAME.' cart is currently in use for another auction. Complete that '.AUCTION_ORDER_NAME.' first, or delete current '.AUCTION_ORDER_NAME.' cart contents.';
            } else {
                $order_id = $order['order_id'];
            }

        }

        return $order_id;
    }

    public static function addOrderItem($db,$table_prefix,$temp_token,$form,&$error) 
    {
        $error_tmp = '';
        $error = '';
        $submit = '';
        
        //message for user, errors for marker/debug
        $message = '';

        //require lot id at a minimum
        if(isset($form['lot_id'])) {
            $lot_id = Secure::clean('integer',$form['lot_id']);
            unset($form['lot_id']);
        } else {
            $error .= 'NO lot ID specified.';
            $message .= 'lot not recognised. ';
        }

        //submit button text
        if(isset($form['submit'])) {
            $submit = Secure::clean('integer',$form['submit']);
            unset($form['submit']);
        }  

        //validate lot setup and status
        if($error === '') {
            $sql = 'SELECT lot_id,auction_id,name,status,price_reserve,weight,volume '.
                   'FROM '.$table_prefix.'lot '.
                   'WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
            $lot = $db->readSqlRecord($sql);
            if($lot == 0 ) {
                $error .= 'Invalid lot ID['.$lot_id.']';
            } else {
                if($lot['status'] !== 'OK') {
                    $error .= 'lot status['.$lot['status'].'] invalid. ';
                    $message .= 'lot no longer available. ';
                }    
            }
        }    

        if($error === '') {
            //NB: also checks that existing order cart and lot have same auction_id
            $order_id = self::setupOrder($db,$table_prefix,$temp_token,$lot['auction_id'],$error_tmp);
            if($error_tmp !== '') {
                $error .= 'Could not setup order:'.$error_tmp;
                $message .= 'Could not add lot: '.$error_tmp;
            }    
            
            if($error === '') {
                $sql = 'SELECT item_id FROM '.$table_prefix.'order_item '.
                       'WHERE order_id = "'.$db->escapeSql($order_id).'" AND '.
                             'lot_id = "'.$db->escapeSql($lot_id).'" ';
                $item_exist = $db->readSqlRecord($sql);

                $item = [];
                if($item_exist === 0) {
                    $item['order_id'] = $order_id;
                    $item['lot_id'] = $lot_id;
                    $item['status'] = 'BID';
                } 

                $item['tax'] = ''; //tax not currently used
                $item['price'] = $lot['price_reserve'];
                $item['weight'] = $lot['weight'];
                $item['volume'] = $lot['volume'];
                self::calcOrderItemTotals($item);

                $table = $table_prefix.'order_item';
                if($item_exist === 0) {
                    $db->insertRecord($table,$item,$error_tmp);
                } else {
                    $where = ['item_id'=>$item_exist['item_id']];
                    $db->updateRecord($table,$item,$where,$error_tmp);
                }

                if($error_tmp !== '') {
                    $error .= 'Could not update '.AUCTION_ORDER_NAME.' item: '.$error_tmp;
                    $message .= 'Could not save '.AUCTION_ORDER_NAME.' item. ';
                }  else {
                    $message .= $lot['name'].': Successfuly added to your '.AUCTION_ORDER_NAME.'. ';
                }  
            }
        }    

        //message for user, error for debug
        return $message;
    }
    
    
    public static function calcOrderItemTotals(&$item)
    {
        
        //tax not provided for at this stage but maybe later
        if($item['tax'] === '') {
            $tax_per_item = 0;
        } else {
            $tax = self::parseTax($item['tax']);

            if($tax['inclusive']) {
                $price_incl = $item['price'];
                if($tax['type'] === 'percentage') {
                    $item['price'] = round(($price_incl / (1 + $tax['rate'])),2);
                } else {
                    $item['price'] = $price_incl - $tax['rate'];
                }
                $tax_per_item = $price_incl - $item['price'];
            } else {
                if($tax['type'] === 'percentage') {
                    $tax_per_item = round(($item['price'] * $tax['rate']),2);
                } else {
                    $tax_per_item = $tax['rate'];
                }
            }
        }
                
        //finally calculate totals
        $item['subtotal'] = round($item['price'],2);
        $item['tax'] = round($tax_per_item,2);
        $item['total'] = $item['subtotal']+$item['tax'];
        $item['weight'] = round($item['weight'],2);
        $item['volume'] = round($item['volume'],2);
    } 

    public static function parseTax($string) 
    {
        if($string === '') $string = '0';

        $tax = [];
        $tax['inclusive'] = true;
        $arr = explode(':',$string);
        if(count($arr) === 1) {
            $rate = $arr[0];
        } else {
            $type = strtolower($arr[0]);
            if(strpos($type,'excl') !== false) $tax['inclusive'] = false;
            $rate = $arr[1];
        }

        if(strpos($rate,'%') !== false) {
            $tax['type'] = 'percentage';
            $tax['rate'] = floatval(str_replace('%','',$rate)) / 100;
        } else { 
            $tax['type'] = 'flat';   
            $tax['rate'] = floatval($rate);
        }

        return $tax;
    }
}


?>
