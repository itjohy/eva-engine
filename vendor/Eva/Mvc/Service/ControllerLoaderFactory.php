<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Mvc
 * @subpackage Service
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Eva\Mvc\Service;

use Zend\Loader\Pluggable;
use Zend\ServiceManager\Di\DiAbstractServiceFactory;
use Zend\ServiceManager\Di\DiServiceInitializer;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\EventManager\EventManagerAwareInterface;

/**
 * @category   Zend
 * @package    Zend_Mvc
 * @subpackage Service
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class ControllerLoaderFactory implements FactoryInterface
{
    /**
     * Create the controller loader service
     *
     * Creates and returns a scoped service manager. The only controllers 
     * this manager will allow are those defined in the application 
     * configuration's "controllers" array. If a controller is matched, the
     * scoped manager will attempt to load the controller, pulling it from 
     * a DI service if a matching service is not found. Finally, it will
     * attempt to inject the controller plugin broker into the controller if
     * it subscribes to the Pluggable interface.
     * 
     * @param  ServiceLocatorInterface $serviceLocator 
     * @return ServiceManager
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        if (!$serviceLocator instanceof ServiceManager) {
            return $serviceLocator;
        }

        $controllerLoader = $serviceLocator->createScopedServiceManager();
        $configuration    = $serviceLocator->get('Configuration');

        $routeMatch = $serviceLocator->get('application')->getMvcEvent()->getRouteMatch();
        if($routeMatch && $routeMatch  instanceof \Zend\Mvc\Router\RouteMatch){
            $routeMatchName =  $routeMatch->getMatchedRouteName();
            $controllerName =  $routeMatch->getParam('controller');

            if(isset($configuration['router']['routes'][$routeMatchName]) 
                && $routeConfiguration = $configuration['router']['routes'][$routeMatchName]
            ){
                if(isset($routeConfiguration['type']) && $routeConfiguration['type'] === 'Eva\Mvc\Router\Http\ModuleRoute'){
                    $configuration['controller']['classes'][$controllerName] = $controllerName;
                }
            }
        }


        if (isset($configuration['controller'])) {
            foreach ($configuration['controller'] as $type => $specs) {
                if ($type == 'classes') {
                    foreach ($specs as $name => $value) {
                        $controllerLoader->setInvokableClass($name, $value);
                    }
                }
                if ($type == 'factories') {
                    foreach ($specs as $name => $value) {
                        $controllerLoader->setFactory($name, $value);
                    }
                }
            }
        }

        if (isset($configuration['di']) && $serviceLocator->has('Di')) {
            $di = $serviceLocator->get('Di');
            $controllerLoader->addAbstractFactory(
                new DiAbstractServiceFactory($di, DiAbstractServiceFactory::USE_SL_BEFORE_DI)
            );
            $controllerLoader->addInitializer(
                new DiServiceInitializer($di, $serviceLocator)
            );
        }

        $controllerLoader->addInitializer(function ($instance) use ($serviceLocator) {
            if ($instance instanceof ServiceLocatorAwareInterface) {
                $instance->setServiceLocator($serviceLocator->get('Zend\ServiceManager\ServiceLocatorInterface'));
            }

            if ($instance instanceof EventManagerAwareInterface) {
                $instance->setEventManager($serviceLocator->get('EventManager'));
            }

            if ($instance instanceof Pluggable) {
                $instance->setBroker(clone $serviceLocator->get('ControllerPluginBroker'));
            }
        });

        return $controllerLoader;
    }
}
