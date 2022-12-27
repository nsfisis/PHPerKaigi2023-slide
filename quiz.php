<?php

declare(strict_types=1);

####################################
# Q1

$x = 1;
$y =& $x;
$y = 2;
echo "x = $x", PHP_EOL;
// => 2
echo "y = $y", PHP_EOL;
// => 2

####################################
# Q2

$x = 1;
$y =& $x;
$z = $y;
$z = 2;
echo "x = $x", PHP_EOL;
// => 1
echo "y = $y", PHP_EOL;
// => 1
echo "z = $z", PHP_EOL;
// => 2

####################################
# Q3

$xs = [1, 2];
$x =& $xs[0];
$x = 42;
echo "x = $x", PHP_EOL;
// => 42
echo "xs = [$xs[0], $xs[1]]", PHP_EOL;
// => [42, 2]

####################################
# Q4

$xs = [1, 2];
$x =& $xs[0];
$ys = $xs;
$x = 42;
$ys[1] = 3;
echo "x = $x", PHP_EOL;
// => 42
echo "xs = [$xs[0], $xs[1]]", PHP_EOL;
// => [42, 2]
echo "ys = [$ys[0], $ys[1]]", PHP_EOL;
// => [42, 3]

####################################
# Q5 (割愛)

$g = 1;
function f(&$x) {
  $x =& $GLOBALS['g'];
}
$y = 0;
f($y);
$y = 42;
echo "y = $y", PHP_EOL;
// => 42
echo "g = $g", PHP_EOL;
// => 1

####################################
# Q6 (割愛)

class C {
  public int $x = 1;
}
$c = new C();
$y =& $c->x;
$y = 'PHPerKaigi';
// => Fatal error: TypeError
//    Cannot assign string to reference held by property C::$x of type int
