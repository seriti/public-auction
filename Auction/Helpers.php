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
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;

use Psr\Container\ContainerInterface;


//static functions for auction module
//see also HelpersPayment and HelperReport
class Helpers {
    
    //generic record get, add any exceptions you want
    public static function get($db,$table_prefix,$table,$id,$key = '') 
    {
        $table_name = $table_prefix.$table;

        if($key === '') $key = $table.'_id';    
        
        $sql = 'SELECT * FROM `'.$table_name.'` WHERE `'.$key.'` = "'.$db->escapeSql($id).'" ';
        
        $record = $db->readSqlRecord($sql);
                        
        return $record;
    }

    //reset/update extended user settings for auctions
    public static function UserReset($db,$options = [])
    {
        $error = '';
        $output = [];

        if(!isset($options['buyer_no'])) $options['buyer_no'] = false;

        if($options['buyer_no']) {
            $sql = 'UPDATE '.TABLE_PREFIX.'user_extend SET bid_no = "" ';
            $no_recs = $db->executeSql($sql,$error);
            if($error !== '') {
                $output['error'] = 'Could not reset user buyer nos!';
                if(DEBUG) $output['error'] .= ': '.$error;
            } else {
                $output['message'] = 'Successfuly removed '.$no_recs.' User Buyer No. settings.';
            }
        }

        return $output;
    }

    //email users who have ACTIVE orders with bids below winning bid
    public static function notifyLowerBids($db,ContainerInterface $container,$auction_id)
    {
        $error = '';
        $output = [];
        $output['error'] = '';
        $output['message'] = '';

        $table_auction = TABLE_PREFIX.'auction';
        $table_lot = TABLE_PREFIX.'lot';
        $table_order = TABLE_PREFIX.'order';
        $table_order_item = TABLE_PREFIX.'order_item';

        $msg_param = [];
        $msg_param['cc_admin'] = true;
        $msg_param['notify_higher_bid'] = true;

        $sql = 'SELECT `auction_id`,`name`,`summary`,`status` '.
               'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql);
        if($auction == 0) {
            $output['error'] .= 'Invalid Auction ID['.$auction_id.']';
        } else {
            //NB: see updateAuctionStatus() which closes the auction and gets rid of any unprocessed bid carts
            if($auction['status'] === 'CLOSED') {
                $output['error'] .= 'Auction['.$auction['name'].'] is CLOSED. It is pointless to notify users as they cannot increase bids!';
            } 

            if($auction['status'] !== 'ACTIVE') {
                $output['error'] .= 'Auction['.$auction['name'].'] status['.$auction['status'].']. Auction must be ACTIVE!';
            } 
        }

        //get all orders that have lower/not winning bid 
        if($output['error'] === '') {
            $sql = 'SELECT I.`lot_id`,O.`order_id`,O.`date_create`,I.`price`,O.`user_id` '.
                   'FROM `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`order_id` = I.`order_id`) '.
                   'WHERE O.`auction_id` = "'.$db->escapeSql($auction_id).'" AND O.`user_id` > 0 AND O.`status` = "ACTIVE" '.
                   'ORDER BY I.`lot_id`,I.`price` DESC, O.`date_create` ';
            $bids = $db->readSqlArray($sql,false);

            $notify_orders = [];
            $lot_id_prev = '';
            $top_bid = [];
            foreach($bids as $bid) {
                
                //first occurence of lot_id is top bid
                if($lot_id_prev === '' or $bid['lot_id'] !== $lot_id_prev) {
                    $top_bid = $bid;
                }

                //when more than one bid for a lot and first bid is best bid. 
                //NB: user check necessary as can have multiple bid forms with same lot
                if($lot_id_prev !== '' and $bid['lot_id'] === $lot_id_prev and $bid['user_id'] !== $top_bid['user_id'] ) {
                    $notify_orders[$bid['order_id']] = true;
                }
                
                $lot_id_prev = $bid['lot_id'];
            }

            foreach($notify_orders as $order_id => $notify) {
                $subject = 'outbid notification'; 
                $message = 'You have been outbid on the lot no/s as indicated below. You may review your bid form in '.
                           '<a href="'.BASE_URL.'public/account/dashboard">Your Account</a> and increase bids if you wish any time before the auction closes.';
                
                self::sendOrderMessage($db,TABLE_PREFIX,$container,$order_id,$subject,$message,$msg_param,$error);
                if($error !== '') {
                    $output['error'] .= 'Notification failed for bid form['.$order_id.']. ';
                } else {
                    $output['message'] .= 'Notification success for bid form['.$order_id.'].<br/>';
                }    
            }

        } 

        return $output;   

    }

    //assign best online bid data and remove shopping cart items linked to auction 
    public static function setupAuctionLotResults($db,$auction_id)
    {
        $error = '';
        $output = [];
        $output['error'] = '';
        $output['message'] = '';

        $table_auction = TABLE_PREFIX.'auction';
        $table_lot = TABLE_PREFIX.'lot';
        $table_order = TABLE_PREFIX.'order';
        $table_order_item = TABLE_PREFIX.'order_item';
                
        $sql = 'SELECT `auction_id`,`name`,`summary`,`status` '.
               'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql);
        if($auction == 0) {
            $output['error'] .= 'Invalid Auction ID['.$auction_id.']';
        } else {
            //NB: see updateAuctionStatus() which closes the auction and gets rid of any unprocessed bid carts
            if($auction['status'] !== 'CLOSED') {
                $output['error'] .= 'Auction['.$auction['name'].'] status['.$auction['status'].'] is not CLOSED. You can only assign lot bidding results to a CLOSED auction.';
            } 
        }
        
        //make sure lots exist 
        if($output['error'] === '') {
            $sql = 'SELECT L.`lot_id`,L.`lot_no`,L.`name`,L.`status`,L.`bid_book_top`,L.`bid_final`,L.`buyer_id`,L.`bid_no` '.
                   'FROM `'.$table_lot.'` AS L '.
                   'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" '.
                   'ORDER BY L.`lot_no`';
            $lots = $db->readSqlArray($sql);
            if($lots == 0) $output['error'] .= 'No lots found to process for auction!';
        }    

        //check for any existing final bids and warn
        if($output['error'] === '') {
            $sql = 'SELECT SUM(`bid_final`) FROM `'.$table_lot.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
            $sum_bid_final = $db->readSqlValue($sql,0);
            if($sum_bid_final > 0) {
                $output['message'] .= 'Final bids already assigned to some Lots. These lots will not be updated.<br/>';
            }
        }

        //finally assign best bids where not done already
        if($output['error'] === '') {

            $update_i = 0;
            foreach($lots as $lot_id=>$lot) {
                
                if($lot['buyer_id'] != 0) {
                    $output['message'] .= 'Lot no['.$lot['lot_no'].'] & ID['.$lot_id.'] already assigned final bids.<br/>';
                } else {
                    $sql = 'SELECT O.`user_id`,O.`date_create`,I.`price` '.
                           'FROM `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`order_id` = I.`order_id`) '.
                           'WHERE I.`lot_id` = "'.$db->escapeSql($lot_id).'" AND O.`user_id` > 0 AND O.`status` = "CLOSED" '.
                           'ORDER BY I.`price` DESC, O.`date_create` '.
                           'LIMIT 1';
                    $best_bid = $db->readSqlRecord($sql); 

                    //capture best bid if any
                    if($best_bid != 0) {

                        $sql = 'UPDATE `'.$table_lot.'` SET `buyer_id` = "'.$best_bid['user_id'].'", '.
                                                         '`bid_no` = "'.$best_bid['user_id'].'", '.
                                                         '`bid_book_top` = "'.$best_bid['price'].'", '.
                                                         '`bid_final` = "'.$best_bid['price'].'" '.
                               'WHERE `lot_id` = '.$lot_id.' ';
                        $db->executeSql($sql,$error); 
                        if($error !== '') {
                            $output['error'] .= 'Could not assign Best Bid for Lot no['.$lot['lot_no'].'] & ID['.$lot_id.']<br/>';
                        } else {
                            $update_i++;
                        }    
                    }      

                    
                }
                
            }
        }

        if($output['error'] === '') {
            $output['message'] = 'Successfully assigned final bids to <strong>'.$update_i.'</strong> lots<br/>'.$output['message'];
        }    

        return $output;
    }

