<?php declare(strict_types=1);

namespace Burba\StrictJson;

use Burba\StrictJson\Fixtures\Adapters\AdapterThatSupportsNoTypes;
use Burba\StrictJson\Fixtures\Adapters\AdapterThatThrowsJsonFormatException;
use Burba\StrictJson\Fixtures\Adapters\AdapterThatThrowsRuntimeException;
use Burba\StrictJson\Fixtures\Adapters\DefaultIfNullAdapter;
use Burba\StrictJson\Fixtures\Adapters\IntPropClassAdapterThatAddsFour;
use Burba\StrictJson\Fixtures\BasicClass;
use Burba\StrictJson\Fixtures\Docs\LenientBooleanAdapter;
use Burba\StrictJson\Fixtures\HasClassProp;
use Burba\StrictJson\Fixtures\HasIntArrayProp;
use Burba\StrictJson\Fixtures\HasIntProp;
use Burba\StrictJson\Fixtures\HasNullableProp;
use Burba\StrictJson\Fixtures\HasObjectProp;
use Burba\StrictJson\Fixtures\MissingConstructor;
use Burba\StrictJson\Fixtures\NoTypesInConstructor;
use Burba\StrictJson\Fixtures\ThrowsInvalidArgumentException;
use Burba\StrictJson\Fixtures\ThrowsUnexpectedException;
use PHPUnit\Framework\TestCase;

