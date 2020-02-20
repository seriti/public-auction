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

class CheckoutWizard extends Wizard 
{
    protected $user;
    protected $temp_token;
    protected $user_id;
    
    //configure
    public function setup($param = []) 
    {
        $this->user = $this->getContainer('user');
        $this->temp_token = $this->user->getTempToken();

        //will return 0 if no logged in user
        $this->user_id = $this->user->getId();

        $param['bread_crumbs'] = true;
        $param['strict_var'] = false;
        $param['csrf_token'] = $this->temp_token;
        parent::setup($param);

        //standard user cols
        $this->addVariable(array('id'=>'ship_option_id','type'=>'INTEGER','title'=>'Shipping option','required'=>true));
        $this->addVariable(array('id'=>'ship_location_id','type'=>'INTEGER','title'=>'Shipping location','required'=>true));
        $this->addVariable(array('id'=>'pay_option_id','type'=>'INTEGER','title'=>'Payment option','required'=>true));
        
        $this->addVariable(array('id'=>'user_email','type'=>'EMAIL','title'=>'Your email address','required'=>false));
        $this->addVariable(array('id'=>'user_name','type'=>'STRING','title'=>'Your name','required'=>false));
        $this->addVariable(array('id'=>'user_cell','type'=>'STRING','title'=>'Your name','required'=>false));
        $this->addVariable(array('id'=>'user_ship_address','type'=>'TEXT','title'=>'Shipping address','required'=>true));
        $this->addVariable(array('id'=>'user_bill_address','type'=>'TEXT','title'=>'Billing address','required'=>true));
        
        //define pages and templates
        $this->addPage(1,'Setup','auction/checkout_page1.php',['go_back'=>true]);
        $this->addPage(2,'Confirm lots','auction/checkout_page2.php');
        $this->addPage(3,'Delivery details','auction/checkout_page3.php');
        $this->addPage(4,'Payment','auction/checkout_page4.php',['final'=>true]);  
        

    }

