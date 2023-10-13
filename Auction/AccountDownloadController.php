<?php
namespace App\Auction;


use Exception;
use Seriti\Tools\Upload;
use Seriti\Tools\Secure;

use Psr\Container\ContainerInterface;

//use for allowing download of account user linked files ONLY
class AccountDownloadController
{
    protected $container;
    protected $db;
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container['mysql'];
    }

    public function __invoke($request, $response, $args)
    {
        $valid_link = false;
        $file_table = $this->table_prefix.'file';
        $user = $this->container->user;
        $error = '';

        $file_id = Secure::clean('integer',$_GET['id']);

        //get file details and verify user has access rights
        $sql = 'SELECT `file_name_orig`,`location_id` '.
               'FROM `'.$file_table.'` WHERE `file_id` = "'.$this->db->escapeSql($file_id).'" ';
        $file_rec = $this->db->readSqlRecord($sql);
        if($file_rec == 0 ) { 
            $error = 'Inval;id File ID['.$file_id.']';
        } else {    
            $location = substr($file_rec['location_id'],0,3);
            $loc_id = substr($file_rec['location_id'],3);

            $sql = '';
            if($location === 'INV') {
                $sql = 'SELECT user_id FROM '.$this->table_prefix.'invoice WHERE invoice_id = "'.$loc_id.'" ';
            }

            if($sql === '') {
                $error = 'Invalid file location['.$location.']';
            } else {    
                $file_user_id = $this->db->readSqlValue($sql,0);

                if($user->getId() === $file_user_id) {
                    $valid_link = true;
                } else {
                    throw new Exception('FILE_DOWNLOAD_ERROR: File id['.$file_id.'] not linked to user!');
                }
            }
        }

        if($error !== '') {
            throw new Exception('FILE_DOWNLOAD_ERROR:'.$error.'!');
        }


        if($valid_link === true) {
            $file_obj = new Upload($this->db,$this->container,$file_table);
            $file_obj->setup(['upload_location'=>'ALL','interface'=>'download']);
            
            return $file_obj->fileDownload($file_id);
            exit;
        }  
    }
}