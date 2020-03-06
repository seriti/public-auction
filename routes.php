<?php  
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/routes.php file within this framework
copy the "/auction" group into the existing "/admin" group within existing "src/routes.php" file 
*/

//*** BEGIN admin access ***
$app->group('/admin', function () {

     $this->group('/auction', function () {
        $this->any('/category', \App\Auction\CategoryController::class);
        $this->any('/condition', \App\Auction\ConditionController::class);
        $this->any('/auction', \App\Auction\AuctionController::class);
        $this->any('/auction_image', \App\Auction\AuctionImageController::class);
        $this->any('/auction_file', \App\Auction\AuctionFileController::class);
        $this->any('/index_term', \App\Auction\IndexTermController::class);
        $this->any('/invoice_wizard', \App\Auction\InvoiceWizardController::class);
        $this->any('/invoice', \App\Auction\InvoiceController::class);
        $this->any('/invoice_file', \App\Auction\InvoiceFileController::class);
        $this->any('/invoice_item', \App\Auction\InvoiceItemController::class);
        $this->any('/invoice_payment', \App\Auction\InvoicePaymentController::class);
        $this->any('/location', \App\Auction\LocationController::class);
        $this->any('/lot', \App\Auction\LotController::class);
        $this->any('/lot_auction', \App\Auction\LotAuctionController::class);
        $this->any('/lot_image', \App\Auction\LotImageController::class);
        $this->any('/lot_info', \App\Auction\LotInfoController::class);
        $this->any('/dashboard', \App\Auction\DashboardController::class);
        $this->any('/order', \App\Auction\OrderController::class);
        $this->any('/order_item', \App\Auction\OrderItemController::class);
        $this->any('/order_message', \App\Auction\OrderMessageController::class);
        $this->any('/order_file', \App\Auction\OrderFileController::class);
        $this->any('/order_payment', \App\Auction\OrderPaymentController::class);
        $this->any('/payment', \App\Auction\PaymentController::class);
        $this->any('/pay_option', \App\Auction\PayOptionController::class);
        $this->any('/report', \App\Auction\ReportController::class);
        $this->any('/task', \App\Auction\TaskController::class);
        $this->get('/setup_data', \App\Auction\SetupDataController::class);
        $this->any('/setup', \App\Auction\SetupController::class);
        $this->any('/seller', \App\Auction\SellerController::class);
        $this->any('/ship_option', \App\Auction\ShipOptionController::class);
        $this->any('/ship_location', \App\Auction\ShipLocationController::class);
        $this->any('/ship_cost', \App\Auction\ShipCostController::class);
        $this->any('/user_extend', \App\Auction\UserExtendController::class);
    })->add(\App\Auction\Config::class);

})->add(\App\ConfigAdmin::class);
//*** END admin access ***

/*
The code snippet below is for use within an existing src/routes.php file within "seriti/slim3-skeleton" framework
replace the existing public access section with this code, or just replace the "auction specific routes" within your existing /public route .  
*/


//*** BEGIN public access ***
$app->redirect('/', '/public/home', 301);
$app->group('/public', function () {
    $this->redirect('', '/public/home', 301);
    $this->redirect('/', 'home', 301);
 
    $this->any('/ajax', \App\Auction\Ajax::class);
    $this->any('/cart', \App\Auction\CartController::class);
    $this->any('/checkout', \App\Auction\CheckoutWizardController::class);
    $this->get('/image_popup', \App\Auction\ImagePopupController::class);

    $this->group('/account', function () {
        $this->redirect('', '/public/account/dashboard', 301);
        $this->redirect('/', 'dashboard', 301);

        $this->get('/dashboard', \App\Auction\AccountDashboardController::class);
        $this->any('/order', \App\Auction\AccountOrderController::class);
        $this->any('/order_item', \App\Auction\AccountOrderItemController::class);
        $this->get('/invoice', \App\Auction\AccountInvoiceController::class);
        $this->get('/invoice_item', \App\Auction\AccountInvoiceItemController::class);
        $this->get('/invoice_payment', \App\Auction\AccountInvoicePaymentController::class);
        $this->any('/profile', \App\Auction\AccountProfileController::class);
    })->add(\App\Auction\ConfigAccount::class);
    
    $this->any('/register', \App\Website\RegisterWizardController::class);
    $this->any('/logout', \App\Website\LogoutController::class);

    //NB: this must come last in group
    $this->any('/{link_url}', \App\Website\WebsiteController::class);
})->add(\App\Website\ConfigPublic::class);
//*** END public access ***

