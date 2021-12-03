<?php 
namespace App\Auction;

use Exception;
use Seriti\Tools\Secure;
use Seriti\Tools\Calc;
use Seriti\Tools\Crypt;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Image;
use Seriti\Tools\Pdf;
use Seriti\Tools\Csv;
use Seriti\Tools\Date;
use Seriti\Tools\Doc;
use Seriti\Tools\Upload;

use Seriti\Tools\STORAGE;
use Seriti\Tools\MAIL_FROM;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\TABLE_USER;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\UPLOAD_TEMP;

use Psr\Container\ContainerInterface;


//static functions for auction module
class HelpersPayment {

    public static function getInvoiceNo($db)  
    {
        $table = TABLE_SYSTEM;
        
        $id = 0;
        $error = '';
        $error_tmp = '';
                     
        $sql = 'LOCK TABLES `'.$table.'` WRITE';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') $error .= 'Could NOT lock system table for INVOICE counter!'; 
                            
        if($error == '') {          
            $sql = 'SELECT `sys_count` FROM `'.$table.'` WHERE `system_id` = "INVOICE" ';
            $id = $db->readSqlValue($sql,0);
            if($id === 0) {
                $error .= 'Could not read System table INVOICE value!';
            } else {
                $id = $id+1;   
            }
        }
        
        if($error == '') {          
            $sql = 'UPDATE `'.$table.'` SET `sys_count` = `sys_count` + 1 WHERE `system_id` = "INVOICE" ';
            $db->executeSql($sql,$error_tmp);
            if($error_tmp != '') $error .= 'Could not update system INVOICE value';
        }
                
        $sql = 'UNLOCK TABLES';
        $db->executeSql($sql,$error_tmp);
        if($error_tmp != '') $error .= 'Could NOT UNlock system table for INVOICE counter!';
        
        if($error !== '') {
            throw new Exception('SYSTEM_INVOICE_ID_ERROR['.$error.']');
        }    

        return $id;
    } 


