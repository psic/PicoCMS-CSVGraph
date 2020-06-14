<?php
/**
 * Copyright (C) 2009-2019 Graham Breach
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

class BarGraph extends GridGraph {

  use BarGraphTrait;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // backwards compatibility
    if(isset($settings['show_bar_labels']) && !isset($settings['show_data_labels']))
      $settings['show_data_labels'] = $settings['show_bar_labels'];

    $fs = ['label_centre' => true];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }
}