    public static function setupAuctionLotNos($db,$auction_id,$options = [])
    {
        $error = '';
        $output = [];
        $output['error'] = '';
        $output['message'] = '';

        if(!isset($options['user_access'])) $options['user_access'] = 'ADMIN';

        $table_auction = TABLE_PREFIX.'auction';
        $table_lot = TABLE_PREFIX.'lot';
        $table_condition = TABLE_PREFIX.'condition';
        $table_category = TABLE_PREFIX.'category';
        $table_order = TABLE_PREFIX.'order';

        $sql = 'SELECT `auction_id`,`name`,`summary`,`status` '.
               'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql);
        if($auction == 0) {
            $output['error'] .= 'Invalid Auction ID['.$auction_id.']';
        } else {
            if($auction['status'] === 'CLOSED' or $auction['status'] === 'CATALOG') {
                $output['error'] .= 'Auction['.$auction['name'].'] status is '.$auction['status'].' you cannot assign numbers.';
            } 
        }

        $sql = 'SELECT SUM(`lot_no`) FROM `'.$table_lot.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $sum_lot_no = $db->readSqlValue($sql,0);
        if($sum_lot_no > 0) {
            if($options['user_access'] !== 'GOD') {
                $output['error'] .= 'Auction['.$auction['name'].'] has already assigned Lot No`s. You cannot assign numbers again.';    
            } else {
                $sql = 'SELECT COUNT(*) FROM '.$table_order.' WHERE auction_id = "'.$db->escapeSql($auction_id).'" ';
                $sum_order_no = $db->readSqlValue($sql,0);
                if($sum_order_no > 0) {
                   $output['error'] .= 'Auction['.$auction['name'].'] has already assigned Lot No`s. '.
                                       'AND '.$sum_order_no.' linked Orders, You cannot assign numbers again.'; 
                } else {
                    $output['message'] .= 'Auction['.$auction['name'].'] has already assigned Lot No`s, '.
                                          'but NO orders are linked to auction so Lot numbers will be re-assigned. '.
                                          'If you have sent catalogues out this will be a problem!<br/>'; 
                }

            }
            
        }

        if($output['error'] === '') {
            $sql = 'SELECT L.`lot_id`,L.`name`,L.`status` '.
                   'FROM `'.$table_lot.'` AS L '.
                         'JOIN `'.$table_condition.'` AS CN ON(L.`condition_id` = CN.`condition_id`) '.
                         'JOIN `'.$table_category.'` AS CT ON(L.`category_id` = CT.`id`) '.
                   'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" '.
                   'ORDER BY CT.`rank`,L.`type_txt1`,L.`type_txt2`,CN.`sort` ';
            $lots = $db->readSqlArray($sql);
            if($lots == 0) $output['error'] .= 'No lots found for auction!';
        }    


        if($output['error'] === '') {
            $lot_no = 0;
            foreach($lots as $lot_id=>$lot) {
                $lot_no++;

                $sql = 'UPDATE `'.$table_lot.'` SET `lot_no` = '.$lot_no.' WHERE `lot_id` = '.$lot_id.' ';
                $db->executeSql($sql,$error); 
                if($error != '') $output['error'] .= 'Could not assign Lot no['.$lot_no.'] to Lot ID['.$lot_id.'] ';
            }

            if($output['error'] === '') {
                $output['message'] .= 'Assigned Ltot No`s From 1 to '.$lot_no.' successfully.'; 

                //want this to be audited, hence updaterecord()
                $update = ['status'=>'CATALOG'];
                $where = ['auction_id'=>$auction_id];
                $db->updateRecord($table_auction,$update,$where,$error);
                if($error != '') $output['error'] .= 'Could not CLOSE auction after assigning Lot numbers';
            } 
        }

        return $output;
    }

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
        unset($lot['lot_no']);
        unset($lot['bid_open']);
        unset($lot['bid_book_top']);
        unset($lot['bid_final']);
        unset($lot['buyer_id']);
        unset($lot['bid_no']);

        $lot['status'] = 'NEW';
        $lot['auction_id'] = $auction_id_copy;
    
