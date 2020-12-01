<?php
namespace App\Auction;

use Seriti\Tools\Wizard;
use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Doc;
use Seriti\Tools\Calc;
use Seriti\Tools\Secure;
use Seriti\Tools\Plupload;
use Seriti\Tools\STORAGE;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_TEMP;
use Seriti\Tools\UPLOAD_DOCS;

use App\Auction\Helpers;

class InvoiceWizard extends Wizard 
{
    
    protected $source = ['USER_ID'=>'User ID','USER_CODE'=>'User Buyer No.','USER_EMAIL'=>'User email address','ORDER'=>MODULE_AUCTION['labels']['order'].' ID'];

    //configure
    public function setup($param = []) 
    {
        $param = ['bread_crumbs'=>true,'strict_var'=>false];
        $param['csrf_token'] = $this->getContainer('user')->getCsrfToken();
        parent::setup($param);

        $this->addVariable(array('id'=>'source_type','type'=>'STRING','title'=>'Initial source'));
        $this->addVariable(array('id'=>'source_id','type'=>'STRING','title'=>'Initial source value'));
        $this->addVariable(array('id'=>'xtra_item_no','type'=>'INTEGER','title'=>'Additional invoice items','new'=>INVOICE_XTRA_ITEMS));
        $this->addVariable(array('id'=>'invoice_for','type'=>'STRING','title'=>'Invoice For'));
        $this->addVariable(array('id'=>'invoice_comment','type'=>'TEXT','title'=>'Invoice Comment','required'=>false));
        $this->addVariable(array('id'=>'email','type'=>'EMAIL','title'=>'Primary Email address'));
        $this->addVariable(array('id'=>'email_xtra','type'=>'EMAIL','title'=>'Secondary Email address','required'=>false));
        $this->addVariable(array('id'=>'vat','type'=>'DECIMAL','title'=>'VAT amount','max'=>1000000));

        //define pages and templates
        $this->addPage(1,'Select User','auction/invoice_wizard_start.php');
        $this->addPage(2,'Manage invoice items','auction/invoice_wizard_items.php');
        $this->addPage(3,'Review final invoice','auction/invoice_wizard_review.php');
        $this->addPage(4,'Wizard complete','auction/invoice_wizard_final.php',array('final'=>true));    

    }

