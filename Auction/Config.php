<?php 
namespace App\Auction;

use Psr\Container\ContainerInterface;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\SITE_NAME;

class Config
{
    
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        
        $module = $this->container->config->get('module','auction');
        $menu = $this->container->menu;
        $cache = $this->container->cache;
        $db = $this->container->mysql;

        $user_specific = true;
        $cache->setCache('Auction',$user_specific);

        //NB: Also defined in Website/ConfigPublic
        define('MODULE_AUCTION',$module);
        
        define('TABLE_PREFIX',$module['table_prefix']);
        if(!defined('CURRENCY_ID')) define('CURRENCY_ID','ZAR');
        if(!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL','R');
        if(!defined('INVOICE_PREFIX')) define('INVOICE_PREFIX','INV');
        if(!defined('INVOICE_XTRA_ITEMS')) define('INVOICE_XTRA_ITEMS',5);
        if(!defined('VAT_RATE')) define('VAT_RATE',0.15);
        if(!defined('VAT_CALC')) define('VAT_CALC',false);

        //can define these in setup page but hard coded for now
        define('AUCTION_FEE',0.10);
        define('AUCTION_SELLER_FEE',0.20);

        //defines access and resize parameters
        define('IMAGE_CONFIG',$module['images']);
                
        define('MODULE_ID','AUCTION');
        define('MODULE_LOGO','<span class="glyphicon glyphicon-shopping-cart"></span> ');
        define('MODULE_PAGE',URL_CLEAN_LAST);      
        
        $user_data = $cache->retrieveAll();
        $table_auction = TABLE_PREFIX.'auction';
        if(!isset($user_data['auction_id'])) {
            //first run on setup fails if table does not exist
            if($db->checkTableExists($table_auction)) {
                $sql = 'SELECT auction_id FROM '.$table_auction.' ORDER BY name LIMIT 1';
                $auction_id = $db->readSqlValue($sql,0);
                if($auction_id !== 0) {
                    $user_data['auction_id'] = $auction_id;
                    $cache->store('auction_id',$auction_id);  
                }   
            }  
        }   

        if(isset($user_data['auction_id'])) {
            $sql = 'SELECT auction_id,name,description,status FROM '.$table_auction.' '.
                   'WHERE auction_id = "'.$user_data['auction_id'].'" ';    
            $auction = $db->readSqlRecord($sql);
            define('AUCTION_ID',$user_data['auction_id']);
            define('AUCTION_NAME',$auction['name']);
        } else {
            define('AUCTION_ID',0);
            define('AUCTION_NAME','');
        }

        //define('MODULE_NAV',$menu->buildNav($module['route_list'],MODULE_PAGE));
        $submenu_html = $menu->buildNav($module['route_list'],MODULE_PAGE);
        $this->container->view->addAttribute('sub_menu',$submenu_html);

        $response = $next($request, $response);
        
        return $response;
    }
}