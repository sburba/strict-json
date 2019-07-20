<?php declare(strict_types=1);

namespace Burba\StrictJson;

use Burba\StrictJson\Internal\ArrayAdapter;
use Burba\StrictJson\Internal\ConstructorParameterFetcher;
use Burba\StrictJson\Internal\TypedParameter;
use Exception;
use InvalidArgumentException;

class StrictJson
{
    /** @var array */
    private $type_adapters;
    /** @var ConstructorParameterFetcher */
    private $parameter_finder;


    /**
     * Create an instance of StrictJson configured with type and parameter adapters.
     *
     * If you're doing more than very simple configuration, you probably want to use the static build method, which
     * gives you a nicer fluent interface for configuration
     *
     * @param array $type_adapters A mapping of string type names to adapter objects. The type names can be either full
     * class names (including the namespace) or names of primitives if you want to change the way all primitives are
     * mapped
     * @param ConstructorParameterFetcher|null $parameter_finder Don't use this, use StrictJson::builder. This parameter
     * is not subject to semantic versioning compatibility guarantees
     *
     * @see StrictJson::builder()
     */
    public function __construct(array $type_adapters = [], ?ConstructorParameterFetcher $parameter_finder = null)
    {
        $this->type_adapters = $type_adapters;
        $this->parameter_finder = $parameter_finder ?? new ConstructorParameterFetcher();
    }

    /**
     * Create a builder for this class for advanced fluent configuration
     *
     * @return StrictJsonBuilder
     */
    public static function builder(): StrictJsonBuilder
    {
        return new StrictJsonBuilder();
    }

    /**
     * Convert the given json into an instance of the given class
     *
     * @param string $json The json string to convert
     * @param string|Type $type Either the class or a Type to map to
     *
     * @return object The parsed JSON in the type of $class
     *
     * @throws JsonFormatException If $json is not valid JSON, or if the constructor for $class has parameters that do
     * not match in name AND type to the JSON's properties
     */
    public function map(string $json, $type)
    {
        $type = $type instanceof Type ? $type : Type::ofClass($type);
        return $this->mapDecoded($this->safeDecode($json), $type, JsonPath::root());
    }

    /**
     * Convert a JSON array of items into a PHP array of items of the specified type
     *
     * @param string $json The JSON string
     * @param string|Type $type Either the class or a Type to map each item in the json array to
     *
     * @return mixed An array of mapped items
     *
     * @throws JsonFormatException If any of the items do not match the specified type, or the JSON is invalid
     */
    public function mapToArrayOf(string $json, $type)
    {
        $type = $type instanceof Type ? $type : Type::ofClass($type);
        return $this->mapWithAdapter($this->safeDecode($json), new ArrayAdapter($type), JsonPath::root());
    }

    /**
     * Convert decoded json into an instance of the given target type
     *
     * @param mixed $decoded_json An associative array or other primitive
     * @param Type $target_type Either a class name or a scalar name (i.e. string, int, float, bool)
     * @param JsonPath $path The current JSON parsing path, or null if being called at the root of the decoded JSON
     *
     * @return mixed
     * @throws JsonFormatException
     */
    public function mapDecoded($decoded_json, Type $target_type, JsonPath $path)
    {
        $adapter = $this->type_adapters[$target_type->getTypeName()] ?? null;
        if ($adapter !== null) {
            return $this->mapWithAdapter($decoded_json, $adapter, $path);
        }

        if ($target_type->isScalar()) {
            return $this->mapScalar($decoded_json, $target_type, $path);
        }

        if ($target_type->isClass()) {
            return $this->mapClass($decoded_json, $target_type, $path);
        }

        if ($target_type->isArray()) {
            throw new InvalidConfigurationException("Cannot map to arrays directly, use StrictJson::mapToArrayOf()", $path);
        }

        // It should be impossible to get here, Type should either be a scalar, a class, or an array
        //@codeCoverageIgnoreStart
        throw new InvalidConfigurationException("Target type \"$target_type\" is not a scalar type or valid class and has no registered type adapter", $path);
        //@codeCoverageIgnoreEnd
    }

