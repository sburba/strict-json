<?php declare(strict_types=1);

namespace Burba\StrictJson\Internal;

use Burba\StrictJson\InvalidConfigurationException;
use Burba\StrictJson\JsonPath;
use Burba\StrictJson\Type;
use ReflectionClass;
use ReflectionException;

/**
 * This class is not subject to semantic versioning compatibility guarantees
 */
class ConstructorParameterFetcher
{
    /** @var array Mapping of class names to a mapping of parameter names to types */
    private $constructor_params_by_class = [];
    /** @var array Mapping of strings to Adapters */
    private $parameter_adapters;

    public function __construct(array $parameter_adapters = [])
    {
        $this->parameter_adapters = $parameter_adapters;
    }

    /**
     * @param string $classname
     * @param JsonPath $path
     *
     * @return TypedParameter[]
     */
    public function getParameters(string $classname, JsonPath $path): array
    {
        $params = $this->constructor_params_by_class[$classname] ?? $this->findParameters($classname, $path);
        $this->constructor_params_by_class[$classname] = $params;

        return $params;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param string $classname
     * @param JsonPath $path
     *
     * @return TypedParameter[]
     */
    private function findParameters(string $classname, JsonPath $path): array
    {
        try {
            $refl_class = new ReflectionClass($classname);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            // Right now it's not possible to trigger this exception from the public API, because the only method that
            // calls this checks if $classname is a class first
            throw new InvalidConfigurationException("Type $classname is not a valid class", $path, $e);
            // @codeCoverageIgnoreEnd
        }

        $parameters = [];
        $constructor = $refl_class->getConstructor();
        if ($constructor === null) {
            throw new InvalidConfigurationException("Type $classname does not have a valid constructor", $path);
        }
        $refl_params = $constructor->getParameters();
        foreach ($refl_params as $refl_param) {
            $param_name = $refl_param->getName();
            $param_type = Type::from($refl_param, $path);
            /** @noinspection PhpUnhandledExceptionInspection */
            $default_value = $refl_param->isDefaultValueAvailable()
                ? $refl_param->getDefaultValue()
                : TypedParameter::noDefaultValue();
            $adapter = $this->parameter_adapters[$classname][$param_name] ?? null;
            $parameters[$param_name] = new TypedParameter($param_type, $default_value, $adapter);
            if ($param_type->isArray() && $adapter === null) {
                // Catch the array case here, even though it would be caught in mapDecoded, because we have more
                // information for a more helpful error message
                throw new InvalidConfigurationException(
                    "$classname::__construct has parameter name $param_name of type array with no parameter adapter\n"
                    . "(Use StrictJson::builder()->addArrayParameterAdapter(...) to register an array adapter for this class)",
                    $path
                );
            }
        }

        return $parameters;
    }
}
