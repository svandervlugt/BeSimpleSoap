<?php
/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle;

use BeSimple\SoapBundle\Converter\TypeRepository;
use BeSimple\SoapBundle\ServiceBinding\MessageBinderInterface;
use BeSimple\SoapBundle\ServiceBinding\ServiceBinder;
use BeSimple\SoapBundle\ServiceDefinition\Dumper\DumperInterface;
use BeSimple\SoapBundle\Soap\SoapServerFactory;

use BeSimple\SoapCommon\Classmap;
use BeSimple\SoapCommon\Converter\TypeConverterCollection;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * WebServiceContext.
 *
 * @author Christian Kerl <christian-kerl@web.de>
 */
class WebServiceContext
{
    private $typeRepository;
    private $converterRepository;

    private $wsdlFileDumper;

    private $options;

    private $serviceDefinition;
    private $serviceBinder;
    private $serverFactory;

    public function __construct(LoaderInterface $loader, DumperInterface $dumper, Classmap $classmap, TypeRepository $typeRepository, TypeConverterCollection $converters, array $options) {
        $this->loader         = $loader;
        $this->wsdlFileDumper = $dumper;

        $this->classmap       = $classmap;

        $this->typeRepository = $typeRepository;
        $this->converters     = $converters;

        $this->options = $options;
    }

    public function getServiceDefinition()
    {
        if (null === $this->serviceDefinition) {
            if (!$this->loader->supports($this->options['resource'], $this->options['resource_type'])) {
                throw new \LogicException(sprintf('Cannot load "%s" (%s)', $this->options['resource'], $this->options['resource_type']));
            }

            $this->serviceDefinition = $this->loader->load($this->options['resource'], $this->options['resource_type']);
            $this->serviceDefinition->setName($this->options['name']);
            $this->serviceDefinition->setNamespace($this->options['namespace']);

            $this->typeRepository->fixTypeInformation($this->serviceDefinition);
        }

        return $this->serviceDefinition;
    }

    public function getWsdlFileContent($endpoint = null)
    {
        return file_get_contents($this->getWsdlFile($endpoint));
    }

    public function getWsdlFile($endpoint = null)
    {
        $file  = sprintf('%s/%s.%s.wsdl', $this->options['cache_dir'], $this->options['name'], md5($endpoint));
        $cache = new ConfigCache($file, $this->options['debug']);

        if(!$cache->isFresh()) {
            $cache->write($this->wsdlFileDumper->dumpServiceDefinition($this->getServiceDefinition(), $endpoint));
        }

        return (string) $cache;
    }

    public function getServiceBinder()
    {
        if (null === $this->serviceBinder) {
            $this->serviceBinder = new ServiceBinder(
                $this->getServiceDefinition(),
                new $this->options['binder_request_header_class'](),
                new $this->options['binder_request_class'](),
                new $this->options['binder_response_class']()
            );
        }

        return $this->serviceBinder;
    }

    public function getServerFactory()
    {
        if (null === $this->serverFactory) {
            $this->serverFactory = new SoapServerFactory(
                $this->getWsdlFile(),
                $this->classmap,
                $this->converters,
                array(
                    'debug'      => $this->options['debug'],
                    'cache_type' => isset($this->options['cache_type']) ? $this->options['cache_type'] : null,
                )
            );
        }

        return $this->serverFactory;
    }
}