<?php
/**
 * Copyright (C) 2014-2019 Graham Breach
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

class Colours implements \Countable {

  private $colours = [];
  private $dataset_count = 0;
  private $fallback = false;

  /**
   * Constructor sets up fallback colour array in case per-dataset
   * functions are not used
   */
  public function __construct($colours = null)
  {
    if(is_array($colours))
      $this->fallback = $colours;
    else
      $this->fallback = [
        '#11c', '#c11', '#cc1', '#1c1', '#c81',
        '#116', '#611', '#661', '#161', '#631'
      ];
  }

  /**
   * Setup based on graph requirements
   */
  public function setup($count, $datasets = null)
  {
    if($this->fallback !== false) {
      if($datasets !== null) {
        foreach($this->fallback as $colour) {
          // in fallback, each dataset gets one colour
          $this->colours[] = new ColourArray([$colour]);
        }
      } else {
        $this->colours[] = new ColourArray($this->fallback);
      }
      $this->dataset_count = count($this->colours);
    }

    foreach($this->colours as $clist)
      $clist->setup($count);
  }

  /**
   * Returns the colour for an index and dataset
   */
  public function getColour($index, $dataset = null)
  {
    // default is for a colour per dataset
    if($dataset === null)
      $dataset = 0;

    // see if specific dataset exists
    if(array_key_exists($dataset, $this->colours))
      return $this->colours[$dataset][$index];

    // try mod
    $dataset = $dataset % $this->dataset_count;
    if(array_key_exists($dataset, $this->colours))
      return $this->colours[$dataset][$index];

    // just use first dataset
    reset($this->colours);
    $clist = current($this->colours);
    return $clist[$index];
  }

  /**
   * Implement Countable to make it non-countable
   */
  public function count()
  {
    throw new \Exception('Cannot count Colours class');
  }

  /**
   * Set an entry in the colours array
   */
  private function setDataset($dataset, $colours)
  {
    if($this->fallback) {
      // use fallback for dataset 0 if not already set
      if($dataset != 0)
        $this->colours[0] = new ColourArray($this->fallback);
      $this->fallback = false;
    }

    $this->colours[$dataset] = $colours;
    $this->dataset_count = count($this->colours);
  }

  /**
   * Assign a colour array for a dataset
   */
  public function set($dataset, $colours)
  {
    if($colours === null) {
      if(array_key_exists($dataset, $this->colours))
        unset($this->colours[$dataset]);
      return;
    }
    $this->setDataset($dataset, new ColourArray($colours));
  }

  /**
   * Set up RGB colour range
   */
  public function rangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
  {
    $this->setDataset($dataset,
      new ColourRangeRGB($r1, $g1, $b1, $r2, $g2, $b2));
  }

  /**
   * HSL colour range, with option to go the long way
   */
  public function rangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2,
    $reverse = false)
  {
    $rng = new ColourRangeHSL($h1, $s1, $l1, $h2, $s2, $l2);
    if($reverse)
      $rng->reverse();
    $this->setDataset($dataset, $rng);
  }

  /**
   * HSL colour range from RGB values, with option to go the long way
   */
  public function rangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
    $reverse = false)
  {
    $rng = ColourRangeHSL::fromRGB($r1, $g1, $b1, $r2, $g2, $b2);
    if($reverse)
      $rng->reverse();
    $this->setDataset($dataset, $rng);
  }

  /**
   * RGB colour range from two RGB hex codes
   */
  public function rangeHexRGB($dataset, $c1, $c2)
  {
    list($r1, $g1, $b1) = $this->hexRGB($c1);
    list($r2, $g2, $b2) = $this->hexRGB($c2);
    $this->rangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
  }

  /**
   * HSL colour range from RGB hex codes
   */
  public function rangeHexHSL($dataset, $c1, $c2, $reverse = false)
  {
    list($r1, $g1, $b1) = $this->hexRGB($c1);
    list($r2, $g2, $b2) = $this->hexRGB($c2);
    $this->rangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse);
  }

  /**
   * Convert a colour code to RGB array
   */
  public static function hexRGB($c)
  {
    $r = $g = $b = 0;
    if(strlen($c) == 7) {
      sscanf($c, '#%2x%2x%2x', $r, $g, $b);
    } elseif(strlen($c) == 4) {
      sscanf($c, '#%1x%1x%1x', $r, $g, $b);
      $r += 16 * $r;
      $g += 16 * $g;
      $b += 16 * $b;
    }
    return [$r, $g, $b];
  }
}

