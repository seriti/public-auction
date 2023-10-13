<?php 
namespace App\Auction;

use Exception;
use Seriti\Tools\Secure;
use Seriti\Tools\Crypt;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Image;
use Seriti\Tools\Pdf;
use Seriti\Tools\Csv;
use Seriti\Tools\Date;
use Seriti\Tools\Doc;

use Seriti\Tools\MAIL_FROM;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\TABLE_AUDIT;
use Seriti\Tools\TABLE_USER;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\UPLOAD_TEMP;

use Psr\Container\ContainerInterface;

use App\Auction\Helpers;


//static functions for auction module
class HelpersReport {

    //show all bids received online ranked by price and time for use by live auctioneer.
    public static function allBidsReport($db,$auction_id,$options = [],&$doc_name,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';

        $pdf_override = true;
        $lot_separate_page = false;
                
        $doc_dir = BASE_UPLOAD.UPLOAD_DOCS;
                
        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'PDF';
        $options['format'] = strtoupper($options['format']);
        
        if($auction_id === 'ALL') {
            $error .= 'Cannot run for ALL auctions. Please select an individual auction.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) $error .= 'Invalid Auction['.$auction_id.'] selected.';
        }    
        
        if($error !== '') return false;

        $doc_name_base = 'all_bids_auction_'.str_replace(' ','_',$auction['name']).'_'.date('Y-m-d');

        $table_lot = TABLE_PREFIX.'lot';
        $table_order = TABLE_PREFIX.'order';
        $table_item = TABLE_PREFIX.'order_item';
        $lot_data = [];


        $sql = 'SELECT I.`item_id`,L.`lot_no`,I.`lot_id`,L.`name` AS lot_name,O.`date_create`,U.`name` AS user_name,O.`user_id`,I.`price`,I.`status` '.
               'FROM `'.$table_item.'` AS I JOIN `'.$table_order.'` AS O ON(I.`order_id` = O.`order_id`) '.
                    'JOIN `'.TABLE_USER.'` AS U ON(O.`user_id` = U.`user_id`) '.
                    'JOIN `'.$table_lot.'` AS L ON(I.`lot_id` = L.`lot_id`) '.
               'WHERE O.`auction_id` = "'.$db->escapeSql($auction_id).'" AND O.`user_id` <> 0  '.
               'ORDER BY L.`lot_no`,I.`price` DESC, O.`date_create` ';
        
        $bids = $db->readSqlArray($sql);
        if($bids == 0) $error .= 'No online bids found for auction.';

        if($error !== '') return false;
        
        if($options['format'] === 'PDF') {

            $pdf_options = [];
            $pdf_options['header_align'] = 'L'; 

            $doc_name = $doc_name_base.'.pdf';
            
            $pdf = new Pdf('Portrait','mm','A4');
            $pdf->AliasNbPages();
                
            $pdf->setupLayout(['db'=>$db]);

            //NB:override PDF settings to economise on space (no logo, small margins,size-6 text)
            if($pdf_override) {
                $pdf->bg_image = array('images/logo.jpeg',5,140,50,20,'YES'); //NB: YES flag turns off logo image display
                $pdf->page_margin = array(10,10,10,10);//top,left,right,bottom!!
                $pdf->text = array(33,33,33,'',8);
                $pdf->h1_title = array(33,33,33,'B',10,'',8,20,'L','YES',33,33,33,'B',12,20,180);
            }
            
            //NB footer must be set before this
            $pdf->AddPage();

            $row_h = 5;

            $pdf->changeFont('H1');
            $pdf->Cell(60,$row_h,'All lot bids for :',0,0,'R',0);
            $pdf->Cell(60,$row_h,$auction['name'],0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);

            
            $lot_id = ''; 
            $header = [];
            $row = 0;
            
            $col_width = array(20,40,40,40);
            $col_type  = array('','','','CASH0'); 
           
            $header[0][$row] = 'User ID';
            $header[1][$row] = 'User Name';
            $header[2][$row] = 'Bid date & time';
            $header[3][$row] = 'Bid Price';
        
                    
            foreach($bids as $bid_id => $bid) {

                if($bid['lot_id'] !== $lot_id) {
                    if($lot_id !== '') {
                        //all buyer invoice lots
                        if($lot_separate_page) $pdf->AddPage();
                        $pdf->changeFont('H1');
                        $pdf->Cell(20,$row_h,'Lot:',0,0,'R',0);
                        $pdf->Cell(20,$row_h,$lot_no.' ('.substr(utf8_decode($lot_name),1,32).')',0,0,'L',0);
                        $pdf->Cell(80,$row_h,'FINAL BID :',0,0,'R',0);
                        $pdf->Cell(80,$row_h,'Buyer No.[    ] or ID [    ]',0,0,'L',0);
                        $pdf->Ln($row_h);
                        $pdf->changeFont('TEXT');
                        $pdf->arrayDrawTable($lot_data,$row_h,$col_width,$col_type,'L',$pdf_options);
                        $pdf->ln($row_h);
                    }
                    

                    $row = 0;
                    $lot_data = $header;
                    $lot_id = $bid['lot_id'];
                    $lot_no = $bid['lot_no'];
                    $lot_name = $bid['lot_name'];
                } 

                $row++;
                $lot_data[0][$row] = $bid['user_id'];
                $lot_data[1][$row] = $bid['user_name'];
                $lot_data[2][$row] = Date::formatDateTime($bid['date_create']);
                $lot_data[3][$row] = $bid['price'];
            }

            if($lot_separate_page) $pdf->AddPage();
            $pdf->changeFont('H1');
            $pdf->Cell(20,$row_h,'Lot :',0,0,'R',0);
            $pdf->Cell(20,$row_h,$lot_no.' ('.substr(utf8_decode($lot_name),1,32).')',0,0,'L',0);
            $pdf->Cell(80,$row_h,'FINAL BID :',0,0,'R',0);
            $pdf->Cell(80,$row_h,'Buyer No.[    ] or ID [    ]',0,0,'L',0);
            
            $pdf->Ln($row_h);
            $pdf->changeFont('TEXT');
            $pdf->arrayDrawTable($lot_data,$row_h,$col_width,$col_type,'L',$pdf_options);
            $pdf->ln($row_h); 

            //finally create pdf file
            if($options['output'] === 'FILE') {
                $file_path = $doc_dir.$doc_name;
                $pdf->Output($file_path,'F');  
            }    

            //send directly to browser
            if($options['output'] === 'BROWSER') {
                $pdf->Output($doc_name,'D');
                exit();      
            }    
        } 

        if($options['format'] === 'HTML') {
            $html = Html::arrayDumpHtml($bids,['show_key'=>false]);
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';

            $doc_name = $doc_name_base.'.csv';
            //dumps key by default unlike html version
            $csv_data = Csv::sqlArrayDumpCsv('ID',$bids);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }               

        return $html;
    }

