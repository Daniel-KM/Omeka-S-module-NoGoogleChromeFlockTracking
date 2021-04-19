<?php declare(strict_types=1);

namespace NoGoogleChromeFlockTracking;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\Http\ClientStatic;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = new Messenger();
        $t = $services->get('MvcTranslator');

        $viewHelpers = $services->get('ViewHelperManager');
        $serverUrl = $viewHelpers->get('serverUrl');
        $assetUrl = $viewHelpers->get('assetUrl');
        $url = $assetUrl('css/style.css', 'Omeka', false, false);
        try {
            $response = ClientStatic::get($serverUrl($url));
        } catch (\Exception $e) {
        }
        // In some cases, the server cannot get its own url.
        if (empty($response)) {
            try {
                $response = ClientStatic::get('http://localhost' . $url);
            } catch (\Exception $e) {
                throw new ModuleCannotInstallException(
                    $t->translate('The module is unable to check if the current install is flock-secure.') // @translate
                        . ' ' . $t->translate('See module’s installation documentation.') // @translate
                );
            }
        }
        $headers = $response->getHeaders();
        if (empty($headers)) {
            throw new ModuleCannotInstallException(
                $t->translate('The module is not able to check if the current install is flock-secure.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }

        $permissionsPolicy = $headers->get('Permissions-Policy');
        if (!empty($permissionsPolicy)) {
            $messenger->addNotice('Your site is already configured and let unchanged.'); // @translate
            $messenger->addNotice('The module can be uninstalled.'); // @translate
            return;
        }

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

        $cli = $this->getServiceLocator()->get('Omeka\Cli');
        $command = 'apachectl -t -D DUMP_MODULES';
        $output = $cli->execute($command);
        if ($output === false) {
            throw new ModuleCannotInstallException(
                $t->translate('It seems this installation doesn’t use the web server Apache: command "apachectl" is not available.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }

        if (!stripos($output, 'headers_module')) {
            throw new ModuleCannotInstallException(
                $t->translate('Apache is working, but its module "headers" is not enabled. Your admin should run command "sudo a2enmod headers; sudo systemctl restart apache2" to enable it.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }

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
