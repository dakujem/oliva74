<?php

declare(strict_types=1);

namespace Dakujem\Test;

use Dakujem\Oliva\Exceptions\ExtractorReturnValueIssue;
use Dakujem\Oliva\Exceptions\InvalidInputData;
use Dakujem\Oliva\Iterator\Filter;
use Dakujem\Oliva\Node;
use Dakujem\Oliva\Recursive\TreeBuilder;
use Dakujem\Oliva\Seed;
use Tester\Assert;

require_once __DIR__ . '/setup.php';

class Item
{
    public $id;
    public $parent;

    public function __construct(
        $id,
        $parent
    ) {
        $this->id = $id;
        $this->parent = $parent;
    }
}

(function () {
    $data = [
        new Item(0, null),
    ];

    $builder = new TreeBuilder(
        fn(?Item $item): Node => new Node($item),
        fn(?Item $item): int => null !== $item ? $item->id : null,
        fn(?Item $item): ?int => null !== $item ? $item->parent : null,
    );

    $tree = $builder->build(
        $data,
    );

    Assert::same([
        '>' => '[0]',
    ], TreeTesterTool::visualize($tree));


    $data = [
        new Item(1, 2),
        new Item(2, 4),
        new Item(4, null),
        new Item(8, 7),
        new Item(77, 42),
        new Item(5, 4),
        new Item(6, 5),
        new Item(3, 4),
    ];

    $tree = $builder->build(
        $data,
    );

    Assert::type(Node::class, $tree);

    Assert::same([
        '>' => '[4]',
        '>0' => '[2]',
        '>0.0' => '[1]',
        '>1' => '[5]',
        '>1.0' => '[6]',
        '>2' => '[3]',
    ], TreeTesterTool::visualize($tree));


    //new Filter($it, Seed::omitNull());
    $withoutRoot = fn(iterable $iterator) => new Filter($iterator, Seed::omitRoot());

    Assert::same([
//        '>' => '[4]', is omitted by the Seed::omitRoot() call
        '>0' => '[2]',
        '>0.0' => '[1]',
        '>1' => '[5]',
        '>1.0' => '[6]',
        '>2' => '[3]',
    ], TreeTesterTool::visualize($tree, $withoutRoot));

    $filter = new Filter($collection = [
        new Node(null),
        new Node('ok'),
    ], Seed::omitNull());
    $shouldContainOneElement = iterator_to_array($filter);
    Assert::count(1, $shouldContainOneElement);
    Assert::same(null, Seed::firstOf($collection)->data());
    Assert::same('ok', Seed::firstOf($filter)->data());


    Assert::throws(
        fn() => $builder->build([]),
        InvalidInputData::class,
        'No root node found in the input collection.',
    );
    Assert::throws(
        fn() => $builder->build([new Item( 7,  42)]),
        InvalidInputData::class,
        'No root node found in the input collection.',
    );
})();


(function () {
    $builder = new TreeBuilder(
        fn(?Item $item): Node => new Node($item),
        fn(?Item $item) => null !== $item ? $item->id : null,
        fn(?Item $item) => null !== $item ? $item->parent : null,
        fn(?Item $item): bool => $item->id === 'unknown',
    );

    /** @var Node $root */
    $root = $builder->build([
        new Item('frodo', 'unknown'),
        new Item('sam', 'unknown'),
        new Item('gandalf', 'unknown'),
        new Item('unknown', 'unknown'),
    ]);

    Assert::same('unknown', $root->data()->id);
    Assert::same('unknown', $root->data()->parent);
    Assert::count(3, $root->children());

    Assert::same([
        '>' => '[unknown]',
        '>0' => '[frodo]',
        '>1' => '[sam]',
        '>2' => '[gandalf]',
    ], TreeTesterTool::visualize($root));
})();

(function () {
    $builder = new TreeBuilder(
        fn(?Item $item): Node => new Node($item),
        fn(?Item $item) => null !== $item ? $item->id : null,
        fn(?Item $item) => null !== $item ? $item->parent : null,
    );

    Assert::throws(
        function () use ($builder) {
            $builder->build([
                new Item(null, null),
            ]);
        },
        ExtractorReturnValueIssue::class,
        'Invalid "self reference" returned by the extractor. Requires a int|string unique to the given node.',
    );

    Assert::throws(
        function () use ($builder) {
            $builder->build([
                new Item(123, 4.2),
            ]);
        },
        ExtractorReturnValueIssue::class,
        'Invalid "parent reference" returned by the extractor. Requires a int|string uniquely pointing to "self reference" of another node, or `null`.',
    );


    Assert::throws(
        function () use ($builder) {
            $builder->build([
                new Item(123, null),
                new Item(42, 123),
                new Item(42, 5),
            ]);
        },
        ExtractorReturnValueIssue::class,
        'Duplicate node reference: 42',
    );
})();