    public static function AuctionStatistics($db,$auction_id,$options = [],&$error)  
    {
        $html = '';
        $error = '';
        $stats = [];

        $table_seller = TABLE_PREFIX.'seller';
        $table_lot = TABLE_PREFIX.'lot';
        $table_auction = TABLE_PREFIX.'auction';
        $table_condition = TABLE_PREFIX.'condition';
        $table_category = TABLE_PREFIX.'category';

                                
        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'HTML';
        $options['format'] = strtoupper($options['format']);

        if(!isset($options['layout'])) $options['layout'] = 'COMPRESSED';

        if($options['format'] !== 'HTML') {
            $error .= 'Only HTML on page format supported for this report';
        }

        if($auction_id === 'ALL') {
            $error .= 'Cannot generate document for ALL auctions.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`postal_only`,`date_start_postal`,`date_end_postal`,`date_start_live`,`status` '.
                   'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            } else {
                $sql = 'SELECT COUNT(*) AS `lot_count`,SUM(L.`price_reserve`) AS `reserve`,SUM(L.`price_estimate`) AS `estimate`,SUM(L.`bid_final`) AS `bid` '.
                       'FROM `'.$table_lot.'` AS L '.
                       'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" ';
                $stats = $db->readSqlRecord($sql);
                if($stats == 0) $error .= 'No lots found for auction!';
            }    
        }

        if($error !== '') return false;
       

        if($options['format'] === 'ARRAY') return $stats;

