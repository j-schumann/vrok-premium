<?php

/**
 * @copyright   (c) 2014-16, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\Premium\Controller;

use Vrok\Premium\Form\FeatureDefaults;
use Vrok\Premium\Service\FeatureManager;
use Vrok\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;

class FeatureController extends AbstractActionController
{
    /**
     * @var FeatureManager
     */
    protected $featureManager = null;

    /**
     * Class constructor.
     *
     * @param FeatureManager $fm
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(FeatureManager $fm, ServiceLocatorInterface $serviceLocator)
    {
        parent::__construct($serviceLocator);
        $this->featureManager = $fm;
    }

    public function indexAction()
    {
        $features = $this->featureManager->getFeatureConfig();
        foreach ($features as $featureName => $definition) {
            $config = $this->featureManager->getDefaultConfig($featureName);
            $features[$featureName]['defaults'] = $config;
        }

        return $this->createViewModel(['features' => $features]);
    }

    public function defaultsAction()
    {
        $name = $this->params('name');
        if (! $this->featureManager->featureExists($name)) {
            $this->getResponse()->setStatusCode(404);

            return $this->createViewModel(['message' => 'view.premium.featureUnknown']);
        }

        $config = $this->featureManager->getDefaultConfig($name);
        $strategy = $this->featureManager->getFeatureStrategy($name);

        $form = $this->getServiceLocator()->get('FormElementManager')
                ->get(FeatureDefaults::class);

        foreach ($config as $param => $value) {
            if ($param == 'active') {
                continue;
            }

            $form->get('defaults')->add($strategy->getParameterFormElement($param));
            $form->getInputFilter()->get('defaults')->add($strategy->getParameterInputFilter($param));
        }

        $form->setData(['defaults' => $config]);

        $viewModel = [
            'form'    => $form,
            'feature' => $name,
        ];

        if (! $this->request->isPost()) {
            return $viewModel;
        }

        $isValid = $form->setData($this->request->getPost())->isValid();
        if (! $isValid) {
            return $viewModel;
        }

        $defaults = $form->getData()['defaults'];
        $this->featureManager->setDefaultConfig($name, $defaults);

        $this->queue('jobs')->push('Vrok\Premium\Job\UpdateFeatureDefaultConfig', [
            'feature'   => $name,
            'oldConfig' => $config,
            'userId'    => $this->identity()->getId(),
        ]);

        $this->featureManager->getEntityManager()->flush();

        if ($config != $defaults) {
            $this->flashMessenger()
                ->addSuccessMessage('message.premium.feature.defaultsChanged');
        }

        return $this->redirect()->toRoute('premium/feature');
    }
}
