<?php

// Sieve of Eratosthenes
function ints($n) {
  for ($i = $n;; $i++) yield $i;
}

function headTail($gen) {
  $head = $gen->current();
  $gen->next();
  return [$head, $gen];
}

function primes($nums) {
  list($head, $tail) = headTail($nums);
  yield $head;
  foreach (primes($tail) as $p) {
    if ($p % $head != 0)
      yield $p;
  }
}


$ps = primes(ints(2));
foreach ($ps as $p) {
  if ($p > 1000) break;
  echo $p." ";
}
