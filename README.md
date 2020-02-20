# Public auction module. 

## Designed for managing physical auctions combined with online viewing and bidding.

The module is designed to replicate how "postal" auctions work where public users can view an auction catalogue and submit bids to be considered during physical live auction.
It also includes powerful administrative tools for:

- Auction Lots with Category hierarchy, condition, descrption, multiple images, reserve pricing, price estimates
- Managing multiple auctions
- Building auction lists with multiple images per lot
- Displaying auction lists in public wesbite with simple text placeholders
- Website users can create multiple orders(bidding lists)
- Managing the physical live auction process including any "postal" bids submitted by public on website.
- Capture bidding details like opening bid, final bid, bidder codes
- Issuing invoices to users on successful bids immediately after auction, including commission and taxes.
- Capture payments
- Catelogue PDF creation for email campaigns, and realised lot reports, as well as master copy for manual bid capture if required.
- Multiple seller setup so can auction lots on behalf of third parties.
- Seller reports and iou pdf generation. 


## Requires Seriti Slim 3 MySQL Framework skeleton

This module integrates seamlessly into [Seriti skeleton framework](https://github.com/seriti/slim3-skeleton).  
You need to first install the skeleton framework and then download the source files for the module and follow these instructions.

It is possible to use this module independantly from the seriti skeleton but you will still need the [Seriti tools library](https://github.com/seriti/tools).  
It is strongly recommended that you first install the seriti skeleton to see a working example of code use before using it within another application framework.  
That said, if you are an experienced PHP programmer you will have no problem doing this and the required code footprint is very small.  

## Requires Seriti public-website module

You will be able to setup and manage auction lots but for the public to view them and place bids/orders you will need to have the **git clone https://github.com/seriti/public-website**
module installed. Payment processing still needs to be added, coming soon...

## Optional Seriti public-website module

This module would be very useful in managing email campaigns for Auction catalogues and realisations. **git clone https://github.com/seriti/contact-manager**

## Install the module

1.) Install Seriti Skeleton framework(see the framework readme for detailed instructions):   
    **composer create-project seriti/slim3-skeleton [directory-for-app]**.   
    Make sure that you have thsi working before you proceed.

2.) Download a copy of public-auction module source code directly from github and unzip,  
or by using **git clone https://github.com/seriti/public-auction** from command line.  
Once you have a local copy of module code check that it has following structure:

/auction/(all module implementation classes are in this folder)  
/setup_add.php  
/routes.php  

3.) Copy the **auction** folder and all its contents into **[directory-for-app]/app** folder.

4.) Open the routes.php file and insert the **$this->group('/auction', function (){}** route definition block
within the existing  **$app->group('/admin', function () {}** code block contained in existing skeleton **[directory-for-app]/src/routes.php** file.
In addition you will need to either replace the entire **$app->group('/public', function () {}** code block or insert auction specific routes within any existing code.

5.) Open the setup_app.php file and  add the module config code snippet into bottom of skeleton **[directory-for-app]/src/setup_app.php** file.  
Please check the **table_prefix** value to ensure that there will not be a clash with any existing tables in your database.

6.) Copy the contents of "templates" folder to **[directory-for-app]/templates/** folder
 
7.) Now in your browser goto URL:  

"http://localhost:8000/admin/auction/dashboard" if you are using php built in server  
OR  
"http://www.yourdomain.com/admin/auction/dashboard" if you have configured a domain on your server  

Now click link at bottom of page **Setup Database**: This will create all necessary database tables with table_prefix as defined above.  
Thats it, you are good to go. Add some product categories and then start adding products.