    public function processPage() 
    {
        $error = '';
        $error_tmp = '';


        //PROCESS shipping and payment options
        if($this->page_no == 1) {

            
            $ship_option_id = $this->form['ship_option_id'];
            $ship_location_id = $this->form['ship_location_id'];
            $pay_option_id = $this->form['pay_option_id'];

            $cart = Helpers::calcCartTotals($this->db,TABLE_PREFIX_AUCTION,$this->temp_token,$ship_option_id,$ship_location_id,$error_tmp);
            if($cart == 0) {
               $error = 'Could not get cart details. ';
               if($this->debug) $error .= $error_tmp; 
               $this->addError($error); 
            } else {
                $sql = 'SELECT name FROM '.TABLE_PREFIX_AUCTION.'ship_location WHERE location_id = "'.$this->db->escapeSql($ship_location_id).'" ';
                $this->data['ship_location'] = $this->db->readSqlValue($sql);
                $sql = 'SELECT name FROM '.TABLE_PREFIX_AUCTION.'ship_option WHERE option_id = "'.$this->db->escapeSql($ship_option_id).'" ';
                $this->data['ship_option'] = $this->db->readSqlValue($sql);
                $sql = 'SELECT name,type_id,config FROM '.TABLE_PREFIX_AUCTION.'pay_option WHERE option_id = "'.$this->db->escapeSql($pay_option_id).'" ';
                $this->data['pay'] = $this->db->readSqlRecord($sql);
                $this->data['pay_option'] = $this->data['pay']['name'];
                
                $this->data['total'] = $cart['total'];
                $this->data['items'] = $cart['items'];
                $this->data['order_id'] = $cart['order_id'];
            }

        } 
        
        //PROCESS additional info required
        if($this->page_no == 2) {
            
        }  
        
        //address details and user register if not logged in
        if($this->page_no == 3) {
            
            //check if an existing user has not logged in
            if($this->user_id == 0) {
                $exist = $this->user->getUser('EMAIL_EXIST',$this->form['user_email']);
                if($exist !== 0 ) {
                    $this->addError('Your email address is already in use! Please <a href="/login">login</a> with that email, or use a different email address.');
                }    
            }

            //register new user if not exist
            if(!$this->errors_found and $this->user_id == 0) {
                
                $password = Form::createPassword();
                $access = 'USER';
                $zone = 'PUBLIC';
                $status = 'NEW';
                $name = $this->form['user_name'];
                $email = $this->form['user_email'];

                $this->user->createUser($name,$email,$password,$access,$zone,$status,$error_tmp);
                if($error_tmp !== '') {
                    $this->addError($error_tmp);
                } else {
                    $user = $this->user->getUser('EMAIL',$email);
                    $remember_me = true;
                    $days_expire = 30;
                    $this->user->manageUserAction('LOGIN_REGISTER',$user,$remember_me,$days_expire);
                    
                    $this->data['user_created'] = true;
                    $this->data['user_name'] = $name;   
                    $this->data['user_email'] = $email;   
                    $this->data['password'] = $password;
                    $this->data['user_id'] = $user[$this->user_cols['id']];
                    //set user_id so wizard knows user created 
                    $this->user_id == $this->data['user_id'];

                    $mailer = $this->getContainer('mail');
                    $to = $email;
                    $from = ''; //default config email from used
                    $subject = SITE_NAME.' user checkout registration';
                    $body = 'Hi There '.$name."\r\n".
                            'You have been registered as a user with us. Please note your credentials below:'."\r\n".
                            'Login email: '.$email."\r\n".
                            'Login Password: '.$password."\r\n\r\n".
                            'Your are logged in for 30 days from device that you processed order from, unless you logout or delete site cookies.'."\r\n".
                            'You can at any point request a password reset or login token to be emailed to you from login screen.';

                    if($mailer->sendEmail($from,$to,$subject,$body,$error_tmp)) {
                        $this->addMessage('Success sending your registration details to['.$to.'] '); 
                    } else {
                        $this->addMessage('Could not email your registration details to['.$to.'] '); 
                        $this->addMessage('This is not a biggie. You are logged in from this device for 30 days, and you can always request a password reset or new login token from login screen using your email address.');
                    } 
                }

            }


            if(!$this->errors_found) {
                $table_extend = TABLE_PREFIX_AUCTION.'user_extend';  

                $data = [];
                $data['user_id'] = $this->user_id;
                $data['cell'] = $this->form['user_cell'];
                $data['ship_address'] = $this->form['user_ship_address'];
                $data['bill_address'] = $this->form['user_bill_address'];

                $extend = $this->db->getRecord($table_extend,['user_id'=>$data['user_id']]);
                if($extend === 0) {
                    $this->db->insertRecord($table_extend,$data,$error_tmp );
                } else {
                    unset($data['user_id']);
                    $where = ['extend_id' => $extend['extend_id']];
                    $this->db->updateRecord($table_extend,$data,$where,$error_tmp );
                }

                if($error_tmp !== '') {
                    $error = 'We could not save your details.';
                    if($this->debug) $error .= $error_tmp;
                    $this->addError($error);
                }
            } 

            //update cart/order with all details AND erase temp_token 
            if(!$this->errors_found) {
                $table_order = TABLE_PREFIX_AUCTION.'order';
                $data = [];
                //NB: assign user id which turns cart into order
                $data['user_id'] = $this->user_id;
                $data['date_create'] = date('Y-m-d H:i:s');
                $data['ship_address'] = $this->form['user_ship_address'];
                $data['temp_token'] = '';

                $where = ['temp_token' => $this->temp_token];
                $this->db->updateRecord($table_order,$data,$where,$error_tmp);
                if($error_tmp !== '') {
                    $error = 'We could not update order details.';
                    if($this->debug) $error .= $error_tmp;
                    $this->addError($error);
                }
            } 

            //finally email order details 
            if(!$this->errors_found) {
                $subject = 'Initial order details';
                $message = 'You will be contacted after completion of auction. Please contact us if you wish to cancel your order.';
                $param = [];
                Helpers::sendOrderMessage($this->db,TABLE_PREFIX_AUCTION,$this->container,$this->data['order_id'],$subject,$message,$param,$error_tmp);
                if($error_tmp == '') {
                    $this->addMessage('Order details sent to email address['.$this->data['user_email'].'] '); 
                } else {
                    $this->addMessage('We could not send order details to['.$this->data['user_email'].']. Please check order details on your account page.'); 
                }
            }   
        }  
    }

    public function setupPageData($no)
    {
        //save user data once only if user logged in
        if($this->user_id != 0 and !isset($this->data['user_id'])) {
            $this->data['user_id'] = $this->user_id;    
            $this->data['user_name'] = $this->user->getName();
            $this->data['user_email'] = $this->user->getEmail();

            $this->saveData('data');

            //get extended user info if any
            $sql = 'SELECT * FROM '.TABLE_PREFIX_AUCTION.'user_extend WHERE user_id = "'.$this->user_id.'" ';
            $user_extend = $this->db->readSqlRecord($sql);
            
            if($user_extend != 0) {
                $this->form['user_email_alt'] = $user_extend['email_alt'];
                $this->form['user_cell'] = $user_extend['cell'];
                $this->form['user_ship_address'] = $user_extend['ship_address'];
                $this->form['user_bill_address'] = $user_extend['bill_address'];

                $this->saveData('form');
            } 
        }
    }

}

?>


