<?php declare(strict_types=1);

namespace Burba\StrictJson\Fixtures\Adapters;

use Burba\StrictJson\Adapter;
use Burba\StrictJson\JsonPath;
use Burba\StrictJson\StrictJson;
use Burba\StrictJson\Type;
use RuntimeException;

class AdapterThatThrowsRuntimeException implements Adapter
{
    public function fromJson($decoded_json, StrictJson $delegate, JsonPath $path)
    {
        throw new RuntimeException("I'm a very bad adapter");
    }

    /**
     * @return Type[]
     */
    public function fromTypes(): array
    {
        return [Type::array()];
    }
}
