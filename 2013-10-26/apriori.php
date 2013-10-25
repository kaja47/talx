<?php // hh

error_reporting(E_ALL);

class ItemsetMap implements ArrayAccess, IteratorAggregate, Countable {
  private $map = []; // [packed int set => val]

  function __construct($init = array()) {
    foreach ($init as $k => $v)
      $this->map[$this->pack(is_array($k) ? $k : [$k])] = $v;
  }

  function offsetGet($k)     { $pk = $this->pack($k); return isset($this->map[$pk]) ? $this->map[$pk] : 0; }
  function offsetSet($k, $v) { return $this->map[$this->pack($k)] = $v; }
  function offsetExists($k)  { return array_key_exists($this->pack($k), $this->map); }
  function offsetUnset($k)   { unset($this->map[$this->pack($k)]); }
  function count() { return count($this->map); }

  function pack(array $arr) {
    array_unshift($arr, 'N*');
    sort($arr);
    return call_user_func_array('pack', $arr);
  }

  function unpack($string) {
    return unpack('N*', $string);
  }

  function getIterator() {
    foreach ($this->map as $pk => $v)
      yield $this->unpack($pk) => $v;
  }

  function groupKeysByPrefix($k) {
    $gs = [];
    foreach ($this->map as $k => $_) {
      $gs[substr($k, 0, ($k-1)*4)][] = $this->unpack($k);
    }
    return $gs;
  }

  function raw() {
    foreach ($this->map as $k => $v)
      yield $k => $v;
  }


  static function merge($itemsetMaps) {
    $res = new ItemsetMap;
    foreach ($itemsetMaps as $itemsetMap)
      foreach ($itemsetMap->map as $k => $v)
        $res->map[$k] = $v;
    return $res;
  }

}


// *** general cruft ***

function keySet($arr) {
  return function ($k) use ($arr) {
    return isset($arr[$k]);
  };
}

function asMap($arr) {
  return function ($k) use ($arr) {
    return $arr[$k];
  };
}

function flatten($gen) {
  foreach ($gen as $xs)
    foreach ($xs as $x)
      yield $x;
}

function forall($gen, $f) {
  foreach ($gen as $v)
    if (!$f($v)) return false;
  return true;
}

function filter($gen, $f) {
  foreach ($gen as $k => $v)
    if ($f($v))
      yield $k => $v;
}


function combinations(array $xs, $k) {
  if ($k === 0)
    yield [];
  else
    foreach ($xs as $i => $x)
      foreach (combinations(array_slice($xs, $i + 1), $k - 1) as $tail)
        yield array_merge([$x], $tail);
}


// *** apriori related garbage


function transactions($file) {
  $f = fopen($file, 'r');
  if ($f !== false) {
    while (($line = fgets($f)) !== false) {
      $ts = array_map('intval', explode(" ", $line));
      sort($ts);
      yield $ts;
    }
    fclose($f);
  }
};

function hist($gen) {
  $buckets = [];
  foreach ($gen as $x) {
    if (!array_key_exists($x, $buckets))
      $buckets[$x] = 0; 

    $buckets[$x] ++;
  }
  return $buckets;
}

function minFreq($gen, $s) {
  return call_user_func(function () use ($gen, $s) {
    foreach ($gen as $k => $v)
      if ($v >= $s)
        yield $k => $v;
  });
}



// *** real deal ***

$file      = 'apriori.data2';
$indexFile = 'apriori.index2';

$minSupport = 6;
$minConfidence = 0.1;


// see: Wu, Kumar, Quinlan, Ghosh, Yang, Motoda, McLachlan, Ng, Liu, Yu, Zhou, 
// Steinbach, Hand, Steinberg (2007) Top 10 algorithms in data mining

function aprioriGen($F, $k) {
  foreach ($F->groupKeysByPrefix($k) as $gr) {
    foreach (combinations($gr, 2) as list($a, $b)) {
      $r = array_unique(array_merge($a, $b));
      if (forall(combinations($r, $k), keySet($F)))
        yield $r => 1;
    }
  }
}

$F = []; // [k => ItemsetMap[itemset => count]]
$hist = minFreq(hist(flatten(transactions($file))), $minSupport);
$F[1] = new ItemsetMap($hist);

for ($k = 1; count($F[$k]) > 0; $k++) {
  $F[$k+1] = new ItemsetMap;
  $C = new ItemsetMap(aprioriGen($F[$k], $k));

  foreach (transactions($file) as $t) {
    $Ct = filter(combinations($t, $k+1), keySet($C));
    foreach ($Ct as $c) $F[$k+1][$c] = $F[$k+1][$c] + 1;
  }
  $F[$k+1] = new ItemsetMap(minFreq($F[$k+1], $minSupport));
}


$index = [];
foreach (array_map('trim', file($indexFile)) as $line) {
  list($id, $name) = preg_split('~\s+~', $line);
  $index[$id] = $name;
}

$count = 0;
foreach ($F as $k => $ff)
foreach ($ff as $is => $c) {
  //echo "$k ".join(" ", $is)." $c\n";
  $count += 1;
}

$supp = ItemsetMap::merge($F);

foreach ($F as $k => $ff)
if ($k >= 2)
foreach ($ff as $is => $c) {
  foreach ($is as $k => $v) {
    $xs = $is;
    unset($xs[$k]);
    $conf = 1.0 * $supp[$is] / $supp[$xs];
    if ($conf > 0.5)
    echo "{ ".join(", ", array_map(asMap($index), $xs))." } => {$index[$v]}  (supp: {$supp[$is]}/{$supp[$xs]} conf: $conf)\n";
  }
}

echo $count, "\n";
