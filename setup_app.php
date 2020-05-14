<?php
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/setup_app.php file within this framework
add the below code snippet to the end of existing "src/setup_app.php" file.
This tells the framework about module: name, sub-memnu route list and title, database table prefix.
*/

$container['config']->set('module','auction',['name'=>'Auction manager',
                                            'route_root'=>'admin/auction/',
                                            'route_list'=>['dashboard'=>'Dashboard','lot'=>'Lots','order'=>'Orders','invoice'=>'Invoices',
                                            'task'=>'Tasks','report'=>'Reports'],
                                            'labels'=>['category'=>'Category','type'=>'Type','type_txt1'=>'Year','type_txt2'=>'Catalog','order'=>'Order'],
                                            'table_prefix'=>'auc_'
                                            ]);
