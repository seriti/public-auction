<?php
namespace App\Auction;

use Seriti\Tools\SetupModuleData;

class SetupData extends SetupModuledata
{

    public function setupSql()
    {
        $this->tables = ['auction','lot','condition','category','type','file','order','order_item','order_message','invoice','invoice_item',
                         'pay_option','ship_option','ship_location','ship_cost','payment','user_extend','seller','index_term'];

        $this->addCreateSql('category',
                            'CREATE TABLE `TABLE_NAME` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `id_parent` int(11) NOT NULL,
                              `title` varchar(255) NOT NULL,
                              `level` int(11) NOT NULL,
                              `lineage` varchar(255) NOT NULL,
                              `rank` int(11) NOT NULL,
                              `rank_end` int(11) NOT NULL,
                              `category_type` varchar(64) NOT NULL,
                              `category_link` varchar(255) NOT NULL,
                              PRIMARY KEY (`id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8'); 

        $this->addCreateSql('type',
                            'CREATE TABLE `TABLE_NAME` (
                              `type_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(250) NOT NULL,
                              `sort` int(11) NOT NULL,
                              `status` varchar(64) NOT NULL,
                               PRIMARY KEY (`type_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('auction',
                            'CREATE TABLE `TABLE_NAME` (
                              `auction_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(250) NOT NULL,
                              `summary` text NOT NULL,
                              `description` text NOT NULL,
                              `postal_only` tinyint(1) NOT NULL,
                              `date_start_postal` DATETIME NOT NULL,
                              `date_end_postal` DATETIME NOT NULL,
                              `date_start_live` DATETIME NOT NULL,
                              `status` varchar(64) NOT NULL,
                               PRIMARY KEY (`auction_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('lot',
                            'CREATE TABLE `TABLE_NAME` (
                              `lot_id` int(11) NOT NULL AUTO_INCREMENT,
                              `lot_no` int(11) NOT NULL,
                              `auction_id` int(11) NOT NULL,
                              `seller_id` int(11) NOT NULL,
                              `category_id` int(11) NOT NULL,
                              `type_id` int(11) NOT NULL,
                              `type_txt1` varchar(64) NOT NULL,
                              `type_txt2` varchar(64) NOT NULL,
                              `condition_id` int(11) NOT NULL,
                              `postal_only` tinyint(1) NOT NULL,
                              `name` varchar(250) NOT NULL,
                              `description` text NOT NULL,
                              `index_terms` text NOT NULL,
                              `price_reserve` decimal(12,2) NOT NULL,
                              `price_estimate` decimal(12,2) NOT NULL,
                              `weight` decimal(12,2) NOT NULL,
                              `volume` decimal(12,2) NOT NULL,
                              `bid_open` decimal(12,2) NOT NULL,
                              `bid_book_top` decimal(12,2) NOT NULL,
                              `bid_final` decimal(12,2) NOT NULL,
                              `buyer_id` int(11) NOT NULL,
                              `bid_no` varchar(64) NOT NULL,
                              `tax` varchar(64) NOT NULL,
                              `status` varchar(64) NOT NULL,
                               PRIMARY KEY (`lot_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('condition',
                            'CREATE TABLE `TABLE_NAME` (
                              `condition_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(250) NOT NULL,
                              `description` varchar(250) NOT NULL,
                              `sort` int(11) NOT NULL,
                              `status` varchar(64) NOT NULL,
                               PRIMARY KEY (`condition_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('index_term',
                            'CREATE TABLE `TABLE_NAME` (
                              `term_id` int(11) NOT NULL AUTO_INCREMENT,
                              `term_code` varchar(64) NOT NULL,
                              `name` varchar(250) NOT NULL,
                              `status` varchar(64) NOT NULL,
                               PRIMARY KEY (`term_id`),
                               UNIQUE KEY `idx_index_term1` (`term_code`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('order',
                            'CREATE TABLE `TABLE_NAME` (
                              `order_id` INT NOT NULL AUTO_INCREMENT,
                              `auction_id` INT NOT NULL,
                              `user_id` INT NOT NULL,
                              `temp_token` VARCHAR(64) NOT NULL,
                              `date_create` DATETIME NOT NULL,
                              `date_update` DATETIME NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              `no_items` INT NOT NULL,
                              `total_bid` decimal(12,2) NOT NULL,
                              `total_success` decimal(12,2) NOT NULL,
                              `ship_address` text NOT NULL,
                              `ship_location_id` INT NOT NULL,
                              `ship_option_id` INT NOT NULL,
                              `pay_option_id` INT NOT NULL,
                              PRIMARY KEY (`order_id`),
                              KEY `idx_auction_order1` (`temp_token`),
                              KEY `idx_auction_order2` (`auction_id`),
                              KEY `idx_auction_order3` (`user_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');
        
        $this->addCreateSql('order_item',
                            'CREATE TABLE `TABLE_NAME` (
                              `item_id` INT NOT NULL AUTO_INCREMENT,
                              `order_id` INT NOT NULL,
                              `lot_id` INT NOT NULL,
                              `price` DECIMAL(12,2) NOT NULL,
                              `subtotal` DECIMAL(12,2) NOT NULL,
                              `tax` DECIMAL(12,2) NOT NULL,
                              `total` DECIMAL(12,2) NOT NULL,
                              `weight` DECIMAL(12,2) NOT NULL,
                              `volume` DECIMAL(12,2) NOT NULL,
                              `status` varchar(64) NOT NULL,
                              PRIMARY KEY (`item_id`),
                              UNIQUE KEY `idx_order_item1` (`order_id`,`lot_id`),
                              KEY `idx_order_item2` (`lot_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('order_message',
                            'CREATE TABLE `TABLE_NAME` (
                              `message_id` INT NOT NULL AUTO_INCREMENT,
                              `order_id` INT NOT NULL,
                              `subject` VARCHAR(250) NOT NULL,
                              `message` TEXT NOT NULL,
                              `date_sent` DATETIME NOT NULL,
                              PRIMARY KEY (`message_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('invoice',
                            'CREATE TABLE `TABLE_NAME` (
                              `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
                              `invoice_no` varchar(64) NOT NULL,
                              `user_id` int(11) NOT NULL,
                              `sub_total` decimal(12,2) NOT NULL,
                              `tax` decimal(12,2) NOT NULL,
                              `total` decimal(12,2) NOT NULL,
                              `date` date NOT NULL,
                              `comment` text NOT NULL,
                              `status` varchar(16) NOT NULL,
                              `doc_name` varchar(255) NOT NULL,
                              `auction_id` int(11) NOT NULL,
                              `order_id` int(11) NOT NULL,
                              PRIMARY KEY (`invoice_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('invoice_item',
                            'CREATE TABLE `TABLE_NAME` (
                              `item_id` int(11) NOT NULL AUTO_INCREMENT,
                              `invoice_id` int(11) NOT NULL,
                              `lot_id` int(11) NOT NULL,
                              `item` varchar(250) NOT NULL,
                              `price` decimal(12,2) NOT NULL,
                              `quantity` int(11) NOT NULL,
                              `total` decimal(12,2) NOT NULL,
                              PRIMARY KEY (`item_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('payment',
                            'CREATE TABLE `TABLE_NAME` (
                              `payment_id` INT NOT NULL AUTO_INCREMENT,
                              `invoice_id` INT NOT NULL,
                              `date_create` DATETIME NOT NULL,
                              `amount` DECIMAL(12,2) NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`payment_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('pay_option',
                            'CREATE TABLE `TABLE_NAME` (
                              `option_id` INT NOT NULL AUTO_INCREMENT,
                              `type_id` VARCHAR(250) NOT NULL,
                              `name` VARCHAR(250) NOT NULL,
                              `config` TEXT NOT NULL,
                              `sort` INT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`option_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');


        $this->addCreateSql('ship_option',
                            'CREATE TABLE `TABLE_NAME` (
                              `option_id` INT NOT NULL AUTO_INCREMENT,
                              `name` VARCHAR(250) NOT NULL,
                              `sort` INT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`option_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('ship_location',
                            'CREATE TABLE `TABLE_NAME` (
                              `location_id` INT NOT NULL AUTO_INCREMENT,
                              `name` VARCHAR(250) NOT NULL,
                              `sort` INT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`location_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('ship_cost',
                            'CREATE TABLE `TABLE_NAME` (
                              `cost_id` INT NOT NULL AUTO_INCREMENT,
                              `option_id` INT NOT NULL,
                              `location_id` INT NOT NULL,
                              `cost_free` DECIMAL(12,2) NOT NULL,
                              `cost_base` DECIMAL(12,2) NOT NULL,
                              `cost_weight` DECIMAL(12,2) NOT NULL,
                              `cost_volume` DECIMAL(12,2) NOT NULL,
                              `cost_item` DECIMAL(12,2) NOT NULL,
                              `cost_max` DECIMAL(12,2) NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`cost_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('file',
                            'CREATE TABLE `TABLE_NAME` (
                              `file_id` int(10) unsigned NOT NULL,
                              `link_id` varchar(255) NOT NULL,
                              `file_name` varchar(255) NOT NULL,
                              `file_name_tn` varchar(255) NOT NULL,
                              `file_name_orig` varchar(255) NOT NULL,
                              `file_text` longtext NOT NULL,
                              `file_date` date NOT NULL DEFAULT \'0000-00-00\',
                              `file_size` int(11) NOT NULL,
                              `location_id` varchar(64) NOT NULL,
                              `location_rank` int(11) NOT NULL,
                              `encrypted` tinyint(1) NOT NULL,
                              `file_ext` varchar(16) NOT NULL,
                              `file_type` varchar(16) NOT NULL,
                              `caption` varchar(255) NOT NULL,
                              PRIMARY KEY (`file_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

      $this->addCreateSql('seller',
                            'CREATE TABLE `TABLE_NAME` (
                              `seller_id` INT NOT NULL AUTO_INCREMENT,
                              `sort` INT NOT NULL,
                              `name` varchar(64) NOT NULL,
                              `cell` varchar(64) NOT NULL,
                              `tel` varchar(64) NOT NULL,
                              `email` varchar(255) NOT NULL,
                              `address` TEXT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              `comm_pct` DECIMAL(5,2) NOT NULL,
                              `seller_code` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`seller_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');  

      $this->addCreateSql('user_extend',
                            'CREATE TABLE `TABLE_NAME` (
                              `extend_id` INT NOT NULL AUTO_INCREMENT,
                              `user_id` INT NOT NULL,
                              `name_invoice` varchar(250) NOT NULL,
                              `bid_no` varchar(64) NOT NULL,
                              `seller_id` INT NOT NULL,
                              `cell` varchar(64) NOT NULL,
                              `tel` varchar(64) NOT NULL,
                              `email_alt` varchar(255) NOT NULL,
                              `bill_address` TEXT NOT NULL,
                              `ship_address` TEXT NOT NULL,
                              PRIMARY KEY (`extend_id`),
                              UNIQUE KEY `idx_auction_user1` (`user_id`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        //initialisation
        $this->addInitialSql('INSERT INTO `TABLE_PREFIXseller` (`name`,`sort`,`status`) '.
                             'VALUES("INTERNAL",1,"OK")','Created default internal seller');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXtype` (`name`,`sort`,`status`) '.
                             'VALUES("Standard",1,"OK")','Created default lot type');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXauction` (`name`,`summary`,`description`,`status`) '.
                             'VALUES("My first auction","Summary of your auction","Detailed description of your auction","NEW")','Created default first auction');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXpay_option` (`type_id`,`name`,`config`,`sort`,`status`) '.
                             'VALUES("EFT_TOKEN","Manual EFT with token","Your bank account details","1","OK")','Created default payment option');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXship_location` (`name`,`sort`,`status`) '.
                             'VALUES("South Africa","1","OK")','Created sample shipping location');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXship_option` (`name`,`sort`,`status`) '.
                             'VALUES("Collect","1","OK"),("Courier","2","OK"),("Postnet","3","OK")','Created sample shipping options');

        $this->addInitialSql('INSERT INTO `TABLE_PREFIXship_cost` (`location_id`,`option_id`,`cost_free`,`cost_base`,'.
                                                                   '`cost_weight`,`cost_volume`,`cost_item`,`cost_max`,status`) '.
                             'VALUES("1","1","0","0","0","0","0","0","OK"),("1","2","1000","100","100","0","0","1000","OK"),("1","3","1000","100","0","0","0","0","OK")','Created sample shipping costs');
        
        //updates use time stamp in ['YYYY-MM-DD HH:MM'] format, must be unique and sequential
        //$this->addUpdateSql('YYYY-MM-DD HH:MM','Update TABLE_PREFIX--- SET --- "X"');
        //$this->addUpdateSql('2020-02-19 18:00','INSERT INTO TABLE_PREFIXseller (name,sort,status) VALUES("Internal",1,"OK")');
    }
}


  
?>
