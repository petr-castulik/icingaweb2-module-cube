<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\BaseCube;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Cube\Web\Form\FormLoader;
use Icinga\Module\Cube\Web\Form\QuickForm;
use Icinga\Web\Controller as WebController;
use Icinga\Web\View;
use Icinga\Web\Widget\Tabextension\DashboardAction;

abstract class Controller extends WebController
{
    /** @var View This helps IDEs to understand that this is not ZF view */
    public $view;

    /** @var \Icinga\Module\Cube\Cube|BaseCube */
    protected $cube;

    /**
     * Return this controllers' cube
     *
     * @return \Icinga\Module\Cube\Cube|BaseCube
     */
    abstract protected function getCube();

    protected function moduleInit()
    {
        $this->cube = $this->getCube();
    }

    public function detailsAction()
    {
        $this->getTabs()->add('details', [
            'label' => $this->translate('Cube details'),
            'url'   => $this->getRequest()->getUrl()
        ])->activate('details');

        $dimensions = $this->params->shift('dimensions');
        $wantNull = $this->params->shift('wantNull');

        if (! (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            $this->cube->chooseFacts(array_keys($this->cube->getAvailableFactColumns()));
            $vars = preg_split('/,/', $dimensions, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($vars as $var) {
                $this->cube->addDimensionByName($var);
                if ($wantNull) {
                    $this->cube->getDimension($var)->wantNull();
                }
            }
        }

        foreach (['renderLayout', 'showFullscreen', 'showCompact', 'view'] as $p) {
            $this->params->shift($p);
        }

        foreach ($this->params->toArray() as $param) {
            $this->cube->slice($param[0], urldecode($param[1]));
        }

        $this->view->title = $this->cube->getSlicesLabel();
        $this->view->links = ActionLinks::renderAll($this->cube, $this->view);

        $this->render('cube-details', null, true);
    }

    protected function renderCube()
    {
        // Hint: order matters, we are shifting!
        $showSettings = $this->params->shift('showSettings');
        $dimensions = $this->params->shift('dimensions');

        if (! (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())) {
            $wantNull = $this->params->shift('wantNull');
            $vars = preg_split('/,/', $dimensions, -1, PREG_SPLIT_NO_EMPTY);

            $this->cube->chooseFacts(array_keys($this->cube->getAvailableFactColumns()));
            foreach ($vars as $var) {
                $this->cube->addDimensionByName($var);
                if ($wantNull) {
                    $this->cube->getDimension($var)->wantNull();
                }
            }
        }

        foreach (['renderLayout', 'showFullscreen', 'showCompact', 'view'] as $p) {
            $this->params->shift($p);
        }

        foreach ($this->params->toArray() as $param) {
            $this->cube->slice($param[0], urldecode($param[1]));
        }

        $this->view->title = sprintf(
            $this->translate('Cube: %s'),
            $this->cube->getPathLabel()
        );

        if (count($this->cube->listDimensions()) > 0) {
            $this->view->cube = $this->cube;
        } else {
            $showSettings = true;
        }

        if ($showSettings) {
            $this->view->form = $this->loadForm('Dimensions')
                ->setCube($this->cube)
                ->handleRequest();
        } else {
            $this->setAutorefreshInterval(15);
        }

        $this->render('cube-index', null, true);
    }

    /**
     * Load a form with a specific name
     *
     * @param $name
     * @return QuickForm
     */
    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    public function createTabs()
    {
        return $this->getTabs()
            ->add('cube/hosts', [
                'label' => $this->translate('Hosts'),
                'url'   => 'cube/hosts' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString())
            ])
            ->add('cube/services', [
                'label' => $this->translate('Services'),
                'url'   => 'cube/services' . ($this->params->toString() === '' ? '' : '?' . $this->params->toString())
            ])
            ->extend(new DashboardAction());
    }
}
