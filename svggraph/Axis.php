<?php
/**
 * Copyright (C) 2011-2019 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

namespace Goat1000\SVGGraph;

/**
 * Class for calculating axis measurements
 */
class Axis {

  protected $length;
  protected $max_value;
  protected $min_value;
  protected $unit_size;
  protected $min_unit;
  protected $min_space;
  protected $fit;
  protected $zero;
  protected $units_before;
  protected $units_after;
  protected $decimal_digits;
  protected $uneven = false;
  protected $rounded_up = false;
  protected $direction = 1;
  protected $label_callback = false;
  protected $values = false;

  public function __construct($length, $max_val, $min_val, $min_unit, $min_space,
    $fit, $units_before, $units_after, $decimal_digits, $label_callback, $values)
  {
    if($max_val <= $min_val && $min_unit == 0)
      throw new \Exception('Zero length axis (min >= max)');
    $this->length = $length;
    $this->max_value = $max_val;
    $this->min_value = $min_val;
    $this->min_unit = $min_unit;
    $this->min_space = $min_space;
    $this->fit = $fit;
    $this->units_before = $units_before;
    $this->units_after = $units_after;
    $this->decimal_digits = $decimal_digits;
    $this->label_callback = $label_callback;
    $this->values = $values;
  }

  /**
   * Allow length adjustment
   */
  public function setLength($l)
  {
    $this->length = $l;
  }

  /**
   * Returns the axis length
   */
  public function getLength()
  {
    return $this->length;
  }

  /**
   * Returns TRUE if the number $n is 'nice'
   */
  private function nice($n)
  {
    if(is_integer($n) && ($n % 100 == 0 || $n % 10 == 0 || $n % 5 == 0))
      return true;

    if($this->min_unit) {
      $d = $n / $this->min_unit;
      if($d != floor($d))
        return false;
    }
    $nn = new Number($n);
    $nn->precision = 4;
    $s = (string)$nn;

    // regex is overkill for this
    if(strpos($s, '.') === false)
      return true;
    $good = ['0.1', '0.2', '0.3', '0.4', '0.5', '1.5', '2.5'];
    if(in_array($s, $good, true))
      return true;
    return false;
  }

  /**
   * Determine the axis divisions
   */
  private function findDivision($length, $min, &$count, &$neg_count, &$magnitude)
  {
    if($length / $count >= $min)
      return;

    $c = $count - 1;
    $inc = 0;
    $max_inc = $this->fit ? 0 : floor($count / 5);
    $candidates = [];
    while($c > 1) {
      $m = ($count + $inc) / $c;
      $l = $length / $c;
      $nc = $neg_count;

      $accept = false;
      if($this->nice($m) && $l >= $min) {
        $accept = true;

        // negative values mean an extra check
        if($nc) {
          $accept = false;
          $nm = $nc / $m;

          if(floor($nm) === $nm) {
            $nc = $nm;
            $accept = true;
          } else {

            // negative section doesn't divide cleanly, try adding from $inc
            if($inc) {
              for($i = 1; $i <= $inc; ++$i) {
                $cc = $nc + $i;
                $nm = ($nc + $i) / $m;

                if(floor($nm) === $nm) {
                  $nc = $nm;
                  $accept = true;
                  break;
                }
              }
            }
          }
        }
      }

      if($accept) {
        // this division is acceptable, store it
        $candidates[] = [
          'cost' => ($inc * 1.5) + $m ,
          'magnitude' => $magnitude * $m,
          'c' => $c,
          'neg_count' => $nc,
        ];
      }

      if($inc < $max_inc) {
        // increase the number of base divisions
        ++$inc;
        continue;
      }

      --$c;
      $inc = 0;
    }

    if(empty($candidates))
      return;

    usort($candidates, function($a, $b) { return $a['cost'] - $b['cost']; });
    $winner = $candidates[0];
    $magnitude = $winner['magnitude'];
    $count = $winner['c'];
    $neg_count = $winner['neg_count'];
  }

