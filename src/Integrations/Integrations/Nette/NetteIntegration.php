<?php

namespace DDTrace\Integrations\Nette;

use DDTrace\Integrations\Integration;
use DDTrace\Integrations\SpanTaxonomy;
use DDTrace\SpanData;
use DDTrace\Tag;
use DDTrace\Type;

class NetteIntegration extends Integration
{
    const NAME = 'nette';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresExplicitTraceAnalyticsEnabling()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (!self::shouldLoad(self::NAME)) {
            return self::NOT_AVAILABLE;
        }

        if (\PHP_MAJOR_VERSION < 7) {
            $integration = $this;
            \DDTrace\hook_method('Nette\Configurator', '__construct', function () use ($integration) {
                $integration->load();
            });
        } else {
            $this->load();
        }

        return self::LOADED;
    }

    public function load()
    {
        $rootSpan = \DDTrace\root_span();
        if (!$rootSpan) {
            return;
        }

        $rootSpan->meta[Tag::SPAN_KIND] = 'server';

        $service = \ddtrace_config_app_name(NetteIntegration::NAME);

        $this->addTraceAnalyticsIfEnabled($rootSpan);
        SpanTaxonomy::instance()->handleServiceName($rootSpan, NetteIntegration::NAME);
        $rootSpan->meta[Tag::COMPONENT] = NetteIntegration::NAME;

        \DDTrace\trace_method(
            'Nette\Configurator',
            'createRobotLoader',
            function (SpanData $span) {
                $span->name = 'nette.configurator.createRobotLoader';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;
            }
        );

        \DDTrace\trace_method(
            'Nette\Application\Application',
            'run',
            function (SpanData $span) use ($rootSpan) {
                $span->name = 'nette.application.run';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $rootSpan->meta[Tag::HTTP_STATUS_CODE] = http_response_code();
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;
            }
        );

        \DDTrace\trace_method(
            'Nette\Application\UI\Presenter',
            'run',
            function (SpanData $span, $args) use ($rootSpan) {

                $span->name = 'nette.presenter.run';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;

                if (count($args) < 1 || !\is_a($args[0], '\Nette\Application\Request')) {
                    return;
                }

                $request = $args[0];
                $presenter = $request->getPresenterName();
                $action = $request->getParameter('action');

                $rootSpan->meta[Tag::HTTP_METHOD] = $request->getMethod();
                $rootSpan->meta['nette.route.presenter'] = $presenter;
                $rootSpan->meta['nette.route.action'] = $action;
            }
        );

        // Latte template engine traces
        \DDTrace\trace_method(
            'Latte\Engine',
            'createTemplate',
            function (SpanData $span, $args) {
                $span->name = 'nette.latte.createTemplate';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;

                if (count($args) >= 1) {
                    $span->meta['nette.latte.templateName'] = $args[0];
                }
            }
        );

        \DDTrace\trace_method(
            'Latte\Engine',
            'render',
            function (SpanData $span, $args) {
                $span->name = 'nette.latte.render';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;

                if (count($args) >= 1) {
                    $span->meta['nette.latte.templateName'] = $args[0];
                }
            }
        );

        \DDTrace\trace_method(
            'Latte\Engine',
            'renderToString',
            function (SpanData $span, $args) {
                $span->name = 'nette.latte.render';
                $span->type = Type::WEB_SERVLET;
                SpanTaxonomy::instance()->handleServiceName($span, NetteIntegration::NAME);
                $span->meta[Tag::COMPONENT] = NetteIntegration::NAME;

                if (count($args) >= 1) {
                    $span->meta['nette.latte.templateName'] = $args[0];
                }
            }
        );
    }
}
