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
use Seriti\Tools\TABLE_USER;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\UPLOAD_TEMP;

use Psr\Container\ContainerInterface;


//static functions for auction module
class HelpersReport {

    //creates index array of term => array of lot ids
    //NB: orders by lot_id not category, so do not use for catelogue
    public static function buildAuctionIndex($db,$auction_id,$options = [])  
    {
        $index = [];

        $sql = 'SELECT lot_id,index_terms '.
               'FROM '.TABLE_PREFIX.'lot '.
               'WHERE auction_id = "'.$db->escapeSql($auction_id).'" AND index_terms <> "" '.
               'ORDER BY lot_id ';

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
            $sql = 'SELECT auction_id,name,summary,description,date_start_postal,date_start_live,status '.
                   'FROM '.$table_auction.' WHERE auction_id = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) $error .= 'Invalid Auction['.$auction_id.'] selected.';
        }

        
        $sql = 'SELECT seller_id,name,cell,tel,email,address,status,comm_pct '.
               'FROM '.$table_seller.' WHERE seller_id = "'.$db->escapeSql($seller_id).'"';
        $seller = $db->readSqlRecord($sql,$db); 
        if($seller === 0) $error .= 'Invalid Seller['.$seller_id.'] selected.';

        if($error === '') {
            $sql = 'SELECT L.lot_id,L.lot_no,L.category_id,L.index_terms,L.name,L.description,L.price_reserve,L.price_estimate,L.postal_only, '.                
                          'L.bid_open,L.bid_book_top,L.bid_final,L.status, '.  
                          'CT.level AS cat_level,CT.title AS cat_name,CN.name AS `condition` '.
                   'FROM '.$table_lot.' AS L '.
                         'JOIN '.$table_condition.' AS CN ON(L.condition_id = CN.condition_id) '.
                         'JOIN '.$table_category.' AS CT ON(L.category_id = CT.id) '.
                   'WHERE L.auction_id = "'.$db->escapeSql($auction_id).'" AND L.seller_id = "'.$db->escapeSql($seller_id).'" '.
                   'ORDER BY CT.rank,L.type_txt1,L.type_txt2,CN.sort ';
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
        $comm_rate = $seller['comm_pct']/100;
        $commission_str = $seller['comm_pct'].'%';

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
            $pdf->Cell(100,$row_h,$seller['name'],0,0,'L',0);
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
            $cat_data_initial[6][$row] = 'Fee '.$commission_str;
            
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
                $cat_data[2][$row] = utf8_decode($lot['name'].': '.$lot['description']).$postal;
                $cat_data[3][$row] = CURRENCY_SYMBOL.$lot['price_reserve'];
                $cat_data[4][$row] = CURRENCY_SYMBOL.$lot['price_estimate']; 
                
                if($lot['status'] === 'SOLD' or $lot['bid_final'] > 0) {
                    $sold = $lot['bid_final']; 
                    $sold_str = CURRENCY_SYMBOL.number_format($sold,0);
                    $fee = round(($lot['bid_final'] * $comm_rate),0);
                    $fee_str =  CURRENCY_SYMBOL.number_format($fee,0);
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
            $pdf->Cell(50,$row_h,'Less '.$commission_str.' commission:',0,0,'R',0);
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

        $category_header = false;
        $lot_no_display = true;
        
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
            $sql = 'SELECT auction_id,name,summary,description,postal_only,date_start_postal,date_end_postal,date_start_live,status '.
                   'FROM '.TABLE_PREFIX.'auction WHERE auction_id = "'.$db->escapeSql($auction_id).'"';
            $auction = $db->readSqlRecord($sql,$db); 
            if($auction === 0) {
                $error .= 'Invalid Auction['.$auction_id.'] selected.';
            } else {
                $sql = 'SELECT L.lot_id,L.lot_no,L.category_id,L.index_terms,L.name,L.description,L.price_reserve,L.price_estimate,L.postal_only, '.                
                              'L.bid_open,L.bid_book_top,L.bid_final,L.status, '.  
                              'CT.level AS cat_level,CT.title AS cat_name,CN.name AS `condition` '.
                       'FROM '.$table_lot.' AS L '.
                             'JOIN '.$table_condition.' AS CN ON(L.condition_id = CN.condition_id) '.
                             'JOIN '.$table_category.' AS CT ON(L.category_id = CT.id) '.
                       'WHERE L.auction_id = "'.$db->escapeSql($auction_id).'" '.
                       'ORDER BY CT.rank,L.type_txt1,L.type_txt2,CN.sort ';
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
            
            $pdf->Cell(100,$row_h,'End date POSTAL :',0,0,'R',0);
            $pdf->Cell(100,$row_h,Date::formatDate($auction['date_end_postal']),0,0,'L',0);
            $pdf->Ln($row_h);
            if(!$auction['postal_only']) {
                $pdf->Cell(100,$row_h,'Start date LIVE :',0,0,'R',0);
                $pdf->Cell(100,$row_h,Date::formatDate($auction['date_start_live']),0,0,'L',0);
                $pdf->Ln($row_h);
            }
            $pdf->Ln($row_h);
           
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
                $col_width = array(10,20,30,10,100,10,10);
                $col_type  = array('','','','','','CASH0','CASH0'); 
               
                $cat_data_initial[0][$row] = 'Lot';
                $cat_data_initial[1][$row] = $labels['category'];
                $cat_data_initial[2][$row] = 'Name';
                $cat_data_initial[3][$row] = 'Cond.';
                $cat_data_initial[4][$row] = 'Description';
                $cat_data_initial[5][$row] = 'Res.';
                $cat_data_initial[6][$row] = 'Est.';
            }

            if($options['layout'] === 'REALISED') {
                $col_width = array(10,20,30,10,100,10,10);
                $col_type  = array('','','','','','CASH0',''); 
               
                $cat_data_initial[0][$row] = 'Lot';
                $cat_data_initial[1][$row] = $labels['category'];
                $cat_data_initial[2][$row] = 'Name';
                $cat_data_initial[3][$row] = 'Con.';
                $cat_data_initial[4][$row] = 'Description';
                $cat_data_initial[5][$row] = 'Reserve';
                $cat_data_initial[6][$row] = 'Realised';
            }

            if($options['layout'] === 'MASTER') {
                $col_width = array(10,20,30,10,100,10,10,20,20,20,20,10);
                $col_type  = array('','','','','','CASH0','CASH0','','','','',''); 
               
                $cat_data_initial[0][$row] = 'Lot';
                $cat_data_initial[1][$row] = $labels['category'];
                $cat_data_initial[2][$row] = 'Name';
                $cat_data_initial[3][$row] = 'Con.';
                $cat_data_initial[4][$row] = 'Description';
                $cat_data_initial[5][$row] = 'Res.';
                $cat_data_initial[6][$row] = 'Est.';
                $cat_data_initial[7][$row] = 'Seller';
                $cat_data_initial[8][$row] = 'Open@';
                $cat_data_initial[9][$row] = 'Top book bid';
                $cat_data_initial[10][$row] = 'Price';
                $cat_data_initial[11][$row] = 'Buyer no';
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
                $cat_data[0][$row] = $lot_str;
                $cat_data[1][$row] = $lot['cat_name'];
                $cat_data[2][$row] = $lot['name'];
                $cat_data[3][$row] = $lot['condition'];
                $cat_data[4][$row] = utf8_decode($lot['description']).$postal;
                $cat_data[5][$row] = $lot['price_reserve'];
 
                if($options['layout'] === 'STANDARD') {
                    $cat_data[6][$row] = $lot['price_estimate']; 
                }

                if($options['layout'] === 'REALISED') {
                    if($lot['status'] === 'SOLD' or $lot['bid_final'] > 0) {
                        $text = $lot['bid_final']; 
                    } else {
                        $text = 'available';
                    }

                    $cat_data[6][$row] = $text;   
                }

                if($options['layout'] === 'MASTER') {
                    $cat_data[6][$row] = $lot['price_estimate']; 
                    $cat_data[7][$row] = '';
                    $cat_data[8][$row] = '';
                    $cat_data[9][$row] = '';
                    $cat_data[10][$row] = '';
                    $cat_data[11][$row] = '';
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
            $sql = 'SELECT auction_id,name,summary,description,date_start_postal,date_start_live,status '.
                   'FROM '.TABLE_PREFIX.'auction WHERE auction_id = "'.$db->escapeSql($auction_id).'"';
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

        $sql = 'SELECT I.invoice_id, I.user_id, U.name, U.email, I.date, I.sub_total, I.tax, I.total '.
               'FROM '.TABLE_PREFIX.'invoice  AS I JOIN '.TABLE_USER.' AS U ON(I.user_id = U.user_id) ';
        
        $where = '';       
        if($status !== 'ALL') $where .= 'I.status = "'.$db->escapeSql($status).'" AND ';
        if($auction_id !== 'ALL') $where .= 'I.auction_id = "'.$db->escapeSql($auction_id).'" AND ';
        if($user_id !== 'ALL') $where .= 'I.user_id = "'.$db->escapeSql($user_id).'" AND '; 
        if($where !== '')  $sql .= 'WHERE '.substr($where,0,-4).' ';

        $sql .= 'ORDER BY date ';

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
            $sql = 'SELECT auction_id,name,summary,description,date_start_postal,date_start_live,status '.
                   'FROM '.TABLE_PREFIX.'auction WHERE auction_id = "'.$db->escapeSql($auction_id).'"';
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

        $sql = 'SELECT O.order_id, O.user_id, U.name, U.email, O.date_create, O.no_items, O.total_bid '.
               'FROM '.TABLE_PREFIX.'order  AS O JOIN '.TABLE_USER.' AS U ON(O.user_id = U.user_id) ';

        $where = '';       
        if($status !== 'ALL') $where .= 'O.status = "'.$db->escapeSql($status).'" AND ';
        if($auction_id !== 'ALL') $where .= 'O.auction_id = "'.$db->escapeSql($auction_id).'" AND ';
        if($user_id !== 'ALL') $where .= 'O.user_id = "'.$db->escapeSql($user_id).'" AND '; 
        if($where !== '')  $sql .= 'WHERE '.substr($where,0,-4).' ';

        $sql .= 'ORDER BY date_create ';

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

}


?>