  /**
   * Sets the bar style (which means an extra unit)
   */
  public function bar()
  {
    if(!$this->rounded_up) {
      $this->max_value += $this->min_unit;
      $this->rounded_up = true;
    }
  }

  /**
   * Sets the direction of axis points
   */
  public function reverse()
  {
    $this->direction = -1;
  }

  /**
   * Returns the grid spacing
   */
  protected function grid()
  {
    $min_space = $this->min_space;
    $this->uneven = false;
    $negative = $this->min_value < 0;
    $min_sub = max($min_space, $this->length / 200);

    if($this->min_value == $this->max_value)
      $this->max_value += $this->min_unit;
    $scale = $this->max_value - $this->min_value;

    $abs_min = abs($this->min_value);
    $magnitude = max(pow(10, floor(log10($scale))), $this->min_unit);
    if($this->min_value > 0 || $this->fit) {
      $count = ceil($scale / $magnitude);
    } else {
      $count = ceil($this->max_value / $magnitude) -
        floor($this->min_value / $magnitude);
    }

    if($count <= 5 && $magnitude > $this->min_unit) {
      $magnitude *= 0.1;
      $count = ceil($this->max_value / $magnitude) -
        floor($this->min_value / $magnitude);
    }

    $neg_count = $this->min_value < 0 ? ceil($abs_min / $magnitude) : 0;
    $this->findDivision($this->length, $min_sub, $count, $neg_count,
      $magnitude);
    $grid = $this->length / $count;

    // guard this loop in case the numbers are too awkward to fit
    $guard = 10;
    while($grid < $min_space && --$guard) {
      $this->findDivision($this->length, $min_sub, $count, $neg_count,
        $magnitude);
      $grid = $this->length / $count;
    }
    if($guard == 0) {
      // could not find a division
      while($grid < $min_space && $count > 1) {
        $count *= 0.5;
        $neg_count *= 0.5;
        $magnitude *= 2;
        $grid = $this->length / $count;
        $this->uneven = true;
      }
    }

    $this->unit_size = $this->length / ($magnitude * $count);
    $this->zero = $negative ? $neg_count * $grid :
      -$this->min_value * $grid / $magnitude;

    return $grid;
  }

  /**
   * Returns the size of a unit in grid space
   */
  public function unit()
  {
    if(!isset($this->unit_size))
      $this->grid();

    return $this->unit_size;
  }

  /**
   * Returns the distance along the axis where 0 should be
   */
  public function zero()
  {
    if(!isset($this->zero))
      $this->grid();

    return $this->zero;
  }

  /**
   * Returns TRUE if the grid spacing does not fill the grid
   */
  public function uneven()
  {
    return $this->uneven;
  }

  /**
   * Returns the position of a value on the axis
   */
  public function position($index, $item = null)
  {
    $value = $index;
    if($item !== null && !$this->values->associativeKeys())
      $value = $item->key;
    if(!is_numeric($value))
      return null;
    return $this->zero() + ($value * $this->unit());
  }

  /**
   * Returns the position of an associative key, if possible
   */
  public function positionByKey($key)
  {
    if($this->values && $this->values->associativeKeys()) {

      // only need to look through dataset 0 because multi-dataset graphs
      // convert to structured
      $index = 0;
      foreach($this->values[0] as $item) {
        if($item->key == $key) {
          return $this->zero() + ($index * $this->unit());
        }
        ++$index;
      }
    }
    return null;
  }

  /**
   * Returns the position of the origin
   */
  public function origin()
  {
    // for a linear axis, it should be the zero point
    return $this->zero();
  }

  /**
   * Returns the value at a position on the axis
   */
  public function value($position)
  {
    return ($position - $this->zero()) / $this->unit();
  }

  /**
   * Return the before units text
   */
  public function beforeUnits()
  {
    return $this->units_before;
  }

  /**
   * Return the after units text
   */
  public function afterUnits()
  {
    return $this->units_after;
  }

