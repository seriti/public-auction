<?php
namespace App\Auction;

use Seriti\Tools\SetupModule;

use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;

class Setup extends SetupModule
{
    public function setup() {
        //upload_dir is NOT publically accessible
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        $this->setUpload($upload_dir,'PRIVATE');

        $param = [];
        $param['info'] = 'Specify email footer text / contact details';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','AUCTION_EMAIL_FOOTER','Email footer',$param);

        $param = [];
        $param['info'] = 'Specify auction catalogue footer text / contact details';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','AUCTION_CATALOGUE_FOOTER','Catalogue footer',$param);

        $param = [];
        $param['info'] = 'Specify invoice footer text / bank account details / any info you require to be added.';
        $param['rows'] = 10;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','AUCTION_INVOICE_FOOTER','Invoice PDF footer',$param);

        $param = [];
        $param['info'] = 'Select the image you would like to use as a signature on invoices and other documents(max 50KB)';
        $param['max_size'] = 50000;
        $param['value'] = 'images/sample_sig.jpeg';
        $this->addDefault('IMAGE','AUCTION_SIGN','Invoice signature',$param);

        $param = [];
        $param['info'] = 'Specify the name and title you wish to have below signature im age.';
        $param['value'] = 'Chief Executive Officer';
        $this->addDefault('TEXT','AUCTION_SIGN_TXT','Invoice signature subtext',$param);

        $param = [];
        $param['info'] = 'Select the number of lots per page when capturing auction results';
        $param['value'] = 10;
        $param['options'] = array(10=>'10 lots per page',20=>'20 lots per page',50=>'50 lots per page');
        $this->addDefault('SELECT','AUCTION_LOTS_PER_PAGE','Auction Lots per page',$param);

        $param = [];
        $param['info'] = 'Select the lot table header options for catalogs';
        $param['value'] = 'NONE';
        $param['options'] = array('NONE'=>'No headers','ALL'=>'Header in ALL catalog documents');
        $this->addDefault('SELECT','AUCTION_CATALOG_HEADER','Catalog '.MODULE_AUCTION['labels']['category'].' headers',$param);
    }    
}
