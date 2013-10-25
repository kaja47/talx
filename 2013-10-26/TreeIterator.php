<?php

interface Tree {
  function preOrder();
  function inOrder();
  function postOrder();
}

class Fork implements Tree {
  private $left, $el, $right;

  function __construct(Tree $left, $el, Tree $right) {
    list($this->left, $this->el, $this->right) = func_get_args();
  }

  function preOrder() {
    yield $this->el;
    foreach ($this->left->preOrder() as $x) yield $x;
    foreach ($this->right->preOrder() as $x) yield $x;
  }

  function inOrder() {
    foreach ($this->left->inOrder() as $x) yield $x;
    yield $this->el;
    foreach ($this->right->inOrder() as $x) yield $x;
  }

  function postOrder() {
    foreach ($this->left->postOrder() as $x) yield $x;
    foreach ($this->right->postOrder() as $x) yield $x;
    yield $this->el;
  }
}

class Leaf implements Tree {
  function preOrder()  { return; yield; }
  function inOrder()   { return; yield; }
  function postOrder() { return; yield; }
}

// ***

$leaf = new Leaf();

$t = new Fork(new Fork($leaf, 1, $leaf), 3, new Fork($leaf, 2, $leaf));
foreach ($t->inOrder() as $x)
  echo $x, "\n";
