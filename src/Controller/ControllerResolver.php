<?php

namespace DI\Bridge\Silex\Controller;

use DI\InvokerInterface;
use Interop\Container\ContainerInterface;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\ParameterResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\Reflection\CallableReflection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

/**
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class ControllerResolver implements ControllerResolverInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var InvokerInterface
     */
    private $invoker;

    /**
     * @var ParameterResolver|null
     */
    private $parameterResolver;

    public function __construct(ContainerInterface $container, InvokerInterface $invoker)
    {
        $this->container = $container;
        $this->invoker = $invoker;
    }

    /**
     * {@inheritdoc}
     */
    public function getController(Request $request)
    {
        $controller = $request->attributes->get('_controller');

        if (! $controller) {
            throw new \LogicException('No controller can be found for this request');
        }

        return function () use ($request, $controller) {
            return $this->invoker->call($controller, $this->getArguments($request, $controller));
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        $controllerReflection = CallableReflection::create($controller);

        $resolvedArguments = [];
        foreach ($controllerReflection->getParameters() as $index => $parameter) {
            if ($parameter->getClass() && $parameter->getClass()->isInstance($request)) {
                $resolvedArguments[$index] = $request;

                break;
            }
        }

        $arguments = $this->getParameterResolver()->getParameters(
            $controllerReflection,
            $request->attributes->all(),
            $resolvedArguments
        );

        ksort($arguments);

        return $arguments;
    }

    /**
     * @return ParameterResolver
     */
    private function getParameterResolver()
    {
        if (null === $this->parameterResolver) {
            $this->parameterResolver = new ResolverChain([
                new AssociativeArrayResolver,
                new TypeHintContainerResolver($this->container),
            ]);
        }

        return $this->parameterResolver;
    }
}
