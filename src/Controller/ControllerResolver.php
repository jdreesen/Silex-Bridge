<?php

namespace DI\Bridge\Silex\Controller;

use Interop\Container\ContainerInterface;
use Invoker\CallableResolver;
use Invoker\Exception\NotCallableException;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
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
     * @var CallableResolver
     */
    private $callableResolver;

    /**
     * @var ParameterResolver
     */
    private $parameterResolver;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->callableResolver = new CallableResolver($container);
        $this->parameterResolver = new ResolverChain([
            new AssociativeArrayResolver,
            new TypeHintContainerResolver($container),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getController(Request $request)
    {
        if (! $controller = $request->attributes->get('_controller')) {
            throw new \LogicException(sprintf(
                'Controller for URI "%s" could not be found because the "_controller" parameter is missing.',
                $request->getPathInfo()
            ));
        }

        try {
            return $this->callableResolver->resolve($controller);
        } catch (NotCallableException $e) {
            throw new \InvalidArgumentException(sprintf(
                'Controller for URI "%s" is not callable: %s',
                $request->getPathInfo(),
                $e->getMessage()
            ));
        }
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

        $arguments = $this->parameterResolver->getParameters(
            $controllerReflection,
            $request->attributes->all(),
            $resolvedArguments
        );

        ksort($arguments);

        return $arguments;
    }
}
