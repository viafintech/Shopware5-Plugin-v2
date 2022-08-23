<?php

namespace ZerintBarzahlenViacash;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Payment\Payment;

class ZerintBarzahlenViacash extends Plugin
{

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => ['onFrontend',-100],
            'Enlight_Controller_Action_PreDispatch_Widgets' => ['onFrontend',-100]
        ];
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name' => 'barzahlen',
            'description' => ' Barzahlen/viacash - Online Payment',
            'action' => 'frontend/barzahlen',
            'active' => 1,
            'position' => 0,
            'additionalDescription' =>
                '<img src="https://cdn.barzahlen.de/images/viafintech_splitlogo.png"/>'
                . '<div id="payment_desc">'
                . '  Pay online in cash | Online bar bezahlen '
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        /** @var \Shopware\Components\CacheManager $cacheManager */
        $cacheManager = Shopware()->Container()->get('shopware.cache_manager');
        $cacheManager->clearHttpCache();
        $cacheManager->clearConfigCache();
        $cacheManager->clearTemplateCache();
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        /** @var \Shopware\Components\CacheManager $cacheManager */
        $cacheManager = Shopware()->Container()->get('shopware.cache_manager');
        $cacheManager->clearHttpCache();
        $cacheManager->clearConfigCache();
        $cacheManager->clearTemplateCache();
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
        /** @var \Shopware\Components\CacheManager $cacheManager */
        $cacheManager = Shopware()->Container()->get('shopware.cache_manager');
        $cacheManager->clearHttpCache();
        $cacheManager->clearConfigCache();
        $cacheManager->clearTemplateCache();
    }

    /**
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @throws \Exception
     */

    public function onFrontend(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Resources/views/'
        );
    }
}
