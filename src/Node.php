<?php

declare(strict_types=1);

namespace Dakujem\Oliva;

use Dakujem\Oliva\Exceptions\ChildKeyCollision;
use Dakujem\Oliva\Iterator\Traversal;
use Generator;
use IteratorAggregate;
use JsonSerializable;

/**
 * Flexible data node implementation.
 *
 * Note: Iterating over a node will iterate over the whole subtree in pre-order DFS.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class Node implements TreeNodeContract, DataNodeContract, MovableNodeContract, IteratorAggregate, JsonSerializable
{
    /** @var mixed */
    protected $data;
    protected array $children = [];
    protected ?TreeNodeContract $parent = null;

    /**
     * Create a data node.
     *
     * Note that passing children or parent here does NOT make a link the other way around. Use the Tree utility for that.
     * @see Tree::link()
     * @see Tree::linkChildren()
     */
    public function __construct(
        $data,
        array $children = [],
        ?TreeNodeContract $parent = null
    ) {
        $this->parent = $parent;
        $this->children = $children;
        $this->data = $data;
    }

    /**
     * Get the node's children.
     *
     * @return iterable<int|string,TreeNodeContract>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * Get the node's parent, if any.
     */
    public function parent(): ?TreeNodeContract
    {
        return $this->parent;
    }

    /**
     * Discover whether the given node is one of this node's children (or the given key points to one of them).
     */
    public function hasChild(/*TreeNodeContract|string|int*/ $child): bool
    {
        if (is_scalar($child)) {
            $key = $child;
            $child = $this->child($key);
        } else {
            $key = $this->childKey($child);
        }
        // Note: Important to check both conditions.
        return null !== $child && null !== $key;
    }

    /**
     * Get a specific child, if possible.
     * Returns `null` when there is no such child.
     */
    public function child(/*int|string*/ $key): ?TreeNodeContract
    {
        return $this->children[$key] ?? null;
    }

    /**
     * Get a child's key (index), if possible.
     * Returns `null` when the node is not a child.
     */
    public function childKey(TreeNodeContract $node)//: string|int|null
    {
        foreach ($this->children as $key => $child) {
            if ($child === $node) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Returns `true` if the node has no children, i.e. it is a leaf node.
     */
    public function isLeaf(): bool
    {
        return count($this->children) === 0;
    }

    /**
     * Returns `true` if the node has no parent, i.e. it is a root node.
     */
    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Get the root node.
     * May be self.
     */
    public function root(): TreeNodeContract
    {
        $root = $this;
        while (!$root->isRoot()) {
            $root = $root->parent();
        }
        return $root;
    }

    /**
     * Get the node's assigned data.
     */
    public function data()//: mixed
    {
        return $this->data;
    }

    /**
     * Set/assign new data to the data node.
     */
    public function fill($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set the parent node.
     *
     * Does NOT alter the new parent node, nor the original parent node.
     * Namely, the call does NOT alter the lists of children.
     */
    public function setParent(?TreeNodeContract $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Add a node to the children list,
     * optionally specifying its key (index).
     *
     * Does NOT set the parent on the child node.
     */
    public function addChild(TreeNodeContract $child, /*string|int|null*/ $key = null): self
    {
        if (null === $key) {
            $this->children[] = $child;
        } elseif (!isset($this->children[$key])) {
            $this->children[$key] = $child;
        } else {
            throw (new ChildKeyCollision('Collision not allowed: ' . $key))
                ->tag('parent', $this)
                ->tag('child', $child)
                ->tag('key', $key);
        }
        return $this;
    }

    /**
     * Remove a specific child from the list of children.
     *
     * Does NOT unset the parent of the child being removed.
     */
    public function removeChild(/*TreeNodeContract|string|int*/ $child): self
    {
        $key = is_scalar($child) ? $child : $this->childKey($child);
        if (null !== $key) {
            unset($this->children[$key]);
        }
        return $this;
    }

    /**
     * Remove all children.
     *
     * Does NOT unset the parent of the children nodes being removed.
     */
    public function removeChildren(): self
    {
        $this->children = [];
        return $this;
    }

    /**
     * Returns an iterator that iterates over this node and all its descendants,
     * in pre-order depth-first search order.
     * @return Generator
     */
    public function getIterator(): Generator
    {
        return Traversal::preOrder($this);
    }

    /**
     * @return mixed Intentionally returns mixed and not an array, so that overriding implementations may expand it to whatever value desired.
     */
    public function jsonSerialize()//: mixed
    {
        return [
            'data' => $this->data(),
            'children' => $this->children(),
        ];
    }
}
