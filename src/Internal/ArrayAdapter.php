<?php declare(strict_types=1);

namespace Burba\StrictJson\Internal;

use Burba\StrictJson\Adapter;
use Burba\StrictJson\JsonFormatException;
use Burba\StrictJson\JsonPath;
use Burba\StrictJson\StrictJson;
use Burba\StrictJson\Type;

/**
 * This class is not subject to semantic versioning compatibility guarantees
 */
class ArrayAdapter implements Adapter
{
    /** @var Type */
    private $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    /**
     * @param array $items
     * @param StrictJson $delegate
     * @param JsonPath $path
     * @return array
     *
     * @throws JsonFormatException
     */
    public function fromJson($items, StrictJson $delegate, JsonPath $path)
    {
        $mapped_items = [];
        foreach ($items as $idx => $item) {
            if (!is_int($idx)) {
                throw new JsonFormatException('Expected array, found object', $path);
            }
            $mapped_items[] = $delegate->mapDecoded($item, $this->type, $path->withArrayIndex($idx));
        }
        return $mapped_items;
    }

    public function fromTypes(): array
    {
        return [Type::array()];
    }
}
