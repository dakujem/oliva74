<?php

declare(strict_types=1);

namespace Dakujem\Oliva\Iterator;

use Dakujem\Oliva\Iterator\Support\Counter;
use Dakujem\Oliva\TreeNodeContract;
use Generator;
use IteratorAggregate;

/**
 * Depth-first search post-order traversal iterator.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class PostOrderTraversal implements IteratorAggregate
{
    /** @var callable */
    private $key;
    private TreeNodeContract $node;
    private ?array $startingVector = null;

    public function __construct(
        TreeNodeContract $node,
        ?callable $key = null,
        ?array $startingVector = null
    ) {
        $this->startingVector = $startingVector;
        $this->node = $node;
        $this->key = $key ?? fn(TreeNodeContract $node, array $vector, int $seq, int $counter): int => $counter;
    }

    public function getIterator(): Generator
    {
        return $this->generate(
            $this->node,
            $this->startingVector ?? [],
            0,
            new Counter(),
        );
    }

    private function generate(TreeNodeContract $node, array $vector, int $nodeSeq, Counter $counter): Generator
    {
        // $seq is the child sequence number, within the given parent node.
        $seq = 0;
        foreach ($node->children() as $index => $child) {
            yield from $this->generate($child, array_merge($vector, [$index]), $seq, $counter);
            $seq += 1;
        }

        // The yielded key is calculated by the key function.
        // By default, it returns an incrementing sequence to prevent issues with `iterator_to_array` casts.
        yield ($this->key)($node, $vector, $nodeSeq, $counter->touch()) => $node;
    }
}
