<?php declare(strict_types=1);

namespace Burba\StrictJson\Fixtures\Adapters;

use Burba\StrictJson\Adapter;
use Burba\StrictJson\JsonPath;
use Burba\StrictJson\StrictJson;
use Burba\StrictJson\Type;

class IntAdapterThatAddsFour implements Adapter
{

    /**
     * @param int $decoded_json
     * @param StrictJson $delegate
     * @param JsonPath $path
     * @return int
     */
    public function fromJson($decoded_json, StrictJson $delegate, JsonPath $path): int
    {
        return $decoded_json + 4;
    }

    /**
     * @return Type[]
     */
    public function fromTypes(): array
    {
        return [Type::int()];
    }
}
