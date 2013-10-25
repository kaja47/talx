<?php
 
function countChange($amount) {
  return cc($amount, 5);
}
 
function cc($amount, $kindOfCoins) {
  if ($amount === 0) { yield []; return; }
  if ($amount < 0) return;
  if ($kindOfCoins === 0) return;
 
 
  foreach (cc($amount, $kindOfCoins - 1) as $c)
    yield $c;
 
  foreach (cc($amount - firstDenomination($kindOfCoins), $kindOfCoins) as $c)
    yield array_merge([firstDenomination($kindOfCoins)], $c);
}
 
function firstDenomination($kindOfCoins) {
  if ($kindOfCoins === 1) return 1;
  if ($kindOfCoins === 2) return 5;
  if ($kindOfCoins === 3) return 10;
  if ($kindOfCoins === 4) return 25;
  if ($kindOfCoins === 5) return 50;
}
 
var_dump(count(iterator_to_array(countChange(100))));
foreach (countChange(100) as $cc) {
  echo join(" ", $cc), "\n";
}
