<?php
/**
 * Copyright (C) 2009-2020 Graham Breach
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

abstract class GridGraph extends Graph {

  protected $x_axes;
  protected $y_axes;
  protected $main_x_axis = 0;
  protected $main_y_axis = 0;
  protected $y_axis_positions = [];
  protected $dataset_axes = null;

  protected $g_width = null;
  protected $g_height = null;
  protected $label_adjust_done = false;
  protected $axes_calc_done = false;
  protected $grid_calc_done = false;
  protected $guidelines;
  protected $min_guide = ['x' => null, 'y' => null];
  protected $max_guide = ['x' => null, 'y' => null];

  protected $grid_limit;
  private $grid_clip_id;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      // Set to true for block-based labelling
      'label_centre' => false,
      // Set to true for graphs that don't support multiple axes (e.g. stacked)
      'single_axis' => false,
    ];
    $fs = array_merge($fs, $fixed_settings);

    // deprecated options need converting
    if(isset($settings['show_label_h']) &&
      !isset($settings['show_axis_text_h']))
      $settings['show_axis_text_h'] = $settings['show_label_h'];
    if(isset($settings['show_label_v']) &&
      !isset($settings['show_axis_text_v']))
      $settings['show_axis_text_v'] = $settings['show_label_v'];

    // convert _x and _y labels to _h and _v
    if(!empty($settings['label_y']) && empty($settings['label_v']))
      $settings['label_v'] = $settings['label_y'];
    if(!empty($settings['label_x']) && empty($settings['label_h']))
      $settings['label_h'] = $settings['label_x'];

    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Modifies the graph padding to allow room for labels
   */
  protected function labelAdjustment()
  {
    $grid_l = $grid_t = $grid_r = $grid_b = null;

    $grid_set = $this->getOption('grid_left', 'grid_right', 'grid_top',
      'grid_bottom');
    if($grid_set) {
      if(!empty($this->grid_left))
        $grid_l = $this->pad_left = abs($this->grid_left);
      if(!empty($this->grid_top))
        $grid_t = $this->pad_top = abs($this->grid_top);

      if(!empty($this->grid_bottom))
        $grid_b = $this->pad_bottom = $this->grid_bottom < 0 ?
          abs($this->grid_bottom) : $this->height - $this->grid_bottom;
      if(!empty($this->grid_right))
        $grid_r = $this->pad_right = $this->grid_right < 0 ?
          abs($this->grid_right) : $this->width - $this->grid_right;
    }

    if($this->axis_right && !empty($this->label_v) && $this->yAxisCount() <= 1) {
      $label = is_array($this->label_v) ? $this->label_v[0] : $this->label_v;
      $this->setOption('label_v', [0 => '', 1 => $label]);
    }

    $pad_l = $pad_r = $pad_b = $pad_t = 0;
    $space_x = $this->width - $this->pad_left - $this->pad_right;
    $space_y = $this->height - $this->pad_top - $this->pad_bottom;
    $ends = $this->getAxisEnds();
    $extra_r = $extra_t = 0;

    for($i = 0; $i < 10; ++$i) {
      // find the text bounding box and add overlap to padding
      // repeat with the new measurements in case overlap increases
      $x_len = $space_x - $pad_r - $pad_l;
      $y_len = $space_y - $pad_t - $pad_b;

      // 3D graphs will use this to reduce axis length
      list($extra_r, $extra_t) = $this->adjustAxes($x_len, $y_len);

      list($x_axes, $y_axes) = $this->getAxes($ends, $x_len, $y_len);
      $bbox = $this->findAxisBBox($x_len, $y_len, $x_axes, $y_axes);
      $pr = $pl = $pb = $pt = 0;

      if($bbox['max_x'] > $x_len)
        $pr = ceil($bbox['max_x'] - $x_len);
      if($bbox['min_x'] < 0)
        $pl = ceil(abs($bbox['min_x']));
      if($bbox['min_y'] < 0)
        $pt = ceil(abs($bbox['min_y']));
      if($bbox['max_y'] > $y_len)
        $pb = ceil($bbox['max_y'] - $y_len);

      if($pr == $pad_r && $pl == $pad_l && $pt == $pad_t && $pb == $pad_b)
        break;
      $pad_r = $pr;
      $pad_l = $pl;
      $pad_t = $pt;
      $pad_b = $pb;
    }

    $pad_r += $extra_r;
    $pad_t += $extra_t;

    // apply the extra padding
    if($grid_l === null)
      $this->pad_left += $pad_l;
    if($grid_b === null)
      $this->pad_bottom += $pad_b;
    if($grid_r === null)
      $this->pad_right += $pad_r;
    if($grid_t === null)
      $this->pad_top += $pad_t;

    if($grid_r !== null || $grid_l !== null) {
      // fixed grid position means recalculating axis positions
      $offset = 0;
      foreach($this->y_axis_positions as $axis_no => $pos) {
        if($axis_no == 0)
          continue;
        if($offset) {
          $newpos = $pos + $offset;
        } else {
          $newpos = $this->width - $this->pad_left - $this->pad_right;
          $offset = $newpos - $pos;
        }
        $this->y_axis_positions[$axis_no] = $newpos;
      }
    }

    // see if the axes fit
    foreach($this->y_axis_positions as $axis_no => $pos) {
      if($axis_no > 0 && $pos <= 0)
        throw new \Exception('Not enough space for ' . $this->yAxisCount() . ' axes');
    }
    $this->label_adjust_done = true;
  }

  /**
   * Subclasses can override this to modify axis lengths
   * Return amount of padding added [r,t]
   */
  protected function adjustAxes(&$x_len, &$y_len)
  {
    return [0, 0];
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function findAxisBBox($length_x, $length_y, $x_axes, $y_axes)
  {
    // initialise maxima and minima
    $min_x = [$this->width];
    $min_y = [$this->height];
    $max_x = [0];
    $max_y = [0];

    $display_axis = $this->getDisplayAxis($x_axes[0], 0, 'h', 'x');
    $m = $display_axis->measure();
    $min_x[] = $m['x'];
    $min_y[] = $m['y'] + $length_y;
    $max_x[] = $m['x'] + $m['width'];
    $max_y[] = $m['y'] + $m['height'] + $length_y;

    $axis_no = -1;
    $right_pos = $length_x;
    foreach($y_axes as $y_axis) {
      ++$axis_no;
      if($y_axis === null)
        continue;
      $ybb = $this->yAxisBBox($y_axis, $length_y, $axis_no);

      if($axis_no > 0) {
        // for offset axes, the inside overlap must be added on too
        $outer = $ybb['max_x'];
        $inner = $axis_no > 1 ? abs($ybb['min_x']) : 0;

        $this->y_axis_positions[$axis_no] = $right_pos + $inner;
        $ybb['max_x'] += $right_pos + $inner;
        $ybb['min_x'] += $right_pos + $inner;
        $right_pos += $inner + $outer + $this->axis_space;
      } else {
        $this->y_axis_positions[$axis_no] = 0;
      }

      $max_x[] = $ybb['max_x'];
      $min_x[] = $ybb['min_x'];
      $max_y[] = $ybb['max_y'];
      $min_y[] = $ybb['min_y'];
    }
    return [
      'min_x' => min($min_x), 'min_y' => min($min_y),
      'max_x' => max($max_x), 'max_y' => max($max_y)
    ];
  }

  /**
   * Returns bounding box for a Y-axis
   */
  protected function yAxisBBox($axis, $length, $axis_no)
  {
    $display_axis = $this->getDisplayAxis($axis, $axis_no, 'v', 'y');
    $measurement = $display_axis->measure();

    $results = [
      'min_x' => $measurement['x'],
      'min_y' => $measurement['y'] + $length,
      'max_x' => $measurement['x'] + $measurement['width'],
      'max_y' => $measurement['y'] + $measurement['height'] + $length,
    ];
    return $results;
  }

  /**
   * Sets up grid width and height to fill padded area
   */
  protected function setGridDimensions()
  {
    $this->g_height = $this->height - $this->pad_top - $this->pad_bottom;
    $this->g_width = $this->width - $this->pad_left - $this->pad_right;
  }

  /**
   * Returns the list of datasets and their axis numbers
   */
  public function getDatasetAxes()
  {
    if($this->dataset_axes !== null)
      return $this->dataset_axes;

    $dataset_axis = $this->getOption('dataset_axis');
    $default_axis = $this->getOption('axis_right') ? 1 : 0;
    $single_axis = $this->getOption('single_axis');
    if(empty($this->multi_graph)) {
      $enabled_datasets = [0];
      $v =& $this->values;
    } else {
      $enabled_datasets = $this->multi_graph->getEnabledDatasets();
      $v =& $this->multi_graph->getValues();
    }

    $d_axes = [];
    foreach($enabled_datasets as $d) {
      $axis = $default_axis;

      // only use the chosen dataset axis when allowed and not empty
      if(!$single_axis && isset($dataset_axis[$d]) && $v->itemsCount($d) > 0)
        $axis = $dataset_axis[$d];
      $d_axes[$d] = $axis;
    }

    // check that the axes are used in order
    $used = [];
    foreach($d_axes as $a)
      $used[$a] = 1;
    $max = max($d_axes);
    $unused = [];
    for($a = $default_axis; $a <= $max; ++$a)
      if(!isset($used[$a]))
        $unused[] = $a;

    if(count($unused))
      throw new \Exception('Unused axis: ' . implode(', ', $unused));

    $this->dataset_axes = $d_axes;
    return $this->dataset_axes;
  }

  /**
   * Returns the number of Y-axes
   */
  protected function yAxisCount()
  {
    $dataset_axes = $this->getDatasetAxes();
    if(count($dataset_axes) <= 1)
      return 1;

    return count(array_unique($dataset_axes));
  }

  /**
   * Returns the number of X-axes
   */
  protected function xAxisCount()
  {
    return 1;
  }

  /**
   * Returns an x or y axis, or NULL if it does not exist
   */
  public function getAxis($axis, $num)
  {
    if($num === null)
      $num = ($axis == 'y' ? $this->main_y_axis : $this->main_x_axis);
    $axis_var = $axis == 'y' ? 'y_axes' : 'x_axes';
    if(isset($this->{$axis_var}) && isset($this->{$axis_var}[$num]))
      return $this->{$axis_var}[$num];
    return null;
  }

  /**
   * Returns the Y-axis for a dataset
   */
  protected function datasetYAxis($dataset)
  {
    $dataset_axes = $this->getDatasetAxes();
    if(isset($dataset_axes[$dataset]))
      return $dataset_axes[$dataset];
    return $this->axis_right ? 1 : 0;
  }

  /**
   * Returns the minimum value for an axis
   */
  protected function getAxisMinValue($axis)
  {
    if($this->yAxisCount() <= 1)
      return $this->getMinValue();

    $min = [];
    $dataset_axes = $this->getDatasetAxes();
    foreach($dataset_axes as $dataset => $d_axis) {
      if($d_axis == $axis) {
        $min_val = $this->values->getMinValue($dataset);
        if($min_val !== null)
          $min[] = $min_val;
      }
    }
    return empty($min) ? null : min($min);
  }

  /**
   * Returns the maximum value for an axis
   */
  protected function getAxisMaxValue($axis)
  {
    if($this->yAxisCount() <= 1)
      return $this->getMaxValue();

    $max = [];
    $dataset_axes = $this->getDatasetAxes();
    foreach($dataset_axes as $dataset => $d_axis) {
      if($d_axis == $axis) {
        $max_val = $this->values->getMaxValue($dataset);
        if($max_val !== null)
          $max[] = $max_val;
      }
    }
    return empty($max) ? null : max($max);
  }

  /**
   * Returns fixed min and max option for an axis
   */
  protected function getFixedAxisOptions($axis, $index)
  {
    $a = $axis == 'y' ? 'v' : 'h';
    $min = $this->getOption(['axis_min_' . $a, $index]);
    $max = $this->getOption(['axis_max_' . $a, $index]);
    return [$min, $max];
  }

  /**
   * Returns an array containing the value and key axis min and max
   */
  protected function getAxisEnds()
  {
    // check guides
    if($this->guidelines === null)
      $this->calcGuidelines();

    $v_max = $v_min = $k_max = $k_min = [];
    $g_min_x = $g_min_y = $g_max_x = $g_max_y = null;

    if($this->guidelines !== null) {
      list($g_min_x, $g_min_y, $g_max_x, $g_max_y) = $this->guidelines->getMinMax();
    }
    $y_axis_count = $this->yAxisCount();
    $x_axis_count = $this->xAxisCount();

    for($i = 0; $i < $y_axis_count; ++$i) {
      list($fixed_min, $fixed_max) = $this->getFixedAxisOptions('y', $i);

      // validate
      if(is_numeric($fixed_min) && is_numeric($fixed_max) &&
        $fixed_max < $fixed_min)
        throw new \Exception('Invalid Y axis options: min > max (' .
          $fixed_min . ' > ' . $fixed_max . ')');

      if(is_numeric($fixed_min)) {
        $v_min[] = $fixed_min;
      } else {
        $minv_list = [$this->getAxisMinValue($i)];
        if($g_min_y !== null)
          $minv_list[] = (float)$g_min_y;

        // if not a log axis, start at 0
        if(!$this->getOption(['log_axis_y', $i]))
          $minv_list[] = 0;
        $v_min[] = min($minv_list);
      }

      if(is_numeric($fixed_max)) {
        $v_max[] = $fixed_max;
      } else {
        $maxv_list = [$this->getAxisMaxValue($i)];
        if($g_max_y !== null)
          $maxv_list[] = (float)$g_max_y;

        // if not a log axis, start at 0
        if(!$this->getOption(['log_axis_y', $i]))
          $maxv_list[] = 0;
        $v_max[] = max($maxv_list);
      }
      if($v_max[$i] < $v_min[$i])
        throw new \Exception('Invalid Y axis: min > max (' .
          $v_min[$i] . ' > ' . $v_max[$i] . ')');
    }

    for($i = 0; $i < $x_axis_count; ++$i) {
      list($fixed_min, $fixed_max) = $this->getFixedAxisOptions('x', $i);

      if($this->datetime_keys) {
        // 0 is 1970-01-01, not a useful minimum
        if(empty($fixed_max)) {
          // guidelines support datetime values too
          if($g_max_x !== null)
            $k_max[] = max($this->getMaxKey(), $g_max_x);
          else
            $k_max[] = $this->getMaxKey();
        } else {
          $d = Graph::dateConvert($fixed_max);
          // subtract a sec
          if($d !== null)
            $k_max[] = $d - 1;
          else
            throw new \Exception('Could not convert [' . $fixed_max .
              '] to datetime');
        }
        if(empty($fixed_min)) {
          if($g_min_x !== null)
            $k_min[] = min($this->getMinKey(), $g_min_x);
          else
            $k_min[] = $this->getMinKey();
        } else {
          $d = Graph::dateConvert($fixed_min);
          if($d !== null)
            $k_min[] = $d;
          else
            throw new \Exception('Could not convert [' . $fixed_min .
              '] to datetime');
        }
      } else {
        // validate
        if(is_numeric($fixed_min) && is_numeric($fixed_max) &&
          $fixed_max < $fixed_min)
          throw new \Exception('Invalid X axis options: min > max (' .
            $fixed_min . ' > ' . $fixed_max . ')');

        if(is_numeric($fixed_max))
          $k_max[] = $fixed_max;
        else
          $k_max[] = max(0, $this->getMaxKey(), (float)$g_max_x);
        if(is_numeric($fixed_min))
          $k_min[] = $fixed_min;
        else
          $k_min[] = min(0, $this->getMinKey(), (float)$g_min_x);
      }
      if($k_max[$i] < $k_min[$i])
        throw new \Exception('Invalid X axis: min > max (' . $k_min[$i] .
          ' > ' . $k_max[$i] . ')');
    }
    return compact('v_max', 'v_min', 'k_max', 'k_min');
  }

  /**
   * Returns the X and Y axis class instances as a list
   */
  protected function getAxes($ends, &$x_len, &$y_len)
  {
    // disable units for associative keys
    if($this->values->associativeKeys())
      $this->units_x = $this->units_before_x = null;

    $x_axes = [];
    $x_axis_count = $this->xAxisCount();
    for($i = 0; $i < $x_axis_count; ++$i) {

      $x_min_space = $this->getOption(['minimum_grid_spacing_h', $i],
        'minimum_grid_spacing');
      $grid_division = $this->getOption(['grid_division_h', $i]);
      if(is_numeric($grid_division)) {
        if($grid_division <= 0)
          throw new \Exception('Invalid grid division');
        // if fixed grid spacing is specified, make the min spacing 1 pixel
        $this->minimum_grid_spacing_h = $x_min_space = 1;
      }

      $max_h = $ends['k_max'][$i];
      $min_h = $ends['k_min'][$i];
      $x_min_unit = 1;
      $x_fit = true;
      $x_units_after = (string)$this->getOption(['units_x', $i]);
      $x_units_before = (string)$this->getOption(['units_before_x', $i]);
      $x_decimal_digits = $this->getOption(['decimal_digits_x', $i],
        'decimal_digits');
      $x_text_callback = $this->getOption(['axis_text_callback_x', $i],
        'axis_text_callback');
      $x_values = $this->multi_graph ? $this->multi_graph : $this->values;

      if(!is_numeric($max_h) || !is_numeric($min_h))
        throw new \Exception('Non-numeric min/max');

      if($this->datetime_keys) {
        $x_axis = new AxisDateTime($x_len, $max_h, $min_h, $x_min_space,
          $grid_division, $this->settings);
      } elseif(!is_numeric($grid_division)) {
        $x_axis = new Axis($x_len, $max_h, $min_h, $x_min_unit, $x_min_space,
          $x_fit, $x_units_before, $x_units_after, $x_decimal_digits,
          $x_text_callback, $x_values);
      } else {
        $x_axis = new AxisFixed($x_len, $max_h, $min_h, $grid_division,
          $x_units_before, $x_units_after, $x_decimal_digits, $x_text_callback,
          $x_values);
      }
      if($this->getOption('label_centre'))
        $x_axis->bar();
      $x_axes[] = $x_axis;
    }

    $y_axes = [];
    $y_axis_count = $this->yAxisCount();
    for($i = 0; $i < $y_axis_count; ++$i) {

      $y_min_space = $this->getOption(['minimum_grid_spacing_v', $i],
        'minimum_grid_spacing');
      // make sure minimum_grid_spacing option is an array
      if(!is_array($this->getOption('minimum_grid_spacing_v')))
        $this->setOption('minimum_grid_spacing_v', []);

      $grid_division = $this->getOption(['grid_division_v', $i]);
      if(is_numeric($grid_division)) {
        if($grid_division <= 0)
          throw new \Exception('Invalid grid division');
        // if fixed grid spacing is specified, make the min spacing 1 pixel
        $this->setOption('minimum_grid_spacing_v', 1, $i);
        $y_min_space = 1;
      } elseif(!isset($this->minimum_grid_spacing_v[$i])) {
        $this->setOption('minimum_grid_spacing_v', $y_min_space, $i);
      }

      $max_v = $ends['v_max'][$i];
      $min_v = $ends['v_min'][$i];
      $y_min_unit = $this->getOption(['minimum_units_y', $i]);
      $y_fit = false;
      $y_values = false;

      $y_text_callback = $this->getOption(['axis_text_callback_y', $i],
        'axis_text_callback');
      $y_decimal_digits = $this->getOption(['decimal_digits_y', $i],
        'decimal_digits');
      $y_units_after = (string)$this->getOption(['units_y', $i]);
      $y_units_before = (string)$this->getOption(['units_before_y', $i]);

      if(!is_numeric($max_v) || !is_numeric($min_v))
        throw new \Exception('Non-numeric min/max');

      if($min_v == $max_v) {
        if($y_min_unit > 0) {
          $inc = $y_min_unit;
        } else {
          $fallback = $this->getOption('axis_fallback_max');
          $inc = $fallback > 0 ? $fallback : 1;
        }
        $max_v += $inc;
      }

      if($this->getOption(['log_axis_y', $i])) {
        $y_axis = new AxisLog($y_len, $max_v, $min_v, $y_min_unit, $y_min_space,
          $y_fit, $y_units_before, $y_units_after, $y_decimal_digits,
          $this->getOption(['log_axis_y_base', $i]),
          $grid_division, $y_text_callback);
      } elseif(!is_numeric($grid_division)) {
        $y_axis = new Axis($y_len, $max_v, $min_v, $y_min_unit, $y_min_space,
          $y_fit, $y_units_before, $y_units_after, $y_decimal_digits,
          $y_text_callback, $y_values);
      } else {
        $y_axis = new AxisFixed($y_len, $max_v, $min_v, $grid_division,
          $y_units_before, $y_units_after, $y_decimal_digits, $y_text_callback,
          $y_values);
      }
      $y_axis->reverse(); // because axis starts at bottom

      $y_axes[] = $y_axis;
    }

    // set the main axis correctly
    if($this->axis_right && count($y_axes) == 1) {
      $this->main_y_axis = 1;
      array_unshift($y_axes, null);
    }
    return [$x_axes, $y_axes];
  }

  /**
   * Calculates the effect of axes, applying to padding
   */
  protected function calcAxes()
  {
    if($this->axes_calc_done)
      return;

    // can't have multiple invisible axes
    if(!$this->show_axes)
      $this->setOption('dataset_axis', null);

    $ends = $this->getAxisEnds();
    if(!$this->label_adjust_done)
      $this->labelAdjustment();
    if($this->g_height === null || $this->g_width === null)
      $this->setGridDimensions();

    list($x_axes, $y_axes) = $this->getAxes($ends, $this->g_width,
      $this->g_height);

    $this->x_axes = $x_axes;
    $this->y_axes = $y_axes;
    $this->axes_calc_done = true;
  }

  /**
   * Calculates the position of grid lines
   */
  protected function calcGrid()
  {
    if($this->grid_calc_done)
      return;

    $y_axis = $this->y_axes[$this->main_y_axis];
    $x_axis = $this->x_axes[$this->main_x_axis];
    $y_axis->getGridPoints(null);
    $x_axis->getGridPoints(null);

    $this->grid_limit = 0.01 + $this->g_width;
    if($this->getOption('label_centre'))
      $this->grid_limit -= $x_axis->unit() / 2;
    $this->grid_calc_done = true;
  }

  /**
   * Returns the grid points for a Y-axis
   */
  protected function getGridPointsY($axis)
  {
    return $this->y_axes[$axis]->getGridPoints($this->height - $this->pad_bottom);
  }

  /**
   * Returns the grid points for an X-axis
   */
  protected function getGridPointsX($axis)
  {
    return $this->x_axes[$axis]->getGridPoints($this->pad_left);
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function getSubDivsY($axis)
  {
    $a = $this->y_axes[$axis];
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'),
      $this->getOption(['minimum_units_y', $axis]),
      $this->height - $this->pad_bottom,
      $this->getOption(['subdivision_v', $axis]));
  }

  /**
   * Returns the subdivisions for an X-axis
   */
  protected function getSubDivsX($axis)
  {
    $a = $this->x_axes[$axis];
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'), 1,
      $this->pad_left, $this->getOption(['subdivision_h', $axis]));
  }

  /**
   * A function to return the DisplayAxis - subclasses should override
   * to return a different axis type
   */
  protected function getDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    $var = 'main_' . $type . '_axis';
    $main = ($axis_no == $this->{$var});
    return new DisplayAxis($this, $axis, $axis_no, $orientation, $type, $main,
      $this->getOption('label_centre'));
  }

  /**
   * Returns the location of an axis
   */
  protected function getAxisLocation($orientation, $axis_no)
  {
    $x = $this->pad_left;
    $y = $this->pad_top + $this->g_height;
    if($orientation == 'h') {
      $y0 = $this->y_axes[$this->main_y_axis]->zero();
      if($this->show_axis_h && $y0 >= 0 && $y0 <= $this->g_height)
        $y -= $y0;
    } else {
      if($axis_no == 0) {
        $x0 = $this->x_axes[$this->main_x_axis]->zero();
        if($x0 >= 1 && $x0 < $this->g_width)
          $x += $x0;
      } else {
        $x += $this->y_axis_positions[$axis_no];
      }
    }
    return [$x, $y];
  }

  /**
   * Draws bar or line graph axes
   */
  protected function axes()
  {
    $this->calcGrid();
    $axes = $label_group = $axis_text = '';

    foreach($this->x_axes as $axis_no => $axis) {
      if($axis !== null) {
        $display_axis = $this->getDisplayAxis($axis, $axis_no, 'h', 'x');
        list($x, $y) = $this->getAxisLocation('h', $axis_no);
        $axes .= $display_axis->draw($x, $y, $this->pad_left, $this->pad_top,
          $this->g_width, $this->g_height);
      }
    }
    foreach($this->y_axes as $axis_no => $axis) {
      if($axis !== null) {
        $display_axis = $this->getDisplayAxis($axis, $axis_no, 'v', 'y');
        list($x, $y) = $this->getAxisLocation('v', $axis_no);
        $axes .= $display_axis->draw($x, $y, $this->pad_left, $this->pad_top,
          $this->g_width, $this->g_height);
      }
    }

    return $axes;
  }

  /**
   * Returns a set of gridlines
   */
  protected function gridLines($path, $colour, $dash, $fill = null)
  {
    if($path->isEmpty() || $colour == 'none')
      return '';
    $opts = ['d' => $path, 'stroke' => new Colour($this, $colour)];
    if(!empty($dash))
      $opts['stroke-dasharray'] = $dash;
    if(!empty($fill))
      $opts['fill'] = $fill;
    return $this->element('path', $opts);
  }

  /**
   * Returns the SVG fragment for grid background stripes
   */
  protected function getGridStripes()
  {
    if(!$this->getOption('grid_back_stripe'))
      return '';

    // use array of colours if available, otherwise stripe a single colour
    $colours = $this->getOption('grid_back_stripe_colour');
    if(!is_array($colours))
      $colours = [null, $colours];
    $opacity = $this->getOption('grid_back_stripe_opacity');

    $bars = '';
    $c = 0;
    $num_colours = count($colours);
    $rect = [
      'x' => new Number($this->pad_left),
      'width' => new Number($this->g_width),
    ];
    if($opacity != 1)
      $rect['fill-opacity'] = $opacity;
    $points = $this->getGridPointsY($this->main_y_axis);
    $first = array_shift($points);
    $last_pos = $first->position;
    foreach($points as $grid_point) {
      $cc = $colours[$c % $num_colours];
      if($cc !== null) {
        $rect['y'] = $grid_point->position;
        $rect['height'] = $last_pos - $grid_point->position;
        $rect['fill'] = new Colour($this, $cc);
        $bars .= $this->element('rect', $rect);
      }
      $last_pos = $grid_point->position;
      ++$c;
    }
    return $this->element('g', [], null, $bars);
  }

  /**
   * Returns the crosshairs code
   */
  protected function getCrossHairs()
  {
    if(!$this->getOption('crosshairs'))
      return '';

    $ch = new CrossHairs($this, $this->pad_left, $this->pad_top,
      $this->g_width, $this->g_height, $this->x_axes[$this->main_x_axis],
      $this->y_axes[$this->main_y_axis], $this->values->associativeKeys(),
      false, $this->encoding);

    if($ch->enabled())
      return $ch->getCrossHairs();
    return '';
  }

  /**
   * Draws the grid behind the bar / line graph
   */
  protected function grid()
  {
    $this->calcAxes();
    $this->calcGrid();

    // these are used quite a bit, so convert to Number now
    $left_num = new Number($this->pad_left);
    $top_num = new Number($this->pad_top);
    $width_num = new Number($this->g_width);
    $height_num = new Number($this->g_height);

    $back = $subpath = '';
    $grid_group = ['class' => 'grid'];
    $crosshairs = $this->getCrossHairs();

    // if the grid is not displayed, stop now
    if(!$this->show_grid || (!$this->show_grid_h && !$this->show_grid_v))
      return empty($crosshairs) ? '' :
        $this->element('g', $grid_group, null, $crosshairs);

    $back_colour = new Colour($this, $this->getOption('grid_back_colour'));
    if(!$back_colour->isNone()) {
      $rect = [
        'x' => $left_num, 'y' => $top_num,
        'width' => $width_num, 'height' => $height_num,
        'fill' => $back_colour
      ];
      if($this->grid_back_opacity != 1)
        $rect['fill-opacity'] = $this->grid_back_opacity;
      $back = $this->element('rect', $rect);
    }
    $back .= $this->getGridStripes();

    if($this->show_grid_subdivisions) {
      $subpath_h = new PathData();
      $subpath_v = new PathData();
      if($this->show_grid_h) {
        $subdivs = $this->getSubDivsY($this->main_y_axis);
        foreach($subdivs as $y)
          $subpath_v->add('M', $left_num, $y->position, 'h', $width_num);
      }
      if($this->show_grid_v){
        $subdivs = $this->getSubDivsX(0);
        foreach($subdivs as $x)
          $subpath_h->add('M', $x->position, $top_num, 'v', $height_num);
      }

      if(!($subpath_h->isEmpty() && $subpath_v->isEmpty())) {
        $colour_h = $this->getOption('grid_subdivision_colour_h',
          'grid_subdivision_colour', 'grid_colour_h', 'grid_colour');
        $colour_v = $this->getOption('grid_subdivision_colour_v',
          'grid_subdivision_colour', 'grid_colour_v', 'grid_colour');
        $dash_h = $this->getOption('grid_subdivision_dash_h',
          'grid_subdivision_dash', 'grid_dash_h', 'grid_dash');
        $dash_v = $this->getOption('grid_subdivision_dash_v',
          'grid_subdivision_dash', 'grid_dash_v', 'grid_dash');

        if($dash_h == $dash_v && $colour_h == $colour_v) {
          $subpath_h->add($subpath_v);
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h);
        } else {
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h) .
            $this->gridLines($subpath_v, $colour_v, $dash_v);
        }
      }
    }

    $path_v = new PathData;
    $path_h = new PathData;
    if($this->show_grid_h) {
      $points = $this->getGridPointsY($this->main_y_axis);
      foreach($points as $y)
        $path_v->add('M', $left_num, $y->position, 'h', $width_num);
    }
    if($this->show_grid_v) {
      $points = $this->getGridPointsX($this->main_x_axis);
      foreach($points as $x)
        $path_h->add('M', $x->position, $top_num, 'v', $height_num);
    }

    $colour_h = $this->getOption('grid_colour_h', 'grid_colour');
    $colour_v = $this->getOption('grid_colour_v', 'grid_colour');
    $dash_h = $this->getOption('grid_dash_h', 'grid_dash');
    $dash_v = $this->getOption('grid_dash_v', 'grid_dash');

    if($dash_h == $dash_v && $colour_h == $colour_v) {
      $path_v->add($path_h);
      $path = $this->gridLines($path_v, $colour_h, $dash_h);
    } else {
      $path = $this->gridLines($path_h, $colour_h, $dash_h) .
        $this->gridLines($path_v, $colour_v, $dash_v);
    }

    return $this->element('g', $grid_group, null,
      $back . $subpath . $path . $crosshairs);
  }

  /**
   * clamps a value to the grid boundaries
   */
  protected function clampVertical($val)
  {
    return max($this->pad_top, min($this->height - $this->pad_bottom, $val));
  }

  protected function clampHorizontal($val)
  {
    return max($this->pad_left, min($this->width - $this->pad_right, $val));
  }

  /**
   * Sets the clipping path for the grid
   */
  protected function clipGrid(&$attr)
  {
    $clip_id = $this->gridClipPath();
    $attr['clip-path'] = 'url(#' . $clip_id . ')';
  }

  /**
   * Returns the ID of the grid clipping path
   */
  public function gridClipPath()
  {
    if(isset($this->grid_clip_id))
      return $this->grid_clip_id;

    $rect = [
      'x' => $this->pad_left, 'y' => $this->pad_top,
      'width' => $this->width - $this->pad_left - $this->pad_right,
      'height' => $this->height - $this->pad_top - $this->pad_bottom
    ];
    $clip_id = $this->newID();
    $this->defs->add($this->element('clipPath', ['id' => $clip_id], null,
      $this->element('rect', $rect)));
    return ($this->grid_clip_id = $clip_id);
  }

  /**
   * Returns the grid position for a bar or point, or NULL if not on grid
   * $item  = data item
   * $index = integer position in array
   */
  protected function gridPosition($item, $index)
  {
    $offset = $this->x_axes[$this->main_x_axis]->position($index, $item);
    $zero = -0.01; // catch values close to 0

    if($offset >= $zero && floor($offset) <= $this->grid_limit)
      return $this->pad_left + $offset;
    return null;
  }

  /**
   * Returns an X unit value as a SVG distance
   */
  public function unitsX($x, $axis_no = null)
  {
    if($axis_no === null)
      $axis_no = $this->main_x_axis;
    if(!isset($this->x_axes[$axis_no]))
      throw new \Exception('Axis x' . $axis_no . ' does not exist');
    if($this->x_axes[$axis_no] === null)
      $axis_no = $this->main_x_axis;
    $axis = $this->x_axes[$axis_no];
    return $axis->position($x);
  }

  /**
   * Returns a Y unit value as a SVG distance
   */
  public function unitsY($y, $axis_no = null)
  {
    if($axis_no === null)
      $axis_no = $this->main_y_axis;
    if(!isset($this->y_axes[$axis_no]))
      throw new \Exception('Axis y' . $axis_no . ' does not exist');
    if($this->y_axes[$axis_no] === null)
      $axis_no = $this->main_y_axis;
    $axis = $this->y_axes[$axis_no];
    return $axis->position($y);
  }

  /**
   * Returns the $x value as a grid position
   */
  public function gridX($x, $axis_no = null)
  {
    $p = $this->unitsX($x, $axis_no);
    if($p !== null)
      return $this->pad_left + $p;
    return null;
  }

  /**
   * Returns the $y value as a grid position
   */
  public function gridY($y, $axis_no = null)
  {
    $p = $this->unitsY($y, $axis_no);
    if($p !== null)
      return $this->height - $this->pad_bottom - $p;
    return null;
  }

  /**
   * Returns the location of the X axis origin
   */
  protected function originX($axis_no = null)
  {
    if($axis_no === null || $this->x_axes[$axis_no] === null)
      $axis_no = $this->main_x_axis;
    $axis = $this->x_axes[$axis_no];
    return $this->pad_left + $axis->origin();
  }

  /**
   * Returns the location of the Y axis origin
   */
  protected function originY($axis_no = null)
  {
    if($axis_no === null || $this->y_axes[$axis_no] === null)
      $axis_no = $this->main_y_axis;
    $axis = $this->y_axes[$axis_no];
    return $this->height - $this->pad_bottom - $axis->origin();
  }

  /**
   * Converts guideline options to more useful member variables
   */
  protected function calcGuidelines()
  {
    // no guidelines?
    $guidelines = $this->getOption('guideline');
    if(empty($guidelines) && $guidelines !== 0)
      return;

    $this->guidelines = new Guidelines($this, false,
      $this->values->associativeKeys(), $this->datetime_keys);
  }

  public function underShapes()
  {
    $content = parent::underShapes();
    if($this->guidelines !== null)
      $content .= $this->guidelines->getBelow();
    return $content;
  }

  public function overShapes()
  {
    $content = parent::overShapes();
    if($this->guidelines !== null)
      $content .= $this->guidelines->getAbove();
    return $content;
  }
}

