<?php

namespace DDTrace\Integrations\Symfony;

use DDTrace\GlobalTracer;
use DDTrace\Integrations\Integration;
use DDTrace\Integrations\AbstractIntegration;
use DDTrace\Util\Versions;

class SymfonyIntegration extends AbstractIntegration
{
    const NAME = 'symfony';
    const BUNDLE_NAME = 'datadog_symfony_bundle';

    /**
     * @var self
     */
    private static $instance;

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    public static function load()
    {
        $instance = new self();
        return $instance->doLoad();
    }

    private function doLoad()
    {
        $self = $this;

        // This is necessary because Symfony\Component\HttpKernel\Kernel::boot it is not properly traced if we do not
        // wrap the context when it is called, which if Symfony\Component\HttpKernel\Kernel::handle.
        dd_trace('Symfony\Component\HttpKernel\Kernel', 'handle', function () {
            $args =  func_get_args();
            return call_user_func_array([$this, 'handle'], $args);
        });

        dd_trace('Symfony\Component\HttpKernel\Kernel', 'boot', function () use ($self) {
            $result = call_user_func_array([$this, 'boot'], func_get_args());

            $name = SymfonyIntegration::BUNDLE_NAME;
            if (!isset($this->bundles[$name])
                    && defined('\Symfony\Component\HttpKernel\Kernel::VERSION')
            ) {
                $version = \Symfony\Component\HttpKernel\Kernel::VERSION;

                $bundle = null;
                if (Versions::versionMatches('3.4', $version) || Versions::versionMatches('3.3', $version)) {
                    $bundle = new \DDTrace\Integrations\Symfony\V3\SymfonyBundle();
                } elseif (Versions::versionMatches('4', $version)) {
                    $bundle = new \DDTrace\Integrations\Symfony\V4\SymfonyBundle();
                } elseif (Versions::versionMatches('2', $version)) {
                    // We do not register the bundle as we do not fully support Symfony 2.8 yet. And probably we won't
                    // use bundles in the future anymore
                    $bundle = null;
                    $self->setupResourceNameTracingV2();
                }

                if ($bundle) {
                    // Simulating behavior of bundle initialization for bundles without any parent bundle based on:
                    // https://github.com/symfony/symfony/blob/05efd1243fb3910fbaaedabf9b4758604b397c0f/src/Symfony/Component/HttpKernel/Kernel.php#L481
                    $this->bundles[$name] = $bundle;
                    $this->bundleMap[$name] = [$bundle];

                    $bundle->setContainer($this->container);
                    $bundle->boot();
                }
            }

            return $result;
        });

        return Integration::LOADED;
    }

    /**
     * Resource name assignment for Symfony 2.
     */
    public function setupResourceNameTracingV2()
    {
        $self = $this;

        dd_trace('Symfony\Component\HttpKernel\Event\FilterControllerEvent', 'setController', function () use ($self) {
            $args = func_get_args();
            $controllerInfo = $args[0];
            $resourceParts = [];

            $tracer = GlobalTracer::get();
            $rootSpan = $tracer->getSafeRootSpan();

            // Controller info can be provided in various ways.
            if (is_string($controllerInfo)) {
                $resourceParts[] = $controllerInfo;
            } elseif (is_array($controllerInfo) && count($controllerInfo) === 2) {
                if (is_object($controllerInfo[0])) {
                    $resourceParts[] = get_class($controllerInfo[0]);
                } elseif (is_string($controllerInfo[0])) {
                    $resourceParts[] = $controllerInfo[0];
                }

                if (is_string($controllerInfo[1])) {
                    $resourceParts[] = $controllerInfo[1];
                }
            }

            if ($rootSpan) {
                $rootSpan->setIntegration($self);
                if (count($resourceParts) > 0) {
                    $rootSpan->setResource(implode(' ', $resourceParts));
                }
            }

            return call_user_func_array([$this, 'setController'], $args);
        });
    }
}
