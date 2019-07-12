<?php declare(strict_types=1);

namespace Burba\StrictJson\Feature;

use Burba\StrictJson\Fixtures\Docs\Address;
use Burba\StrictJson\Fixtures\Docs\DateAdapter;
use Burba\StrictJson\Fixtures\Docs\ErrorPathExampleA;
use Burba\StrictJson\Fixtures\Docs\ErrorPathExampleRoot;
use Burba\StrictJson\Fixtures\Docs\Event;
use Burba\StrictJson\Fixtures\Docs\LenientBooleanAdapter;
use Burba\StrictJson\Fixtures\Docs\ModelWithNullableParam;
use Burba\StrictJson\Fixtures\Docs\ModelWithOptionalParam;
use Burba\StrictJson\Fixtures\Docs\User;
use Burba\StrictJson\JsonFormatException;
use Burba\StrictJson\StrictJson;
use Burba\StrictJson\Type;
use DateTime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Suite of tests that verify that the Readme examples work
 */
class DocsTest extends TestCase
{
    /**
     * @throws JsonFormatException
     */
    public function testBasicExample(): void
    {
        $json = '
        {
          "name": "Joe User",
          "age": 4,
          "address": {
            "street": "1234 Fake St.",
            "zip_code": "12345"
          }
        }
        ';

        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(User::class, 'events_attended', Event::class)
            ->build();

        $this->assertEquals(
            new User('Joe User', 4, new Address('1234 Fake St.', '12345')),
            $mapper->map($json, User::class)
        );
    }

    public function testBasicExampleHandwritten(): void
    {
        $json = '
        {
          "name": "Joe User",
          "age": 4,
          "address": {
            "street": "1234 Fake St.",
            "zip_code": "12345"
          }
        }
        ';

        $decoded_json = json_decode($json, true);
        if (!is_array($decoded_json)) {
            throw new RuntimeException('Invalid JSON');
        }

        $name = $decoded_json['name'] ?? null;
        $age = $decoded_json['age'] ?? null;
        $street = $decoded_json['address']['street'] ?? null;
        $zip_code = $decoded_json['address']['zip_code'] ?? null;

        if (!is_string($name) || !is_int($age) || !is_string($street) || !is_string($zip_code)) {
            throw new RuntimeException('Invalid JSON');
        }

        $address = new Address($street, $zip_code);
        $user = new User($name, $age, $address);

        $this->assertEquals(
            new User('Joe User', 4, new Address('1234 Fake St.', '12345')),
            $user
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testOptionalParamExample(): void
    {
        $mapper = new StrictJson();
        /** @var ModelWithOptionalParam $model */
        $model = $mapper->map('{}', ModelWithOptionalParam::class);
        $this->assertEquals(
            'default',
            $model->getOptionalParam()
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testClassAdapterExample(): void
    {
        $json = '
        {
            "name": "Dinner party for Bob",
            "date": "2013-02-13T08:35:34Z"
        }
        ';

        $mapper = StrictJson::builder()->addClassAdapter(DateTime::class, new DateAdapter())->build();
        /** @var Event $event */
        $event = $mapper->map($json, Event::class);
        $this->assertEquals('2013', $event->getDate()->format('Y'));
    }

    /**
     * @throws JsonFormatException
     */
    public function testParameterAdapterExample(): void
    {
        $json = '
        {
            "name": "Dinner party for Bob",
            "date": "2013-02-13T08:35:34Z",
            "is_suit_required": 1
        }
        ';

        $mapper = StrictJson::builder()
            ->addClassAdapter(DateTime::class, new DateAdapter())
            ->addParameterAdapter(Event::class, 'is_suit_required', new LenientBooleanAdapter())
            ->build();

        /** @var Event $event */
        $event = $mapper->map($json, Event::class);
        $this->assertTrue($event->isSuitRequired());
    }

    /**
     * @throws JsonFormatException
     */
    public function testTypeAdapterExample(): void
    {
        $mapper = StrictJson::builder()
            ->addClassAdapter(DateTime::class, new DateAdapter())
            ->addTypeAdapter(Type::bool(), new LenientBooleanAdapter())
            ->build();

        $json = '
        {
            "name": "Dinner party for Bob",
            "date": "2013-02-13T08:35:34Z",
            "is_suit_required": 1
        }
        ';

        /** @var Event $event */
        $event = $mapper->map($json, Event::class);
        $this->assertTrue($event->isSuitRequired());
    }

    /**
     * @throws JsonFormatException
     */
    public function testArrayAdapterExample(): void
    {
        $json = '
        {
            "name": "Tim Fabulous",
            "age": 40,
            "address": {
                "street": "1234 Fake St.",
                "zip_code": "12345"
            },
            "events_attended": [
                {
                    "name": "Dinner party for Bob",
                    "date": "2013-02-13T08:35:34Z"
                }
            ]
        }
        ';

        $mapper = StrictJson::builder()
            ->addClassAdapter(DateTime::class, new DateAdapter())
            ->addParameterArrayAdapter(User::class, 'events_attended', Event::class)
            ->build();

        /** @var User $user */
        $user = $mapper->map($json, User::class);
        $this->assertEquals(
            'Dinner party for Bob',
            $user->getEventsAttended()[0]->getName()
        );
    }

    /**
     * @throws JsonFormatException
     */
    public function testNullableParamExample(): void
    {
        $json = '{"nullable_param": null}';
        $mapper = new StrictJson();
        /** @var ModelWithNullableParam $model */
        $model = $mapper->map($json, ModelWithNullableParam::class);
        $message = is_null($model->getNullableParam()) ? 'Param is null' : 'Param is not null';
        $this->assertEquals('Param is null', $message);
    }

    /**
     * @throws JsonFormatException
     */
    public function testErrorPathExample(): void
    {
        $json = '
        {
          "a": {
            "b": [1, "two", 3]
          }
        }
        ';

        $mapper = StrictJson::builder()
            ->addParameterArrayAdapter(ErrorPathExampleA::class, 'b', Type::int())
            ->build();

        $this->expectException(JsonFormatException::class);
        $this->expectExceptionMessage('Value is of type string, expected type int at path $.a.b[1]');
        $mapper->map($json, ErrorPathExampleRoot::class);
    }
}
