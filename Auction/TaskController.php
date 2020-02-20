<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use App\Auction\Task;

class TaskController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $task = new Task($this->container->mysql,$this->container);
                
        $task->setup();
        $html = $task->processTasks();

        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'ALL Auctions: Tasks';
        //$template['javascript'] = $setup->getJavascript();

        return $this->container->view->render($response,'admin.php',$template);
    }
}