    //creates invoice from invoice_wizard.php
    public static function createInvoicePdf($db,$system,$auction_id,$user = [],$data = [],&$doc_name,&$error_str) {
        
        $error_str = '';
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        
        $invoice_no = $data['no'];
        $pdf_name = $invoice_no.'.pdf';
        $doc_name = $pdf_name;

        $table_auction = TABLE_PREFIX.'auction';

        $sql = 'SELECT `auction_id`,`name`,`summary`,`status` '.
               'FROM `'.$table_auction.'` WHERE `auction_id` = "'.$db->escapeSql($auction_id).'" ';
        $auction = $db->readSqlRecord($sql);
        if($auction == 0) $error_str .= 'Invalid invoice auction ID['.$auction_id.']';

        if($error_str !== '') return false;
        
        //get setup options
        $footer = $system->getDefault('AUCTION_INVOICE_FOOTER','');
        $signature = $system->getDefault('AUCTION_SIGN','');
        $signature_text = $system->getDefault('AUCTION_SIGN_TXT','');
                    
        $pdf = new Pdf('Portrait','mm','A4');
        $pdf->AliasNbPages();
            
        $pdf->setupLayout(['db'=>$db]);
        
        //NB footer must be set before this
        $pdf->AddPage();

        $row_h = 5;
                                 
        $pdf->SetY(40);
        $pdf->changeFont('H1');
        $pdf->Cell(30,$row_h,'Auction :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$auction['name'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(30,$row_h,'Invoice :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['no'],0,0,'L',0);
        $pdf->Ln($row_h);

        $str = $data['for'].' (User ID '.$user['user_id'].')';
        $pdf->Cell(30,$row_h,'To :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$str,0,0,'L',0);
        $pdf->Ln($row_h);
        
        $pdf->Cell(30,$row_h,'Date issued :',0,0,'R',0);
        $pdf->Cell(30,$row_h,date('j-F-Y'),0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Ln($row_h);
                
        //invoice items table
        if(count($data['items'] != 0)) {
            $pdf->changeFont('TEXT');
            $col_width = array(20,100,20,20);
            $col_type = array('DBL2','','DBL2','DBL2');
            $pdf->arrayDrawTable($data['items'],$row_h,$col_width,$col_type,'L');
        }
        
        //totals
        $pdf->changeFont('H3');
        $pdf->Cell(142,$row_h,'SUBTOTAL :',0,0,'R',0);
        $pdf->Cell(142,$row_h,number_format($data['subtotal'],2),0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(142,$row_h,'VAT :',0,0,'R',0);
        $pdf->Cell(142,$row_h,$data['vat'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(142,$row_h,'TOTAL :',0,0,'R',0);
        $pdf->Cell(142,$row_h,number_format($data['total'],2),0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Ln($row_h);
            
        if($data['comment'] != '') {
            $pdf->MultiCell(0,$row_h,$data['comment'],0,'L',0); 
            $pdf->Ln($row_h);
        }
                
                            
        if($footer !== '') {
            $pdf->MultiCell(0,$row_h,$footer,0,'L',0);      
            $pdf->Ln($row_h);
        }    
                
        if($signature != '') {
            $image_path = $upload_dir.$signature;
            list($img_width,$img_height) = getimagesize($image_path);
            //height specified and width=0 so auto calculated     
            $y1 = $pdf->GetY();
            $pdf->Image($image_path,20,$y1,0,20);
            //$pdf->Image('images/sig_XXX.jpg',20,$y1,66,20);
            $pdf->SetY($y1+25);
        } else {
            $pdf->Ln($row_h*3); 
        }   
        
        if($signature_text != '') {    
            $pdf->Cell(0,$row_h,$signature_text,0,0,'L',0);
            $pdf->Ln($row_h);
        }  
        
        //finally create pdf file
        $file_path = $pdf_dir.$pdf_name;
        $pdf->Output($file_path,'F');   
                
        if($error_str == '') return true; else return false ;
    } 
    
    public static function saveInvoice($db,$system,$s3,$user_id,$data = [],$doc_name,&$error_str) {
        $error_tmp = '';
        $error_str = '';
     
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS; 
        
        $invoice = array();
        $invoice['auction_id'] = $data['auction_id'];
        $invoice['invoice_no'] = $data['no'];
        //order id only set if invoice generated from an order
        $invoice['order_id'] = $data['order_id'];
        $invoice['user_id'] = $user_id;
        $invoice['sub_total'] = $data['subtotal'];
        $invoice['tax'] = $data['vat'];
        $invoice['total'] = $data['total'];
        $invoice['date'] = date('Y-m-d');
        $invoice['comment'] = $data['comment'];
        $invoice['status'] = "OK";
        $invoice['doc_name'] = $doc_name;
        
        //save invoice
        $invoice_id = $db->insertRecord(TABLE_PREFIX.'invoice',$invoice,$error_tmp);
        if($error_tmp != '') $error_str .= 'Could not create invoice record!';
        
        //save invoice item data
        if($error_str == '') {
            $items = $data['items'];
            $item_no = count($items[0])-1; //first line contains headers
            for($i = 1; $i <= $item_no; $i++) {
                $invoice_item = [];
                $invoice_item['invoice_id'] = $invoice_id;
                $invoice_item['quantity'] = $items[0][$i];
                $invoice_item['item'] = $items[1][$i];
                $invoice_item['price'] = $items[2][$i];
                $invoice_item['total'] = $items[3][$i];
                //NB: invoice items can be anything
                if(is_numeric($items[4][$i])) $lot_id = $items[4][$i]; else $lot_id = 0;
                $invoice_item['lot_id'] = $lot_id;

                 
                $db->insertRecord(TABLE_PREFIX.'invoice_item',$invoice_item,$error_tmp);
                if($error_tmp != '') $error_str .= 'Could not add invoice item['.$invoice_item['item'].']!<br/>';              
            } 
        }
        
        //create file table record and rename invoice doc
        if($error_str == '') { 
            $location_id = 'INV'.$invoice_id;
            $file_id = Calc::getFileId($db);
            $file_name = 'INV'.$file_id.'.pdf';
            $pdf_path_old = $pdf_dir.$doc_name;
            $pdf_path_new = $pdf_dir.$file_name;
            //rename doc to new guaranteed non-clashing name
            if(!rename($pdf_path_old,$pdf_path_new)) {
                $error_str .= 'Could not rename invoice pdf!<br/>'; 
            } 
        }
        
        //create file records and upload to amazon if required
        if($error_str == '') {    
            $file = array();
            $file['file_id'] = $file_id; 
            $file['file_name'] = $file_name;
            $file['file_name_orig'] = $doc_name;
            $file['file_ext'] = 'pdf';
            $file['file_date'] = date('Y-m-d');
            $file['location_id'] = $location_id;
            $file['encrypted'] = false;
            $file['file_size'] = filesize($pdf_path_new); 
            
            if(STORAGE === 'amazon') {
                $s3->putFile($file['file_name'],$pdf_path_new,$error_tmp); 
                if($error_tmp !== '') $error_str.='Could NOT upload files to Amazon S3 storage!<br/>';
            } 
            
            if($error_str == '') {
                $db->insertRecord(TABLE_PREFIX.'file',$file,$error_tmp);
                if($error_tmp != '') $error_str .= 'ERROR creating invoice file record: '.$error_tmp.'<br/>';
            }   
        }   
                
        if($error_str == '') return $invoice_id; else return false;
    }  
    
    //email invoice to user
    public static function sendInvoice($db,ContainerInterface $container,$user_id,$invoice_id,$mail_to,&$error_str) {
        $error_str = '';
        $error_tmp = '';
        $attach_msg = '';
                
        $system = $container['system'];
        $mail = $container['mail'];

        
        $user = Helpers::getUserData($db,'USER_ID',$user_id);

        //get invoice details and invoice doc name
        $sql = 'SELECT * FROM '.TABLE_PREFIX.'invoice WHERE invoice_id = "'.$db->escapeSql($invoice_id).'"';
        $invoice = $db->readSqlRecord($sql); 
        
        //get all files related to invoice
        $attach = array();
        $attach_file = array();

        //NB: only using for download, all files associated with invoice will be attached
        $docs = new Upload($db,$container,TABLE_PREFIX.'file');
        $docs->setup(['upload_location'=>'INV','interface'=>'download']);
        
        $sql = 'SELECT `file_id`,`file_name_orig` FROM `'.TABLE_PREFIX.'file` '.
               'WHERE `location_id` ="INV'.$invoice_id.'" ORDER BY `file_id` ';
        $invoice_files = $db->readSqlList($sql);
        if($invoice_files != 0) {
            foreach($invoice_files as $file_id => $file_name) {
                $attach_file['name'] = $file_name;
                $attach_file['path'] = $docs->fileDownload($file_id,'FILE'); 
                if(substr($attach_file['path'],0,5) !== 'Error' and file_exists($attach_file['path'])) {
                    $attach[] = $attach_file;
                    $attach_msg .= $file_name."\r\n";
                } else {
                    $error_str .= 'Error fetching files for attachment to email!'; 
                }   
            }   
        }
            
        //configure and send email
        if($error_str == '') {
            $subject = SITE_NAME.' invoice '.$invoice['invoice_no'];
            $body = 'Attn. '.$user['name']."\r\n\r\n".
                    'Please see attached invoice['.$invoice['doc_name'].'] and any supporting documents.'."\r\n\r\n";
                        
            if($attach_msg != '') $body .= 'All documents attached to this email: '."\r\n".$attach_msg."\r\n";
                        
            $mail_footer = $system->getDefault('AUCTION_EMAIL_FOOTER','');
            $body .= $mail_footer."\r\n";
                        
            $param = ['attach'=>$attach];
            $mail->sendEmail('',$mail_to,$subject,$body,$error_tmp,$param);
            if($error_tmp != '') { 
                $error_str .= 'Error sending invoice email with attachments to email['. $mail_to.']:'.$error_tmp; 
            }       
        }  
            
        if($error_str == '') return true; else return false;  
    } 

}


?>