  /**
   * Returns the text for a grid point
   */
  protected function getText($value)
  {
    $text = $value;

    // try structured data first
    if($this->values && $this->values->getData($value, 'axis_text', $text))
      return (string)$text;

    // use the key if it is not the same as the value
    $key = $this->values ? $this->values->getKey($value) : $value;

    // if there is a callback, use it
    if(is_callable($this->label_callback)) {
      // assoc keys should have integer indices
      if($this->values && $this->values->associativeKeys())
        $value = (int)round($value);
      return (string)call_user_func($this->label_callback, $value, $key);
    }

    if($key !== $value)
      return (string)$key;

    $n = new Number($value, $this->units_after, $this->units_before);
    return $n->format($this->decimal_digits);
  }

  /**
   * Returns the grid points as an array of GridPoints
   *  if $start is NULL, just set up the grid spacing without returning points
   */
  public function getGridPoints($start)
  {
    $this->grid_spacing = $spacing = $this->grid();
    $dlength = $this->length + $spacing * 0.5;
    if($dlength / $spacing > 10000) {
      $pcount = $dlength / $spacing;
      throw new \Exception('Too many grid points (' . $this->min_value . '->' .
        $this->max_value . ' = ' . $pcount . ' points @ ' . $spacing . 'px separation)');
    }
    if($start === null)
      return;

    $c = $pos = 0;
    $points = [];
    while($pos < $dlength) {
      $value = ($pos - $this->zero) / $this->unit_size;
      $text = $this->getText($value);
      $position = $start + ($this->direction * $pos);
      $points[] = new GridPoint($position, $text, $value);
      $pos = ++$c * $spacing;
    }
    // uneven means the divisions don't fit exactly, so add the last one in
    if($this->uneven) {
      $pos = $this->length - $this->zero;
      $value = $pos / $this->unit_size;
      $text = $this->getText($value);
      $position = $start + ($this->direction * $this->length);
      $points[] = new GridPoint($position, $text, $value);
    }

    if($this->direction < 0) {
      usort($points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($points, function($a, $b) { return $a->position - $b->position; });
    }

    $this->grid_spacing = $spacing;
    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function getGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    if(!$this->grid_spacing)
      throw new \Exception('grid_spacing not set');

    $subdivs = [];
    $spacing = $this->findSubdiv($this->grid_spacing, $min_space, $min_unit,
      $fixed);
    if(!$spacing)
      return $subdivs;

    $c = $pos1 = $pos2 = 0;
    $pos1 = $c * $this->grid_spacing;
    while($pos1 + $spacing < $this->length) {
      $d = 1;
      $pos2 = $d * $spacing;
      while($pos2 < $this->grid_spacing) {
        $subdivs[] = new GridPoint($start + (($pos1 + $pos2) * $this->direction), '', 0);
        ++$d;
        $pos2 = $d * $spacing;
      }
      ++$c;
      $pos1 = $c * $this->grid_spacing;
    }
    return $subdivs;
  }

  /**
   * Find the subdivision size
   */
  private function findSubdiv($grid_div, $min, $min_unit, $fixed)
  {
    if(is_numeric($fixed))
      return $this->unit_size * $fixed;

    $D = $grid_div / $this->unit_size;  // D = actual division size
    $min = max($min, $min_unit * $this->unit_size); // use the larger minimum value
    $max_divisions = (int)floor($grid_div / $min);

    // can we subdivide at all?
    if($max_divisions <= 1)
      return null;

    // convert $D to an integer in the 100's range
    $D1 = (int)round(100 * (pow(10,-floor(log10($D)))) * $D);
    for($divisions = $max_divisions; $divisions > 1; --$divisions) {
      // if $D1 / $divisions is not an integer, $divisions is no good
      $dq = $D1 / $divisions;
      if($dq - floor($dq) == 0)
        return $grid_div / $divisions;
    }
    return null;
  }
}

