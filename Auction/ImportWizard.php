<?php 
namespace App\Contact;

use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Doc;
use Seriti\Tools\Calc;
use Seriti\Tools\Secure;
use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;
use Seriti\Tools\ContainerHelpers;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_TEMP;

use App\Contact\Helpers;

use Psr\Container\ContainerInterface;

//NB: legacy code, does not use Wizard class
class ImportWizard
{
    use IconsClassesLinks;
    use MessageHelpers;
    use ContainerHelpers;
   
    protected $container;
    protected $container_allow = ['user'];

    protected $db;
    protected $debug = false;

    protected $mode = '';
    protected $errors = array();
    protected $errors_found = false; 
    protected $messages = array();

    protected $user_id;

    protected $file_formats = array('GOOGLE'=>'Google CSV format','OUTLOOK'=>'Outlook CSV format','SERITI'=>'Seriti contact format');
    protected $upload_dir = BASE_UPLOAD.UPLOAD_TEMP;
    protected $max_size = 1000000;

    public function __construct(DbInterface $db, ContainerInterface $container) 
    {
        $this->db = $db;
        $this->container = $container;
               
        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;
    }

    public function process()
    {
        $html = '';
        $error = '';
        $count = 0;
        $update_contact = false;

        $this->mode = 'contacts';
        if(isset($_GET['mode'])) $this->mode = Secure::clean('basic',$_GET['mode']);

        if($this->mode === 'import') {

            $file_format = Secure::clean('alpha',$_POST['file_format']);
            if(!array_key_exists($file_format,$this->file_formats)) {
                $this->addError('Selected file format['.$file_format.'] INVALID!');
            }  
            
            if(isset($_POST['update_contact']) and $_POST['update_contact'] === 'YES') {
                $update_contact = true;
            } else {
                $update_contact = false;
            }  
              
              
            $file_options = array();
            $file_options['upload_dir'] = $this->upload_dir;
            $file_options['allow_ext'] = array('csv','txt');
            $file_options['max_size'] = $max_size;
            $save_name = 'import_contacts';
            $file_name = Form::uploadFile('import_file',$save_name,$file_options,$error);
            if($error !== '') {
                if($error !== 'NO_FILE') {
                    $this->addError('Import file: '.$error);
                } else {
                    $this->addError('NO file selected for import! Please click [Browse] button and select a valid format file');
                }    
            } else {
                $import_file_path = $this->upload_dir.$file_name;
                if(!file_exists($import_file_path)) {
                    $this->addError('Import File['.$import_file_path.'] does not exist');
                }   
            }  

            if($this->errors_found) {
                $this->mode = 'contacts'; 
            } else {
                //Convert file encoding to UTF8
                if($file_format === 'OUTLOOK' or $file_format === 'OUTLOOK') {
                    $contents = file_get_contents($import_file_path);
                    $encoding = 'ISO-8859-1'; 
                    if($file_format === 'OUTLOOK') $encoding = 'ISO-8859-1';
                    if($file_format === 'GOOGLE') $encoding = 'UTF-16'; //BOM specifies UTF-16LE but this causes convertion error
                    $contents = mb_convert_encoding($contents,'UTF-8',$encoding); 
                    file_put_contents($import_file_path,$contents);
                }    
                
                $handle = fopen($import_file_path,'r');
                $error_file = false;
                $i = 0;
                $insert = 0;
                $found = 0;
                $update = 0;
                while(($line = fgetcsv($handle,0,",",'"')) !== FALSE) {
                  $i++;
                  $value_num = count($line);
                  
                  if($i == 1) {
                        Helpers::checkContactFormat($file_format,$line,$error);
                        if($error !== '') {
                            $this->addError($file_format." file format errors:\r\n".$error);
                            $error_file = true;
                        }  
                  } 
                  
                  if(!$error_file and $i > 1 and $value_num > 5) {  
                        $status = Helpers::importContact($this->db,$update_contact,$file_format,$line,$error);
                        if($error !== '') {
                            $this->addError($error);
                        } else {
                            if(substr($status,0,5) === 'FOUND') $found++;  
                            if($status === 'FOUND_UPDATE') $update++;
                            if($status === 'NEW') $insert++;
                        }    
                  }
                }  
                fclose($handle);
                
                $this->addMessage('Imported <strong>'.$insert.'</strong> NEW contacts.');
                $this->addMessage('Found <strong>'.$found.'</strong> Existing/Duplicate contacts, '.
                                  'updated <strong>'.$update.'</strong> contacts.');
            }  
        }

        if($this->mode === 'contacts') {
            $html .= '<div id="edit_div">'.
                     '<form method="post" id="import_csv_file" action="?mode=import" enctype="multipart/form-data">';
    
            $html .= '<div class="row">';
            $list_param = [];
            $list_param['class'] = 'form-control edit_input';     
            $html .= '<div class="'.$this->classes['col_label'].'">Select file format:</div><div class="col-sm-6">'.
                     Form::arrayList($this->file_formats,'file_format',$file_format,true,$list_param).
                     '</div>';
            $html .= '</div>';
    
            $html .= '<div class="row">';
            $html .= '<div class="'.$this->classes['col_label'].'">Select file:</div><div class="col-sm-6">'.
                     Form::fileInput('import_file','',$list_param).
                     '</div>';     
            $html .= '</div>';
    
            $html .= '<div class="row">';
            $html .= '<div class="'.$this->classes['col_label'].'">Update contacts with same email/phone?:</div><div class="col-sm-6">'.
                     Form::checkBox('update_contact','YES',$update_contact,'edit_input').
                     '</div>';     
            $html .= '</div>';
    
            $html .= '<div class="row">';
            $html .= '<div class="col-sm-6"><input type="submit" class="btn btn-primary" value="Import selected contact file"></div>';
            $html .= '</div>';
    
            $html .= '</form></div>';
        }

        $html = $this->viewMessages().$html;
            
        return $html;
    }
}
?>