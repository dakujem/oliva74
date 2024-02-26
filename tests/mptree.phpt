<?php

declare(strict_types=1);

namespace Dakujem\Test;

use Dakujem\Oliva\Exceptions\ExtractorReturnValueIssue;
use Dakujem\Oliva\Exceptions\InternalLogicException;
use Dakujem\Oliva\Exceptions\InvalidInputData;
use Dakujem\Oliva\Exceptions\InvalidNodeFactoryReturnValue;
use Dakujem\Oliva\Exceptions\InvalidTreePath;
use Dakujem\Oliva\MaterializedPath\Path;
use Dakujem\Oliva\MaterializedPath\Support\AlmostThere;
use Dakujem\Oliva\MaterializedPath\Support\ShadowNode;
use Dakujem\Oliva\MaterializedPath\TreeBuilder;
use Dakujem\Oliva\MovableNodeContract;
use Dakujem\Oliva\Node;
use Dakujem\Oliva\Seed;
use Dakujem\Oliva\Tree;
use Tester\Assert;

require_once __DIR__ . '/setup.php';

class Item
{
    public int $id;
    public string $path;

    public function __construct(
        int $id,
        string $path
    ) {
        $this->path = $path;
        $this->id = $id;
    }
}

(function () {
    $data = [
        new Item(1, '000'),
        new Item(2, '001'),
        new Item(3, '003'),
        new Item(4, '000001'),
        new Item(5, '002000'),
        new Item(6, '002'),
        new Item(7, '007007007'),
        new Item(8, '000000'),
        new Item(9, '009'),
    ];

    $builder = new TreeBuilder(
        fn(?Item $item) => new Node($item),
        Path::fixed(
            3,
            fn(?Item $item) => $item !== null ? $item->path : null,
        ),
    );
    $almost = $builder->processInput(
        Seed::nullFirst($data),
    );

    Assert::type(AlmostThere::class, $almost);
    Assert::type(Node::class, $almost->root());
    Assert::null(null !== $almost->root() ? $almost->root()->data() : null);
    Assert::type(Item::class, Seed::firstOf($almost->root()->children())->data());
    Assert::type(ShadowNode::class, $almost->shadow());
    Assert::same($almost->root(), $almost->shadow()->data());

    Assert::same([
        '>' => 'root',
        '>0' => '[1]',
        '>0.0' => '[4]',
        '>0.1' => '[8]',
        '>1' => '[2]',
        '>2' => '[3]',
        '>3' => '[6]',
        '>3.0' => '[5]',
        '>5' => '[9]', // note the index `4` being skipped - this is expected, because the node with ID 7 is not connected to the root and is omitted during reconstruction by the shadow tree, but the index is not changed
    ], TreeTesterTool::visualize($almost->root()));


    $vectorExtractor = Path::fixed(
        3,
        fn($path) => $path,
    );
    Assert::same(['000', '000'], $vectorExtractor('000000'));
    Assert::same(['foo', 'bar'], $vectorExtractor('foobar'));
    Assert::same(['x'], $vectorExtractor('x')); // shorter than 3
    Assert::same([], $vectorExtractor(''));
    Assert::same([], $vectorExtractor(null));
    Assert::throws(function () use ($vectorExtractor) {
        $vectorExtractor(4.2);
    }, InvalidTreePath::class);


    // an empty input can not result in any tree
    Assert::throws(function () use ($builder) {
        $builder->build([]);
    }, InvalidInputData::class, 'No root node found in the input collection.');


    $failingBuilder = new TreeBuilder(fn() => null, fn() => []);
    Assert::throws(function () use ($failingBuilder) {
        $failingBuilder->build([null]);
    }, InvalidNodeFactoryReturnValue::class, 'The node factory must return a movable node instance (' . MovableNodeContract::class . ').');

    $invalidVector = new TreeBuilder(fn() => new Node(null), fn() => null);
    Assert::throws(function () use ($invalidVector) {
        $invalidVector->build([null]);
    }, ExtractorReturnValueIssue::class, 'The vector extractor must return an array.');


    $invalidVectorContents = new TreeBuilder(fn() => new Node(null), fn() => ['a', null]);
    Assert::throws(function () use ($invalidVectorContents) {
        $invalidVectorContents->build([null]);
    }, ExtractorReturnValueIssue::class, 'The vector may only consist of strings or integers.');


    $duplicateVector = new TreeBuilder(fn() => new Node(null), fn() => ['any']);
    Assert::throws(function () use ($duplicateVector) {
        $duplicateVector->build([null, null]);
    }, InvalidInputData::class, 'Duplicate node vector: any');
})();

