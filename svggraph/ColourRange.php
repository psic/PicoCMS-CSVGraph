<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * Abstract class implements common methods
 */
abstract class ColourRange implements \ArrayAccess {

  protected $count = 2;

  /**
   * Sets up the length of the range
   */
  public function setup($count)
  {
    $this->count = $count;
  }

  /**
   * always true, because it wraps around
   */
  public function offsetExists($offset)
  {
    return true;
  }

  public function offsetSet($offset, $value)
  {
    throw new \Exception('Unexpected offsetSet');
  }

  public function offsetUnset($offset)
  {
    throw new \Exception('Unexpected offsetUnset');
  }

  /**
   * Clamps a value to range $min-$max
   */
  protected static function clamp($val, $min, $max)
  {
    return min($max, max($min, $val));
  }
}

