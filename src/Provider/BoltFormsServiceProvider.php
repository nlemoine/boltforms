<?php

namespace Bolt\Extension\Bolt\BoltForms\Provider;

use Bolt\Extension\Bolt\BoltForms\BoltForms;
use Bolt\Extension\Bolt\BoltForms\BoltFormsExtension;
use Bolt\Extension\Bolt\BoltForms\Config;
use Bolt\Extension\Bolt\BoltForms\Factory;
use Bolt\Extension\Bolt\BoltForms\Submission;
use Bolt\Extension\Bolt\BoltForms\Subscriber\DynamicDataSubscriber;
use Bolt\Extension\Bolt\BoltForms\Twig;
use Bolt\Filesystem\Filesystem;
use Bolt\Filesystem\Handler\File;
use Pimple as Container;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * BoltForms service provider.
 *
 * Copyright (c) 2014-2016 Gawain Lynch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */
class BoltFormsServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['session'] = $app->extend(
            'session',
            function (SessionInterface $session) use ($app) {
                $session->registerBag($app['boltforms.feedback']);

                return $session;
            }
        );

        $app['boltforms'] = $app->share(
            function ($app) {
                $forms = new BoltForms($app);

                return $forms;
            }
        );

        $app['boltforms.config'] = $app->share(
            function ($app) {
                /** @var Filesystem $cacheFs */
                $cacheFs = $app['filesystem']->getFilesystem('cache');
                /** @var File $cacheFile */
                $cacheFile = $cacheFs->getFile('boltforms.config.cache');
                $cachePath = $cacheFs->getAdapter()->getPathPrefix();
                $cacheFilePath = $cachePath . $cacheFile->getPath();
                $configCache = new ConfigCache($cacheFilePath, $app['debug']);

                if ($configCache->isFresh()) {
                    $config = unserialize($cacheFile->read());
                } else {
                    $configFile = $app['resources']->getPath('extensionsconfig/boltforms.bolt.yml');
                    /** @var BoltFormsExtension $boltForms */
                    $boltForms = $app['extensions']->get('Bolt/BoltForms');
                    $config = new Config\Config($boltForms->getConfig());
                    $resources = new FileResource($configFile);
                    $configCache->write(serialize($config), [$resources]);
                }

                return $config;
            }
        );

        $app['boltforms.feedback'] = $app->share(
            function () {
                $bag = new FlashBag('_boltforms');
                $bag->setName('boltforms');

                return $bag;
            }
        );

        $app['boltforms.form.context.factory'] = $app->protect(
            function () use ($app) {
                $webPath = $app['extensions']->get('Bolt/BoltForms')->getWebDirectory()->getPath();
                $compiler = new Factory\FormContext($webPath);

                return $compiler;
            }
        );

        $app['boltforms.subscriber.custom_data'] = $app->share(function ($app) {
            return new DynamicDataSubscriber($app);
        });

        $this->registerHandlers($app);
        $this->registerProcessors($app);
        $this->registerTwig($app);
    }

    public function boot(Application $app)
    {
        $dispatcher = $app['dispatcher'];
        $dispatcher->addSubscriber($app['boltforms.subscriber.custom_data']);
    }

    private function registerTwig(Application $app)
    {
        $app['boltforms.twig.helper'] = $app->share(
            function (Application $app) {
                return new Container([
                    'form' => $app->share(
                        function () use ($app) {
                            return new Twig\Helper\FormHelper(
                                $app['boltforms'],
                                $app['boltforms.config'],
                                $app['boltforms.processor'],
                                $app['boltforms.form.context.factory'],
                                $app['boltforms.feedback'],
                                $app['session'],
                                $app['request_stack'],
                                $app['logger.system']
                            );
                        }
                    ),
                ]);
            }
        );

        $app['boltforms.twig'] = $app->share(
            function ($app) {
                $twig = new Twig\BoltFormsExtension($app, $app['boltforms.config']);

                return $twig;
            }
        );
    }

    private function registerHandlers(Application $app)
    {
        /** @deprecated Since 3.1 and to be removed in 4.0. Use $app['boltforms.handlers']['database'] instead. */
        $app['boltforms.database'] = $app->share(
            function ($app) {
                return $app['boltforms.handlers']['database'];
            }
        );

        /** @deprecated Since 3.1 and to be removed in 4.0. Use $app['boltforms.handlers']['email'] instead. */
        $app['boltforms.email'] = $app->share(
            function ($app) {
                return $app['boltforms.handlers']['email'];
            }
        );

        $app['boltforms.handlers'] = $app->share(
            function (Application $app) {
                return new Container([
                    'content'  => $app->share(
                        function () use ($app) {
                            return new Submission\Handler\ContentType(
                                $app['boltforms.config'],
                                $app['storage'],
                                $app['boltforms.feedback'],
                                $app['logger.system'],
                                $app['mailer']
                            );
                        }
                    ),
                    'database' => $app->share(
                        function () use ($app) {
                            return new Submission\Handler\DatabaseTable(
                                $app['boltforms.config'],
                                $app['storage'],
                                $app['boltforms.feedback'],
                                $app['logger.system'],
                                $app['mailer']
                            );
                        }
                    ),
                    'email'    => $app->share(
                        function () use ($app) {
                            return new Submission\Handler\Email(
                                $app['boltforms.config'],
                                $app['storage'],
                                $app['boltforms.feedback'],
                                $app['logger.system'],
                                $app['mailer'],
                                $app['dispatcher'],
                                $app['twig'],
                                $app['url_generator']
                            );
                        }
                    ),
                    'redirect' => $app->share(
                        function () use ($app) {
                            return new Submission\Handler\Redirect($app['url_matcher']);
                        }
                    ),
                    'request' => $app->share(
                        function () use ($app) {
                            return new Submission\Handler\PostRequest($app['request_stack']);
                        }
                    ),
                    'upload'  => $app->protect(
                        function (Config\FormConfig $formConfig, UploadedFile $file) use ($app) {
                            return new Submission\Handler\Upload(
                                $app['boltforms.config'],
                                $formConfig,
                                $file
                            );
                        }
                    ),
                ]);
            }
        );
    }

    private function registerProcessors(Application $app)
    {
        $app['boltforms.processors'] = $app->share(
            function (Application $app) {
                return new Container([
                    'content'  => $app->share(function () use ($app) { return new Submission\Processor\ContentType($app['boltforms.handlers']); }),
                    'database' => $app->share(function () use ($app) { return new Submission\Processor\DatabaseTable($app['boltforms.handlers']); }),
                    'email'    => $app->share(function () use ($app) { return new Submission\Processor\Email($app['boltforms.handlers']); }),
                    'feedback' => $app->share(function () use ($app) { return new Submission\Processor\Feedback($app['boltforms.handlers'], $app['session']); }),
                    'fields'   => $app->share(function () use ($app) { return new Submission\Processor\Fields($app['boltforms.handlers'], $app['boltforms.config']); }),
                    'redirect' => $app->share(function () use ($app) { return new Submission\Processor\Redirect($app['boltforms.handlers'], $app['url_matcher'], $app['request_stack'], $app['session']); }),
                ]);
            }
        );

        $app['boltforms.processor'] = $app->share(
            function (Application $app) {
                $processor = new Submission\Processor(
                    $app['boltforms.config'],
                    $app['boltforms'],
                    $app['boltforms.processors'],
                    $app['boltforms.handlers'],
                    $app['dispatcher'],
                    $app['logger.system'],
                    $app
                );

                return $processor;
            }
        );
    }
}
