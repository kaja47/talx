<?php
 
function countChange($amount) {
  return cc($amount, 5);
}
 
function cc($amount, $kindOfCoins) {
  if ($amount === 0) return 1;
  if ($amount < 0) return 0;
  if ($kindOfCoins === 0) return 0;
 
  return cc($amount, $kindOfCoins - 1) + cc($amount - firstDenomination($kindOfCoins), $kindOfCoins);
}
 
function firstDenomination($kindOfCoins) {
  if ($kindOfCoins === 1) return 1;
  if ($kindOfCoins === 2) return 5;
  if ($kindOfCoins === 3) return 10;
  if ($kindOfCoins === 4) return 25;
  if ($kindOfCoins === 5) return 50;
}
 
var_dump(countChange(100));
