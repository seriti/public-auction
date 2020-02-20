<?php
namespace App\Contact;

use Psr\Container\ContainerInterface;

use App\Contact\ImportWizard;

class ImportWizardController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $wizard = new ImportWizard($this->container->mysql,$this->container);
        
        $html = $wizard->process();

        $template['html'] = $html;
        $template['title'] = MODULE_LOGO;
        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'admin.php',$template);
    }
}