    /**
     * Map a given decoded json value with the specified adapter
     *
     * @param string|int|array|bool|float $value The decoded json value to adapt
     * @param Adapter $adapter Object with a fromJson method
     * @param JsonPath $path The current decoding path
     *
     * @return mixed Whatever the adapter returns
     * @throws JsonFormatException If the provided value doesn't match the value the adapter expects
     */
    private function mapWithAdapter($value, Adapter $adapter, JsonPath $path)
    {
        $path = $path ?? JsonPath::root();

        $supports_value = false;
        foreach ($adapter->fromTypes() as $supported_type) {
            $supports_value = $supports_value || $supported_type->allowsValue($value);
        }

        if (!$supports_value) {
            $json_type = gettype($value);
            $num_parameters = count($adapter->fromTypes());
            $adapter_class = get_class($adapter);
            if ($num_parameters > 1) {
                $type_names = [];
                foreach ($adapter->fromTypes() as $type) {
                    $type_names[] = $type->__toString();
                }
                $type_name_list = join(', ', $type_names);
                $expectation = "one of [$type_name_list]";
            } elseif ($num_parameters > 0) {
                $expectation = $adapter->fromTypes()[0]->__toString();
            } else {
                throw new InvalidConfigurationException(
                    "Adapter $adapter_class does not support any types! (fromTypes must return a non-empty array)",
                    $path
                );
            }

            throw new JsonFormatException("Expected $expectation, found $json_type (using $adapter_class)", $path);
        }

        try {
            return $adapter->fromJson($value, $this, $path);
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (JsonFormatException $e) {
            throw $e;
        } catch (Exception $e) {
            $adapter_class = get_class($adapter);
            throw new InvalidConfigurationException("Adapter {$adapter_class} threw an exception", $path, $e);
        }
    }

    /**
     * @param mixed $decoded_json
     * @param Type $target_type
     * @param JsonPath $path
     *
     * @return mixed
     * @throws JsonFormatException
     */
    private function mapScalar($decoded_json, Type $target_type, JsonPath $path)
    {
        if ($target_type->allowsValue($decoded_json)) {
            return $decoded_json;
        } else {
            $json_type = gettype($decoded_json);
            throw new JsonFormatException("Value is of type $json_type, expected type $target_type", $path);
        }
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param mixed $decoded_json
     * @param Type $target_type
     * @param JsonPath $path
     *
     * @return object
     * @throws JsonFormatException
     */
    private function mapClass($decoded_json, Type $target_type, JsonPath $path): object
    {
        $type_name = $target_type->getTypeName();
        $parameters = $this->parameter_finder->getParameters($type_name, $path);

        if (!is_array($decoded_json)) {
            $json_type = gettype($decoded_json);
            throw new JsonFormatException("Expected object, found $json_type", $path);
        }

        $constructor_args = [];
        /**
         * @var string $param_name
         * @var TypedParameter $param
         */
        foreach ($parameters as $param_name => $param) {
            if (array_key_exists($param_name, $decoded_json)) {
                $value = $decoded_json[$param_name];
                $param_path = $path->withProperty($param_name);
                if ($param->getAdapter() !== null) {
                    $value = $this->mapWithAdapter($value, $param->getAdapter(), $param_path);
                } else {
                    $value = $this->mapDecoded($value, $param->getType(), $param_path);
                }
                $constructor_args[] = $value;
            } elseif ($param->hasDefaultValue()) {
                $constructor_args[] = $param->getDefaultValue();
            } else {
                throw new JsonFormatException("$target_type::__construct has non-optional parameter named $param_name that does not exist in JSON", $path);
            }
        }

        try {
            return new $type_name(...$constructor_args);
        } catch (InvalidArgumentException $e) {
            $encoded_args = json_encode($constructor_args);
            throw new JsonFormatException("{$type_name}::_construct threw a validation exception for args $encoded_args", $path, $e);
        } catch (Exception $e) {
            $encoded_args = json_encode($constructor_args);
            throw new InvalidConfigurationException(
                "Unable to construct object of type {$type_name} with args $encoded_args",
                $path,
                $e
            );
        }
    }

    /**
     * Decode the specified JSON, throwing if it's invalid JSON
     *
     * @param string $json
     * @return mixed
     *
     * @throws JsonFormatException
     */
    private function safeDecode(string $json)
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $err = json_last_error_msg();
            throw new JsonFormatException(
                "Unable to parse invalid JSON ($err): $json",
                JsonPath::root()
            );
        }
        return $decoded;
    }
}