    public function processPage() 
    {
        $error = '';
        $error_tmp = '';

        //PROCESS select user using multiple options
        if($this->page_no == 1) {
            $source_type = $this->form['source_type'];
            $source_id = $this->form['source_id'];
            $xtra_item_no = $this->form['xtra_item_no'];
            $order_id = 0;

            if($source_type === 'USER_CODE') {
                $user = Helpers::getUserData($this->db,'BID_NO',$source_id);
                if($user === 0 ) $this->addError('INVALID Bid no['.$source_id.']');
            }
            
            if($source_type === 'USER_ID') {
                $user = Helpers::getUserData($this->db,'USER_ID',$source_id);
                if($user === 0 ) $this->addError('INVALID User ID['.$source_id.']');
            }

            if($source_type === 'USER_EMAIL') {
                $user = Helpers::getUserData($this->db,'USER_EMAIL',$source_id);
                if($user === 0 ) $this->addError('INVALID User Email['.$source_id.']');
            }

            if($source_type === 'ORDER') {
                $order_id = $source_id;
                $order = Helpers::getOrderDetails($this->db,TABLE_PREFIX,$order_id,$error_tmp);
                if($error_tmp !== '') {
                    $this->addError('INVALID '.MODULE_AUCTION['labels']['order'].' ID['.$order_id.'] :'.$error_tmp);
                } else {
                    $user_id = $order['order']['user_id'];
                    $user = Helpers::getUserData($this->db,'USER_ID',$user_id);
                    if($user === 0 ) $this->addError('INVALID '.MODULE_AUCTION['labels']['order'].' linked user ID['.$user_id.']');
                }    
            }

            if(!$this->errors_found) {

                //invoicing an order assumes that order created only with unsold lots after an auction
                if($source_type === 'ORDER') {
                    $sql = 'SELECT I.lot_id,L.lot_no,L.name,L.description,I.price AS bid_final,L.weight,L.volume,L.status '.
                           'FROM '.TABLE_PREFIX.'order_item AS I LEFT JOIN '.TABLE_PREFIX.'lot AS L ON(I.lot_id = L.lot_id) '.
                           'WHERE I.order_id = "'.$this->db->escapeSql($order_id).'" ';
                    $lots = $this->db->readSqlArray($sql);
                } else {
                    $sql = 'SELECT lot_id,lot_no,name,description,bid_final,weight,volume,status '.
                           'FROM '.TABLE_PREFIX.'lot '.
                           'WHERE auction_id = "'.AUCTION_ID.'" AND buyer_id = "'.$user['user_id'].'" '.
                           'ORDER BY lot_no';
                    $lots = $this->db->readSqlArray($sql);
                    if($lots == 0) $this->addError('No lots allocated to User ID['.$user['user_id'].'] for auction.');    
                }
                
            }           

            //check lot price is best and not already sold
            foreach($lots as $lot_id => $lot) {
                Helpers::checkLotPriceValid($this->db,TABLE_PREFIX,$lot_id,AUCTION_ID,$lot['bid_final'],$error_tmp);
                if($error_tmp !== '') $this->addError($error_tmp);
            }

            if(!$this->errors_found) {
                //invoice items setup
                $item_no = 0;
                $item_total = 0.00;
                $item_vat = 0.00;
                $item_weight = 0;
                $item_volume = 0;

                $items = array();
                $items[0][0] = 'Quantity';
                $items[1][0] = 'Description';
                $items[2][0] = 'Price';
                $items[3][0] = 'Total';
                //NOT displayed but needed for updating lot status
                $items[4][0] = 'Lot ID';

                foreach($lots as $lot_id => $lot) {
                    $item_no++;
                    $items[0][$item_no] = '1';
                    $items[1][$item_no] = 'Lot No['.$lot['lot_no'].'] : '.$lot['name']; //.': '.$lot['description']
                    $items[2][$item_no] = $lot['bid_final'];
                    $items[3][$item_no] = $lot['bid_final'];
                    $items[4][$item_no] = $lot_id;
                    
                    $item_total += round($lot['bid_final'],0);
                    $item_weight += round($lot['weight'],0);
                    $item_volume += round($lot['volume'],0);
                }

                //calculate auction fee and add as item
                $fee = round(($item_total * AUCTION_FEE),0);
                $item_no++;
                $items[0][$item_no] = '1';
                $items[1][$item_no] = 'Buyers fee @'.(AUCTION_FEE * 100).'%';
                $items[2][$item_no] = $fee;
                $items[3][$item_no] = $fee;
                $items[4][$item_no] = '0';
                $item_total += $fee;
            }    
            

            if(VAT_CALC) $item_vat = round(($item_total*VAT_RATE),2);
                    
            //add $xtra_item_no empty rows to items
            for($i = 1; $i <= $xtra_item_no; $i++) {
              $item_no++;
              $items[0][$item_no] = '';
              $items[1][$item_no] = '';
              $items[2][$item_no] = '';
              $items[3][$item_no] = '';
              $items[4][$item_no] = '0';
            }   
            
            $this->data['user'] = $user;    
            //NB: use this value in all subsequent steps so that even if user changes active auction, not a problem
            $this->data['auction_id'] = AUCTION_ID;   
            $this->data['order_id'] = $order_id;
            //NB: lots data remains unchanged and is used to update lot status after invoice issued
            $this->data['lots'] = $lots;
            //NB: items can be modified in wizard, and random shit added, primarily for invoice pdf creation
            $this->data['items'] = $items;
            $this->data['item_vat'] = $item_vat;
            $this->data['item_total'] = $item_total;
            $this->data['item_weight'] = $item_weight;
            $this->data['item_volume'] = $item_volume;
        } 
        
        //PROCESS invoice options and manual items adjustments
        if($this->page_no == 2) {
            $items=$this->data['items'];
            $vat=$this->form['vat'];
            
            //check invoice_no unique
            $sql='SELECT * FROM '.TABLE_PREFIX.'invoice '.
                 'WHERE invoice_no = "'.$this->db->escapeSql($this->form['invoice_no']).'" ';
            $invoice_dup=$this->db->readSqlRecord($sql);
            if($invoice_dup!=0) {
                $this->addError('Invoice No['.$this->form['invoice_no'].'] has been used before!'); 
            }  
            
            
            $item_no=count($items[0])-1; //first row are headers
            $item_total=0.00;
            //process standard items
            for($i=1;$i<=$item_no;$i++) {  
                //Validate::integer('Quantity',0,10000,$_POST['quant_'.$i],$error);
                $items[0][$i]=Secure::clean('float',$_POST['quant_'.$i]);
                $items[1][$i]=Secure::clean('string',$_POST['desc_'.$i]);
                $items[2][$i]=Secure::clean('float',$_POST['price_'.$i]);
                $items[3][$i]=Secure::clean('float',$_POST['total_'.$i]);
                $item_total+=$items[3][$i];
                
                if(round($items[0][$i]*$items[2][$i],2)!=round($items[3][$i],2)) {
                    $this->addError('Invoice item in Row['.$i.'] invalid total!');
                }  
                if($items[0][$i]==0) $items[0][$i]='';
            }  
          
            if(VAT_CALC) {
                $vat = round(($item_total*VAT_RATE),2);
                $this->form['vat'] = $vat; 
            } else {
                //check if vat rate valid(if entered)
                if($vat!=0 and $vat>(VAT_RATE*$item_total)) $this->addError('VAT entered is greater than '.(VAT_RATE*100).'% of sub-total!');
            }

            
            $this->data['items'] = $items;
            $this->data['item_total'] = $item_total;
        }  
        
        //final review/check and processing
        if($this->page_no == 3) {
            $items = $this->data['items'];
            $user = $this->data['user'];
            $email = $this->form['email'];
            $email_xtra = $this->form['email_xtra'];

            $system = $this->getContainer('system');
            if(STORAGE === 'amazon') $s3 = $this->getContainer('s3'); else $s3 = '';
                
            //get rid of empty rows in item array
            $item_no = count($items[0])-1; //first line contains headers
            for($i = 1; $i <= $item_no; $i++) {
                if($items[0][$i] == 0) {
                    unset($items[0][$i]);
                    unset($items[1][$i]);
                    unset($items[2][$i]);
                    unset($items[3][$i]);
                }               
            } 
            //get valid item_no after removing empty rows
            $item_no = count($items[0])-1;
            
            //specify all invoice paramaters
            $invoice = array();
            $invoice['auction_id'] = $this->data['auction_id'];
            $invoice['order_id'] = $this->data['order_id'];
            $invoice['items'] = $items;
            $invoice['item_no'] = $item_no;
            $invoice['no'] = INVOICE_PREFIX.HelpersPayment::getInvoiceNo($this->db);
            $invoice['for'] = $this->form['invoice_for'];
            $invoice['comment'] = $this->form['invoice_comment'];
            $invoice['subtotal'] = $this->data['item_total'];
            $invoice['vat'] = $this->form['vat'];
            $invoice['total'] = $invoice['subtotal']+$invoice['vat'];
              
            
            HelpersPayment::createInvoicePdf($this->db,$system,$this->data['auction_id'],$user,$invoice,$doc_name,$error);
            if($error != '') {
                $this->addError('Could not create invoice pdf: '.$error); 
            } else {    
                $this->data['doc_name'] = $doc_name;
                $this->addMessage('Successfully created invoice pdf: "'.$doc_name.'" ');

                //update lot status to sold and update any order item status
                for($i = 1; $i <= $item_no; $i++) {
                    $lot_id = $items[4][$i];
                    $price = $items[2][$i];
                    if($lot_id != 0) {
                        Helpers::updateSoldLot($this->db,TABLE_PREFIX,$lot_id,$price,$this->data['auction_id'],$user['user_id'],$error);
                        if($error !== '') $this->addError($error); 
                    }
                } 
                if(!$this->errors_found) $this->addMessage('Successfully updated all lots and linked orders.');
            } 

            //create invoice records and upload pdf to S3
            if(!$this->errors_found) {
                $this->data['invoice_id'] = HelpersPayment::saveInvoice($this->db,$system,$s3,$user['user_id'],$invoice,$doc_name,$error);
                if($error != '') {
                    $this->addError('Could not save invoice data: '.$error); 
                } else {    
                    $this->addMessage('Successfully saved all invoice data.');
                }  
            }
            
            //now that we have invoice id and pdf assign any additional documents and upload to S3
            if(!$this->errors_found) {
                $docs = array();
                $doc_no = 0;
                foreach($_POST as $key => $value) {
                    if(strpos($key,'file_id_') !== false) {
                        $doc_no++;
                        $file_id = substr($key,8);
                        $docs[$file_id]['name'] = $value;
                    }
                    if(strpos($key,'file_name_') !== false) {
                        $file_id = substr($key,10);
                        $docs[$file_id]['name_original'] = $value;
                    }   
                }
              
                //rename using incremental file id        
                if($doc_no > 0) {
                    $temp_dir = BASE_UPLOAD.UPLOAD_TEMP;
                    $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
                    
                    foreach($docs as $id => $doc) {
                        $rename_from_path = '';
                        $rename_to_path = '';
                        
                        $info = Doc::fileNameParts($doc['name']);
                        $info['extension'] = strtolower($info['extension']);
                        
                        $file_id = Calc::getFileId($this->db);
                        $file_name = 'INV'.$file_id.'.'.$info['extension'];
                        //NB Plupload placed docs in temporary folder
                        $path_old = $temp_dir.$doc['name'];
                        $path_new = $upload_dir.$file_name;
                        //rename doc to new guaranteed non-clashing name
                        if(!rename($path_old,$path_new)) {
                            $this->addError('Could not rename invoice supporting document['.$doc['name_original'].']'); 
                        } else {
                            $docs[$id]['file_name'] = $file_name;
                            $docs[$id]['file_id'] = $file_id;
                            $docs[$id]['file_ext'] = $info['extension'];
                            $docs[$id]['file_size'] = filesize($path_new);
                        }  
                    } 
                }
            }   
            
            //create file records and upload documents to amazon if required
            if(!$this->errors_found and $doc_no > 0) {
                
                if(STORAGE === 'amazon') $s3_files = [];
            
                foreach($docs as $id => $doc) {
                    $file = array();
                    $file['file_id'] = $doc['file_id']; 
                    $file['file_name'] = $doc['file_name'];
                    $file['file_name_orig'] = $doc['name_original'];
                    $file['file_ext'] = $doc['file_ext'];
                    $file['file_date'] = date('Y-m-d');
                    $file['location_id'] = 'INV'.$this->data['invoice_id'];
                    $file['encrypted'] = false;
                    $file['file_size'] = $doc['file_size']; 
                    
                    if(STORAGE === 'amazon') {
                        $path = $upload_dir.$doc['file_name'];
                        $s3_files[] = ['name'=>$file['file_name'],'path'=>$path];
                    } 
                    //NB: TABLE NAME IN CLIENT MODULE invoice wizard IS "files" FUUUUCK!!!!
                    $this->db->insertRecord(TABLE_PREFIX.'file',$file,$error);
                    if($error != '') $this->addError('ERROR creating supporting document file record: '.$error);
                        
                }
                
                if(STORAGE === 'amazon') {
                    $s3->putFiles($s3_files,$error);
                    if($error != '' ) {
                        $error = 'Amazon S3 document upload error ';
                        if($this->debug) $error .= ': ['.$error.']';
                        $this->addError($error);
                    }    
                }   
            }  
            
            //setup file list with links for final page
            if(!$this->errors_found) {
                $location_id = 'INV'.$this->data['invoice_id'];
                $file_html = '<ul>';
                $sql = 'SELECT file_id,file_name_orig FROM '.TABLE_PREFIX.'file '.
                       'WHERE location_id ="'.$location_id.'" ORDER BY file_id ';
                $invoice_files = $this->db->readSqlList($sql);
                if($invoice_files != 0) {
                    foreach($invoice_files as $file_id => $file_name_orig) {
                        $file_path = 'invoice_files?mode=download&id='.$file_id; 
                        $file_html .= '<li><a href="'.$file_path.'" target="_blank">'.$file_name_orig.'</a></li>';
                    }   
                }
                $file_html .= '</ul>' ; 
                $this->data['files'] = $file_html;
            }  
            
            //finally send invoice with all supporting documents  
            if(!$this->errors_found) {  
                HelpersPayment::sendInvoice($this->db,$this->container,$user['user_id'],$this->data['invoice_id'],$email,$error);
                if($error != '') {
                    $error_tmp = 'Could not send invoice to "'.$email.'" ';
                    if($this->debug) $error_tmp .= ': '.$error; 
                    $this->addError($error_tmp);
                } else {
                    $this->addMessage('Successfully sent invoice to "'.$email.'" ');
                }  
                if($email_xtra != '' and $error == '') {
                    HelpersPayment::sendInvoice($this->db,$this->container,$user['user_id'],$this->data['invoice_id'],$email_xtra,$error);
                    if($error != '') {
                        $error_tmp = 'Could not send invoice to "'.$email_xtra.'" ';
                        if($this->debug) $error_tmp .= ': '.$error; 
                        $this->addError($error_tmp);
                    } else {
                        $this->addMessage('Successfully sent invoice to "'.$email_xtra.'" ');
                    }
                }  
            }   
          
        }  
    }

    public function setupPageData($no)
    {
        $this->data['source'] = $this->source;

        //for upload of any supporting documents
        if($no == 3) {
            $param = array();
            $param['upload_url'] = '/admin/data/upload?mode=upload';
            $param['list_id'] = 'file-list';
            $param['reset_id'] = 'reset-upload';
            $param['start_id'] = 'start-upload';
            $param['browse_id'] = 'browse-files';
            $param['browse_txt'] = 'Select any supporting documents.';
            $param['reset_txt'] = 'Reset supporting documents.';
              
            $plupload = new Plupload($param);
            
            //creates necessay includes and custom js
            $this->data['upload_div'] = $plupload->setupFileDiv();
            $this->javascript .= $plupload->getJavascript();
            
        }
    }



}

?>