class StrictJsonTest extends TestCase
{
    /**
     * @throws JsonFormatException
     */
    public function testBasicCase()
    {
        $json = '
        {
            "string_prop": "string_value",
            "int_prop": 1,
            "float_prop": 1.2,
            "bool_prop": true,
            "array_prop": [1, 2, 3],
            "class_prop": {
                "int_prop": 5
            }
        }
        ';

        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(BasicClass::class, 'array_prop', Type::int())
            ->build();
        $this->assertEquals(
            new BasicClass(
                'string_value',
                1,
                1.2,
                true,
                [1, 2, 3],
                new HasIntProp(5)
            ),
            $mapper->map($json, BasicClass::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testMapToArrayOf()
    {
        $json = '[{"int_prop": 1}, {"int_prop": 2}]';
        $mapper = new StrictJson();
        $this->assertEquals(
            [new HasIntProp(1), new HasIntProp(2)],
            $mapper->mapToArrayOf($json, HasIntProp::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testMapDecodedWithArray()
    {
        $decoded_json = ['does not' => 'matter'];
        $mapper = new StrictJson();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot map to arrays directly, use StrictJson::mapToArrayOf() at path <json_root>');
        $mapper->mapDecoded($decoded_json, Type::array(), JsonContext::root());
    }

    /**
     * @throws JsonFormatException
     */
    public function testArrayParameterWithoutAdapter()
    {
        $json = '{"int_array_prop": [1, 2, 3]}';
        $mapper = new StrictJson();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('has parameter name int_array_prop of type array with no parameter adapter');
        $mapper->map($json, HasIntArrayProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testIntArrayProperty()
    {
        $json = '{ "int_array_prop": [1, 2, 3] }';
        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(HasIntArrayProp::class, 'int_array_prop', Type::int())
            ->build();

        $this->assertEquals(
            new HasIntArrayProp([1, 2, 3]),
            $mapper->map($json, HasIntArrayProp::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testClassAdapterForRootObject()
    {
        $mapper = StrictJson::builder()
            ->addClassAdapter(HasIntProp::class, new IntPropClassAdapterThatAddsFour())
            ->build();

        $json = '{ "int_prop": 1 }';
        $this->assertEquals(
            new HasIntProp(5),
            $mapper->map($json, HasIntProp::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testClassAdapterForProperty()
    {
        $mapper = StrictJson::builder()
            ->addClassAdapter(HasIntProp::class, new IntPropClassAdapterThatAddsFour())
            ->build();

        $json = '{ "int_prop_class": { "int_prop": 1 } }';
        $this->assertEquals(
            new HasClassProp(new HasIntProp(5)),
            $mapper->map($json, HasClassProp::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testInvalidJson()
    {
        $mapper = new StrictJson();
        $json = '{ invalid';
        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage("Unable to parse invalid JSON (Syntax error): $json");
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testInvalidTargetType()
    {
        $mapper = new StrictJson();
        $json = '{"does_not": "matter"}';
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Type "invalid" is not a valid class');
        $mapper->map($json, 'invalid');
    }

    /**
     * @throws JsonFormatException
     */
    public function testClassAdapterThatThrowsJsonFormatException()
    {
        $mapper = new StrictJson([HasIntProp::class => new AdapterThatThrowsJsonFormatException()]);
        $json = '{"does_not": "matter"}';
        $this->expectException(JsonFormatException::class);
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * Verify that parsing json with types that don't match the target class' constructor args throws a
     * JsonFormatException
     * @throws JsonFormatException
     */
    public function testMismatchedTypes()
    {
        $mapper = new StrictJson();
        $json = '{"int_prop": "1"}';
        $this->expectException(JsonFormatException::class);
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * Verify that trying to map to a class that has constructor arguments that don't have types throws an
     * InvalidConfigurationException
     *
     * @throws JsonFormatException
     */
    public function testClassWithNonTypedConstructorArgs()
    {
        $mapper = new StrictJson();
        $json = '{"unknown_property": "value"}';
        $this->expectException(InvalidConfigurationException::class);
        $mapper->map($json, NoTypesInConstructor::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testMissingProperty()
    {
        $mapper = new StrictJson();
        $json = '{"unknown_property": "value"}';
        $this->expectException(JsonFormatException::class);
        $classname = HasIntProp::class;
        $this->expectExceptionMessage("{$classname}::__construct has non-optional parameter named int_prop that does not exist in JSON");
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testJsonHasWrongItemType()
    {
        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(HasIntArrayProp::class, 'int_array_prop', Type::int())
            ->build();
        $json = '{"int_array_prop": [1, "2", 3]}';
        $this->expectException(JsonFormatException::class);
        $mapper->map($json, HasIntArrayProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testMissingConstructor()
    {
        $mapper = new StrictJson();
        $json = '{"does not": "matter"}';
        $this->expectException(InvalidConfigurationException::class);
        $classname = MissingConstructor::class;
        $this->expectExceptionMessage("Type $classname does not have a valid constructor");
        $mapper->map($json, MissingConstructor::class);
    }

    /**
     * Verify that StrictJson throws an exception when an Adapter specifies a type but the JSON type doesn't match
     *
     * @throws JsonFormatException
     */
    public function testMismatchedAdapterParameterJsonField()
    {
        $mapper = new StrictJson([HasIntProp::class => new IntPropClassAdapterThatAddsFour()]);
        $json = '{"int_prop_class": 4}';
        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('Expected array, found integer');
        $mapper->map($json, HasClassProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testNullableParameterWithNullValue()
    {
        $mapper = new StrictJson();
        $json = '{"nullable_prop": null}';
        $this->assertEquals(
            new HasNullableProp(null),
            $mapper->map($json, HasNullableProp::class)
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testNullValueForNonNullableParameter()
    {
        $mapper = new StrictJson();
        $json = '{"int_prop": null}';
        $this->expectException(JsonFormatException::class);
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testMissingPropertyInNestedClass()
    {
        $json = '
        {
            "string_prop": "string_value",
            "int_prop": 1,
            "float_prop": 1.2,
            "bool_prop": true,
            "array_prop": [1, 2, 3],
            "class_prop": {
            }
        }
        ';

        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(BasicClass::class, 'array_prop', Type::int())
            ->build();
        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('Burba\StrictJson\Fixtures\HasIntProp::__construct has non-optional parameter named int_prop that does not exist in JSON at path $.class_prop');
        $mapper->map($json, BasicClass::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testUnsupportedType()
    {
        $json = '{"object": {"should not": "work"}}';
        $mapper = new StrictJson();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unsupported type object at path $.object');
        $mapper->map($json, HasObjectProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testMissingPropertyInNestedArray()
    {
        $json = '
        {
            "string_prop": "string_value",
            "int_prop": 1,
            "float_prop": 1.2,
            "bool_prop": true,
            "array_prop": [1, "two", 3],
            "class_prop": {
                "int_prop": 1
            }
        }
        ';

        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(BasicClass::class, 'array_prop', Type::int())
            ->build();
        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('Value is of type string, expected type int at path $.array_prop[1]');
        $mapper->map($json, BasicClass::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testAdapterThatThrowsRuntimeException()
    {
        $json = '{"does not": "matter"}';
        $mapper = StrictJson::builder()
            ->addClassAdapter(HasIntProp::class, new AdapterThatThrowsRuntimeException())
            ->build();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('threw an exception');
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testAdapterThatSupportsNoTypes()
    {
        $json = '{"does not": "matter"}';
        $mapper = StrictJson::builder()
            ->addClassAdapter(HasIntProp::class, new AdapterThatSupportsNoTypes())
            ->build();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not support any types!');
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testAdapterThatSupportsManyTypesNotMatching()
    {
        $json = '{"does not": "matter"}';
        $mapper = StrictJson::builder()
            ->addClassAdapter(HasIntProp::class, new LenientBooleanAdapter())
            ->build();

        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('Expected one of [int, bool], found array');
        $mapper->map($json, HasIntProp::class);
    }

    /**
     * @throws JsonFormatException
     */
    public function testAdapterThatSupportsNullable()
    {
        $json = '{"nullable_prop": null}';
        $mapper = StrictJson::builder()
            ->addParameterAdapter(HasNullableProp::class, 'nullable_prop', new DefaultIfNullAdapter(1.4))
            ->build();

        $this->assertEquals(
            new HasNullableProp(1.4),
            $mapper->map($json, HasNullableProp::class)
        );
    }

    /**
     * Verify that we wrap InvalidArgumentExceptions in JsonFormatExceptions
     *
     * @throws JsonFormatException
     */
    public function testModelThatThrowsInvalidArgumentException()
    {
        $json = '{"value": "not good enough"}';
        $mapper = new StrictJson();
        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('threw a validation exception in the constructor');
        $mapper->map($json, ThrowsInvalidArgumentException::class);
    }

    /** @throws JsonFormatException */
    public function testModelThatThrowsUnexpectedException()
    {
        $json = '{"value": "not good enough"}';
        $mapper = new StrictJson();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unable to construct object');
        $mapper->map($json, ThrowsUnexpectedException::class);
    }
}