        $lot_id_copy = $db->insertRecord($table_lot,$lot,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Cound not copy lot['.$lot_id.'] to auction['.$auction_id_copy.']';
        } else {
            $location_id = 'LOT'.$lot_id;
            $location_id_copy = 'LOT'.$lot_id_copy;
        
            $sql = 'SELECT * FROM `'.$table_file.'` WHERE `location_id` = "'.$db->escapeSql($location_id).'" ';
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

    public static function reverseSale($db,$lot_id,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_lot = TABLE_PREFIX.'lot';
        $table_order_item = TABLE_PREFIX.'order_item';
        
        $sql = 'SELECT * FROM '.$table_lot.' '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" ';
        $lot = $db->readSqlRecord($sql);

        if($lot['status'] !== 'SOLD') $error .= 'Lot status not = SOLD. ';

        $sql = 'SELECT order_id FROM '.$table_order_item.' '.
               'WHERE lot_id = "'.$db->escapeSql($lot_id).'" AND status = "SUCCESS" ';
        $order_id = $db->readSqlValue($sql,0);
        if($order_id != 0) $error .= 'Order['.$order_id.'] still has Lot status = SUCCESS.';

        if($error === '') {
            $where = ['lot_id'=>$lot_id];
            $update = [];
            $update['bid_no'] = '';
            $update['buyer_id'] = 0;
            $update['bid_open'] = 0;
            $update['bid_book_top'] = 0;
            $update['bid_final'] = 0;
            $update['status'] = 'OK';

            $db->updateRecord($table_lot,$update,$where,$error_tmp);
            if($error_tmp !== '') {
                $error .= 'Cound not reverse sale';
            }
        }    
        
        if($error === '') return true; else return false;
                  
    }

    //check for other higher bids
    public static function getBestBid($db,$table_prefix,$lot_id)
    {
        $output = [];

        //$table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_order_item = $table_prefix.'order_item';

        $sql = 'SELECT O.`user_id`,O.`date_create`,I.`price` '.
               'FROM `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`order_id` = I.`order_id`) '.
               'WHERE I.`lot_id` = "'.$db->escapeSql($lot_id).'" AND O.`user_id` > 0 AND O.`status` = "ACTIVE" '.
               'ORDER BY I.`price` DESC, O.`date_create` '.
               'LIMIT 1';
        $best_bid = $db->readSqlRecord($sql); 
        if($best_bid == 0) {
            $output['active_bids'] = false;
        } else {
            $output['active_bids'] = true;
            $output['best_bid'] = $best_bid;
        }

        return $output;
    }

    //used to check final price is best price and not sold before
    public static function checkLotPriceValid($db,$table_prefix,$lot_id,$auction_id,$price,&$error)
    {
        $error = '';

        $table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_order_item = $table_prefix.'order_item';
        $table_invoice_item = $table_prefix.'invoice_item';

        $sql = 'SELECT `lot_id`,`lot_no`,`auction_id`,`price_reserve`,`bid_final`,`buyer_id`,`bid_no`,`status` '.
               'FROM `'.$table_lot.'` WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" ';
        $lot = $db->readSqlRecord($sql);
        if($lot == 0) {
            $error .= 'Unrecognised Lot['.$lot_id.']';
        } else {
            if($lot['auction_id'] !== $auction_id) $error .= 'Lot No['.$lot['lot_no'].'] & ID['.$lot_id.'] auction ID['.$lot['auction_id'].'] not same as active auction ID['.$auction_id.'] ';
            if($lot['status'] === 'SOLD') {
                $sql = 'SELECT `invoice_id`,`price` '.
                       'FROM `'.$table_invoice_item.'` WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" ';
                $invoice_item = $db->readSqlRecord($sql);

                $error .= 'Lot No['.$lot['lot_no'].'] & ID['.$lot_id.'] has a already been SOLD, see Invoice ID['.$invoice_item['invoice_id'].'] at price['.$invoice_item['price'].'] ';
            }    
        }    
        

        if($error === '') {
            //check above reserve price
            if(MODULE_AUCTION['result']['check_reserve']) {
                if($price < $lot['price_reserve']) {
                    $error .= 'Lot price['.$price.'] less than reserve price['.$lot['price_reserve'].']. ';
                }
            }
           
            //check that no valid order exists with a higher bid 
            if(MODULE_AUCTION['result']['check_bid_highest']) {
                $sql = 'SELECT O.`order_id`,O.`user_id`,I.`price` '.
                       'FROM `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`order_id` = I.`order_id`) '.
                       'WHERE O.`auction_id` = "'.$db->escapeSql($auction_id).'" AND O.`status` <> "HIDE" AND '.
                             'I.`lot_id` = "'.$db->escapeSql($lot_id).'" AND I.`price` > "'.$db->escapeSql($price).'" ';
                $shafted = $db->readSqlArray($sql);            
                if($shafted != 0) {
                    foreach($shafted as $order_id => $order) {
                        $user = Self::getUserData($db,'USER_ID',$order['user_id']);
                        $error .= 'User :'.$user['name'].' ID['.$order['user_id'].'] ';
                        if($user['bid_no'] != '') $error .= '& Bid No.['.$user['bid_no'].'] ';
                        $error .= 'Submitted a higher online bid['.$order['price'].'] in '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.']<br/>';
                    }
                    $error .= 'You can change '.MODULE_AUCTION['labels']['order'].' status to HIDE if you wish to ignore this '.MODULE_AUCTION['labels']['order'].'.';
                }
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
        $sql = 'UPDATE `'.$table_lot.'` SET `status` = "SOLD", `bid_final` = "'.$db->escapeSql($price).'" '.
               'WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') {
            $error .= 'Could not set status = SOLD for Lot['.$lot_id.'] '; 
        } else {

        }

        //update any related orders for this auction
        $sql = 'UPDATE `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`auction_id` = "'.$auction_id.'" AND O.`user_id` = "'.$user_id.'" AND O.`order_id` = I.`order_id`) '.
               'SET I.`status` = "SUCCESS" '.
               'WHERE I.`lot_id` = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') $error .= 'Could not set user['.$user_id.'] order item status = SUCCESS for Lot['.$lot_id.'] '; 

        $sql = 'UPDATE `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`auction_id` = "'.$auction_id.'" AND O.`user_id` <> "'.$user_id.'" AND O.`order_id` = I.`order_id`) '.
               'SET I.`status` = "OUT_BID" '.
               'WHERE I.`lot_id` = "'.$db->escapeSql($lot_id).'" ';
        $db->executeSql($sql,$error_tmp); 
        if($error_tmp != '') $error .= 'Could not set other users '.MODULE_AUCTION['labels']['order'].' item status = OUT_BID for Lot['.$lot_id.'] user['.$user_id.'] '; 
    }

    public static function checkOrderUpdateOk($db,$table_prefix,$order_id,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_auction = $table_prefix.'auction';
        $table_order = $table_prefix.'order';

        $sql = 'SELECT T.`order_id`,T.`auction_id`,T.`status`,'.
                      'A.`status` AS `auction_status`,A.`date_start_postal`,A.`date_start_live` '.
               'FROM `'.$table_order.'` AS T JOIN `'.$table_auction.'` AS A ON(T.`auction_id` = A.`auction_id`) '.
               'WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
        $data = $db->readSqlRecord($sql);       
        if($data == 0) {
            $error .= 'Could not find '.MODULE_AUCTION['labels']['order'].' details.';
        } else {
            /*
            $date_cut = Date::mysqlGetDate($data['date_start_live']);
            $time_now = time();
            if($time_now >= $date_cut[0]) $error .= 'You cannot modify an '.MODULE_AUCTION['labels']['order'].' after auction start date. ';
            */

            if($data['status'] === 'CLOSED') $error .= 'You cannot modify a CLOSED '.MODULE_AUCTION['labels']['order'].'. ';
            if($data['auction_status'] === 'CLOSED') $error .= 'You cannot modify an '.MODULE_AUCTION['labels']['order'].' for a CLOSED auction. ';
        }

        if($error === '') return true; else return false;
    }

    public static function updateAuctionStatus($db,$auction_id,$status_new,&$error)
    {
        $error = '';
        $error_tmp = '';

        $table_auction = TABLE_PREFIX.'auction';
        $table_order = TABLE_PREFIX.'order';
        $table_order_item = TABLE_PREFIX.'order_item';

        $sql = 'SELECT `auction_id`,`status` FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql); 
        if($auction['status'] !== $status_new) {
            if($status_new === 'CLOSED') {
                //first remove all bid form carts that have not been processed
                $sql = 'DELETE O,I FROM `'.$table_order.'` AS O JOIN `'.$table_order_item.'` AS I ON(O.`order_id` = I.`order_id`) '.
                       'WHERE O.`auction_id` = "'.$db->escapeSql($auction_id).'" AND O.`status` = "NEW" AND O.`temp_token` <> "" ';
                $db->executeSql($sql,$error_tmp);

                //now update all bid forms 
                if($error_tmp === '') {
                    $sql = 'UPDATE `'.$table_order.'` SET `status` = "CLOSED" WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
                    $db->executeSql($sql,$error_tmp); 
                }
            }
            /* this will allow users to delete lots from ACTIVE bid forms after an auction has been closed
            if($status_new === 'ACTIVE' and $auction['status'] === 'CLOSED') {
                $sql = 'UPDATE `'.$table_order.'` SET `status` = "ACTIVE" WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
                $db->executeSql($sql,$error_tmp); 
            }
            */
            
            if($error_tmp != '') {
                $error .= 'Could not close '.MODULE_AUCTION['labels']['order'].'s for auction. ';
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

        $sql = 'SELECT SUM(`price`) as `total_bid`,COUNT(*) as `no_items` FROM `'.$table_item.'` '.
               'WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
        $totals = $db->readSqlRecord($sql);
        if($totals == 0) {
            //maybe just delete order if not closed
            $error .= 'No '.MODULE_AUCTION['labels']['order'].' items exist.';
        } else {
            $sql = 'UPDATE `'.$table_order.'` SET `total_bid` = "'.$totals['total_bid'].'", `no_items` = "'.$totals['no_items'].'" '.
                   'WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
            $db->executeSql($sql,$error_tmp);
            if($error_tmp !== '') $error = 'could not update '.MODULE_AUCTION['labels']['order'].' totals';
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
        $table_payment_option = $table_prefix.'pay_option';

        $sql = 'SELECT O.`order_id`,O.`auction_id`,O.`date_create`,O.`status`,O.`total_bid`,O.`total_success`,'.
                      'O.`ship_address`,O.`ship_location_id`,O.`ship_option_id`, '.
                      'A.`name` AS `auction`, A.`date_start_postal` AS `auction_start_postal`, '.
                      'A.`date_start_live` AS `auction_start_live`,A.`status` AS `auction_status`, '.
                      'O.`user_id`, U.`name` AS `user_name`, U.`email` AS `user_email`, '.
                      'L.`name` AS `ship_location`, S.`name` AS `ship_option`, P.`name` AS `pay_option` '.
               'FROM `'.$table_order.'` AS O '.
                     'JOIN `'.$table_auction.'` AS A ON(O.`auction_id` = A.`auction_id`) '.
                     'LEFT JOIN `'.TABLE_USER.'` AS U ON(O.`user_id` = U.`user_id`) '.
                     'LEFT JOIN `'.$table_ship_location.'` AS L ON(O.`ship_location_id` = L.`location_id`) '.
                     'LEFT JOIN `'.$table_ship_option.'` AS S ON(O.`ship_option_id` = S.`option_id`) '.
                     'LEFT JOIN `'.$table_payment_option.'` AS P ON(O.`pay_option_id` = P.`option_id`) '.
               'WHERE O.`order_id` = "'.$db->escapeSql($order_id).'" ';
        $order = $db->readSqlRecord($sql);
        if($order === 0) {
            $error .= 'Invalid auction '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.']. ';
        } else {
            $output['order'] = $order;
        }

        $sql = 'SELECT I.`item_id`,I.`lot_id`,L.`lot_no`,L.`name`,I.`price`,I.`status`,L.`weight`,L.`volume` '.
               'FROM `'.$table_item.'` AS I LEFT JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
               'WHERE I.`order_id` = "'.$db->escapeSql($order_id).'" '.
               'ORDER BY L.`lot_no` ';
        $items = $db->readSqlArray($sql);
        if($items === 0) {
            $error .= 'Invalid or no auction lots for '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.']. ';
        } else {
            $output['items'] = $items;
        }

        //same as above but for presentation purposes.
        $sql = 'SELECT I.`item_id`,L.`lot_no`,L.`name`,I.`price` AS `bid` '.
               'FROM `'.$table_item.'` AS I LEFT JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
               'WHERE I.`order_id` = "'.$db->escapeSql($order_id).'" '.
               'ORDER BY L.`lot_no` ';
        $items = $db->readSqlArray($sql);
        if($items === 0) {
            $error .= 'Invalid or no auction lots-2 for '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.']. ';
        } else {
            $output['items_show'] = $items;
        }

        /*
        $sql = 'SELECT  `date_create`,`amount`,`status` '.
               'FROM `'.$table_payment.'` WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
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

        if(!isset($param['notify_higher_bid'])) $param['notify_higher_bid'] = false;
        if(!isset($param['include_links'])) $param['include_links'] = false;

        $system = $container['system'];
        $mail = $container['mail'];
        $user = $container['user'];
        $user_id = $user->getId();

        //setup email parameters
        $mail_footer = $system->getDefault('AUCTION_EMAIL_FOOTER','');
        $mail_param = [];
        $mail_param['format'] = 'html';
        if($param['cc_admin']) $mail_param['bcc'] = MAIL_FROM;
       
        $data = self::getOrderDetails($db,$table_prefix,$order_id,$error_tmp);
        if($data === false or $error_tmp !== '') {
            $error .= 'Could not get '.MODULE_AUCTION['labels']['order'].' details: '.$error_tmp;
        } else {
            if($data['order']['user_id'] == 0 or $data['order']['user_email'] === '') $error .= 'No user data linked to '.MODULE_AUCTION['labels']['order'];
        } 

        if($error === '' and $param['include_links'] and $data['order']['auction_status'] === 'ACTIVE')  {
            $message .= '<br/>You can view or review your bid form in <a href="'.BASE_URL.'public/account/dashboard">Your Account</a> and increase bids if you wish,  '.
                        'or <a href="'.BASE_URL.'public/contact">contact us</a> and we will do so on your behalf.<br/>'.
                        'If you want to delete any bids <a href="'.BASE_URL.'public/contact">contact us</a> and we will do so on your behalf<br/>'.
                        'You may also simply generate another bid form. Multiple bid forms are not a problem.<br/>'.
                        'If you want us to break any bidding ties, please <a href="'.BASE_URL.'public/contact">contact us</a> and we will increase your bid by one bidding step, '.
                        'or more if you so choose.<br/>'.
                        'You will be advised after completion of auction.<br/>';
                       
        }


        if($error === '' and $param['notify_higher_bid'] and $data['order']['auction_status'] === 'ACTIVE')  {
            $outbid_no = 0;
            foreach($data['items'] as $item_id => $item) {
                $bids = self::getBestBid($db,$table_prefix,$item['lot_id']);
                if($bids['active_bids']) {
                    if($bids['best_bid']['user_id'] != $data['order']['user_id']) {
                        $outbid_no++;
                        $data['items_show'][$item_id]['bid'] .= ' (there is a higher bid)';
                    }
                }
            }

            if($outbid_no !== 0) $message .= ' '.$outbid_no.' bids are not winning bids. ';
        }

        if($error === '') {
            $mail_from = ''; //will use default MAIL_FROM
            $mail_to = $data['order']['user_email'];

            $mail_subject = SITE_NAME.' '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.'] ';
            $audit_str = MODULE_AUCTION['labels']['order'].' ID['.$order_id.'] ';

            if($subject !== '') $mail_subject .= ': '.$subject;
            
            $mail_body = '<h1>Attention: '.$data['order']['user_name'].'(User ID '.$data['order']['user_id'].')</h1>';
            $mail_body .= '<h2>Auction: '.$data['order']['auction'].'</h2>';

            if($message !== '') $mail_body .= '<h2>'.$message.'</h2>';
            
            //do not want bootstrap class default
            $html_param = ['class'=>''];

            $mail_body .= '<h3>'.MODULE_AUCTION['labels']['order'].' lots:</h3>'.Html::arrayDumpHtml($data['items_show'],$html_param);

            /* Payments lonked to invoices NOT orders
            if($data['payments'] !== 0) {
                $mail_body .= '<h3>Payments</h3>'.Html::arrayDumpHtml($data['payments'],$html_param);
            }
            */
    
            $mail_body .= '<br/><br/>'.$mail_footer;
            
            $mail->sendEmail($mail_from,$mail_to,$mail_subject,$mail_body,$error_tmp,$mail_param);
            if($error_tmp != '') { 
                $error .= 'ERROR sending '.MODULE_AUCTION['labels']['order'].' details to email['. $mail_to.']:'.$error_tmp; 
                $audit_str .=  $error_str;
            } else {
                $audit_str .= 'SUCCESS sending '.MODULE_AUCTION['labels']['order'].' details to email['. $mail_to.']'; 
            }

            Audit::action($db,$user_id,'ORDER_EMAIL',$audit_str);
        }

        if($error === '') return true; else return false;
    }
    
    //create gallery of s3 lot images 
    public static function getLotImageGallery($db,$table_prefix,$s3,$lot_id,$param = [])
    {
        $html = '';

        if(!isset($param['access'])) $param['access'] = MODULE_AUCTION['images']['access'];
        if(!isset($param['storage'])) $param['storage'] = STORAGE;

        $sql = 'SELECT `name`,`description` '.
               'FROM `'.$table_prefix.'lot` '.
               'WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" AND `status` <> "HIDE"';
        $lot = $db->readSqlRecord($sql);
        if($lot === 0) {
            $html = '<h1>lot no longer available.</h1>';
            return $html;
        } else {
            $html .= '<h1>'.$lot['name'].'</h1>';
        }


        $location_id = 'LOT'.$lot_id;
        $sql = 'SELECT `file_id`,`file_name`,`file_name_tn`,`caption` AS `title`,`file_ext` AS `extension` '.
               'FROM `'.$table_prefix.'file` WHERE `location_id` = "'.$db->escapeSql($location_id).'" '.
               'ORDER BY `location_rank` ';
        $images = $db->readSqlArray($sql);
        if($images != 0) {
            //setup amazon links
            if($param['storage'] === 'amazon') {
                foreach($images as $id => $image) {
                    $url = $s3->getS3Url($image['file_name'],['access'=>$param['access']]);
                    $images[$id]['src'] = $url;
                }    
            }

            if($param['storage'] === 'local') {
                foreach($images as $id => $image) {
                    $path = BASE_UPLOAD.UPLOAD_DOCS.$image['file_name'];
                    $url = Image::getImage('SRC',$path,$error);
                    $images[$id]['src'] = $url;
                }
            }    

            if(count($images) == 1) {
                foreach($images as $image) {
                    if(strtolower($image['extension']) === 'mp4') {
                        $html .= '<video width="600" height="400" controls> '.
                                   '<source src="'.$image['src'].'" type="video/mp4"> '.
                                 '</video>'; 
                    } else {
                       $html .= '<img src="'.$image['src'].'" class="img-responsive center-block">'; 
                    }
                        
                }  
            } else {  
                $options = array();
                $options['img_style'] = 'max-height:600px;';
                $options['src_root'] = '';   //only used if NOT stored on AMAZON & image['src'] not defined
                $type = 'CAROUSEL'; //'THUMBNAIL'
                
                $html .= Image::buildGallery($images,$type,$options);
                
            }  
            
        } 

        return $html; 
    }

    public static function getLot($db,$table_prefix,$type,$lot_id,$auction_id)
    {
        $sql = 'SELECT * FROM `'.$table_prefix.'lot` WHERE ';        
        if($type === 'LOT_NO') {
            $sql .= '`auction_id` = "'.$db->escapeSql($auction_id).'" AND `lot_no` = "'.$db->escapeSql($lot_id).'" ';    
        } else {
            $sql .= '`lot_id` = "'.$db->escapeSql($lot_id).'" ';    
        } 
        
        $lot = $db->readSqlRecord($sql);

        return $lot;
    }    
    
    public static function getLotSummary($db,$table_prefix,$s3,$lot_id,$param = [])
    {
        $html = '';
        $error = '';

        $lot_id_display = false;
        $lot_no_display = true;

        if(!isset($param['access'])) $param['access'] = MODULE_AUCTION['images']['access'];
        
        $no_image_src = BASE_URL.'images/no_image.png';

        $sql = 'SELECT `lot_id`,`lot_no`,`name`,`description`,`price_reserve`,`status` '.
               'FROM `'.$table_prefix.'lot` '.
               'WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" AND `status` <> "HIDE"';
        $lot = $db->readSqlRecord($sql);
        if($lot === 0) {
            $html = '<p>lot no longer available.</p>';
            return $html;
        } else {
            $lot_str = '';
            if($lot_id_display) $lot_str .= 'Lot ID['.$lot['lot_id'].'] ';
            if($lot_no_display) $lot_str .= 'Lot No['.$lot['lot_no'].'] ';

            $html .= '&nbsp;<strong>'.$lot['name'].': '.$lot_str.'</strong><br/>'.
                     '&nbsp;Reserve price: '.CURRENCY_SYMBOL.number_format($lot['price_reserve'],2);
        }


        $location_id = 'LOT'.$lot_id;
        $sql = 'SELECT `file_id`,`file_name_tn` AS `file_name`,`file_name_orig` AS `name` '.
               'FROM `'.$table_prefix.'file` WHERE `location_id` = "'.$db->escapeSql($location_id).'" '.
               'ORDER BY `location_rank`, `file_date` DESC LIMIT 1';
        $image = $db->readSqlRecord($sql);
        if($image != 0) {
            if(STORAGE === 'amazon') {
                $url = $s3->getS3Url($image['file_name'],['access'=>$param['access']]);
            }    
            if(STORAGE === 'local') {
                $path = BASE_UPLOAD.UPLOAD_DOCS.$image['file_name'];
                $url = Image::getImage('SRC',$path,$error);
            }


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

        $sql = 'DELETE E FROM `'.TABLE_PREFIX.'user_extend` AS E LEFT JOIN `'.TABLE_USER.'` AS U ON(E.`user_id` = U.`user_id`) '.
               'WHERE U.`name` is NULL ';
        $recs = $db->executeSql($sql,$error);

        return $recs;
    }

    public static function getUserData($db,$ref_type,$ref_value)
    {
        $rec = 0;
        $ref_value = trim($ref_value);

        if($ref_value != '') {
            $where = '';
            if($ref_type === 'USER_ID') $where .= 'U.`user_id` = "'.$db->escapeSql($ref_value).'" ';
            if($ref_type === 'USER_EMAIL') $where .= 'U.`email` = "'.$db->escapeSql($ref_value).'" ';
            if($ref_type === 'BID_NO') $where .= 'E.`bid_no` = "'.$db->escapeSql($ref_value).'" ';

            if($where !== '') {
                $sql = 'SELECT U.`user_id`,U.`name`,U.`email`,U.`access`,E.`extend_id`,E.`name_invoice`,E.`bid_no`,'.
                              'E.`seller_id`,E.`cell`,E.`tel`,E.`email_alt`,E.`bill_address`,E.`ship_address` '.
                       'FROM `'.TABLE_USER.'` AS U LEFT JOIN `'.TABLE_PREFIX.'user_extend` AS E ON(U.`user_id` = E.`user_id`) '.
                       'WHERE '.$where;
                $rec = $db->readSqlRecord($sql); 
                if($rec != 0) {
                    if($rec['name_invoice'] === '' or is_null($rec['name_invoice'])) $rec['name_invoice'] = $rec['name'];
                }   
            }
        }    

        return $rec;
    }

    //NB: Cart is a special case of an order with status = NEW
    //$table_prefix must be passed in as not always called within auction module
    public static function getCart($db,$table_prefix,$temp_token)  
    {
        $error = '';
        $table_auction = $table_prefix.'auction';
        $table_lot = $table_prefix.'lot';
        $table_cart = $table_prefix.'order';
        $table_item = $table_prefix.'order_item';

        $sql = 'SELECT C.`order_id`,C.`auction_id`,C.`date_create`,C.`status`,A.`name` AS `auction` '.
               'FROM `'.$table_cart.'` AS C JOIN `'.$table_auction.'` AS A ON(C.`auction_id` = A.`auction_id`) '.
               'WHERE C.`temp_token` = "'.$db->escapeSql($temp_token).'" AND C.`status` = "NEW" ';
        $cart = $db->readSqlRecord($sql);

        if($cart !== 0 ) {
            $sql = 'SELECT I.`item_id`,I.`lot_id`,L.`lot_no`,L.`name`,I.`price`,L.`price_reserve`,I.`status`,L.`weight`,L.`volume` '.
                   'FROM `'.$table_item.'` AS I LEFT JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
                   'WHERE I.`order_id` = "'.$cart['order_id'].'" ';
            $cart['items'] = $db->readSqlArray($sql);

            $sql = 'SELECT SUM(`price`) AS `total`,COUNT(*) AS `no_items` '.
                   'FROM `'.$table_item.'` '.
                   'WHERE `order_id` = "'.$cart['order_id'].'" ';
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
        $sql = 'SELECT SUM(`price`) AS `total`,COUNT(*) AS `no_items` '.
               'FROM `'.$table.'` '.
               'WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
        $totals = $db->readSqlRecord($sql);
        
        if($totals === 0) {
            unset($totals);
            $totals['total'] = 0.00;
            $totals['no_items'] = 0;
        }

        return $totals;
    }

    //NB: recalculates all item and cart totals based on latest lot data, ONLY call BEFORE order finalised.
    public static function calcCartTotals($db,$table_prefix,$temp_token,$ship_option_id,$ship_location_id,$pay_option_id,&$error)  
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
            $cart_update['pay_option_id'] = $pay_option_id;
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


    public static function getInvoiceStatusText($status)
    {
        $text = '';
        switch($status) {
            case 'OK': $text = 'Invoice is valid but has not been paid yet.'; break;
            case 'PAID': $text = 'Invoice payment received'; break;
            case 'BAD_DEBT': $text = 'Invoice has written off as a bad debt.'; break;
            default: $text = 'Unrecognised invoice status['.$status.']';
        }

        return $text;
    }

    public static function getInvoiceDetails($db,$table_prefix,$invoice_id,&$error)
    {
        $error = '';
        $output = [];
        
        
        $table_invoice = $table_prefix.'invoice';
        $table_item = $table_prefix.'invoice_item';
        $table_auction = $table_prefix.'auction';
        $table_lot = $table_prefix.'lot';
        $table_order = $table_prefix.'order';
        $table_payment = $table_prefix.'payment';
        
        //$table_ship_location = $table_prefix.'ship_location';
        //$table_ship_option = $table_prefix.'ship_option';
        //$table_payment_option = $table_prefix.'pay_option';

        $sql = 'SELECT I.`invoice_id`,I.`invoice_no`,I.`date`,I.`status`,I.`sub_total`,I.`tax`,I.`total`,I.`comment`,I.`status`,'.
                      'I.`auction_id`,A.`name` AS `auction`, A.`status` AS `auction_status`, '.
                      'I.`user_id`,U.`name` AS `user_name`, U.`email` AS `user_email`, '.
                      'I.`order_id`, O.`ship_location_id`, O.`ship_option_id`, O.`pay_option_id`  '.
               'FROM `'.$table_invoice.'` AS I '.
                     'JOIN `'.$table_auction.'` AS A ON(I.`auction_id` = A.`auction_id`) '.
                     'LEFT JOIN `'.TABLE_USER.'` AS U ON(I.`user_id` = U.`user_id`) '.
                     'LEFT JOIN `'.$table_order.'` AS O ON(I.`order_id` = O.`order_id`) '.
               'WHERE I.`Invoice_id` = "'.$db->escapeSql($invoice_id).'" ';
        $invoice = $db->readSqlRecord($sql);
        if($invoice === 0) {
            $error .= 'Invalid auction invoice ID['.$invoice_id.']. ';
        } else {
            $output['invoice'] = $invoice;
        }

        //ALL invoice items including discounts, tax, commission etc
        $sql = 'SELECT I.`item_id`,I.`item` AS `name`,I.`price`,I.`quantity`,I.`total` '.
               'FROM `'.$table_item.'` AS I  '.
               'WHERE I.`invoice_id` = "'.$db->escapeSql($invoice_id).'" '.
               'ORDER BY I.`item_id` ';
        $items = $db->readSqlArray($sql);
        if($items === 0) {
            $error .= 'Invalid or No items for Invoice ID['.$invoice_id.']. ';
        } else {
            $output['items'] = $items;
        }

        //NB: returns ONLY Lots due to JOIN
        $sql = 'SELECT I.`lot_id`,L.`lot_no`,L.`name`,I.`price`,I.`quantity`,I.`total` '.
               'FROM `'.$table_item.'` AS I JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
               'WHERE I.`invoice_id` = "'.$db->escapeSql($invoice_id).'" '.
               'ORDER BY I.`item_id` ';
        $lots = $db->readSqlArray($sql);
        if($lots === 0) {
            $output['no_lots'] = 0;
            //$error .= 'Invalid or no lots for Invoice ID['.$invoice_id.']. ';
        } else {
            $output['lots'] = $lots;
            $output['no_lots'] = count($lots);
        }
        
        //get all existing payment data and total paid
        $sql = 'SELECT  `date_create`,`amount`,`status` '.
               'FROM `'.$table_payment.'` WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" ';
        $output['payments'] = $db->readSqlArray($sql);

        $sql = 'SELECT  SUM(`amount`)'.
               'FROM `'.$table_payment.'` WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" ';
        $output['payment_total'] = $db->readSqlValue($sql,0);
        

        if($error !== '') return false; else return $output;
    }  

    public static function sendInvoicePaymentMessage($db,$table_prefix,ContainerInterface $container,$invoice_id,$subject,$message,$param=[],&$error)
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
       
        $data = self::getInvoiceDetails($db,$table_prefix,$invoice_id,$error_tmp);
        if($data === false or $error_tmp !== '') {
            $error .= 'Could not get Invoice details: '.$error_tmp;
        } else {
            if($data['invoice']['user_id'] == 0 or $data['invoice']['user_email'] === '') $error .= 'No user data linked to invoice';

            
        }    

        if($error === '') {
            $mail_from = ''; //will use default MAIL_FROM
            $mail_to = $data['invoice']['user_email'];
 
            $mail_subject = SITE_NAME.' Invoice No['.$data['invoice']['invoice_no'].'] ';

            if($subject !== '') $mail_subject .= ': '.$subject;
            
            $mail_body = '<h1>Hi there '.$data['invoice']['user_name'].'</h1>';
            
            if($message !== '') $mail_body .= '<h3>'.$message.'</h3>';
            
            //do not want bootstrap class default
            $html_param = ['class'=>''];

            $mail_body .= '<h3>Invoice items:</h3>'.Html::arrayDumpHtml($data['items'],$html_param);
            $mail_body .= 'Sub Total: '.$data['invoice']['sub_total'].'<br/>'.
                          'Tax: '.$data['invoice']['tax'].'<br/>'.
                          '<strong>Total: '.CURRENCY_SYMBOL.$data['invoice']['total'].'</strong><br/>';

            if($data['payments'] !== 0) {
                $total_due = $data['invoice']['total'] - $data['payment_total'];

                $mail_body .= '<h3>Less Payments</h3>'.Html::arrayDumpHtml($data['payments'],$html_param);
                $mail_body .= '<h3>Total due: '.CURRENCY_SYMBOL.$total_due.'</h3>';
            }

            
                
            $mail_body .= '<br/><br/>'.$mail_footer;
            
            $mail->sendEmail($mail_from,$mail_to,$mail_subject,$mail_body,$error_tmp,$mail_param);
            if($error_tmp != '') { 
                $error .= 'Error sending Invoice details to email['. $mail_to.']:'.$error_tmp; 
            }
        }

        if($error === '') return true; else return false;
    }


    //called from payment module code after a transaction SUCCESSFULLY confirmed or notified
    public static function paymentGatewayInvoiceUpdate($db,$table_prefix,$invoice_id,$amount,&$error) 
    {
        $error = '';
        $table_invoice = $table_prefix.'invoice';
        $table_payment = $table_prefix.'payment';

        //check if payment exists
        $sql = 'SELECT `payment_id`,`date_create`,`status` '.
               'FROM `'.$table_payment.'` '.
               'WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" AND `amount` = "'.$db->escapeSql($amount).'" ';
        $payment = $db->readSqlRecord($sql); 
        if($payment != 0) {
            $error .= 'Auction invoice['.$invoice_id.'] already has a payment amount['.$amount.'] @ '.$payment['date_create'];
        } else {
            $data = [];
            $data['invoice_id'] = $invoice_id;
            $data['date_create'] = date('Y-m-d H:i:s');
            $data['amount'] = $amount;
            $data['status'] = 'CONFIRMED';

            $payment_id = $db->insertRecord($table_payment,$data,$error);
            if($error === '') {
                //SEND SOME MESSAGE TO USER?
            }
        }  

        //update invoice status if all paid up
        if($error === '') {
            self::updateInvoiceStatus($db,$table_prefix,$invoice_id,$error);
        } 

    } 

    public static function updateInvoiceStatus($db,$table_prefix,$invoice_id,&$error)
    {
        $error = '';
        $table_invoice = $table_prefix.'invoice';
        $table_payment = $table_prefix.'payment';

        $sql = 'SELECT * FROM '.$table_invoice.' WHERE invoice_id = "'.$db->escapeSql($invoice_id).'" ';
        $invoice = $db->readSqlRecord($sql);
        if($invoice == 0) {
            $error .= 'Invalid invoice ID['.$invoice_id.']';
        } else {
            $sql = 'SELECT SUM(`amount`) FROM `'.$table_payment.'` WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" AND `status` = "CONFIRMED" ';
            $total_confirmed = $db->readSqlValue($sql,0);
        }

        if($error === '') {
            //echo 'WTF'.$total_confirmed.'for '.$invoice['total'];
            if($total_confirmed - $invoice['total'] > -1.00) $paid_up = true; else $paid_up = false;
            if($paid_up and $invoice['status'] !== 'SHIPPED' and $invoice['status'] !== 'COMPLETED' ) {
                $sql = 'UPDATE `'.$table_invoice.'` SET `status` = "PAID" WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" ';
                //die($sql);
                $db->executeSql($sql,$error);
                if($error === '') {
                    //SEND SOME MESSAGE TO USER?
                }
            }
        }
    }

    public static function getUnpaidInvoices($db,$table_prefix,$user_id)
    {
        $error = '';
        $table_invoice = $table_prefix.'invoice';
        $table_payment = $table_prefix.'payment';
        $table_file = $table_prefix.'file';

        $output = [];

        //check if payment gateway active
        $payment_gateway = MODULE_AUCTION['access']['payment'];
        

        $sql = 'SELECT `invoice_id`,`invoice_no`,`total`,`date` FROM `'.$table_invoice.'` '.
               'WHERE `user_id` = "'.$db->escapeSql($user_id).'" AND `status` = "OK" ORDER BY `date` DESC';
        $invoices = $db->readSqlArray($sql);
        $html = '';
        if($invoices == 0) {
            $html .= 'No outstanding invoices found.';
        } else {
            $html .= '<ul>';
            foreach($invoices as $invoice_id=>$invoice) {
                $sql = 'SELECT SUM(`amount`) FROM `'.$table_payment.'` WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'" AND `status` = "CONFIRMED" ';
                $total_confirmed = $db->readSqlValue($sql,0);
                $total_due = $invoice['total'] - $total_confirmed;

                $location_id = 'INV'.$invoice_id;
                //should only be one file linked to invoice rec
                $sql = 'SELECT `file_id` AS `id`,`file_name_orig` AS `name` '.
                       'FROM `'.$table_file.'` WHERE `location_id` = "'.$location_id.'" LIMIT 1';
                $file_rec = $db->readSqlRecord($sql);


                $html .= '<li>';
                if($file_rec == 0) {
                    $name = $invoice['invoice_no'];
                } else {
                    $name = '<a href="account_file?mode=download&id='.$file_rec['id'].'" Target="_blank">'.$invoice['invoice_no'].'</a>';
                }    
                $html .= 'Invoice '.$name.'  issued on '.Date::formatDate($invoice['date']).', ';    

                if($payment_gateway) {
                    $html .= '<a href="payment_wizard?id='.$invoice_id.'" target="_blank">click to pay balance['.CURRENCY_SYMBOL.$total_due.']</a>';
                } else {
                    $html .= 'balance due['.CURRENCY_SYMBOL.$total_due.']';
                }
                
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $output['html'] = $html;

        
        return $output;
    }  

    //NB: recalculates all item and cart totals based on latest lot data, ONLY call BEFORE invoice finalised.
    //**** not finished & not used anywhere***
    public static function calcInvoiceTotals($db,$table_prefix,$temp_token,$ship_option_id,$ship_location_id,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $output = [];

        $table_cart = $table_prefix.'order';
        $table_ship = $table_prefix.'ship_cost';
        $table_item = $table_prefix.'order_item';
        $table_lot = $table_prefix.'lot';

        //NB: THIS WILL ONLY WORK ON CART AND NOT ORDERS
        $cart = Helpers::getCart($db,$table_prefix,$temp_token);
        if($cart === 0) {
            $error .= 'Cart has expired';
        } else { 
            $order_id = $cart['order_id'];

            $sql = 'SELECT I.`item_id`,I.`price`,L.`name`,L.`tax`,L.`weight`,L.`volume` '.
                   'FROM `'.$table_item.'` AS I LEFT JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
                   'WHERE `order_id` = "'.$db->escapeSql($order_id).'" ';
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
            $sql = 'SELECT `cost_free`,`cost_max`,`cost_base`,`cost_weight`,`cost_volume`,`cost_item` FROM `'.$table_ship .'` '.
                   'WHERE `option_id` = "'.$db->escapeSql($ship_option_id).'" AND `location_id` = "'.$db->escapeSql($ship_location_id).'" ';
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

    //NB: user_id = 0 if user not logged in, but temp token must always be set
    public static function setupOrder($db,$table_prefix,$temp_token,$user_id,$auction_id,&$error)  
    {
        $error = '';
        $table = $table_prefix.'order';


        $sql = 'SELECT `order_id`,`auction_id`,`date_create` FROM `'.$table.'` '.
               'WHERE `temp_token` = "'.$db->escapeSql($temp_token).'" AND `status` = "NEW" ';
        $order = $db->readSqlRecord($sql);
        if($order === 0) {
            $data = [];
            $data['date_create'] = date('Y-m-d H:i:s');
            $data['status'] = 'NEW';
            $data['temp_token'] = $temp_token;
            $data['auction_id'] = $auction_id;
            $data['user_id'] = $user_id;

            $order_id = $db->insertRecord($table,$data,$error); 
        } else {
            if($order['auction_id'] !== $auction_id) {
                $error = 'Your auction '.MODULE_AUCTION['labels']['order'].' cart is currently in use for another auction. Complete that '.MODULE_AUCTION['labels']['order'].' first, or delete current '.MODULE_AUCTION['labels']['order'].' cart contents.';
            } else {
                $order_id = $order['order_id'];
            }

        }

        return $order_id;
    }

    public static function addOrderItem($db,$table_prefix,$temp_token,$user_id,$form,&$error) 
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
            $sql = 'SELECT `lot_id`,`auction_id`,`name`,`status`,`price_reserve`,`weight`,`volume` '.
                   'FROM `'.$table_prefix.'lot` '.
                   'WHERE `lot_id` = "'.$db->escapeSql($lot_id).'" ';
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
            $order_id = self::setupOrder($db,$table_prefix,$temp_token,$user_id,$lot['auction_id'],$error_tmp);
            if($error_tmp !== '') {
                $error .= 'Could not setup order:'.$error_tmp;
                $message .= 'Could not add lot: '.$error_tmp;
            }    
            
            if($error === '') {
                $sql = 'SELECT `item_id` FROM `'.$table_prefix.'order_item` '.
                       'WHERE `order_id` = "'.$db->escapeSql($order_id).'" AND '.
                             '`lot_id` = "'.$db->escapeSql($lot_id).'" ';
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
                    $error .= 'Could not update '.MODULE_AUCTION['labels']['order'].' item: '.$error_tmp;
                    $message .= 'Could not save '.MODULE_AUCTION['labels']['order'].' item. ';
                }  else {
                    $message .= $lot['name'].': Successfuly added to your '.MODULE_AUCTION['labels']['order'].'. ';
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
