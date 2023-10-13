<?php
namespace App\Auction;

use Exception;

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
use Seriti\Tools\TABLE_USER;

use App\Auction\Helpers;

use App\Payment\Helpers as PaymentHelpers;
use App\Payment\Gateway;

class PaymentWizard extends Wizard 
{
    protected $user;
    protected $table_prefix;
    protected $table_prefix_pmt;
    
    //configure
    public function setup($param = []) 
    {
        $error = '';
        if(!defined('MODULE_AUCTION')) $error .= 'Auction module not defined. ';
        if(!defined('MODULE_PAYMENT')) $error .= 'Payment module not defined. ';
        if($error !== '')  throw new Exception('CONSTANT_NOT_DEFINED: '.$error);
        
        $this->table_prefix = MODULE_AUCTION['table_prefix'];
        $this->table_prefix_pmt = MODULE_PAYMENT['table_prefix'];

        $this->user = $this->getContainer('user');
        
        //for use in templates
        $this->data['user_id'] = $this->user->getId();
        $this->data['user_name'] = $this->user->getName();
        $this->data['user_email'] = $this->user->getEmail();

        $param = [];
        $param['bread_crumbs'] = true;
        $param['strict_var'] = false;
        //NB: Assumes user will always be logged in order to request payment
        $param['csrf_token'] = $this->user->getCsrfToken();
        parent::setup($param);

        //wizard variables
        $this->addVariable(array('id'=>'pay_option_id','type'=>'INTEGER','title'=>'Payment option','required'=>true));
                
        
        //pages and templates
        $this->addPage(1,'Select payment option','auction/payment_page1.php',['go_back'=>false]);
        $this->addPage(2,'Process payment','auction/payment_page2.php',['final'=>true]);
            

    }

    //tell wizard what order we are dealing with
    public function initialConfig() 
    {
        if(isset($_GET['id'])) {
            $this->data['invoice_id'] = Secure::clean('integer',$_GET['id']);
            $this->data['invoice'] = Helpers::getInvoiceDetails($this->db,$this->table_prefix,$this->data['invoice_id'],$error);
            if($error !== '') {
                throw new Exception('INVOICE_PAYMENT_ERROR: Could not make payment for unrecognised Invoice['.$this->data['invoice_id'].'].');
                exit;
            } 
            $this->data['pay_amount'] = $this->data['invoice']['invoice']['total'] - $this->data['invoice']['payment_total'];

            $lot_html = '';
            foreach($this->data['invoice']['lots'] AS $lot_id => $lot) {
                $lot_html .= Helpers::getLotSummary($this->db,$this->table_prefix,$this->container->s3,$lot_id);
            }
            $this->data['lots'] = $lot_html;

            $this->saveData('data');
            
            //Order form allows specification of payment option BUT this may not have a valid provider code as independant of Payment module  
            //$this->form['pay_option_id'] = $this->data['invoice']['invoice']['pay_option_id'];
        }

    }

    public function processPage() 
    {
        $error = '';
        $error_tmp = '';

        //process address details and user register if not logged in
        if($this->page_no == 1) {
            //get selected payment provider details
            $sql = 'SELECT name,provider_code,status FROM '.$this->table_prefix.'pay_option '.
                   'WHERE option_id = "'.$this->db->escapeSql($this->form['pay_option_id']).'" ';
            $this->data['pay'] = $this->db->readSqlRecord($sql);
            
            $provider = PaymentHelpers::getProvider($this->db,$this->table_prefix_pmt,'CODE',$this->data['pay']['provider_code']);
            if($provider == 0) {
                $this->addError('Payment provider not recognised');
            } else {
                $this->data['pay']['type_id'] = $provider['type_id'];
                $this->data['pay']['provider_id'] = $provider['provider_id'];
            }    

            //finally update order with payment details
            /*
            if(!$this->errors_found) {
                $table_order = $this->table_prefix.'order';
                $data = [];
                $data['pay_option_id'] = $this->form['pay_option_id'];
                $data['date_update'] = date('Y-m-d H:i:s');
                //$data['status'] = 'ACTIVE';
                
                $where = ['order_id' => $this->data['order_id']];
                $this->db->updateRecord($table_order,$data,$where,$error_tmp);
                if($error_tmp !== '') {
                    $error = 'We could not save order details.';
                    if($this->debug) $error .= $error_tmp;
                    $this->addError($error);
                } 
            }
            */

            //finally SETUP payment gateway form if that option requested, or email EFT instructions
            if(!$this->errors_found) {
                
                if($provider['type_id'] === 'EFT_TOKEN') {
                    //send user message with payment instructions
                    $param = ['cc_admin'=>true];
                    $subject = 'EFT Payment instructions';
                    $message = 'Please use payment Reference: <strong>Invoice-'.$this->data['invoice']['invoice']['invoice_no'].'</strong><br/>'.
                               'We will ship your items once payment is received. <br/>'. 
                               'Our bank account details:<br/>'.nl2br($provider['config']);

                    Helpers::sendInvoicePaymentMessage($this->db,$this->table_prefix,$this->container,$this->data['invoice_id'],$subject,$message,$param,$error_tmp);
                    if($error_tmp !== '') {
                        $message = 'We could not email your invoice details and EFT payment instructions.';
                        if($this->debug) $message .= $error_tmp;
                        $this->addMessage($message);
                    } else {
                        $this->addMessage('Successfully emailed you Invoice details and Payment instructions.');
                    }
                }

                if($provider['type_id'] === 'GATEWAY_FORM') {
                    $gateway = new Gateway($this->db,$this->container);
                    $gateway->setup('AUCTION',$provider['provider_id']);

                    $reference = $this->data['invoice']['invoice']['invoice_no'];
                    $reference_id =$this->data['invoice_id']; 
                    $amount = $this->data['pay_amount'];
                    $currency = CURRENCY_ID;                    
                    $gateway_form = $gateway->getGatewayForm($reference,$reference_id,$amount,$this->data['user_email'],$error_tmp);
                    if($error_tmp !== '') {
                        $error .= 'Could not setup payment gateway! Please try again later or select an alternative payment method.';
                        if($this->debug) $error .= $error_tmp;
                        $this->addError($error);
                    } else {
                        $this->data['gateway_form'] = $gateway_form;
                    }
                }
            }
               
        } 

        //final page so no fucking processing possible moron
        if($this->page_no == 2) {

            

            

            
        } 
    }

    

}

?>