        if($options['format'] === 'HTML') {
            $html .= '<H1>'.$auction['name'].': statistics</H1>';
            $html .= '<h2>Total Lots: '.number_format($stats['lot_count'],0).'</h2>';    
            $html .= '<h2>Total reserve value: '.number_format($stats['reserve'],2).'</h2>';
            $html .= '<h2>Total estimate value: '.number_format($stats['estimate'],2).'</h2>'; 
            $html .= '<h2>Total bid value: '.number_format($stats['bid'],2).'</h2>';

            return $html;       
        }
    }    

    //get lists of successful bids for each buyer so can prepare for invoicing
    public static function buyerInvoiceLotsReport($db,$auction_id,$options = [],&$doc_name,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';

        $pdf_override = true;
        $buyer_separate_page = true;
        
        $doc_dir = BASE_UPLOAD.UPLOAD_DOCS;
                
        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'PDF';
        $options['format'] = strtoupper($options['format']);
        
        if($auction_id === 'ALL') {
            $error .= 'Cannot run for ALL auctions. Please select an individual auction.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) $error .= 'Invalid Auction['.$auction_id.'] selected.';
        }    
        
        if($error !== '') return false;

        $doc_name_base = 'buyer_invoice_lots_auction_'.$auction_id.'_'.date('Y-m-d');

        $sql = 'SELECT L.`lot_id`,L.`buyer_id`,L.`lot_no`,L.`name`,L.`bid_final`,L.`weight`,L.`volume`,L.`status`,U.`name` AS `buyer_name` '.
               'FROM `'.TABLE_PREFIX.'lot` AS L JOIN `'.TABLE_USER.'` AS U ON(L.`buyer_id` = U.`user_id`) '.
               'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" AND L.`buyer_id` > 0 '.
               'ORDER BY L.`buyer_id`,L.`lot_no` ';
                    
        $lots = $db->readSqlArray($sql);
        if($lots == 0) $error .= 'No auction lots found with a linked buyer id.';

        if($error !== '') return false;
        
        if($options['format'] === 'PDF') {

            $pdf_options = [];
            $pdf_options['header_align'] = 'L'; 

            $doc_name = $doc_name_base.'.pdf';
            
            $pdf = new Pdf('Portrait','mm','A4');
            $pdf->AliasNbPages();
                
            $pdf->setupLayout(['db'=>$db]);

            //NB:override PDF settings to economise on space (no logo, small margins,size-6 text)
            if($pdf_override) {
                $pdf->bg_image = array('images/logo.jpeg',5,140,50,20,'YES'); //NB: YES flag turns off logo image display
                $pdf->page_margin = array(10,10,10,10);//top,left,right,bottom!!
                $pdf->text = array(33,33,33,'',8);
                $pdf->h1_title = array(33,33,33,'B',10,'',8,20,'L','YES',33,33,33,'B',12,20,180);
            }
            
            //NB footer must be set before this
            $pdf->AddPage();

            $row_h = 5;

            $pdf->changeFont('H1');
            $pdf->Cell(60,$row_h,'Buyer invoice lots for :',0,0,'R',0);
            $pdf->Cell(60,$row_h,$auction['name'],0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);

            
            $buyer_id = ''; 
            $header = [];
            $row = 0;
            
            $col_width = array(20,20,80,30,20,20);
            $col_type  = array('','','','CASH0','',''); 
           
            $header[0][$row] = 'Lot No.';
            $header[1][$row] = 'Lot ID';
            $header[2][$row] = 'Name';
            $header[3][$row] = 'Final bid';
            $header[4][$row] = 'Weight';
            $header[5][$row] = 'Volume';
            
            foreach($lots as $lot_id => $lot) {

                if($lot['buyer_id'] !== $buyer_id) {
                    if($buyer_id !== '') {
                        //all buyer invoice lots
                        if($buyer_separate_page) $pdf->AddPage();
                        $pdf->changeFont('H1');
                        $pdf->Cell(20,$row_h,'Buyer :',0,0,'R',0);
                        $pdf->Cell(20,$row_h,$buyer_name,0,0,'L',0);
                        $pdf->Ln($row_h);
                        $pdf->changeFont('TEXT');
                        $pdf->arrayDrawTable($buyer_data,$row_h,$col_width,$col_type,'L',$pdf_options);
                        $pdf->ln($row_h);
                    }
                    

                    $row = 0;
                    $buyer_data = $header;
                    $buyer_id = $lot['buyer_id'];
                    $buyer_name = $lot['buyer_name'].'(ID='.$buyer_id.')';
                } 

                $row++;
                $buyer_data[0][$row] = $lot['lot_no'];
                $buyer_data[1][$row] = $lot_id;
                $buyer_data[2][$row] = utf8_decode($lot['name']);
                $buyer_data[3][$row] = $lot['bid_final'];
                if($lot['weight'] == 0) $str = ''; else $str = $lot['weight'];
                $buyer_data[4][$row] = $str;
                if($lot['volume'] == 0) $str = ''; else $str = $lot['volume'];
                $buyer_data[5][$row] = $str; 
            }

            if($buyer_separate_page) $pdf->AddPage();
            $pdf->changeFont('H1');
            $pdf->Cell(20,$row_h,'Buyer :',0,0,'R',0);
            $pdf->Cell(20,$row_h,$buyer_name,0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->changeFont('TEXT');
            $pdf->arrayDrawTable($buyer_data,$row_h,$col_width,$col_type,'L',$pdf_options);
            $pdf->ln($row_h); 

            //finally create pdf file
            if($options['output'] === 'FILE') {
                $file_path = $doc_dir.$doc_name;
                $pdf->Output($file_path,'F');  
            }    

            //send directly to browser
            if($options['output'] === 'BROWSER') {
                $pdf->Output($doc_name,'D');
                exit();      
            }    
        } 

        if($options['format'] === 'HTML') {
            $html = Html::arrayDumpHtml($lots,['show_key'=>false]);
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';

            $doc_name = $doc_name_base.'.csv';
            //dumps key by default unlike html version
            $csv_data = Csv::sqlArrayDumpCsv('ID',$lots);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }               

        return $html;
    }

    //creates index array of term => array of lot ids
    //NB: orders by lot_id not category, so do not use for catelogue
    public static function buildAuctionIndex($db,$auction_id,$options = [])  
    {
        $index = [];

        $sql = 'SELECT `lot_id`,`index_terms` '.
               'FROM `'.TABLE_PREFIX.'lot` '.
               'WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" AND `index_terms` <> "" '.
               'ORDER BY `lot_id` ';

        $lots = $db->readSqlList($sql);
        if($lots !== 0) {
            foreach($lots as $lot_id => $terms) {
                $arr = explode(',',$terms);

                foreach($arr as $term) {
                    $term = trim($term);
                    $index[$term][] = $lot_id;
                }
            }   
        }
        
        return $index;

    }


    public static function calcSellerCommission($base,$pct,$value)  
    {
        $comm = 0;

        $comm = round(($value * $pct / 100),0);
        if($base > $comm) $comm = $base;

        return $comm;
    }

    public static function createAuctionSellerPdf($db,$system,$auction_id,$seller_id,$options = [],&$doc_name,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $doc_name = '';

        $table_seller = TABLE_PREFIX.'seller';
        $table_lot = TABLE_PREFIX.'lot';
        $table_auction = TABLE_PREFIX.'auction';
        $table_condition = TABLE_PREFIX.'condition';
        $table_category = TABLE_PREFIX.'category';

        $category_header = false;
        $lot_no_display = true;

        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'PDF';
        $options['format'] = strtoupper($options['format']);

        if(!isset($options['layout'])) $options['layout'] = 'STANDARD'; // REALISED,MASTER

        if($options['format'] !== 'PDF' and $options['format'] !== 'CSV') {
            $error .= 'Only PDF and CSV format supported for this report. ';
        }

        if($auction_id === 'ALL') {
            $error .= 'Cannot generate auction seller document for ALL auctions.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) $error .= 'Invalid Auction['.$auction_id.'] selected.';
        }

        
        $sql = 'SELECT `seller_id`,`name`,`cell`,`tel`,`email`,`address`,`status`,`comm_pct`,`comm_base` '.
               'FROM `'.$table_seller.'` WHERE `seller_id` = "'.$db->escapeSql($seller_id).'"';
        $seller = $db->readSqlRecord($sql,$db); 
        if($seller === 0) $error .= 'Invalid Seller['.$seller_id.'] selected.';

        if($error === '') {
            $sql = 'SELECT L.`lot_id`,L.`lot_no`,L.`category_id`,L.`index_terms`,L.`name`,L.`description`,'.
                          'L.`price_reserve`,L.`price_estimate`,L.`postal_only`, '.                
                          'L.`bid_open`,L.`bid_book_top`,L.`bid_final`,L.`status`, '.  
                          'CT.`level` AS `cat_level`,CT.`title` AS `cat_name`,CN.`name` AS `condition` '.
                   'FROM `'.$table_lot.'` AS L '.
                         'JOIN `'.$table_condition.'` AS CN ON(L.`condition_id` = CN.`condition_id`) '.
                         'JOIN `'.$table_category.'` AS CT ON(L.`category_id` = CT.`id`) '.
                   'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" AND L.`seller_id` = "'.$db->escapeSql($seller_id).'" '.
                   'ORDER BY CT.`rank`,L.`type_txt1`,L.`type_txt2`,CN.`sort` ';
            $lots = $db->readSqlArray($sql);
            if($lots == 0) $error .= 'No lots found for seller in auction!';
        }
  
        if($error !== '') return false;   



        $doc_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        
        //get setup options
        $footer = $system->getDefault('AUCTION_CATALOGUE_FOOTER','');
        $signature = $system->getDefault('AUCTION_SIGN','');
        $signature_text = $system->getDefault('AUCTION_SIGN_TXT','');
        
        $doc_name_base = 'seller_'.$seller_id.'_auction_'.$auction_id.'_'.str_replace(' ','_',$auction['name']).date('Y-m-d');
        
        $total_sold = 0;
        $total_fee = 0;

        //each seller has own commision rate
        $comm_base = $seller['comm_base'];
        $comm_pct = $seller['comm_pct'];

        //temporary override for historical auctions 
        if($auction_id <= 14) $comm_base = 0;
        
        $comm_str = $comm_pct.'%';
        if($comm_base > 0.00) $comm_str .= ' (min '.$comm_base.' per lot)';

        //lot block setup
        $pdf_options = [];
        $pdf_options['header_align'] = 'L'; 
        
        if($options['format'] === 'PDF') {

            $doc_name = $doc_name_base.'.pdf';
            
            $pdf = new Pdf('Portrait','mm','A4');
            $pdf->AliasNbPages();
                
            $pdf->setupLayout(['db'=>$db]);
            
            //NB footer must be set before this
            $pdf->AddPage();

            $row_h = 5;

            $pdf->SetY(40);
            $pdf->changeFont('H1');
            
            $pdf->Cell(100,$row_h,'Auction :',0,0,'R',0);
            $pdf->Cell(100,$row_h,$auction['name'],0,0,'L',0);
            $pdf->Ln($row_h);
            
            $pdf->Cell(100,$row_h,'Seller :',0,0,'R',0);
            $str = $seller['name'].' ('.$seller['seller_id'].')';
            $pdf->Cell(100,$row_h,$str,0,0,'L',0);
            $pdf->Ln($row_h);
            
            $pdf->Cell(100,$row_h,'Fee structure :',0,0,'R',0);
            $pdf->Cell(100,$row_h,$comm_str,0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);

            
            $category = ''; 
            $cat_data_initial = [];
            $row = 0;

            
            $col_width = array(10,20,60,20,20,20,20);
            $col_type  = array('','','','CASH0','CASH0','CASH0','CASH0'); 
           
            $cat_data_initial[0][$row] = 'Lot';
            $cat_data_initial[1][$row] = 'Category';
            $cat_data_initial[2][$row] = 'Description';
            $cat_data_initial[3][$row] = 'Reserve';
            $cat_data_initial[4][$row] = 'Estimate';
            $cat_data_initial[5][$row] = 'Sold';
            $cat_data_initial[6][$row] = 'Fee';
            
            foreach($lots as $lot_id => $lot) {

                if($lot['cat_name'] !== $category and $lot['cat_level'] === '1') {
                    if($category !== '') {
                        //top level category header
                        if($category_header) {
                            $pdf->Ln($row_h);
                            $pdf->changeFont('H1');
                            $pdf->Cell(0,$row_h,$category.':',0,0,'L',0);
                            $pdf->Ln($row_h);
                        }
                        //all lots in category
                        $pdf->changeFont('TEXT');
                        $pdf->arrayDrawTable($cat_data,$row_h,$col_width,$col_type,'L',$pdf_options);
                        if($category_header) $pdf->ln($row_h);
                    }
                    

                    $row = 0;
                    $cat_data = $cat_data_initial;
                    $category = $lot['cat_name'];
                } 

                $row++;
                if($lot['postal_only']) $postal = '(***Postal only***)'; else $postal = '';
                if($lot_no_display) $lot_str = $lot['lot_no']; else $lot_str = $lot_id;
                $cat_data[0][$row] = $lot_str;
                $cat_data[1][$row] = $lot['cat_name'];
                //$cat_data[2][$row] = utf8_decode($lot['name'].': '.$lot['description']).$postal;
                $cat_data[2][$row] = utf8_decode($lot['name']).$postal;
                $cat_data[3][$row] = CURRENCY_SYMBOL.$lot['price_reserve'];
                $cat_data[4][$row] = CURRENCY_SYMBOL.$lot['price_estimate']; 
                
                if($lot['status'] === 'SOLD' or $lot['bid_final'] > 0) {
                    $sold = $lot['bid_final']; 
                    $sold_str = CURRENCY_SYMBOL.$sold;
                    $fee = self::calcSellerCommission($comm_base,$comm_pct,$lot['bid_final']); 
                    $fee_str =  CURRENCY_SYMBOL.$fee;
                } else {
                    $sold = 0;
                    $sold_str = '-';
                    $fee = 0;
                    $fee_str = '-';
                }
                $total_sold += $sold;
                $total_fee += $fee;

                $cat_data[5][$row] = $sold_str; 
                $cat_data[6][$row] = $fee_str; 
            }

            //final category block
            if($category_header) {
                $pdf->Ln($row_h);
                $pdf->changeFont('H1');
                $pdf->Cell(0,$row_h,$category.':',0,0,'L',0);
                $pdf->Ln($row_h);    
            }
            //all lots in category
            $pdf->changeFont('TEXT');
            $pdf->arrayDrawTable($cat_data,$row_h,$col_width,$col_type,'L',$pdf_options);
            $pdf->ln($row_h); 

            //show fee summary and IOU amount
            $total_iou = $total_sold - $total_fee;

            $pdf->changeFont('H2');
            $pdf->Cell(50,$row_h,'Total lots sold :',0,0,'R',0);
            $pdf->Cell(50,$row_h,CURRENCY_SYMBOL.number_format($total_sold,0),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(50,$row_h,'Less commission fee:',0,0,'R',0);
            $pdf->Cell(50,$row_h,CURRENCY_SYMBOL.number_format($total_fee,0),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(50,$row_h,'Total due to you:',0,0,'R',0);
            $pdf->Cell(50,$row_h,CURRENCY_SYMBOL.number_format($total_iou,0),0,0,'L',0);
            $pdf->Ln($row_h);


            //finally create pdf file
            if($options['output'] === 'FILE') {
                $file_path = $doc_dir.$doc_name;
                $pdf->Output($file_path,'F');  
            }    

            //send directly to browser
            if($options['output'] === 'BROWSER') {
                $pdf->Output($doc_name,'D');
                exit();      
            }    
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';

            $doc_name = $doc_name_base.'.csv';

            $csv_data = Csv::sqlArrayDumpCsv('Lot',$lots);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }    
                
        if($error_str == '') return true; else return false;
    }

    public static function createAuctionCatalogPdf($db,$system,$auction_id,$options = [],&$doc_name,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $doc_name = '';

        $table_lot = TABLE_PREFIX.'lot';
        $table_condition = TABLE_PREFIX.'condition';
        $table_category = TABLE_PREFIX.'category';

        $description_only = false;
        $category_header = false;
        $lot_no_display = true;
        $pdf_override = true;

        $str = $system->getDefault('AUCTION_CATALOG_HEADER','NONE');
        if($str === 'ALL') $category_header = true;

        $str = $system->getDefault('AUCTION_CATALOG_LAYOUT','NONE');
        if($str === 'CONDENSED') $description_only = true;
        
        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'PDF';
        $options['format'] = strtoupper($options['format']);

        if(!isset($options['layout'])) $options['layout'] = 'STANDARD'; // REALISED,MASTER

        if($options['format'] !== 'PDF' and $options['format'] !== 'CSV') {
            $error .= 'Only PDF and CSV format supported for this report';
        }

        if($auction_id === 'ALL') {
            $error .= 'Cannot generate auction document for ALL auctions.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`postal_only`,`date_start_postal`,`date_end_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            } else {
                $sql = 'SELECT L.`lot_id`,L.`lot_no`,L.`category_id`,L.`index_terms`,L.`name`,L.`description`,'.
                              'L.`price_reserve`,L.`price_estimate`,L.`postal_only`, '.                
                              'L.`buyer_id`,L.`bid_no`,L.`bid_open`,L.`bid_book_top`,L.`bid_final`,L.`status`,L.`seller_id`, '.  
                              'CT.`level` AS `cat_level`,CT.`title` AS `cat_name`,CN.`name` AS `condition` '.
                       'FROM `'.$table_lot.'` AS L '.
                             'JOIN `'.$table_condition.'` AS CN ON(L.`condition_id` = CN.`condition_id`) '.
                             'JOIN `'.$table_category.'` AS CT ON(L.`category_id` = CT.`id`) '.
                       'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" '.
                       'ORDER BY CT.`rank`,L.`type_txt1`,L.`type_txt2`,CN.`sort` ';
                $lots = $db->readSqlArray($sql);
                if($lots == 0) $error .= 'No lots found for auction!';
            }    

        }

        if($error !== '') return false;   



        $doc_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        

        //get setup options
        $footer = $system->getDefault('AUCTION_CATALOGUE_FOOTER','');
        $signature = $system->getDefault('AUCTION_SIGN','');
        $signature_text = $system->getDefault('AUCTION_SIGN_TXT','');
        


        if($options['layout'] === 'STANDARD') {
            $doc_name_base = 'auction_'.$auction_id.'_'.str_replace(' ','_',$auction['name']).date('Y-m-d');
        } else {
            $doc_name_base = 'auction_'.$auction_id.'_'.str_replace(' ','_',$auction['name']).'_'.strtolower($options['layout']).'_'.date('Y-m-d');
        }
        
       

        //lot block setup
        $pdf_options = [];
        $pdf_options['header_align'] = 'L'; 

        $lot_page = []; 
        $index_terms = [];


        if($options['format'] === 'PDF') {

            $doc_name = $doc_name_base.'.pdf';
            $page_layout = 'Portrait';
            if($options['layout'] === 'MASTER') $page_layout = 'Landscape';

            $pdf = new Pdf($page_layout,'mm','A4');
            $pdf->AliasNbPages();
                
            $pdf->setupLayout(['db'=>$db]);

            //NB:override PDF settings to economise on space (no logo, small margins,size-6 text)
            if($pdf_override) {
                $pdf->bg_image = array('images/logo.jpeg',5,140,50,20,'YES'); //NB: YES flag turns off logo image display
                $pdf->page_margin = array(10,10,10,10);//top,left,right,bottom!!
                //$pdf->text = array(33,33,33,'',11);
                //$pdf->h1_title = array(33,33,33,'B',10,'',8,20,'L','YES',33,33,33,'B',12,20,180);
            }
            
            //NB footer must be set before this
            $pdf->AddPage();

            $row_h = 5;

            $pdf->SetY(20);
            $pdf->changeFont('H1');
            $pdf->Cell(100,$row_h,'Auction :',0,0,'R',0);
            $pdf->Cell(100,$row_h,$auction['name'],0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(100,$row_h,'Price currency :',0,0,'R',0);
            $pdf->Cell(100,$row_h,CURRENCY_ID,0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);

            /*
            $pdf->Cell(50,$row_h,'Summary :',0,0,'R',0);
            $pdf->MultiCell(120,$row_h,$auction['summary'],0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(50,$row_h,'Description :',0,0,'R',0);
            $pdf->MultiCell(120,$row_h,$auction['description'],0,'L',0);      
            $pdf->Ln($row_h);
            */

            if($options['layout'] !== 'MASTER') {
                $pdf->Cell(100,$row_h,'End date POSTAL :',0,0,'R',0);
                $pdf->Cell(100,$row_h,Date::formatDate($auction['date_end_postal']),0,0,'L',0);
                $pdf->Ln($row_h);
                if(!$auction['postal_only']) {
                    $pdf->Cell(100,$row_h,'Start date LIVE :',0,0,'R',0);
                    $pdf->Cell(100,$row_h,Date::formatDate($auction['date_start_live']),0,0,'L',0);
                    $pdf->Ln($row_h);
                }
                $pdf->Ln($row_h);    
            }
            
           
            //need to add some images here??
            /* 
            foreach($images as $file_name)
                $image_path = $upload_dir.$file_name;
                list($img_width,$img_height) = getimagesize($image_path);
                //height specified and width=0 so auto calculated     
                $y1 = $pdf->GetY();
                $pdf->Image($image_path,20,$y1,0,20);
                //$pdf->Image('images/sig_XXX.jpg',20,$y1,66,20);
                $pdf->SetY($y1+25);
            }
            */  

            $category = ''; 
            $cat_data_initial = [];
            $row = 0;

            $labels = MODULE_AUCTION['labels'];
            
            if($options['layout'] === 'STANDARD') {
                if($description_only) {
                    $col_width = array(10,20,120,20,20);
                    $col_type  = array('','','','CASH0','CASH0'); 

                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = 'Condition';
                    $cat_data_initial[2][$row] = 'Description';
                    $cat_data_initial[3][$row] = 'Reserve';
                    $cat_data_initial[4][$row] = 'Estimate';
                } else {
                    $col_width = array(10,20,30,10,80,20,20);
                    $col_type  = array('','','','','','CASH0','CASH0'); 

                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = $labels['category'];
                    $cat_data_initial[2][$row] = 'Name';
                    $cat_data_initial[3][$row] = 'Cond.';
                    $cat_data_initial[4][$row] = 'Description';
                    $cat_data_initial[5][$row] = 'Res.';
                    $cat_data_initial[6][$row] = 'Est.';
                }
            }

            if($options['layout'] === 'REALISED') {
                if($description_only) {
                    $col_width = array(10,20,120,20,20);
                    $col_type  = array('','','','CASH0',''); 

                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = 'Condition';
                    $cat_data_initial[2][$row] = 'Description';
                    $cat_data_initial[3][$row] = 'Reserve';
                    $cat_data_initial[4][$row] = 'Realised';
                } else {
                    $col_width = array(10,20,30,10,80,20,20);
                    $col_type  = array('','','','','','CASH0',''); 
                   
                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = $labels['category'];
                    $cat_data_initial[2][$row] = 'Name';
                    $cat_data_initial[3][$row] = 'Con.';
                    $cat_data_initial[4][$row] = 'Description';
                    $cat_data_initial[5][$row] = 'Reserve';
                    $cat_data_initial[6][$row] = 'Realised';
                }    
            }

            if($options['layout'] === 'MASTER') {
                if($description_only) {
                    $col_width = array(10,15,150,15,15,15,15,15,15,15);
                    $col_type  = array('','','','CASH0','CASH0','','CASH0','CASH0','CASH0',''); 

                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = 'Con.';
                    $cat_data_initial[2][$row] = 'Description';
                    $cat_data_initial[3][$row] = 'Res.';
                    $cat_data_initial[4][$row] = 'Est.';
                    $cat_data_initial[5][$row] = 'Seller';
                    $cat_data_initial[6][$row] = 'Open@';
                    $cat_data_initial[7][$row] = 'Book bid';
                    $cat_data_initial[8][$row] = 'Price';
                    $cat_data_initial[9][$row] = 'Buyer';
                } else {
                    $col_width = array(10,20,30,10,105,15,15,15,15,15,15,15);
                    $col_type  = array('','','','','','CASH0','CASH0','','CASH0','CASH0','CASH0',''); 
                   
                    $cat_data_initial[0][$row] = 'Lot';
                    $cat_data_initial[1][$row] = $labels['category'];
                    $cat_data_initial[2][$row] = 'Name';
                    $cat_data_initial[3][$row] = 'Con.';
                    $cat_data_initial[4][$row] = 'Description';
                    $cat_data_initial[5][$row] = 'Res.';
                    $cat_data_initial[6][$row] = 'Est.';
                    $cat_data_initial[7][$row] = 'Seller';
                    $cat_data_initial[8][$row] = 'Open@';
                    $cat_data_initial[9][$row] = 'Book bid';
                    $cat_data_initial[10][$row] = 'Price';
                    $cat_data_initial[11][$row] = 'Buyer';
                }    

                
            }
            
            foreach($lots as $lot_id => $lot) {

                //generate array of lot ids for each index term
                if(trim($lot['index_terms']) !== '') {
                    $arr = explode(',',$lot['index_terms']);

                    foreach($arr as $term) {
                        $term = trim($term);
                        $index_terms[$term][] = $lot_id;
                    }    
                }
                

                if($lot['cat_name'] !== $category and $lot['cat_level'] === '1') {
                    if($category !== '') {
                        //top level category header
                        if($category_header) {
                            $pdf->Ln($row_h);
                            $pdf->changeFont('H1');
                            $pdf->Cell(0,$row_h,$category.':',0,0,'L',0);
                            $pdf->Ln($row_h);
                        }
                        //all lots in category
                        $pdf->changeFont('TEXT');
                        $pdf->arrayDrawTable($cat_data,$row_h,$col_width,$col_type,'L',$pdf_options);
                        if($category_header) {
                            $pdf->ln($row_h); 
                        } else {
                            //NB want first block to have headers so only define this here
                            $pdf_options['header_show'] = false;
                        }     
                    }
                    

                    $row = 0;
                    $cat_data = $cat_data_initial;
                    $category = $lot['cat_name'];
                } 

                $row++;
                //only show postal only text if auction has both
                if(!$auction['postal_only'] and $lot['postal_only']) $postal = '(***Postal only***)'; else $postal = '';
                if($lot_no_display) $lot_str = $lot['lot_no']; else $lot_str = $lot_id;

                if($description_only) {
                    $cat_data[0][$row] = $lot_str;
                    $cat_data[1][$row] = $lot['condition'];
                    $cat_data[2][$row] = utf8_decode($lot['description']).$postal;
                    $cat_data[3][$row] = $lot['price_reserve'];
                    $next_col = 4;
                } else {
                    $cat_data[0][$row] = $lot_str;
                    $cat_data[1][$row] = $lot['cat_name'];
                    $cat_data[2][$row] = $lot['name'];
                    $cat_data[3][$row] = $lot['condition'];
                    $cat_data[4][$row] = utf8_decode($lot['description']).$postal;
                    $cat_data[5][$row] = $lot['price_reserve'];
                    $next_col = 6;
                }    
                
 
                if($options['layout'] === 'STANDARD') {
                    $cat_data[$next_col][$row] = $lot['price_estimate']; 
                }

                if($options['layout'] === 'REALISED') {
                    if($lot['status'] === 'SOLD' or $lot['bid_final'] > 0) {
                        $text = $lot['bid_final']; 
                    } else {
                        $text = 'available';
                    }

                    $cat_data[$next_col][$row] = $text;   
                }

                if($options['layout'] === 'MASTER') {
                    $cat_data[$next_col][$row] = $lot['price_estimate']; 
                    $cat_data[$next_col+1][$row] = $lot['seller_id'];
                    $cat_data[$next_col+2][$row] = '';

                    //top online bid or live bid depending on when report run
                    if($lot['bid_book_top'] != 0) $str = $lot['bid_book_top']; else $str = '';
                    $cat_data[$next_col+3][$row] = $str;
                    $cat_data[$next_col+4][$row] = '';

                    //top bid buyer id or winning bid id & buyer no if captured at live auction
                    if($lot['buyer_id'] != 0) {
                        $str = $lot['buyer_id'];
                        if($lot['bid_no'] !== '' and $lot['bid_no'] != $lot['buyer_id']) $str .= '('.$lot['bid_no'].')';
                    } else {
                        $str = '';
                    }    
                    $cat_data[$next_col+5][$row] = $str;
                }

                $lot_index[$lot_id] = $pdf->PageNo().', '.$category.', row '.$row;
                
            }

            //final category block
            if($category_header) {
                $pdf->Ln($row_h);
                $pdf->changeFont('H1');
                $pdf->Cell(0,$row_h,$category.':',0,0,'L',0);
                $pdf->Ln($row_h);
            }    
            //all lots in category
            $pdf->changeFont('TEXT');
            $pdf->arrayDrawTable($cat_data,$row_h,$col_width,$col_type,'L',$pdf_options);
            $pdf->ln($row_h); 


            //Finally create lot Index if any index terms specified
            if(count($index_terms)) {
                $sql = 'SELECT term_code,name FROM '.TABLE_PREFIX.'index_term ';
                $term_names = $db->readSqlList($sql);

                $pdf->addPage();
                $pdf->changeFont('H1');
                $pdf->Cell(0,$row_h,'Auction terms index',0,0,'L',0);
                $pdf->Ln($row_h);

                
                foreach($index_terms as $term => $term_lots) {
                    $pdf->changeFont('H2');
                    $pdf->Cell(0,$row_h,$term_names[$term].':',0,0,'L',0);
                    $pdf->Ln($row_h);
                    $pdf->changeFont('TEXT');
                    foreach($term_lots as $lot_id) {
                        $text = 'Page:'.$lot_index[$lot_id];
                        $pdf->SetX(20);
                        $pdf->Cell(0,$row_h,$text,0,0,'L',0);
                        $pdf->Ln($row_h);
                    }
                    
                    
                }
            }    

           
            //finally create pdf file
            if($options['output'] === 'FILE') {
                $file_path = $doc_dir.$doc_name;
                $pdf->Output($file_path,'F');
            }    

            //send directly to browser
            if($options['output'] === 'BROWSER') {
                $pdf->Output($doc_name,'D'); 
                exit();     
            }    
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';

            $doc_name = $doc_name_base.'.csv';

            $csv_data = Csv::sqlArrayDumpCsv('Lot',$lots);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }    
                
        if($error_str == '') return true; else return false ;
    }

    public static function createAuctionSummary($db,$system,$auction_id,$options = [],&$doc_name,&$error)  
    {
        $error = '';
        $error_tmp = '';
        $doc_name = '';

        $table_lot = TABLE_PREFIX.'lot';
        $table_condition = TABLE_PREFIX.'condition';
        $table_category = TABLE_PREFIX.'category';

        $category_header = false;
        $lot_no_display = true;
        $pdf_override = true;
        
        if(!isset($options['output'])) $options['output'] = 'BROWSER';
        if(!isset($options['format'])) $options['format'] = 'PDF';
        $options['format'] = strtoupper($options['format']);

        if(!isset($options['layout'])) $options['layout'] = 'COMPRESSED';

        if($options['format'] !== 'PDF' and $options['format'] !== 'CSV') {
            $error .= 'Only PDF and CSV format supported for this report';
        }

        if($auction_id === 'ALL') {
            $error .= 'Cannot generate document for ALL auctions.';
        } else {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`postal_only`,`date_start_postal`,`date_end_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            } else {
                $sql = 'SELECT L.`lot_id`,L.`lot_no`,L.`category_id`,L.`index_terms`,L.`name`,L.`description`,'.
                              'L.`price_reserve`,L.`price_estimate`,L.`postal_only`, '.                
                              'L.`bid_open`,L.`bid_book_top`,L.`bid_final`,L.`status` '.  
                       'FROM `'.$table_lot.'` AS L '.
                       'WHERE L.`auction_id` = "'.$db->escapeSql($auction_id).'" '.
                       'ORDER BY L.`lot_no` ';
                $lots = $db->readSqlArray($sql);
                if($lots == 0) $error .= 'No lots found for auction!';
            }    

        }

        if($error !== '') return false;   



        $doc_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        

        //get setup options
        $footer = $system->getDefault('AUCTION_CATALOGUE_FOOTER','');
        $signature = $system->getDefault('AUCTION_SIGN','');
        $signature_text = $system->getDefault('AUCTION_SIGN_TXT','');
        


        if($options['layout'] === 'STANDARD') {
            $doc_name_base = 'auction_'.$auction_id.'_'.str_replace(' ','_',$auction['name']).date('Y-m-d');
        } else {
            $doc_name_base = 'auction_'.$auction_id.'_'.str_replace(' ','_',$auction['name']).'_'.strtolower($options['layout']).'_'.date('Y-m-d');
        }
              

        //lot block setup
        $pdf_options = [];
        $pdf_options['header_align'] = 'L'; 

        if($options['format'] === 'PDF') {

            $doc_name = $doc_name_base.'.pdf';
            $page_layout = 'Portrait';
          
            $pdf = new Pdf($page_layout,'mm','A4');
            $pdf->AliasNbPages();
                
            $pdf->setupLayout(['db'=>$db]);

            //NB:override PDF settings to economise on space (no logo, small margins,size-6 text)
            if($pdf_override) {
                $pdf->bg_image = array('images/logo.jpeg',5,140,50,20,'YES'); //NB: YES flag turns off logo image display
                $pdf->page_margin = array(10,10,10,10);//top,left,right,bottom!!
                $pdf->text = array(33,33,33,'',6);
                $pdf->h1_title = array(33,33,33,'B',10,'',5,10,'L','YES',33,33,33,'B',12,20,180);

                $pdf->SetMargins($pdf->page_margin[1],$pdf->page_margin[0],$pdf->page_margin[2]);
            }

            $pdf->page_title = $auction['name'].' Realised prices in '.CURRENCY_ID. ' (Lots without price available at reserve price)';
            
            //NB footer must be set before this
            $pdf->AddPage();

            $row_h = 5;
            $data = [];
            $row = 0;

            $labels = MODULE_AUCTION['labels'];

            if($options['layout'] === 'COMPRESSED') {
                $col_width = array(10,15);
                $col_type  = array('',''); 
                //NB: 8 column layout
                $pdf_options['page_split'] = 8; 
               
                $data[0][$row] = 'Lot No.';
                $data[1][$row] = 'Price';
            }

            $totals = ['reserve'=>0.00,'estimate'=>0.00,'bid'=>0.00,'lots'=>count($lots)];
            foreach($lots as $lot_id => $lot) {

                $row++;
                
                if($lot_no_display) $lot_str = $lot['lot_no']; else $lot_str = $lot_id;
                
                
                if($options['layout'] === 'COMPRESSED') {
                    $data[0][$row] = $lot_str;
                    if($lot['bid_final']== 0) {
                        $price_str = '';
                    } else {
                        $price_str = number_format(round($lot['bid_final'],0));
                        $totals['bid'] += $lot['bid_final'];
                    }    
                    $data[1][$row] = $price_str; 
                }

                $totals['reserve'] += $lot['price_reserve'];
                $totals['estimate'] += $lot['price_estimate'];
            }

            $pdf->changeFont('H2');
            $pdf->Cell(100,$row_h,'Total lots :',0,0,'R',0);
            $pdf->Cell(100,$row_h,number_format($totals['lots'],0),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(100,$row_h,'Total reserve value :',0,0,'R',0);
            $pdf->Cell(100,$row_h,number_format($totals['reserve'],2),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Cell(100,$row_h,'Total estimate value :',0,0,'R',0);
            $pdf->Cell(100,$row_h,number_format($totals['estimate'],2),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);
            $pdf->Cell(100,$row_h,'Total bid value :',0,0,'R',0);
            $pdf->Cell(100,$row_h,number_format($totals['bid'],2),0,0,'L',0);
            $pdf->Ln($row_h);
            $pdf->Ln($row_h);

            
            $pdf->changeFont('TEXT');
            $pdf->arrayDrawTable2($data,$row_h,$col_width,$col_type,'L',$pdf_options);
            $pdf->ln($row_h); 

            //finally create pdf file
            if($options['output'] === 'FILE') {
                $file_path = $doc_dir.$doc_name;
                $pdf->Output($file_path,'F');
            }    

            //send directly to browser
            if($options['output'] === 'BROWSER') {
                $pdf->Output($doc_name,'D'); 
                exit();     
            }    
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';

            $doc_name = $doc_name_base.'.csv';

            $csv_data = Csv::sqlArrayDumpCsv('Lot',$lots);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }    
                
        if($error_str == '') return true; else return false ;
    }    

    public static function invoiceReport($db,$status,$user_id,$auction_id,$options = [],&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';
        $name_str = '';
        
        if(!isset($options['format'])) $options['format'] = 'HTML';

        if($options['format'] == 'PDF') $error .= 'PDF format not currently available for this report.';
        
        if($status === 'ALL') $name_str .= 'all_';

        if($auction_id !== 'ALL') {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            }
            $name_str .= 'auction'.$auction_id.'_'; 
        } else {
            $name_str .= 'all_auctions_';
        }

        if($user_id !== 'ALL') $name_str .= 'user'.$user_id.'_';
        
        if($error !== '') return false;

        $doc_name_base = 'invoices_'.$name_str;

        $sql = 'SELECT I.`invoice_id`, I.`user_id`, U.`name`, U.`email`, I.`date`, I.`sub_total`, I.`tax`, I.`total` '.
               'FROM `'.TABLE_PREFIX.'invoice` AS I JOIN `'.TABLE_USER.'` AS U ON(I.`user_id` = U.`user_id`) ';
        
        $where = '';       
        if($status !== 'ALL') $where .= 'I.`status` = "'.$db->escapeSql($status).'" AND ';
        if($auction_id !== 'ALL') $where .= 'I.`auction_id` = "'.$db->escapeSql($auction_id).'" AND ';
        if($user_id !== 'ALL') $where .= 'I.`user_id` = "'.$db->escapeSql($user_id).'" AND '; 
        if($where !== '')  $sql .= 'WHERE '.substr($where,0,-4).' ';

        $sql .= 'ORDER BY `date` ';

        $invoices = $db->readSqlArray($sql);
        if($invoices == 0) {
            $error .= 'No invoices found matching your criteria';
        } else {
            if($options['format'] === 'HTML') {
                $html = Html::arrayDumpHtml($invoices,['show_key'=>true]);
            }

            if($options['format'] === 'CSV') {
                $csv_data = '';

                $doc_name = $doc_name_base.'.csv';
                //dumps key by default unlike html version
                $csv_data = Csv::sqlArrayDumpCsv('ID',$invoices);
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }    
        }            
                    

        return $html;
    } 

    public static function orderReport($db,$status,$user_id,$auction_id,$options = [],&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';
        $name_str = '';
        
        if(!isset($options['format'])) $options['format'] = 'HTML';

        if($options['format'] == 'PDF') $error .= 'PDF format not currently available for this report.';
        
        if($status === 'ALL') $name_str .= 'all_';

        if($auction_id !== 'ALL') {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            }
            $name_str .= 'auction'.$auction_id.'_'; 
        } else {
            $name_str .= 'all_auctions_';
        }

        if($user_id !== 'ALL') $name_str .= 'user'.$user_id.'_';

        if($error !== '') return false;

        $doc_name_base = 'orders_'.$name_str;

        $sql = 'SELECT O.`order_id`, O.`user_id`, U.`name`, U.`email`, O.`date_create`, O.`no_items`, O.`total_bid` '.
               'FROM `'.TABLE_PREFIX.'order`  AS O JOIN `'.TABLE_USER.'` AS U ON(O.`user_id` = U.`user_id`) ';

        $where = '';       
        if($status !== 'ALL') $where .= 'O.`status` = "'.$db->escapeSql($status).'" AND ';
        if($auction_id !== 'ALL') $where .= 'O.`auction_id` = "'.$db->escapeSql($auction_id).'" AND ';
        if($user_id !== 'ALL') $where .= 'O.`user_id` = "'.$db->escapeSql($user_id).'" AND '; 
        if($where !== '')  $sql .= 'WHERE '.substr($where,0,-4).' ';

        $sql .= 'ORDER BY `date_create` ';

        $orders = $db->readSqlArray($sql);
        if($orders == 0) {
            $error .= 'No orders found matching your criteria';
        } else {
            if($options['format'] === 'HTML') {
                $html = Html::arrayDumpHtml($orders,['show_key'=>true]);
            }

            if($options['format'] === 'CSV') {
                $csv_data = '';

                $doc_name = $doc_name_base.'.csv';

                $csv_data = Csv::sqlArrayDumpCsv('Lot',$orders);
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }    
        }            
                    

        return $html;
    }

    //admin user productivity reportLOTS_CAPTURED
    public static function lotCaptureReport($db,$user_id,$date_from,$date_to,$options = [],&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';

        $table_audit = TABLE_AUDIT;
        $table_auction = TABLE_PREFIX.'auction';
        $table_lot = TABLE_PREFIX.'lot';

        $name_str = 'Lots_created_';
        
        if(!isset($options['format'])) $options['format'] = 'HTML';

        if($options['format'] == 'PDF') $error .= 'PDF format not currently available for this report.';

        $user = Helpers::getUserData($db,'USER_ID',$user_id); 
        
        if($auction_id !== 'ALL') {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            }
            $name_str .= 'auction'.$auction_id.'_'; 
        } else {
            $name_str .= 'all_auctions_';
        }

        $name_str .= 'user'.$user_id.'_from_'.$date_from.'_to_'.$date_to;

        if($error !== '') return false;

        $doc_name_base = $name_str;

        $sql = 'SELECT `audit_id`, DATE(`date`) AS `date`, `text`, `action`  '.
               'FROM `'.$table_audit.'` AS A '.
               'WHERE A.`user_id` = "'.$db->escapeSql($user_id).'" AND A.`action` LIKE "%_AUC_LOT" AND  '.
                     'DATE(A.`date`) >= "'.$db->escapeSql($date_from).'" AND DATE(A.`date`) <= "'.$db->escapeSql($date_to).'"'.
               'ORDER BY A.`date`';
        $audits = $db->readSqlArray($sql);
        if($audits == 0) $error .= 'No lots created matching your criteria';

        if($error !== '') return false;

        //construct data array
        $data = [];
        $r = 0;
        $data[0][$r] = 'Date';
        $data[1][$r] = 'No. Created';
        $data[2][$r] = 'Create value';
        $data[3][$r] = 'No. Updated';
        $data[4][$r] = 'Update value';
        $data[5][$r] = 'No. Deleted';
        $data[6][$r] = 'Deleted value';
        
        $date_prev = '';
        $total = ['create_no'=>0,'create_value'=>0,
                  'update_no'=>0,'update_value'=>0,
                  'delete_no'=>0,'delete_value'=>0];
        foreach($audits as $audit) {
            if($audit['date'] !== $date_prev) {
                                    
                if($date_prev !== '') {
                    $r++; 
                    $data[0][$r] = Date::formatDate($date_prev);
                    $data[1][$r] = $total['date_create_no'];
                    $data[2][$r] = $total['date_create_value'];
                    $data[3][$r] = $total['date_update_no'];
                    $data[4][$r] = $total['date_update_value'];
                    $data[5][$r] = $total['date_delete_no'];
                    $data[6][$r] = $total['date_delete_value'];
                }                   
                                    
                
                $total['date_create_no'] = 0;
                $total['date_create_value'] = 0;
                $total['date_update_no'] = 0;
                $total['date_update_value'] = 0;
                $total['date_delete_no'] = 0;
                $total['date_delete_value'] = 0;
            }

            $action = explode('_',$audit['action']);
            $verb = $action[0];
            $lot = json_decode($audit['text'],true);
            $value = $lot['price_reserve']; //could use price_estimate
            switch($verb) {
                case 'CREATE': 
                    $total['create_no']++;
                    $total['create_value'] += $value;
                    $total['date_create_no']++;
                    $total['date_create_value'] += $value;
                    break;
                case 'UPDATE': 
                    $total['update_no']++;
                    $total['update_value'] += $value;
                    $total['date_update_no']++;
                    $total['date_update_value'] += $value;
                    break;
                case 'DELETE':
                    $total['delete_no']++;
                    $total['delete_value'] += $value;
                    $total['date_delete_no']++;
                    $total['date_delete_value'] += $value;
                    break;
              
            }

            $date_prev = $audit['date'];
        }

        //final date totals
        $r++; 
        $data[0][$r] = Date::formatDate($date_prev);
        $data[1][$r] = $total['date_create_no'];
        $data[2][$r] = $total['date_create_value'];
        $data[3][$r] = $total['date_update_no'];
        $data[4][$r] = $total['date_update_value'];
        $data[5][$r] = $total['date_delete_no'];
        $data[6][$r] = $total['date_delete_value'];
        

        //totals for all cols
        $r++; 
        $data[0][$r] = 'Totals';
        $data[1][$r] = $total['create_no'];
        $data[2][$r] = $total['create_value'];
        $data[3][$r] = $total['update_no'];
        $data[4][$r] = $total['update_value'];
        $data[5][$r] = $total['delete_no'];
        $data[6][$r] = $total['delete_value'];

        
        if($options['format'] === 'HTML') {
            $html = '<h2>'.$user['name'].'</h2>'.
                    Html::arrayDumpHtml2($data,['show_key'=>true]);
        }

        if($options['format'] === 'CSV') {
            $csv_data = '';
            $doc_name = $doc_name_base.'.csv';

            $csv_data = Csv::arrayDumpCsv($data);
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
            exit();
        }


        //if($options['format'] === 'PDF') 
                    

        return $html;
    }

    public static function auctionSellerReport($db,$auction_id,$options = [],&$error)  
    {
        $error = '';
        $error_tmp = '';
        $html = '';
                
        if(!isset($options['format'])) $options['format'] = 'HTML';

        if($options['format'] == 'PDF') $error .= 'PDF format not currently available for this report.';
        
        $doc_name_base = 'sellers_';
        
        if($auction_id !== 'ALL') {
            $sql = 'SELECT `auction_id`,`name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
                   'FROM `'.TABLE_PREFIX.'auction` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            } else {
                $auction_name = $auction['name'];
            }

            $doc_name_base .= 'auction'.$auction_id.'_'; 
        } else {
            $doc_name_base .= 'all_auctions_';
            $auction_name = 'ALL auctions';
        }

        if($error !== '') return false;

        $sql = 'SELECT L.`seller_id`, S.`name`, 
                       GROUP_CONCAT(L.`lot_id` ORDER BY L.`lot_no` SEPARATOR ", ") AS `Lot_ids`, 
                       GROUP_CONCAT(L.`lot_no` ORDER BY L.`lot_no` SEPARATOR ", ") AS `Lot_nos`,
                       COUNT(*) AS `Total_lots` '.
               'FROM `'.TABLE_PREFIX.'lot` AS L LEFT JOIN `'.TABLE_PREFIX.'seller` AS S ON(L.`seller_id` = S.`seller_id`) ';

        $where = '';       
        if($auction_id !== 'ALL') $where .= 'L.`auction_id` = "'.$db->escapeSql($auction_id).'" AND ';
        if($where !== '')  $sql .= 'WHERE '.substr($where,0,-4).' ';

        $sql .= 'GROUP BY L.`seller_id` ORDER BY S.`sort`, S.`name` ';

        $sellers = $db->readSqlArray($sql);
        if($sellers == 0) {
            $error .= 'No sellers found matching your criteria';
        } else {
            if($options['format'] === 'HTML') {
                $html = '<h2>'.$auction_name. ' seller lots sorted by lot number:</h2>';
                $html .= Html::arrayDumpHtml($sellers,['show_key'=>true]);
            }

            if($options['format'] === 'CSV') {
                $csv_data = '';

                $doc_name = $doc_name_base.'.csv';

                $csv_data = Csv::sqlArrayDumpCsv('Lot',$sellers);
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }    
        }            

        return $html;
    }    

}


?>
