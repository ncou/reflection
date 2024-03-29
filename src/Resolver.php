<?php

declare(strict_types=1);

namespace Chiron\Reflection;

use Chiron\Reflection\Exception\CannotResolveException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionObject;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use Closure;
use RuntimeException;
use ReflectionFunctionAbstract;
use InvalidArgumentException;
use Throwable;

use ReflectionMethod;
use ReflectionException;

//https://github.com/yiisoft/injector/blob/master/src/Injector.php#L121
//https://github.com/yiisoft/injector/blob/3bd38d4ebc70f39050e4ae056ac10c40c4975cb1/src/Injector.php#L196
//https://github.com/spiral/framework/blob/1a8851523ad1eb62bcbb50be7eff47646c711692/src/Core/src/Container.php#L110

final class Resolver
{
    /** ContainerInterface */
    private $container;

    /**
     * Invoker constructor.
     *
     * @param $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    //https://github.com/yiisoft/injector/blob/3bd38d4ebc70f39050e4ae056ac10c40c4975cb1/src/Injector.php#L135
    // TODO : gérer le PHP8 ReflectionUnionType   https://github.com/spiral/framework/blob/1a8851523ad1eb62bcbb50be7eff47646c711692/src/Core/src/Container.php#L110
    public function resolveArguments(ReflectionFunctionAbstract $reflection, array $parameters = []): array {
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {

            try {


                //Information we need to know about argument in order to resolve it's value
                $name = $parameter->getName();
                $class = $parameter->getClass();



            } catch (\ReflectionException $e) {

                //throw new CannotResolveException($parameter);


                //Possibly invalid class definition or syntax error
                throw new InvalidArgumentException(sprintf('Invalid value for parameter %s', Reflection::toString($parameter)), $e->getCode());
                //throw new InvocationException("Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}", $e->getCode());
                //throw new InvocationException("Unresolvable dependency resolving [$parameter] in function " . $parameter->getDeclaringClass()->getName() . '::' . $parameter->getDeclaringFunction()->getName(), $e->getCode());
            }


            //die(var_dump($class));

            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                //Supplied by user as object
                $arguments[] = $parameters[$name];
                continue;
            }
            //No declared type or scalar type or array
            if (empty($class)) {
                //Provided from outside
                if (array_key_exists($name, $parameters)) {
                    //Make sure it's properly typed
                    $this->assertType($parameter, $parameters[$name]);
                    $arguments[] = $parameters[$name];
                    continue;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    //Default value
                    //$arguments[] = $parameter->getDefaultValue();
                    $arguments[] = Reflection::getParameterDefaultValue($parameter);
                    continue;
                }
                //Unable to resolve scalar argument value
                throw new CannotResolveException($parameter);
            }

            try {
                //Requesting for contextual dependency
                $arguments[] = $this->container->get($class->getName());
                continue;
            } catch (ContainerExceptionInterface $e) {
                if ($parameter->isOptional()) {
                    //This is optional dependency, skip
                    $arguments[] = null;
                    continue;
                }
                throw $e;
            }
        }

        return $arguments;
    }

    /**
     * Assert that given value are matched parameter type.
     *
     * @param \ReflectionParameter        $parameter
     * @param mixed                       $value
     *
     * @throws CannotResolveException
     */
    private function assertType(ReflectionParameter $parameter, $value): void
    {
        if ($value === null) {
            if (!$parameter->isOptional() &&
                !($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === null)
            ) {
                throw new CannotResolveException($parameter);
            }
            return;
        }

        // TODO : utiliser la méthode hasType()
        $type = $parameter->getType();

        if ($type === null) {
            return;
        }

        // TODO : on devrait aussi vérifier que la classe est identique, et vérifier aussi le type string pour que cette méthode soit plus générique. Vérifier ce qui se passe si on fait pas cette vérification c'est à dire appeller une fonction avec des paramétres qui n'ont pas le bon typehint !!!!
        $typeName = $type->getName();
        if ($typeName == 'array' && !is_array($value)) {
            throw new CannotResolveException($parameter);
        }
        if (($typeName == 'int' || $typeName == 'float') && !is_numeric($value)) {
            throw new CannotResolveException($parameter);
        }
        if ($typeName == 'bool' && !is_bool($value) && !is_numeric($value)) {
            throw new CannotResolveException($parameter);
        }
    }
}
