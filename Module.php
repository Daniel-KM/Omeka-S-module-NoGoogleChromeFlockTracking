<?php declare(strict_types=1);

namespace NoGoogleChromeFlockTracking;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $t = $this->getServiceLocator()->get('MvcTranslator');
        $htaccess = OMEKA_PATH . '/.htaccess';
        if (!file_exists($htaccess) || !is_readable($htaccess)) {
            throw new ModuleCannotInstallException(
                $t->translate('It seems this installation doesn’t use the web server Apache: there is no file ".htaccess" at the root of Omeka.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }

        if (!is_writeable($htaccess)) {
            throw new ModuleCannotInstallException(
                $t->translate('The file ".htaccess" at the root of Omeka is not writeable and cannot be updated by this module.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }

        $messenger = new Messenger();

        $content = file_get_contents($htaccess);
        if (stripos($content, 'Permissions-Policy') || stripos($content, 'interest-cohort')) {
            $messenger->addNotice('Your site is already configured and let unchanged.'); // @translate
            $messenger->addNotice('The module can be uninstalled.'); // @translate
            return;
        }

        $content .= <<<'HTACCESS'

<IfModule mod_headers.c>
    Header always set Permissions-Policy: interest-cohort=()
</IfModule>

HTACCESS;
        file_put_contents($htaccess, $content);

        $messenger->addSuccess('The privacy anti-theft header has been added successfully to your file ".htaccess".'); // @translate
        $messenger->addNotice('The module can be uninstalled.'); // @translate
    }
}