(function () {
    $collection = [
        new Item(0, ''), // the root
        new Item(1, '.0'),
        new Item(2, '.1'),
        new Item(3, '.3'),
        new Item(4, '.0.0'),
        new Item(5, '.2.0'),
        new Item(6, '.2'),
        new Item(7, '.7.7.7'),
        new Item(8, '.0.1'),
        new Item(9, '.9'),
    ];

    $builder = new TreeBuilder(
        fn(Item $item) => new Node($item),
        Path::delimited(
            '.',
            fn(Item $item) => $item->path,
        ),
    );

    $root = $builder->build(
        $collection,
    );



    Assert::same([
        '>' => '[0]',
        '>0' => '[1]',
        '>0.0' => '[4]',
        '>0.1' => '[8]',
        '>1' => '[2]',
        '>2' => '[3]',
        '>3' => '[6]',
        '>3.0' => '[5]',
        '>5' => '[9]', // note the index `4` being skipped - this is expected
    ], TreeTesterTool::visualize($root));


    Tree::reindexTree($root, fn(Node $node) => $node->data()->id, null);
    Assert::same([
        '>' => '[0]',
        '>1' => '[1]',
        '>1.4' => '[4]',
        '>1.8' => '[8]',
        '>2' => '[2]',
        '>3' => '[3]',
        '>6' => '[6]',
        '>6.5' => '[5]',
        '>9' => '[9]',
    ], TreeTesterTool::visualize($root));

    Tree::reindexTree($root, null, fn(Node $a, Node $b) => $a->data()->path <=> $b->data()->path);
    Assert::same([
        '>' => '[0]',
        '>1' => '[1]', // .0
        '>1.4' => '[4]', // .0.0
        '>1.8' => '[8]', // .0.1
        '>2' => '[2]', // .1
        '>6' => '[6]', // .2
        '>6.5' => '[5]', // .2.0
        '>3' => '[3]', // .3
        '>9' => '[9]', // .9
    ], TreeTesterTool::visualize($root));
})();

(function () {
    $builder = new TreeBuilder(
        fn(?string $path) => new Node($path),
        Path::fixed(
            3,
            fn(?string $path) => $path,
        ),
    );
    $almost = $builder->processInput(
        [],
    );
    Assert::same(null, $almost->shadow());
    Assert::same(null, $almost->root());

    Assert::throws(
        function () use ($builder) {
            $builder->build(
                [],
            );
        },
        InvalidInputData::class,
        'No root node found in the input collection.',
    );

    $root = $builder->build(
        [null],
    );
    Assert::type(Node::class, $root);
    Assert::same(null, $root->data());
    $root = $builder->build(
        [''],
    );
    Assert::type(Node::class, $root);
    Assert::same('', $root->data());

    Assert::throws(
        function () use ($builder) {
            $builder->build(
                ['000'],
            );
        },
        InvalidInputData::class,
        'No root node found in the input collection.',
    );

    // Here no root node will be found.
    Assert::throws(
        function () use ($builder) {
            $builder->build(
                ['007000', '007', '007001'],
            );
        },
        InvalidInputData::class,
        'No root node found in the input collection.',
    );

    // However, a shadow tree will be returned and the node can be accessed, as the first shadow-child in these cases:
    $almost = $builder->processInput(
        ['000'],
    );
    $node = $almost->shadow()->child(0)->data();
    Assert::type(Node::class, $node);
    Assert::same('000', $node->data());

    $almost = $builder->processInput(
        ['007000', '007', '007001'],
    );
    $node = $almost->shadow()->child(0)->data();
    Assert::type(Node::class, $node);
    Assert::same('007', $node->data());
    Assert::count(2, $node->children());
    Assert::same('007000', $node->child(0)->data());
    Assert::same('007001', $node->child(1)->data());
})();

(function () {
    $shadow = new ShadowNode(null);
    Assert::same(null, $shadow->data());

    $shadow = new ShadowNode($node = new Node(null));
    Assert::same($node, $shadow->data());

    $parent = new ShadowNode(new Node('root'));
    $shadow->setParent($parent);
    Assert::same($parent, $shadow->parent());
    Assert::same('root', $shadow->parent() !== null ? $shadow->parent()->data()->data() : null);

    Assert::same([], $shadow->children());
    $shadow->addChild(new ShadowNode(new Node('child')), 0);
    Assert::count(1, $shadow->children());
    Assert::same('child', null !== $shadow->child(0) ? $shadow->child(0)->data()->data() : null);

    Assert::throws(function () use ($shadow) {
        $shadow->addChild(new Node('another'));
    }, InternalLogicException::class, 'Invalid use of a shadow node. Only shadow nodes can be children of shadow nodes.');
    Assert::throws(function () use ($shadow) {
        $shadow->setParent(new Node('new parent'));
    }, InternalLogicException::class, 'Invalid use of a shadow node. Only shadow nodes can be parents of shadow nodes.');
})();