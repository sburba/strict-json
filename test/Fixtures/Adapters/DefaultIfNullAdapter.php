<?php declare(strict_types=1);

namespace Burba\StrictJson\Fixtures\Adapters;

use Burba\StrictJson\Adapter;
use Burba\StrictJson\JsonPath;
use Burba\StrictJson\StrictJson;
use Burba\StrictJson\Type;

/**
 * Don't actually do something like this in real code, just use default values for your constructor parameters!
 */
class DefaultIfNullAdapter implements Adapter
{
    /** @var float */
    private $default_value;

    public function __construct(float $default_value)
    {
        $this->default_value = $default_value;
    }

    /**
     * @param float|null $decoded_json
     * @param StrictJson $delegate
     * @param JsonPath $path
     *
     * @return float
     */
    public function fromJson($decoded_json, StrictJson $delegate, JsonPath $path): float
    {
        return $decoded_json === null ? $this->default_value : $decoded_json;
    }

    /** @return Type[] */
    public function fromTypes(): array
    {
        return [Type::float()->asNullable()];
    }
}
